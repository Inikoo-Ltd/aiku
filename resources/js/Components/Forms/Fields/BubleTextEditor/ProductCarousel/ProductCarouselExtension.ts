import { Node } from "@tiptap/core"
import { type ProductCarouselAttributes, defaultProductCarouselAttributes, familyProductsLimit, parseProductCodes, productCarouselSource, productsPerView } from "./productCarouselAttributes"

export { type ProductCarouselAttributes, defaultProductCarouselAttributes } from "./productCarouselAttributes"

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
	emptyText: string
	familyText: (family: string, limit: number) => string
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
			emptyText: "No products yet",
			familyText: (family: string, limit: number) => `Family ${family}, up to ${limit} products`,
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
		}
	},

	parseHTML() {
		return [{ tag: "div[data-product-carousel]", priority: 100 }]
	},

	renderHTML({ HTMLAttributes }) {
		return ["div", { "data-product-carousel": "", ...HTMLAttributes }]
	},

	addNodeView() {
		return ({ node, getPos }) => {
			const wrapper = document.createElement("div")
			wrapper.className = "tiptap-product-carousel"
			wrapper.setAttribute("contenteditable", "false")
			wrapper.style.cssText = "margin:1rem 0;padding:0.75rem 1rem;border:1px dashed var(--app-accent-muted, #9ca3af);border-radius:0.5rem;background:var(--app-accent-soft, #f9fafb);font-size:0.8125rem;color:#374151;cursor:pointer;"

			const heading = document.createElement("div")
			heading.style.cssText = "font-weight:600;margin-bottom:0.25rem;"
			const subtitle = document.createElement("div")
			subtitle.style.cssText = "margin-bottom:0.375rem;color:#4b5563;"
			const codes = document.createElement("div")
			codes.style.cssText = "display:flex;flex-wrap:wrap;gap:0.25rem;"
			wrapper.append(heading, subtitle, codes)

			const render = (attributes: ProductCarouselAttributes) => {
				heading.textContent = this.options.title
				subtitle.textContent = attributes.title ?? ""
				subtitle.style.display = attributes.title ? "" : "none"
				codes.replaceChildren(
					...(productCarouselSource(attributes.source) === "family"
						? [attributes.family ? this.options.familyText(attributes.family, familyProductsLimit(attributes.limit)) : this.options.emptyText]
						: attributes.codes?.length ? attributes.codes : [this.options.emptyText]
					).map((code) => {
						const chip = document.createElement("span")
						chip.textContent = code
						chip.style.cssText = "padding:0.0625rem 0.5rem;border-radius:9999px;background:#ffffff;border:1px solid #e5e7eb;font-family:ui-monospace,monospace;font-size:0.75rem;"

						return chip
					}),
				)
			}

			render(node.attrs as ProductCarouselAttributes)

			wrapper.addEventListener("dblclick", (event) => {
				event.preventDefault()
				const position = typeof getPos === "function" ? getPos() : undefined
				if (typeof position === "number") {
					this.options.onEdit(position)
				}
			})

			return {
				dom: wrapper,
				update: (updatedNode) => {
					if (updatedNode.type.name !== PRODUCT_CAROUSEL_NODE) {
						return false
					}
					render(updatedNode.attrs as ProductCarouselAttributes)

					return true
				},
			}
		}
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
