import assert from "node:assert/strict"
import test from "node:test"
import {
	applyLinkColor,
	buttonLinkStyle,
	createRenderContext,
	imageDisplayWidth,
	isLinkUnderlined,
	renderModuleHtml,
	renderRowHtml,
	withoutLinkUnderlineChoices,
} from "../../resources/js/Components/CMS/Website/Outboxes/EmailWorkshop/renderEmailHtml.ts"
import {
	MODULE_TYPES,
	backgroundImageCss,
	backgroundImageUrl,
	buttonBorder,
	createModule,
	createRow,
	setBackgroundImage,
	setButtonBorder,
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

test("a picked background image fills the content area the first time and keeps the settings chosen after that", () => {
	const style: Record<string, string> = { "background-image": "none", "background-repeat": "no-repeat", "background-position": "top left" }

	setBackgroundImage(style, "https://media.aiku.io/banner.jpg")
	assert.equal(style["background-image"], "url('https://media.aiku.io/banner.jpg')")
	assert.equal(style["background-size"], "cover")
	assert.equal(style["background-position"], "center")

	style["background-position"] = "top right"
	setBackgroundImage(style, "https://media.aiku.io/other.jpg")
	assert.equal(backgroundImageUrl(style["background-image"]), "https://media.aiku.io/other.jpg")
	assert.equal(style["background-position"], "top right")

	setBackgroundImage(style, "  ")
	assert.equal(style["background-image"], "none")
})

test("background image urls are read back from every css form and written so they cannot break the style attribute", () => {
	assert.equal(backgroundImageUrl("url('https://a.test/x.png')"), "https://a.test/x.png")
	assert.equal(backgroundImageUrl('url("https://a.test/x.png")'), "https://a.test/x.png")
	assert.equal(backgroundImageUrl("url(https://a.test/x.png)"), "https://a.test/x.png")
	assert.equal(backgroundImageUrl("none"), "")
	assert.equal(backgroundImageCss(`https://a.test/it's "x" (1).png`), "url('https://a.test/it%27s%20%22x%22%20%281%29.png')")
})

test("the row and its content area are sent with their background images", () => {
	const email = emailWithBody()
	const row = createRow([12], "600px")
	setBackgroundImage(row.container.style, "https://a.test/row.png")
	setBackgroundImage(row.content.style, "https://a.test/content.png")

	const html = renderRowHtml(email, row, 0, createRenderContext(email, 600))

	assert.match(html, /class="row row-1[^"]*"[^>]*style="[^"]*background-image:url\('https:\/\/a\.test\/row\.png'\)/)
	assert.match(html, /class="row-content[^"]*"[^>]*style="[^"]*background-image:url\('https:\/\/a\.test\/content\.png'\);[^"]*background-size:cover/)
})

test("a button border has a width and a colour on all four sides, in the editor and in the sent email", () => {
	const button = createModule(MODULE_TYPES.button)
	assert.deepEqual(buttonBorder(button.descriptor.button.style), { width: "0px", color: "transparent" })
	assert.deepEqual(buttonBorder({}), { width: "0px", color: "transparent" })

	setButtonBorder(button.descriptor.button.style, { width: "2px" })
	assert.deepEqual(buttonBorder(button.descriptor.button.style), { width: "2px", color: "#000000" })

	setButtonBorder(button.descriptor.button.style, { color: "rgb(10, 20, 30)" })
	assert.deepEqual(buttonBorder(button.descriptor.button.style), { width: "2px", color: "rgb(10, 20, 30)" })

	const style = buttonLinkStyle(button)
	for (const side of ["top", "right", "bottom", "left"]) {
		assert.ok(style.includes(`border-${side}:2px solid rgb(10, 20, 30)`))
	}
	assert.ok(renderModuleHtml(button, createRenderContext(emailWithBody(), 600)).includes(`style="${style}"`))

	setButtonBorder(button.descriptor.button.style, { color: "" })
	setButtonBorder(button.descriptor.button.style, { width: "0px" })
	assert.equal(button.descriptor.button.style["border-left"], "0px solid transparent")
})

test("a button border that is dashed, or drawn on one side only, is read from its widest side and stays dashed when edited", () => {
	const style: Record<string, any> = {
		"border-top": "0px none transparent",
		"border-right": "0px solid transparent",
		"border-bottom": "0px solid transparent",
		"border-left": "3px dashed #FF0000",
	}
	assert.deepEqual(buttonBorder(style), { width: "3px", color: "#FF0000" })

	setButtonBorder(style, { width: "1px" })
	assert.equal(style["border-top"], "1px dashed #FF0000")
	assert.equal(style["border-left"], "1px dashed #FF0000")

	const borderless: Record<string, any> = { "border-top": "0px none transparent" }
	setButtonBorder(borderless, { width: "2px" })
	assert.equal(borderless["border-bottom"], "2px solid #000000")
})
