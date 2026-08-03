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

type DepartmentStructuredDataWebpageData = {
    seo_data?: {
        structured_data?: unknown
    }
    canonical_url?: string
}

type BuildDepartmentStructuredDataOptions = {
    subDepartments?: any[] | null
    collections?: any[] | null
    webpageData?: DepartmentStructuredDataWebpageData
    listId?: string | number | null
}

const getDepartmentSourceItems = ({
    subDepartments,
    collections,
}: Pick<BuildDepartmentStructuredDataOptions, "subDepartments" | "collections">): Record<string, any>[] => {
    return [
        ...(Array.isArray(subDepartments) ? subDepartments : []),
        ...(Array.isArray(collections) ? collections : []),
    ].filter((item): item is Record<string, any> => isPlainObject(item))
}

const getDepartmentItemName = (item: Record<string, any>): string | undefined => {
    return (
        stripHtml(item.title) ??
        stripHtml(item.name) ??
        stripHtml(item.label) ??
        (isFilledValue(item.code) ? String(item.code) : undefined)
    )
}

const getDepartmentItemUrl = (item: Record<string, any>): string | undefined => {
    return normalizeUrl(item.url ?? item.canonical_url ?? item.slug)
}

const buildDepartmentRelatedNodes = (
    options: Pick<BuildDepartmentStructuredDataOptions, "subDepartments" | "collections">
): StructuredDataNode[] => {
    const relatedNodes = new Map<string, StructuredDataNode>()

    for (const item of getDepartmentSourceItems(options)) {
        const url = getDepartmentItemUrl(item)
        const name = getDepartmentItemName(item)

        if (!url || !name) continue

        const node: StructuredDataNode = {
            "@type": "CollectionPage",
            "@id": url,
            name,
            url,
        }

        const imageUrls = getEntityImageUrls(item)
        if (imageUrls.length) {
            node.image = imageUrls
        }

        if (!relatedNodes.has(url)) {
            relatedNodes.set(url, node)
        }
    }

    return Array.from(relatedNodes.values())
}

export const buildDepartmentItemListNode = ({
    subDepartments,
    collections,
    webpageData,
    listId,
}: BuildDepartmentStructuredDataOptions): StructuredDataNode | null => {
    const itemListElement = buildDepartmentRelatedNodes({ subDepartments, collections }).map((item, index) => ({
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
        node["@id"] = `${pageUrl}#department-list${isFilledValue(listId) ? `-${listId}` : ""}`
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

export const buildDepartmentStructuredData = (
    options: BuildDepartmentStructuredDataOptions
): StructuredDataValue | null => {
    const itemListNode = buildDepartmentItemListNode(options)
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
// console.log('ereeeeeeeeeee', structuredData)
    return structuredData
}

export const useDepartmentStructuredData = () => {
    const mountDepartmentStructuredData = (
        options: BuildDepartmentStructuredDataOptions
    ): HTMLScriptElement | null => {
        const structuredData = buildDepartmentStructuredData(options)
        if (!structuredData) return null

        return injectStructuredDataScript(structuredData)
    }

    return {
        buildDepartmentItemListNode,
        buildDepartmentStructuredData,
        mountDepartmentStructuredData,
        removeStructuredDataScript,
    }
}
