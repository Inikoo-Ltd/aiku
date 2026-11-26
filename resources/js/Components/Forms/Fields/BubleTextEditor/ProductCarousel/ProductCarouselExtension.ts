import { Node } from "@tiptap/core"
import { VueNodeViewRenderer } from "@tiptap/vue-3"
import type { Component } from "vue"
import { DEFAULT_PRODUCT_NAME_TAG, DEFAULT_TITLE_TAG, type ProductCarouselAttributes, carouselTextTag, carouselWidth, defaultProductCarouselAttributes, familyProductsLimit, parseProductCodes, productCarouselSource, productsPerView } from "./productCarouselAttributes.ts"

export { type ProductCarouselAttributes, defaultProductCarouselAttributes } from "./productCarouselAttributes.ts"

export const PRODUCT_CAROUSEL_NODE = "productCarousel"

declare module "@tiptap/core" {
	interface Commands<ReturnType> {
		productCarousel: {
			insertProductCarousel: (attributes: ProductCarouselAttributes) => ReturnType
		}
	}
}

interface ProductCarouselOptions {
	onEdit: (position: number) => void
	title: string
	nodeView: Component | null
	previewRoute: () => { name: string, parameters: Record<string, any> } | undefined
}

export const ProductCarousel = Node.create<ProductCarouselOptions>({
	name: PRODUCT_CAROUSEL_NODE,
	group: "block",
	atom: true,
	selectable: true,
	draggable: true,

	addOptions() {
		return {
			onEdit: () => {},
			title: "Product carousel",
			nodeView: null,
			previewRoute: () => undefined,
		}
	},

	addAttributes() {
		return {
			source: {
				default: defaultProductCarouselAttributes().source,
				parseHTML: (element) => productCarouselSource(element.getAttribute("data-source")),
				renderHTML: (attributes) => ({ "data-source": productCarouselSource(attributes.source) }),
			},
			family: {
				default: "",
				parseHTML: (element) => element.getAttribute("data-family") ?? "",
				renderHTML: (attributes) => (productCarouselSource(attributes.source) === "family" && attributes.family ? { "data-family": attributes.family } : {}),
			},
			limit: {
				default: defaultProductCarouselAttributes().limit,
				parseHTML: (element) => familyProductsLimit(element.getAttribute("data-limit")),
				renderHTML: (attributes) => (productCarouselSource(attributes.source) === "family" ? { "data-limit": String(familyProductsLimit(attributes.limit)) } : {}),
			},
			codes: {
				default: [],
				parseHTML: (element) => parseProductCodes(element.getAttribute("data-codes") ?? ""),
				renderHTML: (attributes) => (productCarouselSource(attributes.source) === "products" ? { "data-codes": (attributes.codes ?? []).join(",") } : {}),
			},
			title: {
				default: "",
				parseHTML: (element) => element.getAttribute("data-title") ?? "",
				renderHTML: (attributes) => (attributes.title ? { "data-title": attributes.title } : {}),
			},
			perView: {
				default: defaultProductCarouselAttributes().perView,
				parseHTML: (element) => productsPerView(element.getAttribute("data-per-view")),
				renderHTML: (attributes) => ({ "data-per-view": String(productsPerView(attributes.perView)) }),
			},
			titleTag: {
				default: DEFAULT_TITLE_TAG,
				parseHTML: (element) => carouselTextTag(element.getAttribute("data-title-tag"), DEFAULT_TITLE_TAG),
				renderHTML: (attributes) => ({ "data-title-tag": carouselTextTag(attributes.titleTag, DEFAULT_TITLE_TAG) }),
			},
			productNameTag: {
				default: DEFAULT_PRODUCT_NAME_TAG,
				parseHTML: (element) => carouselTextTag(element.getAttribute("data-product-name-tag"), DEFAULT_PRODUCT_NAME_TAG),
				renderHTML: (attributes) => ({ "data-product-name-tag": carouselTextTag(attributes.productNameTag, DEFAULT_PRODUCT_NAME_TAG) }),
			},
			width: {
				default: defaultProductCarouselAttributes().width,
				parseHTML: (element) => carouselWidth(element.getAttribute("data-width")),
				renderHTML: (attributes) => ({ "data-width": carouselWidth(attributes.width) }),
			},
		}
	},

	parseHTML() {
		return [{ tag: "div[data-product-carousel]", priority: 100 }]
	},

	renderHTML({ HTMLAttributes }) {
		return ["div", { "data-product-carousel": "", ...HTMLAttributes }]
	},

	addNodeView() {
		return this.options.nodeView ? VueNodeViewRenderer(this.options.nodeView) : null
	},

	addCommands() {
		return {
			insertProductCarousel:
				(attributes) =>
				({ commands }) =>
					commands.insertContent({ type: this.name, attrs: attributes }),
		}
	},
})
