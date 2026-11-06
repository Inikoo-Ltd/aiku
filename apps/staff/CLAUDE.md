<dart-boost-guidelines>
=== foundation rules ===

# Foundation

These guidelines were composed for **aiku_mobile** by dart_boost
0.2.1 from its resolved dependency versions. They describe *this*
project, not Dart in general -- when they contradict a general habit, they win.

## Versions are facts, not guesses

- Every version-specific section below was selected from what this project
  *actually resolves*. Do not second-guess it against a version you remember.
- Do not upgrade, downgrade or add dependencies unless you were asked to. A
  `flutter pub add` you ran to "make the import work" silently invalidates
  the rest of this document.
- Never invent an API. If you are unsure whether a method exists on the
  installed version, look it up in the real resolved sources -- the `dart` MCP
  server's `analyze_files`, `lsp`, `read_package_uris` and
  `rip_grep_packages` all read them, and `pub_dev_search` does not.

## The verification loop

Run these before you consider a change finished. All three must be clean:

1. `flutter analyze` -- zero errors *and* zero warnings. A new lint on a
   line you touched is yours.
2. `dart format .` -- formatting is not a matter of taste here.
3. `flutter test` -- run the suite, not just the file you edited.

Do not write a throwaway script to prove a change works when a test can prove
it instead. If the behaviour is worth checking twice, it is worth a test.

## Conventions

- Follow the conventions already in this repository. Before creating a file,
  read its siblings for structure, naming and import style, and match them.
- Prefer editing an existing file over creating a new one. Prefer extending an
  existing widget, provider or helper over adding a parallel one.
- Descriptive names over short ones: `isEligibleForRefund`, not `check()`.
- Stick to the existing directory layout. Do not introduce a new top-level
  `lib/` folder without being asked.
- Only create Markdown documentation when you were explicitly asked to.

=== project rules ===

# Project rules

This project records its own decisions as rules, separately from the general
guidance above. Rules describe *this* codebase, and they outrank anything here
that contradicts them.

**Before planning or editing any file**, check `.ai/rules/index.md` if it
exists. Find the row whose globs match the file's path and read that rule file.
Read only the rows that match -- the index exists so that rules for parts of
the codebase you are not touching stay out of the way.

When you learn something durable about this project -- a settled decision, a
non-obvious trap, a standing constraint -- and the `record_rule` tool is
available, use it rather than writing a rule file by hand: the index is
regenerated on every recorded rule, and a hand-written file stays invisible to
other agents until it is. Do not record secrets, transient state, or anything
already obvious from the code.

When asked to infer this project's conventions, read
`.ai/infer-conventions.md` if it is present, and follow it.

=== dart rules ===

# Dart

Dart 3.13.7 resolved this project's package graph. Everything below
is available on that version; nothing below needs an experiment flag.

## Types

- Sound null safety everywhere. Do not add `!` to silence the analyzer -- it
  is a claim about runtime that you must be able to justify.
- Use `Object` rather than `dynamic`. `dynamic` disables every check that
  makes the analyzer useful, and it does it silently.
- Prefer `final` for locals and fields that are not reassigned; prefer `const`
  when the value is known at compile time.
- Prefer `sealed` or `final` classes over open inheritance for types you own.
  `sealed` is what makes a `switch` exhaustive, which is what makes the
  analyzer tell you about the variant you forgot.

## Control flow

- Use exhaustive `switch` expressions over `if` chains on a sealed type or an
  enum. Never add a `default` clause to an exhaustive switch -- it converts a
  future compile error into a silent fallthrough.
- Use destructuring patterns instead of positional access on records and
  known-shape maps.
- Use a record for a small unnamed tuple of values. Reach for a class as soon
  as the fields want names, invariants or methods.

```dart
sealed class Result<T> {}

final class Ok<T> extends Result<T> {
  const Ok(this.value);
  final T value;
}

final class Err<T> extends Result<T> {
  const Err(this.error);
  final Object error;
}

String describe(Result<int> result) => switch (result) {
  Ok(:final value) => 'ok $value',
  Err(:final error) => 'failed: $error',
};
```

## Modern syntax worth using

These read better than the workarounds they replace, and the analyzer accepts
them on this SDK:

- Dot shorthands for a known context type: `Alignment.center` becomes
  `.center` when the parameter type already says `Alignment`.
- Null-aware elements in collection literals: `[a, ?maybeB]` instead of
  `[a, if (maybeB != null) maybeB]`.
- Wildcards for bindings you do not use: `for (final _ in items)`,
  `(_, second)`.
- Digit separators for long literals: `1_000_000`, `0xFF_FF_00`.

## Asynchrony

- Never leave a `Future` unawaited by accident. If it is deliberate, say so
  with `unawaited(...)` from `dart:async` and explain why in a comment.
- Do not use `async` on a function that only forwards a `Future` -- return it.
- Prefer `Future.wait` over sequential awaits for independent work, and handle
  the fact that it reports only the first error.
- A `Stream` you subscribe to is a resource. Cancel the subscription in the
  same object that created it.

## Errors

- Throw a specific type, not `Exception('...')`, for anything a caller might
  want to distinguish.
- Catch narrowly. A bare `catch (e)` with no `on` clause also swallows the
  programming errors you wanted to see.
- Use `ArgumentError`/`StateError` for contract violations by the caller and
  reserve custom exceptions for conditions the caller can act on.
- Rethrow with `rethrow`, never `throw e` -- the second one discards the
  original stack trace.

## Style and tooling

- `dart format` owns formatting. Do not hand-format around it, and do
  not add a trailing comma to force a split -- the current formatter decides
  line breaking from width, not from your commas. Set `formatter: page_width:`
  in `analysis_options.yaml` if the default width is wrong for this project.
- `dart fix --apply` clears mechanical lints. Read the diff before
  committing it; some fixes are library migrations, not cleanups.
- Public API gets doc comments. Private helpers get a comment only when the
  *why* is not obvious from the code.
- Do not commit `print`. Use the project's logger, or `debugPrint` in Flutter
  code.

=== dart/v3.13 rules ===

# Dart 3.13

Guidance for Dart 3.13 specifically. This project resolved Dart
3.13.7, so everything below analyzes without an experiment flag --
and none of it compiles on a 3.12 checkout of the same code.

## Primary constructors

3.13 is the release that stabilizes them. A parameter list on the *class
header* declares the constructor, and each `final`/`var` parameter also
declares the matching field, so a value class is one line and has no
assignment boilerplate.

Prefer the header form for value classes, DTOs and any class whose
constructor only assigns fields. Keep a body constructor when the constructor
has to do work.

```dart
class Point(final int x, final int y) {
  double get magnitude => (x * x + y * y).toDouble();
}
```

The rules that actually bite:

- **`final` or `var` makes a field; a bare type does not.** `class W(int seed)`
  declares a parameter, not a field. It is in scope in *field initializers*
  (`final int start = seed * 2;`) and nowhere else -- methods cannot see it.
- **`const` goes on the class, not the constructor:** `class const
  Temp.celsius(final double degrees);` A header constructor may be named.
- **There is no initializer list.** `class A(int seed) : start = seed;` does
  not parse. Initialize in the field declaration instead.
- **There is no superclass argument list.** `class D(...) extends Base(id);`
  does not parse either. Forward with `super.` parameters:
  `class Derived(super.id, final String label) extends Base;`
- **A body constructor can redirect to the header one:**
  `Money.dollars(int d) : this(d * 100);`
- Named and optional parameters, defaults, `required`, annotations and doc
  comments all work in the header exactly as they do in a normal parameter
  list.
- Enums take a header constructor too:
  `enum Suit(final String glyph) { hearts('H'), spades('S') }`
- A class with a header constructor and nothing else ends in `;`, with no
  braces.

```dart
class Base(final int id);

class Derived(super.id, final String label) extends Base;

class Window(int seed, {final String title = 'untitled'}) {
  final int start = seed * 2;
}
```

Do not convert a whole codebase mechanically. Rewrite a class when you are
already editing it, and leave classes whose constructors validate, compute or
call `super` with real arguments alone.

## Core library additions

These do not exist on 3.12, so do not introduce them into a package whose
`environment: sdk:` lower bound is below `3.13.0`:

- `List.unmodifiableOf(elements)` and `Map.unmodifiableOf(other)` -- aliases
  for the `unmodifiable` constructors that read as the `of`-family
  counterparts they sit next to.
- `int.oneBitCount` and `int.trailingZeroBitCount` -- popcount and
  lowest-set-bit position. Both count against the platform integer width, so
  `(-1).oneBitCount` is 64 natively and 32 on the web.
- `Future.pause([duration])` -- `Future.delayed` without a computation, so it
  cannot complete with an error. Use it instead of
  `Future.delayed(d, () {})`.

`dart:isolate` also gained the synchronous `Isolate.create` / `runSync` /
`shutdownSync` / `runEventLoopSync` embedder API. It is for native code
driving a Dart isolate from a foreign event loop, not for application-level
concurrency -- keep using `Isolate.run`.

## Not new here

Augmentations (`augment class`), enhanced parts (a part file with its own
imports) and static extension members (`extension on F { static F make() }`
called as `F.make()`) are still experiments on this SDK. They do not analyze,
and enabling the experiment flag to use them is not an option this project
takes.

=== flutter rules ===

# Flutter

This project targets Flutter 3.47.9 (stable).
Version-specific guidance for the 3.47 line, if any, follows this
section.

## Widgets

- Compose small `Widget` subclasses rather than returning big trees from
  helper *methods*. A subclass gets a `const` constructor and its own rebuild
  boundary; a `_buildFoo()` gets neither and rebuilds with its parent.
- Add `const` wherever the analyzer allows it. It is the cheapest performance
  work available and it also stops needless rebuilds.
- Keep `build` pure: no I/O, no allocation of controllers, no `setState`, no
  side effects. Anything expensive belongs in `initState`, a provider, or a
  memoised field.
- Prefer `ListView.builder` / `SliverList.builder` over `ListView(children:)`
  for any list whose length is not a small constant.
- Give a `Key` to widgets in a list whose identity outlives their position.
  Without one, Flutter reuses the wrong element when items reorder.

## State and lifecycle

- Dispose everything you create: `TextEditingController`, `FocusNode`,
  `ScrollController`, `AnimationController`, `StreamSubscription`, `Ticker`.
  A `dispose` that is missing one of them is the standard memory leak here.
- Do not touch `BuildContext` across an `await` without checking `mounted`
  first. This is a lint (`use_build_context_synchronously`), not a style note.
- Keep `setState` bodies to the assignment. Compute before the call, so a
  thrown exception cannot leave the widget half-updated.
- Handle changed configuration in `didUpdateWidget`, not by comparing in
  `build`.

```dart
Future<void> _save() async {
  await repository.save(draft);
  if (!mounted) return;
  Navigator.of(context).pop();
}
```

## Layout

- Read sizing from `LayoutBuilder`, `MediaQuery.sizeOf(context)` or the theme
  rather than hard-coding pixel values. Prefer the `.sizeOf`/`.paddingOf`
  accessors over `MediaQuery.of(context)`, which rebuilds on every change.
- `Expanded`/`Flexible` only inside a `Row`, `Column` or `Flex`. Unbounded
  constraints plus an unbounded child is the "RenderBox was not laid out"
  error you will otherwise spend an hour on.
- `IntrinsicHeight`, `IntrinsicWidth` and unbounded `shrinkWrap: true` lists
  are the expensive escape hatches. Use them knowingly, not by default.

## Theming and platform

- Take colours and text styles from `Theme.of(context)` /
  `ColorScheme.of(context)`. A hard-coded `Color(0xFF...)` in a widget is a
  dark-mode bug waiting to happen.
- Build the scheme with `ColorScheme.fromSeed` so light and dark stay in step.
- Branch on `Theme.of(context).platform` or `defaultTargetPlatform`, never on
  `dart:io`'s `Platform` -- that one throws on web.
- Impeller is the default renderer, with the legacy backend still reachable
  behind `--no-enable-impeller`. When you report a shader or raster problem,
  say which renderer you tested on.

## Iterating

- `flutter run` then hot reload (`r`) for widget and build-method changes. Hot
  restart (`R`) is required for `main()`, global state, `initState` shape and
  anything `const`-folded.
- Annotate a widget with `@Preview` and run
  `flutter widget-preview start` to iterate on it without booting
  the whole app.
- Profile with `flutter run --profile`. Debug-mode frame times
  are not evidence of anything.

## Assets and dependencies

- Declare every asset and font in `pubspec.yaml`. A file in `assets/` that is
  not declared is absent at runtime, and the failure is a load exception, not
  a build error.
- Platform channels and plugins need a full restart after
  `flutter pub get`; a hot reload will not register a new plugin.

=== flutter/v3.47 rules ===

# Flutter 3.47

Guidance for the 3.47 line specifically, and only for what Flutter itself
changed. This SDK ships Dart 3.13; that language and core-library guidance is
composed separately as `dart/v3.13`, so it is not repeated here.

## `flutter pub get` rewrites `analysis_options.yaml`

3.47 runs an analysis-options migration as part of `flutter pub get`: it
adds an `analyzer: exclude:` block covering `build/**` and whichever platform
directories exist (`android/**`, `ios/**`, `web/**`, ...). It only touches
packages that actually depend on `flutter`.

An unexplained diff in `analysis_options.yaml` after a pub get is the SDK, not
you. Commit it; do not revert it and do not re-add the excluded directories.

## `dart fix` now carries the Material/Cupertino package migration

This SDK's fix data contains a `replacedBy` transform pointing
`package:flutter/material.dart` at `package:material_ui/material_ui.dart`, and
`package:flutter/cupertino.dart` at `package:cupertino_ui/cupertino_ui.dart`.

Never run a blanket `dart fix --apply` and commit it unreviewed on
this SDK. Read the diff, and reject any rewritten `material.dart` or
`cupertino.dart` import unless migrating to those packages is the task you
were given -- they are not dependencies of this project unless someone added
them.

## Build version constants

`package:flutter/services.dart` exports `appBuildName` and `appBuildNumber`,
compiled in from the pubspec `version:` field (or `--build-name` /
`--build-number`). Use them for a version string in the UI instead of adding a
plugin or reading `version.json` at runtime; both are `String?` and are `null`
when the pubspec has no version.

## `awaitNotRequired` comes from `foundation.dart`

`package:flutter/foundation.dart` re-exports `awaitNotRequired`, so a file that
already imports `foundation.dart` needs no further import to use it. Annotate
a `Future`-returning API that callers may legitimately fire and forget, rather
than spreading `unawaited(...)` across every call site.

=== testing rules ===

# Testing

`flutter test` runs the suite for this project. Run the whole thing before
you call a change finished, not just the file you touched.

## What to write

- Test the behaviour, not the implementation. A test that breaks when you
  rename a private method was testing the wrong thing.
- One reason to fail per test. Assert several things only when together they
  describe one outcome.
- Name the test after the behaviour and the condition: `test('rejects an
  expired token')`, not `test('validate')`.
- When you fix a bug, write the failing test first. A regression test that was
  never seen red is not evidence.
- Prefer real objects over mocks for anything you own. Mock only at the
  process boundary: HTTP, platform channels, the filesystem, the clock.

## How to structure it

- `group` shares setup, not state. `setUp` runs per test for a reason -- never
  hoist mutable state into a `late` top-level to "save time".
- Register cleanup next to the thing that needs it with `addTearDown`, so a
  test that returns early still cleans up.
- Keep fixtures in the test that uses them until a second test needs them.
  A shared builder that every test has to read is worse than duplication.
- Never depend on test order, and never share a temp directory between tests.

```dart
test('rejects an expired token', () {
  final result = validate(Token(expiresAt: clock.now().subtract(oneHour)));

  expect(result, isA<Err<Session>>());
});
```

## Async and flakiness

- `await` or `return` every future in a test body. A dropped future turns a
  failure into a pass.
- Use `expectLater` with `emitsInOrder` for streams rather than collecting
  into a list and sleeping.
- Never call a real `Future.delayed` to wait for something. Inject a clock, or
  use `package:fake_async`.
- No network, no real filesystem outside a temp dir, no wall-clock assertions.
  Those are the three sources of a flaky suite.

## Widget tests

- `testWidgets` with a `WidgetTester` is the default tool here. Widget tests
  are cheap; write one for every non-trivial widget instead of reaching for an
  integration test.
- Find by `Key` or by semantics before finding by text. `find.text` couples the
  test to copy and breaks the moment the string is localised.
- `pumpAndSettle()` only when you genuinely need every animation to finish.
  Otherwise `pump(duration)` a fixed amount, so an animation that never
  settles fails loudly instead of hanging until the timeout.
- Wrap the widget in the same `MaterialApp`/`Theme`/localisation ancestors it
  has in the app, or you are testing a widget that does not exist.
- Anything that needs a real event loop -- an actual HTTP client, an
  `Isolate`, a plugin -- goes inside `tester.runAsync`.
- Golden tests are for layout you cannot assert on otherwise. Regenerate with
  `--update-goldens` and *look at the produced image* before committing it.

</dart-boost-guidelines>
