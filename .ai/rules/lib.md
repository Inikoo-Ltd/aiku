---
paths:
  - lib/**
---

# Lib

## New hand-written Dart files start with an author header

Every Dart file a developer (or their agent) creates by hand, here or in `test/` and `integration_test/`, starts with this block, as the very first lines, before any `library`, `import` or `part` directive:

```dart
/*
 * Author <full name>
 * Created on <dd-MM-yyyy-HHh-MMm, local time of creation>
 * GitHub: https://github.com/<handle>
 * Copyright (c) <year of creation>, Raul A Perusquia Flores
*/
```

For example, `Created on 05-11-2026-14h-47m`. PowerShell gives that value with `Get-Date -Format 'dd-MM-yyyy-HH\h-mm\m'`. The copyright holder is always Raul A Perusquia Flores.

- The name and GitHub link are your own; never copy them from another file. Keep them in your personal, uncommitted agent instructions (for Claude Code, `~/.claude/CLAUDE.md`). If they are missing there, ask the developer once and save them there.
- Skip generated files: `*.g.dart`, `*.freezed.dart`, `*.mocks.dart`, `GeneratedPluginRegistrant`, and anything `flutter create` or another tool writes (including `lib/main.dart` as it was created).
- Never add, change or "fix" the header of an existing file, even one that has none.
- Non-Dart platform files (Gradle, Kotlin, XML) get no header.
- `dart format` and `flutter analyze` accept the block unchanged.

## Doc comments on public API; aiku's no-comments rule does not apply here

aiku_mobile follows the dart_boost guideline, not aiku's "write no comments" rule. Public classes, members and top-level functions get `///` doc comments saying what they do and anything a caller must know. Private helpers get a comment only when the *why* is not obvious from the code.

## The app is a client of aiku's grp staff API only

aiku_mobile never touches the aiku database. Everything goes through the grp staff API (`/app/api`, route names `grp.api.*`) with a Sanctum bearer token. The customer API (retina, `/app/re-api`) is not for this app.

When a screen needs data or an action the API doesn't offer, add a grp API endpoint in aiku that calls the existing aiku action and its `authorize()`, so the app runs exactly the rules the web runs. Never re-implement backend logic in Dart: the earlier Maya app drifted from the web that way.

## Permissions and button visibility come from the server

The app never works out permissions itself. A button must not be shown to someone the action will refuse, so the API sends flags computed from the same rule the action checks (aiku's web pages send `can_work` and `is_editable` for delivery notes), and the app shows or hides buttons from those flags.

Warehouse work (picking, packing, trolleys, picking sessions, scan to pick or pack, finalise and dispatch) is for warehouse staff only (aiku INI-073). That rule lives on the server in `DeliveryNote::canBeWorkedOnBy()` and is not repeated in Dart.

## Picking: aiku's states, decimal quantities, scanner input

- **States are aiku's, never app-side inventions.** The aiku enums are the source of truth; as of 5 Nov 2026:
  - Delivery note (`DeliveryNoteStateEnum`): `unassigned`, `queued`, `handling`, `handling_blocked`, `picked`, `packing`, `packed`, `finalised`, `dispatched`, `cancelled`.
  - Picking session (`PickingSessionStateEnum`): `in_process`, `handling`, `handling_blocked`, `picking_finished`, `packing_finished`.
- **Quantities can be part-packs.** The backend accepts decimal picks everywhere, so every quantity input accepts decimals. One scan picks quantity 1, which is one pack. Locale decimal separators ("0,75" vs "0.75") were never tested on the web.
- **A barcode scanner arrives as keyboard input.** A laser scanner, or a handheld's built-in one, types the barcode as fast keystrokes. That is the normal input path to handle; no camera or pairing is involved.

## Colour: the accent is the user's theme[4]; colours with meaning stay fixed

- Interactive colour (buttons, links, focus rings, selected states, accents) comes from the signed-in user's aiku theme, never a hard-coded colour. The theme is 8 hex colours in `users.settings.app_theme`, and **the accent is `theme[4]`**.
- Before login no user is known, so the login screen uses aiku's default accent `#4f46e5`, as the web login does.
- Derived shades are ported exactly from aiku's `useAppAccent.ts` (`Color.lerp` works):
  - strong: 80% accent + 20% black
  - deep: 60% accent + 40% black
  - soft: 10% accent + 90% white
  - muted: 25% accent + 75% white
  - text on the accent: white if its relative luminance is below 0.4, otherwise `#111827`
- Colour that carries meaning is never themed: green for done or passed, red for failed or destructive, amber for blocked or waiting. Fixed chart and stat palettes stay fixed too.

## Buttons have icons, dialogs are compact, no toasts or SnackBars, one loading style

- **Every button has an icon** (aiku rule, 4 Nov 2026). Outside dialogs, buttons are white (the raised grey in dark mode) or transparent with a border, and the icon carries the colour: a red ✕ for cancel, a green ✓ for confirm, an amber warning for destructive actions. No grey fills. Use `BorderedIconButton`.
  - **Exception:** "Sign in" on the login screen and "Enter" on the two-factor screen have no icon and are filled with the accent, matching aiku's web login. Nothing else is exempt.
- **Dialog footer:** Cancel is the bordered button, not stretched. Submit is the primary button, full width, filled with the accent, with its icon in the button's text colour. A destructive submit stays bordered and full width, with the amber icon.
- **Dialog headers are compact:** a small grey semibold title and a small borderless close button. No big titles or big round ✕ buttons.
- **No toasts, and a `SnackBar` counts as a toast**. Errors show where the user acted: inside the dialog, or as small red text under the control they pressed. Success shows in place: the dialog closes, the row appears, "Copy" changes to "Copied".
- **Loading:** one standard loading indicator across the app, and skeleton placeholders for content that is still loading. No hand-made spinners or pulsing grey boxes.

## Money formatting, codes never wrap, English fallback for text

- **Money always uses currency formatting**, never a bare number and never "12.5 EUR". Prices and totals have 2 decimals; supplier unit costs have 4, because they are stored like 0.0825. Money inputs are currency inputs too. In a translated string, pass the formatted amount as one placeholder.
- **Codes and references never wrap.** Product codes, SKO codes and PO or delivery note references stay on one line ("ACEBL-BOX-" / "01" reads as two codes). Let names and descriptions wrap instead. Table headers never wrap either.
- **A missing translation falls back to the English text** with its placeholders filled in, never a blank or a raw key, as aiku's `ctrans()` does.

## Folder layout and state handling

Feature folders under `lib/`, with tests mirroring them under `test/`:

- `main.dart`: reads the build settings and starts the app. `app.dart`: `MaterialApp`, and switches between login, two-factor and signed-in.
- `config/`: build settings (`AppConfig`).
- `api/`: the aiku API client and its sealed `ApiFailure` types.
- `auth/`: session controller, token store, device name, and the login, two-factor and forgot-password screens.
- `home/`: signed-in screens.
- `ui/`: shared look.
  - Sign-in only: the palette (`AikuPalette`), the watercolour background and the brand header.
  - Signed-in: `AppColours` (light and dark colours) and the dashboard building blocks (`dashboard_kit.dart`).
  - Both: accent shades, fields and buttons.

State lives in plain `ChangeNotifier` classes with no state-management package, passed into the widgets that need them; no globals. The HTTP client and secure storage are injected, so tests swap them for `MockClient` and an in-memory store.

## Build settings come from dart_define files; never commit or log secrets

`AIKU_API_BASE_URL` and `AIKU_APP_KEY` are read with `String.fromEnvironment` and have no default in code; a build without them stops at start-up with a `StateError`. Run with `flutter run --dart-define-from-file=dart_define/<env>.json`. Only `dart_define/example.json` is committed (see `.gitignore`).

Never log or print a password, a one-time password, a token or the app key.

Local development: aiku runs `php artisan serve --host=0.0.0.0 --port=8000` in WSL, and the emulator calls `http://10.0.2.2:8000`. aiku skips 2FA when `APP_ENV=local`, so the two-factor screen can only be checked against local through the widget tests.

## Mobile theme: its own aiku setting, exact accent, mobile tones

- The app's theme is the user's **mobile** theme, `users.settings.mobile_app_theme` (8 hex colours), saved with `PATCH /app/api/mobile/profile/theme`. It is never written to the web's `app_theme`.
- Which theme shows (`effectiveTheme`): the mobile theme once one has been picked, otherwise the web `app_theme`, otherwise aiku's default preset.
- Picking a theme saves it on the phone first (with the session in secure storage), then on aiku. If aiku can't be reached, the phone keeps it and the Theme screen says why. A 401 signs the user out.
- The picker offers aiku's presets only (`aikuThemePresets`, copied from `useColorTheme`), compared case-insensitively.
- Colours: `primary` is exactly `theme[4]`, and `onPrimary` follows the luminance rule (not `theme[5]`). The other roles come from `ColorScheme.fromSeed(seedColor: accent, dynamicSchemeVariant: DynamicSchemeVariant.fidelity)`, which keeps the accent's hue so the app sits in mobile-friendly tones around it (`signedInThemeData`).
- Only the signed-in screens follow the theme. The login, two-factor and forgot-password screens keep the default accent.
- Colours that carry meaning (status, stat tiles, charts) stay fixed and never follow the theme.

## Light and dark appearance; the theme band; colours from AppColours

- **Appearance.** The signed-in app has a light and a dark appearance. The Theme screen offers System / Light / Dark as radio options (`AppearanceController`). The choice is kept on the phone only (secure storage key `aiku.appearance`), never in aiku. System follows the phone's dark mode.
- **Sign-in screens stay light.** Login, two-factor and forgot password copy aiku's web login.
- **Signed-in widgets take colours from `AppColours.of(context)`.** That is a ThemeExtension built from `theme[4]` by `signedInThemeData(accent, brightness)`. Never use a hard-coded light colour such as `AikuPalette` or `Colors.white` in a signed-in screen. `AikuPalette` is for the sign-in screens only.
- **Light.** The accent is `theme[4]` exactly, as `primary` and as the theme band. Only accent-coloured text on white (`accentText`) is darkened, and only until it reaches 4.5:1.
- **Dark.**
  - The page is a dark grey, never black, because black made the band and figures look harsh. The neutral scale is: page `#202124`, raised `#2A2B2F`, hairline `#3C4043`, text `#E8EAED`.
  - Only an accent below 3:1 on the page is lightened, within its own hue (e.g. `#000000` in "Pure Black and White").
  - The band is a deep tone of the accent, kept dark enough for white text at 4.5:1. Highlights are a light tone of the accent.
- **The theme band.** The top of each Home (app bar, tabs and the leading figure) sits on `band`. Text on it uses `onBand`, which follows the luminance rule, and underlines and progress use `bandHighlight`.
- **Fixed colours.** Status colours (amber blocked, green done, red error) and the stage palette never follow the theme, in either appearance.

## Signed-in UI style: flat, led by the theme band

- **Reuse the building blocks; don't rebuild them.** `lib/ui/dashboard_kit.dart` has `ThemeBand`, `TextTabs`, `SectionHeader`, `HeroFigure`, `Caption`, `StageList`, `formatCount`, `updatedAt` and `placeholderFigure`. Also use `AppColours` (`lib/ui/app_colours.dart`), `LoadProblem`, `PreviewNotice` and `NotConnectedCard`.
- **Layout.** A Home starts with a `ThemeBand` that continues the app bar. It holds the screen's tabs and one leading `HeroFigure`, with an `updatedAt` caption on the right for live figures. Everything else sits flat on the page: sections under an accent `SectionHeader`, rows split by hairlines, and whitespace between sections.
- **No pills and no boxed cards.** Don't use `Card`, `Chip`, `ChoiceChip` or `SegmentedButton`, and don't add rounded tinted fills around content. The bottom bar has no indicator pill; the chosen tab is drawn in `accentText`. Grids use a hairline `TableBorder`, not boxes.
- **Choosing.** Switching between views of one screen uses `TextTabs`: underlined text with no tap box. On the band pass `onBand: true`. A setting with one value uses `RadioGroup` with `RadioListTile` rows: icon first, radio last. A longer list of options opens in a bottom sheet with a tick on the chosen one, like More periods.
- **Figures.**
  - Use tabular figures, and counts through `formatCount`.
  - Money is shown exactly as aiku formats it, as full amounts. The app never abbreviates or re-formats money.
  - While loading, show the same layout with `—`; the layout is its own skeleton.
  - Live figures refresh on pull-to-refresh and when the app comes back to the foreground. A failed refresh keeps the old figures and adds a red line. A failed first load shows `LoadProblem` with Try again.
- **Check both appearances.** Look at every new screen in light and dark. Also try a pale preset (Rosewater Blush or Spain Distribution 2) and a near-black one (Pure Black and White). Colour logic gets a test that loops over every preset.
