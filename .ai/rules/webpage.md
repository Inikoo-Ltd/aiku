---
paths:
  - 'app/Actions/Web/Webpage/**'
---

# Webpage

## Storefront HTML is also cached at the Cloudflare edge
Since 31 Oct 2026 (trial on www.awgifts.eu, every live storefront zone from 15:00 UTC) a Cloudflare cache rule stores anonymous storefront HTML at the edge for 10 minutes, with Tiered Cache (Smart topology) on so one copy near our servers serves every Cloudflare location (bypassed by the iris_vua cookie, X-Inertia, Authorization, static extensions and the Varnish pass paths; X-Aiku-Ssr-Fallback and JSON responses are never stored). In Cloudflare rule expressions `http.request.headers.names[*]` keeps the client's case, so header checks must be `any(lower(http.request.headers.names[*])[*] eq "x-inertia")`: a plain `eq "x-inertia"` let an HTTP/1.1 `X-Inertia` request store Inertia JSON as the page. Varnish bans (BreakWebpageCache, BreakWebsiteVarnishCache) do not purge Cloudflare: anonymous visitors can see the old page for up to 10 minutes after a ban. That is deliberate: on the Free plan, purging by tag or host is limited to 5 requests a minute per account, and bans burst to 10k a minute. Any new storefront path that must never be shared between visitors needs adding to that rule's exclusions as well as the VCL pass list. The rules live in `devops/cloudflare/edge-cache-rules.json`; apply or roll back a zone with `devops/cloudflare/edge-cache.sh <zone_id> <host> [--off]`, and change the JSON first, never only the zone. A new storefront zone needs the script run for its storefront host (the canonical host of the website's storefront webpage), or it serves every page from our servers.
