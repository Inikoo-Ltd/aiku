<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Resource;

#[Description('How Aiku data is organised: entity hierarchy, where sales numbers come from, table naming conventions, and the traps that make a technically valid query return wrong numbers. Read this before writing SQL against Aiku.')]
class AikuDataGuideResource extends Resource
{
    public function handle(Request $request): Response
    {
        return Response::text(<<<'GUIDE'
# Aiku data guide

PostgreSQL. Read-only access. Use the `describe-tables` tool for exact columns —
this guide covers what the schema alone does NOT tell you.

## Hierarchy

Group (1) → Organisation (many) → Shop / Warehouse (many).

Most tables carry `group_id`, `organisation_id` and/or `shop_id` denormalised, so you
can scope directly without joining up the tree. Check which exist with
`describe-tables` before assuming.

There is also a **masters layer** (`master_assets`, `master_product_categories`,
`master_shops`): group-level catalogue definitions that shops mirror into their own
`assets` / `products` / `product_categories`. Masters have `group_id` but no
`organisation_id`/`shop_id`. Similarly `stocks`/`stock_families` are group-level and
`org_stocks`/`org_stock_families` are the per-organisation mirror.

## Sales numbers — read this before any revenue query

**Hard sales truth lives in `invoices` and `invoice_transactions`.** Everything else
is aggregated from them.

Pre-aggregated time series exist per entity grain and are the fast, correct way to
answer "how much did X sell over period Y". The pattern is always a pair:

  <entity>_time_series          (parent: entity FK, frequency, from, to)
  <entity>_time_series_records  (one row per period: sales_external, orders,
                                 invoices, refunds, customers_invoiced, ...)

Available at: shop, asset, product_category, collection, variant, org_stock,
org_stock_family, trade_unit, trade_unit_family, invoice_category, warehouse,
organisation, group (parent only — group records table does not exist yet).

### Rules

- **`sales_external` is the sales figure to use.** `_org_currency_` and
  `_grp_currency_` variants exist for cross-currency aggregation: use
  `sales_org_currency_external` when totalling across shops in one organisation, and
  `sales_grp_currency_external` when totalling across organisations.
- **`*_intervals` tables no longer exist.** The legacy `*_sales_intervals` /
  `*_ordering_intervals` tables were dropped; time series tables are the only
  source for period metrics.
- `frequency` on the **parent** table is the full word (`daily`, `weekly`, `monthly`,
  `quarterly`, `yearly`); on the **records** table it is `char(1)` (`d`, `w`, `m`,
  `q`, `y`). Both tables have a `frequency` column, so qualify it in joins or
  Postgres will error on ambiguity.
- Filter periods on `records.from` between your date bounds.
- Daily records are the safe grain to sum over an arbitrary date range.

## Traps that produce wrong-but-plausible results

- **Soft deletes.** Almost every business table has `deleted_at`. Always add
  `WHERE deleted_at IS NULL` unless you specifically want deleted rows — no ORM is
  filtering for you in raw SQL.
- **Orders in `creating` state are baskets, not sales.** Exclude `state` in
  ('creating', 'cancelled') when counting real orders.
- **Invoices**: `in_process = true` means not finalised. `type` is 'invoice' or
  'refund' — refunds are separate rows, not negative invoices.
- **`master_assets.price` and `.rrp` are deprecated scalars** holding a EUR value.
  Real prices are in the `master_prices` / `master_rrps` jsonb columns keyed by
  currency. Never report the scalar as a price.
- **Case-insensitive text search**: plain `ILIKE` fails on some columns because of a
  nondeterministic collation. Use `WHERE col COLLATE "C" ILIKE '%x%'`.
- **Polymorphic columns store short aliases**, not class names: `'Customer'`,
  `'Employee'`, `'Shop'` — not `App\Models\...`.
- Money columns are numeric; always state which currency you are reporting.

## Where common things live

| Question | Tables |
| --- | --- |
| Sales / revenue | `invoices`, `invoice_transactions`, `*_time_series_records` |
| Orders & baskets | `orders`, `transactions` (line items, `asset_id`) |
| Customers | `customers`, `web_users` (their logins), `customer_notes` |
| Catalogue | `products`, `assets`, `product_categories` (type: department / sub_department / family) |
| Stock | `org_stocks` (`quantity_in_locations`, `value_in_locations`), `locations`, `location_org_stock` |
| Dispatch | `delivery_notes`, `delivery_note_items`, `picking_sessions` |
| Suppliers / purchasing | `suppliers`, `supplier_products`, `purchase_orders`, `stock_deliveries` |
| Marketing | `mailshots` + `mailshot_stats`, `outboxes`, `dispatched_emails`, `email_bulk_runs` |
| Offers | `offers` + `offer_stats`, `offer_campaigns` |
| Reviews | `reviews`, `shop_review_stats` |
| Web | `websites`, `webpages`, `web_user_requests` (logged-in traffic only) |
| HR | `employees`, `timesheets` (`working_duration` in seconds), `clockings`, `leaves` |
| Audit / notes | `audits` (customer notes live here: `event = 'customer_note'`, text in `new_values->>'note'`) |

## Suggested workflow

1. `describe-tables` with `search` to find the table.
2. `describe-tables` with `tables: [...]` for exact columns and foreign keys.
3. Prefer a purpose-built tool (shop sales, stock levels, top products...) when one
   fits the question — they already encode these rules.
4. Drop to SQL only for questions no tool covers. Always `LIMIT`, always handle
   `deleted_at`.
GUIDE);
    }
}
