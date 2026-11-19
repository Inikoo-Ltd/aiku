---
paths:
  - 'apps/**'
---

# Apps

## Mobile apps refetch permissions on every open and cache them
Staff mobile apps fetch the user's permissions from the mobile API each time the app opens or returns to the foreground, keep the last result in a local cache (excluded from backup) so the app still works offline, and clear it on sign-out. Planned backend side: when SetUserAuthorisedModels (the permission hydrator) changes a user's access, Aiku broadcasts over the websocket to that user's registered mobile devices (Sanctum `mobile|<device>` tokens, FcmToken), and the app refetches on that event. iOS: PermissionStore + PermissionUpdateSource in apps/staff-ios/Aiku/Core/Permissions.

## Mobile module access comes only from the warehouse can.* abilities
Both staff apps decide which modules, sections and shortcuts a user sees only from the `can` abilities of the selected warehouse in GET mobile/warehouses (GetMobileWarehouses::abilities, which mirror the web rules). Never derive access from permission names or hardcode it on the device; a signed-in user sees no warehouse module until the abilities arrive. Goods in shows with view_goods_in or view_returns; Backlog and Stock deliveries need view_goods_in, Returns needs view_returns. Louis, 2026-11-18; applied to iOS 2026-11-19.

## Both apps follow the user's Aiku language
The app language is the user's Aiku account language: read `language` ({code, name} or null) from `GET mobile/profile` after sign in, on each profile load and on return to the app; send a choice made in the app with `PATCH mobile/profile/language` {"language": code}. A choice is applied at once and kept pending (on the device) until the PATCH succeeds; while pending the account language never overwrites it, offline it is retried, a 422 drops it and reapplies the account language, sign out forgets it. Codes are the `lang/*.json` names (Chinese is `zh-Hans`). Every request sends the app language as `Accept-Language`, which `SetMobileApiLocale` uses for server-built texts.
