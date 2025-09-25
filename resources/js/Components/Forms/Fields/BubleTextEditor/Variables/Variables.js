import Mention  from "@tiptap/extension-mention"
import { PluginKey } from '@tiptap/pm/state'

export const MentionPluginKey = new PluginKey('variableMention')

const CustomMention = Mention.configure({
  HTMLAttributes: { class: 'mention' },

  // Single trigger ({{)
  suggestions: [
    {
      char: '{{',
      pluginKey: MentionPluginKey,

      allow: ({ state, range }) => {
        if (range.from < 2) return false
        const textBefore = state.doc.textBetween(range.from - 2, range.from, '\0', '\0')
        return textBefore === '{{'
      },

      items: ({ query }) => {
        const q = query?.toLowerCase() ?? ''
        return [
          'name',
          'username',
          'email',
          'reference',
          'favourites_count',
          'cart_count',
          'cart_amount',
        ]
          .filter(item => item.toLowerCase().startsWith(q))
          .slice(0, 5)
          .map(item => ({ id: item, label: item }))
      },

      command: ({ editor, range, props }) => {
        const nodeAfter = editor.view.state.selection.$to.nodeAfter
        const overrideSpace = nodeAfter?.text?.startsWith(' ')

        if (overrideSpace) {
          range.to += 1
        }

        editor
          .chain()
          .focus()
          .insertContentAt(range, [
            {
              type: 'mention', // no need for CustomMention.name
              attrs: {
                ...props,
                mentionSuggestionChar: '{{',
              },
            },
            { type: 'text', text: ' ' },
          ])
          .run()

        editor.view.dom.ownerDocument.defaultView
          ?.getSelection()
          ?.collapseToEnd()
      },
    },
  ],

  // How mentions should render in text mode (plain)
  renderText({ node }) {
    return `{{${node.attrs.label ?? node.attrs.id}}}`
  },

  // How mentions should render in HTML mode
  renderHTML({ options, node }) {
    return [
      'span',
      { ...options.HTMLAttributes, 'data-type': 'mention' },
      `{{${node.attrs.label ?? node.attrs.id}}}`,
    ]
  },
})

export default CustomMention
