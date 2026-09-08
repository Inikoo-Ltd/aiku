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
