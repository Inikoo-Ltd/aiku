/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

/**
 * The compare button of a dashboard's settings bar. The pick lives in the URL (`compare`,
 * `compare_from`, `compare_to`); a dashboard that compares by default sends its `default_mode`,
 * and turning comparing off there is kept as `compare=none`.
 */
export type CompareProp = {
    available: boolean
    mode: string | null
    default_mode?: string | null
    options: { value: string, label: string }[]
    from: string | null
    to: string | null
    custom_from: string | null
    custom_to: string | null
}

export const compareQueryKeys = ["compare", "compare_from", "compare_to"]

/** Copies the compare pick of the current URL onto another query, e.g. when switching tabs. */
export const keepCompareQuery = (target: URLSearchParams, source: URLSearchParams = new URLSearchParams(window.location.search)) => {
    compareQueryKeys.forEach(key => {
        const value = source.get(key)
        if (value) {
            target.set(key, value)
        }
    })

    return target
}
