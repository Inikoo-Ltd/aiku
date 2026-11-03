import assert from "node:assert/strict"
import test from "node:test"
import { widestSrcsetWidth } from "../../resources/js/Common/Composables/useSrcsetWidth.ts"

test("an image smaller than every requested box is capped at its own width", () => {
	assert.equal(widestSrcsetWidth("https://img.test/a.jpg 300w"), 300)
})

test("the cap is the widest variant whatever order the variants arrive in", () => {
	assert.equal(widestSrcsetWidth("https://img.test/a.jpg 360w, https://img.test/b.jpg 1440w, https://img.test/c.jpg 720w"), 1440)
	assert.equal(widestSrcsetWidth("https://img.test/a.jpg 360w ,https://img.test/b.jpg 720w "), 720)
})

test("srcsets without width descriptors leave the image uncapped", () => {
	assert.equal(widestSrcsetWidth("https://img.test/a.jpg 1x, https://img.test/a_2x.jpg 2x"), undefined)
	assert.equal(widestSrcsetWidth("https://img.test/a.jpg"), undefined)
	assert.equal(widestSrcsetWidth(""), undefined)
	assert.equal(widestSrcsetWidth(undefined), undefined)
	assert.equal(widestSrcsetWidth(null), undefined)
})

test("digits followed by w inside the url are not mistaken for a width", () => {
	assert.equal(widestSrcsetWidth("https://img.test/rs:fit:999w/a.jpg 480w"), 480)
})
