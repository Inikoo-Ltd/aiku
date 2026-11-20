# aiku_droid

Native Android staff app for aiku: warehouse work (inventory, locations, goods in, goods out, fulfilment) and clocking, built so more of aiku can be added later. Not a customer app. It uses Kotlin and Jetpack Compose, has one `:app` module, and its package is `io.aiku.droid`.

The Laravel, PHP, Vue and Pest rules in the aiku root do not apply here.

## Fake data: the red dashed outline

Most screens are still skeletons built on sample data, so developers can see how each screen will look before it is wired to the API.

- **A red dashed outline (`Modifier.fakeData()`, colour `FakeDataRed`) means "fake data / skeleton, not wired to aiku yet".**
- Every block that shows sample data carries it.
- Sample data lives in `private` values in the screen file, or in an area's `*Samples.kt`.
- When you wire a block to real data, remove its sample data and its `fakeData()` outline together.
- Never outline real data. Today that is: the signed-in user (`LocalStaff`), the warehouses and their permissions (`LocalWarehouse`, `LocalWarehouses`) and appearance (`LocalAppearanceController`), the scanning preferences (Profile → Scanning), the Menu badges (`GET /warehouses/{id}/badges`), clocking (`LocalClocking`: status, sessions, methods, code, timesheets, target), Clocking & HR (`LocalLeave`, `LocalClockingAdjustments`, `LocalOvertime`, `LocalClockingCalendar`), the profile card (`GET /profile`), and Goods out (`GoodsOutApi`: the delivery note list, its counts and backlog, the delivery note screens, the scan lookup, picking (the footer and ⋯ actions from `available_actions`, their sheets, the line actions and scan to pick), packing and dispatch (line packing, scan to pack, boxes, parcels, consumables, inserts, Add shipment, the shipments block, the external shipment, finalise and dispatch, and the Documents sheet), and the 2e extras (`PickingSessionApi`: picking sessions, pick by location, pack by scan, multi-select into a session, pickers and packers now; trolleys and picked bays; cancel, note, packer and address edits; Ask CRM through `StaffChatApi`)), and Inventory and Locations (`InventoryApi`: the warehouse overview and auto-remove switch, areas, the location list, filter and scan, the location page with its audit, move and remove sheets, SKO-in-location settings, the inventory dashboard, the SKO list, filter and scan, SKO families, the SKO page and its stock actions, stock movements per SKO and per location, and Goods out's Change picking location), and Goods in (`GoodsInApi`: the incoming hub, the stock delivery list, filter and scan, the stock delivery screen with receiving, checking, the checklist and placing, its scan camera, the customer returns list and the return screen with its actions).
- The outline is dashed on purpose, so it is not confused with the solid red border of a field in error.

## Backend

- The app talks to aiku only through the staff API, never the database.
- Mobile routes sit under `/app/api/mobile`. They need the `X-Aiku-App-Key` header and a Sanctum bearer token with the `mobile` ability.
- Two-factor follows the backend's `MOBILE_TWO_FACTOR` switch: on by default in every environment, local included. A developer can set `MOBILE_TWO_FACTOR=false` in their local `.env`. An account that must use two-factor but has none sets it up in the app at sign-in (authenticator link, QR code or setup key).
- `GET /app/api/mobile/warehouses` lists the warehouses the user is authorised in (`authorisedWarehouses`). Each carries a `can` block computed with the same `authTo` permission checks the web uses:
  - `view_locations`, `edit_locations`
  - `view_inventory`, `move_stock`
  - `view_goods_in`, `book_in`, `supervise_goods_in` (Unmark as received), `edit_procurement` (Unmark as dispatched, cancel a stock delivery), `place_stock_deliveries` (Place)
  - `view_returns`, `handle_returns` (customer returns: `incoming.{wh}` or `returns.{wh}`)
  - `view_goods_out`, `pick_and_pack`, `supervise_goods_out`
  - `view_fulfilment`, `edit_fulfilment`

## Screen cache

The first screens open from a saved copy on the phone, so they show at once (Louis, 2026-11-19). The fresh answer from aiku then replaces it.

- **What is saved:** the API answers behind My work, the work badges (`/warehouses/{id}/badges`: bottom bar, Home, Menu), the warehouse dashboard, the Inbox's first page per filter, and Home's clocking card (`GET /clocking` and this week's `GET /clocking/timesheets`). Nothing else.
- **Storage:** SQLite `aiku_screen_cache.db`, table `screen_cache` (`data/cache`). One row per user, warehouse (0 for user-wide screens) and screen. The row holds the raw JSON answer, encrypted with the session cipher (`KeystoreCipher`, the key that also protects the session and the warehouse cache). The screen reads it back through the same parser as the live answer (`CachedScreens`).
- **Reading:** on opening, the screen shows the saved copy with its refreshing indicator, then asks aiku. If that fails, the saved copy stays, with the screen's usual error and "Last updated HH:mm" (`LastUpdatedNote`; the date is added when the copy is not from today). A copy that cannot be decrypted or parsed is deleted and treated as missing.
- **Replacing:** `ScreenCacheInterceptor` keeps the rows in step with every answer, whichever screen asked. A successful answer replaces its row wholesale, never merged, so anything aiku no longer returns disappears from the phone too. A 403 or 404 deletes the row.
- **Writes:** a confirmed write updates or removes the rows it affects, so a cold start never shows something the user already changed. Inbox read and read all update the saved Inbox and drop the saved badges. A clocking write drops the saved clocking. Any other warehouse write (picking, packing, returns, stock and so on) drops the saved My work, badges and dashboards. Profile, password, push and chat requests leave the rows alone.
- **Clocking:** the saved status is for display only. Clock in and out, the methods, the personal code and the shift choice use `ClockingUiState.actionableStatus`, which stays empty until the fresh status has arrived.
- **Clean-up:**
  - On app start, and at most once a day, rows not opened for 7 days are deleted. This also covers an Inbox filter or a warehouse that has not been opened for 7 days.
  - Rows of any user other than the one signed in are deleted.
  - When the warehouse list no longer has a warehouse, its rows are deleted.
  - Signing out, or any 401 (expired session), deletes everything, because handhelds are shared.

## Offline work

Line-level work (pick, not picked, waiting, pack, move, place, return puts, audits) can be done offline and is sent later (phase 8: offline design v3 and the phase 8 contract). Screens use only `OfflineWork` (`AppContainer.offlineWork`, implemented by `DeviceOfflineWork` in `data/offline`; `NoOfflineWork` is for tests and previews).

- **Headers and time:** every request carries `X-Aiku-Device` (a v4 UUID made once per install, kept in the `aiku_offline` preferences next to the sequence counters), `X-Aiku-Platform: android` and `X-Aiku-App-Version` (`1.4.2 (52)`). `ServerClock` keeps the `Date` of the last answer with the uptime clock at that moment; `performedAt` is that server time plus the uptime since (`time_source` server). After a phone restart it uses the phone clock (`time_source` device) until aiku answers again.
- **Online:** the default network has a validated internet connection (`NetworkConnectivity`).
- **Downloads:** `OfflineDownloadInterceptor` saves the answers of `GET /delivery-notes/{id}`, `/warehouses/{w}/returns/{r}`, `/warehouses/{w}/stock-deliveries/{s}`, `/locations/{id}` and `/warehouses/{w}/org-stocks/{o}`, with `seenAt` from the answer's `Date`. A write under a record whose answer carries the record (`data.id`) replaces it; a 403 or 404 deletes it. When My work loads, up to 30 assigned delivery notes in handling, handling_blocked or packing are fetched; when the returns list loads, the returns in returning that the person handles. A record downloaded in the last minute is not fetched again. Screens read a download with `downloaded(record)` and the normal response parser.
- **Storage:** SQLite `aiku_offline.db` (`SqliteOfflineStore`), tables `downloads` and `queue`. Bodies, payloads and results are encrypted with `KeystoreCipher("aiku_offline_key")`.
- **Queue:** `enqueue` saves the action with its user, warehouse, record, explicit payload, `client_request_id`, `performedAt` and `seenAt`, and asks for a sync. `shouldQueue(record)` is true offline, or while the record has WAITING or SENDING actions. `pendingFor(record)` lists the signed-in user's WAITING, SENDING and NEEDS_YOU actions on the record. `undo` deletes an action without a number and turns a numbered one into VOID, which is sent as `void`.
- **Sync:** numbers are per device and user, given at first send (`OfflineDevice`) and never reused. Batches hold at most 50 actions in number order and go to the warehouse of their actions. `applied` and `already_done` → APPLIED; `partly_applied`, `refused`, `conflict` and `blocked` → NEEDS_YOU; `void` → VOID; `missing_previous` → the numbers from `expected_sequence` are sent again (one the phone no longer holds goes as `void`). A network failure or a 5xx keeps the numbers and retries; a 401, 403 or 422 stops. After a sync the changed records are fetched again so their downloads are fresh, and NEEDS_YOU actions that `GET offline/operations?state=open` no longer lists leave the queue. APPLIED and sent VOID rows are deleted 24 hours after syncing. `flush(record)` sends now and is true when nothing is left WAITING, SENDING or NEEDS_YOU for the record.
- **Battery (WorkManager):** one unique one-time work (`offline_sync`) with a CONNECTED constraint, exponential backoff from 1 minute, at most 5 attempts and at most 4 batches per run (a follow-up is appended when more is waiting). Triggers: an action queued, network back, the app to the foreground, clock-out (expedited, replaces the waiting work) and Sync now (runs at once in the app). A 6-hourly periodic work (connected, battery not low) exists only while the signed-in user has waiting actions. Background runs never resolve anything; when they leave actions that need the person, a local notification says "N actions need you" and opens Resolve conflicts.
- **Shared handhelds:** actions sync only while their own user is signed in. Signing out offline with waiting actions asks first and keeps them. When someone else signs in while the queue holds another user's waiting actions, the app sends `POST offline/waiting-notice` for each such user, once per sign-in.
- **Purge:** downloads follow the screen cache's rules (7 days unopened, other users, sign-out, any 401). The queue survives sign-out, a 401 and every purge.
- **Shell:** `OfflineIndicator` sits under every `AppScreen` header: "Offline · N waiting" offline, "N need you" (opens Resolve conflicts), "Syncing N" while work is waiting, and nothing otherwise.

## Permissions

- The chosen warehouse (`LocalWarehouse.current`) scopes every screen. Its `can` (`WarehouseAbilities`) decides what the user sees.
- Wrap each module screen in `RequireAbility(ability = Ability.…)`. When access is missing, the no-access screen names the permission (`Ability.permission`). `Ability` and `WarehouseAbilities.allows()` live in `data/api/WarehouseApi.kt`.
- **Hide** tabs, menu rows, dashboard sections and actions the user cannot use. Never grey them out.
- Only the actions allowed for the record's state AND the user's rights appear, exactly as on the web. With none, a short status line (`FooterStatus`) says what is missing.
- The one exception: an `available_actions` key with a `disabled_reason` shows that reason in place of its button (`DisabledActionNotice`), because the server says why it cannot be used yet.
- Picker/packer roles (Dispatch clerk) get goods out, picking/packing and location viewing, but not the SKO screens: the web does not give them those either.
- Goods in shows with `view_goods_in` or `view_returns`: its backlog and stock deliveries need `view_goods_in`, its returns `view_returns`, and the return actions `handle_returns`. A returns clerk sees only Returns.
- Clocking & HR is for every signed-in staff member, with no ability check.
- Staff with no warehouse role get Home's staff variant (clocking, profile) and no Scan slot. For them `LocalWarehouses` is empty and `LocalWarehouse` is `NoWarehouseAccess`, whose abilities are all false. Hide warehouse-only parts when `LocalWarehouses.current.isEmpty()`.

## Navigation

- One fixed bottom bar (`MainBar`, `MainTab`): Home · My work · Scan · Inbox · Menu. It never changes per screen. The Scan slot shows only when the account has a warehouse.
- Menu (`MenuRoute`) lists every module and section the account can open, filtered by permission, plus Profile, Appearance and Log out.
- Inside a module, `ModuleHeader(WarehouseSection.X)` draws the header (context line over the title, back to wherever you came from) and the section tabs in the web menu order. Section tabs are underlined tabs (accent text and bar on the active one) in every module, Clocking included: Louis chose tabs over the canvas pills on 2026-11-11. Fulfilment is not a module: its sections sit inside Goods in, Inventory and Goods out and need `view_fulfilment`.
- The section tab row never scrolls by itself. Switching section hands the row's scroll position to the next screen (`TabHandoff` in `AppChrome.kt`), so the row stays exactly where it was when the tab was tapped. Louis rejected the jump to the active tab on 2026-11-11.
- `AppScreen(header, activeTab, showBar, footer, bottomPanel)`: `footer` is the fixed action area above the bar (main action or `FooterStatus`), `bottomPanel` a self-framed panel such as `ScanPanel`.
- Every screen that loads data from aiku passes `refresh = PullRefresh(isRefreshing, onRefresh, isEnabled)` (`Refresh.kt`) to `AppScreen`: pulling down reloads through the ViewModel's own load (`refresh()`), the loaded content stays on screen meanwhile (no skeleton), and pulling is off while a write or a queued scan is running. Scan screens (Place item included), sheets, Search, Menu and the Ask CRM chat (it polls) have none.
- A board titled "Sheet · …" or "Filter …" on the canvas is a bottom sheet (`AikuBottomSheet`, `FilterSheet`), not a route. The opening screen holds its visibility in state.
- Aiku says SKO (stock keeping outer), never SKU, in everything staff see. Delivery note references are order references (AR24811).
- Routes to records carry the record's id (`SkuRoute`, `SkuFamilyRoute`, `AreaRoute`, `LocationRoute`, `LocationSkuRoute`, the delivery note routes), plus a code or reference for the title. Every endpoint takes ids, and the scan result gives them.

## Structure

- `ui/components`: the design system's shared pieces, following the canvas's component boards C01–C26.
  - `AppChrome.kt`: `AppScreen`, `AppHeader` (`isSample` outlines a sample title), `SectionTabs`, `MainBar`, icon and avatar buttons, `SearchScanField`, `FooterStatus`.
  - `WarehouseSections.kt`: `WarehouseModule`, `WarehouseSection` (route, label, fulfilment flag), `ModuleHeader`.
  - `DeliveryBlocks.kt` (C25): state stepper, note cards, timeline, detail card, platform chip, quick actions.
  - `Lists.kt` (C26): product photo, item row, scan panel, choice rows. `FilterSheet.kt`, `SwipeRow.kt` (C24).
  - `Cards.kt`, `Controls.kt` (buttons, chips, bottom sheet C15), `Feedback.kt` (alerts, toasts via `LocalToasts`, dialog, empty states, skeletons), `Inputs.kt` (stepper, checkbox/radio/toggle rows), `FakeData.kt`, `States.kt` (`RequireAbility`).
- `ui/navigation`: type-safe routes (`AppRoutes.kt`, one per screen board), composition locals, `AppNavigator` (`open`, `switchSection`, `openTab`, `back`, `showWarehouseSwitcher`, `showReportProblem`, `signOut`).
- `ui/app`: the signed-in shell. Warehouse loading and switching, the route-to-screen map, sign out.
- Shell packages: `home`, `mywork`, `inbox`, `menu`, `search`, `scan` (scan result), `dashboard` (warehouse dashboard), `profile`, `sync` (offline queue and conflicts), `states` (full-screen and in-context states, the not-designed placeholder, and the outlined skeleton previews reached from Profile), `splash` (the start-up splash).
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

## Release

Pilot builds reach testers through Firebase App Distribution. There is no Play Console yet.

- **Two Firebase projects, one per build type (Louis, 2026-11-19):** debug builds use `aiku-demo` (local backends), release builds use `aiku-production` (the production backend pushes through it). Put each project's `google-services.json` at `app/src/debug/google-services.json` and `app/src/release/google-services.json`; both are gitignored. A phone only receives pushes from the project its build was made with, so never ship a release build with the demo config. Without a release config the release build still succeeds, with push disabled (Gradle warns).
- App Distribution for pilot builds is in `aiku-production`.

- **The release keystore, its passwords and the App Distribution service account file never go in git.** Louis or Raul create and keep the keystore. Keep all three outside the checkout and point to them from `local.properties`. `*.jks` and `*.keystore` are ignored as a second line of defence only.
- `local.properties` keys for release:
  - `aiku.releaseStoreFile`: absolute path to the release keystore.
  - `aiku.releaseStorePassword`, `aiku.releaseKeyAlias`, `aiku.releaseKeyPassword`.
  - `aiku.appDistributionCredentials`: optional, absolute path to the Firebase service account JSON. Without it the upload uses `GOOGLE_APPLICATION_CREDENTIALS` or a `firebase login` session.
  - `aiku.appDistributionGroups`: optional, comma-separated tester groups, default `warehouse-pilot`.
- Without all four signing keys, or when the store file does not exist, the release build still succeeds but is unsigned (`app-release-unsigned.apk`). `assembleRelease` ends with a warning that says which key is missing, and `appDistributionUploadRelease` stops before uploading.
- Versions are `aiku.versionCode` and `aiku.versionName` in `gradle.properties`, which is committed. For every build that leaves a developer's machine, raise `aiku.versionCode` by 1 and set `aiku.versionName` to match (0.1.0 → 0.1.1). The minimum-version check compares `versionCode`, and Android will not install a lower code over a higher one. The same keys in `local.properties` override them, for a one-off local build only.
- R8 (minify) is off. It has not been checked on a device, and kotlinx-serialization, OkHttp and Firebase need keep rules. Turn it on only together with a check that the release build starts and signs in.
- Upload to App Distribution (needs `app/src/release/google-services.json`):
  1. Raise the version in `gradle.properties`.
  2. Optionally put the testers' notes in `app/release-notes.txt`; the upload uses it when present.
  3. Run `./gradlew :app:assembleRelease :app:appDistributionUploadRelease`. The upload task does not build the APK by itself.

## Design

- The screens follow the "Aiku Mobile" design canvas (navigation v3). The older "Aiku Mobile Warehouse" canvas is obsolete.
- Theme tokens for all 11 themes, light and dark, are computed in `ui/theme/Color.kt` the way the canvas mixes them. Appearance (theme and mode) is saved on the device.
- Type is Inter, with a Bitter wordmark and a Caveat tagline. The fonts are OFL, and their licences ship in `assets/licenses`.
- The sign-in watercolour backgrounds are rendered from the canvas's own SVG (`login_background`, `login_background_dark`).
- The start-up splash (Louis, 2026-11-19) is that watercolour with the invader at 42% of the screen's shorter side, exactly centred. It covers a fresh launch until the app knows whether to show sign-in, Home or the update screen (at least 400 ms, a small progress ring after 1.5 s), then fades out over 250 ms. It never shows on a return from the background, a rotation or a language restart. The system splash (Android 12+) and the window background use `splash_background` (`values/splash.xml`, `values-night/splash.xml`), the watercolour's base colour, with the invader as `aiku_invader_splash`.
- Icons are the canvas's own SVGs converted to vector drawables, named `ic_<area>_<name>` for area icons. The shared icon set (C23) is `ic_fa_<font-awesome name>`, and the feedback icons are `ic_feedback_*`.

## Scanning

- Scans arrive through one `ScanController` (`LocalScanController`, `ScanEffect`), from the camera (`CameraBarcodePreview`) or a hardware scanner that types like a keyboard (`HardwareScanDetector`, fed by a `Window.Callback` in `MainActivity`). A burst of 3 or more characters, 50 ms or less apart and ending in Enter, is a scan; slower typing and anything typed into a focused text field are left alone.
- Each scan goes to the topmost screen that listens, and only while that screen is resumed. Only warehouse screens and clocking listen; elsewhere a scan does nothing.
- `GET /warehouses/{id}/scan` resolves a code to an SKO, a location, a delivery note or a stock delivery. Each kind is returned only with its own view right, so a dispatch clerk without inventory view gets `unknown` for an SKO.
- Goods in: the stock delivery list opens a scanned stock delivery; the stock delivery screen listens only on its To check and To place tabs, while the user can check or place, and jumps to the scanned SKO's line (a choice sheet when it is on several lines). The place screen takes a scanned location.
- Every scan counts 1. The shared `ScanPanel` (C26) offers "Pick all N" and − / + for amounts.
- Profile → Scanning is stored on the phone (`DeviceSettings.scanPreferences`, `ScanPreferences`; defaults Automatic, sound on, vibration on) and reaches screens through `ScanController.preferences`.
- The screens that open the camera by themselves (the Scan tab, the stock delivery camera and the clocking QR scan) ask `rememberScanCamera()`. Automatic starts the camera unless an external, non-virtual, alphabetic keyboard (a Bluetooth or USB scanner) is connected, re-checked when input devices change; before Android 10 any physical alphabetic keyboard counts, because Android cannot tell external ones apart. Bluetooth scanner never starts it. Camera always does. Without the camera the screen shows `ScannerReadyNotice` with "Use the camera this time". Keyboard-wedge scans are accepted in every mode. The inline "Use the camera" toggles (scan to pick, pick by location, pack by scan, location pickers) stay as they are in every mode.
- Feedback (`ScanFeedback`, `DeviceScanSignals`): a short beep (media volume) and a short vibration for every accepted scan, given once by `ScanController.emit` for hardware scans and by `CameraBarcodePreview` for camera reads. A low error tone and a double vibration when the scan is not recognised or not found here: each scan flow calls `LocalScanController.current.feedback.rejected()` where it shows its miss (`ScanMissEffect` for misses held in state). Connection and server failures keep their message without the error tone. Sound and vibration follow the Profile switches; phones without a vibrator only beep.
- Scan to pick (`POST /delivery-notes/{id}/scan-pick`) listens on the picking screen and on `ScanToPickScreen`, only while the note is in handling, the user can act on it, the organisation allows scan to pick and the note is not dropshipping. Scans queue one at a time in `DeliveryNoteViewModel`.
- Scan to pack (`POST /delivery-notes/{id}/scan-pack`) listens on the delivery note screen only while the note is in packing, the user can act on it, the organisation allows scan to pack and the note is not dropshipping. It shares the scan queue and `ScanPanel` with scan to pick.
- In a picking session, Pick by location and Pack by scan listen only while the session offers `scan_pick` / `scan_pack`, and post to `/picking-sessions/{id}/scan-pick` / `scan-pack` through their own queue in `PickingSessionViewModel`.

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
