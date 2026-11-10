---
paths:
  - 'apps/**'
---

# Apps

## Mobile apps refetch permissions on every open and cache them
Staff mobile apps fetch the user's permissions from the mobile API each time the app opens or returns to the foreground, keep the last result in a local cache (excluded from backup) so the app still works offline, and clear it on sign-out. Planned backend side: when SetUserAuthorisedModels (the permission hydrator) changes a user's access, Aiku broadcasts over the websocket to that user's registered mobile devices (Sanctum `mobile|<device>` tokens, FcmToken), and the app refetches on that event. iOS: PermissionStore + PermissionUpdateSource in apps/staff-ios/Aiku/Core/Permissions.
