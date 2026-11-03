# ShopX API

Laravel 13 API for the React shop and administration applications. Requires PHP 8.3+, Composer 2, and MySQL 8.4 in the Docker stack. The Dockerfile supplies the PHP runtime and extensions.

## Local development

Use the parent `th-e-commerce-stack` repository to configure and run the complete application. The API, shop, and admin remain separate Git repositories.

To rebuild an existing local installation after updating this API, run these commands from the parent stack folder:

```sh
./scripts/stack local build api api-web
./scripts/stack local up -d --no-deps api api-web
ecom artisan migrate --force
ecom artisan l5-swagger:generate
```

If scheduler or worker services are enabled, recreate those services with the new API image too. Back up the database before upgrading. Preserve `APP_KEY` and `JWT_SECRET`; do not regenerate them for an existing installation.

**Do not use `ecom setup` to upgrade an existing installation.** Setup intentionally erases its database and persistent volumes. Use the commands above to retain existing data.

`MAIL_MAILER` and `FILESYSTEM_DISK` are supported; existing `MAIL_DRIVER` and `FILESYSTEM_DRIVER` settings remain valid fallbacks. Stripe's `STRIPE_SK` is read through `config/services.php`, including when configuration is cached.

## API documentation

Browse `/api/documentation`. Regenerate the OpenAPI JSON with:

```sh
php artisan l5-swagger:generate
```

Documentation uses PHP attributes supported by L5-Swagger 11. It describes the existing annotated authentication, category, and product endpoints; it is not a complete specification of every route.

## Tests

With PHP 8.3+ and dependencies installed, run the SQLite suite:

```sh
composer install
composer test
```

To test using a disposable MySQL 8.4 database from the API folder:

```sh
docker compose -p shopx-laravel13-test -f docker/compose.test.yaml run --build --rm tests
docker compose -p shopx-laravel13-test -f docker/compose.test.yaml down --volumes
```

This uses a separate Compose project, no published ports, and a memory-backed MySQL data directory. Tests refuse to use any database other than SQLite `:memory:` or MySQL `shopx_test`. Never point tests at application data. The test container also checks platform requirements, OpenAPI generation, and route/configuration caching. CI runs these checks before publishing images.

Coverage includes JWT login/refresh/logout, API errors, product administration/catalog, delivery and collection charges, collection order creation and retrieval, uploads/replacement/deletion, email rendering and transport, CORS, and Stripe intent creation, provider confirmation, amount/currency/order matching, signed webhooks, duplicate events, and disabled PayPal. No real payments or outbound emails are sent.

## Laravel 6 to 13 migration

The upgrade retains the classic kernels and service providers, `/api` routes, JWT response fields, and `Y-m-d H:i:s` model date serialization used by the React clients. No business-schema migration or data reset is required by this upgrade. Test fresh migrations and a restored copy of your existing SQL before deployment.

Framework changes include built-in CORS/proxy/maintenance middleware, modern exception handling and HTTP statuses, Symfony Mailer, Flysystem 3, namespaced seeders/class factories, and PHPUnit 12. Unrouted Laravel UI authentication scaffolding was removed; the application uses its existing JWT and custom password-reset controllers.

Existing demo seeders contain demonstration accounts/data and are optional. They are not required to upgrade or start an installation.

## Checkout verification and remaining work

Stripe confirmation retrieves the PaymentIntent from Stripe and checks its ID, order metadata, GBP amount and received amount against the saved payment. The API stores the intent before returning its client secret. Webhooks require `STRIPE_WEBHOOK_SECRET` and a valid Stripe signature; duplicate events cannot downgrade a confirmed payment. The storefront retains the basket until the API confirms payment and lets the user retry confirmation without charging again.

Set `STRIPE_SK` to the sandbox secret key and configure the matching publishable key in the storefront runtime configuration. Register `POST /api/stripe-payment-status` for `payment_intent.succeeded`, `payment_intent.payment_failed`, `payment_intent.processing`, and `payment_intent.canceled`, then save its signing secret as `STRIPE_WEBHOOK_SECRET`. Missing webhook configuration returns HTTP 503; invalid signatures return HTTP 400. No schema changes are required.

Order creation reloads published products and non-deleted variants from MySQL, validates quantities and currently tracked stock, and calculates item totals and shipping in whole pence. Delivery quotes use saved dimensions, weight, surcharges, allowed zones, package sizes and available rates. Every selected package must match the server's package contents; collection requires an active saved point and one of its available rates. The saved order uses database prices, labels, addresses and charges. Unknown payment methods are rejected. If the submitted total differs, HTTP 422 is returned before creating an order or Stripe intent, so the customer must review the basket and shipping instead of being charged a changed amount. Successful order responses include `subtotal`, `shipping_cost`, and `total_amount`.

Delivery options retain the carrier ID in `id` and add the exact `rate_id`. Submit that `rate_id` with each package selection. Existing selections containing only `shipping_option_id` (or legacy `id`) work when they identify exactly one available rate; ambiguous or outdated selections require a fresh quote. Collection submissions must include the saved point ID and rate ID. No schema migration is needed.

Remaining production work includes persistent recovery after navigating away during payment, and stock reservation/decrement/release across concurrent checkouts (the current quantity check does not reserve inventory). PayPal remains disabled by default (`PAYPAL_ENABLED=false`) because its legacy verification is incomplete. Automated tests mock Stripe. Public staging sandbox checks passed for successful, declined and 3D Secure payments; Stripe success events reported no pending webhook deliveries. After deploying pricing changes, repeat checkout smoke tests.

The pre-existing routes `GET /api/orders/sequence`, `DELETE /api/orders/{order_id}`, `POST /api/orders/filter`, and `POST /api/customers/filter` remain unimplemented. The route regression check records this baseline so the upgrade cannot introduce additional missing actions. Several other legacy customer/address actions are placeholders; feature completeness is separate from framework compatibility.

## Transactional email

Set `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET` (API key) and `MAILGUN_ENDPOINT=api.mailgun.net` for the US region or `api.eu.mailgun.net` for EU. Symfony's official Mailgun transport and HTTP client are installed through Composer. The API sends over HTTPS; SMTP usernames/passwords are not required. Set a verified `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`. `ORDER_NOTIFICATION_EMAIL` selects the shop's administration recipient; customer replies go to the customer's address using Reply-To, while all messages use the configured sender. SMTP remains available for other environments using its existing settings.

Set `ADMIN_URL` to the admin site's base URL so password-reset links match the environment. `MAIL_TEST_RECIPIENT` redirects messages from `send:emails` to one test inbox. Keep staging's scheduler stopped until its recipients and backlog have been reviewed. Never run the bulk command on an imported database just to test mail.

`php artisan send:emails --order=ID` only processes that paid, confirmed order and skips password resets. The default command processes pending reset messages and up to six paid, confirmed orders. Sent timestamps are updated only after the configured transport accepts the message; failures remain pending, are logged without transport secrets, and produce a nonzero exit code. A transport success does not prove inbox delivery. Already marked messages are skipped. The scheduler avoids overlapping scheduled runs. These timestamp checks do not guarantee exactly-once delivery if a process crashes after SMTP acceptance and before the database update.

Regression tests use captured messages, not customer inboxes, and cover sender/Reply-To, recipient redirection, failed transport retries, single-order isolation, payment verification, reset links and HTML escaping.

## Security and license

Report vulnerabilities to [mail@uxblondon.com](mailto:mail@uxblondon.com). Laravel is licensed under the [MIT license](https://opensource.org/licenses/MIT).
