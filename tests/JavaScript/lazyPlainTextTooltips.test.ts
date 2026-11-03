import assert from "node:assert/strict"
import test from "node:test"
import { lazyPlainTextTooltips } from "../../resources/js/Composables/sharedTooltip.ts"

const tooltipDirective = () => {
	const calls: string[] = []
	const directive = {
		beforeMount: (el: any) => { calls.push("mount"); el.$_popper = {} },
		updated: (el: any) => { calls.push("update"); el.$_popper = {} },
		beforeUnmount: (el: any) => { calls.push("unmount"); delete el.$_popper },
	}
	lazyPlainTextTooltips(directive as any)

	const el: any = { dataset: {} }
	const bind = (hook: "beforeMount" | "updated", value: unknown, modifiers: Record<string, boolean> = {}) =>
		(directive[hook] as any)(el, { value, modifiers })

	return { calls, el, bind }
}

test("a plain text tooltip is left for the shared tooltip instead of being mounted", () => {
	const { calls, el, bind } = tooltipDirective()

	bind("beforeMount", "Print label")
	assert.equal(el.dataset.tooltip, "Print label")

	bind("updated", 3)
	assert.equal(el.dataset.tooltip, "3")

	bind("updated", 0)
	assert.equal(el.dataset.tooltip, undefined)
	assert.deepEqual(calls, [])
})

test("re-rendering an element while its shared tooltip is shown leaves that tooltip up", () => {
	const { calls, el, bind } = tooltipDirective()

	bind("beforeMount", "Print label")
	el.$_popper = {}
	bind("updated", "Print label")

	assert.deepEqual(calls, [])
	assert.ok(el.$_popper)
})

test("a tooltip with options or a placement modifier still goes to floating-vue", () => {
	const { calls, el, bind } = tooltipDirective()

	bind("beforeMount", "Print label", { bottom: true })
	bind("updated", { content: "Print label", html: true })
	assert.deepEqual(calls, ["mount", "update"])
	assert.equal(el.dataset.tooltip, undefined)

	bind("updated", "Print label")
	assert.deepEqual(calls, ["mount", "update", "unmount"])
	assert.equal(el.dataset.tooltip, "Print label")
})
