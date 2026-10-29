import assert from "node:assert/strict"
import test from "node:test"
import { Schema } from "@tiptap/pm/model"
import { findQuoteInDoc } from "../../resources/js/Components/WritingQuality/writingQualityText.ts"

const schema = new Schema({
	nodes: {
		doc: { content: "block+" },
		paragraph: { group: "block", content: "inline*" },
		text: { group: "inline" },
	},
	marks: { strong: {} },
})

const doc = schema.node("doc", null, [
	schema.node("paragraph", null, [schema.text("Elevate your experience with our ")]),
	schema.node("paragraph", null, [
		schema.text("hand poured "),
		schema.text("soy wax", [schema.marks.strong.create()]),
		schema.text(" candles."),
	]),
])

test("finds a quote inside one paragraph and returns the range that covers it", () => {
	const range = findQuoteInDoc(doc, "Elevate your experience")

	assert.deepEqual(range, { from: 1, to: 24 })
	assert.equal(doc.textBetween(range!.from, range!.to), "Elevate your experience")
})

test("finds a quote that runs across bold text, reading a non-breaking space as a space", () => {
	const range = findQuoteInDoc(doc, "hand poured soy wax candles")

	assert.ok(range)
	assert.equal(doc.textBetween(range!.from, range!.to).replace(/ /g, " "), "hand poured soy wax candles")
})

test("never matches across two paragraphs or text that is not there", () => {
	assert.equal(findQuoteInDoc(doc, "with our hand poured"), null)
	assert.equal(findQuoteInDoc(doc, "beeswax"), null)
})
