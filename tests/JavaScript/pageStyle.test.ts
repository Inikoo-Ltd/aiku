import assert from "node:assert/strict"
import test from "node:test"
import {
	applyPageStyleToBlock,
	changedPageStyleFields,
	usesWebsiteLayout,
} from "../../resources/js/Composables/usePageStyle.ts"
import TextBlueprint from "../../resources/js/Components/CMS/Webpage/Text/Blueprint.ts"

const blueprint = [
	{
		name: "Button",
		key: ["button"],
		editGlobalStyle: "button",
		replaceForm: [
			{ key: ["container", "properties", "text"], type: "textProperty" },
			{ key: ["container", "properties", "background"], type: "background" },
			{ key: ["container", "properties", "border"], type: "border" },
		],
	},
	{
		name: "Layout",
		key: ["container", "properties"],
		replaceForm: [
			{ key: ["background"], type: "background" },
			{ key: ["padding"], type: "padding" },
			{ key: ["text"], type: "textProperty" },
		],
	},
]

const block = (fieldValue: Record<string, any> = {}, extra: Record<string, any> = {}) => ({
	type: "family-2",
	web_block: { layout: { data: { fieldValue } } },
	...extra,
})

const changeAt = (changes: { path: string[], value: unknown }[], path: string) =>
	changes.find((change) => change.path.join(".") === path)?.value

test("button radius is written into the button border and reported as a change", () => {
	const webBlock = block()
	const changes = applyPageStyleToBlock(webBlock, { button: { radius: 24 } }, ["button.radius"], blueprint)

	assert.equal(webBlock.web_block.layout.data.fieldValue.button.container.properties.border.rounded.topleft.value, 24)
	assert.deepEqual(changeAt(changes, "button.container.properties.border.rounded"), {
		unit: "px",
		topleft: { value: 24 },
		topright: { value: 24 },
		bottomleft: { value: 24 },
		bottomright: { value: 24 },
	})
})

test("only the style paths are reported, never the data the workshop loaded into the block", () => {
	const webBlock = block({ family: { name: "Cotton bags", products: [{ code: "CB-01", price: 4.5 }] } })
	const changes = applyPageStyleToBlock(webBlock, { spacing: { vertical: 40 } }, ["spacing.vertical"], blueprint)

	assert.ok(changes.length > 0)
	assert.ok(changes.every((change) => change.path[0] === "container"))
	assert.ok(changes.every((change) => !JSON.stringify(change.value).includes("CB-01")))
})

test("spacing is written on every screen the block has its own padding for", () => {
	const webBlock = block({ container: { properties: { padding: { unit: "px", desktop: { unit: "px", top: { value: 5 } } } } } })
	const changes = applyPageStyleToBlock(webBlock, { spacing: { vertical: 40 } }, ["spacing.vertical"], blueprint)

	assert.deepEqual(changeAt(changes, "container.properties.padding.top"), { value: 40 })
	assert.deepEqual(changeAt(changes, "container.properties.padding.desktop.top"), { value: 40 })
	assert.deepEqual(changeAt(changes, "container.properties.padding.desktop.bottom"), { value: 40 })
})

test("padding set in another unit than px is left alone", () => {
	const webBlock = block({ container: { properties: { padding: { unit: "%", top: { value: 5 } } } } })
	const changes = applyPageStyleToBlock(webBlock, { spacing: { vertical: 40 } }, ["spacing.vertical"], blueprint)

	assert.deepEqual(changes, [])
	assert.equal(webBlock.web_block.layout.data.fieldValue.container.properties.padding.top.value, 5)
})

test("a block background that is an image keeps its image", () => {
	const webBlock = block({ container: { properties: { background: { type: "image", image: "hero.jpg" } } } })
	const changes = applyPageStyleToBlock(webBlock, { colors: { background: "rgba(1, 2, 3, 1)" } }, ["colors.background"], blueprint)

	assert.deepEqual(changes, [])
	assert.equal(webBlock.web_block.layout.data.fieldValue.container.properties.background.type, "image")
})

test("text colour goes to block text and button text colour to buttons", () => {
	const webBlock = block()
	const style = { colors: { text: "rgba(10, 10, 10, 1)", button_text: "rgba(255, 255, 255, 1)" } }
	const changes = applyPageStyleToBlock(webBlock, style, ["colors.text", "colors.button_text"], blueprint)

	assert.equal(changeAt(changes, "container.properties.text.color"), "rgba(10, 10, 10, 1)")
	assert.equal(changeAt(changes, "button.container.properties.text.color"), "rgba(255, 255, 255, 1)")
})

test("blocks that follow the website template are not changed", () => {
	const webBlock = block({}, { uses_website_layout: true })
	const changes = applyPageStyleToBlock(webBlock, { button: { radius: 24 } }, ["button.radius"], blueprint)

	assert.equal(usesWebsiteLayout(webBlock), true)
	assert.deepEqual(changes, [])
	assert.deepEqual(webBlock.web_block.layout.data.fieldValue, {})
})

test("blocks without a blueprint, like raw HTML, are not changed", () => {
	const webBlock = block({ value: "<section>...</section>" })

	assert.deepEqual(applyPageStyleToBlock(webBlock, { button: { radius: 24 } }, ["button.radius"], undefined), [])
})

test("a cleared choice is not reported as changed, so blocks keep what they have", () => {
	assert.deepEqual(changedPageStyleFields({ button: { radius: 24 } }, { button: { radius: null } }), [])
	assert.deepEqual(changedPageStyleFields({ button: { radius: 24 } }, { button: { radius: 8 } }), ["button.radius"])
})

test("the text block takes the page text colour and font", () => {
	const webBlock = block({ value: "<p>Returns policy</p>", container: { properties: { padding: {} } } })
	applyPageStyleToBlock(webBlock, { colors: { text: "rgba(0, 255, 0, 1)" }, font_family: "Raleway" }, ["colors.text", "font_family"], TextBlueprint.blueprint)

	assert.deepEqual(webBlock.web_block.layout.data.fieldValue.container.properties.text, { color: "rgba(0, 255, 0, 1)", fontFamily: "Raleway" })
	assert.equal(webBlock.web_block.layout.data.fieldValue.value, "<p>Returns policy</p>")
})

test("blocks whose text sits in the editor take the page text colour and font on their container", () => {
	const ctaBlueprint = [
		{ key: ["container", "properties", "visibility"], type: "visibility" },
		{
			name: "Button",
			key: ["button"],
			replaceForm: [{ key: ["container", "properties", "text"], type: "textProperty" }],
		},
		{ name: "Layout", key: ["container", "properties"], replaceForm: [{ key: ["background"], type: "background" }] },
	]
	const webBlock = block({ text: "<h2>Complete Your Bath & Beauty Collection</h2>" })
	applyPageStyleToBlock(webBlock, { colors: { text: "indigo", button_text: "yellow" }, font_family: "Arial" }, ["colors.text", "colors.button_text", "font_family"], ctaBlueprint)

	const fieldValue = webBlock.web_block.layout.data.fieldValue
	assert.deepEqual(fieldValue.container.properties.text, { color: "indigo", fontFamily: "Arial" })
	assert.deepEqual(fieldValue.button.container.properties.text, { color: "yellow", fontFamily: "Arial" })
	assert.equal(fieldValue.text, "<h2>Complete Your Bath & Beauty Collection</h2>")
})

test("a block with no container of its own gets no container text", () => {
	const webBlock = block()
	const changes = applyPageStyleToBlock(webBlock, { colors: { text: "indigo" } }, ["colors.text"], [
		{ key: ["container", "properties", "visibility"], type: "visibility" },
	])

	assert.deepEqual(changes, [])
})
