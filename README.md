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

Coverage includes JWT login/refresh/logout, API errors, product administration/catalog, delivery and collection charges, collection order creation and retrieval, uploads/replacement/deletion, email rendering and transport, CORS, and a mocked Stripe PaymentIntent request. No real payments or outbound emails are sent.

## Laravel 6 to 13 migration

The upgrade retains the classic kernels and service providers, `/api` routes, JWT response fields, and `Y-m-d H:i:s` model date serialization used by the React clients. No business-schema migration or data reset is required by this upgrade. Test fresh migrations and a restored copy of your existing SQL before deployment.

Framework changes include built-in CORS/proxy/maintenance middleware, modern exception handling and HTTP statuses, Symfony Mailer, Flysystem 3, namespaced seeders/class factories, and PHPUnit 12. Unrouted Laravel UI authentication scaffolding was removed; the application uses its existing JWT and custom password-reset controllers.

Existing demo seeders contain demonstration accounts/data and are optional. They are not required to upgrade or start an installation.

## Outstanding application issues

This framework upgrade does not redesign checkout or establish production payment verification. Existing checkout handlers accept client-supplied payment details, Stripe confirmation trusts the supplied success status, and Stripe/PayPal webhook handlers lack signature verification and reference a debug `test` table absent from fresh migrations. Resolve these issues and run provider sandbox end-to-end tests before production use. The automated Stripe test mocks the provider; it does not prove a live payment succeeds.

The pre-existing routes `GET /api/orders/sequence`, `DELETE /api/orders/{order_id}`, `POST /api/orders/filter`, and `POST /api/customers/filter` remain unimplemented. The route regression check records this baseline so the upgrade cannot introduce additional missing actions. Several other legacy customer/address actions are placeholders; feature completeness is separate from framework compatibility.

## Security and license

Report vulnerabilities to [mail@uxblondon.com](mailto:mail@uxblondon.com). Laravel is licensed under the [MIT license](https://opensource.org/licenses/MIT).
