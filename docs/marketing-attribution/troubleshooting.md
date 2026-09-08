# Troubleshooting runbook

Symptom → cause → fix. Every query below is read-only unless marked otherwise.

---

## ROAS or CAC shows a dash

**Cause.** No advertising spend for that shop, channel and period. ROAS and CAC are only computed
when spend is greater than zero — `null` renders as a dash. This is the state of every shop as of
7 August 2026: `traffic_source_costs` is empty.

```sql
SELECT shop, channel, cost, revenue, roas FROM marketing_channel_performance WHERE cost > 0;
```

**Fix.** Import spend. Either the automated route (Google Ads script + a shop token) or a one-off CSV:

```bash
php artisan traffic-source:cost-token uk "AW Advantage"
```

```bash
php artisan traffic-source:import-costs storage/app/spend.csv --dry-run
```

Then rebuild the campaign rollup:

```bash
php artisan traffic-source:hydrate-campaign-stats --shop=uk
```

If spend *is* present but ROAS is still a dash, check the period: the dashboard compares a period's
revenue against **that period's** spend. Spend dated outside the selected range does not count.

---

## A shop captures nothing at all

**Cause.** Almost always missing traffic source rows. A shop with no `traffic_sources` rows has
nothing to attach touches to, so capture writes the cookie and the attachment then silently drops it.
New shops and newly added `TrafficSourcesTypeEnum` cases both cause this.

Rather than hard-code the expected count, ask which enum cases have no row at all — a threshold
written down here goes stale the moment a case is added, and a stale one reports every shop as
healthy while a whole channel is being dropped. That is exactly what happened with `instagram-ads`
between 8 and 10 August 2026.

```sql
SELECT s.slug, count(ts.id) AS sources
FROM shops s LEFT JOIN traffic_sources ts ON ts.shop_id = s.id
GROUP BY 1 ORDER BY 2 LIMIT 20;
```

Every shop should show the same number, and that number should equal
`count(TrafficSourcesTypeEnum::cases())` — 21 as of 10 August 2026. To find the missing channel
rather than just the count:

```sql
SELECT DISTINCT type FROM traffic_sources ORDER BY type;
```

Compare that against the enum. A case present in the enum and absent here is a channel whose
touches are being silently discarded.

**Fix.**

```bash
php artisan traffic-source:seed
```

Idempotent, runs across every shop, and also removes rows whose type has left the enum.

**If the counts are fine**, the problem is upstream in capture — see the next two entries.

---

## Paid clicks land as organic, or not at all

**Cause.** The landing URL is missing the click id. Capture checks paid parameters first and falls
back to the referring domain, so a stripped `gclid` becomes organic Google.

**Check.** Look at a real landing URL from the ad. It must carry `gclid` (Google), `msclkid` (Bing),
or `fbclid` **plus** `utm_medium=paid` (Meta). Meta is the common failure — any other `utm_medium`
and the click is not recognised as paid.

**Fix.** Turn on auto-tagging in the ad platform; stop any redirect or landing-page rule that strips
query parameters. See [utm-campaign-naming.md](utm-campaign-naming.md).

---

## Mailshot clicks are not attributed

**Causes, in the order worth checking:**

1. **The mailshot type is excluded.** Only `newsletter`, `marketing` and `invite` mailshots appear in
   `marketing_mailshot_performance`, and only mailshot clicks create touches at all — transactional
   email is deliberately excluded by the guard in `StoreEmailTrackingEvent`.
2. **`DispatchedEmail::mailshot()` was used instead of `sentMailshot()`.** The former is dead; its
   column was dropped in May 2025 and it returns null, which silently discards every click. This has
   already happened once and cost a day.
3. **The `ses-analytics` queue is stalled**, so `RecordEmailClickTouchpoint` never ran.

```sql
SELECT m.id, m.subject, ms.number_dispatched_emails_state_clicked AS clicks,
       (SELECT count(*) FROM traffic_source_campaigns c WHERE c.reference = 'mailshot-'||m.id) AS campaign_row
FROM mailshots m JOIN mailshot_stats ms ON ms.mailshot_id = m.id
WHERE ms.number_dispatched_emails_state_clicked > 0
ORDER BY m.id DESC LIMIT 20;
```

Clicks recorded but `campaign_row` at 0 points at cause 2 or 3.

**Fix.** For a stalled queue, check Horizon and run `php artisan horizon:terminate` — workers keep
running old code after a deploy. Attributed *revenue* of zero on a mailshot with clicks is usually
correct and not a fault: see the next entry.

---

## Numbers dropped sharply after a deploy

**Cause.** Almost certainly the attribution window landing. On 7 August 2026 it took production
revenue from **€652,672 to €1,439**. That was the correction, not a regression: before it, a channel
was credited with everything its customers had *ever* spent, including years of trade predating the
touch. One newsletter click had claimed €22,410 of 2022 trade.

**Confirm it is the window and not a fault.**

```sql
SELECT count(*) AS pivots,
       count(*) FILTER (WHERE first_touch_at IS NULL) AS legacy_no_dates
FROM model_has_traffic_sources WHERE model_type='Customer';
```

Then pick a customer whose revenue vanished and open their marketing journey. If every invoice
predates the first touch, or falls more than 90 days after the last one, the drop is correct.

**Fix.** None needed. If a shop genuinely has a longer buying cycle, widen its window rather than
disabling the rule:

```sql
-- read the current value
SELECT slug, settings->'marketing'->>'attribution_window_days' FROM shops WHERE slug='uk';
```

Set `settings.marketing.attribution_window_days` on the shop. `0` disables the window entirely and
restores the old inflated behaviour — only ever useful as a comparison.

> If you change the default in `config/marketing.php`, you **must** recreate the SQL views. The views
> cannot read Laravel config and carry their own copy of the 90.

---

## Shares do not sum to 1.00

**Cause.** A partially applied recalculation, or pivot rows written by two paths at once.

```sql
WITH per_customer AS (
  SELECT model_id, round(sum(share),2) AS t
  FROM model_has_traffic_sources WHERE model_type='Customer' GROUP BY model_id
) SELECT t, count(*) FROM per_customer GROUP BY t ORDER BY 1;
```

Healthy output is a single row, `1.00`. On 7 August 2026: `1.00 | 331`.

To see who is broken:

```sql
SELECT model_id, sum(share) FROM model_has_traffic_sources
WHERE model_type='Customer' GROUP BY 1 HAVING round(sum(share),2) <> 1.00;
```

**Fix.** Rebuild from raw touch history — always dry-run first:

```bash
php artisan traffic-source:recalculate-attribution --shop=uk --dry-run
```

```bash
php artisan traffic-source:recalculate-attribution --shop=uk
```

```bash
php artisan traffic-source:hydrate-campaign-stats --shop=uk
```

Then re-run the invariant query. Note that **order** pivots are not recalculable — an order carries
no touch history, only a snapshot taken at submission. Only customer rows are covered by the check
and by the command.

---

## A migration fails on deploy, complaining about a view

**Cause.** The four `marketing_*` views hold Postgres dependencies on the columns they select. Any
migration altering `traffic_source_stats`, `mailshot_stats`, `customer_stats`, `invoices` or
`traffic_source_costs` is blocked by them.

**Fix.** Drop the views at the top of the migration and recreate them at the end. Copy the
definitions from `2026_08_07_150000_apply_attribution_window_to_marketing_views.php`, which holds the
current version of all four.

---

## Rollback

### Turning attribution off in a hurry

There is no kill switch, and none is needed — the pieces are independently disableable, and none of
them blocks trading.

| To stop | Do this | Effect |
| --- | --- | --- |
| Revenue attribution distorting reports | Set `settings.marketing.attribution_window_days` to `0` on the shop | Reverts to the pre-window behaviour: causality still applies, but a touch claims the customer's whole history. Reversible instantly. |
| Automated spend arriving | `php artisan traffic-source:cost-token --revoke=<id>` | The ad-platform script gets a 401. No data lost; already-imported rows stay. |
| Stats jobs consuming workers | Pause the `low-priority` and `ses-analytics` queues in Horizon | Touches keep being *recorded*; only the rollups go stale. Resume and re-hydrate to catch up. |
| Everything, at the source | Comment out the `CaptureTrafficSource::run()` call in `app/Actions/Traits/HasIrisUserData.php` and deploy | No new touch cookies are written. Existing data untouched. |

Do **not** truncate `model_has_traffic_sources` as a rollback move. It is cheaper to zero the window.

### What would actually be lost

| Data | Recoverable? |
| --- | --- |
| `customers.traffic_sources` — raw touch history | **No. This is the source of truth.** Nothing rebuilds it. Never clear this column. |
| `model_has_traffic_sources` for Customers | Yes — `traffic-source:recalculate-attribution`. |
| `model_has_traffic_sources` for Orders | **No.** An order's attribution is a snapshot taken at submission from a customer history that may since have changed. There is nothing to rebuild it from. |
| `traffic_source_stats`, `traffic_source_campaign_stats` | Yes — the hydrators, then `traffic-source:hydrate-campaign-stats`. |
| `traffic_source_campaigns` | Partly. Numeric refs are recreated from touches on recalculation; the human names imported with spend are not. |
| `traffic_source_costs` | Only from the ad platform. Re-run the ingestion script for the affected dates or re-import the CSV; the upsert is keyed on source + campaign + date, so re-running is safe. |
| The four `marketing_*` views | Yes — re-run the view migration. |

### Restoring after a rollback

```bash
php artisan traffic-source:seed
```

```bash
php artisan traffic-source:recalculate-attribution --dry-run
```

```bash
php artisan traffic-source:recalculate-attribution
```

```bash
php artisan traffic-source:hydrate-campaign-stats
```

```bash
php artisan horizon:terminate
```

Then check the shares-sum-to-1.00 invariant above before telling anyone the numbers are back.
