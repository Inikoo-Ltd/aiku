---
paths:
  - 'vite.*.config.mjs'
---

# General

## Judge bundle changes only by A/B builds; vendor chunks only for what the entry loads
31 Oct 2026: grp manualChunks is `startUpVendorChunks` (vite.app-plugins.mjs). Only vendor modules the entry imports statically get a per-package chunk; the rest stay with the pages that use them. One chunk per whole package had put every page's date-fns locales and datatable styles into the first load. Sentry stays out of vendor chunks (it makes a circular chunk, @sentry-internal -> @sentry). Measure a bundle change only by building the same source twice into a scratch --outDir (NODE_OPTIONS=--max-old-space-size=8192, VITE_SENTRY_GRP_DSN=dummy so Sentry is included as on prod) and comparing brotli sizes, never against a stale public/grp. A dialog or picker that renders only after a click is loaded with defineAsyncComponent; check what pulls a heavy package into a page before adding code, since an unused import of a Page file once dragged the whole Customer page into Product.
