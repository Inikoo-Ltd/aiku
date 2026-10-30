<laravel-boost-guidelines>
=== .ai/aiku-ai.guidelines rules ===

- DO not write code comments you must write clear, self-explanatory code instead
- Do not create test file unless we ask you

## Frontend translations use `ctrans`, never `trans`

Every translated string in `resources/js` goes through
`import { ctrans } from "@/Composables/useTrans"`, never `trans` from `laravel-vue-i18n`.
Same signature — `ctrans(text, replacements)` — but it falls back to the original text with
`:placeholder` replacements applied when no translation exists, so a missing key renders the
English string instead of an empty node. When you touch a file that still calls `trans(`,
convert those calls and the import as well.

## Every change is tested

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

## Laravel 12 on the Laravel 10 structure

This project upgraded from Laravel 10 without migrating to the streamlined Laravel 11+ file
structure. That is deliberate: follow the Laravel 10 structure unless asked to migrate.

- Middleware lives in `app/Http/Middleware/` and service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration:
    - Middleware registration happens in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule register in `app/Console/Kernel.php`
    - Rate limits live in `RouteServiceProvider` or `app/Http/Kernel.php`
- When modifying a column, the migration must include every attribute previously defined on
  the column, or they are dropped.
- Eager loads can be limited natively: `$query->latest()->limit(10);`.
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts`
  property. Follow the conventions of sibling models.

## Interactive colour comes from the organisation's theme

Every organisation sets its own theme. A hardcoded `indigo-600` button beside an orange
breadcrumb and an orange active tab reads as a different application, and the app's default
accent happens to be indigo, so the mistake is invisible on a default-themed org and obvious
on every other one.

Buttons, links, focus rings, selected states and accents take the theme, through the CSS
custom properties `useAppAccent.ts` derives from `theme[4]`:

| instead of | use |
| --- | --- |
| `indigo-600`, `indigo-500`, `indigo-400` | `[var(--app-accent)]` |
| `indigo-700` | `[var(--app-accent-strong)]` |
| `indigo-800`, `indigo-900` | `[var(--app-accent-deep)]` |
| `indigo-50`, `indigo-100` | `[var(--app-accent-soft)]` |
| `indigo-200`, `indigo-300` | `[var(--app-accent-muted)]` |

Written as Tailwind arbitrary values: `bg-[var(--app-accent)] text-[var(--app-accent-text)]
hover:bg-[var(--app-accent-strong)]`, `focus:ring-[var(--app-accent)]`,
`border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)]`. Keep the `var()`: the
bare `bg-[--app-accent]` form is Tailwind 3 shorthand that Tailwind 4 rewrites, while
`bg-[var(--app-accent)]` gives the same CSS in both. `--app-accent-text` is black or white,
whichever reads on the accent, so never pair the accent with a hardcoded `text-white`.
`layout.app.theme[...]` and `var(--theme-color-*)` are the same theme by another route, for
inline styles. Prefer the existing `Button` component and its `type`s over classes by hand.

The exception is colour that carries meaning, which keeps its own: green for done or passed,
red for failed or destructive, amber for blocked or waiting, and the fixed palettes behind
stat cards, chart series and legends. There the colour is information, not decoration, and
theming it would destroy what it says.

When you touch a page, fix the hardcoded interactive colour you find on it rather than
matching it. `grep -n "indigo-\|blue-600" <file>` is usually the whole audit.

## Controls are PrimeVue components, not native elements

Build form controls and toggles from PrimeVue (v4, `primevue/*`) instead of plain HTML:
`DatePicker` rather than `<input type="date">`, `SelectButton` rather than a row of
hand-built `<button>`s, and likewise `Select`, `InputText`, `ToggleSwitch`, `Checkbox` and
`Dialog`. A native input next to a PrimeVue one renders at a different size and height, and
the page reads as stitched together.

Before writing a new wrapper, reuse the existing ones:

- `@/Components/Utils/SegmentedToggle.vue` is `SelectButton` in the organisation's theme
  colour, with hover and pressed states and optional icons. Use it for Daily/Weekly style
  switches.
- For a date range, copy the `DatePicker` setup in
  `@/Components/SalesAnalysis/SalesAnalysisReport.vue` (`dateModel`, `datePickerPt`,
  `fieldFocusClass`), which converts between the ISO strings the server sends and the `Date`
  the picker needs.

Style PrimeVue through `pt` or scoped `:deep()` rules using the theme variables from the
section above, never hardcoded colours. When you touch a file that still has hand-rolled
controls, convert them.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel-octane/core rules ===

# Laravel Octane

This application uses Laravel Octane, a long-running PHP server. The application bootstraps once and handles many requests within the same process.

- Never store request-specific state in singletons or static properties, because it can leak across requests.
- Use `config('octane.server')` to detect the active driver (`swoole`, `roadrunner`, or `frankenphp`).
- Prefer scoped bindings (`$this->app->scoped()`) over singletons for per-request services.

When working on Octane-specific features (concurrency, shared tables, memory, driver configuration, testing), invoke `octane-development` for detailed rules.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
