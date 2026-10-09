---
paths:
  - 'routes/grp/web/org/**'
---

# Org

## Agent organisations use grp.org.agent.* routes only
Agent orgs are a closed garden: their pages live in routes/grp/web/org/agent.php (grp.org.agent.*, no URL prefix) and 404 for other org types. SeparateAgentRoutes redirects GET grp.org.procurement.* on an agent org to the twin with the same suffix (or 404 if none), and while serving an agent route renames it to its procurement twin so shared actions' route-name matches keep working. A new agent page needs its route in agent.php; a procurement page agents must reach needs a twin there with the same suffix. Frontend route() maps procurement names to agent ones (useAgentRoutes.ts). KeepAgentStaffInTheirOrganisation and EnsureOrganisationIsAuthorised run before the rename, so any route-name check there must accept both prefixes.
