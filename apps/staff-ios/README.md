# Aiku iOS

Native SwiftUI companion app for the Aiku Laravel backend. iOS 17+, Swift 6.

The Xcode project is generated from `project.yml` by [XcodeGen](https://github.com/yonaskolb/XcodeGen). `Aiku.xcodeproj` is not committed; regenerate it after pulling or after editing `project.yml`.

## Setup

```bash
brew install xcodegen
```

Set your signing team (find it in Xcode > Settings > Accounts, or developer.apple.com > Membership). The file is gitignored:

```bash
cd apps/staff-ios && cp Config/Signing.xcconfig.example Config/Signing.xcconfig
```

```bash
xcodegen generate
```

```bash
open Aiku.xcodeproj
```

## Layout

```
Aiku/
  App/          entry point, root (sign in or main) and the navigation stack
  Core/
    Theme/      11 Aiku themes, light and dark palette, ThemeStore
    UI/         shared components (header, tab bar, buttons, rows, sheets, scan screen)
    Navigation/ AppRouter, one route enum per module, module section tabs
    Models/     Foundation-only shared types (Tone, Chip, AppIcon, Session)
    Permissions/ permission cache and refetch
  Features/     one folder per module; pure logic and sample data in Models/
  Networking/   APIClient, endpoints, errors
  Resources/    assets (Info.plist is generated)
AikuTests/      unit tests (Swift Testing)
AikuUITests/    UI tests (XCTest)
Config/         per-configuration xcconfig files
```

The screens follow the Aiku Mobile design canvas and run on in-memory sample data; nothing is connected to the API yet. Debug builds have a Developer card at the bottom of Profile with the permission sync status and a gallery of the error, empty and loading states.

## Configurations

| | Debug | Release |
| --- | --- | --- |
| Bundle id | `com.inikoo.aiku.dev` | `com.inikoo.aiku` |
| API base URL | `http://aiku.test/app/api` | `https://aiku.io/app/api` |

The signing team comes from `Config/Signing.xcconfig` (`DEVELOPMENT_TEAM`), which each developer creates locally. A free Personal Team is enough to run on your own iPhone; use the company team when archiving for the App Store. Debug uses a separate bundle id so a personal team never claims `com.inikoo.aiku`.

An iPhone cannot resolve `aiku.test`. To reach your local Laravel server from the phone, copy the example and point it at your Mac's LAN IP (the file is gitignored):

```bash
cp Config/Local.xcconfig.example Config/Local.xcconfig
```

Every mobile API route needs the `X-Aiku-App-Key` header (one of the backend's `MOBILE_APP_KEYS`). Put it in the gitignored secrets file:

```bash
cp Config/Secrets.xcconfig.example Config/Secrets.xcconfig
```

Write `//` as `/$()/` inside xcconfig values, otherwise the rest of the line is treated as a comment.

## Build and run

Compile for any iOS device without signing:

```bash
xcodebuild build -scheme Aiku -destination 'generic/platform=iOS' -derivedDataPath build CODE_SIGNING_ALLOWED=NO
```

Install and run on a connected iPhone (Developer Mode on, phone unlocked):

```bash
xcrun devicectl list devices
```

```bash
xcodebuild build -scheme Aiku -destination 'platform=iOS,name=<your iPhone name>' -derivedDataPath build -allowProvisioningUpdates
```

```bash
xcrun devicectl device install app --device <UDID> build/Build/Products/Debug-iphoneos/Aiku.app
```

```bash
xcrun devicectl device process launch --device <UDID> com.inikoo.aiku.dev
```

Or select the phone in Xcode and press Cmd+R.

## Tests

Unit tests on a connected iPhone:

```bash
xcodebuild test -scheme Aiku -destination 'platform=iOS,name=<your iPhone name>' -only-testing:AikuTests -derivedDataPath build -allowProvisioningUpdates
```

Drop `-only-testing:AikuTests` to run the UI tests too.

## API client

`APIClient` builds requests against `AikuAPIBaseURL` from Info.plist, sends `Accept: application/json`, and adds `Authorization: Bearer <token>` when its `tokenProvider` returns a Sanctum token. Add endpoints as static members on `Endpoint`:

```swift
static func shop(_ id: Int) -> Endpoint { Endpoint(path: "shops/\(id)") }
```

