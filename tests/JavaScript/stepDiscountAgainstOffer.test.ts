import assert from "node:assert/strict"
import test from "node:test"
import {
    activeSpecialOfferPercentageOff,
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
