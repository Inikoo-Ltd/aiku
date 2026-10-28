import type { Node as ProseMirrorNode } from '@tiptap/pm/model'

const normalise = (text: string): string => text.replace(/\u00A0/g, ' ')

/**
 * Where the quote sits in the editor, searched one paragraph, heading or list item at a time,
 * the same lines the check was asked about.
 */
export const findQuoteInDoc = (doc: ProseMirrorNode, quote: string): { from: number, to: number } | null => {
    let found: { from: number, to: number } | null = null

    doc.descendants((node, position) => {
        if (found || !node.isTextblock) {
            return !found
        }

        let text = ''
        const offsets: number[] = []
        node.forEach((child, childOffset) => {
            const piece = child.isText ? normalise(child.text ?? '') : '\uFFFC'
            for (let index = 0; index < piece.length; index++) {
                offsets.push(position + 1 + childOffset + index)
            }
            text += piece
        })

        const start = text.indexOf(quote)
        if (start >= 0) {
            found = { from: offsets[start], to: offsets[start + quote.length - 1] + 1 }
        }

        return false
    })

    return found
}

/**
 * Replaces the quote in an HTML fragment that is not open in an editor (an email block),
 * keeping the formatting of the text around it.
 */
export const replaceQuoteInHtml = (html: string, quote: string, suggestion: string): string | null => {
    const container = document.createElement('div')
    container.innerHTML = html

    const blocks = container.querySelectorAll('p, li, h1, h2, h3, h4, h5, h6, blockquote, td, div')
    const leafBlocks = Array.from(blocks).filter((block) => !block.querySelector('p, li, h1, h2, h3, h4, h5, h6, blockquote, td, div'))

    for (const root of leafBlocks.length ? leafBlocks : [container]) {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT)
        const nodes: Array<Text | null> = []
        let text = ''
        while (walker.nextNode()) {
            const node = walker.currentNode
            if (node.nodeType === Node.TEXT_NODE) {
                nodes.push(node as Text)
                text += normalise((node as Text).data)
            } else if ((node as Element).tagName === 'BR') {
                nodes.push(null)
                text += '\uFFFC'
            }
        }

        const start = text.indexOf(quote)
        if (start < 0) {
            continue
        }

        const end = start + quote.length
        let offset = 0
        let isReplaced = false
        for (const node of nodes) {
            const nodeStart = offset
            const nodeEnd = offset + (node ? node.data.length : 1)
            offset = nodeEnd
            if (!node || nodeEnd <= start || nodeStart >= end) {
                continue
            }

            const before = node.data.slice(0, Math.max(0, start - nodeStart))
            const after = node.data.slice(Math.max(0, end - nodeStart))
            node.data = before + (isReplaced ? '' : suggestion) + after
            isReplaced = true
        }

        return container.innerHTML
    }

    return null
}
