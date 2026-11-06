---
paths:
  - android/**
---

# Android

## Plain HTTP is allowed only in debug builds, only for 10.0.2.2

`android/app/src/debug/res/xml/network_security_config.xml`, referenced from the debug `AndroidManifest.xml`, permits cleartext to `10.0.2.2` so the emulator can reach local aiku. Release builds keep Android's default (HTTPS only). Never add a cleartext permission to the main manifest. The main manifest declares `INTERNET`, which release builds need.
