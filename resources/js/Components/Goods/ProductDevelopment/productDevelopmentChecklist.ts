/*
 * Author Louis Perez
 * Created on 16-10-2026-23h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

import { routeType } from "@/types/route"

export type ChecklistInputType = "integer" | "decimal" | "dimensions" | "country" | "textarea" | "text" | "pictograms" | "label_marks" | "ingredients" | "photos"

export interface ChecklistDimensions {
    l?: number | null
    w?: number | null
    h?: number | null
    units?: string
    type?: string
}

export interface ChecklistRow {
    id: number
    label: string
    role: { value: string, label: string, icon: string, color: string }
    required: boolean
    state: "missing" | "complete" | "not_applicable"
    input_type: ChecklistInputType
    placeholder?: string | null
    information?: string | null
    unit: string | null
    currency: string | null
    writes_to: string | null
    needs_supplier_product: boolean
    value: string | number | ChecklistDimensions | Record<string, unknown> | { slug: string, name: string }[] | null
    na_reason: string | null
    saved_by: string | null
    saved_at: string | null
    can_edit: boolean
    can_reopen: boolean
    label_field?: string | null
    on_label?: boolean
}

export interface ChecklistSku {
    id: number
    code: string
    name: string
    route: routeType
    supplier_product: { id: number, code: string } | null
    can_link_supplier_product: boolean
    rows: ChecklistRow[]
    label_content_route?: routeType
}

export interface ChecklistGroupOptions {
    pictograms?: { key: string, label: string, icon: string }[]
    label_marks?: {
        markets: { value: string, label: string }[]
        languages: { value: string, label: string }[]
        best_before: { value: string, label: string }[]
        packaging: { value: string, code: string, label: string }[]
        presence: { key: string, label: string }[]
    }
    ingredients?: { fetchRoute: routeType, parseRoute: routeType, valueProp: string, labelProp: string, mode: string }
}

export interface ChecklistEditorData {
    family: ChecklistRow[]
    skus: ChecklistSku[]
    currency: string | null
    options: ChecklistGroupOptions
    countries: { value: number, label: string }[]
    routes: {
        update: string
        on_label?: string
        supplier_product: string
        supplier_products_search: routeType | null
    }
}

function formatMoney(amount: number, currency: string): string {
    try {
        return new Intl.NumberFormat("en", { style: "currency", currency, currencyDisplay: "narrowSymbol", minimumFractionDigits: 2, maximumFractionDigits: 4 }).format(amount)
    } catch {
        return `${amount} ${currency}`
    }
}

/**
 * The saved value as a reader sees it: dimensions as W × L × H, the country by name, money with its
 * symbol and numbers without trailing zeros, followed by their unit.
 */
export function formatChecklistValue(row: ChecklistRow, countries: { value: number, label: string }[]): string | null {
    const value = row.value
    if (value === null || value === "" || value === undefined) {
        return null
    }
    if (row.input_type === "dimensions" && typeof value === "object" && !Array.isArray(value)) {
        const dimensions = value as ChecklistDimensions
        const sizes = [dimensions.w, dimensions.l, dimensions.h].map((size) => Number(size ?? 0))
        return sizes.some((size) => size > 0) ? `${sizes.join(" × ")} ${dimensions.units ?? "cm"}` : null
    }
    if (row.input_type === "ingredients" && Array.isArray(value)) {
        return value.length ? (value as { name: string }[]).map((ingredient) => ingredient.name).join(", ") : null
    }
    if (row.input_type === "pictograms" || row.input_type === "label_marks" || row.input_type === "photos") {
        return null
    }
    if (row.input_type === "country") {
        return countries.find((country) => country.value === Number(value))?.label ?? String(value)
    }
    if (row.currency) {
        return formatMoney(Number(value), row.currency)
    }
    const text = row.input_type === "integer" || row.input_type === "decimal" ? String(Number(value)) : String(value)

    return row.unit ? `${text} ${row.unit}` : text
}
