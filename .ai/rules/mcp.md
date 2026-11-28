---
paths:
  - 'app/Mcp/**'
---

# Mcp

## MCP and staff chat: own conversations only
Decided by Raul on 26 Nov 2026 (INI-427). Through MCP, a user may read only the staff chat conversations they are a member of: their own DMs, the group chats they belong to and the private channels they are in. No other user's conversations, whatever their role, and that includes engineers and admins. This goes through an app-level tool that filters on the authenticated user's membership. Raw SQL stays closed: `staff_messages`, `staff_message_*` and `slack_*` remain under `never` in config/mcp_sql_tiers.php. Every existing or new MCP tool that returns message bodies (for example StaffTasksTool) must apply the same membership check.

## Verba tools on MCP: the user's own Verba, nothing logged, one confirm token per send (INI-596)
Raul, 28 Nov 2026 (INI-596; extends "MCP and staff chat: own conversations only"). verba-tool reads Verba conversations by hasParticipant only (no supervisor or department path, cut at left_at) and Slack channels only through SlackChannel::readableBy, which adds the public V·Desks the person may read in Verba; unknown, foreign and malformed ids get the same answer (WithVerbaConversation::NOT_FOUND), and nothing is marked read. verba-send-tool sends only through SendStaffMessage and SendSlackMessage, strips U+E000 so no real mention is injected, needs the confirm_token of a preview of that exact message (used once, also Slack's idempotency key) and is limited to 20 sends a minute. No tool writes message text or private channel names anywhere others read: never through recordChange (mcp_changes), never in error strings (use slack-<id>), and LogMcpRequest keeps only VERBA_LOGGED_ARGUMENTS for any tool name containing verba. verba-channel-settings-tool is for verba-admin only, public kept channels only, saves through UpdateVerbaChannelSettings with Context audit_user set so the audit names the admin.
