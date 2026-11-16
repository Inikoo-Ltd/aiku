import assert from "node:assert/strict"
import test from "node:test"
import {
	DELETE_SELECTION_COMBOS,
	MODULE_TYPES,
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
