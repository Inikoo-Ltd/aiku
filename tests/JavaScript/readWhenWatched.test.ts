import assert from "node:assert/strict"
import test from "node:test"
import { readWhenWatchedQueue } from "../../resources/js/Composables/useReadWhenWatched.ts"

test("a customer message reaching a hidden tab stays unread until the tab is in front", () => {
	let watched = false
	let reads = 0
	const { readWhenWatched, onBackInFront } = readWhenWatchedQueue(() => reads++, () => watched)

	readWhenWatched()
	onBackInFront()
	assert.equal(reads, 0)

	watched = true
	onBackInFront()
	assert.equal(reads, 1)

	onBackInFront()
	assert.equal(reads, 1)

	readWhenWatched()
	assert.equal(reads, 2)
})
