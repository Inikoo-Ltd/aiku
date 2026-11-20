import assert from "node:assert/strict"
import test from "node:test"
import { readFileSync } from "node:fs"
import { getSchema } from "@tiptap/core"
import Document from "@tiptap/extension-document"
import Paragraph from "@tiptap/extension-paragraph"
import Text from "@tiptap/extension-text"
import { ScriptBlock, SCRIPT_BLOCK_NODE } from "../../resources/js/Components/Forms/Fields/BubleTextEditor/ScriptBlock/ScriptBlockExtension.ts"
import { decodeScriptBlockHtml, encodeScriptBlockHtml } from "../../resources/js/Composables/useHtmlWithScripts.ts"
import { buildBlogTableOfContents } from "../../resources/js/Iris/Composables/useBlogTableOfContents.ts"
import { BLOG_EDITOR_TOOLS, DEFAULT_EDITOR_TOOLS } from "../../resources/js/Components/Forms/Fields/BubleTextEditor/editorTools.ts"

const schema = getSchema([Document, Paragraph, Text, ScriptBlock])
const snippet = '<h2>Quiz</h2><div id="quiz"></div><script>document.getElementById("quiz").textContent = "1 < 2 & 3 > 2"</script>'

const renderScriptBlock = (html: string) =>
	schema.nodes[SCRIPT_BLOCK_NODE].spec.toDOM!(schema.nodes[SCRIPT_BLOCK_NODE].create({ html })) as [string, Record<string, string>]

test("only the blog editor offers the script block", () => {
	assert.ok(BLOG_EDITOR_TOOLS.includes("scriptBlock"))
	assert.ok(!DEFAULT_EDITOR_TOOLS.includes("scriptBlock"))
})

test("the script is saved encoded so no tag of it sits in the article html", () => {
	const [tag, attributes] = renderScriptBlock(snippet)

	assert.equal(tag, "div")
	assert.equal(attributes["data-script-block"], "")
	assert.ok(!/[<>"]/.test(attributes["data-html"]))
	assert.equal(decodeScriptBlockHtml(attributes["data-html"]), snippet)
})

test("a heading inside a script does not reach the table of contents", () => {
	const [, attributes] = renderScriptBlock(snippet)
	const content = `<h2>First</h2><p>a</p><div data-script-block="" data-html="${attributes["data-html"]}"></div><h2>Second</h2><p>b</p><h2>Third</h2>`

	const headings = buildBlogTableOfContents(content).headings.map((heading) => heading.text)

	assert.deepEqual(headings, ["First", "Second", "Third"])
})

test("decoding text that was never encoded keeps it as it is", () => {
	assert.equal(decodeScriptBlockHtml("100% <b>off</b>"), "100% <b>off</b>")
	assert.equal(decodeScriptBlockHtml(encodeScriptBlockHtml("100% off")), "100% off")
})

test("opening a dialog removes the teleported bubble menu, not the menu element tiptap detaches while hidden", () => {
	const source = readFileSync("resources/js/Components/Forms/Fields/BubleTextEditor/EditorV2.vue", "utf8")
	const bubbleMenuTag = source.match(/<BubbleMenu\b[^>]*>/)?.[0] ?? ""

	assert.match(source, /<Teleport to="body" v-if="editorInstance && !showDialog">\s*<BubbleMenu\b/)
	assert.doesNotMatch(bubbleMenuTag, /v-if=/)
})
