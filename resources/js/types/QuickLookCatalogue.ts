/*
 * Author Louis Perez
 * Created on 29-09-2026-13h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

export type QuickLookTab = "overview" | "sales_analysis" | "history"

export interface QuickLookRoutes {
    overview: string
    sales_analysis: string
    history: string
}

export interface QuickLookOverview {
    code: string
    name: string | null
    [key: string]: any
}

export interface QuickLookHistoryRecord {
    icon: string
    tooltip: string
    url: string | null
}

export type QuickLookHistoryRecordResolver = (entry: QuickLookHistoryEntry) => QuickLookHistoryRecord | null

export interface QuickLookHistoryEntry {
    id: number
    datetime: string
    event: string
    user_name: string
    record?: string | null
    auditable_type: string
    auditable_id: number
    old_values: Record<string, unknown> | unknown[] | null
    new_values: Record<string, unknown> | unknown[] | null
}
