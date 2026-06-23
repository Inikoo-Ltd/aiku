export type StructuredDataNode = Record<string, any>
export type StructuredDataValue = StructuredDataNode | StructuredDataNode[]

type StructuredDataWebpageData = {
    seo_data?: {
        structured_data?: unknown
    }
    title?: string
    description?: string
    canonical_url?: string
    model_type?: string
    sub_type?: string
}

type GenerateProductsStructureOptions = {
    webBlocks?: any[] | Record<string, any>
    categoryName?: string | null
    currencyCode?: string | null
}

type BuildStructuredDataOptions = {
    webpageData?: StructuredDataWebpageData
    webBlocks?: any[] | Record<string, any>
    currencyCode?: string | null
    websiteName?: string | null
}

const PRODUCT_BLOCK_TYPES = ["products-1", "products-2"]  // Family page
const DEPARTMENT_BLOCK_TYPES = ["sub-departments-1", "sub-departments-2", "sub-departments-3"]

const normalizeWebBlocks = (webBlocks: GenerateProductsStructureOptions["webBlocks"]): any[] => {
    if (Array.isArray(webBlocks)) return webBlocks
    if (!webBlocks || typeof webBlocks !== "object") return []

    return Object.values(webBlocks).flatMap((blockGroup) =>
        Array.isArray(blockGroup) ? blockGroup : [blockGroup]
    )
}

export const parseStructuredData = (raw: unknown): StructuredDataValue | null => {
    if (!raw) return null
    if (Array.isArray(raw)) return raw
    if (typeof raw === "object") return raw as StructuredDataNode
    if (typeof raw !== "string") return null

    try {
        const parsed = JSON.parse(raw)
        return Array.isArray(parsed) || typeof parsed === "object" ? parsed : null
    } catch {
        return null
    }
}

export const injectStructuredDataScript = (
    data: StructuredDataValue
): HTMLScriptElement | null => {
    try {
        const script = document.createElement("script")
        script.type = "application/ld+json"
        script.textContent = JSON.stringify(data)
        document.head.appendChild(script)
        return script
    } catch (error) {
        console.error("Failed to inject structured data:", error)
        return null
    }
}

// Method: remove script from DOM (used when move to another page to avoid duplicated structured data)
export const removeStructuredDataScript = (
    script: HTMLScriptElement | null | undefined
): void => {
    script?.parentNode?.removeChild(script)
}

export const generateProductsStructureFromProductsList = ({
    webBlocks,
    categoryName,
    currencyCode,
}: GenerateProductsStructureOptions): StructuredDataNode[] => {
    const variants: StructuredDataNode[] = []

    for (const block of normalizeWebBlocks(webBlocks)) {
        if (!PRODUCT_BLOCK_TYPES.includes(block?.type)) continue

        const fieldValue = block?.web_block?.layout?.data?.fieldValue ?? block?.structure
        const products: any[] = fieldValue?.products?.data ?? []

        for (const product of products) {
            if (!product?.url) continue

            const variant: StructuredDataNode = {
                "@type": "Product",
                "@id": product.url,
                name: product.name,
                sku: product.code,
                url: product.url,
            }

            if (product.description) {
                variant.description = product.description
            }

            if (product.brand_name) {
                variant.brand = {
                    "@type": "Brand",
                    name: product.brand_name,
                }
            }

            if (categoryName) {
                variant.category = categoryName
            }

            const imageUrl =
                product.web_images?.main?.original?.original ??
                product.web_images?.main?.original ??
                product.web_images?.main?.gallery?.original ??
                product.web_images?.main?.gallery ??
                product.image?.source?.original

            if (imageUrl) {
                variant.image = [imageUrl]
            }

            if (product.rating && product.rating_count) {
                variant.aggregateRating = {
                    "@type": "AggregateRating",
                    ratingValue: product.rating,
                    reviewCount: product.rating_count,
                    bestRating: 5,
                    worstRating: 1,
                }
            }

            if (product.price) {
                variant.offers = {
                    "@type": "Offer",
                    price: product.price,
                    availability:
                        product.stock > 0
                            ? "https://schema.org/InStock"
                            : "https://schema.org/OutOfStock",
                    url: product.url,
                }

                if (currencyCode) {
                    variant.offers.priceCurrency = currencyCode
                }
            }

            variants.push(variant)
        }
    }

    return variants
}

export const isFilledValue = (value: unknown): boolean => {
    return value !== null && value !== undefined && value !== ""
}

export const isPlainObject = (value: unknown): value is Record<string, any> => {
    return typeof value === "object" && value !== null && !Array.isArray(value)
}

export const stripHtml = (value: unknown): string | undefined => {
    if (typeof value !== "string") return undefined

    const sanitized = value
        .replace(/<[^>]*>/g, " ")
        .replace(/\s+/g, " ")
        .trim()

    return sanitized || undefined
}

export const getEntityImageUrls = (entity: Record<string, any> | null | undefined): string[] => {
    const candidates = [
        entity?.web_images?.main?.original?.original,
        entity?.web_images?.main?.original,
        entity?.web_images?.main?.gallery?.original,
        entity?.web_images?.main?.gallery,
        entity?.web_images?.all?.[0]?.original?.original,
        entity?.web_images?.all?.[0]?.original,
        entity?.web_images?.all?.[0]?.gallery?.original,
        entity?.web_images?.all?.[0]?.gallery,
        entity?.image?.source?.original,
        entity?.image,
    ]

    return [...new Set(candidates.filter((candidate): candidate is string => typeof candidate === "string" && candidate.length > 0))]
}

export const normalizeUrl = (value: unknown): string | undefined => {
    if (typeof value !== "string" || value.length === 0) return undefined

    try {
        if (typeof window !== "undefined" && window.location?.origin) {
            return new URL(value, window.location.origin).toString()
        }

        return new URL(value).toString()
    } catch {
        return value
    }
}

const getDepartmentSourceItems = (
    webBlocks: BuildStructuredDataOptions["webBlocks"]
): Record<string, any>[] => {
    const items: Record<string, any>[] = []

    for (const block of normalizeWebBlocks(webBlocks)) {
        if (!DEPARTMENT_BLOCK_TYPES.includes(block?.type)) continue

        const fieldValue = block?.web_block?.layout?.data?.fieldValue ?? block?.structure ?? {}

        for (const item of [
            ...(Array.isArray(fieldValue?.sub_departments) ? fieldValue.sub_departments : []),
            ...(Array.isArray(fieldValue?.collections) ? fieldValue.collections : []),
        ]) {
            if (isPlainObject(item)) {
                items.push(item)
            }
        }
    }

    return items
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
    webBlocks: BuildStructuredDataOptions["webBlocks"]
): StructuredDataNode[] => {
    const relatedNodes = new Map<string, StructuredDataNode>()

    for (const item of getDepartmentSourceItems(webBlocks)) {
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

const buildDepartmentItemListNode = (
    webBlocks: BuildStructuredDataOptions["webBlocks"],
    pageUrl?: string
): StructuredDataNode | null => {
    const itemListElement = buildDepartmentRelatedNodes(webBlocks).map((item, index) => ({
        "@type": "ListItem",
        position: index + 1,
        name: item.name,
        url: item.url,
        // item: item["@id"] ?? item.url,
    }))

    if (!itemListElement.length) return null

    const node: StructuredDataNode = {
        "@type": "ItemList",
        itemListElement,
    }

    if (pageUrl) {
        node["@id"] = `${pageUrl}#department-list`
        node.url = pageUrl
    }

    return node
}

const buildFamilyProductNode = ({
    webpageData,
    websiteName,
}: Pick<BuildStructuredDataOptions, "webpageData" | "websiteName">): StructuredDataNode => {
    const node: StructuredDataNode = {
        "@type": "ProductGroup",
        name: webpageData?.title,
    }

    if (webpageData?.description) {
        node.description = webpageData.description
    }

    node.aggregateRating = {
        "@type": "AggregateRating",
        ratingValue: 4.8,
        reviewCount: 524,
    }

    if (websiteName) {
        node.review = {
            "@type": "Review",
            reviewRating: {
                "@type": "Rating",
                ratingValue: 5,
                bestRating: 5,
            },
            author: {
                "@type": "Organization",
                name: websiteName,
            },
        }
    }

    return node
}

// Node: CollectionPage (for department pages)
// const buildDepartmentNode = ({
//     webpageData,
//     webBlocks,
// }: Pick<BuildStructuredDataOptions, "webpageData" | "webBlocks">): StructuredDataNode | null => {
//     const pageUrl = normalizeUrl(webpageData?.canonical_url)
//     const hasPart = buildDepartmentRelatedNodes(webBlocks)
//     const description = stripHtml(webpageData?.description)
//     const name = stripHtml(webpageData?.title)

//     if (!pageUrl && !name && !description && !hasPart.length) return null

//     const node: StructuredDataNode = {
//         "@type": "CollectionPage",
//     }

//     if (pageUrl) {
//         node["@id"] = `${pageUrl}#webpage`
//         node.url = pageUrl
//         node.mainEntityOfPage = pageUrl
//     }

//     if (name) {
//         node.name = name
//     }

//     if (description) {
//         node.description = description
//     }

//     if (hasPart.length) {
//         node.hasPart = hasPart
//     }

//     return node
// }

export const normalizeStructuredDataForGraph = (
    structuredData: StructuredDataValue | null
): StructuredDataNode | null => {
    if (!structuredData) return null

    if (Array.isArray(structuredData)) {
        return {
            "@context": "https://schema.org",
            "@graph": structuredData,
        }
    }

    return structuredData
}

const findOrCreateProductGroupNode = (
    data: StructuredDataNode,
    buildNode: () => StructuredDataNode
): StructuredDataNode => {
    if (Array.isArray(data["@graph"])) {
        const existingNode =
            data["@graph"].find((node: StructuredDataNode) => node?.["@type"] === "ProductGroup") ??
            null

        if (existingNode) return existingNode

        const newNode = buildNode()
        data["@graph"].push(newNode)
        return newNode
    }

    if (data["@type"] === "ProductGroup") {
        return data
    }

    const newNode = buildNode()

    data["@context"] = data["@context"] ?? "https://schema.org"
    data["@graph"] = [newNode]

    return newNode
}

// const findOrCreateCollectionPageNode = (
//     data: StructuredDataNode,
//     buildNode: () => StructuredDataNode
// ): StructuredDataNode => {
//     if (Array.isArray(data["@graph"])) {
//         const existingNode =
//             data["@graph"].find((node: StructuredDataNode) =>
//                 ["CollectionPage", "WebPage"].includes(node?.["@type"])
//             ) ?? null

//         if (existingNode) return existingNode

//         const newNode = buildNode()
//         data["@graph"].push(newNode)
//         return newNode
//     }

//     if (["CollectionPage", "WebPage"].includes(data["@type"])) {
//         return data
//     }

//     const currentNode = { ...data }
//     const newNode = buildNode()

//     for (const key of Object.keys(data)) {
//         delete data[key]
//     }

//     data["@context"] = currentNode["@context"] ?? "https://schema.org"
//     data["@graph"] = [currentNode, newNode]

//     return newNode
// }

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

const mergeAutoVariants = (productNode: StructuredDataNode, autoVariants: StructuredDataNode[]): void => {
    const variantMap = new Map<string, StructuredDataNode>()

    for (const existingVariant of productNode.hasVariant ?? []) {
        if (existingVariant?.["@id"]) {
            variantMap.set(existingVariant["@id"], existingVariant)
        }
    }

    for (const autoVariant of autoVariants) {
        if (autoVariant?.["@id"] && !variantMap.has(autoVariant["@id"])) {
            variantMap.set(autoVariant["@id"], autoVariant)
        }
    }

    productNode.hasVariant = Array.from(variantMap.values())
}

// const mergeAutoHasParts = (pageNode: StructuredDataNode, autoHasParts: StructuredDataNode[]): void => {
//     const hasPartMap = new Map<string, StructuredDataNode>()

//     for (const existingPart of pageNode.hasPart ?? []) {
//         const key =
//             existingPart?.["@id"] ??
//             existingPart?.url ??
//             existingPart?.name

//         if (key) {
//             hasPartMap.set(String(key), existingPart)
//         }
//     }

//     for (const autoHasPart of autoHasParts) {
//         const key =
//             autoHasPart?.["@id"] ??
//             autoHasPart?.url ??
//             autoHasPart?.name

//         if (key && !hasPartMap.has(String(key))) {
//             hasPartMap.set(String(key), autoHasPart)
//         }
//     }

//     pageNode.hasPart = Array.from(hasPartMap.values())
// }

export const mergeStructuredDataNode = (
    targetNode: StructuredDataNode,
    sourceNode: StructuredDataNode
): StructuredDataNode => {
    for (const [key, sourceValue] of Object.entries(sourceNode)) {
        const targetValue = targetNode[key]

        if (!isFilledValue(targetValue)) {
            targetNode[key] = sourceValue
            continue
        }

        if (isPlainObject(targetValue) && isPlainObject(sourceValue)) {
            mergeStructuredDataNode(targetValue, sourceValue)
            continue
        }

        if (Array.isArray(targetValue) && Array.isArray(sourceValue) && targetValue.length === 0) {
            targetNode[key] = sourceValue
        }
    }

    return targetNode
}

// Method: build structured data based on the Page type ('family', 'product', etc)
export const buildStructuredData = ({
    webpageData,
    webBlocks,
    currencyCode,
    websiteName,
}: BuildStructuredDataOptions): StructuredDataValue | null => {
    if (webpageData?.sub_type === "department") {
        const baseStructuredData = parseStructuredData(webpageData?.seo_data?.structured_data)
        const pageUrl = normalizeUrl(webpageData?.canonical_url)
        // const autoDepartmentNode = buildDepartmentNode({
        //     webpageData,
        //     webBlocks,
        // })
        const itemListNode = buildDepartmentItemListNode(webBlocks, pageUrl)

        if (!baseStructuredData) {
            const graph = [/*autoDepartmentNode,*/ itemListNode].filter(
                (node): node is StructuredDataNode => node !== null
            )

            if (!graph.length) return null

            return {
                "@context": "https://schema.org",
                "@graph": graph,
            }
        }

        const structuredData =
            normalizeStructuredDataForGraph(baseStructuredData) ?? {
                "@context": "https://schema.org",
            }

        // if (autoDepartmentNode) {
        //     const departmentNode = findOrCreateCollectionPageNode(structuredData, () => ({
        //         ...autoDepartmentNode,
        //     }))

        //     mergeStructuredDataNode(departmentNode, autoDepartmentNode)
        //     mergeAutoHasParts(departmentNode, autoDepartmentNode.hasPart ?? [])
        // }

        if (itemListNode) {
            appendGraphNode(
                structuredData,
                itemListNode,
                (node) => node?.["@type"] === "ItemList" && node?.["@id"] === itemListNode["@id"]
            )
        }

        return structuredData
    }

    if (webpageData?.model_type === "ProductCategory" && webpageData?.sub_type === "family") {
        const baseStructuredData = parseStructuredData(webpageData?.seo_data?.structured_data)
    
        const autoVariants = generateProductsStructureFromProductsList({
            webBlocks,
            categoryName: webpageData?.title ?? null,
            currencyCode,
        })
    
        if (!autoVariants.length) {
            return baseStructuredData
        }
    
        const structuredData =
            normalizeStructuredDataForGraph(baseStructuredData) ?? {
                "@context": "https://schema.org",
            }
    
        const productNode = findOrCreateProductGroupNode(structuredData, () =>
            buildFamilyProductNode({ webpageData, websiteName })
        )
    
        mergeAutoVariants(productNode, autoVariants)
        return structuredData
    }

    // Note: Product page structured data is mounted independently in the product
    // components (product-1 / product-2) via useProductStructuredData, so it lives
    // in its own <script> and stays separate from the rest of the page schema.

    return null

}

export const useStructuredData = () => {
    const mountStructuredData = (options: BuildStructuredDataOptions): HTMLScriptElement | null => {
        const structuredData = buildStructuredData(options)
        if (!structuredData) return null

        return injectStructuredDataScript(structuredData)
    }

    return {
        buildStructuredData,
        generateProductsStructureFromProductsList,
        injectStructuredDataScript,
        mountStructuredData,
        parseStructuredData,
        removeStructuredDataScript,
    }
}
