import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"
import { route as npmRoute } from "ziggy-js"
import { cacheCurrentRoute } from "../../resources/js/Composables/cacheCurrentRoute.ts"

const routesScriptModule = { exports: {} as any }
new Function("module", "exports", readFileSync(new URL("../../vendor/tightenco/ziggy/dist/route.umd.js", import.meta.url), "utf8"))(routesScriptModule, routesScriptModule.exports)
const routesScriptRoute = routesScriptModule.exports

const location = { href: "", host: "app.aiku.test", pathname: "", search: "", hash: "" }
Object.assign(globalThis, { window: { location } })

const visit = (path: string, search = "") => Object.assign(location, { pathname: path, search, href: `https://app.aiku.test${path}${search}` })

const config = {
	url: "https://app.aiku.test",
	port: null,
	defaults: {},
	routes: {
		"grp.org.chat.supervision": { uri: "org/{organisation}/chat/supervision", methods: ["GET", "HEAD"] },
		"grp.org.shops.show.ordering.orders.show": { uri: "org/{organisation}/shops/{shop}/orders/{order}", methods: ["GET", "HEAD"] },
	} as Record<string, object>,
}
for (let i = 0; i < 500; i++) {
	config.routes[`filler.${i}`] = { uri: `filler${i}/{a}`, methods: ["GET"] }
}

const reads = (route: (...args: any[]) => any) => {
	const current = () => route(undefined, undefined, undefined, config)
	return [
		["/org/aw/chat/supervision", ""],
		["/org/aw/chat/supervision", "?channel=whatsapp&filter[state]=open&elements[]=a"],
		["/org/sk/shops/uk/orders/123", ""],
		["/nowhere", ""],
	].map(([path, search]) => {
		visit(path, search)
		return {
			name: current().current(),
			params: current().params,
			routeParams: current().routeParams,
			queryParams: current().queryParams,
			inChat: current().current("grp.org.chat.*"),
			sameOrder: current().current("grp.org.shops.show.ordering.orders.show", { order: 123 }),
			otherOrder: current().current("grp.org.shops.show.ordering.orders.show", { order: 124 }),
		}
	})
}

const cachedMethods = (router: object) => {
	const prototype = Object.getPrototypeOf(router)
	return Object.getOwnPropertyNames(prototype).filter((name) => Object.getOwnPropertyDescriptor(prototype, name)?.value?.isCachedByAiku)
}

for (const [copy, route] of [["npm package", npmRoute], ["@routes script", routesScriptRoute]] as const) {
	test(`${copy}: the current route is cached without changing any answer`, () => {
		const before = reads(route)

		cacheCurrentRoute(route(undefined, undefined, undefined, config))

		assert.equal(cachedMethods(route(undefined, undefined, undefined, config)).length, 1)
		assert.deepEqual(reads(route), before)
	})

	test(`${copy}: a caller changing what it read does not change the next read`, () => {
		cacheCurrentRoute(route(undefined, undefined, undefined, config))
		visit("/org/aw/chat/supervision", "?filter[state]=open")

		route(undefined, undefined, undefined, config).params.filter.state = "changed"

		assert.equal(route(undefined, undefined, undefined, config).params.filter.state, "open")
	})
}
