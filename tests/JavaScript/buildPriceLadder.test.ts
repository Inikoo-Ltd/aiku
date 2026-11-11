import assert from "node:assert/strict"
import test from "node:test"
import { activeLadderOption, basketLadderOptionKeys, buildPriceLadder, formatLadderAmount, ladderMeasure, sharesAmountWithAnotherOption } from "../../resources/js/Composables/buildPriceLadder.ts"

const halfKilo = { id: 1, price: 46, stock: 20, size_resolved: true, size_kilograms: 0.5, size_label: "500g" }
const oneKilo = {
	id: 2, price: 73, stock: 40, size_resolved: true, size_kilograms: 1, size_label: "1kg",
	step_discount: { steps: [{ min_quantity: 10, percentage_off: 0.1 }, { min_quantity: 5, percentage_off: 0.05 }] }
}

test("sizes and the bulk prices of a size make one list, smallest amount first (HELP-4531)", () => {
	const options = buildPriceLadder([oneKilo, halfKilo])

	assert.deepEqual(options.map((option) => [option.productId, option.minQuantity, option.amount]), [
		[1, 1, 0.5],
		[2, 1, 1],
		[2, 5, 5],
		[2, 10, 10]
	])
})

test("each option is priced like the basket: the whole line less the step discount, with the price per kg", () => {
	const [half, kilo, five, ten] = buildPriceLadder([halfKilo, oneKilo])

	assert.equal(half.total, 46)
	assert.equal(half.pricePerMeasure, 92)
	assert.equal(kilo.total, 73)
	assert.equal(five.grossTotal, 365)
	assert.equal(five.total, 346.75)
	assert.equal(five.pricePerMeasure, 69.35)
	assert.equal(ten.total, 657)
	assert.equal(ten.pricePerMeasure, 65.7)
})

test("the list is only built when every option is a size in the same measure, so other variants keep their own display", () => {
	assert.equal(ladderMeasure([halfKilo, oneKilo]), "kg")
	assert.equal(ladderMeasure([{ id: 3, price: 10, size_resolved: true, size_litres: 1 }, { id: 4, price: 40, size_resolved: true, size_litres: 5 }]), "L")
	assert.equal(ladderMeasure([halfKilo, { id: 5, price: 10, size_resolved: true, size_litres: 1 }]), null)
	assert.equal(ladderMeasure([halfKilo, { id: 6, price: 30, size_resolved: false, size_label: "EU plug" }]), null)
	assert.deepEqual(buildPriceLadder([{ id: 7, price: 20, size_resolved: false }]), [])
})

test("the selected option follows the quantity of the selected size", () => {
	const options = buildPriceLadder([halfKilo, oneKilo])

	assert.equal(activeLadderOption(options, 2, 0)?.minQuantity, 1)
	assert.equal(activeLadderOption(options, 2, 4)?.minQuantity, 1)
	assert.equal(activeLadderOption(options, 2, 7)?.minQuantity, 5)
	assert.equal(activeLadderOption(options, 2, 12)?.minQuantity, 10)
	assert.equal(activeLadderOption(options, 1, 12)?.minQuantity, 1)
})

test("an option is unavailable when the stock cannot cover its quantity", () => {
	const options = buildPriceLadder([halfKilo, { ...oneKilo, stock: 7 }])

	assert.deepEqual(options.filter((option) => option.productId === 2).map((option) => option.isAvailable), [true, true, false])
	assert.equal(buildPriceLadder([{ ...halfKilo, stock: 0 }])[0].isAvailable, false)
	assert.equal(buildPriceLadder([{ ...halfKilo, stock: 0, is_on_demand: true }])[0].isAvailable, true)
})

test("two options with the same amount are told apart by their containers", () => {
	const halfKiloWithSteps = { ...halfKilo, step_discount: { steps: [{ min_quantity: 10, percentage_off: 0.05 }] } }
	const options = buildPriceLadder([halfKiloWithSteps, oneKilo])
	const fiveFromHalves = options.find((option) => option.productId === 1 && option.minQuantity === 10)!

	assert.equal(fiveFromHalves.amount, 5)
	assert.equal(sharesAmountWithAnotherOption(options, fiveFromHalves), true)
	assert.equal(sharesAmountWithAnotherOption(options, options[0]), false)
})

test("amounts read as kg, litres, or ml under one litre", () => {
	assert.equal(formatLadderAmount(0.5, "kg"), "0.5 kg")
	assert.equal(formatLadderAmount(10, "kg"), "10 kg")
	assert.equal(formatLadderAmount(0.05, "L"), "50 ml")
	assert.equal(formatLadderAmount(5, "L"), "5 L")
})

test("the one list is chosen in the product template and replaces the size tiles and the bulk offer cards", async () => {
	const { readFileSync } = await import("node:fs")
	const read = (path: string) => readFileSync(new URL(`../../${path}`, import.meta.url), "utf8")
	const english = JSON.parse(read("lang/en.json"))

	for (const blueprint of ["resources/js/Components/CMS/Webpage/Product1/Ecommerce/Blueprint.ts", "resources/js/Components/CMS/Webpage/Product3/Blueprint.ts"]) {
		assert.match(read(blueprint), /key: \["variant_display"\][\s\S]*value: "price_ladder"/, `${blueprint} offers the one list`)
	}

	for (const template of ["ProductIris1Ecom", "ProductIris4Ecom"]) {
		const page = read(`resources/js/Iris/Components/IrisBlocks/Product/Ecom/${template}.vue`)

		assert.match(page, /variantDisplayMode\(props\.fieldValue\?\.setting, \{/, `${template} reads the chosen display`)
		assert.equal(page.match(/<VariantPriceLadder/g)?.length, 2, `${template} shows the list on desktop and mobile`)
		assert.equal(page.match(/:holdQuantity="showPriceLadder"/g)?.length, 2, `${template} holds the quantity until the button is clicked`)
		assert.match(page, /patchVariantCustomerData\?\.\(option\.productId, \{ quantity_ordered_new: option\.minQuantity \}\)/, `${template} carries the chosen quantity to another size`)
	}

	assert.match(read("resources/js/Components/Iris/Products/EcomAddToBasketv2.vue"), /const holdsQuantity = computed\(\(\) => props\.holdQuantity \|\| hasStepDiscount\.value\)/)

	for (const key of ["Size and quantity", "You save :percentage", "Not enough stock", "In your basket: :quantity × :size", "Bulk prices count containers of one size; different sizes are not added together."]) {
		assert.ok(english[key], `${key} is in lang/en.json`)
	}
})

test("the basket badge sits on the bulk price the basket quantity reaches, one per size, none for sizes not in the basket", () => {
	const options = buildPriceLadder([halfKilo, oneKilo])

	assert.deepEqual([...basketLadderOptionKeys(options, { 1: 1, 2: 10 })].sort(), ["1:1", "2:10"])
	assert.deepEqual([...basketLadderOptionKeys(options, { 2: 7 })], ["2:5"])
	assert.deepEqual([...basketLadderOptionKeys(options, { 2: 3 })], ["2:1"])
	assert.deepEqual([...basketLadderOptionKeys(options, { 1: 0 })], [])
})
