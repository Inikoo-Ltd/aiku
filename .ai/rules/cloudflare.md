---
paths:
  - 'app/Actions/Web/Website/Cloudflare/**'
---

# Cloudflare

## Edge time series daily rows are the last copy of Cloudflare's analytics
website_edge_time_series_records (websites:fetch_edge_time_series, every five minutes for today, the last 4 days too on the first run of each hour; today stored as it stands so far) come from Cloudflare GraphQL Analytics, which the Free plan keeps 31 days (one query spans 30 at most, about 10 zones). Daily rows are only ever upserted: never delete them, never move this series to syncTimeSeriesRecords or a rebuild-from-source redo, and never store a day Cloudflare has partly dropped (FetchWebsiteEdgeTimeSeries starts at the first whole day still kept). Weekly to yearly rows are rebuilt from the daily ones. Each website's cloudflare_token needs Zone > Analytics > Read as well as Cache Purge; websites are grouped by decrypted token before batching zones. A day whose cache-status total and response-status total disagree (by more than 100 requests and 10%) is refused and reported, never stored: on 19 Nov 2026 Cloudflare answered awgifts.eu's 18 Nov with exactly 2,000,000 bypass requests for a day of about 50,000, and other zones with no cache rows at all.
