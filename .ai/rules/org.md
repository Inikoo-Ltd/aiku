---
paths:
  - 'routes/grp/web/org/**'
---

# Org

## Agent organisations use grp.org.agent.* routes only
Agent orgs are a closed garden: their pages live in routes/grp/web/org/agent.php (grp.org.agent.*, no URL prefix) and 404 for other org types. SeparateAgentRoutes redirects GET grp.org.procurement.* on an agent org to the twin with the same suffix (or 404 if none), and while serving an agent route renames it to its procurement twin so shared actions' route-name matches keep working. A new agent page needs its route in agent.php; a procurement page agents must reach needs a twin there with the same suffix. Frontend route() maps procurement names to agent ones (useAgentRoutes.ts). KeepAgentStaffInTheirOrganisation and EnsureOrganisationIsAuthorised run before the rename, so any route-name check there must accept both prefixes.

## Agent supplier pages are addressed by the group supplier, with ?organisation=
INI-379 (18 Nov 2026). grp.org.agent.org_suppliers.* use {supplier} (suppliers.slug), one page per supplier. ResolveAgentOrganisationSelection turns it into the org row the shared actions take (orgSupplier, same position): the selected organisation's (?organisation=es) or, in All, the first buying organisation's (AW). It also redirects old org slugs (wdi-es → wdi?organisation=es) and stores an AgentOrganisationSelection on the request (rows, pageRow, selected, route() adds _query, selector() shared as organisationSelector). Links built with orgSupplier for the procurement twin are renamed to supplier by SeparateAgentRoutes::agentRouteParameters and useAgentRoutes.ts. ?organisation collides with the {organisation} route parameter: read it with $request->query(), never input()/->organisation; in the browser route().params is wrapped so route parameters win. Totals and lists filter only when an organisation is selected; All sums every buying organisation. The agent's own org suppliers (organisation_id = agent org, no org agent) are pages of the agent too.
