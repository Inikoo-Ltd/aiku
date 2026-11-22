---
paths:
  - 'app/Enums/UI/**'
---

# U I

## History is the rightmost tab icon; right-aligned tabs render reversed
Raul, 22 Nov 2026: the History icon tab is always the rightmost tab. Tabs.vue draws right-aligned (icon) tabs in a flex-row-reverse nav, so the FIRST right-aligned case in a *TabsEnum is drawn furthest right: declare HISTORY before ATTACHMENTS and any other 'align' => 'right' case (AgentTabsEnum, OrgAgentTabsEnum).
