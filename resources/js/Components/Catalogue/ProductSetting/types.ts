import type { ChecklistItem } from "@/Components/DataDisplay/ChecklistItems.vue"
import type { WorkflowStep } from "@/Components/DataDisplay/WorkflowSteps.vue"
import type { routeType } from "@/types/route"
import type { WebpageSeoPreviewData } from "@/Components/DataDisplay/WebpageSeoPreviewCard.vue"
import type { WebpageStructuredDataSource } from "@/Iris/Composables/useWebpageStructuredData"

export type MasterProductData = {
    has_master: boolean
    code: string
    name: string
    description: string | null
    description_extra: string | null
    meta_description: string | null
    price: string | number | null
    currency_code: string
    marketing_weight: number | null
    gross_weight: number | null
    dimensions: string | null
    ingredients: string | null
    barcode: string | null
    origin_country: string | null
}

export type SeoSuggestionField = {
    field: string
    group: "search" | "content" | "structured_data"
    label: string
    current: string | null
    suggestion: {
        id: number
        value: string | null
        items: { json_ld: string, why: string | null, is_implemented: boolean }[] | null
        is_implemented: boolean
        can_implement: boolean
        implement_route: routeType
    } | null
}

export type SeoReview = {
    keywords: string
    pending: number
    implemented: number
    is_validated: boolean
    can_validate: boolean
    store_route: routeType
    validate_route: routeType
    fields: SeoSuggestionField[]
}

export type WebpageIssueGroup = {
    key: string
    label: string
    intro: string
    issues: Array<{
        id: number
        title: string
        description: string | null
        severity: "error" | "warning" | "notice"
        found: string | null
        places?: string[]
    }>
}

export type WebpageStateField = {
    can_change: boolean
    redirect_webpage_id: number | null
    default_storefront: Record<string, unknown> | null
    init_options: Record<string, unknown>[] | null
    fetch_params: Record<string, string | null>
}

export type ShopProductSetting = {
    id: number
    shop: { code: string, name: string, language_code: string }
    can_edit_seo: boolean
    product: {
        id: number
        code: string
        name: string | null
        description: string | null
        description_extra: string | null
        price: string | number | null
        currency_code: string
        follows_master: { name: boolean, description: boolean, description_extra: boolean, price: boolean }
        route: routeType
        update_route: routeType
    }
    webpage: {
        id: number
        state: string
        title: string | null
        description: string | null
        url: string | null
        page_url: string | null
        breadcrumb_label: string | null
        index_page: boolean
        follow_link: boolean
        structured_data: string | object | null
        structured_data_source: WebpageStructuredDataSource | null
        seo_image_field: Record<string, any>
        preview: WebpageSeoPreviewData | null
        state_field: WebpageStateField
        update_route: routeType
        publish_route: routeType
        workshop_route: routeType | null
        crawl_route: (routeType & { method?: string }) | null
    } | null
    checklist: { complete: number, total: number, items: ChecklistItem[], missing: string[] }
    workflow: { current: number, label: string, steps: WorkflowStep[] }
    review: SeoReview | null
    audit: { last_crawl_at: string | null, issue_count: number, issue_groups: WebpageIssueGroup[] } | null
    shared: {
        shipping: SharedInformationValue | null
        return_policy: SharedInformationValue | null
        website_edit_route: routeType | null
    }
}

export const htmlFields = ["top_desc", "extra_desc"]

export type SharedInformationValue = { value: string, source: "master" | "website" }

export type ProductSettingTab = "setting" | "seo_review" | "validation" | "issues"

export const settingFieldId = (field: string) => `setting-field-${field}`

export const productSettingActionsId = "product-setting-actions"
