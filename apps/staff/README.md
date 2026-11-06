<p align="center">
  <img src=".github/art/invader-sketch.svg" alt="aiku" width="140">
</p>

<h1 align="center">aiku mobile</h1>

<h3 align="center">The Android app for aiku staff.</h3>

<p align="center"><a href="https://aiku.io">aiku.io</a> · <a href="https://aiku.io/blog">Engineering notes</a> · <a href="https://github.com/Inikoo-Ltd/aiku">aiku</a></p>

<p align="center">
  A Flutter companion to the aiku back office, starting with picking on the warehouse floor.
</p>

---

## What it is

aiku mobile is a client of [aiku](https://github.com/Inikoo-Ltd/aiku). It never connects to the database. Everything goes through aiku's staff API (`/app/api`), authenticated with a Sanctum token per device, so permissions, audit trails and business rules stay in one place: an action taken in the app runs the same code as the same action in the web app.

## Status

Base project only: the Flutter app shell and the AI tooling. No features yet. Login against the staff API comes next.

## Under the hood

**Flutter 3.47 · Dart 3.13 · Android · flutter_lints · dart_boost · Dart and Flutter MCP server**

Package ID `io.aiku.aiku_mobile`. iOS is not set up yet; when it is needed, run `flutter create --platforms ios .` and build it on a Mac.

## Getting started

You need Flutter 3.47 stable, the Android SDK (through Android Studio), and an emulator or an Android phone with USB or wireless debugging turned on.

```bash
flutter pub get
flutter run
```

Before calling a change finished:

```bash
flutter analyze
dart format .
flutter test
```

## AI tooling

The repository is set up for Claude Code.

| Piece | What it does | Where |
|---|---|---|
| **dart_boost guidelines** | Dart and Flutter guidance chosen for the exact versions this project resolves | `CLAUDE.md`, inside the `<dart-boost-guidelines>` block; choices in `dart_boost.json` |
| **Project rules** | Decisions and traps recorded per area of the codebase, indexed by file glob | `.ai/rules/` |
| **dart_boost MCP server** | The `record_rule` tool, which files a rule and regenerates the index | `.mcp.json` |
| **Dart and Flutter plugin** (official) | The Dart MCP server (analysis, pub, hot reload, widget inspector, runtime errors) and the Flutter skills | `.claude/settings.json` |

**One-time setup on each machine:**

1. Open the folder in Claude Code, trust it, and approve the `dart_boost` MCP server when asked.
2. Install the Flutter plugin. The project enables it, but Claude Code does not install plugins for you:

```bash
claude plugin marketplace add flutter/agent-plugins
```

```bash
claude plugin install dart-flutter@dart-flutter
```

**After upgrading Flutter or a dependency**, recompose the guidelines so they match the new versions:

```bash
dart run dart_boost:update
```

Only the `<dart-boost-guidelines>` block is rewritten. Project-specific instructions belong in `CLAUDE.md` outside that block, and durable decisions belong in `.ai/rules/` through `record_rule`.
