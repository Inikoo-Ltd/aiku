import { buildStructuredData, type StructuredDataNode, type StructuredDataValue } from "@/Iris/Composables/useStructuredData"
import { buildBreadcrumbListNode } from "@/Iris/Composables/useBreadcrumbStructuredData"
import { buildDepartmentStructuredData } from "@/Iris/Composables/useDepartmentStructuredData"
import { buildSubDepartmentStructuredData } from "@/Iris/Composables/useSubDepartmentStructuredData"
import { buildFaqPageNode } from "@/Iris/Composables/useFaqStructuredData"
import { buildProductStructuredData } from "@/Iris/Composables/useProductStructuredData"

export type WebpageStructuredDataSource = {
    webpage_data: Record<string, any>
    web_blocks: any[]
    breadcrumbs: any[]
    website_url: string
    website_name?: string | null
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

const getFamiliesList = (families: unknown): any[] => {
    if (Array.isArray(families)) return families

    return (families as Record<string, any>)?.data ?? []
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
            return buildDepartmentStructuredData({
                subDepartments: fieldValue.sub_department_list,
                collections: fieldValue.collections_list,
                webpageData,
                listId,
            })
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
                      families: getFamiliesList(fieldValue.families),
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

const getOrigin = (url: string): string | null => {
    try {
        return new URL(url).origin
    } catch {
        return null
    }
}

export const buildWebpageStructuredData = (originalSource: WebpageStructuredDataSource): StructuredDataValue[] => {
    const source: WebpageStructuredDataSource = JSON.parse(JSON.stringify(originalSource))
    const webpageData = source.webpage_data ?? {}
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

    for (const [index, block] of (source.web_blocks ?? []).entries()) {
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
        }
    }

    const websiteOrigin = getOrigin(source.website_url)
    if (!websiteOrigin || typeof window === "undefined" || window.location.origin === websiteOrigin) {
        return structuredDataList
    }

    return rebaseUrls(structuredDataList, window.location.origin, websiteOrigin) as StructuredDataNode[]
}
