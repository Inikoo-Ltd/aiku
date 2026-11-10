import assert from "node:assert/strict"
import test from "node:test"
import { blogTitleInlineHtml } from "../../resources/js/Iris/Composables/useBlogTableOfContents.ts"

test("unwraps a paragraph so the title sits directly in the h1", () => {
	assert.equal(blogTitleInlineHtml("<p>Best <strong>Incense</strong> Guide</p>"), "Best <strong>Incense</strong> Guide")
})

test("unwraps headings from the editor so the h1 never holds another heading", () => {
	assert.equal(blogTitleInlineHtml('<h2 style="text-align:center">Essential Oils</h2>'), "Essential Oils")
})

test("keeps a line break between several blocks", () => {
	assert.equal(blogTitleInlineHtml("<p>Line one</p><p>Line two</p>"), "Line one<br>Line two")
})

test("leaves plain text titles untouched", () => {
	assert.equal(blogTitleInlineHtml("Plain title"), "Plain title")
	assert.equal(blogTitleInlineHtml(""), "")
})
