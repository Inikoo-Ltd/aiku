import assert from "node:assert/strict"
import test from "node:test"
import { parseAmount } from "../../resources/js/Composables/parseAmount.ts"

test("a bank amount with a decimal comma is not read as a hundred times bigger (HELP-4522)", () => {
	assert.equal(parseAmount("146,17"), 146.17)
	assert.equal(parseAmount("146.17"), 146.17)
	assert.equal(parseAmount("146,1"), 146.1)
})

test("thousands separators in either convention are dropped", () => {
	assert.equal(parseAmount("1.234,56"), 1234.56)
	assert.equal(parseAmount("1,234.56"), 1234.56)
	assert.equal(parseAmount("1 234,56 €"), 1234.56)
	assert.equal(parseAmount("1 234,56"), 1234.56)
	assert.equal(parseAmount("1.234.567"), 1234567)
	assert.equal(parseAmount("1,234"), 1234)
	assert.equal(parseAmount("0,500"), 0.5)
})

test("currency text and signs around the amount are ignored", () => {
	assert.equal(parseAmount("EUR 146,17"), 146.17)
	assert.equal(parseAmount("-146,17"), -146.17)
	assert.equal(parseAmount("146"), 146)
	assert.equal(parseAmount("abc"), null)
	assert.equal(parseAmount(""), null)
})

test("an amount being typed keeps its value at every keystroke", () => {
	assert.equal(parseAmount("146,"), 146)
	assert.equal(parseAmount("146,1"), 146.1)
	assert.equal(parseAmount("146,17"), 146.17)
	assert.equal(parseAmount(",5"), 0.5)
})

test("text after the amount and a repeated separator before cents do not inflate it", () => {
	assert.equal(parseAmount("146,17 (ref 123456)"), 146.17)
	assert.equal(parseAmount("1.234.56"), 1234.56)
	assert.equal(parseAmount("€ 1,234,56"), 1234.56)
	assert.equal(parseAmount("1,234."), 1234)
	assert.equal(parseAmount("- 146,17"), -146.17)
})
