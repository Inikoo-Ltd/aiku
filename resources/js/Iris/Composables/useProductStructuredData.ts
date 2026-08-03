import { ctrans } from "@/Composables/useTrans"
import {
    getEntityImageUrls,
    injectStructuredDataScript,
    isFilledValue,
    isPlainObject,
    mergeStructuredDataNode,
    normalizeStructuredDataForGraph,
    parseStructuredData,
    removeStructuredDataScript,
    stripHtml,
    type StructuredDataNode,
    type StructuredDataValue,
} from "@/Iris/Composables/useStructuredData"

type ProductStructuredDataWebpageData = {
    seo_data?: {
        structured_data?: unknown
    }
    title?: string
    description?: string
    canonical_url?: string
}

type BuildProductStructuredDataOptions = {
    product?: Record<string, any> | null
    variant?: Record<string, any> | null
    webpageData?: ProductStructuredDataWebpageData
    currencyCode?: string | null
    websiteName?: string | null
}

const buildOfferNode = ({
    product,
    currencyCode,
    websiteName,
    pageUrl,
}: {
    product: Record<string, any>
    currencyCode?: string | null
    websiteName?: string | null
    pageUrl?: string | null
}): StructuredDataNode | null => {
    if (!isFilledValue(product?.price)) return null

    const offerNode: StructuredDataNode = {
        "@type": "Offer",
        price: product.price,
        availability:
            product?.stock > 0
                ? "https://schema.org/InStock"
                : "https://schema.org/OutOfStock",
    }

    if (currencyCode || product?.currency_code) {
        offerNode.priceCurrency = currencyCode ?? product.currency_code
    }

    if (pageUrl) {
        offerNode.url = pageUrl
    }

    if (websiteName) {
        offerNode.seller = {
            "@type": "Organization",
            name: websiteName,
        }
    }

    return offerNode
}

const buildAdditionalProperties = (
    specifications: Record<string, any> | null | undefined
): StructuredDataNode[] | undefined => {
    if (!isPlainObject(specifications)) return undefined

    const properties = Object.entries(specifications)
        .filter(([, value]) => isFilledValue(value))
        .map(([name, value]) => ({
            "@type": "PropertyValue",
            name,
            value: name === "countries_of_origin"
                ? (Array.isArray(value) && value.length
                    ? value.map((country: { name?: string }) => country?.name).filter(Boolean).join(", ")
                    : ctrans("No country"))
                : String(value),
        }))

    return properties.length ? properties : undefined
}

export const buildProductNode = ({
    product,
    variant,
    webpageData,
    currencyCode,
    websiteName,
}: BuildProductStructuredDataOptions): StructuredDataNode | null => {
    if (!isPlainObject(product)) return null

    const pageUrl = webpageData?.canonical_url ?? null
    const description =
        stripHtml(product.description) ??
        stripHtml(product.description_extra) ??
        stripHtml(webpageData?.description)

    const productNode: StructuredDataNode = {
        "@type": "Product",
        name: stripHtml(product.name) ?? webpageData?.title,
    }

    if (pageUrl) {
        productNode["@id"] = pageUrl
        productNode.url = pageUrl
        productNode.mainEntityOfPage = pageUrl
    }

    if (isFilledValue(product?.id)) {
        productNode.productID = String(product.id)
    }

    if (isFilledValue(product?.code)) {
        productNode.sku = product.code
        productNode.mpn = product.code
    }

    if (description) {
        productNode.description = description
    }

    const imageUrls = getEntityImageUrls(product)
    if (imageUrls.length) {
        productNode.image = imageUrls
    }

    if (isFilledValue(product?.brand_name)) {
        productNode.brand = {
            "@type": "Brand",
            name: product.brand_name,
        }
    }

    if (isFilledValue(product?.barcode)) {
        productNode.gtin = String(product.barcode)
    }

    const additionalProperties = buildAdditionalProperties(product.specifications)
    if (additionalProperties) {
        productNode.additionalProperty = additionalProperties
    }

    const offerNode = buildOfferNode({
        product,
        currencyCode,
        websiteName,
        pageUrl,
    })
    if (offerNode) {
        productNode.offers = offerNode
    }

    if (isFilledValue(product?.rating) && isFilledValue(product?.rating_count)) {
        productNode.aggregateRating = {
            "@type": "AggregateRating",
            ratingValue: product.rating,
            reviewCount: product.rating_count,
            bestRating: 5,
            worstRating: 1,
        }
    }

    const variantDimensions = Array.isArray(variant?.data?.variants)
        ? variant.data.variants
            .map((item: Record<string, any>) => item?.label)
            .filter((label: unknown): label is string => typeof label === "string" && label.length > 0)
        : []

    if (variant?.id) {
        productNode.isVariantOf = {
            "@type": "ProductGroup",
            productGroupID: String(variant.id),
            name: stripHtml(product.name) ?? webpageData?.title,
        }

        if (variantDimensions.length) {
            productNode.isVariantOf.variesBy = variantDimensions
        }
    }

    return productNode
}

const findOrCreateProductNode = (
    data: StructuredDataNode,
    buildNode: () => StructuredDataNode
): StructuredDataNode => {
    if (Array.isArray(data["@graph"])) {
        const existingNode =
            data["@graph"].find((node: StructuredDataNode) => node?.["@type"] === "Product") ?? null

        if (existingNode) return existingNode

        const newNode = buildNode()
        data["@graph"].push(newNode)
        return newNode
    }

    if (data["@type"] === "Product") {
        return data
    }

    const newNode = buildNode()

    data["@context"] = data["@context"] ?? "https://schema.org"
    data["@graph"] = [newNode]

    return newNode
}

export const buildProductStructuredData = (
    options: BuildProductStructuredDataOptions
): StructuredDataValue | null => {
    const baseStructuredData = parseStructuredData(options.webpageData?.seo_data?.structured_data)
    const autoProductNode = buildProductNode(options)

    if (!autoProductNode) {
        return baseStructuredData
    }

    if (!baseStructuredData) {
        return {
            "@context": "https://schema.org",
            ...autoProductNode,
        }
    }

    const structuredData =
        normalizeStructuredDataForGraph(baseStructuredData) ?? {
            "@context": "https://schema.org",
        }

    const productNode = findOrCreateProductNode(structuredData, () => ({
        ...autoProductNode,
    }))

    mergeStructuredDataNode(productNode, autoProductNode)

    return structuredData
}

export const useProductStructuredData = () => {
    const mountProductStructuredData = (
        options: BuildProductStructuredDataOptions
    ): HTMLScriptElement | null => {
        const structuredData = buildProductStructuredData(options)
        if (!structuredData) return null

        return injectStructuredDataScript(structuredData)
    }

    return {
        buildProductNode,
        buildProductStructuredData,
        mountProductStructuredData,
        removeStructuredDataScript,
    }
}
