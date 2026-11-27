import assert from "node:assert/strict"
import test from "node:test"
import { withDefaultLinkType } from "../../resources/js/Components/CMS/Fields/linkFieldValue.ts"

test("a new menu link without a type opens as an internal link so its destination shows", () => {
	assert.deepEqual(withDefaultLinkType({}), {
		href: null,
		id: null,
		url: null,
		data: {},
		type: "internal",
		target: "_self",
	})
})

test("a link saved as an empty array by the server opens as an internal link", () => {
	assert.deepEqual(withDefaultLinkType([]), {
		href: null,
		id: null,
		url: null,
		data: {},
		type: "internal",
		target: "_self",
	})
})

test("a link without a type keeps its chosen target and destination", () => {
	const link = withDefaultLinkType({ target: "_blank", href: "/aromatherapy" }) as Record<string, unknown>

	assert.equal(link.type, "internal")
	assert.equal(link.target, "_blank")
	assert.equal(link.href, "/aromatherapy")
})

test("a link with a type is left as it is", () => {
	const link = { type: "external", href: "https://example.com", target: "_blank" }

	assert.equal(withDefaultLinkType(link), link)
})

test("no link stays no link", () => {
	assert.equal(withDefaultLinkType(null), null)
	assert.equal(withDefaultLinkType(undefined), undefined)
})
