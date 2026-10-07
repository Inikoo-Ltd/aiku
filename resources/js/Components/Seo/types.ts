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
    add_competitor: routeType & { method: string }
}

export type SeoKeywordDefaults = {
    country_code: string | null
    language_code: string | null
}
