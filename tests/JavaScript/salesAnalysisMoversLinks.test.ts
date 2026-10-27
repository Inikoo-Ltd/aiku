import assert from "node:assert/strict"
import test from "node:test"
import { readFileSync } from "node:fs"

const source = readFileSync("resources/js/Components/SalesAnalysis/SalesAnalysisMovers.vue", "utf8")

test("every mover figure links to the same page as its name", () => {
	const figures = source.match(/<component :is="row\.href \? Link : 'span'"[^>]*>\{\{ (display|tooltip)\(row\) \}\}<\/component>/g) ?? []

	assert.equal(figures.length, 4)
	for (const figure of figures) {
		assert.match(figure, /:href="row\.href \?\? undefined"/)
	}
})

test("no mover figure is left as plain text", () => {
	assert.doesNotMatch(source, /<span[^>]*>\{\{ (display|tooltip)\(row\) \}\}<\/span>/)
})

test("shop rows with a live page get an icon that opens it in a new tab", () => {
	const icons = source.match(/<a v-if="row\.pageHref"[^>]*>/g) ?? []

	assert.equal(icons.length, 2)
	for (const icon of icons) {
		assert.match(icon, /:href="row\.pageHref"/)
		assert.match(icon, /target="_blank"/)
		assert.match(icon, /rel="noopener noreferrer"/)
	}
})
