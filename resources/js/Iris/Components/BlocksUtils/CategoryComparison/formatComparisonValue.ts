import { ctrans } from "@/Composables/useTrans"

type ComparisonSize = {
    h?: number | string
    l?: number | string
    w?: number | string
    type?: string
    units?: string
}

export type ComparisonValue = {
    text: string | null
    values: string[]
    hiddenCount: number
}

export const MAXIMUM_LISTED_VALUES = 3

const METRES_TO = {
    mm: 1000,
    cm: 100,
    yd: 1.09361,
    in: 39.3701,
    ft: 3.28084,
} as Record<string, number>

const formatNumber = (value: number) =>
    new Intl.NumberFormat("en", { maximumFractionDigits: 1 }).format(value)

const parseJson = (value: unknown): unknown => {
    if (typeof value !== "string") {
        return value
    }

    try {
        return JSON.parse(value)
    } catch {
        return null
    }
}

const asList = (value: unknown): unknown[] => {
    const parsed = parseJson(value)

    return Array.isArray(parsed) ? parsed : []
}

const uniqueLabels = (labels: (string | null)[]) =>
    [...new Set(labels.filter((label): label is string => Boolean(label)))]

const buildValue = (labels: (string | null)[], separator: string): ComparisonValue => {
    const values = uniqueLabels(labels)
    const visible = values.slice(0, MAXIMUM_LISTED_VALUES)

    return {
        text: visible.length ? visible.join(separator) : null,
        values,
        hiddenCount: values.length - visible.length,
    }
}

const fromMetres = (value: unknown, units: string) =>
    formatNumber((Number(value) || 0) * (METRES_TO[units] ?? 1))

const formatSize = (size: unknown): string | null => {
    const parsed = parseJson(size) as ComparisonSize | null

    if (!parsed || typeof parsed !== "object") {
        return null
    }

    const units = parsed.units ?? "m"
    const suffix = ` (${units})`
    const length = () => fromMetres(parsed.l, units)
    const width = () => fromMetres(parsed.w, units)
    const height = () => fromMetres(parsed.h, units)

    switch (parsed.type) {
        case "rectangular":
            return `${length()}x${width()}x${height()}${suffix}`
        case "sheet":
            return `${length()}x${width()}${suffix}`
        case "cilinder":
        case "cylinder":
            return `${height()}x${width()}${suffix}`
        case "sphere":
            return `D:${height()}${suffix}`
        case "string":
            return `L.${length()}${suffix}`
        default:
            return null
    }
}

const formatPackaging = (packagings: unknown[]): ComparisonValue =>
    buildValue(
        packagings.map(packaging => {
            const { unit, units } = (packaging ?? {}) as { unit?: string; units?: number | string }
            const name = String(unit ?? "").trim()

            if (!name) {
                return null
            }

            const quantity = Number(units) || 0

            return quantity > 1 ? `${formatNumber(quantity)} x ${name}` : name
        }),
        ", "
    )

const formatWeightAndSize = (value: Record<string, unknown>): ComparisonValue => {
    const averageWeight = String(value.average_weight ?? "").trim()
    const weight = averageWeight ? `${averageWeight} g` : null

    return buildValue([weight, ...asList(value.dimensions).map(formatSize)], " / ")
}

export const formatComparisonValue = (value: unknown): ComparisonValue => {
    if (typeof value === "boolean") {
        return buildValue([value ? ctrans("Yes") : ctrans("No")], ", ")
    }

    if (typeof value === "number") {
        return buildValue([formatNumber(value)], ", ")
    }

    if (typeof value === "string") {
        return buildValue([value.trim() || null], ", ")
    }

    if (Array.isArray(value)) {
        return formatPackaging(value)
    }

    if (value && typeof value === "object") {
        return formatWeightAndSize(value as Record<string, unknown>)
    }

    return buildValue([], ", ")
}
