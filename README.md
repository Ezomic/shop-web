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
| `SHOP_DOWNLOAD_LINK_TTL_DAYS` | Days an emailed download link stays valid (0 disables expiry) |
| `SHOP_DOWNLOAD_MAX_USES` | Times one download link may be used (0 disables the cap) |
| `FLARE_ENABLED` / `FLARE_URL` / `FLARE_KEY` | Error reporting to the self-hosted flare instance |

Stripe/Mollie keys can also be set from `/admin/settings` once logged in as an admin. Those are
stored **encrypted in the `settings` table** and take precedence over the values above. Nothing is
written to `.env` at runtime.

## Testing

```bash
php artisan test
```

## Key concepts

- **Two separate auth guards.** Customers (`customer` guard, `Customer` model) use the storefront
  account area (`/register`, `/login`, `/orders`). Admins (`web` guard, `User` model) use
  `/admin/login`. They never overlap.
- **Cart is session-based**, not a database table — see `App\Services\CartService`.
- **Downloads are expiring signed URLs pinned to the file that was bought.** `Download::url()`
  returns a temporary signed route and each `Download` points at the exact `ProductFile` purchased,
  so replacing a product's file does not change what earlier buyers get. Files live on a private
  `shop` disk and are never exposed publicly. The order page always mints a current link, and both
  customers and admins can re-issue one.
- **Payments are provider-agnostic at the domain level.** `App\Actions\CreateOrderAction` creates
  the order regardless of provider; `App\Services\PaymentService` handles the Stripe/Mollie
  specifics; `App\Actions\CompleteOrderAction` is the single place that marks an order paid,
  generates downloads, and sends the confirmation email — called from both webhook controllers.

Full architecture, routes, models, and gotchas are documented in [`CLAUDE.md`](CLAUDE.md).

## Linear

Tracked under team **THI** (Thijssen Software). Branches follow `feature/thi-{number}-{description}`
or `fix/thi-{number}-{description}`.

## Deploying

Not deployed yet. What a first production release needs, beyond this repo:

1. **A droplet directory** at `/home/deploy/shop-web` on `thijssensoftware-prod`, with the repo
   cloned and a production `.env` (see `.env.example`). `APP_URL` must be the real public URL:
   Stripe and Mollie derive their webhook and return URLs from it.
2. **A queue worker.** `OrderPaidMail` is queued, so without a worker customers never get their
   download links. Provision a `shop-web-queue` systemd unit running
   `php artisan queue:work --tries=3`; the deploy workflow restarts it by that name.
3. **A real mail transport.** `MAIL_MAILER=log` means no order mail leaves the server.
4. **Webhook endpoints registered** with both providers, pointing at `/webhooks/stripe` and
   `/webhooks/mollie`, and the Stripe signing secret saved at `/admin/settings`.
5. **Backups.** `storage/app/shop/` holds the sellable files themselves and
   `database/database.sqlite` holds every order; neither is currently backed up anywhere.
6. **Deploy secrets** on the GitHub repo: `DEPLOY_SSH_HOST`, `DEPLOY_SSH_USER`, `DEPLOY_SSH_KEY`.

`.github/workflows/deploy.yml` performs the deploy but is **`workflow_dispatch` only** — it never
fires on a merge, so nothing happens until the steps above are done and it is run by hand.

## Backups and restore

`php artisan shop:backup` writes two artefacts into `SHOP_BACKUP_PATH` (default
`storage/backups`), and the scheduler runs it nightly at 03:20:

- `database-<stamp>.sqlite` — produced with `VACUUM INTO`, not a file copy. The database runs in
  WAL mode, so copying the file alone can capture a torn state missing whatever is still in the
  `-wal` sidecar.
- `product-files-<stamp>.tar.gz` — the contents of `storage/app/shop`, which *is* the product.

Anything older than `SHOP_BACKUP_KEEP_DAYS` (default 14) is pruned.

### Offsite

Local backups protect against a bad migration or an accidental delete. They do **not** survive
losing the droplet. Set `SHOP_BACKUP_OFFSITE_DISK` to a configured filesystem disk (an S3
compatible bucket) and every artefact is copied there as well.

The droplet itself has DigitalOcean backups enabled, which is a weekly, whole-machine safety net,
not a substitute for this.

### Restoring

```bash
# Database
sudo systemctl stop shop-web-queue
cp storage/backups/database-<stamp>.sqlite database/database.sqlite
chgrp www-data database/database.sqlite && chmod 664 database/database.sqlite
php artisan migrate --force          # only if the backup predates a schema change
sudo systemctl start shop-web-queue

# Product files
tar -xzf storage/backups/product-files-<stamp>.tar.gz -C storage/app --strip-components=0
```

Verify a restore by checking the order count and that a download still streams, not just that the
files exist.
