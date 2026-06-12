type StructuredDataNode = Record<string, any>
type StructuredDataValue = StructuredDataNode | StructuredDataNode[]

type StructuredDataWebpageData = {
    seo_data?: {
        structured_data?: unknown
    }
    title?: string
    description?: string
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

const PRODUCT_BLOCK_TYPES = ["products-1", "products-2"]

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

const normalizeStructuredDataForGraph = (
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

// Method: build structured data based on the Page type ('family', 'product', etc)
export const buildStructuredData = ({
    webpageData,
    webBlocks,
    currencyCode,
    websiteName,
}: BuildStructuredDataOptions): StructuredDataValue | null => {
    if (webpageData?.model_type === "ProductCategory" || webpageData?.sub_type === "family") {
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
