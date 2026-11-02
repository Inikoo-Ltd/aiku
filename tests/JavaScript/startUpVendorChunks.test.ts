import assert from "node:assert/strict"
import test from "node:test"
import { startUpVendorChunks } from "../../vite.app-plugins.mjs"

const graph: Record<string, { isEntry?: boolean, importedIds: string[] }> = {
	"/app/app-grp.js"                         : { isEntry: true, importedIds: ["/app/Layout.vue", "/app/node_modules/vue/index.js"] },
	"/app/Layout.vue"                         : { importedIds: ["/app/node_modules/date-fns/format.mjs", "/app/Layout.vue"] },
	"/app/Page.vue"                           : { importedIds: ["/app/node_modules/date-fns/locale/cs.mjs", "/app/node_modules/swiper/index.mjs"] },
	"/app/node_modules/vue/index.js"          : { importedIds: [] },
	"/app/node_modules/date-fns/format.mjs"   : { importedIds: [] },
	"/app/node_modules/date-fns/locale/cs.mjs": { importedIds: [] },
	"/app/node_modules/swiper/index.mjs"      : { importedIds: [] },
	"/app/node_modules/@sentry/vue/index.js"  : { importedIds: [] },
}

const meta = {
	getModuleIds : () => Object.keys(graph)[Symbol.iterator](),
	getModuleInfo: (id: string) => graph[id] ?? null,
}

test("vendor code the entry loads gets its package chunk", () => {
	const manualChunks = startUpVendorChunks()

	assert.equal(manualChunks("/app/node_modules/vue/index.js", meta), "vue")
	assert.equal(manualChunks("/app/node_modules/date-fns/format.mjs", meta), "date-fns")
})

test("vendor code only pages use is left with the pages", () => {
	const manualChunks = startUpVendorChunks()

	assert.equal(manualChunks("/app/node_modules/date-fns/locale/cs.mjs", meta), undefined)
	assert.equal(manualChunks("/app/node_modules/swiper/index.mjs", meta), undefined)
})

test("app code and excluded packages are never put in a vendor chunk", () => {
	graph["/app/app-grp.js"].importedIds.push("/app/node_modules/@sentry/vue/index.js")
	const manualChunks = startUpVendorChunks(["sentry"])

	assert.equal(manualChunks("/app/Layout.vue", meta), undefined)
	assert.equal(manualChunks("/app/node_modules/@sentry/vue/index.js", meta), undefined)
})
