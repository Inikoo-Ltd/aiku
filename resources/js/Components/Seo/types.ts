/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

import { routeType } from "@/types/route"

export type SelectOption = { value: string, label: string }

export type SeoKeywordOptions = {
    countries: SelectOption[]
    languages: SelectOption[]
    devices: SelectOption[]
    frequencies: SelectOption[]
}

export type SeoKeywordRoutes = {
    track: routeType & { method: string }
}

export type SeoResearchQuery = {
    seed: string
    url: string
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
