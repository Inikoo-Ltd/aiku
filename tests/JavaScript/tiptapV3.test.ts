import assert from "node:assert/strict"
import test from "node:test"
import { getSchema, mergeAttributes } from "@tiptap/core"
import StarterKit from "@tiptap/starter-kit"
import Underline from "@tiptap/extension-underline"
import Heading from "@tiptap/extension-heading"
import Link from "@tiptap/extension-link"
import { BulletList, ListItem } from "@tiptap/extension-list"
import { Placeholder } from "@tiptap/extensions"
import { TextStyle, Color, FontFamily, FontSize } from "@tiptap/extension-text-style"

const warningsWhile = (build: () => void): string[] => {
	const warnings: string[] = []
	const originalWarn = console.warn
	console.warn = (...messages: unknown[]) => warnings.push(messages.join(" "))
	try {
		build()
	} finally {
		console.warn = originalWarn
	}

	return warnings
}

test("mergeAttributes does not turn an own __proto__ key into inherited attributes (Dependabot #526)", () => {
	const hostile = JSON.parse('{"__proto__": {"onclick": "alert(1)"}, "class": "mention"}')
	const merged = mergeAttributes({ class: "base" }, hostile)

	assert.equal((merged as Record<string, unknown>).onclick, undefined)
	assert.equal(Object.getPrototypeOf(merged), Object.prototype)
	assert.equal(merged.class, "base mention")
})

test("font size, colour and font family render on the text style span as the old font size package did", () => {
	const schema = getSchema([StarterKit, TextStyle, FontSize, Color, FontFamily])
	const mark = schema.marks.textStyle.create({ fontSize: "1.1250rem", color: "#ff0000", fontFamily: "Lato" })
	const [tag, attributes] = schema.marks.textStyle.spec.toDOM!(mark, true) as [string, Record<string, string>]

	assert.equal(tag, "span")
	assert.match(attributes.style, /font-size: 1\.1250rem/)
	assert.match(attributes.style, /color: #ff0000/)
	assert.match(attributes.style, /font-family: Lato/)
})

test("the chat editor's starter kit adds no link mark of its own", () => {
	const schema = getSchema([
		StarterKit.configure({ heading: false, blockquote: false, codeBlock: false, horizontalRule: false, link: false, underline: false, trailingNode: false }),
		Underline,
		Placeholder,
	])

	assert.equal(schema.marks.link, undefined)
	assert.ok(schema.marks.underline)
})

test("the pure editor's extensions load without duplicate extension warnings", () => {
	const warnings = warningsWhile(() => getSchema([
		StarterKit.configure({ heading: false, bulletList: false, listItem: false, link: false, underline: false, trailingNode: false }),
		Underline,
		BulletList,
		ListItem,
		Heading.configure({ levels: [1, 2, 3] }),
		Link,
		TextStyle,
		FontSize,
	]))

	assert.deepEqual(warnings.filter((warning) => warning.includes("Duplicate extension names")), [])
})
