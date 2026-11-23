/*
 * Author Louis Perez
 * Created on 23-11-2026-11h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

import { routeType } from "@/types/route"

export interface StaticWebsiteShowcaseData {
    id: number
    url: string
    url_hint: string
    can_edit: boolean
    route_storefront: routeType | null
    route_header: routeType | null
    route_footer: routeType | null
    details: { label: string, value: string | number | null, href?: string | null, type?: "date" }[]
}

export interface StaticWebpageShowcaseData {
    id: number
    path: string
    title: string | null
    domain: string
    preview_url: string
    can_edit: boolean
    seo: Record<string, any>
    details: { label: string, value: string | number | null, href?: string | null, hint?: string, type?: "date", code?: boolean }[]
    shared_parts?: { label: string, route: { name: string, parameters: (string | number)[] } }[]
}
