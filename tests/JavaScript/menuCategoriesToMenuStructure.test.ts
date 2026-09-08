import assert from "node:assert/strict"
import test from "node:test"
import { menuCategoriesToMenuStructure } from "../../resources/js/Composables/Iris/useMenu.ts"

const fragranceOils = {
	name: "Fragrance Oils",
	url: "/fragrance-oils",
	collections: [
		{ id: "c1", name: "Sweet Fragrance Oils", url: "/fragrance-oils/sweet", families: [{ name: "Vanilla", url: "/fragrance-oils/sweet/vanilla" }] },
		{ id: "c2", name: "Citrus Fragrance Oils", url: "/fragrance-oils/citrus" },
	],
	sub_departments: [
		{
			name: "Bulk Fragrance Oils",
			url: "/fragrance-oils/bulk",
			collections: [],
			families: [
				{ name: "Zesty", url: "/fragrance-oils/bulk/zesty" },
				{ name: "Amber", url: "/fragrance-oils/bulk/amber" },
			],
		},
	],
}

const whiteLabel = { name: "White Label", url: "/white_label", collections: [], sub_departments: [] }

test("merges sub_departments with collections and sorts everything, like the sidebar", () => {
	const menu = menuCategoriesToMenuStructure([whiteLabel, fragranceOils])

	assert.deepEqual(menu.map((m) => m.label), ["Fragrance Oils", "White Label"])

	const fragrance = menu[0]
	assert.equal(fragrance.type, "multiple")
	assert.deepEqual(
		fragrance.subnavs?.map((s) => s.title),
		["Bulk Fragrance Oils", "Citrus Fragrance Oils", "Sweet Fragrance Oils"]
	)
	assert.deepEqual(fragrance.subnavs?.[0].links?.map((l) => l.label), ["Amber", "Zesty"])
	assert.deepEqual(fragrance.subnavs?.[2].links?.map((l) => l.label), ["Vanilla"])
	assert.equal(fragrance.subnavs?.[1].links, undefined)

	assert.equal(menu[1].type, "single")
	assert.equal(menu[1].subnavs, undefined)
})
