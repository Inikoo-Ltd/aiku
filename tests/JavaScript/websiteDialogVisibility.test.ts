import assert from "node:assert/strict"
import test from "node:test"
import {
	isWebsiteDialogDismissed,
	pickWebsiteDialog,
	rememberWebsiteDialogDismissal,
	websiteDialogUlidFromHref,
	type DismissalStores,
	type WebsiteDialogDisplayFrequency,
} from "../../resources/js/Iris/Composables/useWebsiteDialogVisibility.ts"

const memoryStore = () => {
	const items = new Map<string, string>()

	return {
		getItem: (key: string) => items.get(key) ?? null,
		setItem: (key: string, value: string) => void items.set(key, value),
	}
}

const stores = (): DismissalStores => ({ session: memoryStore(), local: memoryStore() })

const dialog = (ulid: string, frequency: WebsiteDialogDisplayFrequency, version = "v1") => ({
	ulid,
	version,
	component: "dialog-centered",
	settings: { display_frequency: frequency },
})

const always = () => true

test("picks the first visible dialog", () => {
	const hidden = dialog("hidden", "once")
	const shown = dialog("shown", "once")

	assert.equal(pickWebsiteDialog([hidden, shown], (item) => item.ulid !== "hidden", stores()), shown)
})

test("skips a dialog without a layout component", () => {
	assert.equal(pickWebsiteDialog([{ ...dialog("empty", "once"), component: null }], always, stores()), null)
})

test("a dialog shown on every page view is never remembered as dismissed", () => {
	const everyView = dialog("every", "every_page_view")
	const visitorStores = stores()

	rememberWebsiteDialogDismissal(everyView, visitorStores)

	assert.equal(pickWebsiteDialog([everyView], always, visitorStores), everyView)
})

test("a once per session dialog stays closed for the rest of the session only", () => {
	const perSession = dialog("session", "once_per_session")
	const visitorStores = stores()

	rememberWebsiteDialogDismissal(perSession, visitorStores)

	assert.equal(isWebsiteDialogDismissed(perSession, visitorStores), true)
	assert.equal(isWebsiteDialogDismissed(perSession, { ...visitorStores, session: memoryStore() }), false)
})

test("a dialog shown once comes back when it is published again", () => {
	const visitorStores = stores()

	rememberWebsiteDialogDismissal(dialog("once", "once", "v1"), visitorStores)

	assert.equal(isWebsiteDialogDismissed(dialog("once", "once", "v1"), visitorStores), true)
	assert.equal(isWebsiteDialogDismissed(dialog("once", "once", "v2"), visitorStores), false)
})

test("the next dialog shows when the newest one was already dismissed", () => {
	const newest = dialog("newest", "once")
	const older = dialog("older", "once")
	const visitorStores = stores()

	rememberWebsiteDialogDismissal(newest, visitorStores)

	assert.equal(pickWebsiteDialog([newest, older], always, visitorStores), older)
})

test("blocked storage never breaks the storefront", () => {
	const throwing = {
		getItem: () => { throw new Error("blocked") },
		setItem: () => { throw new Error("blocked") },
	}
	const blocked = { session: throwing, local: throwing }
	const once = dialog("once", "once")

	assert.doesNotThrow(() => rememberWebsiteDialogDismissal(once, blocked))
	assert.equal(pickWebsiteDialog([once], always, blocked), once)
})

test("a dialog opened by a button never pops up by itself", () => {
	const onClick = { ...dialog("button", "every_page_view"), settings: { display_frequency: "every_page_view" as const, trigger: "on_click" as const } }

	assert.equal(pickWebsiteDialog([onClick], always, stores()), null)
})

test("reads the dialog from a button link", () => {
	assert.equal(websiteDialogUlidFromHref("#website-dialog-01ABC"), "01ABC")
	assert.equal(websiteDialogUlidFromHref("https://shop.test/page#website-dialog-01ABC"), "01ABC")
	assert.equal(websiteDialogUlidFromHref("#website-dialog-"), null)
	assert.equal(websiteDialogUlidFromHref("#reviews"), null)
	assert.equal(websiteDialogUlidFromHref("/products"), null)
})

test("a once per customer dialog stays closed on a new device once the account closed it", () => {
	const perCustomer = dialog("account", "once_per_customer", "v1")
	const newDevice = { ...stores(), customer: { account: "v1" } }

	assert.equal(isWebsiteDialogDismissed(perCustomer, newDevice), true)
	assert.equal(isWebsiteDialogDismissed(dialog("account", "once_per_customer", "v2"), newDevice), false)
})

test("a guest closing a once per customer dialog keeps it closed on that browser", () => {
	const perCustomer = dialog("account", "once_per_customer")
	const guestStores = stores()

	rememberWebsiteDialogDismissal(perCustomer, guestStores)

	assert.equal(isWebsiteDialogDismissed(perCustomer, guestStores), true)
})
