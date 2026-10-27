import assert from "node:assert/strict"
import test from "node:test"
import { auditedQuantity, splitQuantity } from "../../resources/js/Composables/auditQuantity.ts"

test("auditing stock stored a hair under whole packs down to zero sends zero, not a negative remainder", () => {
	assert.deepEqual(splitQuantity(1.999666, 6), { whole: 2, numerator: 0 })
	assert.equal(auditedQuantity(1.999666, 0, 0, 6), 0)
	assert.equal(auditedQuantity(1.999666, 0, 0, 1), 0)
})

test("untouched inputs keep the stored quantity so the row is not flagged as modified", () => {
	assert.equal(auditedQuantity(1.999666, 2, 0, 6), 1.999666)
	assert.equal(auditedQuantity(2.1666, 2, 1, 6), 2.1666)
})

test("changed inputs give whole packs plus the counted fraction", () => {
	assert.equal(auditedQuantity(2.1666, 3, 1, 6), 3.166667)
	assert.equal(auditedQuantity(5, 4, 0, 1), 4)
})
