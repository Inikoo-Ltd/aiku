# aiku_droid

Native Android staff app for aiku. It covers the picker/packer warehouse flow. It uses Kotlin and Jetpack Compose, has one `:app` module, and its package is `io.aiku.droid`.

The Laravel, PHP, Vue and Pest rules in the aiku root do not apply here.

## Backend

- The app talks to aiku only through the staff API, never the database.
- Mobile routes sit under `/app/api/mobile`. They need the `X-Aiku-App-Key` header and a Sanctum bearer token with the `mobile` ability.

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
3. `./gradlew :app:testDebugUnitTest`, once tests exist

## Conventions

- No code comments. Name things so the code explains itself.
- Text that staff see goes in `res/values/strings.xml`, never hardcoded in a composable.
- `local.properties` holds the machine's `sdk.dir`. Android Studio writes it, and it stays out of git.
