import assert from "node:assert/strict"
import test from "node:test"
import { openTabForRequest } from "../../resources/js/Composables/openTabForRequest.ts"

const fakeOpener = (opensTab: boolean) => {
	const calls: string[] = []
	const tab = { location: { href: "" }, closed: false, close() { this.closed = true } }
	const opener = {
		open: (url: string) => {
			calls.push(`open:${url}`)
			return opensTab ? tab : null
		},
		location: { assign: (url: string) => calls.push(`assign:${url}`) },
	}
	return { calls, tab, opener: opener as any }
}

test("the tab opens before the request resolves, so it stays inside the tap", async () => {
	const { calls, tab, opener } = fakeOpener(true)

	await openTabForRequest(async () => {
		assert.deepEqual(calls, ["open:"])
		return "https://auth.ebay.com/consent"
	}, opener)

	assert.equal(tab.location.href, "https://auth.ebay.com/consent")
	assert.equal(tab.closed, false)
})

test("a blocked pop-up falls back to navigating the current page", async () => {
	const { calls, opener } = fakeOpener(false)

	await openTabForRequest(async () => "https://auth.ebay.com/consent", opener)

	assert.deepEqual(calls, ["open:", "assign:https://auth.ebay.com/consent"])
})

test("a failed request closes the blank tab and rethrows", async () => {
	const { tab, opener } = fakeOpener(true)

	await assert.rejects(openTabForRequest(async () => {
		throw new Error("422")
	}, opener), /422/)

	assert.equal(tab.closed, true)
})

test("no url closes the blank tab", async () => {
	const { tab, opener } = fakeOpener(true)

	await openTabForRequest(async () => null, opener)

	assert.equal(tab.closed, true)
})
