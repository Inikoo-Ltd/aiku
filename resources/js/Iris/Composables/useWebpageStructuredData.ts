import {
    buildStructuredData,
    getCardImageUrl,
    isPlainObject,
    normalizeStructuredDataForGraph,
    normalizeUrl,
    normalizeWebBlocks,
    stripHtml,
    withImageLicense,
    type StructuredDataNode,
    type StructuredDataValue,
} from "@/Iris/Composables/useStructuredData"
import { buildBreadcrumbListNode } from "@/Iris/Composables/useBreadcrumbStructuredData"
import { buildDepartmentStructuredData } from "@/Iris/Composables/useDepartmentStructuredData"
import { buildSubDepartmentStructuredData } from "@/Iris/Composables/useSubDepartmentStructuredData"
import { buildFaqPageNode } from "@/Iris/Composables/useFaqStructuredData"
import { buildProductStructuredData } from "@/Iris/Composables/useProductStructuredData"

export type WebpageStructuredDataSource = {
    webpage_data: Record<string, any>
    web_blocks: any[] | Record<string, any>
    breadcrumbs?: any[]
    website_url?: string
    website_name?: string | null
    terms_url?: string | null
    currency_code?: string | null
}

type BlockStructuredDataContext = {
    fieldValue: Record<string, any>
    listId: string | number
    webpageData: Record<string, any>
    currencyCode?: string | null
    websiteName?: string | null
}

const SCHEMA_CONTEXT = "https://schema.org"

const PRODUCT_BLOCK_TYPES = ["product-1", "product-2", "product-3", "product-4"]

const CATEGORY_ITEM_LIST_BLOCK_TYPES = [
    "sub-departments-1",
    "sub-departments-2",
    "sub-departments-3",
    "sub-departments-4",
    "families-1",
    "families-2",
    "families-3",
    "families-4",
]

const getListItems = (list: unknown): any[] => {
    if (Array.isArray(list)) return list

    return (list as Record<string, any>)?.data ?? []
}

const buildPictureItemListNode = (
    items: unknown,
    webpageData: Record<string, any>,
    listName: string
): StructuredDataNode | null => {
    const itemListElement: StructuredDataNode[] = []
    const seenUrls = new Set<string>()

    for (const item of Array.isArray(items) ? items : []) {
        if (!isPlainObject(item)) continue

        const url = normalizeUrl(item.url)
        const name = stripHtml(item.title) ?? stripHtml(item.name)
        if (!url || !name || seenUrls.has(url)) continue

        seenUrls.add(url)

        const listItem: StructuredDataNode = { "@type": "ListItem", position: itemListElement.length + 1, name, url }

        const imageUrl = getCardImageUrl(item)
        if (imageUrl) {
            listItem.image = imageUrl
        }

        itemListElement.push(listItem)
    }

    if (!itemListElement.length) return null

    const node: StructuredDataNode = { "@type": "ItemList", itemListElement }

    const pageUrl = normalizeUrl(webpageData?.canonical_url)
    if (pageUrl) {
        node["@id"] = `${pageUrl}#${listName}`
        node.url = pageUrl
    }

    return node
}

const LUIGI_SEE_ALSO_TYPES = ["luigi-trends", "luigi-recently_ordered", "luigi-last_seen", "luigi-item_detail_alternatives"]

const getSeeAlsoProducts = (productsData: Record<string, any> | null | undefined): any[] => {
    switch (productsData?.type) {
        case "custom":
            return productsData.products ?? []
        case "current-family":
            return productsData.current_family?.option ?? []
        case "other-family":
            return productsData.other_family?.option ?? []
    }

    if (LUIGI_SEE_ALSO_TYPES.includes(productsData?.type)) return []

    return productsData?.top_sellers ?? []
}

const withListNodes = (
    structuredData: StructuredDataValue | null,
    listNodes: (StructuredDataNode | null)[]
): StructuredDataValue | null => {
    const nodes = listNodes.filter((node): node is StructuredDataNode => !!node)
    if (!nodes.length) return structuredData

    const graph = normalizeStructuredDataForGraph(structuredData)
    if (!graph) return { "@context": SCHEMA_CONTEXT, "@graph": nodes }

    if (Array.isArray(graph["@graph"])) {
        return { ...graph, "@graph": [...graph["@graph"], ...nodes] }
    }

    const { "@context": context, ...node } = graph

    return { "@context": context ?? SCHEMA_CONTEXT, "@graph": [node, ...nodes] }
}

const buildBlockStructuredData = (type: string, context: BlockStructuredDataContext): StructuredDataValue | null => {
    const { fieldValue, listId, webpageData, currencyCode, websiteName } = context

    switch (type) {
        case "sub-departments-1":
        case "sub-departments-2":
        case "sub-departments-4":
            return buildDepartmentStructuredData({
                subDepartments: fieldValue.sub_departments,
                collections: fieldValue.collections,
                webpageData,
                listId,
            })
        case "sub-departments-3":
            return withListNodes(
                buildDepartmentStructuredData({
                    subDepartments: fieldValue.sub_department_list,
                    collections: fieldValue.collections_list,
                    webpageData,
                    listId,
                }),
                [buildPictureItemListNode(getListItems(fieldValue.families), webpageData, `family-list-${listId}`)]
            )
        case "top-families":
            return withListNodes(null, [
                buildPictureItemListNode(fieldValue.families, webpageData, `top-families-${listId}`),
            ])
        case "see-also-1":
            return withListNodes(null, [
                buildPictureItemListNode(getSeeAlsoProducts(fieldValue.settings?.products_data), webpageData, `see-also-${listId}`),
            ])
        case "products-1":
        case "products-2":
            if (webpageData?.sub_type === "family") return null

            return withListNodes(null, [
                buildPictureItemListNode(
                    [...getListItems(fieldValue.products), ...getListItems(fieldValue.products_out_of_stock)],
                    webpageData,
                    `product-list-${listId}`
                ),
            ])
        case "families-1":
        case "families-2":
        case "families-3":
            return buildSubDepartmentStructuredData({
                families: fieldValue.families,
                collections: fieldValue.collections,
                webpageData,
                listId,
            })
        case "families-4":
            return webpageData?.sub_type === "sub_department"
                ? buildSubDepartmentStructuredData({
                      families: getListItems(fieldValue.families),
                      collections: fieldValue.collections_list,
                      webpageData,
                      listId,
                  })
                : buildDepartmentStructuredData({
                      subDepartments: fieldValue.sub_department_list,
                      collections: fieldValue.collections_list,
                      webpageData,
                      listId,
                  })
        case "disclosure": {
            if (fieldValue.seo?.is_faq !== true) return null

            const faqPageNode = buildFaqPageNode({ faqs: fieldValue.value, webpageData, listId })

            return faqPageNode ? { "@context": SCHEMA_CONTEXT, ...faqPageNode } : null
        }
    }

    if (PRODUCT_BLOCK_TYPES.includes(type)) {
        return buildProductStructuredData({
            product: fieldValue.product,
            variant: fieldValue.variant,
            webpageData,
            currencyCode,
            websiteName,
        })
    }

    return null
}

const rebaseUrls = (value: unknown, fromOrigin: string, toOrigin: string): unknown => {
    if (typeof value === "string") {
        return value.startsWith(fromOrigin) ? toOrigin + value.slice(fromOrigin.length) : value
    }

    if (Array.isArray(value)) {
        return value.map((item) => rebaseUrls(item, fromOrigin, toOrigin))
    }

    if (value && typeof value === "object") {
        return Object.fromEntries(
            Object.entries(value).map(([key, item]) => [key, rebaseUrls(item, fromOrigin, toOrigin)])
        )
    }

    return value
}

const getOrigin = (url: string | undefined): string | null => {
    try {
        return new URL(url ?? "").origin
    } catch {
        return null
    }
}

export const buildWebpageStructuredData = (source: WebpageStructuredDataSource): StructuredDataValue[] => {
    const webpageData: Record<string, any> = JSON.parse(JSON.stringify(source.webpage_data ?? {}))
    const structuredDataList: StructuredDataValue[] = []

    const breadcrumbNode = buildBreadcrumbListNode(source.breadcrumbs)
    if (breadcrumbNode) {
        structuredDataList.push({ "@context": SCHEMA_CONTEXT, ...breadcrumbNode })
    }

    const pageStructuredData = buildStructuredData({
        webpageData,
        webBlocks: source.web_blocks,
        currencyCode: source.currency_code,
        websiteName: source.website_name,
    })
    if (pageStructuredData) {
        structuredDataList.push(pageStructuredData)
    }

    let hasCategoryItemList = false

    for (const [index, block] of normalizeWebBlocks(source.web_blocks).entries()) {
        const isCategoryItemList = CATEGORY_ITEM_LIST_BLOCK_TYPES.includes(block?.type)
        if (isCategoryItemList && hasCategoryItemList) continue

        const fieldValue: Record<string, any> = block?.web_block?.layout?.data?.fieldValue || block?.structure || {}
        const blockStructuredData = buildBlockStructuredData(block?.type, {
            fieldValue,
            listId: fieldValue.id ?? index,
            webpageData,
            currencyCode: source.currency_code,
            websiteName: source.website_name,
        })

        if (blockStructuredData) {
            structuredDataList.push(blockStructuredData)
            hasCategoryItemList ||= isCategoryItemList
        }
    }

    const licensedStructuredDataList = withImageLicense(structuredDataList, {
        websiteName: source.website_name,
        termsUrl: source.terms_url,
    })

    const websiteOrigin = getOrigin(source.website_url)
    if (!websiteOrigin || typeof window === "undefined" || window.location.origin === websiteOrigin) {
        return licensedStructuredDataList
    }

    return rebaseUrls(licensedStructuredDataList, window.location.origin, websiteOrigin) as StructuredDataNode[]
}
