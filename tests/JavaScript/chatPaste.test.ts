import assert from "node:assert/strict"
import test from "node:test"
import { Schema } from "@tiptap/pm/model"
import { keepBlankLines, plainTextSlice } from "../../resources/js/Components/Chat/chatPaste.ts"

const schema = new Schema({
    nodes: { doc: { content: "paragraph+" }, paragraph: { content: "text*" }, text: {} },
    marks: { strong: {} },
})

const lines = (text: string) => {
    const result: string[] = []
    plainTextSlice(text, schema).content.forEach((node) => result.push(node.textContent))
    return result
}

test("keeps the empty lines between pasted paragraphs", () => {
    assert.deepEqual(lines("Hello,\n\nWe send only paid products.\n\nRegards"), ["Hello,", "", "We send only paid products.", "", "Regards"])
})

test("reads Windows line breaks the same way", () => {
    assert.deepEqual(lines("One\r\n\r\nTwo"), ["One", "", "Two"])
})

test("keeps the empty lines of text copied from a mail client", () => {
    assert.equal(keepBlankLines('<div>A</div><div><br></div><div dir="ltr"><br/></div><div>B</div>'), "<div>A</div><p><br></p><p><br></p><div>B</div>")
})

test("pasted text takes the formatting where it lands", () => {
    const slice = plainTextSlice("bold", schema, [schema.marks.strong.create()])
    assert.equal(slice.content.firstChild!.firstChild!.marks[0].type.name, "strong")
})
