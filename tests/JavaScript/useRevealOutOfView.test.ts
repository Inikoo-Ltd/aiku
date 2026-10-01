import assert from "node:assert/strict"
import test from "node:test"
import { effectScope, nextTick, ref } from "vue"
import { useRevealOutOfView } from "../../resources/js/Iris/Composables/useRevealOutOfView.ts"

let reportVisibility: (isIntersecting: boolean) => void = () => {}

globalThis.IntersectionObserver = class {
	constructor(callback: (entries: { isIntersecting: boolean }[]) => void) {
		reportVisibility = (isIntersecting) => callback([{ isIntersecting }])
	}
	observe() {}
	disconnect() {}
} as any

const setup = (container: unknown) => {
	const products = ref<number[]>([])
	const isRevealed = effectScope().run(() => useRevealOutOfView(ref(container as HTMLElement), () => products.value.length > 0))!

	return { products, isRevealed }
}

test("stays hidden until products arrive", async () => {
	const { isRevealed } = setup({})
	await nextTick()

	assert.equal(isRevealed.value, false)
})

test("reveals at once when the block is off screen", async () => {
	const { products, isRevealed } = setup({})
	products.value = [1]
	await nextTick()
	reportVisibility(false)

	assert.equal(isRevealed.value, true)
})

test("holds products that arrive while the block is on screen until it scrolls out of view", async () => {
	const { products, isRevealed } = setup({})
	products.value = [1]
	await nextTick()
	reportVisibility(true)

	assert.equal(isRevealed.value, false)

	reportVisibility(false)

	assert.equal(isRevealed.value, true)
})

test("reveals without a container to observe", async () => {
	const { products, isRevealed } = setup(null)
	products.value = [1]
	await nextTick()

	assert.equal(isRevealed.value, true)
})
