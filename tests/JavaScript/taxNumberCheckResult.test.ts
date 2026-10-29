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
