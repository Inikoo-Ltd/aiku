import assert from "node:assert/strict"
import test from "node:test"
import { getSchema } from "@tiptap/core"
import Document from "@tiptap/extension-document"
import Paragraph from "@tiptap/extension-paragraph"
import Text from "@tiptap/extension-text"
import { ProductCarousel } from "../../resources/js/Components/Forms/Fields/BubleTextEditor/ProductCarousel/ProductCarouselExtension.ts"
import {
	CAROUSEL_TEXT_TAGS,
	carouselTextTag,
	defaultProductCarouselAttributes,
} from "../../resources/js/Components/Forms/Fields/BubleTextEditor/ProductCarousel/productCarouselAttributes.ts"

const schema = getSchema([Document, Paragraph, Text, ProductCarousel])
const renderCarousel = (attributes: Record<string, unknown>) =>
	(schema.nodes.productCarousel.spec.toDOM!(schema.nodes.productCarousel.create(attributes)) as [string, Record<string, string>])[1]

const parseRule = schema.nodes.productCarousel.spec.parseDOM![0]
const attributeSpecs = (ProductCarousel.config.addAttributes as () => Record<string, { parseHTML: (element: any) => unknown }>).call({ options: {} })
const parseCarousel = (dataAttributes: Record<string, string>) => {
	const element = { getAttribute: (name: string) => dataAttributes[name] ?? null }

	return Object.fromEntries(Object.entries(attributeSpecs).map(([name, spec]) => [name, spec.parseHTML(element)]))
}

test("editors can pick H2 to H6 or a paragraph", () => {
	assert.deepEqual([...CAROUSEL_TEXT_TAGS], ["h2", "h3", "h4", "h5", "h6", "p"])
})

test("a new carousel keeps the title as H3 and product names as plain text", () => {
	assert.equal(defaultProductCarouselAttributes().titleTag, "h3")
	assert.equal(defaultProductCarouselAttributes().productNameTag, "p")
})

test("both tags are saved independently on the carousel element", () => {
	const html = renderCarousel({ codes: ["XMAS-01"], title: "Gifts", titleTag: "h2", productNameTag: "h4" })

	assert.equal(html["data-title-tag"], "h2")
	assert.equal(html["data-product-name-tag"], "h4")
	assert.equal(parseRule.tag, "div[data-product-carousel]")
})

test("saved tags are read back, and carousels saved before the option keep their old look", () => {
	assert.equal(parseCarousel({ "data-title-tag": "h5", "data-product-name-tag": "h2" }).titleTag, "h5")
	assert.equal(parseCarousel({ "data-title-tag": "h5", "data-product-name-tag": "h2" }).productNameTag, "h2")
	assert.equal(parseCarousel({}).titleTag, "h3")
	assert.equal(parseCarousel({}).productNameTag, "p")
})

test("anything that is not an allowed tag falls back", () => {
	assert.equal(carouselTextTag("H4", "h3"), "h4")
	assert.equal(carouselTextTag("h1", "h3"), "h3")
	assert.equal(carouselTextTag("script", "p"), "p")
	assert.equal(carouselTextTag(null, "p"), "p")
	assert.equal(renderCarousel({ titleTag: "h1", productNameTag: "div" })["data-title-tag"], "h3")
	assert.equal(renderCarousel({ titleTag: "h1", productNameTag: "div" })["data-product-name-tag"], "p")
})
