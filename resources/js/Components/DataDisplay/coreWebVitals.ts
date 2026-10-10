/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

import { ctrans } from "@/Composables/useTrans"

export type CoreWebVital = "lcp" | "inp" | "cls"

export type CoreWebVitalRating = "good" | "needs_improvement" | "poor"

export const coreWebVitalLimits: Record<CoreWebVital, [number, number]> = {
    lcp: [2500, 4000],
    inp: [200, 500],
    cls: [0.1, 0.25],
}

export const coreWebVitalRating = (metric: CoreWebVital, value: number | null | undefined): CoreWebVitalRating | null => {
    if (value === null || value === undefined) {
        return null
    }

    const [good, poor] = coreWebVitalLimits[metric]

    return value <= good ? "good" : value <= poor ? "needs_improvement" : "poor"
}

export const formatCoreWebVital = (metric: CoreWebVital, value: number | null | undefined) => {
    if (value === null || value === undefined) {
        return "-"
    }

    if (metric === "cls") {
        return value.toFixed(2)
    }

    return value >= 1000 ? `${(value / 1000).toFixed(1)} s` : `${Math.round(value)} ms`
}

export const ratingStyles: Record<CoreWebVitalRating, { dot: string, text: string, label: string }> = {
    good: { dot: "bg-green-600", text: "text-green-800", label: ctrans("Good") },
    needs_improvement: { dot: "bg-amber-500", text: "text-amber-800", label: ctrans("Needs improvement") },
    poor: { dot: "bg-red-600", text: "text-red-800", label: ctrans("Poor") },
}
