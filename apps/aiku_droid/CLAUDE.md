# aiku_droid

Native Android staff app for aiku: warehouse work (inventory, locations, goods in, goods out, fulfilment) and clocking, built so more of aiku can be added later. Not a customer app. It uses Kotlin and Jetpack Compose, has one `:app` module, and its package is `io.aiku.droid`.

The Laravel, PHP, Vue and Pest rules in the aiku root do not apply here.

## Fake data: the red dashed outline

Most screens are still skeletons built on sample data, so developers can see how each screen will look before it is wired to the API.

- **A red dashed outline (`Modifier.fakeData()`, colour `FakeDataRed`) means "fake data / skeleton, not wired to aiku yet".**
- Every block that shows sample data carries it.
- Sample data lives in `private` values in the screen file, or in an area's `*Samples.kt`.
- When you wire a block to real data, remove its sample data and its `fakeData()` outline together.
- Never outline real data. Today that is: the signed-in user (`LocalStaff`), the warehouses and their permissions (`LocalWarehouse`, `LocalWarehouses`) and appearance (`LocalAppearanceController`), clocking (`LocalClocking`: status, sessions, methods, code, timesheets, target), and Clocking & HR (`LocalLeave`, `LocalClockingAdjustments`, `LocalOvertime`, `LocalClockingCalendar`).
- The outline is dashed on purpose, so it is not confused with the solid red border of a field in error.

## Backend

- The app talks to aiku only through the staff API, never the database.
- Mobile routes sit under `/app/api/mobile`. They need the `X-Aiku-App-Key` header and a Sanctum bearer token with the `mobile` ability.
- Two-factor follows the backend's `MOBILE_TWO_FACTOR` switch: on by default in every environment, local included. A developer can set `MOBILE_TWO_FACTOR=false` in their local `.env`. An account that must use two-factor but has none sets it up in the app at sign-in (authenticator link, QR code or setup key).
- `GET /app/api/mobile/warehouses` lists the warehouses the user is authorised in (`authorisedWarehouses`). Each carries a `can` block computed with the same `authTo` permission checks the web uses:
  - `view_locations`, `edit_locations`
  - `view_inventory`, `move_stock`
  - `view_goods_in`, `book_in`, `supervise_goods_in` (Unmark as received), `edit_procurement` (Unmark as dispatched, cancel a stock delivery), `place_stock_deliveries` (Place)
  - `view_goods_out`, `pick_and_pack`, `supervise_goods_out`
  - `view_fulfilment`, `edit_fulfilment`

## Permissions

- The chosen warehouse (`LocalWarehouse.current`) scopes every screen. Its `can` (`WarehouseAbilities`) decides what the user sees.
- Wrap each module screen in `RequireAbility(ability = Ability.…)`. When access is missing, the no-access screen names the permission (`Ability.permission`). `Ability` and `WarehouseAbilities.allows()` live in `data/api/WarehouseApi.kt`.
- **Hide** tabs, menu rows, dashboard sections and actions the user cannot use. Never grey them out.
- Only the actions allowed for the record's state AND the user's rights appear, exactly as on the web. With none, a short status line (`FooterStatus`) says what is missing.
- Picker/packer roles (Dispatch clerk) get goods out, picking/packing and location viewing, but not the SKO screens: the web does not give them those either.
- Clocking & HR is for every signed-in staff member, with no ability check.
- Staff with no warehouse role get Home's staff variant (clocking, profile) and no Scan slot. For them `LocalWarehouses` is empty and `LocalWarehouse` is `NoWarehouseAccess`, whose abilities are all false. Hide warehouse-only parts when `LocalWarehouses.current.isEmpty()`.

## Navigation

- One fixed bottom bar (`MainBar`, `MainTab`): Home · My work · Scan · Inbox · Menu. It never changes per screen. The Scan slot shows only when the account has a warehouse.
- Menu (`MenuRoute`) lists every module and section the account can open, filtered by permission, plus Profile, Appearance and Log out.
- Inside a module, `ModuleHeader(WarehouseSection.X)` draws the header (context line over the title, back to wherever you came from) and the section tabs in the web menu order. Section tabs are underlined tabs (accent text and bar on the active one) in every module, Clocking included: Louis chose tabs over the canvas pills on 2026-11-11. Fulfilment is not a module: its sections sit inside Goods in, Inventory and Goods out and need `view_fulfilment`.
- The section tab row never scrolls by itself. Switching section hands the row's scroll position to the next screen (`TabHandoff` in `AppChrome.kt`), so the row stays exactly where it was when the tab was tapped. Louis rejected the jump to the active tab on 2026-11-11.
- `AppScreen(header, activeTab, showBar, footer, bottomPanel)`: `footer` is the fixed action area above the bar (main action or `FooterStatus`), `bottomPanel` a self-framed panel such as `ScanPanel`.
- A board titled "Sheet · …" or "Filter …" on the canvas is a bottom sheet (`AikuBottomSheet`, `FilterSheet`), not a route. The opening screen holds its visibility in state.
- Aiku says SKO (stock keeping outer), never SKU, in everything staff see. Delivery note references are order references (AR24811).

## Structure

- `ui/components`: the design system's shared pieces, following the canvas's component boards C01–C26.
  - `AppChrome.kt`: `AppScreen`, `AppHeader` (`isSample` outlines a sample title), `SectionTabs`, `MainBar`, icon and avatar buttons, `SearchScanField`, `FooterStatus`.
  - `WarehouseSections.kt`: `WarehouseModule`, `WarehouseSection` (route, label, fulfilment flag), `ModuleHeader`.
  - `DeliveryBlocks.kt` (C25): state stepper, note cards, timeline, detail card, platform chip, quick actions.
  - `Lists.kt` (C26): product photo, item row, scan panel, choice rows. `FilterSheet.kt`, `SwipeRow.kt` (C24).
  - `Cards.kt`, `Controls.kt` (buttons, chips, bottom sheet C15), `Feedback.kt` (alerts, toasts via `LocalToasts`, dialog, empty states, skeletons), `Inputs.kt` (stepper, checkbox/radio/toggle rows), `FakeData.kt`, `States.kt` (`RequireAbility`).
- `ui/navigation`: type-safe routes (`AppRoutes.kt`, one per screen board), composition locals, `AppNavigator` (`open`, `switchSection`, `openTab`, `back`, `showWarehouseSwitcher`, `showReportProblem`, `signOut`).
- `ui/app`: the signed-in shell. Warehouse loading and switching, the route-to-screen map, sign out.
- Shell packages: `home`, `mywork`, `inbox`, `menu`, `search`, `scan` (scan result), `dashboard` (warehouse dashboard), `profile`, `sync` (offline queue and conflicts), `states` (full-screen and in-context states, the not-designed placeholder, and the outlined skeleton previews reached from Profile).
- Area packages: `inventory`, `locations`, `goodsin`, `goodsout`, `goodsout/extra` (waiting, sessions, trolleys, bays, More actions), `fulfilment`, `clocking`, `login`. Each area keeps its sample data in `<Area>Samples.kt` and its text in `strings_<area>.xml`.
- Icons used by the canvas are converted once as `ic_ui_<name>`; reuse them before adding `ic_<area>_<name>`.

## Local settings

`local.properties` stays out of git. Besides `sdk.dir`, debug builds read:

- `aiku.appKey`: one of the backend's `MOBILE_APP_KEYS`. Without it every mobile route answers 404.
- `aiku.devCaCertPath`: the Valet CA that signs the local site, e.g. `//wsl.localhost/Ubuntu/home/<user>/.valet/CA/LaravelValetCASelfSigned.pem`. Debug builds trust it on top of the system CAs.
- `aiku.apiBaseUrl`: optional, default `https://app.aiku.test`.
- `aiku.sentryDsn`: the key (DSN) of the `aiku-droid` Sentry project in INIKOO LTD. Both debug and release builds read it. When it is empty, crash reporting is off. Reports carry no personal data; the user is identified only by their numeric id, and screenshots and view hierarchies are off.
- `aiku.devHostAddress`: the emulator's route to the host, default `10.0.2.2`. With the backend in WSL set it to `wsl`, and the build asks `wsl.exe hostname -I` for the current WSL address: the emulator cannot reach WSL through `10.0.2.2`. Use the PC's LAN address for a physical device.

Release builds use `https://app.aiku.io` and none of the development settings.

## Design

- The screens follow the "Aiku Mobile" design canvas (navigation v3). The older "Aiku Mobile Warehouse" canvas is obsolete.
- Theme tokens for all 11 themes, light and dark, are computed in `ui/theme/Color.kt` the way the canvas mixes them. Appearance (theme and mode) is saved on the device.
- Type is Inter, with a Bitter wordmark and a Caveat tagline. The fonts are OFL, and their licences ship in `assets/licenses`.
- The sign-in watercolour backgrounds are rendered from the canvas's own SVG (`login_background`, `login_background_dark`).
- Icons are the canvas's own SVGs converted to vector drawables, named `ic_<area>_<name>` for area icons. The shared icon set (C23) is `ic_fa_<font-awesome name>`, and the feedback icons are `ic_feedback_*`.

## Focus state

Every component staff can act on shows a clear focus state in the theme's colours. The canvas draws none, but Louis asked for one on 2026-11-11. Warehouse handhelds have hardware keys, and staff may use a keyboard, so the element that has focus must always be visible.

- `colors.focus` is the focus colour: the theme colour in light mode, the theme's link colour in dark mode (the dark theme colour is too dim on dark surfaces). Never hardcode a focus colour.
- Tappable things (`clickable`, `selectable`, `toggleable`) get the focus state for free. `AikuDroidTheme` provides `AikuFocusIndication` (`ui/components/Focus.kt`) as `LocalIndication`. It keeps the ripple and, while the component has focus, draws a two-tone ring inside it: 2dp of `colors.focus`, then a 1.5dp line of `colors.background`. The second line keeps the ring visible on any fill, including primary-coloured buttons built by hand.
- When a component sets its own `indication`, wrap the ripple instead of replacing it: `AikuFocusIndication(ringColor = …, gapColor = colors.background, ripple = ripple(color = …))`. The ring must contrast with the fill: `onPrimary` on primary buttons, `onScan` on scan buttons, otherwise `colors.focus`. Never use `indication = null` on something staff can act on.
- On nav-coloured areas the ring is `navAccent` with a `nav` line, from `rememberNavFocusIndication()`. `AppHeader` (with its section tabs) and `MainBar` already provide it; anything else drawn on `colors.nav` must provide it too.
- Text fields hoist a `MutableInteractionSource`, read `collectIsFocusedAsState()` and turn it into `animateFocusProgress(isFocused)`. The border blends towards `colors.focus` with `lerp(idle, colors.focus, progress)`, and `Modifier.focusHalo(progress, …)`, placed before `clip`, adds the soft outer glow the web uses. An error border wins over focus.
- Focus never pops. Every focus visual (ring, border, glow) eases in over 160 ms and out over 120 ms with `FastOutSlowInEasing`, through `focusAnimationSpec` / `animateFocusProgress`; the ring also grows from half to full width as it fades in. Never switch a focus visual on and off with a plain `if`. Louis found the instant switch jarring on 2026-11-11.
- Every tab shows the focus state: section tabs, the bottom bar, segmented controls, scope pills, camera mode pills, and any new tab-like control. Tabs on nav-coloured areas take `rememberNavFocusIndication()`. Check a new tab with the keyboard (Tab, or D-pad) on the emulator before calling it done.
- A tab passes its own corner radius (the radius of its shape), so the ring matches every tab in the row however long the label is; the automatic shape is only for other components. The ring never covers a tab's underline: underlined tabs pass `bottomInset` so the ring ends above the bar. Louis found the pill ring on a short label and the ring swallowing the underline odd on 2026-11-11.
- Focus shows only when moving with a keyboard, D-pad or scanner keys, plus on a text field while typing. Never show a focus ring on a tap or click: the ripple and the active tab's underline are the tap feedback. Louis tried a brief ring on tap on 2026-11-11 and rejected it as weird.

## Versions

- Every version lives in `gradle/libs.versions.toml`. Do not add, upgrade or downgrade a dependency unless asked.
- AGP 9 compiles Kotlin itself, so never apply `org.jetbrains.kotlin.android` in a module. The root build file declares it with `apply false` only to pin the Kotlin Gradle plugin to the catalogue's `kotlin` version. On its own, AGP would bring Kotlin 2.2.10, and the Compose compiler plugin must match the Kotlin version exactly.
- AGP must stay within the minor version that the team's Android Studio is built against (Studio 2026.2.2 is built against AGP 9.5). A newer minor fails to sync.
- minSdk is 26 until the warehouse devices are confirmed. compileSdk and targetSdk are 37, because current AndroidX releases need compileSdk 37.
- App data is excluded from cloud backup and device transfer (`res/xml/data_extraction_rules.xml`), because the app holds staff bearer tokens.

## Verification

All of these must pass before a change is finished:

1. `./gradlew :app:assembleDebug`
2. `./gradlew :app:lintDebug`
3. `./gradlew :app:testDebugUnitTest`

## Conventions

- No code comments. Name things so the code explains itself.
- Text that staff see goes in `res/values/strings.xml` (shared) or the area's `strings_<area>.xml`, with `<area>_` keys, never hardcoded in a composable. The exception is sample data inside a `fakeData()` outline.
- Colours come only from `LocalAikuColors` tokens and `Tone`.
