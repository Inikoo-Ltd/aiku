import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"
import { basketSizeSuffix, productNameWithPackAndSize } from "../../resources/js/Composables/useBasketSizeSuffix.ts"

const read = (path: string) => readFileSync(new URL(`../../${path}`, import.meta.url), "utf8")

test("search results name the size of each variant and never read a size as a pack (HELP-4610)", () => {
	assert.equal(productNameWithPackAndSize({ name: "Blonde Bubbles Fragrance Oil", units: "0.05", size_label: "50ml" }), "Blonde Bubbles Fragrance Oil — 50ml")
	assert.equal(productNameWithPackAndSize({ name: "Almond Fragrance Oil - 1 Litre", units: 1, size_label: "1L" }), "Almond Fragrance Oil - 1 Litre")
	assert.equal(productNameWithPackAndSize({ name: "Gemstone Obelisk Points", units: 5 }), "[5x] Gemstone Obelisk Points")
	assert.equal(productNameWithPackAndSize({ name: "Gemstone Obelisk Points", units: 1, size_label: null }), "Gemstone Obelisk Points")

	for (const component of ["SearchResultCatalogue", "SearchResultCatalogueMobile", "SearchFeatured", "SearchFeaturedMobile"]) {
		assert.match(read(`resources/js/Iris/Components/${component}.vue`), /const getProductName = productNameWithPackAndSize/, `${component} uses the shared name`)
	}
})

test("order confirmation and order history show the size after the product name", () => {
	const table = read("resources/js/Components/Retina/Ecom/EcomTableOrderTransactions.vue")

	assert.equal(table.match(/\{\{ item\.asset_name \}\}\{\{ basketSizeSuffix\(item\.asset_name, item\.size_label\) \}\}/g)?.length, 2)
	assert.match(read("app/Http/Resources/Ordering/TransactionsResource.php"), /'size_label'\s+=> GetVariantProductSizeLabel::make\(\)->forBasketLine\(\$this->variant_option_label, \$this->asset_code, \$this->product_unit, \$this->product_units\)/)
})

test("category cards of a variant list its sizes instead of a pack count", () => {
	const english = JSON.parse(read("lang/en.json"))

	for (const card of ["ProductCardEcom1", "ProductCardEcom2", "ProductCardEcom3"]) {
		const source = read(`resources/js/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/${card}.vue`)

		assert.match(source, /ctrans\("Sizes: :sizes", \{ sizes: product\.variant_sizes\.join\(" · "\) \}\)/, `${card} lists the sizes`)
		assert.doesNotMatch(source, /v-if="product\.units != 1"/, `${card} hides the pack count of a size group`)
	}
	assert.ok(english["Sizes: :sizes"])
})

test("the size is not added again when the name already says it, however it is written", () => {
	assert.equal(basketSizeSuffix("Almond Fragrance Oil - 1 Litre", "1L"), "")
	assert.equal(basketSizeSuffix("Basil Oil 1 Ltr", "1L"), "")
	assert.equal(basketSizeSuffix("Basil Bulk Essential Oil 0.5 kg", "0.5kg"), "")
	assert.equal(basketSizeSuffix("Blonde Bubbles Fragrance Oil 1L", "1L"), "")
	assert.equal(basketSizeSuffix("Rose Water 110ml", "10ml"), " — 10ml")
	assert.equal(basketSizeSuffix("Rose Water 100ml", "10ml"), " — 10ml")
	assert.equal(basketSizeSuffix("Blonde Bubbles Fragrance Oil", "10ml sample"), " — 10ml sample")
	assert.equal(basketSizeSuffix("Blonde Bubbles Fragrance Oil", null), "")
})
