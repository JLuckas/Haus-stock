# Haus Stock Management (POC)

A stock control proof of concept for Haus Hamburgueria, built on Laravel 12, PHP 8.2+ and PostgreSQL.
It polls Saipos for orders. For each order it deducts ingredients from stock using each product's Bill of Materials (BoM). It also records manual deductions, such as employee meals and waste.

## Setup

```bash
composer install
cp .env.example .env        # configure DB_* (PostgreSQL) and SAIPOS_*
php artisan key:generate
php artisan migrate --seed  # seeds a demo catalogue, including a combo (Box Cheeseburger)
```

PostgreSQL needs the `pdo_pgsql` PHP extension. The tests run on in-memory SQLite: `php artisan test`.

## Web app

Build the assets once (`npm install && npm run build`), then serve the app with `php artisan serve` or any PHP web server pointed at `public/`. Sign in at `/login`.

On this Windows machine `php artisan serve` fails with "Failed to listen … (reason: ?)", so start PHP's built-in server directly from the project folder and open http://localhost:8123:

```bash
php -S 127.0.0.1:8123 -t public .claude/dev-router.php
```

- **Language:** the interface is in Brazilian Portuguese (`APP_LOCALE=pt_BR`). The code uses English strings as translation keys (`__('Add product')`), and the Portuguese lives in `lang/pt_BR.json`. Validation, password and pagination messages are in `lang/pt_BR/*.php`, from [laravel-lang](https://laravel-lang.com). `tests/Feature/TranslationTest.php` fails if a new key has no Portuguese translation. Set `APP_LOCALE=en` for English. The CLI output and logs stay in English.
- **Dark mode:** the theme switch in the top bar cycles light → dark → system. The phone menu has the same choice as three buttons. "System" follows the device's setting. The choice is stored in the browser (`localStorage`) and applied before the page paints, so there's no white flash.
- **Responsive:** the layout works from 320 px phones to 3440 px ultrawide monitors.
  - Below 1024 px, the navigation becomes a menu, and tables become one card per row with labeled values. In Blade, this is the `table-stack` class plus a `data-label` on each cell.
  - The content area grows with the screen: up to 1536 px on 2xl screens, 1800 px from 1920 px (`3xl`), and 2400 px from 2560 px (`4xl`).
  - Forms stack their fields on phones.

- **Accounts:** self-registration is disabled. Create staff accounts with `php artisan user:create "Name" email@example.com`, which prompts for a password. `migrate --seed` also creates an admin account, but only in the `local` environment; its login is in `DatabaseSeeder`.
- **Dashboard:** low-stock items, failed orders with a Retry button, products not mapped to stock, recent manual deductions, and when the last Saipos order arrived.
- **Inventory:** list, search and filter items (including "below minimum only"), and add or edit them.
  - **Receive stock** adds goods from a supplier.
  - **Deduct** opens a manual deduction for that item.
  - Current stock can't be typed over in the edit form, so a save can't erase deductions the poller made in the meantime.
- **Products:** set the Saipos SKU, the price, and what each unit sold takes from stock: either a recipe or one standalone item. A recipe can list ingredients, other products (for combos), or both. The edit page shows the total one unit deducts, with every nested level added up, and which combos contain the product.
- **Orders:** filter by stock state (Deducted, Failed, Pending, Cancelled). An order's page shows its items, which ones have no matching product (with a link that registers the product with its SKU prefilled), and the raw Saipos payload.
  - **Retry** reprocesses an order from its stored payload, so it also works for orders outside the poller's lookback window.
- **Deductions:** the manual deduction log, and a form with one-tap reasons such as "Employee meal". A deduction can target an inventory item or a whole product. For a product, the form previews exactly what will leave stock, and the log keeps that breakdown. Each entry records who made it.

## Deploy (Render + Neon)

The app ships as a Docker image (`Dockerfile`): PHP 8.4 with Apache and the Postgres driver, with the CSS/JS built inside the image. The database is Neon Postgres.

1. Push the repository to GitHub. `.env` and `.env.production` are git-ignored and never leave your machine.
2. In Render choose **New → Blueprint** and pick the repository. `render.yaml` defines the web service (Docker, health check on `/up`).
   Alternatively choose **New → Web Service → Docker** and paste `.env.production` under **Environment → Add from .env**.
3. Fill in the secrets Render asks for, copying them from `.env.production`: `APP_KEY`, `DATABASE_URL` (Neon pooled), `DATABASE_URL_UNPOOLED` (Neon direct) and `APP_URL`.
   For the first login also set `ADMIN_NAME`, `ADMIN_EMAIL` and `ADMIN_PASSWORD`.

Every deploy runs `docker/entrypoint.sh` before Apache starts:
- `php artisan migrate --force` over the direct Neon connection. Set `RUN_MIGRATIONS=false` to skip it.
- `user:create … --skip-existing` when `ADMIN_EMAIL` and `ADMIN_PASSWORD` are set. The account is created once and never changed afterwards, so remove `ADMIN_PASSWORD` from Render after you sign in.
- `php artisan optimize`, which caches config, routes and views.

Test the image locally with `docker build -t haus-stock .` and `docker run -p 10000:10000 --env-file .env.production haus-stock`, then open http://localhost:10000.

The Neon database is in São Paulo (`sa-east-1`) and Render's closest region is Virginia, so every query crosses that distance.

## Cost, profit and margin

- **Ingredient cost:** each inventory item has a cost, entered per kg, per L or per unit (how suppliers quote it). It is stored per g / ml / un in `inventory_items.cost_per_unit` (`decimal(14,6)`). An empty cost means "not set", never "free".
- **Product cost** is the sum of quantity × cost_per_unit over the whole recipe, with nested products (combos) and standalone items included. It uses the same resolver as stock deduction.
- **Metrics:** profit = price − cost, margin % = profit ÷ price, and food cost % (CMV) = cost ÷ price.
- **Statuses**, from most to least urgent:

  | Status | Meaning |
  |---|---|
  | `loss` | Price is below cost. |
  | `unmapped` | The recipe can't be resolved. |
  | `incomplete_cost` | Some ingredient has no cost, so the margin is overstated. |
  | `below_threshold` | Profitable, but the margin is under `PROFITABILITY_HEALTHY_MARGIN` (default 65 %). |
  | `no_price` | Price is 0, for example a product sold only inside combos. |
  | `healthy` | None of the above. |

  `flags` lists every condition that applies. A loss is reported even when some costs are missing, because the missing costs could only make it worse.
- **Web:** the Profitability page shows a summary, status filters, and every product worst first. Each product page shows its cost breakdown and margin.
- **API:** `GET /api/reports/profitability` returns JSON for the whole menu. It accepts tokens only, and a browser session doesn't count:
  ```bash
  php artisan api:token admin@example.com --name="Power BI"     # prints the token once
  curl -H "Authorization: Bearer <token>" https://host/api/reports/profitability
  php artisan api:token admin@example.com --name="Power BI" --revoke
  ```
  The logic lives in `App\Services\Finance\ProfitabilityService` (`calculateProductCost`, `getProfitabilityMetrics` and `generateSystemProfitabilityReport`).

## Commands

| Command | Purpose |
|---|---|
| `php artisan saipos:poll` | Long-running poller. Each cycle fetches orders and deducts stock, then sleeps 30s. Flags: `--once`, `--interval=`, `--lookback=`, `--max-memory=` |
| `php artisan stock:deduct "Pão brioche" 2 "Employee Meal"` | Manual deduction of an inventory item, recorded in `manual_deductions` |
| `php artisan stock:deduct "Box Cheeseburger" 1 "Employee Meal" --product` | Manual deduction of a product, including the products inside it |
| `php artisan user:create "Name" email@example.com` | Create a staff login (prompts for the password; `--password-env=VAR` reads it from an environment variable, `--skip-existing` succeeds quietly when the e-mail already has an account) |

In production, run `saipos:poll` under a supervisor such as Supervisor, systemd or a Docker restart policy. On Linux it finishes the current cycle when it gets SIGTERM. It exits by itself above `--max-memory` so the supervisor can start a fresh process.

## How stock is deducted

- **BoM products**: each unit sold deducts `quantity_needed` of every `recipe_ingredients` line. For example, Box 1 deducts 160 g of meat, 2 buns and 50 g of bacon.
- **Products inside products (combos)**: a product can contain other products through `product_components`. For example, Box Cheeseburger = 1 × Cheeseburger + 1 × Batata frita + 1 × Coke Zero 2L. Selling or deducting the box deducts each inner product's own recipe, multiplied by its quantity, at any depth. The inner products are deducted only as part of the box, never as separate deductions. A product can't contain itself, directly or through another product: the form rejects it, and the resolver refuses loops and nesting deeper than 10 levels.
- **Standalone products**: when `products.inventory_item_id` points to a `standalone_product` item (such as Coke Zero 2L), each unit sold deducts exactly 1 unit.
- **Add-ons**: Saipos `choice_items` that have an `integration_code` mapped to a product are deducted too. The quantity is the parent quantity times the choice quantity. Modifiers without a code, such as "no onion", are ignored.
- **All-or-nothing**: an order deducts all of its items in one transaction, or none of them. If a main item's SKU is not registered, the whole order fails. The reason is stored in `orders.last_error`, and the order is retried on the next cycle.
- **At-most-once**: the order row is locked, and `is_stock_processed` is checked inside the transaction. Overlapping poll windows and restarts never deduct twice.
- **Cancelled orders** are never deducted. If an order is cancelled after its stock was deducted, a warning is logged and the stock is not returned automatically.
- **Stock can go negative.** The sale already happened at the POS, so a negative balance points to a missing purchase entry.
- **Low stock**: when an item falls below `minimum_stock`, a warning is logged.

## Saipos integration status

These parts follow the [Saipos Order API docs](https://saipos-docs-order-api.readme.io/reference/introducao):
- `POST /auth` with `idPartner` and `secret`. The token is cached for 47h; the docs say it is valid for 2 days.
- The token is sent in the `Authorization` header, with no `Bearer` prefix.
- `GET /order?cod_store=&order_id=`.

**These parts still need confirmation from Saipos:**

1. **The order-listing endpoint.** The public Order API is built for partners that *send* orders to Saipos, and it notifies them through webhooks. It documents no endpoint that lists a store's sales. Set `SAIPOS_ORDERS_ENDPOINT` to the endpoint Saipos provides, and adjust `config/saipos.php` → `list_query`. Until it is set, the poller stops with a clear error.
2. **The `/auth` response body.** The docs do not show it. The code accepts `token`, `access_token` or a raw string.
3. **Item codes in `GET /order`.** The documented example shows only `desc_sale_item`, with no `integration_code`. Products are matched by integration code, so the listing (or detail) payload must include it.
