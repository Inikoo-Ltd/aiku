---
paths:
  - 'app/Http/Middleware/Handle*InertiaRequests.php'
---

# Middleware

## Shared Inertia props that cost anything are closures
`share()` runs on every request of the group, including `/json/*` calls and redirects that never render a page. Any shared prop that runs a query, reads the cache or decodes `published_layout` must be a closure (`fn () => ...`), so it is resolved only when a page renders. Eager values there cost every storefront JSON call (appointment types alone ran ~640k times a day before 13 Nov 2026). Capture request-time state such as the locale outside the closure. Tests: 'iris shares the out of stock notification outbox only when a page renders' and 'iris pages still receive announcements, dialogs and appointment types' in tests/Feature/IrisTest.php.
