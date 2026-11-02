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

/**
 * Ziggy finds the current route by testing the address against every route, one regex each: with
 * 4,008 grp routes that is about 50 ms on a staff computer, paid again by every route().params,
 * route().routeParams or route().current() (1,500 call sites, some in templates), INI-145. The
 * answer only changes with the address, so the last one is kept per route list. Each route() call
 * copies Ziggy's config, but the route list in it is shared, so that is what the answer is kept on.
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
			last = { key, resolved: resolve.call(this) }
			lastByRouteList.set(config.routes, last)
		}

		const { resolved } = last
		return { ...resolved, params: copy(resolved.params), query: copy(resolved.query) }
	}
	cached.isCachedByAiku = true
	prototype[resolverName] = cached
}
