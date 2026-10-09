const PROCUREMENT_PREFIX = "grp.org.procurement."
const AGENT_PREFIX = "grp.org.agent."

let agentOrganisations = new Set<string>()

export const setAgentOrganisations = (agents?: { slug: string }[]): void => {
	if (agents) {
		agentOrganisations = new Set(agents.map((agent) => agent.slug))
	}
}

const organisationOf = (params: unknown): string | undefined => {
	if (typeof params === "string") return params
	if (Array.isArray(params)) return params[0]
	if (params && typeof params === "object") {
		const organisation = (params as Record<string, unknown>).organisation
		return typeof organisation === "string" ? organisation : undefined
	}
	return window.location.pathname.match(/^\/org\/([^/]+)/)?.[1]
}

export const withAgentRoutes = <T extends (...args: any[]) => any>(route: T): T =>
	((name?: string, params?: unknown, ...rest: unknown[]) => {
		if (typeof name !== "string" || !name.startsWith(PROCUREMENT_PREFIX)) {
			return route(name, params, ...rest)
		}

		const organisation = organisationOf(params)
		if (!organisation || !agentOrganisations.has(organisation)) {
			return route(name, params, ...rest)
		}

		if (name === `${PROCUREMENT_PREFIX}dashboard`) {
			return route("grp.org.dashboard.show", { organisation }, ...rest)
		}

		const agentName = AGENT_PREFIX + name.slice(PROCUREMENT_PREFIX.length)

		return route(route().has(agentName) ? agentName : name, params, ...rest)
	}) as T
