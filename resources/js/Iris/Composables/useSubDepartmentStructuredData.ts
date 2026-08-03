import {
    getEntityImageUrls,
    injectStructuredDataScript,
    isFilledValue,
    isPlainObject,
    normalizeStructuredDataForGraph,
    normalizeUrl,
    parseStructuredData,
    removeStructuredDataScript,
    stripHtml,
    type StructuredDataNode,
    type StructuredDataValue,
} from "@/Iris/Composables/useStructuredData"

type SubDepartmentStructuredDataWebpageData = {
    seo_data?: {
        structured_data?: unknown
    }
    canonical_url?: string
}

type BuildSubDepartmentStructuredDataOptions = {
    families?: any[] | null
    collections?: any[] | null
    webpageData?: SubDepartmentStructuredDataWebpageData
    listId?: string | number | null
}

const getSubDepartmentSourceItems = ({
    families,
    collections,
}: Pick<BuildSubDepartmentStructuredDataOptions, "families" | "collections">): Record<string, any>[] => {
    return [
        ...(Array.isArray(families) ? families : []),
        ...(Array.isArray(collections) ? collections : []),
    ].filter((item): item is Record<string, any> => isPlainObject(item))
}

const getSubDepartmentItemName = (item: Record<string, any>): string | undefined => {
    return (
        stripHtml(item.title) ??
        stripHtml(item.name) ??
        stripHtml(item.label) ??
        (isFilledValue(item.code) ? String(item.code) : undefined)
    )
}

const getSubDepartmentItemUrl = (item: Record<string, any>): string | undefined => {
    return normalizeUrl(item.url ?? item.canonical_url ?? item.slug)
}

const getSubDepartmentItemImageUrls = (item: Record<string, any>): string[] => {
    const imageUrls = getEntityImageUrls(item)
    if (imageUrls.length) {
        return imageUrls
    }

    if (Array.isArray(item.images)) {
        return [
            ...new Set(
                item.images
                    .map((image: any) => image?.source ?? image)
                    .filter((source: unknown): source is string => typeof source === "string" && source.length > 0)
            ),
        ]
    }

    return []
}

const buildSubDepartmentRelatedNodes = (
    options: Pick<BuildSubDepartmentStructuredDataOptions, "families" | "collections">
): StructuredDataNode[] => {
    const relatedNodes = new Map<string, StructuredDataNode>()

    for (const item of getSubDepartmentSourceItems(options)) {
        const url = getSubDepartmentItemUrl(item)
        const name = getSubDepartmentItemName(item)

        if (!url || !name) continue

        const node: StructuredDataNode = {
            "@type": "CollectionPage",
            "@id": url,
            name,
            url,
        }

        const imageUrls = getSubDepartmentItemImageUrls(item)
        if (imageUrls.length) {
            node.image = imageUrls
        }

        if (!relatedNodes.has(url)) {
            relatedNodes.set(url, node)
        }
    }

    return Array.from(relatedNodes.values())
}

export const buildSubDepartmentItemListNode = ({
    families,
    collections,
    webpageData,
    listId,
}: BuildSubDepartmentStructuredDataOptions): StructuredDataNode | null => {
    const itemListElement = buildSubDepartmentRelatedNodes({ families, collections }).map((item, index) => ({
        "@type": "ListItem",
        position: index + 1,
        name: item.name,
        url: item.url,
    }))

    if (!itemListElement.length) return null

    const node: StructuredDataNode = {
        "@type": "ItemList",
        itemListElement,
    }

    const pageUrl = normalizeUrl(webpageData?.canonical_url)
    if (pageUrl) {
        node["@id"] = `${pageUrl}#sub-department-list${isFilledValue(listId) ? `-${listId}` : ""}`
        node.url = pageUrl
    }

    return node
}

const appendGraphNode = (
    data: StructuredDataNode,
    node: StructuredDataNode,
    matcher: (existingNode: StructuredDataNode) => boolean
): void => {
    if (Array.isArray(data["@graph"])) {
        if (!data["@graph"].some((existingNode: StructuredDataNode) => matcher(existingNode))) {
            data["@graph"].push(node)
        }

        return
    }

    if (matcher(data)) {
        return
    }

    const currentNode = { ...data }

    for (const key of Object.keys(data)) {
        delete data[key]
    }

    data["@context"] = currentNode["@context"] ?? "https://schema.org"
    data["@graph"] = [currentNode, node]
}

export const buildSubDepartmentStructuredData = (
    options: BuildSubDepartmentStructuredDataOptions
): StructuredDataValue | null => {
    const itemListNode = buildSubDepartmentItemListNode(options)
    const baseStructuredData = parseStructuredData(options.webpageData?.seo_data?.structured_data)

    if (!baseStructuredData) {
        if (!itemListNode) return null

        return {
            "@context": "https://schema.org",
            "@graph": [itemListNode],
        }
    }

    const structuredData =
        normalizeStructuredDataForGraph(baseStructuredData) ?? {
            "@context": "https://schema.org",
        }

    if (itemListNode) {
        appendGraphNode(
            structuredData,
            itemListNode,
            (node) => node?.["@type"] === "ItemList" && node?.["@id"] === itemListNode["@id"]
        )
    }

    console.log('pfldpslpf', structuredData)

    return structuredData
}

export const useSubDepartmentStructuredData = () => {
    const mountSubDepartmentStructuredData = (
        options: BuildSubDepartmentStructuredDataOptions
    ): HTMLScriptElement | null => {
        const structuredData = buildSubDepartmentStructuredData(options)
        if (!structuredData) return null

        return injectStructuredDataScript(structuredData)
    }

    return {
        buildSubDepartmentItemListNode,
        buildSubDepartmentStructuredData,
        mountSubDepartmentStructuredData,
        removeStructuredDataScript,
    }
}
