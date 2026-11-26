---
paths:
  - 'app/Mcp/**'
---

# Mcp

## MCP and staff chat: own conversations only
Decided by Raul on 26 Nov 2026 (INI-427). Through MCP, a user may read only the staff chat conversations they are a member of: their own DMs, the group chats they belong to and the private channels they are in. No other user's conversations, whatever their role, and that includes engineers and admins. This goes through an app-level tool that filters on the authenticated user's membership. Raw SQL stays closed: `staff_messages`, `staff_message_*` and `slack_*` remain under `never` in config/mcp_sql_tiers.php. Every existing or new MCP tool that returns message bodies (for example StaffTasksTool) must apply the same membership check.
