import {
    injectStructuredDataScript,
    normalizeUrl,
    removeStructuredDataScript,
    stripHtml,
} from "@/Iris/Composables/useStructuredData"

type StructuredDataNode = Record<string, any>

// Node: BreadcrumbList (kept independent from the page structured data so it can be
// mounted directly by BreadcrumbsIris.vue, making it easier to maintain)
export const buildBreadcrumbListNode = (
    breadcrumbs: any[] | null | undefined
): StructuredDataNode | null => {
    if (!Array.isArray(breadcrumbs) || !breadcrumbs.length) return null

    const itemListElement = breadcrumbs
        .map((breadcrumb, index) => {
            if (breadcrumb?.type !== "simple") return null

            const name = stripHtml(breadcrumb?.simple?.label) ?? (index === 0 ? "Home" : undefined)
            if (!name) return null

            const item = normalizeUrl(breadcrumb?.simple?.url)

            const listItem: StructuredDataNode = {
                "@type": "ListItem",
                position: index + 1,
                name,
            }

            if (item) {
                listItem.item = item
            }

            return listItem
        })
        .filter((item): item is StructuredDataNode => item !== null)

    if (!itemListElement.length) return null

    return {
        "@type": "BreadcrumbList",
        itemListElement,
    }
}

export const useBreadcrumbStructuredData = () => {
    const mountBreadcrumbStructuredData = (
        breadcrumbs: any[] | null | undefined
    ): HTMLScriptElement | null => {
        const breadcrumbNode = buildBreadcrumbListNode(breadcrumbs)

        if (!breadcrumbNode) return null

        return injectStructuredDataScript({
            "@context": "https://schema.org",
            ...breadcrumbNode,
        })
    }

    return {
        buildBreadcrumbListNode,
        mountBreadcrumbStructuredData,
        removeStructuredDataScript,
    }
}
