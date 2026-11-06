import assert from "node:assert/strict"
import test from "node:test"
import axios from "axios"

const store = new Map<string, string>()
const location = { href: "https://app.aiku.test/org/aw/chat/inbox" }
const listeners: Record<string, () => void> = {}
Object.assign(globalThis, {
	window: {
		location,
		addEventListener: (name: string, handler: () => void) => { listeners[name] = handler },
		localStorage: {
			getItem: (key: string) => store.get(key) ?? null,
			setItem: (key: string, value: string) => store.set(key, value),
		},
	},
})

const { listKey, prefetchInboxListOnVisit, rememberFirstList, takePrefetchedList } = await import("../../resources/js/Composables/inboxListPrefetch.ts")

const requests: Array<{ url: string, params: unknown }> = []
let answer: () => Promise<any> = () => Promise.resolve({ data: "list" })
axios.get = ((url: string, config: { params: unknown }) => {
	requests.push({ url, params: config.params })
	return answer()
}) as typeof axios.get

let onBefore: (event: any) => void = () => {}
prefetchInboxListOnVisit({ on: (name: string, handler: (event: any) => void) => { if (name === "before") onBefore = handler } } as any)

const visit = (path: string, extra: Record<string, unknown> = {}) => onBefore({ detail: { visit: { method: "get", url: new URL(`https://app.aiku.test${path}`), only: [], except: [], prefetch: false, ...extra } } })

const url = "https://app.aiku.test/app/api/chats/sessions"
const params = { statuses: ["waiting", "active"], page: 1, shop_ids: [1] }

test("going to the inbox asks for the list it opened with last time, and the inbox takes that answer", async () => {
	requests.length = 0
	location.href = "https://app.aiku.test/org/aw/chat/inbox"
	rememberFirstList(url, params)

	visit("/org/aw/chat/inbox/01M52ZHJ1KPDCSXMVMF33CNSY2")

	assert.deepEqual(requests, [{ url, params }])
	const taken = takePrefetchedList(url, params)
	assert.equal(taken.outcome, "prefetched")
	assert.equal((await taken.response)?.data, "list")
	assert.deepEqual(takePrefetchedList(url, params), { response: null, outcome: "fetched" }, "handed out once")
})

test("a different list, another organisation, page or shop, a partial reload or a hover prefetch asks for nothing the inbox would take", () => {
	requests.length = 0
	rememberFirstList(url, params)

	visit("/org/aw/chat/inbox")
	assert.deepEqual(takePrefetchedList(url, { ...params, shop_ids: [2] }), { response: null, outcome: "missed" })

	visit("/org/sk/chat/inbox")
	visit("/org/aw/chat/supervision")
	visit("/org/aw/chat/supervision/dashboard")
	visit("/org/aw/shops/uk/chat/inbox")
	visit("/org/aw/chat/inbox", { only: ["inboxes"] })
	visit("/org/aw/chat/inbox", { prefetch: true })
	visit("/org/aw/shops/uk/orders")
	visit("/org/aw/chat/inbox", { method: "post" })

	assert.equal(requests.length, 1)
	assert.deepEqual(takePrefetchedList(url, params), { response: null, outcome: "fetched" })
})

test("an answer asked for too long ago is not used", (t) => {
	rememberFirstList(url, params)
	let now = 1_000
	t.mock.method(performance, "now", () => now)

	visit("/org/aw/chat/inbox")
	now += 10_001

	assert.deepEqual(takePrefetchedList(url, params), { response: null, outcome: "missed" })
})

test("the inbox list key is the address followed by the parameters, in their order", () => {
	assert.equal(listKey(url, params), url + JSON.stringify(params))
	assert.notEqual(listKey(url, { page: 1, statuses: ["waiting", "active"], shop_ids: [1] }), listKey(url, params))
})

test("a failed answer reaches whoever takes it, so the inbox can ask again", async () => {
	answer = () => Promise.reject(new Error("422"))
	rememberFirstList(url, params)

	visit("/org/aw/chat/inbox")

	await assert.rejects(takePrefetchedList(url, params).response!)
	answer = () => Promise.resolve({ data: "list" })
})

test("Back or Forward drops an answer asked for before", () => {
	location.href = "https://app.aiku.test/org/aw/chat/inbox"
	rememberFirstList(url, params)

	visit("/org/aw/chat/inbox")
	listeners.popstate()

	assert.deepEqual(takePrefetchedList(url, params), { response: null, outcome: "fetched" })
})

test("nothing is remembered from a page that is not an inbox", () => {
	store.clear()
	location.href = "https://app.aiku.test/org/aw/shops/uk/orders"

	rememberFirstList(url, params)

	assert.equal(store.size, 0)
})

test("a shop inbox and a notification link each open on their own list, apart from the inbox", () => {
	store.clear()
	requests.length = 0
	const shopParams = { ...params, shop_ids: [7] }
	const whatsappParams = { ...params, pairs: ["whatsapp"] }

	location.href = "https://app.aiku.test/org/aw/chat/inbox"
	rememberFirstList(url, params)
	location.href = "https://app.aiku.test/org/aw/shops/uk/chat/inbox"
	rememberFirstList(url, shopParams)
	location.href = "https://app.aiku.test/org/aw/chat/inbox?channel=whatsapp&session=01M6R7A16XM0BDRTKYAD7XBCPH"
	rememberFirstList(url, whatsappParams)

	visit("/org/aw/shops/uk/chat/inbox")
	assert.equal(takePrefetchedList(url, shopParams).outcome, "prefetched")

	visit("/org/aw/chat/inbox?channel=whatsapp&session=01M6R7B2KQ5ZJ8WXN3T4V6Y9DE")
	assert.equal(takePrefetchedList(url, whatsappParams).outcome, "prefetched")

	visit("/org/aw/chat/inbox")
	assert.equal(takePrefetchedList(url, params).outcome, "prefetched")

	assert.deepEqual(requests.map((r) => r.params), [shopParams, whatsappParams, params])
})

test("a choice changed while a notification link is open is remembered for the inbox it names", () => {
	store.clear()
	requests.length = 0
	location.href = "https://app.aiku.test/org/aw/chat/inbox?channel=whatsapp&session=01M6R7A16XM0BDRTKYAD7XBCPH"

	rememberFirstList(url, params, "https://app.aiku.test/org/aw/chat/inbox")

	assert.deepEqual([...store.keys()], ["inbox-first-list:aw:inbox"])
})
