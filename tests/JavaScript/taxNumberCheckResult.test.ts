import assert from "node:assert/strict"
import test from "node:test"
import { taxNumberCheckResult } from "../../resources/js/Composables/useTaxNumberValidation.ts"

test("a number VIES could not check is pending, not invalid", () => {
	assert.equal(taxNumberCheckResult("unknown", false), "pending")
})

test("only a rejected number is invalid", () => {
	assert.equal(taxNumberCheckResult("invalid", false), "invalid")
	assert.equal(taxNumberCheckResult("invalid", true), "invalid")
	assert.equal(taxNumberCheckResult("na", false), "invalid")
})

test("a checked or manually validated number is valid", () => {
	assert.equal(taxNumberCheckResult("valid", true), "valid")
	assert.equal(taxNumberCheckResult("unknown", true), "valid")
})

test("an unchecked number that is not EU VAT is not checked, never pending on VIES", () => {
	assert.equal(taxNumberCheckResult("unknown", false, "other"), "not_checked")
	assert.equal(taxNumberCheckResult("unknown", false, "gb-vat"), "not_checked")
	assert.equal(taxNumberCheckResult("unknown", false, "eu-vat"), "pending")
	assert.equal(taxNumberCheckResult("valid", true, "other"), "valid")
})
