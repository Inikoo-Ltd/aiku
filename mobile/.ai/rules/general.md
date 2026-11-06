---
paths:
  - .mcp.json
  - pubspec.yaml
---

# General

## The Dart MCP server comes from the official plugin, not .mcp.json

`.mcp.json` holds only `dart_boost` (its `record_rule` tool). The Dart MCP server (`dart mcp-server`) and the Flutter skills come from the official `dart-flutter` plugin, which `.claude/settings.json` enables; each machine installs it once.

Never add `dart mcp-server` to `.mcp.json`: it would run a second copy beside the plugin's. dart_boost is installed with `--no-mcp` for the same reason, because with MCP writing on it always adds its own `dart mcp-server` entry.

## Refresh the guidelines after upgrades; re-review dart_boost before upgrading it

- Run `dart run dart_boost:update` after upgrading Flutter or any package. It rewrites only the `<dart-boost-guidelines>` block in `CLAUDE.md`, so the version-specific guidance matches what actually resolves.
- dart_boost is a dev dependency pinned at 0.2.1. It comes from a single, unverified author with very few downloads, and its 0.2.1 source was read before it was installed (no network code; it writes only inside the project). Read the new version's source again before upgrading it.
- Known 0.2.1 bug: `record_rule` writes globs into YAML frontmatter unquoted, so a glob starting with `*` (for example `**/*.dart`) makes the rule file unreadable, and it silently drops out of the index. Use globs that start with a directory or file name, such as `lib/**`.
