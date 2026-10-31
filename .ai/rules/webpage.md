---
paths:
  - 'app/Actions/Web/Webpage/**'
---

# Webpage

## Storefront HTML is also cached at the Cloudflare edge (trial: www.awgifts.eu)
Since 31 Oct 2026 a Cloudflare cache rule on the awgifts.eu zone stores anonymous storefront HTML at the edge for 120 s (bypassed by the iris_vua cookie, X-Inertia, Authorization, static extensions and the Varnish pass paths; X-Aiku-Ssr-Fallback and JSON responses are never stored). In Cloudflare rule expressions `http.request.headers.names[*]` keeps the client's case, so header checks must be `any(lower(http.request.headers.names[*])[*] eq "x-inertia")`: a plain `eq "x-inertia"` let an HTTP/1.1 `X-Inertia` request store Inertia JSON as the page. Varnish bans (BreakWebpageCache, BreakWebsiteVarnishCache) do not purge Cloudflare: anonymous visitors can see the old page for up to 120 s after a ban. That is deliberate: on the Free plan, purging by tag or host is limited to 5 requests a minute per account, and bans burst to 10k a minute. Any new storefront path that must never be shared between visitors needs adding to that rule's exclusions as well as the VCL pass list.
