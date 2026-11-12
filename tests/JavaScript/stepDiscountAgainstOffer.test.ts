import assert from "node:assert/strict"
import test from "node:test"
import {
    activeSpecialOfferPercentageOff,
    isStepCoveredBySpecialOffer,
    popularStepMinQuantity,
    stepsBeatingSpecialOffer,
} from "../../resources/js/Composables/stepDiscountAgainstOffer.ts"

const steps = [
    { min_quantity: 1, percentage_off: 0 },
    { min_quantity: 6, percentage_off: 0.1 },
    { min_quantity: 12, percentage_off: 0.15, is_popular: true },
    { min_quantity: 24, percentage_off: 0.25 },
]

const offersData = { best_percentage_off: { percentage_off: 0.2, offer_id: 7 } }

test("keeps every step unchanged when there is no special offer", () => {
    assert.deepEqual(stepsBeatingSpecialOffer(steps, 0), steps)
    assert.equal(popularStepMinQuantity(steps, stepsBeatingSpecialOffer(steps, 0)), 12)
})

test("hides every step when the special offer beats them all", () => {
    assert.deepEqual(stepsBeatingSpecialOffer(steps, 0.3), [])
    assert.equal(popularStepMinQuantity(steps, []), null)
})

test("shows only the steps that beat the special offer", () => {
    assert.deepEqual(stepsBeatingSpecialOffer(steps, 0.2), [{ min_quantity: 24, percentage_off: 0.25 }])
})

test("hides a step whose discount equals the special offer", () => {
    assert.deepEqual(stepsBeatingSpecialOffer(steps, 0.25), [])
})

test("drops the popular badge when its step is hidden by the special offer", () => {
    assert.equal(popularStepMinQuantity(steps, stepsBeatingSpecialOffer(steps, 0.2)), null)
})

test("marks the steps a member price already covers so they show greyed, not hidden (HELP-4657)", () => {
    assert.deepEqual(steps.map((step) => isStepCoveredBySpecialOffer(step, 0.15)), [true, true, true, false])
    assert.deepEqual(steps.map((step) => isStepCoveredBySpecialOffer(step, 0)), [false, false, false, false])
})

test("counts the shown special offer percentage", () => {
    assert.equal(activeSpecialOfferPercentageOff({ type: "Category Ordered" }, offersData, false), 0.2)
    assert.equal(activeSpecialOfferPercentageOff({ type: "Category Ordered" }, { best_percentage_off: { percentage_off: "0.15" } }, false), 0.15)
})

test("ignores a member price offer the customer does not get", () => {
    assert.equal(activeSpecialOfferPercentageOff({ type: "Category Quantity Ordered Order Interval" }, offersData, false), 0)
    assert.equal(activeSpecialOfferPercentageOff({ type: "Category Quantity Ordered Order Interval" }, offersData, true), 0.2)
})

test("counts nothing when no offer is running", () => {
    assert.equal(activeSpecialOfferPercentageOff(null, offersData, true), 0)
})

test("the size and bulk price list prices the base size at the special offer and keeps only bulk prices that beat it (HELP-4564)", async () => {
    const { buildPriceLadder } = await import("../../resources/js/Composables/buildPriceLadder.ts")
    const product = {
        id: 1, price: 16, stock: 100, size_resolved: true, size_kilograms: 1, size_label: "1kg",
        step_discount: { steps: [{ min_quantity: 6, percentage_off: 0.1 }, { min_quantity: 12, percentage_off: 0.15 }, { min_quantity: 24, percentage_off: 0.25 }] },
    }

    const withOffer = buildPriceLadder([product], () => 0.2)
    assert.deepEqual(withOffer.map((option) => [option.minQuantity, option.percentageOff, option.total]), [[1, 0.2, 12.8], [24, 0.25, 288]])
    assert.equal(withOffer[0].isSpecialOffer, true)

    assert.deepEqual(buildPriceLadder([product], () => 0.3).map((option) => option.minQuantity), [1])
    assert.deepEqual(buildPriceLadder([product]).map((option) => option.minQuantity), [1, 6, 12, 24])
    assert.equal(buildPriceLadder([product])[0].isSpecialOffer, false)
})

test("the total before adding uses the better of the special offer and the bulk price, never both", async () => {
    const { stepDiscountTotal } = await import("../../resources/js/Composables/stepDiscountTotal.ts")
    const steps = [{ min_quantity: 24, percentage_off: 0.25 }, { min_quantity: 6, percentage_off: 0.1 }]

    assert.deepEqual(stepDiscountTotal(16, steps, 6, 0.2), { gross: 96, net: 76.8 })
    assert.deepEqual(stepDiscountTotal(16, steps, 24, 0.2), { gross: 384, net: 288 })
    assert.deepEqual(stepDiscountTotal(16, steps, 6), { gross: 96, net: 86.4 })
})

test("every place that shows bulk prices knows about the special offer", async () => {
    const { readFileSync } = await import("node:fs")
    const read = (path: string) => readFileSync(new URL(`../../${path}`, import.meta.url), "utf8")

    for (const template of ["ProductIris1Ecom", "ProductIris2Ecom", "ProductIris3Ecom", "ProductIris4Ecom"]) {
        assert.equal(read(`resources/js/Iris/Components/IrisBlocks/Product/Ecom/${template}.vue`).match(/:specialOfferPercentageOff="specialOfferPercentageOff"/g)?.length, 4, `${template}: bulk offer cards and both quantity boxes`)
    }
    assert.match(read("resources/js/Iris/Components/IrisBlocks/Product/Ecom/VariantSizeOrderingTable.vue"), /Number\(step\.percentage_off\) > Math\.max\(0, offerPercentageOff\(product\)\)/)
    assert.match(read("resources/js/Iris/Components/IrisBlocks/Products/Ecom/VariantDialogContent.vue"), /buildPriceLadder\(props\.variants as any\[\], \(variant: any\) => offerPercentageOff\(variant\)\)/)
    assert.match(read("resources/js/Components/Iris/Products/EcomAddToBasketv2.vue"), /selectedQuantity\.value, props\.specialOfferPercentageOff\)/)
})
