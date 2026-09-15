import assert from "node:assert/strict"
import test from "node:test"
import {
	hasLabelInfoContent,
	isProductTabVisible,
	isTabVisible,
} from "../../resources/js/Iris/Components/BlocksUtils/FamilyExtraDescription2/tabVisibility.ts"

const labelInfoWith = (shown: string[]) =>
	Object.fromEntries(
		["markets", "barcode", "ce_marking", "safety_icons"].map(key => [
			key,
			{ show: shown.includes(key), label: key, value: null },
		])
	)

test("reports no label info content when the product carries none", () => {
	assert.equal(hasLabelInfoContent(undefined), false)
	assert.equal(hasLabelInfoContent({}), false)
	assert.equal(hasLabelInfoContent({ label_info: {} }), false)
})

test("reports no label info content when every item is hidden", () => {
	assert.equal(hasLabelInfoContent({ label_info: labelInfoWith([]) }), false)
})

test("reports label info content when at least one item is shown", () => {
	assert.equal(hasLabelInfoContent({ label_info: labelInfoWith(["safety_icons"]) }), true)
	assert.equal(hasLabelInfoContent({ label_info: labelInfoWith(["markets", "barcode"]) }), true)
})

test("hides the regulatory product tab from logged out visitors", () => {
	const product = { label_info: labelInfoWith(["ce_marking"]) }

	assert.equal(isProductTabVisible("regulatory_label_information", {}, product, false), false)
	assert.equal(isProductTabVisible("regulatory_label_information", {}, product, true), true)
})

test("shows the regulatory product tab only when the product has label info", () => {
	assert.equal(
		isProductTabVisible("regulatory_label_information", {}, { label_info: labelInfoWith(["ce_marking"]) }, true),
		true
	)
	assert.equal(isProductTabVisible("regulatory_label_information", {}, { label_info: labelInfoWith([]) }, true), false)
	assert.equal(isProductTabVisible("regulatory_label_information", {}, {}, true), false)
})

test("keeps the regulatory tab out of family pages", () => {
	assert.equal(isTabVisible("regulatory_label_information", { is_aroma_organisation: true }, true), false)
	assert.equal(isTabVisible("regulatory_label_information", {}, false), false)
})

test("leaves the other product tabs unaffected", () => {
	assert.equal(isProductTabVisible("about", { description: "<p>Hello</p>" }, {}, true), true)
	assert.equal(isProductTabVisible("marketing", {}, {}, true), true)
	assert.equal(isProductTabVisible("marketing", {}, {}, false), false)
})

test("always shows the regulatory tab in the workshop preview", () => {
	assert.equal(
		isProductTabVisible("regulatory_label_information", {}, { label_info: labelInfoWith([]) }, true, true),
		true
	)
	assert.equal(isProductTabVisible("regulatory_label_information", {}, {}, true, true), true)
	assert.equal(isProductTabVisible("regulatory_label_information", {}, {}, false, true), true)
})

test("does not let the workshop override affect the other tabs", () => {
	assert.equal(isProductTabVisible("about", {}, {}, true, true), false)
	assert.equal(isProductTabVisible("marketing", {}, {}, false, true), false)
})
