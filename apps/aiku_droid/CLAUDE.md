# aiku_droid

Native Android staff app for aiku. It covers the picker/packer warehouse flow. It uses Kotlin and Jetpack Compose, has one `:app` module, and its package is `io.aiku.droid`.

The Laravel, PHP, Vue and Pest rules in the aiku root do not apply here.

## Fake data: the red dashed outline

Most screens are still skeletons built on sample data, so developers can see how each screen will look before it is wired to the API.

- **A red dashed outline (`Modifier.fakeData()`, colour `FakeDataRed`) means "fake data / skeleton, not wired to aiku yet".**
- Every block that shows sample data carries it.
- Sample data lives in `private` values in the screen file, or in an area's `*Samples.kt`.
- When you wire a block to real data, remove its sample data and its `fakeData()` outline together.
- Never outline real data. Today that is: the signed-in user (`LocalStaff`), the warehouses and their permissions (`LocalWarehouse`, `LocalWarehouses`) and appearance (`LocalAppearanceController`).
- The outline is dashed on purpose, so it is not confused with the solid red border of a field in error.

## Backend

- The app talks to aiku only through the staff API, never the database.
- Mobile routes sit under `/app/api/mobile`. They need the `X-Aiku-App-Key` header and a Sanctum bearer token with the `mobile` ability.
- The backend skips two-factor checks when `APP_ENV=local`, so the code step only appears against staging or production. The unit tests cover it.
- `GET /app/api/mobile/warehouses` lists the warehouses the user is authorised in (`authorisedWarehouses`). Each carries a `can` block computed with the same `authTo` permission checks the web uses:
  - `view_locations`, `edit_locations`
  - `view_inventory`, `move_stock`
  - `view_goods_in`, `book_in`
  - `view_goods_out`, `pick_and_pack`, `supervise_goods_out`
  - `view_fulfilment`

## Permissions

- The chosen warehouse (`LocalWarehouse.current`) scopes every screen. Its `can` (`WarehouseAbilities`) decides what the user sees.
- Wrap each module screen in `RequireAbility(isAllowed = can.…)`.
- **Hide** tabs, menu rows, dashboard sections and actions the user cannot use. Never grey them out.
- Picker/packer roles (Dispatch clerk) get goods out, picking/packing and location viewing, but not the SKU screens: the web does not give them those either.

## Structure

- `ui/components`: the design system's shared pieces. `AppScreen`/`AppHeader`/tab bar, cards, list rows, badges (`Tone`), stats, buttons, search, chips, segmented control, bottom sheet, `fakeData()`, `RequireAbility`.
- `ui/navigation`: type-safe routes (`AppRoutes.kt`) and the composition locals plus `AppNavigator`.
- `ui/app`: the signed-in shell. Warehouse loading and switching, the route-to-screen map, sign out.
- One package per area: `dashboard`, `menu`, `warehouse`, `locations`, `inventory`, `goodsin`, `goodsout`, `scan`, `actions`, `profile`, `search`, `notifications`, `login`.

## Local settings

`local.properties` stays out of git. Besides `sdk.dir`, debug builds read:

- `aiku.appKey`: one of the backend's `MOBILE_APP_KEYS`. Without it every mobile route answers 404.
- `aiku.devCaCertPath`: the Valet CA that signs the local site, e.g. `//wsl.localhost/Ubuntu/home/<user>/.valet/CA/LaravelValetCASelfSigned.pem`. Debug builds trust it on top of the system CAs.
- `aiku.apiBaseUrl`: optional, default `https://app.aiku.test`.
- `aiku.devHostAddress`: the emulator's route to the host, default `10.0.2.2`. With the backend in WSL set it to `wsl`, and the build asks `wsl.exe hostname -I` for the current WSL address: the emulator cannot reach WSL through `10.0.2.2`. Use the PC's LAN address for a physical device.

Release builds use `https://app.aiku.io` and none of the development settings.

## Design

- The screens follow the "Aiku Mobile Warehouse" design canvas.
- Theme tokens for all 11 themes, light and dark, are computed in `ui/theme/Color.kt` the way the canvas mixes them. Appearance (theme and mode) is saved on the device.
- Type is Inter, with a Bitter wordmark and a Caveat tagline. The fonts are OFL, and their licences ship in `assets/licenses`.
- The sign-in watercolour backgrounds are rendered from the canvas's own SVG (`login_background`, `login_background_dark`).
- Icons are the canvas's own SVGs converted to vector drawables, named `ic_<area>_<name>` for area icons.

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
