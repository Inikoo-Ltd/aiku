- DO not write code comments you must write clear, self-explanatory code instead
- Do not create test file unless we ask you

## Frontend translations use `ctrans`, never `trans`

Every translated string in `resources/js` goes through
`import { ctrans } from "@/Composables/useTrans"`, never `trans` from `laravel-vue-i18n`.
Same signature — `ctrans(text, replacements)` — but it falls back to the original text with
`:placeholder` replacements applied when no translation exists, so a missing key renders the
English string instead of an empty node. When you touch a file that still calls `trans(`,
convert those calls and the import as well.

## Tests share one database per parallel worker

Pest runs with `--parallel`. Each worker restores the dump ONCE and then runs many test
files in it, in an order paratest decides at runtime. Rows a test leaves behind are visible
to every later test in that worker, and which files share a worker changes run to run. A
test that passes alone and in its own file can still fail in CI.

When writing or fixing a test:

- Never assert an absolute count on a shared fixture. Read the baseline first, assert the delta.
- Never select a row by hard-coded id (`Pallet::find(4)`) or bare `first()`. Query for the
  invariants your assertions actually need, and exclude rows already used.
- Set the preconditions your assertions depend on instead of assuming the fixture arrives clean.
- Build fixtures through the `tests/Pest.php` helpers (`createGroup`, `createOrganisation`,
  `createShop`, ...), never through a bare model factory. The helpers return the FIRST row of
  their kind, so a factory-made half-seeded Group or Organisation left behind by any earlier
  file becomes the one every later test gets, and the failure surfaces far from its cause.
- Unit tests must not write to the database. A hand-rolled transaction is not enough of a
  guard: anything written on another connection, or in a job, survives the rollback.
- Reset any process-wide static you rely on (e.g. model auditing) inside the test itself.

A red CI run is usually several unrelated causes at once, and `--stop-on-defect` hides the
rest. Fix the one that fails every run first, then re-read the log.

## Bundles: derive the whole from the parts, every time

A bundle is one `Product` (`is_bundle`) whose trade units stand in for all of its components.
Everything downstream — available quantity, gross weight, the SKU on the platform variant,
picking — is read off those trade units, so a component missing there is a component missing
from the customer's order. Three ways it goes wrong, all seen in the wild:

- Two components made of the same trade unit. `SyncProductTradeUnits` assigns per trade unit
  (`$tradeUnits[$id] = ...`), so a list carrying the same id twice keeps the last and silently
  drops the rest. Callers must hand it ONE row per trade unit with the quantities already
  added up — it will not do that for you, and this is true of every caller, not just bundles.
- A component that is itself several trade units. The bundle holds
  `component_pivot_quantity * bundle_quantity` of it, not `bundle_quantity`.
- Recomputing from whatever the request happened to mention. After items are created,
  requantified or deleted, read the trade units back off the bundle's CURRENT items
  (`UpdateBundle::getCurrentBundleTradeUnits`). Merging per-branch partials leaves the
  product describing an older component set.

Portfolio `sku` is a snapshot taken at `StorePortfolio` time. Anything that changes a
bundle's components must recompute it via `WithPortfolioSKU::getSKU`, or the platform keeps
selling the old component set. Use `App\Actions\Dropshipping\Bundle\WithBundleTradeUnits`
rather than re-deriving this mapping.

## Platform listed-product payloads share one shape

Every `Get*ListedProducts` / `Search*Products` action feeding the "match to an existing
product" picker must return `id`, `name`, `slug`, `code`, `images` (and `sku_list` where the
platform has variants). The pickers render `item.name` / `item.code` and fall back to the
literal strings "no name" / "no code" — a platform that returns its own vocabulary
(Shopify's `title` / `handle`) renders an entire list of blanks.

## An action returning `[bool, string]` has a result you must check

The Shopify product/variant actions report failure by return value, not by throwing. Calling
one and discarding what it returns turns a rejected push into a 2xx and a "synchronised"
toast, which is indistinguishable to the user from the feature not existing. Propagate it —
`ValidationException` for a retina request — or state in the caller why swallowing is right.

## Interactive colour comes from the organisation's theme

Every organisation sets its own theme. A hardcoded `indigo-600` button beside an orange
breadcrumb and an orange active tab reads as a different application, and the app's default
accent happens to be indigo, so the mistake is invisible on a default-themed org and obvious
on every other one.

Buttons, links, focus rings, selected states and accents take the theme, through the CSS
custom properties `useAppAccent.ts` derives from `theme[4]`:

| instead of | use |
| --- | --- |
| `indigo-600`, `indigo-500`, `indigo-400` | `[--app-accent]` |
| `indigo-700` | `[--app-accent-strong]` |
| `indigo-800`, `indigo-900` | `[--app-accent-deep]` |
| `indigo-50`, `indigo-100` | `[--app-accent-soft]` |
| `indigo-200`, `indigo-300` | `[--app-accent-muted]` |

Written as Tailwind arbitrary values: `bg-[--app-accent] text-[--app-accent-text]
hover:bg-[--app-accent-strong]`, `focus:ring-[--app-accent]`,
`border-[--app-accent-muted] bg-[--app-accent-soft]`. `--app-accent-text` is black or white,
whichever reads on the accent, so never pair the accent with a hardcoded `text-white`.
`layout.app.theme[...]` and `var(--theme-color-*)` are the same theme by another route, for
inline styles. Prefer the existing `Button` component and its `type`s over classes by hand.

The exception is colour that carries meaning, which keeps its own: green for done or passed,
red for failed or destructive, amber for blocked or waiting, and the fixed palettes behind
stat cards, chart series and legends. There the colour is information, not decoration, and
theming it would destroy what it says.

When you touch a page, fix the hardcoded interactive colour you find on it rather than
matching it. `grep -n "indigo-\|blue-600" <file>` is usually the whole audit.
