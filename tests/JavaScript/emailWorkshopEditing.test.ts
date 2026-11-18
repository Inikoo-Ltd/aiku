import assert from "node:assert/strict"
import test from "node:test"
import {
	DELETE_SELECTION_COMBOS,
	EMAIL_CLIPBOARD_STORAGE_KEY,
	copyEmailSelection,
	pasteEmailClipboard,
	readEmailClipboard,
	MODULE_TYPES,
	ROW_DRAG_MODE,
	createDynamicBlockRows,
	createEmptyEmail,
	createModule,
	createRow,
	createUnsubscribeModule,
	droppedImageFiles,
	isDraggingFiles,
	isRowLockedByUnsubscribe,
	isUnsubscribeModule,
	isUnsubscribeModuleLocked,
	setModuleImage,
	unsubscribeBlockCount,
} from "../../resources/js/Components/CMS/Website/Outboxes/EmailWorkshop/emailWorkshopBlocks.ts"
import { formattedClipboardContent, stripPastedFormatting } from "../../resources/js/Components/Forms/Fields/BubleTextEditor/pastedHtml.ts"
import { readFileSync } from "node:fs"
import { fontSizeInPixels } from "../../resources/js/Components/Forms/Fields/BubleTextEditor/editorTools.ts"

const rowWith = (...modules: any[]) => {
	const row = createRow([12], "650px")
	row.columns[0].modules.push(...modules)

	return row
}

const footerTemplateLayout = () => {
	const layout = createEmptyEmail()
	layout.page.rows = [rowWith(createModule(MODULE_TYPES.paragraph)), rowWith(createUnsubscribeModule())]

	return layout
}

test("a saved footer inserted into an email that already has an unsubscribe block brings no second one", () => {
	const rows = createDynamicBlockRows(footerTemplateLayout(), "725px", true)
	const modules = rows.flatMap((row) => row.columns.flatMap((column) => column.modules))

	assert.equal(rows.length, 1)
	assert.equal(modules.some(isUnsubscribeModule), false)
	assert.equal(rows[0].content.style.width, "725px")
})

test("a saved footer inserted into an email without an unsubscribe block keeps its own", () => {
	const rows = createDynamicBlockRows(footerTemplateLayout(), "725px", false)

	assert.equal(rows.length, 2)
	assert.equal(rows[1].columns[0].modules.some(isUnsubscribeModule), true)
})

test("the only unsubscribe block is locked, but a second copy can be deleted", () => {
	const first = createUnsubscribeModule()
	const second = createUnsubscribeModule()
	const firstRow = rowWith(first)
	const email = createEmptyEmail()
	email.page.rows = [firstRow]

	assert.equal(isUnsubscribeModuleLocked(email, first), true)
	assert.equal(isRowLockedByUnsubscribe(email, firstRow), true)

	const secondRow = rowWith(second)
	email.page.rows.push(secondRow)

	assert.equal(unsubscribeBlockCount(email), 2)
	assert.equal(isUnsubscribeModuleLocked(email, second), false)
	assert.equal(isRowLockedByUnsubscribe(email, secondRow), false)
	assert.equal(isUnsubscribeModuleLocked(email, createModule(MODULE_TYPES.paragraph)), false)
})

test("a row holding every unsubscribe block of the email stays locked", () => {
	const row = rowWith(createUnsubscribeModule(), createUnsubscribeModule())
	const email = createEmptyEmail()
	email.page.rows = [row]

	assert.equal(isRowLockedByUnsubscribe(email, row), true)
})

test("backspace no longer deletes the selected block or row, only the Delete key does", () => {
	assert.deepEqual(DELETE_SELECTION_COMBOS, [["Delete"]])
})

test("a dropped image upload fills an image block and keeps an alt text already written", () => {
	const image = createModule(MODULE_TYPES.image)
	setModuleImage(image, { source: { original: "https://media.aiku.test/a.png" }, name: "a.png" })

	assert.equal(image.descriptor.image.src, "https://media.aiku.test/a.png")
	assert.equal(image.descriptor.image.alt, "a.png")

	setModuleImage(image, { source: { original: "https://media.aiku.test/b.png" }, alt: "B" })

	assert.equal(image.descriptor.image.src, "https://media.aiku.test/b.png")
	assert.equal(image.descriptor.image.alt, "a.png")
})

test("only files dragged from the computer count as a drop, and only images are taken", () => {
	const png = { type: "image/png", name: "a.png" }
	const pdf = { type: "application/pdf", name: "a.pdf" }

	assert.equal(isDraggingFiles({ types: ["Files"] } as any), true)
	assert.equal(isDraggingFiles({ types: ["text/plain"] } as any), false)
	assert.deepEqual(droppedImageFiles({ files: [png, pdf] } as any), [png])
})

test("text pasted from the website loses its font, size and colour but keeps bold, links and lists", () => {
	const pasted = '<meta charset="utf-8"><span style="font-size: 24px; font-family: Raleway; color: rgb(29, 37, 46);">Handmade </span>'
		+ '<strong style="font-size: 24px;">soap</strong> <a href="https://www.awgifts.se/soap" class="link" style="color: red;">here</a>'
		+ '<p class="product-text" style="font-size: 18px; line-height: 2;">Line</p><ul><li style="font-size: 18px;">One</li></ul>'

	assert.equal(
		stripPastedFormatting(pasted),
		'Handmade <strong>soap</strong> <a href="https://www.awgifts.se/soap">here</a><p>Line</p><ul><li>One</li></ul>',
	)
})

test("text pasted from Google Docs is not made bold by its wrapper", () => {
	const pasted = '<b style="font-weight:normal;" id="docs-internal-guid-1234"><p dir="ltr"><span style="font-size:11pt;">Hello</span></p></b>'

	assert.equal(stripPastedFormatting(pasted), "<p>Hello</p>")
})

const clipboardItem = (entries: Record<string, string>) => ({
	types: Object.keys(entries),
	getType: async (type: string) => new Blob([entries[type]], { type }),
})

test("paste with formatting keeps the website styling of the copied text", async () => {
	const html = '<meta charset="utf-8"><!--StartFragment--><span style="font-size: 24px; color: rgb(29, 37, 46);">Handmade <strong>soap</strong></span><!--EndFragment-->'
	const content = await formattedClipboardContent([clipboardItem({ "text/plain": "Handmade soap", "text/html": html })])

	assert.equal(content, '<span style="font-size: 24px; color: rgb(29, 37, 46);">Handmade <strong>soap</strong></span>')
})

test("paste with formatting has no formatted copy when only plain text was copied", async () => {
	assert.equal(await formattedClipboardContent([clipboardItem({ "text/plain": "Handmade soap" })]), null)
})

test("the toolbar shows a pasted pixel font size as it is, and a rem size in pixels", () => {
	assert.equal(fontSizeInPixels("26px"), "26")
	assert.equal(fontSizeInPixels("1.6250rem"), "26")
	assert.equal(fontSizeInPixels(undefined), "")
})

test("rows dragged from the Rows tab can be dropped on the canvas because both lists share the fallback drag mode", () => {
	const workshop = readFileSync(new URL("../../resources/js/Components/CMS/Website/Outboxes/EmailWorkshop/EmailWorkshop.vue", import.meta.url), "utf8")
	const rowLists = [...workshop.matchAll(/<draggable\b(?:=>|[^>])*>/g)].map((match) => match[0]).filter((tag) => tag.includes("email-rows"))

	assert.equal(ROW_DRAG_MODE.forceFallback, true)
	assert.equal(rowLists.length, 2)
	rowLists.forEach((rowList) => {
		assert.match(rowList, /v-bind="ROW_DRAG_MODE"/)
		assert.doesNotMatch(rowList, /force-fallback/)
	})
})

test("while dragging, the landing place is a labelled drop bar and a new row from the Rows tab outlines the canvas", () => {
	const workshop = readFileSync(new URL("../../resources/js/Components/CMS/Website/Outboxes/EmailWorkshop/EmailWorkshop.vue", import.meta.url), "utf8")
	const draggableTags = [...workshop.matchAll(/<draggable\b(?:=>|[^>])*>/g)].map((match) => match[0])
	const canvasRows = draggableTags.find((tag) => tag.includes('v-model="email.page.rows"'))!
	const rowsPalette = draggableTags.find((tag) => tag.includes(":list=\"rowLayouts\""))!
	const canvasModules = draggableTags.find((tag) => tag.includes('v-model="column.modules"'))!

	assert.equal(ROW_DRAG_MODE.fallbackClass, "email-drag-preview")
	assert.match(canvasRows, /ghost-class="email-row-ghost"/)
	assert.match(canvasModules, /ghost-class="email-module-ghost"/)
	assert.doesNotMatch(rowsPalette, /ghost-class/)
	assert.match(rowsPalette, /@start="onRowLayoutDragStart"/)
	assert.match(workshop, /\.email-row-ghost::before \{\s*content: var\(--email-drop-row-label/)
	assert.match(workshop, /\.email-module-ghost::before \{\s*content: var\(--email-drop-block-label/)
})

const textModule = (text: string) => {
	const module = createModule(MODULE_TYPES.text)
	module.descriptor.text = { html: text }

	return module
}

test("a copied block pastes below the selected block as a new block with its own ids", () => {
	const email = createEmptyEmail()
	const first = textModule("first")
	const second = textModule("second")
	const row = rowWith(first, second)
	email.page.rows = [row]

	const clipboard = copyEmailSelection(first, null)!
	const pasted = pasteEmailClipboard(email, clipboard, { moduleUuid: first.uuid!, rowUuid: row.uuid! }, "650px")!

	assert.equal(clipboard.kind, "module")
	assert.deepEqual(row.columns[0].modules.map((module) => module.descriptor.text.html), ["first", "first", "second"])
	assert.equal(pasted.module, row.columns[0].modules[1])
	assert.notEqual(pasted.module!.uuid, first.uuid)
	assert.equal(pasted.row, row)
})

test("a copied block pastes into an empty column of the selected row, or into a new row when nothing is selected", () => {
	const email = createEmptyEmail()
	const filled = createRow([6, 6], "650px")
	filled.columns[0].modules.push(textModule("left"))
	email.page.rows = [filled]
	const clipboard = copyEmailSelection(textModule("copied"), null)!

	pasteEmailClipboard(email, clipboard, { moduleUuid: null, rowUuid: filled.uuid! }, "650px")
	assert.equal(filled.columns[1].modules[0].descriptor.text.html, "copied")

	const pasted = pasteEmailClipboard(email, clipboard, { moduleUuid: null, rowUuid: null }, "650px")!
	assert.equal(email.page.rows.length, 2)
	assert.equal(email.page.rows[1], pasted.row)
	assert.equal(pasted.row.columns[0].modules[0].descriptor.text.html, "copied")
})

test("a copied row pastes below the selected row, or below the row of the selected block, with new ids throughout", () => {
	const email = createEmptyEmail()
	const inner = textModule("inside")
	const first = rowWith(inner)
	const last = rowWith(textModule("last"))
	email.page.rows = [first, last]

	const clipboard = copyEmailSelection(null, first)!
	const pasted = pasteEmailClipboard(email, clipboard, { moduleUuid: inner.uuid!, rowUuid: first.uuid! }, "650px")!

	assert.equal(clipboard.kind, "row")
	assert.deepEqual(email.page.rows.map((row) => row.columns[0].modules[0].descriptor.text.html), ["inside", "inside", "last"])
	assert.equal(email.page.rows[1], pasted.row)
	assert.equal(pasted.module, null)
	assert.notEqual(pasted.row.uuid, first.uuid)
	assert.notEqual(pasted.row.columns[0].modules[0].uuid, inner.uuid)
})

test("a copy is a snapshot: editing the original afterwards does not change what is pasted", () => {
	const email = createEmptyEmail()
	const original = textModule("before")
	email.page.rows = [rowWith(original)]

	const clipboard = copyEmailSelection(original, null)!
	original.descriptor.text.html = "after"
	const pasted = pasteEmailClipboard(email, clipboard, { moduleUuid: original.uuid!, rowUuid: null }, "650px")!

	assert.equal(pasted.module!.descriptor.text.html, "before")
})

test("the unsubscribe block and its row cannot be copied or pasted", () => {
	const email = createEmptyEmail()
	const unsubscribe = createUnsubscribeModule()
	const row = rowWith(unsubscribe)
	email.page.rows = [row]

	assert.equal(copyEmailSelection(unsubscribe, null), null)
	assert.equal(copyEmailSelection(null, row), null)
	assert.equal(pasteEmailClipboard(email, { kind: "module", module: unsubscribe }, { moduleUuid: null, rowUuid: null }, "650px"), null)
	assert.equal(email.page.rows.length, 1)
	assert.equal(unsubscribeBlockCount(email), 1)
})

test("a copied block kept in browser storage is read back, and anything else stored there is ignored", () => {
	const clipboard = copyEmailSelection(textModule("kept"), null)!

	assert.deepEqual(readEmailClipboard(JSON.stringify(clipboard)), clipboard)
	assert.equal(readEmailClipboard(null), null)
	assert.equal(readEmailClipboard("not json"), null)
	assert.equal(readEmailClipboard(JSON.stringify({ kind: "module", module: { type: 1 } })), null)
	assert.equal(readEmailClipboard(JSON.stringify({ kind: "row", row: { columns: [{}] } })), null)
	assert.equal(EMAIL_CLIPBOARD_STORAGE_KEY, "aiku.emailWorkshop.clipboard")
})
