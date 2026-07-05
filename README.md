# Shop

A digital script sales webshop built with Laravel, Inertia.js, and Vue 3. Customers browse
scripts, check out with Stripe or Mollie (iDEAL), and receive signed download links by email.
Registered customers can view order history and re-download purchases at any time. Admins manage
products, orders, coupons, and payment settings from a dedicated `/admin` panel.

## Stack

- PHP 8.4, Laravel 13
- Inertia.js + Vue 3 + TypeScript
- Tailwind CSS v4 + shadcn-vue (New York, neutral, CSS variables, Lucide icons)
- SQLite
- Stripe (`stripe/stripe-php`) and Mollie (`mollie/laravel-mollie`) for payments
- `spatie/laravel-translatable` for NL/EN product content
- `tightenco/ziggy` for `route()` in Vue

## Requirements

- PHP 8.4+, Composer
- Node 20+, npm
- SQLite

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate
php artisan db:seed      # creates admin@shop.test / password
php artisan storage:link

npm run build            # or: npm run dev
```

Add your Stripe and Mollie credentials to `.env` (see [Environment variables](#environment-variables)).

The app is served via [Herd](https://herd.laravel.com) at `shop.test`, or run
`php artisan serve` for a local dev server.

## Environment variables

| Variable | Purpose |
|---|---|
| `STRIPE_KEY` | Stripe publishable key |
| `STRIPE_SECRET` | Stripe secret key, used server-side to create Checkout Sessions and refunds |
| `STRIPE_WEBHOOK_SECRET` | Signing secret for verifying `POST /webhooks/stripe` |
| `MOLLIE_KEY` | Mollie API key (test or live) |

Stripe/Mollie keys can also be edited from `/admin/settings` once logged in as an admin — this
writes directly to `.env` and clears the config cache.

## Testing

```bash
php artisan test
```

## Key concepts

- **Two separate auth guards.** Customers (`customer` guard, `Customer` model) use the storefront
  account area (`/register`, `/login`, `/orders`). Admins (`web` guard, `User` model) use
  `/admin/login`. They never overlap.
- **Cart is session-based**, not a database table — see `App\Services\CartService`.
- **Downloads are permanent signed URLs.** `App\Models\Download::url()` returns a Laravel signed
  route with no expiry; the token itself is the credential. Files are served from a private
  `shop` disk and never exposed via a public URL.
- **Payments are provider-agnostic at the domain level.** `App\Actions\CreateOrderAction` creates
  the order regardless of provider; `App\Services\PaymentService` handles the Stripe/Mollie
  specifics; `App\Actions\CompleteOrderAction` is the single place that marks an order paid,
  generates downloads, and sends the confirmation email — called from both webhook controllers.

Full architecture, routes, models, and gotchas are documented in [`CLAUDE.md`](CLAUDE.md).

## Linear

Tracked under team **THI** (Thijssen Software). Branches follow `feature/thi-{number}-{description}`
or `fix/thi-{number}-{description}`.
