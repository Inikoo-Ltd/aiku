/*
 * Author Louis Perez
 * Created on 26-10-2026-21h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

export interface SkuListEntry {
    id: number
    code: string
    name: string
    done: number
    total: number
    image?: (Record<string, string> & { original: string }) | null
    badge?: { label: string, class: string } | null
}

export type SkuProgress = "not_started" | "in_progress" | "complete"

export const skuProgress = (entry: { done: number, total: number }): SkuProgress =>
    entry.done >= entry.total ? "complete" : entry.done === 0 ? "not_started" : "in_progress"

export const skuProgressPills: Record<SkuProgress, { label: string, class: string, dot: string }> = {
    not_started: { label: "Not started", class: "bg-gray-100 text-gray-700", dot: "bg-gray-400" },
    in_progress: { label: "In progress", class: "bg-[--app-accent-soft] text-[--app-accent-deep]", dot: "bg-[--app-accent]" },
    complete: { label: "Complete", class: "bg-green-50 text-green-700", dot: "bg-green-500" },
}
