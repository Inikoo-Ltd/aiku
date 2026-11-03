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

let uriReads = 0
const trickyConfig = {
	url: "https://app.aiku.test",
	port: null,
	defaults: {},
	routes: {
		"home": { uri: "/", methods: ["GET", "HEAD"] },
		"supervision.store": { uri: "org/{organisation}/chat/supervision", methods: ["POST"] },
		"org.page": { uri: "org/{organisation}/{page}", methods: ["GET", "HEAD"] },
		"supervision": { uri: "org/{organisation}/chat/supervision", methods: ["GET", "HEAD"] },
		"order": { uri: "org/{organisation}/shops/{shop}/orders/{order}", methods: ["GET", "HEAD"] },
		"orders": { uri: "orders/{order?}", methods: ["GET", "HEAD"] },
		"files": { uri: "files/{path}", methods: ["GET", "HEAD"], wheres: { path: ".*" } },
		"download": { uri: "download/{name}.{extension}", methods: ["GET", "HEAD"] },
		"cafe": { uri: "café/menu", methods: ["GET", "HEAD"] },
		"tenant": { uri: "tenant/{id}", methods: ["GET", "HEAD"], domain: "{team}.aiku.test" },
	} as Record<string, object>,
}
for (let i = 0; i < 300; i++) {
	trickyConfig.routes[`filler.${i}`] = { get uri() { uriReads++; return `filler${i}/{a}` }, methods: ["GET"] }
}

const trickyAddresses = [
	["/", ""], ["/org/aw/chat/supervision", "?channel=email&filter[state]=open"], ["/org/aw/chat/supervision/", ""], ["/org/aw/chat", ""],
	["/org/sk/shops/uk/orders/123", ""], ["/orders", ""], ["/orders/5", ""], ["/files/a/b/c.txt", ""], ["/download/report.pdf", ""],
	["/caf%C3%A9/menu", ""], ["/café/menu", ""], ["/tenant/7", ""], ["/filler7/x", ""], ["/org//chat/supervision", ""], ["//org/aw/chat/supervision", ""], ["/nowhere", ""],
]

const answersAt = (route: (...args: any[]) => any, [path, search]: string[], fixed: boolean, absolute = true) => {
	visit(path, search)
	const current = () => route(undefined, undefined, absolute, fixed ? { ...trickyConfig, location: { host: location.host, pathname: path, search } } : trickyConfig)
	return { name: current().current(), params: current().params, inSupervision: current().current("supervision", { organisation: "aw" }) }
}

for (const [copy, route] of [["npm package", npmRoute], ["@routes script", routesScriptRoute]] as const) {
	test(`${copy}: a new address gives Ziggy only the routes that fit it, with the same answers`, () => {
		cacheCurrentRoute(route(undefined, undefined, undefined, trickyConfig))

		for (const address of trickyAddresses) {
			assert.deepEqual(answersAt(route, address, false), answersAt(route, address, true), address.join(""))
			assert.deepEqual(answersAt(route, address, false, false), answersAt(route, address, true, false), `relative ${address.join("")}`)
		}

		visit("/org/es/chat/supervision")
		uriReads = 0
		assert.equal(route(undefined, undefined, undefined, trickyConfig).current(), "supervision")
		assert.equal(uriReads, 0)
	})
}
