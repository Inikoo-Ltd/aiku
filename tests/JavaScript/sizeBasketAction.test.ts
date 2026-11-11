import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"
import { pendingChangeLabel, pendingChanges, sizeBasketButtonLabel } from "../../resources/js/Composables/sizeBasketAction.ts"

test("the button says what to do first while no quantity has changed (HELP-4531)", () => {
	assert.deepEqual(sizeBasketButtonLabel([{ size: "1kg", selected: 0, basket: 0 }]).text, "Choose a quantity to add to basket")
	assert.deepEqual(sizeBasketButtonLabel([{ size: "1kg", selected: 2, basket: 2 }]).text, "Change a quantity to update your basket")
})

test("one changed size is named with its quantity on the button", () => {
	assert.deepEqual(
		sizeBasketButtonLabel([{ size: "500g", selected: 0, basket: 0 }, { size: "1kg", selected: 5, basket: 0 }]),
		{ text: "Add :quantity × :size to basket", replacements: { quantity: "5", size: "1kg" } }
	)
	assert.deepEqual(
		sizeBasketButtonLabel([{ size: "1kg", selected: 7, basket: 5 }]),
		{ text: "Change :size to :quantity in basket", replacements: { quantity: "7", size: "1kg" } }
	)
	assert.deepEqual(
		sizeBasketButtonLabel([{ size: "1kg", selected: 0, basket: 5 }]),
		{ text: "Remove :size from basket", replacements: { size: "1kg" } }
	)
})

test("several new sizes are counted in containers and sizes", () => {
	assert.deepEqual(
		sizeBasketButtonLabel([{ size: "500g", selected: 1, basket: 0 }, { size: "1kg", selected: 5, basket: 0 }]),
		{ text: "Add :containers containers in :sizes sizes to basket", replacements: { containers: "6", sizes: "2" } }
	)
	assert.deepEqual(
		sizeBasketButtonLabel([{ size: "500g", selected: 1, basket: 0 }, { size: "1kg", selected: 3, basket: 5 }]),
		{ text: "Update :sizes sizes in basket", replacements: { sizes: "2" } }
	)
})

test("unsaved changes are listed as the difference from the basket", () => {
	const lines = [{ size: "500g", selected: 1, basket: 0 }, { size: "1kg", selected: 3, basket: 5 }, { size: "5L", selected: 2, basket: 2 }]

	assert.deepEqual(pendingChanges(lines).map(pendingChangeLabel), ["+1 × 500g", "−2 × 1kg"])
})

test("the sizes table uses the descriptive label and shows what is not saved yet", () => {
	const table = readFileSync(new URL("../../resources/js/Iris/Components/IrisBlocks/Product/Ecom/VariantSizeOrderingTable.vue", import.meta.url), "utf8")
	const english = JSON.parse(readFileSync(new URL("../../lang/en.json", import.meta.url), "utf8"))

	assert.doesNotMatch(table, /Add selected items to basket/)
	assert.match(table, /<template #label>\{\{ buttonLabel \}\}<\/template>/)
	assert.match(table, /Not in your basket yet: :changes/)

	for (const key of ["Choose a quantity to add to basket", "Add :quantity × :size to basket", "Not in your basket yet: :changes"]) {
		assert.ok(english[key], `${key} is in lang/en.json`)
	}
})

test("with a quantity already in the basket, the button says whether a click adds more or reduces it (HELP-4531)", async () => {
	const { heldQuantityChange } = await import("../../resources/js/Composables/sizeBasketAction.ts")

	assert.deepEqual(heldQuantityChange(10, 5, "1L"), { direction: "decrease", label: { text: "Reduce basket to :quantity × :size", replacements: { quantity: "5", size: "1L" } } })
	assert.deepEqual(heldQuantityChange(10, 15, "1L"), { direction: "increase", label: { text: "Add :more more (:quantity × :size in basket)", replacements: { more: "5", quantity: "15", size: "1L" } } })
	assert.deepEqual(heldQuantityChange(0, 5, "1L"), { direction: "add", label: { text: "Add :quantity × :size to basket", replacements: { quantity: "5", size: "1L" } } })
	assert.deepEqual(heldQuantityChange(10, 0, "1L"), { direction: "remove", label: { text: "Remove :size from basket", replacements: { size: "1L" } } })
	assert.equal(heldQuantityChange(10, 10, "1L").direction, "none")
	assert.equal(heldQuantityChange(10, 5, null).label.text, "Reduce basket to :quantity")
	assert.equal(heldQuantityChange(0, 3).label.text, "Add :quantity to basket")
})

test("the held quantity box names the change and warns when it reduces the basket", () => {
	const component = readFileSync(new URL("../../resources/js/Components/Iris/Products/EcomAddToBasketv2.vue", import.meta.url), "utf8")

	assert.match(component, /:label="heldButtonLabel"/)
	assert.match(component, /heldChange\.direction === 'decrease' \? 'font-medium text-amber-600'/)
	assert.match(component, /ctrans\('Total in basket'\)/)
	for (const page of ["ProductIris1Ecom", "ProductIris4Ecom"]) {
		const source = readFileSync(new URL(`../../resources/js/Iris/Components/IrisBlocks/Product/Ecom/${page}.vue`, import.meta.url), "utf8")
		assert.equal(source.match(/:holdQuantity="showPriceLadder" :sizeLabel="currentSizeLabel"/g)?.length, 2, `${page} passes the size to both boxes`)
	}
})
