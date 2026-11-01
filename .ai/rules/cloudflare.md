---
paths:
  - 'app/Actions/Web/Website/Cloudflare/**'
---

# Cloudflare

## Edge time series daily rows are the last copy of Cloudflare's analytics
website_edge_time_series_records (websites:fetch_edge_time_series, 00:20 UTC) come from Cloudflare GraphQL Analytics, which the Free plan keeps 31 days (one query spans 30 at most, about 10 zones). Daily rows are only ever upserted: never delete them, never move this series to syncTimeSeriesRecords or a rebuild-from-source redo, and never store a day Cloudflare has partly dropped (FetchWebsiteEdgeTimeSeries starts at the first whole day still kept). Weekly to yearly rows are rebuilt from the daily ones. Each website's cloudflare_token needs Zone > Analytics > Read as well as Cache Purge; websites are grouped by decrypted token before batching zones.
