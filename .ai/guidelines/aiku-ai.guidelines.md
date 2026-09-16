- DO not write code comments you must write clear, self-explanatory code instead
- Do not create test file unless we ask you

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
