---
paths:
  - 'app/Actions/UI/Grp/**'
---

# Grp

## grp layout cache is keyed by menu version, not deploy
INI-230 (9 Nov 2026): GetFirstLoadProps caches per user+language under GetFirstLoadProps::menuVersion(), a hash of the MENU_SOURCES files, lang/{code}.json and the route names, so a deploy that leaves the menu alone keeps every cached layout. If you build menu content from a file outside MENU_SOURCES, add it there, or users keep the old menu until `php artisan ui:recache-user-props`. A full load carries only the current organisation's menu (layout.navigation.org) and no badges. initialiseApp.ts fetches grp.json.layout.organisation_navigation when the user moves to another org, and grp.json.layout.badges after window load. Never put badges or every org's menu back into the first-load props.
