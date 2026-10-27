import assert from "node:assert/strict"
import test from "node:test"
import { readFileSync } from "node:fs"

const blocksWithHoverZoomedBackground = [
	"resources/js/Iris/Components/IrisBlocks/CtaImageBackroundIris.vue",
	"resources/js/Components/CMS/Webpage/CtaImageBackround/CtaImageBackroundWorkshop.vue",
	"resources/js/Iris/Components/IrisBlocks/Cta3Iris.vue",
	"resources/js/Components/CMS/Webpage/Cta3/Cta3Workshop.vue",
]

for (const file of blocksWithHoverZoomedBackground) {
	test(`${file} clips its hover-zoomed image so the page does not scroll sideways`, () => {
		const source = readFileSync(file, "utf8")
		const containerClass = source.match(/class="(relative grid[^"]*)"/)?.[1] ?? ""

		assert.match(source, /hover:scale-105/)
		assert.ok(containerClass.split(/\s+/).includes("overflow-hidden"), containerClass)
	})
}
