import assert from "node:assert/strict"
import test from "node:test"
import { skipUnchangedTooltips } from "../../resources/js/Composables/skipUnchangedTooltips.ts"

const tooltipDirective = () => {
	const calls: unknown[] = []
	const directive = { updated: (_el: unknown, binding: { value: unknown }) => calls.push(binding.value) }
	skipUnchangedTooltips(directive as any)

	return { calls, update: (value: unknown, oldValue: unknown) => directive.updated({}, { value, oldValue } as any) }
}

test("a tooltip whose text did not change is not handed to floating-vue again", () => {
	const { calls, update } = tooltipDirective()

	update("Pick all", "Pick all")
	update(undefined, undefined)
	update({ content: "Pick all", placement: "top" }, { content: "Pick all", placement: "top" })

	assert.deepEqual(calls, [])
})

test("a tooltip whose text changed, or the same object passed again, still updates", () => {
	const { calls, update } = tooltipDirective()
	const changedInPlace = { content: "Pick 3" }

	update("Pick 3", "Pick 2")
	update({ content: "Pick 3" }, { content: "Pick 2" })
	update(changedInPlace, changedInPlace)
	update("Pick 3", undefined)

	assert.equal(calls.length, 4)
})
