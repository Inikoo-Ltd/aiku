import assert from "node:assert/strict"
import test from "node:test"
import { getSchema } from "@tiptap/core"
import Document from "@tiptap/extension-document"
import Paragraph from "@tiptap/extension-paragraph"
import Text from "@tiptap/extension-text"
import { ProductCarousel } from "../../resources/js/Components/Forms/Fields/BubleTextEditor/ProductCarousel/ProductCarouselExtension.ts"
import {
	PRODUCTS_PER_VIEW_OPTIONS,
	carouselWidth,
	defaultProductCarouselAttributes,
	productCarouselHasContent,
	productCarouselPreviewParams,
	productsPerView,
} from "../../resources/js/Components/Forms/Fields/BubleTextEditor/ProductCarousel/productCarouselAttributes.ts"

const schema = getSchema([Document, Paragraph, Text, ProductCarousel])
const attributeSpecs = (ProductCarousel.config.addAttributes as () => Record<string, { parseHTML: (element: any) => unknown }>).call({ options: {} })
const parseWidth = (dataWidth: string | null) => attributeSpecs.width.parseHTML({ getAttribute: (name: string) => (name === "data-width" ? dataWidth : null) })
const renderCarousel = (attributes: Record<string, unknown>) =>
	(schema.nodes.productCarousel.spec.toDOM!(schema.nodes.productCarousel.create(attributes)) as [string, Record<string, string>])[1]

test("editors can make the products bigger by showing two at once", () => {
	assert.deepEqual([...PRODUCTS_PER_VIEW_OPTIONS], [2, 3, 4, 5, 6])
	assert.equal(productsPerView("2"), 2)
	assert.equal(renderCarousel({ codes: ["XMAS-01"], perView: 2 })["data-per-view"], "2")
})

test("a products per view outside the options falls back to four", () => {
	assert.equal(productsPerView(1), 4)
	assert.equal(productsPerView(7), 4)
	assert.equal(productsPerView(null), 4)
})

test("the preview asks for the picked products in their order, once each", () => {
	assert.deepEqual(productCarouselPreviewParams({ source: "products", codes: ["XMAS-02", "xmas-01", "Xmas-02", " "], family: "IGNORED", limit: 5 }), {
		source: "products",
		codes: "XMAS-02,xmas-01",
	})
})

test("the preview of a family carousel asks for the family up to its limit", () => {
	assert.deepEqual(productCarouselPreviewParams({ source: "family", codes: ["IGNORED"], family: "XMAS", limit: 50 }), {
		source: "family",
		family: "XMAS",
		limit: 20,
	})
})

test("the preview only loads once there is something to show", () => {
	assert.equal(productCarouselHasContent({ source: "products", codes: [], family: "XMAS" }), false)
	assert.equal(productCarouselHasContent({ source: "products", codes: ["XMAS-01"], family: "" }), true)
	assert.equal(productCarouselHasContent({ source: "family", codes: ["XMAS-01"], family: "" }), false)
	assert.equal(productCarouselHasContent({ source: "family", codes: [], family: "XMAS" }), true)
})

test("a carousel can be as wide as the text or wide, and stays as wide as the text unless chosen", () => {
	assert.equal(defaultProductCarouselAttributes().width, "content")
	assert.equal(renderCarousel({ codes: ["XMAS-01"], width: "wide" })["data-width"], "wide")
	assert.equal(renderCarousel({ codes: ["XMAS-01"], width: "huge" })["data-width"], "content")
	assert.equal(parseWidth("wide"), "wide")
	assert.equal(parseWidth(null), "content")
	assert.equal(carouselWidth("full"), "content")
})
