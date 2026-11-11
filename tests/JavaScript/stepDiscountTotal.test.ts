import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"
import { stepDiscountTotal, stepPercentageOffForQuantity } from "../../resources/js/Composables/stepDiscountTotal.ts"

const steps = [{ min_quantity: 10, percentage_off: 0.1 }, { min_quantity: 5, percentage_off: 0.05 }]

test("the bulk offer price follows the quantity chosen, before it is saved (HELP-4531)", () => {
	assert.equal(stepPercentageOffForQuantity(steps, 4), 0)
	assert.equal(stepPercentageOffForQuantity(steps, 5), 0.05)
	assert.equal(stepPercentageOffForQuantity(steps, 9), 0.05)
	assert.equal(stepPercentageOffForQuantity(steps, 10), 0.1)
})

test("the total changes with the quantity in the box and matches what the basket charges", () => {
	assert.deepEqual(stepDiscountTotal(23.12, steps, 5), { gross: 115.6, net: 109.82 })
	assert.deepEqual(stepDiscountTotal(23.12, steps, 7), { gross: 161.84, net: 153.75 })
	assert.deepEqual(stepDiscountTotal(23.12, steps, 10), { gross: 231.2, net: 208.08 })
	assert.deepEqual(stepDiscountTotal(23.12, steps, 2), { gross: 46.24, net: 46.24 })
	assert.deepEqual(stepDiscountTotal(23.12, null, 0), { gross: 0, net: 0 })
})

test("the add to basket box shows the total of the unsaved quantity", () => {
	const component = readFileSync(new URL("../../resources/js/Components/Iris/Products/EcomAddToBasketv2.vue", import.meta.url), "utf8")
	const english = JSON.parse(readFileSync(new URL("../../lang/en.json", import.meta.url), "utf8"))

	assert.match(component, /v-if="showsSelectionTotal"/)
	assert.match(component, /const basketQuantity = computed<number>\(\(\) => Number\(customer\.value\.quantity_ordered \?\? 0\) \|\| 0\)/, "the basket quantity arrives as \"10.000000\" and is shown as 10")
	assert.match(component, /stepDiscountTotal\(Number\(product\.value\.price\) \|\| 0, product\.value\.step_discount\?\.steps, selectedQuantity\.value, props\.specialOfferPercentageOff\)/)
	assert.ok(english["Total for :quantity"])
	assert.ok(english["Your basket: :from → :to (not saved yet)"])
})

test("the bulk offer card follows the quantity typed in the box, not only the saved basket", () => {
	const component = readFileSync(new URL("../../resources/js/Components/Iris/Products/EcomAddToBasketv2.vue", import.meta.url), "utf8")
	const offer = readFileSync(new URL("../../resources/js/Components/CMS/Webpage/Product1/StepDiscountOffer.vue", import.meta.url), "utf8")

	assert.match(component, /props\.customerData\.quantity_ordered_new = quantity/)
	assert.match(component, /watch\(selectedQuantity, shareSelectedQuantity\)/, "only the box's own quantity triggers the write, so the desktop and mobile boxes cannot overwrite each other in a loop")
	assert.doesNotMatch(component, /\[selectedQuantity\.value, props\.customerData\?\.quantity_ordered_new\]/)
	assert.match(offer, /\(\) => props\.quantity,\s*\(quantity\) => \(activeMinQuantity\.value = reachedMinQuantity\(quantity \?\? 0\)\)/)

	for (const template of ["ProductIris1Ecom", "ProductIris2Ecom", "ProductIris3Ecom", "ProductIris4Ecom"]) {
		const page = readFileSync(new URL(`../../resources/js/Iris/Components/IrisBlocks/Product/Ecom/${template}.vue`, import.meta.url), "utf8")
		assert.match(page, /customerData\?\.quantity_ordered_new \?\? props\.customerData\?\.quantity_ordered/, `${template} passes the unsaved quantity to the bulk offer`)
	}
})
