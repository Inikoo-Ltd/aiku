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
