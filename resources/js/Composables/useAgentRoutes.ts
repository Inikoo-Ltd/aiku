const PROCUREMENT_PREFIX = "grp.org.procurement."
const AGENT_PREFIX = "grp.org.agent."

let agentOrganisations = new Set<string>()

export const setAgentOrganisations = (agents?: { slug: string }[]): void => {
	if (agents) {
		agentOrganisations = new Set(agents.map((agent) => agent.slug))
	}
}

export const isAgentOrganisation = (organisation?: string): boolean =>
	!!organisation && agentOrganisations.has(organisation)

const AGENT_PARAMETERS: Record<string, string> = {
	orgSupplier: "supplier",
	orgSupplierProduct: "supplierProduct",
}

declare const Ziggy: { routes?: Record<string, { uri?: string; parameters?: string[] }> } | undefined

const routeParameterNames = (name: string): string[] => {
	const definition = typeof Ziggy === "undefined" ? undefined : Ziggy?.routes?.[name]

	return definition?.parameters ?? [...(definition?.uri ?? "").matchAll(/{([^}?]+)\??}/g)].map((match) => match[1])
}

const agentRouteParams = (agentName: string, params: unknown): unknown => {
	if (!params || typeof params !== "object" || Array.isArray(params)) return params

	const names = routeParameterNames(agentName)

	return Object.fromEntries(
		Object.entries(params as Record<string, unknown>).map(([key, value]) => {
			const agentKey = AGENT_PARAMETERS[key]

			return [agentKey && !names.includes(key) && names.includes(agentKey) ? agentKey : key, value]
		})
	)
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

/**
 * Ziggy's route().params lays the query over the route parameters, so an agent page opened with
 * ?organisation=es would report the organisation as es instead of the agent's own; route parameters win.
 */
const withRouteParametersFirst = (router: any) =>
	new Proxy(router, {
		get(target, property) {
			if (property === "params") {
				return { ...target.params, ...target.routeParams }
			}
			const value = Reflect.get(target, property, target)

			return typeof value === "function" ? value.bind(target) : value
		},
	})

export const withAgentRoutes = <T extends (...args: any[]) => any>(route: T): T =>
	((name?: string, params?: unknown, ...rest: unknown[]) => {
		if (name === undefined && params === undefined) {
			return withRouteParametersFirst(route())
		}

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

		return route().has(agentName) ? route(agentName, agentRouteParams(agentName, params), ...rest) : route(name, params, ...rest)
	}) as T

export const asProcurementRouteName = (name?: string): string | undefined =>
	name?.startsWith(AGENT_PREFIX) ? PROCUREMENT_PREFIX + name.slice(AGENT_PREFIX.length) : name
