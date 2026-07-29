import { Node, mergeAttributes } from "@tiptap/core"
import { VueNodeViewRenderer } from "@tiptap/vue-3"
import IframeNodeView from "@/Components/Forms/Fields/BubleTextEditor/Iframe/IframeNodeView.vue"

export default Node.create({
    name: "iframe",
    group: "block",
    atom: true,
    draggable: true,
    selectable: true,

    addOptions() {
        return {
            allowFullscreen: true,
            HTMLAttributes: {
                class: "editor-iframe",
            },
        }
    },

    addAttributes() {
        return {
            src: {
                default: null,
            },
            width: {
                default: "400",
            },
            height: {
                default: "225",
            },
            frameborder: {
                default: "0",
            },
            allowfullscreen: {
                default: this.options.allowFullscreen,
                parseHTML: () => this.options.allowFullscreen,
            },
        }
    },

    parseHTML() {
        return [
            {
                tag: "iframe",
                getAttrs: (element) => {
                    return element.closest("[data-youtube-video]") ? false : null
                },
            },
        ]
    },

    renderHTML({ HTMLAttributes }) {
        return ["iframe", mergeAttributes(this.options.HTMLAttributes, HTMLAttributes)]
    },

    addNodeView() {
        return VueNodeViewRenderer(IframeNodeView)
    },

    addCommands() {
        return {
            setIframe:
                (options) =>
                ({ tr, dispatch }) => {
                    const { selection } = tr
                    const node = this.type.create(options)

                    if (dispatch) {
                        tr.replaceRangeWith(selection.from, selection.to, node)
                    }

                    return true
                },
        }
    },
})
