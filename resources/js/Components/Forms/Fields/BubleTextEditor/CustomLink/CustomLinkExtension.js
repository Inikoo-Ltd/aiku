import { Link } from "@tiptap/extension-link"

const CustomLink = Link.extend({
  addAttributes() {
    return {
      ...this.parent?.(),

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
        parseHTML: element => element.getAttribute("href"),
      },

      target: {
        default: null,
        parseHTML: element => element.getAttribute("target"),
      },

      rel: {
        default: null,
        parseHTML: element => element.getAttribute("rel"),
      },
    }
  },

  /**
   * 🔥 THIS IS THE IMPORTANT PART
   * Override Link's default renderHTML
   */
  renderHTML({ HTMLAttributes }) {
    const attrs = { ...HTMLAttributes }

    // ❌ Remove TipTap forced rel
    if (!attrs.rel) {
      delete attrs.rel
    }

    return ["a", attrs, 0]
  },

  addCommands() {
    return {
      setCustomLink:
        attrs =>
        ({ commands }) => {
          if (!attrs?.href) {
            console.warn("The href attribute is required but was not provided.")
            return false
          }

          return commands.setMark("link", attrs)
        },
    }
  },
})

export default CustomLink
