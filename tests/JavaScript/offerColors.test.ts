import assert from "node:assert/strict"
import test from "node:test"
import { getOfferTextColorClass, isRedOfferType } from "../../resources/js/Composables/offerColors.ts"

test("treats category, department and subdepartment ordered offers as red offers", () => {
	assert.equal(isRedOfferType("Category Ordered"), true)
	assert.equal(isRedOfferType("Department Quantity Ordered"), true)
	assert.equal(isRedOfferType("Subdepartment Ordered"), true)
})

test("does not treat member or product offers as red offers", () => {
	assert.equal(isRedOfferType("Category Quantity Ordered Order Interval"), false)
	assert.equal(isRedOfferType("Product Quantity Ordered"), false)
	assert.equal(isRedOfferType(undefined), false)
	assert.equal(isRedOfferType(null), false)
})

test("colours a red offer discount red and every other discount orange", () => {
	assert.equal(getOfferTextColorClass({ type: "Category Ordered" }), "text-red-700")
	assert.equal(getOfferTextColorClass({ type: "Category Quantity Ordered Order Interval" }), "text-[#E87928]")
	assert.equal(getOfferTextColorClass({ type: "First Order Bonus" }), "text-[#E87928]")
})

test("returns no discount colour when there is no offer", () => {
	assert.equal(getOfferTextColorClass(null), null)
	assert.equal(getOfferTextColorClass(undefined), null)
})
