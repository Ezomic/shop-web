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

`OrderPaidMail` is **queued**, so a local run needs `php artisan queue:work` (or `composer dev`,
which starts one) before order emails go anywhere. In production this is a systemd unit named
`shop-web-queue`; without it customers never receive their download links.

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
| `GET /terms` `GET /privacy` `GET /contact` | `legal.*` | `LegalController` |

### Auth (guest:customer)

`GET|POST /register`, `GET|POST /login`, `POST /logout`,
`GET|POST /forgot-password`, `GET|POST /reset-password/{token}`

### Customer area (auth:customer)

`GET|POST /checkout`, `GET /checkout/success`, `GET /checkout/cancel`, `GET /checkout/mollie/{order}`,
`GET /orders`, `GET /orders/{order}`, `POST /orders/{order}/downloads/{download}/reissue`,
`GET /email/verify` (notice), `GET /email/verify/{id}/{hash}` (signed), `POST /email/verification-notification`

### Webhook (CSRF-excluded)

`POST /webhooks/stripe`, `POST /webhooks/mollie`

### Admin (auth + admin middleware, prefix `/admin`, name prefix `admin.`)

| What | Routes |
|------|--------|
| Login | `GET|POST /admin/login`, `POST /admin/logout` |
| Products | full CRUD + `POST /admin/products/reorder` |
| Orders | index + show + `POST /admin/orders/{order}/refund`, `POST /admin/orders/{order}/resend`, `POST /admin/orders/{order}/downloads/{download}/reissue` |
| Coupons | full CRUD |
| Settings | `GET|PUT /admin/settings` |

## Architecture

### Models

| Model | Relations | Notes |
|-------|-----------|-------|
| `Product` | `HasMany: ProductFile`, `HasMany: OrderItem` | `spatie/laravel-translatable` on `name`, `description`; `published()` + `ordered()` scopes; `priceFormatted()` |
| `ProductFile` | `BelongsTo: Product` | stored on private `shop` disk |
| `Customer` | `HasMany: Order` | separate `customer` guard; implements `MustVerifyEmail` |
| `Setting` | — | encrypted key/value store behind `PaymentCredentials` |
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
- **`CompleteOrderAction`** — marks order paid, increments the coupon `uses_count`, generates `Download` uuid tokens, queues `OrderPaidMail`. No-ops on an already paid order, which is what keeps webhook replays idempotent
- **`AllocateInvoiceNumberAction`** — hands out the next `YYYY-NNNN` invoice number. Only paid orders get one, so the sequence has no gaps; the unique index plus a retry is what makes it safe under concurrent webhooks
- **`InvoiceRenderer`** — renders the invoice PDF on demand from the order snapshot. Invoices are never stored as files, so there is nothing extra to back up and no way for a later product edit to change an issued invoice
- **`WithdrawalConsent`** — the consent wording plus a version. EU buyers of downloadable content keep a 14 day right of withdrawal unless they expressly consent to immediate supply and acknowledge losing it, so checkout refuses without the box and the order stores the exact text agreed to, not a boolean (SHOP-22)
- **`VatThresholdMonitor`** — sums paid cross-border orders for the year and remembers which thresholds have been announced, so `shop:check-vat-threshold` warns once per threshold per year rather than every week
- **`RefundOrderAction`** — refunds through `PaymentService` and releases the coupon use again
- **`ProcessDownloadAction`** — streams the `ProductFile` the `Download` points at, 404s if it is gone, increments `download_count`

### Middleware

- **`SetLocale`** — reads `session('locale')`, validates `en|nl`, calls `app()->setLocale()`; appended to web group
- **`HandleInertiaRequests`** — standard Inertia middleware; appended to web group
- **`EnsureAdmin`** — redirects to `admin.login` if `web` guard has no user; aliased as `admin`.
  The admin routes use **only** this alias, not `auth`: `auth` runs first and would bounce admins
  to the customer login page instead.

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
| `SHOP_VAT_RATE` | VAT percentage extracted from the VAT-inclusive price (default 21) |
| `SHOP_SUPPLIER_*` | Name, address, VAT and KvK number printed on invoices |
| `SHOP_HOME_COUNTRY` | Country recorded when the provider gives none, flagged as `fallback` (default NL) |
| `SHOP_VAT_THRESHOLD` / `_WARNING` / `_NOTIFY` | Cross-border sales watch in cents; warns once per threshold per year (SHOP-20) |
| `FLARE_ENABLED` / `FLARE_URL` / `FLARE_KEY` | Error reporting to the self-hosted flare instance (`php artisan flare:test`) |
| `SHOP_DOWNLOAD_LINK_TTL_DAYS` | Days an emailed download link stays valid (0 disables expiry) |
| `SHOP_DOWNLOAD_MAX_USES` | Times a single download link may be used (0 disables the cap) |
| `STRIPE_KEY` | Stripe publishable key |
| `STRIPE_SECRET` | Stripe secret key — used server-side for Checkout Sessions and refunds |
| `STRIPE_WEBHOOK_SECRET` | Signing secret used by `StripeWebhookController` to verify incoming webhooks |
| `MOLLIE_KEY` | Mollie API key (test or live) |

Payment credentials can also be set from `/admin/settings`. They are stored **encrypted in the
`settings` table**, not written to `.env` (SHOP-12): the old behaviour rewrote the env file at
runtime, which cannot work under `config:cache` and fails on a deploy-owned filesystem. Read them
through `PaymentCredentials`, which prefers the stored value and falls back to the environment.
Never read `config('services.stripe.secret')` directly in new code.

## Testing

```bash
php artisan test
php artisan test --filter=ProductTest
```

The suite covers the money paths end to end: order creation and completion, coupon lifecycle and
discount math, both webhooks (signature rejection, tampering, idempotency), the checkout return
paths for both providers, the download lifecycle, admin products/orders/coupons/settings, and auth
including throttling and guard separation.

Factories exist for every model. Use `RefreshDatabase` and factories; no database mocking, per
project convention. External payment APIs are the one thing that is mocked — bind a Mockery double
for `PaymentService` (or `PaymentCredentials`) into the container rather than reaching for Stripe
or Mollie in a test.

## Key gotchas

1. **Two separate auth guards** — customer login uses `auth:customer`, admin login uses `auth` (web). Never mix them.
2. **Download links expire and are capped** — `Download::url()` returns a *temporary* signed route
   (`shop.downloads.link_ttl_days`, default 14) and `ProcessDownloadAction` refuses a link past
   `shop.downloads.max_uses` (default 10). Both are disabled by setting them to 0. The order page
   mints a fresh link on every render, so only emailed links go stale; customers can also re-issue
   a link themselves and admins can regenerate one or resend the whole order email (SHOP-10).
3. **Price is always in cents** (int). Format with `priceFormatted()` on `Product` or `totalFormatted()` on `Order`.
   Prices are **VAT inclusive**: `Product::price` is the gross the customer pays, and `VatCalculator`
   splits it into net and VAT rather than adding anything on top. `CreateOrderAction` shares the
   discount across lines by largest remainder, so `sum(items.net_price + items.vat_amount)` always
   equals `orders.total`. The rate is `SHOP_VAT_RATE`, snapshotted per order and per item so a later
   rate change never rewrites history (SHOP-15).
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
