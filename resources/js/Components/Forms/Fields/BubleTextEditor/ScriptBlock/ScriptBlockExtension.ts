import { Node } from "@tiptap/core"
import { decodeScriptBlockHtml, encodeScriptBlockHtml } from "../../../../../Composables/useHtmlWithScripts.ts"

export const SCRIPT_BLOCK_NODE = "scriptBlock"

declare module "@tiptap/core" {
	interface Commands<ReturnType> {
		scriptBlock: {
			insertScriptBlock: (html: string) => ReturnType
		}
	}
}

interface ScriptBlockOptions {
	onEdit: (position: number) => void
	title: string
	emptyText: string
}

export const balancedScriptHtml = (html: string): string => {
	const template = document.createElement("template")
	template.innerHTML = html

	return template.innerHTML.trim()
}

export const scriptBlockHtml = (html: string): string => {
	const element = document.createElement("div")
	element.setAttribute("data-script-block", "")
	element.setAttribute("data-html", encodeScriptBlockHtml(balancedScriptHtml(html)))

	return element.outerHTML
}

const summaryOf = (html: string): string => {
	const template = document.createElement("template")
	template.innerHTML = html
	const text = (template.content.textContent ?? "").replace(/\s+/g, " ").trim()

	return text || html.replace(/\s+/g, " ").trim()
}

export const ScriptBlock = Node.create<ScriptBlockOptions>({
	name: SCRIPT_BLOCK_NODE,
	group: "block",
	atom: true,
	selectable: true,
	draggable: true,

	addOptions() {
		return {
			onEdit: () => {},
			title: "</> Script",
			emptyText: "Empty script",
		}
	},

	addAttributes() {
		return {
			html: { default: "", rendered: false },
		}
	},

	parseHTML() {
		return [
			{
				tag: "div[data-script-block]",
				priority: 100,
				getAttrs: (element) => ({ html: decodeScriptBlockHtml((element as HTMLElement).getAttribute("data-html") ?? "") }),
			},
		]
	},

	renderHTML({ node }) {
		return ["div", { "data-script-block": "", "data-html": encodeScriptBlockHtml(node.attrs.html ?? "") }]
	},

	addNodeView() {
		return ({ node, getPos }) => {
			const wrapper = document.createElement("div")
			wrapper.className = "tiptap-script-block"
			wrapper.setAttribute("contenteditable", "false")
			wrapper.style.cssText = "margin:1rem 0;padding:0.75rem 1rem;border:1px dashed #9ca3af;border-radius:0.5rem;background:#f9fafb;font-family:ui-monospace,monospace;font-size:0.8125rem;color:#374151;cursor:pointer;"

			const title = document.createElement("div")
			title.style.cssText = "font-family:inherit;font-weight:600;margin-bottom:0.25rem;"
			const summary = document.createElement("div")
			summary.style.cssText = "overflow:hidden;white-space:nowrap;text-overflow:ellipsis;color:#6b7280;"
			wrapper.append(title, summary)

			const render = (html: string) => {
				title.textContent = this.options.title
				summary.textContent = summaryOf(html) || this.options.emptyText
			}

			render(node.attrs.html ?? "")

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
					if (updatedNode.type.name !== SCRIPT_BLOCK_NODE) {
						return false
					}
					render(updatedNode.attrs.html ?? "")

					return true
				},
			}
		}
	},

	addCommands() {
		return {
			insertScriptBlock:
				(html) =>
				({ commands }) =>
					commands.insertContent({ type: this.name, attrs: { html: balancedScriptHtml(html) } }),
		}
	},
})
