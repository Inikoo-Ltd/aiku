import { Extension } from '@tiptap/core'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'

export type SelectionOptions = {
  /**
   * The class name that should be added to the selected text.
   * @default 'selection'
   * @example 'is-selected'
   */
  className: string
}

/**
 * This extension allows you to add a class to the selected text.
 * @see https://www.tiptap.dev/api/extensions/selection
 */
export const Selection = Extension.create({
  name: 'selection',

  addOptions() {
    return {
      className: 'selection',
    }
  },

  addProseMirrorPlugins() {
    const { editor, options } = this

    return [
      new Plugin({
        key: new PluginKey('selection'),
        props: {
            decorations(state) {
              const { from, to } = state.selection;
          
              const decorations: Decoration[] = [];
          
              state.doc.nodesBetween(from, to, (node, pos) => {
                if (node.type.name === 'image') {
                  decorations.push(
                    Decoration.node(pos, pos + node.nodeSize, {
                      class: 'selected-image',
                    }),
                  );
                }
              });
          
              // Also highlight selected text
              if (!state.selection.empty) {
                decorations.push(
                  Decoration.inline(from, to, {
                    class: options?.className ?? 'selection', // fallback
                  }),
                );
              }
          
              return DecorationSet.create(state.doc, decorations);
            },
          },
      }),
    ]
  },
})

export default Selection
