import type { ComparisonTemplate, ComparisonTemplateName } from "@/Composables/comparisonTemplates"

export type ComparisonFamily = {
    name: string
    url?: string
    image?: any
    is_current?: boolean
    category_comparison?: {
        template?: ComparisonTemplateName
        items?: ComparisonTemplate
    }
}

export type CategoryComparisonValue = {
    id?: string
    title?: string
    container?: any
    families?: ComparisonFamily[]
    settings?: {
        template?: ComparisonTemplateName
        highlight_color?: string
        label_color?: string
        link_color?: string
        value_color?: string
    }
}
