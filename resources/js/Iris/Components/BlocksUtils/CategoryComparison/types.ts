import type { ComparisonTemplate, ComparisonTemplateName } from "@/Composables/comparisonTemplates"

export type ScreenType = "mobile" | "tablet" | "desktop"

export type ResponsiveNumber = Partial<Record<ScreenType, number>>

export type ComparisonFamily = {
    id?: number
    slug?: string
    code?: string
    name: string
    url?: string
    image?: any
    srcset?: any
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
    number_of_families?: number | ResponsiveNumber
    settings?: {
        template?: ComparisonTemplateName
        highlight_color?: string
        label_color?: string
        link_color?: string
        value_color?: string
    }
}
