import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"
import { getStyles, splitBackgroundStyles } from "../../resources/js/Composables/styles.ts"

const flatBackground = {
	background: { type: "color", color: "#f4f4f4", image: { original: null } },
	padding: { unit: "px", top: { value: 20 } },
}

test("a flat block background colours the whole section, not only the inner container", () => {
	const { background, rest } = splitBackgroundStyles(getStyles(flatBackground, "desktop"))

	assert.equal(background.background, "#f4f4f4 !important")
	assert.equal(rest.background, undefined)
	assert.equal(rest.paddingTop, "20px !important")
})

test("a per-screen block background is read for the screen being shown", () => {
	const { background } = splitBackgroundStyles(getStyles({
		background: {
			type: "color",
			color: "#ffffff",
			mobile: { type: "color", color: "#000000" },
		},
	}, "mobile"))

	assert.equal(background.background, "#000000 !important")
})

test("no block styles leaves both parts empty so the section keeps its default colour", () => {
	assert.deepEqual(splitBackgroundStyles(null), { background: {}, rest: {} })
})

for (const file of [
	"resources/js/Iris/Components/IrisBlocks/Family2ExtraDescriptionIris.vue",
	"resources/js/Components/CMS/Webpage/Family2ExtraDescription/Family2ExtraDescriptionWorkshop.vue",
]) {
	test(`${file} paints the background on the section only`, () => {
		const source = readFileSync(file, "utf8")

		assert.match(source, /const sectionStyle = computed\(\(\) => blockStyles\.value\.background\)/)
		assert.match(source, /\.\.\.blockStyles\.value\.rest/)
	})
}
