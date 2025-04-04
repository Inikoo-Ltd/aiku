import { mergeAttributes } from "@tiptap/core";
import Image from "@tiptap/extension-image";

export  const CustomImage = Image.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      float: {
        default: "none",
        rendered: false,
      },
    };
  },

  renderHTML({ node }) {
    return [
      "img",
      mergeAttributes(this.options.HTMLAttributes, {
        src: node.attrs.src,
        alt: node.attrs.alt,
        title: node.attrs.title,
        style: `float: ${node.attrs.float}; margin: 10px; max-width: 250px;`,
      }),
    ];
  },
});
