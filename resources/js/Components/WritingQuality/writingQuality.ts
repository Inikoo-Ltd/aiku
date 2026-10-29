import axios from 'axios'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'
import type { Editor } from '@tiptap/vue-3'
import { ctrans } from '@/Composables/useTrans'
import { findQuoteInDoc } from './writingQualityText'

export { findQuoteInDoc, replaceQuoteInHtml } from './writingQualityText'

export type WritingQualityContext = 'product_description' | 'web_content' | 'newsletter' | 'customer_email'

export interface WritingQualitySegment {
    id: string
    text: string
}

export interface WritingQualityIssue {
    key: string
    segment: string
    quote: string
    category: string
    explanation: string
    suggestion: string
}

export const writingQualityCategoryLabel = (category: string): string => ({
    repetition: ctrans('Repetition'),
    cliche: ctrans('Stock phrase'),
    hype: ctrans('Overselling'),
    structure: ctrans('Artificial structure'),
    filler: ctrans('Filler'),
    formatting: ctrans('Formatting'),
    tone: ctrans('Tone'),
    vague: ctrans('Not specific'),
    translation: ctrans('Sounds translated'),
    unsupported_claim: ctrans('Check this claim'),
    placeholder: ctrans('Placeholder'),
} as Record<string, string>)[category] ?? category

export const checkWritingQuality = async (segments: WritingQualitySegment[], context: WritingQualityContext): Promise<WritingQualityIssue[]> => {
    try {
        const response = await axios.post(route('grp.json.writing_quality.check'), { segments, context })

        return (response.data.issues ?? []).map((issue: Omit<WritingQualityIssue, 'key'>, index: number) => ({ ...issue, key: `${issue.segment}-${index}` }))
    } catch (error: any) {
        const errors = error?.response?.data?.errors
        throw new Error(errors ? Object.values(errors).flat()[0] as string : (error?.response?.data?.message ?? ctrans('The writing check failed. Try again.')))
    }
}

export const writingQualityPluginKey = new PluginKey<string[]>('writingQuality')

/**
 * Underlines the quotes of the open suggestions; it stays out of the document, so nothing is saved.
 */
export const writingQualityPlugin = (): Plugin<string[]> => new Plugin<string[]>({
    key: writingQualityPluginKey,
    state: {
        init: () => [],
        apply: (transaction, quotes) => transaction.getMeta(writingQualityPluginKey) ?? quotes,
    },
    props: {
        decorations(state) {
            const quotes = writingQualityPluginKey.getState(state) ?? []
            const decorations = quotes
                .map((quote) => findQuoteInDoc(state.doc, quote))
                .filter((range): range is { from: number, to: number } => !!range)
                .map((range) => Decoration.inline(range.from, range.to, { class: 'writing-quality-mark' }))

            return DecorationSet.create(state.doc, decorations)
        },
    },
})

export const showWritingQualityMarks = (editor: Editor | null | undefined, quotes: string[]): void => {
    if (!editor || editor.isDestroyed) {
        return
    }

    editor.view.dispatch(editor.state.tr.setMeta(writingQualityPluginKey, quotes).setMeta('addToHistory', false))
}
