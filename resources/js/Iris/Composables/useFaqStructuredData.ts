import {
    injectStructuredDataScript,
    isFilledValue,
    isPlainObject,
    normalizeUrl,
    removeStructuredDataScript,
    stripHtml,
    type StructuredDataNode,
} from "@/Iris/Composables/useStructuredData"

type FaqStructuredDataWebpageData = {
    canonical_url?: string
}

type BuildFaqStructuredDataOptions = {
    faqs?: any[] | null
    webpageData?: FaqStructuredDataWebpageData
    listId?: string | number | null
}

const buildQuestionNodes = (faqs: BuildFaqStructuredDataOptions["faqs"]): StructuredDataNode[] => {
    if (!Array.isArray(faqs)) return []

    const questionNodes = new Map<string, StructuredDataNode>()

    for (const faq of faqs) {
        if (!isPlainObject(faq)) continue

        const name = stripHtml(faq.label ?? faq.question)
        const text = stripHtml(faq.description ?? faq.answer)

        if (!name || !text) continue
        if (questionNodes.has(name)) continue

        questionNodes.set(name, {
            "@type": "Question",
            name,
            acceptedAnswer: {
                "@type": "Answer",
                text,
            },
        })
    }

    return Array.from(questionNodes.values())
}

export const buildFaqPageNode = ({
    faqs,
    webpageData,
    listId,
}: BuildFaqStructuredDataOptions): StructuredDataNode | null => {
    const mainEntity = buildQuestionNodes(faqs)

    if (!mainEntity.length) return null

    const node: StructuredDataNode = {
        "@type": "FAQPage",
        mainEntity,
    }

    const pageUrl = normalizeUrl(webpageData?.canonical_url)
    if (pageUrl) {
        node["@id"] = `${pageUrl}#faq${isFilledValue(listId) ? `-${listId}` : ""}`
        node.url = pageUrl
    }

    return node
}

export const buildFaqPageJsonLd = (options: BuildFaqStructuredDataOptions): string | null => {
    const faqPageNode = buildFaqPageNode(options)

    if (!faqPageNode) return null

    return JSON.stringify({
        "@context": "https://schema.org",
        ...faqPageNode,
    }).replace(/</g, "\\u003c")
}

export const useFaqStructuredData = () => {
    const mountFaqStructuredData = (
        options: BuildFaqStructuredDataOptions
    ): HTMLScriptElement | null => {
        const faqPageNode = buildFaqPageNode(options)

        if (!faqPageNode) return null

        return injectStructuredDataScript({
            "@context": "https://schema.org",
            ...faqPageNode,
        })
    }

    return {
        buildFaqPageNode,
        mountFaqStructuredData,
        removeStructuredDataScript,
    }
}
