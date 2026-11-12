import { Node } from "@tiptap/core"
import {
	type CtaButtonAttributes,
	ctaButtonRel,
	ctaButtonStyle,
	ctaButtonWrapperStyle,
	defaultCtaButtonAttributes,
	normaliseCtaButtonAttributes,
} from "./ctaButtonLayout.ts"

export const CTA_BUTTON_NODE = "ctaButton"

declare module "@tiptap/core" {
	interface Commands<ReturnType> {
		ctaButton: {
			insertCtaButton: (attributes: Partial<CtaButtonAttributes>) => ReturnType
		}
	}
}

interface CtaButtonOptions {
	onEdit: (position: number) => void
}

const anchorAttributes = (attributes: CtaButtonAttributes): Record<string, string> => {
	const rel = ctaButtonRel(attributes)

	return {
		class: "cta-button",
		...(attributes.href ? { href: attributes.href } : {}),
		target: attributes.target,
		...(rel ? { rel } : {}),
		"data-size": attributes.size,
		"data-shape": attributes.shape,
		...(attributes.backgroundColor ? { "data-background-color": attributes.backgroundColor } : {}),
		...(attributes.textColor ? { "data-text-color": attributes.textColor } : {}),
		style: ctaButtonStyle(attributes),
	}
}

const attributesFromElement = (element: HTMLElement): CtaButtonAttributes => {
	const anchor = element.querySelector("a")

	return normaliseCtaButtonAttributes({
		label: anchor?.textContent,
		href: anchor?.getAttribute("href"),
		target: anchor?.getAttribute("target"),
		alignment: element.dataset.align,
		size: anchor?.dataset.size,
		shape: anchor?.dataset.shape,
		backgroundColor: anchor?.dataset.backgroundColor,
		textColor: anchor?.dataset.textColor,
	})
}

export const CtaButton = Node.create<CtaButtonOptions>({
	name: CTA_BUTTON_NODE,
	group: "block",
	atom: true,
	selectable: true,
	draggable: true,

	addOptions() {
		return {
			onEdit: () => {},
		}
	},

	addAttributes() {
		return Object.fromEntries(
			Object.entries(defaultCtaButtonAttributes()).map(([name, value]) => [name, { default: value, rendered: false }]),
		)
	},

	parseHTML() {
		return [
			{
				tag: "div[data-cta-button]",
				priority: 100,
				getAttrs: (element) => attributesFromElement(element as HTMLElement),
			},
		]
	},

	renderHTML({ node }) {
		const attributes = normaliseCtaButtonAttributes(node.attrs)

		return [
			"div",
			{ "data-cta-button": "", "data-align": attributes.alignment, style: ctaButtonWrapperStyle(attributes.alignment) },
			["a", anchorAttributes(attributes), attributes.label],
		]
	},

	addNodeView() {
		return ({ node, getPos }) => {
			const wrapper = document.createElement("div")
			const anchor = document.createElement("a")
			wrapper.appendChild(anchor)

			const render = (attributes: CtaButtonAttributes) => {
				wrapper.dataset.ctaButton = ""
				wrapper.dataset.align = attributes.alignment
				wrapper.setAttribute("style", ctaButtonWrapperStyle(attributes.alignment))
				Array.from(anchor.attributes).forEach((attribute) => anchor.removeAttribute(attribute.name))
				Object.entries(anchorAttributes(attributes)).forEach(([name, value]) => anchor.setAttribute(name, value))
				anchor.style.cursor = "pointer"
				anchor.textContent = attributes.label
			}

			render(normaliseCtaButtonAttributes(node.attrs))

			anchor.addEventListener("click", (event) => event.preventDefault())
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
					if (updatedNode.type.name !== CTA_BUTTON_NODE) {
						return false
					}

					render(normaliseCtaButtonAttributes(updatedNode.attrs))

					return true
				},
			}
		}
	},

	addCommands() {
		return {
			insertCtaButton:
				(attributes) =>
				({ commands }) =>
					commands.insertContent({ type: this.name, attrs: normaliseCtaButtonAttributes({ ...defaultCtaButtonAttributes(), ...attributes }) }),
		}
	},
})
