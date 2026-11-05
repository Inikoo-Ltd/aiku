/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

import { routeType } from "@/types/route"

export type SelectOption = { value: string, label: string }

export type SeoKeywordOptions = {
    countries: SelectOption[]
    languages: SelectOption[]
    frequencies: SelectOption[]
    scopes: SelectOption[]
}

export type SeoKeywordRoutes = {
    track: routeType & { method: string }
    bulkTrack: routeType & { method: string }
    bulkDelete: routeType & { method: string }
    storeTag: routeType & { method: string }
    assignTags: routeType & { method: string }
}

export type SeoCheckNow = {
    keywords: number
    cost: number
    route: routeType
}

export type SeoKeywordTag = {
    id: number
    name: string
    keywords: number
    update_route: routeType & { method: string }
    delete_route: routeType & { method: string }
}

export type SeoKeywordFilterOptions = {
    tags: SeoKeywordTag[]
    serp_features: { value: string, keywords: number, ours: number }[]
}

export type SeoKeywordRule = {
    type: "include" | "exclude"
    match: "contains" | "exact" | "begins" | "ends"
    value: string
}

export type SeoKeywordFilterValues = {
    position?: string
    position_min?: number | string | null
    position_max?: number | string | null
    change?: string[]
    serp_feature?: string
    serp_mode?: string
    tags?: string[]
    tags_mode?: "include" | "exclude"
    intents?: string[]
    volume_min?: number | string | null
    volume_max?: number | string | null
    volume_unknown?: boolean | string
    kd_min?: number | string | null
    kd_max?: number | string | null
    kd_unknown?: boolean | string
    rules?: SeoKeywordRule[]
    logic?: "and" | "or"
}

export type SeoResearchQuery = {
    seed: string
    url: string
    scope: string
    country_code: string
    language_code: string
}

export type ContentSuggestion = {
    id: number
    field: "title" | "description"
    current_value: string | null
    suggestion: string
    reason: string
    created_at: string | null
    accept_route: routeType
    dismiss_route: routeType
}

export type ContentSuggestions = {
    items: ContentSuggestion[]
    request_route: routeType
    limits: { title: number, description: number }
}
