import assert from "node:assert/strict"
import test from "node:test"
import { boxTextToDoc, deskDocToSlackText } from "../../resources/js/Components/Messaging/deskMarkup.ts"

const doc = (text: string, styled = true) => ({ type: "doc", content: boxTextToDoc(text, [], [], styled) })
const roundTrip = (text: string) => deskDocToSlackText(doc(text) as any, true)
const types = (text: string) => doc(text).content.map((node: any) => node.type)

test("INI-560: a kept draft with bold, a list and a quote opens with the same blocks", () => {
    const draft = "*INI-560 QA draft bold*\n• list item one\n• list item two\n> quoted line for Slack (draft)"
    const content = doc(draft).content as any[]

    assert.deepEqual(content.map((node) => node.type), ["paragraph", "bulletList", "blockquote"])
    assert.deepEqual(content[0].content[0].marks, [{ type: "bold" }])
    assert.equal(content[1].content.length, 2)
    assert.equal(content[1].content[1].content[0].content[0].text, "list item two")
    assert.equal(content[2].content[0].content[0].text, "quoted line for Slack (draft)")
    assert.equal(roundTrip(draft), draft)
})

test("ordered lists keep their first number", () => {
    const content = doc("3. third\n4. fourth").content as any[]
    assert.equal(content[0].type, "orderedList")
    assert.equal(content[0].attrs.start, 3)
    assert.equal(roundTrip("3. third\n4. fourth"), "3. third\n4. fourth")
})

test("nested lists, continuation lines and a list inside a quote survive the round trip", () => {
    for (const text of [
        "• one\n    • one a\n    • one b\n• two",
        "1. first\n    more about first\n2. second",
        "> • quoted item\n> plain quoted",
        "> first\n> \n> after an empty quoted line",
    ]) assert.equal(roundTrip(text), text, text)
})

test("code blocks come back as code blocks and keep their text as it was", () => {
    const text = "before\n```\n*not bold*\n• not a list\n```\nafter"
    assert.deepEqual(types(text), ["paragraph", "codeBlock", "paragraph"])
    assert.equal(roundTrip(text), text)
})

test("a lone ``` without a closing fence stays a plain paragraph", () => {
    assert.deepEqual(types("```\nnot closed"), ["paragraph", "paragraph"])
})

test("a list followed by a paragraph and a bullet list followed by a numbered list stay apart", () => {
    assert.deepEqual(types("• a\nplain"), ["bulletList", "paragraph"])
    assert.deepEqual(types("• a\n1. b"), ["bulletList", "orderedList"])
})

test("unstyled boxes keep every line as a plain paragraph", () => {
    assert.deepEqual(doc("• a\n> b", false).content.map((node: any) => node.type), ["paragraph", "paragraph"])
})
