# Shop — Project context for Claude

## What this is

A digital script sales webshop. Customers browse, add scripts to a session cart, check out via
Stripe (card) or Mollie (iDEAL / card), and receive download links by email. Customer accounts
allow re-downloading purchases. Admins manage products, orders, coupons, and settings via `/admin`.

## Stack

- **PHP 8.4, Laravel 13** — Inertia.js + Vue 3 + TypeScript
- **SQLite** — single file at `database/database.sqlite`
- **Tailwind CSS v4** + **shadcn-vue** (New York, neutral, CSS vars, Lucide)
- `stripe/stripe-php ^20` — Stripe Checkout Sessions
- `mollie/laravel-mollie ^4` — Mollie payments (iDEAL)
- `spatie/laravel-translatable ^6` — JSON-column NL/EN translations on `Product`
- `tightenco/ziggy ^2` — `route()` helper in Vue

## Running locally

Site runs under **Herd** at `shop.test`. No `php artisan serve` needed.

```bash
php artisan migrate          # run pending migrations
php artisan db:seed          # seed admin user: admin@shop.test / password
php artisan storage:link     # once after fresh install (already done)
npm run build                # or: npm run dev
```

## Routes

### Public storefront

| Route | Name | Controller |
|-------|------|------------|
| `GET /` | `shop.index` | `ShopController@index` |
| `GET /products/{product:slug}` | `shop.show` | `ShopController@show` |
| `POST /cart/add` | `cart.add` | `CartController@add` |
| `POST /cart/remove` | `cart.remove` | `CartController@remove` |
| `POST /cart/apply-coupon` | `cart.coupon` | `CartController@applyCoupon` |
| `GET /downloads/{token}` | `downloads.get` | `DownloadController@get` (signed URL) |
| `POST /locale/{locale}` | `locale.switch` | closure |

### Auth (guest:customer)

`GET|POST /register`, `GET|POST /login`, `POST /logout`,
`GET|POST /forgot-password`, `GET|POST /reset-password/{token}`

### Customer area (auth:customer)

`GET|POST /checkout`, `GET /checkout/success`, `GET /checkout/cancel`, `GET /checkout/mollie/{order}`,
`GET /orders`, `GET /orders/{order}`,
`GET /email/verify` (notice), `GET /email/verify/{id}/{hash}` (signed), `POST /email/verification-notification`

### Webhook (CSRF-excluded)

`POST /webhooks/stripe`, `POST /webhooks/mollie`

### Admin (auth + admin middleware, prefix `/admin`, name prefix `admin.`)

| What | Routes |
|------|--------|
| Login | `GET|POST /admin/login`, `POST /admin/logout` |
| Products | full CRUD + `POST /admin/products/reorder` |
| Orders | index + show + `POST /admin/orders/{order}/refund` |
| Coupons | full CRUD |
| Settings | `GET|PUT /admin/settings` |

## Architecture

### Models

| Model | Relations | Notes |
|-------|-----------|-------|
| `Product` | `HasMany: ProductFile`, `HasMany: OrderItem` | `spatie/laravel-translatable` on `name`, `description`; `published()` + `ordered()` scopes; `priceFormatted()` |
| `ProductFile` | `BelongsTo: Product` | stored on private `shop` disk |
| `Customer` | `HasMany: Order` | separate `customer` guard; implements `MustVerifyEmail` |
| `Coupon` | `HasMany: Order` | `isValid(): bool`, `discountFor(int): int` |
| `Order` | `BelongsTo: Customer`, `BelongsTo: Coupon`, `HasMany: OrderItem` | `isPaid(): bool`, `totalFormatted()` |
| `OrderItem` | `BelongsTo: Order`, `BelongsTo: Product`, `HasMany: Download` | price/name snapshots at time of purchase |
| `Download` | `BelongsTo: OrderItem`, `BelongsTo: ProductFile` | one row per purchased file; `token` uuid; `url()` returns signed route |
| `User` | — | admin users only; `web` guard |

### Guards

- `web` — `User` model — admin panel
- `customer` — `Customer` model — storefront account area

### Services & Actions (`app/Services/`, `app/Actions/`)

- **`CartService`** — session-backed cart: `add`, `remove`, `contents`, `applyCoupon`, `totals`
- **`PaymentService`** — `createStripeSession(Order)`, `createMolliePayment(Order)`, `refundStripe`, `refundMollie`
- **`CreateOrderAction`** — reads cart + coupon → creates `Order` + `OrderItem` rows. Deliberately does **not** touch `uses_count`
- **`CompleteOrderAction`** — marks order paid, increments the coupon `uses_count`, generates `Download` uuid tokens, sends `OrderPaidMail`. No-ops on an already paid order, which is what keeps webhook replays idempotent
- **`RefundOrderAction`** — refunds through `PaymentService` and releases the coupon use again
- **`ProcessDownloadAction`** — streams the `ProductFile` the `Download` points at, 404s if it is gone, increments `download_count`

### Middleware

- **`SetLocale`** — reads `session('locale')`, validates `en|nl`, calls `app()->setLocale()`; appended to web group
- **`HandleInertiaRequests`** — standard Inertia middleware; appended to web group
- **`EnsureAdmin`** — redirects to `admin.login` if `web` guard has no user; aliased as `admin`

### i18n

Session-based locale (`en` / `nl`). Files: `lang/en/shop.php`, `lang/nl/shop.php`, `lang/en/mail.php`, `lang/nl/mail.php`.
Product `name` and `description` are stored as JSON via `spatie/laravel-translatable` — `$product->name` is locale-aware.

### Payment flow

**Stripe**: `CheckoutController@store` → `CreateOrderAction` → `PaymentService@createStripeSession` → redirect to Stripe → `StripeWebhookController` receives `checkout.session.completed` → `CompleteOrderAction`.

`CheckoutController@success` also completes the order, but only as a shortcut for the customer
already looking at the page. It goes through `PaymentService@stripeSessionStatus`, which returns a
`PaymentStatus` DTO, and it completes nothing unless the session reports `payment_status = paid`
**and** the order belongs to the logged-in customer (403 otherwise). The webhook stays the source
of truth. Never widen this path: skipping either check lets anyone with a session id mark an order
paid without paying (SHOP-5).

**Mollie**: same order creation → `PaymentService@createMolliePayment` → redirect to Mollie → `MollieWebhookController` receives POST with payment ID → fetches status → `CompleteOrderAction`.

Mollie's `redirectUrl` is `checkout.mollie` (`/checkout/mollie/{order}`, behind `auth:customer`).
`CheckoutController@mollieReturn` reads the real payment status and routes accordingly: paid
completes the order and goes to success, cancelled/expired/failed goes to `checkout.cancel`, and
anything still open goes to the success page in its pending state. Do not point `redirectUrl` at a
bare success route again: Mollie uses the same URL for every outcome, so that showed a thank-you
page for payments that never happened (SHOP-6).

Both webhooks excluded from CSRF in `bootstrap/app.php`.

### File storage

Private `shop` disk (`storage/app/shop/`). Files never publicly accessible — always streamed via `ProcessDownloadAction`. Download URLs are Laravel signed routes (no expiry — permanent access).

A `Download` points at the exact `ProductFile` that was bought, so replacing a product's file does
not change what earlier buyers get (SHOP-8). Replacing a file detaches the old `ProductFile` from
the product (`product_id` becomes null) instead of deleting it whenever a `Download` still
references it. `order_items.product_id` is `restrictOnDelete`, so a product that has been ordered
cannot be deleted at all — the admin is told to set it to draft instead.

### Frontend pages (Inertia + Vue)

```
layouts/  ShopLayout, AuthLayout, AdminLayout
pages/
  shop/         Index, Show
  checkout/     Index, Success, Cancel
  orders/       Index, Show
  auth/         Login, Register, ForgotPassword, ResetPassword
  admin/        Login, Settings
  admin/products/  Index, Create, Edit
  admin/orders/    Index, Show
  admin/coupons/   Index, Create, Edit
```

## Environment variables

| Variable | Purpose |
|---|---|
| `STRIPE_KEY` | Stripe publishable key |
| `STRIPE_SECRET` | Stripe secret key — used server-side for Checkout Sessions and refunds |
| `STRIPE_WEBHOOK_SECRET` | Signing secret used by `StripeWebhookController` to verify incoming webhooks |
| `MOLLIE_KEY` | Mollie API key (test or live) |

`Admin\SettingsController` can also write these to `.env` directly from `/admin/settings` and
clears the config cache afterward — no server restart needed.

## Testing

```bash
php artisan test
php artisan test --filter=ProductTest
```

No feature tests exist yet beyond the default Laravel skeleton (`tests/Feature`, `tests/Unit`).
When adding coverage, prioritize:

- `CreateOrderAction` / `CompleteOrderAction` — order + download creation logic
- `CartService::totals()` — coupon discount math (percent vs fixed, expiry, max uses)
- Webhook controllers — signature validation and idempotency (`CompleteOrderAction` no-ops if
  the order is already paid)
- `ProcessDownloadAction` — signed URL rejection, download counting

Use `RefreshDatabase` and factories; no database mocking, per project convention.

## Key gotchas

1. **Two separate auth guards** — customer login uses `auth:customer`, admin login uses `auth` (web). Never mix them.
2. **Download tokens are permanent** — `Download::url()` returns a signed route with no expiry. The customer's order page always has working links.
3. **Price is always in cents** (int). Format with `priceFormatted()` on `Product` or `totalFormatted()` on `Order`.
4. **Webhook CSRF exclusion** — both `/webhooks/stripe` and `/webhooks/mollie` are in the `validateCsrfTokens` except list in `bootstrap/app.php`.
5. **Translatable fields** — `Product::name` and `Product::description` are each their own JSON
   column holding `{"en": "...", "nl": "..."}`, which is the shape spatie expects. Assign them as
   arrays keyed by locale. Reading `$product->name` resolves the current locale; for a specific
   one use `$product->getTranslation('name', 'en')`. There is no `translations` column any more
   (SHOP-14); the old single-column shape silently stored the string `Array` and read back NULL.
6. **Admin seeded user** — `admin@shop.test` / `password` (run `php artisan db:seed`).
7. **Login throttling** — both `POST /login` and `POST /admin/login` use the `throttle:login` limiter (5/min by IP), registered in `AppServiceProvider::boot()`.
8. **Email verification is not a gate** — `Customer` implements `MustVerifyEmail` and registration
   sends the notification, but nothing requires a verified address. Checkout and downloads stay
   open; `ShopLayout` just shows a resend prompt. The `verification.verify` route must exist or
   registration itself 500s, since the notification builds a signed URL for it (SHOP-4).

## Linear

Team: **THI** (Thijssen Software) — `3b1bf7b2-5ff4-4e70-9ca5-a1efb1280839`

Branch format: `feature/thi-{number}-{description}` or `fix/thi-{number}-{description}`

Follow the full workflow in `~/.claude/CLAUDE.md`. See parent context in `~/Projects/shop/CLAUDE.md`.
