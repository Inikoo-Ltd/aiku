# How it works, and how to operate it

For engineers. Everything below was checked against the code and against production on 7 August 2026.

## The pipeline

```
visit  →  aiku_tsd cookie (touch string)
             │
             ├─ registration ──→ customers.traffic_sources  (raw touch history, source of truth)
             ├─ login ────────→ device cookie merged into the customer's history
             └─ mailshot click → touch appended server-side
                                   │
                                   ▼
                       model_has_traffic_sources  (derived pivot: share per channel)
                                   │
                                   ▼
              traffic_source_stats / traffic_source_campaign_stats  (derived rollups)
                                   │
                                   ▼
                     dashboard, listings, marketing_* SQL views
```

**Only `customers.traffic_sources` is source data.** Everything downstream is derived and can be
rebuilt.

## The touch string

`app/Actions/Iris/CaptureTrafficSource.php` writes the `aiku_tsd` cookie; 120-day lifetime, trimmed
oldest-first when it approaches 3.9 KB. It is in the encrypt-cookies exclusion list
(`app/Http/Middleware/EncryptCookies.php`) so it stays readable as plain text.

Format — segments joined by `|` (a `,` also parses, for legacy values):

```
<unix_ts><abbr><campaign_ref?>
1754563200b21723300927 | 1754649600a
└─ timestamp ─┘│└ campaign ref ┘
               └ channel abbreviation
```

The abbreviation map lives in `TrafficSourcesTypeEnum::abbr()` — `a` organic Google, `b` Google Ads,
`c` organic Bing, `d` Bing Ads, `e` organic Meta, `f` Meta Ads, `g`/`h` Pinterest, `i`/`j` TikTok,
`k`/`l` LinkedIn, `m`/`n` Twitter, `o` YouTube, **`p` newsletter**. Anything ending `-ads` is paid
(`TrafficSourcesTypeEnum::isPaid()`).

Parse with `ParseTrafficSourceTouches` — never by hand. A companion cookie `aiku_lts` holds the last
touch and suppresses a repeat of the identical source.

### Capture rules

`GetTrafficSourceFromUrl` (paid, checked first):

| Channel | Trigger | Campaign ref |
| --- | --- | --- |
| Google Ads | `gclid` present | `gad_campaignid` if present |
| Meta Ads | `fbclid` **and** `utm_medium=paid` | `utm_campaign` |
| Bing Ads | `msclkid` | none, deliberately |

`msclkid` is unique per click; recording it as a campaign made every Bing click its own campaign and
matched no cost row ever.

`GetTrafficSourceFromRefererHeader` (organic fallback) matches the domain of `X-Original-Referer`, or
of the referer, against a fixed list: google, bing, facebook/instagram/threads/messenger/whatsapp,
youtube/youtu.be, linkedin, pinterest, tiktok, twitter/x. No match, no touch.

Campaign refs are sanitised — `|`, `,` and whitespace become `-`, because either separator inside a
ref shatters the cookie on parse.

## The pivot

`model_has_traffic_sources` is polymorphic over Customer / Prospect / Order, with `share`,
`traffic_source_campaign_id`, `attribution_model`, `first_touch_at`, `last_touch_at`.

Shares are assigned by `ProcessTrafficSourceShare` (default model: linear). **Invariant — every
customer's shares sum to exactly 1.00.** Check it after any change:

```sql
WITH per_customer AS (
  SELECT model_id, round(sum(share),2) AS t
  FROM model_has_traffic_sources WHERE model_type='Customer' GROUP BY model_id
) SELECT t, count(*) FROM per_customer GROUP BY t;
```

One row, `1.00`. On 7 Aug 2026 that was `1.00 | 331`.

**Order pivots are not recalculable.** An order carries no touch history of its own — its attribution
is a snapshot `ProcessOrderTrafficSource` takes from the customer at submission. There is nothing to
rebuild it from, which is why `traffic-source:recalculate-attribution` handles customers only.

### Campaign auto-creation is restricted to numeric references

`AttachTrafficSourcesToModel` will only create a campaign row for a reference matching `^\d{1,20}$`.
Touch strings come from a client-controlled cookie; without the restriction a visitor could mint
unlimited campaign rows named whatever they liked. `mailshot-<id>` refs are created server-side by
`RecordEmailClickTouchpoint` with a real name and bypass this path. `reference` is globally unique —
a collision across shops leaves the touch with its channel-level share and no campaign breakdown, by
design.

## The attribution window — the one thing with two copies

Revenue may be claimed by a touch only when it was **invoiced after** that touch and **within N days**
of it. Default 90, `config/marketing.php`, per-shop override at
`settings.marketing.attribution_window_days`. Zero or negative disables the window; causality still
applies.

It is enforced in two places that **must stay in step**:

- `App\Actions\CRM\TrafficSource\WithAttributionWindow` (PHP) — dashboard, both hydrators, email panel.
  Resolved per shop by `GetAttributionWindow`.
- The `marketing_attributable_invoices` SQL view. A view cannot read Laravel config, so the 90-day
  default is duplicated in the migration.

They were separate copies once, and a channel's campaigns could then legitimately out-earn the
channel itself. **If `config/marketing.php` changes, recreate the views.**

Rows with a null `first_touch_at` are legacy and are deliberately left in rather than silently
dropped.

Revenue is measured from **invoices** (`net_amount`, `in_process = false`), matching how
`customer_stats.sales_all` is built — not from orders.

## SQL views

Four, created by `2026_08_06_230000_create_marketing_performance_views.php`,
`2026_08_07_100000_create_marketing_daily_view.php`, and rewritten by
`2026_08_07_150000_apply_attribution_window_to_marketing_views.php`:

| View | Purpose |
| --- | --- |
| `marketing_attributable_invoices` | The window rule, in SQL. Base for the others. |
| `marketing_channel_daily` | Cost, revenue, invoices, registrations per shop/channel/day. |
| `marketing_channel_performance` | All-time rollup with ROAS and cost per registration. |
| `marketing_mailshot_performance` | Sent / opened / clicked / attributed revenue per mailshot. |

> **Standing trap.** These views hold Postgres dependencies on the columns they reference. Any
> migration altering `traffic_source_stats`, `mailshot_stats`, `customer_stats`, `invoices` or
> `traffic_source_costs` **must drop and recreate them**, or it fails at deploy.

## Commands

```bash
php artisan traffic-source:seed
```
Creates the per-shop channel row for every enum case, across all shops, and deletes rows whose type
left the enum. **Mandatory after adding a `TrafficSourcesTypeEnum` case**, and for any new shop.

```bash
php artisan traffic-source:recalculate-attribution [--shop=slug] [--model=linear] [--from=Y-m-d] [--to=Y-m-d] [--dry-run]
```
Rebuilds customer pivot rows from raw touch history. Models: `first_touch`, `last_touch`,
`last_non_direct_touch`, `last_paid_touch`, `linear`. Customers only.
`--model=last_non_direct_touch` currently runs the same code path as `last_touch`.

```bash
php artisan traffic-source:hydrate-campaign-stats [--shop=slug]
```
Rebuilds the campaign stats rollup from attribution and spend.

```bash
php artisan traffic-source:import-costs <file.csv> [--dry-run]
```
Columns: `shop,source,campaign,date,amount,currency`.

```bash
php artisan traffic-source:cost-token <shop> "<name>"
php artisan traffic-source:cost-token <shop> --list
php artisan traffic-source:cost-token --revoke=<id>
```
Sanctum tokens issued against the Shop, so scoping is free and revocation is per holder. Consumed by
`POST /webhooks/traffic-source-costs` (`ReceiveTrafficSourceCostWebhook`).

## Queues

| Job | Queue |
| --- | --- |
| `RecordEmailClickTouchpoint` | `ses-analytics` |
| `SyncCustomerTrafficSourcesFromDevice` | `low-priority` |
| `TrafficSourceHydrateCustomers` | `low-priority` |
| `TrafficSourceCampaignHydrateStats` | `low-priority` |

The hydrators refresh dashboard statistics and must never compete with order processing. Both are
`ShouldBeUnique`, keyed on the traffic source / campaign id. After editing any of these, run
`php artisan horizon:terminate` or workers keep running the old code.

## Files worth knowing

| Path | What |
| --- | --- |
| `app/Actions/Iris/CaptureTrafficSource.php` | Writes the cookie. |
| `app/Actions/CRM/TrafficSource/` | Everything else in the domain. |
| `app/Actions/Traits/HasIrisUserData.php` | Merges the device cookie into a logged-in customer. |
| `app/Actions/Comms/EmailTrackingEvent/StoreEmailTrackingEvent.php` | Mailshot-click guard. |
| `app/Actions/Ordering/Order/ProcessOrderTrafficSource.php` | Order-level snapshot. |
| `app/Actions/CRM/Customer/UI/GetCustomerJourney.php` | The timeline. |
| `app/Actions/UI/Dropshipping/Marketing/ShowMarketingDashboard.php` | Dashboard page. |
| `app/Enums/UI/Marketing/MarketingPeriodEnum.php` | Period options; default `LAST_30`. |
| `config/marketing.php` | The window default. |

## Traps that have already cost a day each

- `DispatchedEmail::mailshot()` is **dead** — its column was dropped in May 2025. Use `sentMailshot()`
  (a `hasOneThrough` via `mailshot_recipients`). The dead relation silently discarded every mailshot
  click for a day.
- **Only mailshot clicks are touches.** Transactional email reaches the same `CLICKED` state; the
  guard in `StoreEmailTrackingEvent` keeps it out. Removing it hands the newsletter channel a share
  of every engaged customer's revenue.
- Touch data cannot be backfilled. Nothing exists before 7 August 2026.
- `assistingTouches()` exists and is tested but has no callers.
