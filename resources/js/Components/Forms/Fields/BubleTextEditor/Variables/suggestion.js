import { VueRenderer } from "@tiptap/vue-3"
import { computePosition, flip, offset, shift } from "@floating-ui/dom"

import MentionList from "./MentionList.vue"

export default {
	items: ({ query }) => {
		const q = query?.toLowerCase() ?? "" // safe default

		return [
			"name",
			"username",
			"email",
			"reference",
			"favourites_count",
			"cart_count",
			"cart_amount",
		]
			.filter((item) => item.toLowerCase().startsWith(q))
			.slice(0, 5)
	},

	render: () => {
		let component
		let popup

		const positionPopup = (clientRect) => {
			const rect = clientRect?.()
			if (!popup || !rect) {
				return
			}

			computePosition({ getBoundingClientRect: () => rect }, popup, {
				placement: "bottom-start",
				strategy: "fixed",
				middleware: [offset(8), flip(), shift({ padding: 8 })],
			}).then(({ x, y }) => {
				Object.assign(popup.style, { left: `${x}px`, top: `${y}px` })
			})
		}

		return {
			onStart: (props) => {
				component = new VueRenderer(MentionList, {
					props,
					editor: props.editor,
				})

				if (!props.clientRect) {
					return
				}

				popup = document.createElement("div")
				Object.assign(popup.style, { position: "fixed", top: "0", left: "0", zIndex: "9999" })
				popup.appendChild(component.element)
				document.body.appendChild(popup)
				positionPopup(props.clientRect)
			},

			onUpdate(props) {
				component.updateProps(props)
				positionPopup(props.clientRect)
			},

			onKeyDown(props) {
				if (props.event.key === "Escape") {
					popup?.remove()
					popup = null
					return true
				}

				return component.ref?.onKeyDown(props)
			},

			onExit() {
				popup?.remove()
				popup = null
				component.destroy()
			},
		}
	},
}
