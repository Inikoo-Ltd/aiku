import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"
import { chosenVariantDisplay, variantDisplayMode } from "../../resources/js/Composables/variantDisplayMode.ts"

const bothPossible = { sizesTable: true, priceLadder: true }

test("only the chosen way of showing sizes is shown, never two at once (HELP-4531)", () => {
	assert.equal(variantDisplayMode({ variant_display: "sizes_table" }, bothPossible), "sizes_table")
	assert.equal(variantDisplayMode({ variant_display: "price_ladder" }, bothPossible), "price_ladder")
	assert.equal(variantDisplayMode({ variant_display: "default" }, bothPossible), "default")
	assert.equal(variantDisplayMode(null, bothPossible), "default")
})

test("when the chosen display cannot show this product, the page falls back to the default, not to the other display", () => {
	assert.equal(variantDisplayMode({ variant_display: "price_ladder" }, { sizesTable: true, priceLadder: false }), "default")
	assert.equal(variantDisplayMode({ variant_display: "sizes_table" }, { sizesTable: false, priceLadder: true }), "default")
})

test("templates saved with the earlier switches keep what they showed", () => {
	assert.equal(chosenVariantDisplay({ variant_sizes_table: true }), "sizes_table")
	assert.equal(chosenVariantDisplay({ variant_price_ladder: true }), "price_ladder")
	assert.equal(chosenVariantDisplay({ variant_sizes_table: true, variant_display: "default" }), "default")
	assert.equal(chosenVariantDisplay({ variant_sizes_table: true, variant_display: "price_ladder" }), "price_ladder")
})

test("the product 3 and 4 template has one choice for the sizes instead of two switches", () => {
	const blueprint = readFileSync(new URL("../../resources/js/Components/CMS/Webpage/Product3/Blueprint.ts", import.meta.url), "utf8")
	const page = readFileSync(new URL("../../resources/js/Iris/Components/IrisBlocks/Product/Ecom/ProductIris4Ecom.vue", import.meta.url), "utf8")

	assert.doesNotMatch(blueprint, /variant_sizes_table|variant_price_ladder/)
	for (const value of ["default", "sizes_table", "price_ladder"]) {
		assert.match(blueprint, new RegExp(`value: "${value}"`))
	}
	assert.match(page, /const showSizesTable = computed<boolean>\(\(\) => displayMode\.value === "sizes_table"\)/)
	assert.match(page, /const showPriceLadder = computed<boolean>\(\(\) => displayMode\.value === "price_ladder"\)/)
})
