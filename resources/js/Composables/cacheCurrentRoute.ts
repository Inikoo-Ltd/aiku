/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 31 Oct 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

type ZiggyConfig = { routes: object, absolute?: boolean, url?: string, location?: unknown }

const copy = (value: any): any => {
	if (Array.isArray(value)) return value.map(copy)
	if (!value || typeof value !== "object") return value

	return Object.assign(Object.create(Object.getPrototypeOf(value)), Object.fromEntries(Object.entries(value).map(([key, item]) => [key, copy(item)])))
}

type RouteDefinition = { uri: string, methods?: string[], wheres?: Record<string, string> }
type RouteShape = { name: string, definition: RouteDefinition, literals: Array<[number, string]>, fixedLength: number, exact: boolean }

const shapesByRouteList = new WeakMap<object, RouteShape[]>()

const shapesOf = (routes: Record<string, RouteDefinition>): RouteShape[] => {
	let shapes = shapesByRouteList.get(routes)
	if (shapes) return shapes

	shapes = Object.entries(routes).filter(([, definition]) => definition.methods?.includes("GET")).map(([name, definition]) => {
		const segments = definition.uri === "/" ? [] : definition.uri.split("/")
		const literals: Array<[number, string]> = []
		let fixedLength = 0
		for (const segment of segments) {
			const params = segment.match(/{([^}?]*)\??}/g)?.map((param) => param.replace(/[{}?]/g, "")) ?? []
			const matchesOneSegment = !/[?^|\\]/.test(segment) && !params.some((param) => definition.wheres?.[param])
			if (!matchesOneSegment) break
			if (!params.length) literals.push([fixedLength, segment])
			fixedLength++
		}

		return { name, definition, literals, fixedLength, exact: fixedLength === segments.length }
	})
	shapesByRouteList.set(routes, shapes)

	return shapes
}

const decodedOrNull = (text: string): string | null => {
	try {
		return decodeURI(text)
	} catch {
		return null
	}
}

const routesThatCanMatch = (routes: Record<string, RouteDefinition>, config: ZiggyConfig): Record<string, RouteDefinition> | null => {
	const basePath = String(config.url ?? "").replace(/^\w*:\/\/[^/]+/, "")
	const { pathname } = window.location
	if (basePath || decodedOrNull(pathname) === null) return null

	const path = pathname.replace(/^\/+/, "").replace(/\/$/, "")
	const segments = path === "" ? [] : path.split("/")
	const decodedSegments = segments.map((segment) => decodedOrNull(segment))

	const fits = ({ literals, fixedLength, exact }: RouteShape) =>
		(exact ? segments.length === fixedLength : segments.length >= fixedLength)
		&& segments.slice(0, fixedLength).every((segment) => segment !== "")
		&& literals.every(([index, literal]) => segments[index] === literal || decodedSegments[index] === literal)

	return Object.fromEntries(shapesOf(routes).filter(fits).map(({ name, definition }) => [name, definition]))
}

const resolveAmong = (router: Record<string, any>, config: ZiggyConfig, resolve: (this: object) => any) => {
	const configName = Object.keys(router).find((name) => router[name] === config)
	const routes = configName ? routesThatCanMatch(config.routes as Record<string, RouteDefinition>, config) : null
	if (!configName || !routes) return resolve.call(router)

	const scoped = Object.create(router)
	scoped[configName] = { ...config, routes }

	return resolve.call(scoped)
}

/**
 * Ziggy finds the current route by testing the address against every route, one regex each: with
 * 4,008 grp routes that is about 50 ms on a staff computer, paid again by every route().params,
 * route().routeParams or route().current() (1,500 call sites, some in templates), INI-145. The
 * answer only changes with the address, so the last one is kept per route list. Each route() call
 * copies Ziggy's config, but the route list in it is shared, so that is what the answer is kept on.
 * A new address still cost one full pass (Ziggy builds and compiles a regex per route, ~180 ms at
 * 4x CPU when the inbox opens), so Ziggy is only handed the routes whose fixed segments fit the
 * path, in their original order: the first match, and so the answer, is the one Ziggy would give.
 *
 * Ziggy's copies (the @routes script, the npm package as built and as served by Vite in dev) name
 * the resolver and the config differently, so both are found by what they are; if a Ziggy upgrade
 * changes that, nothing is cached.
 */
export const cacheCurrentRoute = (router: unknown): void => {
	const prototype = router ? Object.getPrototypeOf(router) : null
	if (!prototype) return

	const resolverName = Object.getOwnPropertyNames(prototype).find((name) => {
		const method = Object.getOwnPropertyDescriptor(prototype, name)?.value
		return name !== "constructor" && typeof method === "function" && String(method).includes("matchesUrl") && String(method).includes(".routes")
	})
	if (!resolverName || prototype[resolverName].isCachedByAiku) return

	const resolve = prototype[resolverName]
	const lastByRouteList = new WeakMap<object, { key: string, resolved: any }>()

	const cached = function (this: object, url?: string) {
		const config = Object.values(this).find((value) => value && typeof value === "object" && "routes" in value) as ZiggyConfig | undefined
		if (url !== undefined || !config || config.location) return resolve.call(this, url)

		const key = `${window.location.href} ${config.absolute} ${config.url}`
		let last = lastByRouteList.get(config.routes)
		if (last?.key !== key) {
			last = { key, resolved: resolveAmong(this as Record<string, any>, config, resolve) }
			lastByRouteList.set(config.routes, last)
		}

		const { resolved } = last
		return { ...resolved, params: copy(resolved.params), query: copy(resolved.query) }
	}
	cached.isCachedByAiku = true
	prototype[resolverName] = cached
}
