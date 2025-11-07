import { Link } from "@tiptap/extension-link"

const CustomLink = Link.extend({
	addAttributes() {
		return {
			type: {
				default: null,
			},
			workshop: {
				default: null,
			},
			id: {
				default: null,
			},
			content: {
				default: null,
			},
			class: {
				default: "customLink",
			},
			href: {
				default: null,
				parseHTML(element) {
					return element.getAttribute("href")
				},
			},
			target: {
				default: this.options.HTMLAttributes.target,
			},
		}
	},
	addCommands() {
		return {
			setCustomLink:
				(attrs) =>
				({ commands }) => {
					if (!attrs.href) {
						console.warn("The href attribute is required but was not provided.")
						return false
					}
					return commands.setMark("link", attrs)
				},
		}
	},
})




export default CustomLink
