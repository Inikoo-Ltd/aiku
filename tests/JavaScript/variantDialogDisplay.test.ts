import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"

const read = (path: string) => readFileSync(new URL(`../../${path}`, import.meta.url), "utf8")
const dialog = read("resources/js/Iris/Components/IrisBlocks/Products/Ecom/VariantDialogContent.vue")

test("the family page pop-up follows the product template's choice of display (HELP-4531)", () => {
	assert.match(read("app/Http/Middleware/WithIrisInertia.php"), /'product_variant_setting' => Arr::only\(Arr::get\(\$website->published_layout, 'product\.data\.fieldValue\.setting'\)/)
	assert.match(dialog, /variantDisplayMode\(layout\?\.iris\?\.product_variant_setting, \{/)
	assert.match(dialog, /<div v-if="displayMode === 'sizes_table'" class="min-w-0">\s*<VariantSizeOrderingTable/)
	assert.match(dialog, /<div v-else-if="displayMode === 'price_ladder' && activeProduct" class="min-w-0">\s*<VariantPriceLadder/)
	assert.match(dialog, /<EcomAddToBasketv2 [^>]*holdQuantity/)
	assert.match(dialog, /<button v-if="isLoggedIn && displayMode === 'default'" type="button"/, "Save Selection belongs to the default table only")
})

test("the pop-up names sizes, not Bulk or Tester, and calls the column Size", () => {
	const render = read("resources/js/Iris/Components/IrisBlocks/Products/Ecom/RenderProduct.vue")

	assert.match(dialog, /\(product\.size_resolved && product\.size_label\) \|\| product\.variant_label \|\| product\.code/)
	assert.match(dialog, /\{\{ rowLabel\(variant\) \}\}/)
	assert.match(render, /listProducts\.value\.every\(\(item: any\) => item\.size_resolved\)\s*\? ctrans\("Size"\)/)
	assert.match(render, /:variantAxisLabel="dialogAxisLabel"/)
})
