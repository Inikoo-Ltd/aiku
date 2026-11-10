import assert from "node:assert/strict"
import test from "node:test"
import {
	applyLinkColor,
	buttonLinkStyle,
	createRenderContext,
	imageDisplayWidth,
	isLinkUnderlined,
	renderModuleHtml,
	withoutLinkUnderlineChoices,
} from "../../resources/js/Components/CMS/Website/Outboxes/EmailWorkshop/renderEmailHtml.ts"
import {
	MODULE_TYPES,
	createModule,
} from "../../resources/js/Components/CMS/Website/Outboxes/EmailWorkshop/emailWorkshopBlocks.ts"

const emailWithBody = (computedStyle: Record<string, unknown> = {}) => ({
	page: {
		rows: [],
		body: { container: { style: {} }, content: { style: {}, computedStyle: { linkColor: "#0068A5", ...computedStyle } } },
	},
}) as any

test("links are underlined unless the block or the email turns it off", () => {
	assert.equal(isLinkUnderlined(undefined, undefined), true)
	assert.equal(isLinkUnderlined(undefined, false), false)
	assert.equal(isLinkUnderlined(true, false), true)
	assert.equal(isLinkUnderlined(false, true), false)
})

test("a link without its own style gets the link colour and the chosen underline", () => {
	assert.equal(
		applyLinkColor('<a href="https://aiku.test">Shop</a>', "#ff0000"),
		'<a style="text-decoration:underline;color:#ff0000" href="https://aiku.test">Shop</a>',
	)
	assert.equal(
		applyLinkColor('<a href="https://aiku.test">Shop</a>', "#ff0000", false),
		'<a style="text-decoration:none;color:#ff0000" href="https://aiku.test">Shop</a>',
	)
})

test("a link's own underline choice wins over the block setting", () => {
	const notUnderlined = '<a href="https://aiku.test" style="text-decoration:none">Shop</a>'
	const underlined = '<a href="https://aiku.test" style="text-decoration:underline">Shop</a>'

	assert.equal(applyLinkColor(notUnderlined, "#ff0000", true), '<a href="https://aiku.test" style="color:#ff0000;text-decoration:none">Shop</a>')
	assert.equal(applyLinkColor(underlined, "#ff0000", false), '<a href="https://aiku.test" style="color:#ff0000;text-decoration:underline">Shop</a>')
})

test("a link with a style of its own keeps its colour and gets the block underline when it has none", () => {
	const html = '<a href="https://aiku.test" style="color:#123456">Shop</a>'

	assert.equal(applyLinkColor(html, "#ff0000", false), '<a href="https://aiku.test" style="text-decoration:none;color:#123456">Shop</a>')
	assert.equal(applyLinkColor('<a href="https://aiku.test" style="color:#123456;text-decoration:none">Shop</a>', "#ff0000"), '<a href="https://aiku.test" style="color:#123456;text-decoration:none">Shop</a>')
})

test("changing the block underline setting clears the underline each link chose", () => {
	assert.equal(
		withoutLinkUnderlineChoices('<p><a href="https://aiku.test" style="text-decoration:underline">Shop</a> <a href="https://aiku.test/b" style="color:#123456; text-decoration: none">Bath</a></p>'),
		'<p><a href="https://aiku.test">Shop</a> <a href="https://aiku.test/b" style="color:#123456">Bath</a></p>',
	)
})

test("a paragraph renders its links without underline when the block turns it off", () => {
	const paragraph = createModule(MODULE_TYPES.paragraph)
	paragraph.descriptor.paragraph.html = '<p><a href="https://aiku.test">Shop</a></p>'
	paragraph.descriptor.paragraph.computedStyle.linkUnderline = false

	const html = renderModuleHtml(paragraph, createRenderContext(emailWithBody(), 600))

	assert.match(html, /<a style="text-decoration:none;color:#0068A5" href="https:\/\/aiku.test">/)
})

test("the email wide setting turns the underline off for blocks that do not set it", () => {
	const paragraph = createModule(MODULE_TYPES.paragraph)
	paragraph.descriptor.paragraph.html = '<p><a href="https://aiku.test">Shop</a></p>'

	const html = renderModuleHtml(paragraph, createRenderContext(emailWithBody({ linkUnderline: false }), 600))

	assert.match(html, /text-decoration:none/)
})

test("the sent email shrinks a heading on mobile through a media rule, and the mobile canvas shows that size", () => {
	const heading = createModule(MODULE_TYPES.heading)
	heading.descriptor.heading.style["font-size"] = "38px"
	heading.descriptor.mobileStyle = { "font-size": "24px", "padding-top": "4px" }

	const sentContext = createRenderContext(emailWithBody(), 600)
	const sentHtml = renderModuleHtml(heading, sentContext)
	assert.match(sentHtml, /<h1 style="margin:0;[^"]*font-size:38px/)
	assert.deepEqual(sentContext.mobileRules, [
		".block-1 .pad{padding-top:4px!important}",
		".block-1 h1{font-size:24px!important}",
	])

	const mobileCanvasContext = createRenderContext(emailWithBody(), 375, true, true)
	const mobileCanvasHtml = renderModuleHtml(heading, mobileCanvasContext)
	assert.match(mobileCanvasHtml, /<h1 style="margin:0;[^"]*font-size:24px/)
	assert.match(mobileCanvasHtml, /class="pad" style="[^"]*padding-top:4px/)
	assert.deepEqual(mobileCanvasContext.mobileRules, [])

	const desktopCanvasHtml = renderModuleHtml(heading, createRenderContext(emailWithBody(), 600, true))
	assert.match(desktopCanvasHtml, /<h1 style="margin:0;[^"]*font-size:38px/)
})

test("mobile styles saved as an empty list by older layouts are ignored", () => {
	const heading = createModule(MODULE_TYPES.heading)
	heading.descriptor.mobileStyle = []

	const context = createRenderContext(emailWithBody(), 375, true, true)

	assert.match(renderModuleHtml(heading, context), /font-size:32px/)
})

test("the button being edited uses the same style as the button in the email, width included", () => {
	const button = createModule(MODULE_TYPES.button)
	button.descriptor.button.style.width = "100%"
	button.descriptor.button.style["font-family"] = "Georgia, serif"

	const style = buttonLinkStyle(button)
	const html = renderModuleHtml(button, createRenderContext(emailWithBody(), 600))

	assert.match(style, /;width:100%$/)
	assert.match(style, /font-family:Georgia, serif/)
	assert.ok(html.includes(`<a class="button" href="https://" target="_blank" style="${style}">`))
})

test("a button with automatic width has no width rule, so it fits its label", () => {
	assert.doesNotMatch(buttonLinkStyle(createModule(MODULE_TYPES.button)), /(^|;)width:/)
})

test("the button being edited on the mobile canvas uses its mobile font size", () => {
	const button = createModule(MODULE_TYPES.button)
	button.descriptor.mobileStyle = { "font-size": "20px" }

	assert.match(buttonLinkStyle(button), /font-size:16px/)
	assert.match(buttonLinkStyle(button, true), /font-size:20px/)
})

test("an image imported with its own pixel width keeps that width instead of filling the column", () => {
	const image = createModule(MODULE_TYPES.image)
	image.descriptor.image = { src: "https://img.aiku.test/aromatherapy.jpg", alt: "Aromatherapy", width: "200px" }
	image.descriptor.computedStyle = { class: "center autowidth", width: "200.000px" }

	assert.equal(imageDisplayWidth(image, 325), 200)
	assert.equal(imageDisplayWidth(image, 150), 150)
	assert.match(renderModuleHtml(image, createRenderContext(emailWithBody(), 325)), /<div style="max-width:200px">/)
})

test("a new image block still fills the column by its width percentage", () => {
	const image = createModule(MODULE_TYPES.image)

	assert.equal(imageDisplayWidth(image, 600), 600)

	image.descriptor.image.percWidth = 50
	assert.equal(imageDisplayWidth(image, 600), 300)
})

test("dynamic content such as the order table is not allowed to break words, so amounts stay on one line", () => {
	const mergeContent = createModule(MODULE_TYPES.mergeContent)
	mergeContent.descriptor.mergeContent = { value: "[Order]" }
	const paragraph = createModule(MODULE_TYPES.paragraph)
	const context = createRenderContext(emailWithBody(), 600)

	assert.doesNotMatch(renderModuleHtml(mergeContent, context), /word-break/)
	assert.match(renderModuleHtml(paragraph, context), /word-break:break-word/)
})

test("merge tags imported from Beefree lose their code wrapper, so addresses are not shown in a typewriter font", () => {
	const paragraph = createModule(MODULE_TYPES.paragraph)
	paragraph.descriptor.paragraph.html = '<p><strong>Invoice Address:</strong><br><code data-bee-code="">[Invoice Address]</code> <code>kept</code></p>'

	const html = renderModuleHtml(paragraph, createRenderContext(emailWithBody(), 600))

	assert.match(html, /<br>\[Invoice Address\] <code>kept<\/code>/)
	assert.doesNotMatch(html, /data-bee-code/)
})

test("text and button paragraphs have no margin in the sent email, so Gmail spaces them as the editor does", () => {
	const text = createModule(MODULE_TYPES.text)
	text.descriptor.text = { html: "<p><strong>50% OFF</strong></p>", style: {} }
	const button = createModule(MODULE_TYPES.button)
	button.descriptor.button = { ...button.descriptor.button, label: '<p style="font-size:16px">Natural Teak Display</p>' }
	const context = createRenderContext(emailWithBody(), 600)

	assert.match(renderModuleHtml(text, context), /<p style="margin:0"><strong>50% OFF<\/strong><\/p>/)
	assert.match(renderModuleHtml(button, context), /<p style="margin:0;font-size:16px">Natural Teak Display<\/p>/)
})

test("an empty paragraph typed as a blank line between paragraphs keeps its line in the sent email", () => {
	const text = createModule(MODULE_TYPES.text)
	text.descriptor.text = { html: "<p>Turn up the colour!</p><p></p><p>Black Friday savings</p>", style: {} }

	assert.match(renderModuleHtml(text, createRenderContext(emailWithBody(), 600)), /<p style="margin:0 0 0 0">Turn up the colour!<\/p><p style="margin:0 0 0 0">&nbsp;<\/p><p style="margin:0">Black Friday savings<\/p>/)
})
