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
- **Marketing attribution**: a customer's credit is split across the channels that touched
  them (`model_has_traffic_sources.share`, summing to 1 per customer), so always multiply by
  `share`; revenue is credited to the ORDER date within the shop's attribution window
  (`shops.settings->marketing->attribution_window_days`, default 90); recording started
  7 Aug 2026, so earlier periods are zero, not "no marketing". Email sending has no invoiced
  cost, it is estimated. Prefer marketing-performance-tool, marketing-trend-tool and
  email-marketing-performance-tool over SQL: they encode all of this.

## Stock history is split across two databases

`org_stock_histories` (one row per SKU per day) and `location_org_stock_histories` (per SKU
per location per day) are downsampled: the operational database keeps the **last three years
daily**, plus **one snapshot per month** for everything older. Every other historic day was
moved to the archive database, same table names, same columns — query it with
`database: archive`.

So:

- A question inside the last three years: `database: aiku`, nothing to think about.
- A month end or year end value from any year (stock valuations, year end accounts): still
  `database: aiku` — the monthly snapshots never left. **This is the common case.**
- A specific non month end day from more than three years ago, or a day-by-day series
  crossing the boundary: query both and union. A given date lives entirely on one side, so
  the two result sets never overlap.
- The monthly keeper is the last date **present** in that month, not necessarily the 28th to
  31st. Do not assume `date = last_day_of_month`; take `max(date)` within the month.

Organisation and group level history (`organisation_stock_histories`, `group_stock_histories`)
is **never** archived and stays daily for every year, so totals, dashboards and any
organisation wide valuation series need the archive at all. Only per SKU detail does.

`org_stocks` and `locations` are mirrored into the archive, so joins for codes and names work
there exactly as they do on aiku (refreshed daily; a SKU renamed today updates there tomorrow).

## Where common things live

| Question | Tables |
| --- | --- |
| Sales / revenue | `invoices`, `invoice_transactions`, `*_time_series_records` |
| Orders & baskets | `orders`, `transactions` (line items, `asset_id`) |
| Customers | `customers`, `web_users` (their logins), `customer_notes` |
| Catalogue | `products`, `assets`, `product_categories` (type: department / sub_department / family) |
| Stock | `org_stocks` (`quantity_in_locations`, `value_in_locations`), `locations`, `location_org_stock` |
| Stock history / valuation over time | `organisation_stock_histories` + `group_stock_histories` (daily, all years, never archived), `org_stock_histories` / `location_org_stock_histories` (per SKU, three years daily + monthly snapshots locally, older days in `database: archive`). Valuation columns come in three methods: `*_lpp_*` (last purchase price), `*_wac_*` (weighted average cost), `*_fifo_*`; the official one is a group setting, do not assume |
| Dispatch | `delivery_notes`, `delivery_note_items`, `picking_sessions` |
| Warehouse labour | `pickings` (`picker_user_id`, `last_picked_at`), `packings` (`packer_user_id`, `queued_at`, `packing_at`, `done_at`). Neither carries `warehouse_id`: join `delivery_notes`. Only trust rows where `created_at >= shops.migrated_to_aiku_on` and `delivery_notes.source_id IS NULL`; earlier work was picked on paper. For per line packing rates exclude `packings.data->>'auto_packed' = 'true'`, those lines were swept up by one click on the note rather than packed one by one |
| Suppliers / purchasing | `suppliers`, `supplier_products`, `purchase_orders`, `stock_deliveries` |
| Marketing (email) | `mailshots` + `mailshot_stats`, `outboxes`, `dispatched_emails`, `email_bulk_runs` |
| Marketing attribution (traffic sources, ROI) | `traffic_sources` (one row per shop per channel `type`: google-ads, meta-ads, organic-google, organic-search, newsletter, marketing-mailshot, email-automated, referral, ai...), `traffic_source_campaigns` (`reference` = ad platform campaign id, `mailshot-N`, or the referring host for referral/organic-search/ai; unique per traffic source for those host channels), `model_has_traffic_sources` (pivot: `model_type` Customer/Order/Prospect, `share` 0-1, `traffic_source_campaign_id`, `first_touch_at`/`last_touch_at`), `traffic_source_costs` (daily ad spend), `traffic_source_stats` / `traffic_source_campaign_stats` (hydrated lifetime totals). Read revenue ONLY through the views: `marketing_channel_daily` (per shop/channel/day: cost, revenue, invoices, registrations), `marketing_channel_performance` (lifetime per channel with roas), `marketing_mailshot_performance` (per mailshot attributed revenue), `marketing_attributable_invoices` (the invoice rows behind them, already windowed and shared) |
| Offers | `offers` + `offer_stats`, `offer_campaigns` |
| Reviews | `reviews`, `shop_review_stats` |
| Web | `websites`, `webpages`, `web_user_requests` (logged-in traffic only) |
| HR | `employees`, `timesheets` (`working_duration` in seconds), `clockings`, `leaves` |
| Staff chat (internal messaging between staff, NOT customer chat) | `staff_conversations` (`type` dm/group, `context_type`/`context_id` when opened from an order or delivery note, `dm_key`), `staff_conversation_participants` (`last_read_at`), `staff_messages` (`user_id` sender, `body`, `media_id`, `parent_id`), `staff_message_reactions`, `staff_message_translations`. Usage/who-chats-with-whom questions: aggregate counts only, never return message bodies. Customer chat is `chat_sessions`/`chat_messages`, a different system |
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
