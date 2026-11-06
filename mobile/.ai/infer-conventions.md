# Infer this project's conventions

Bootstrap `.ai/rules` from the code that already exists here. Recording rules
one at a time works going forward; this is the pass that catches what the
project already decided.

## How to run the sweep

Work through these dimensions one at a time. For each, read a representative
sample of the files that dimension covers -- not every file -- and write down
the pattern you actually see.

- **State management** -- which solution, how providers/blocs/notifiers are
  named, where they live, how they are scoped and disposed.
- **Widget composition** -- StatelessWidget vs StatefulWidget vs hooks, when a
  widget gets extracted, const usage, how build methods are kept small.
- **Navigation and routing** -- declarative vs imperative, where routes are
  declared, how arguments and deep links are typed.
- **Error handling** -- Result types vs exceptions, where errors are caught,
  what reaches the user, how failures are logged.
- **Async and streams** -- Future vs Stream conventions, cancellation,
  `mounted` checks after await, timeout policy.
- **Testing** -- unit vs widget vs integration split, fake vs mock, fixture and
  helper layout, what is asserted and what is not.
- **Code generation** -- which generators, whether generated files are
  committed, the exact build command.
- **Project layout** -- feature-first vs layer-first, package boundaries in a
  monorepo, what belongs in `lib/src`.

Then make one open-ended pass for base classes, shared mixins, extension
conventions and module boundaries that the list above does not name.

## What to record, and what not to

- **Document what the code does, not what it should do.** This is a description
  of the project, not a wish list.
- **Record only well-supported, non-default conventions.** If you cannot point
  to several files doing it, it is not a convention yet.
- **Skip anything the linter already enforces.** Read `analysis_options.yaml`
  first and never restate a rule it already covers. `dart format` settles
  formatting; do not write rules about it.
- **Report mixed patterns instead of recording them.** If half the codebase
  does it one way and half the other, say so and let a human decide. A rule
  that codifies a coin flip is worse than no rule.

## Before writing anything

Present every convention you found, each with the evidence that supports it --
the files you saw it in and the count. Wait for approval. The person reading
this knows which patterns are deliberate and which are accidents.

Once a convention is approved, record it with the `record_rule` tool: a `glob`
for the files it applies to, a short specific `title` (e.g. "state management:
providers live in `lib/state`, one per feature"), and a `note` of a few lines.
Prefer this over writing files by hand -- it also regenerates the index, so
the rule is visible immediately.

If `record_rule` is not available in this session, write the file yourself
instead of discarding the work: create `.ai/rules/<area>.md` with a `paths:`
frontmatter list naming the globs it applies to, then run
`dart run dart_boost rules index` so the new file shows up in the index --
without that step it stays invisible to agents. This is the fallback, not the
first choice.
