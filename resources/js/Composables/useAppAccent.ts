/*
 * Author Louis Perez
 * Created on 18-09-2026-16h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

import { watchEffect } from "vue"

const DEFAULT_ACCENT = "#4f46e5"

export const readableTextOn = (color: string): string => {
    const hex = color.replace("#", "").trim()
    const full = hex.length === 3 ? hex.split("").map((part) => part + part).join("") : hex.slice(0, 6)
    const [red, green, blue] = [0, 2, 4].map((start) => {
        const channel = parseInt(full.slice(start, start + 2), 16) / 255
        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
    })
    const luminance = 0.2126 * red + 0.7152 * green + 0.0722 * blue
    return Number.isNaN(luminance) || luminance < 0.4 ? "#ffffff" : "#111827"
}

export const useAppAccentVariables = (theme: () => string[] | undefined) => {
    watchEffect(() => {
        if (typeof document === "undefined") return
        const accent = theme()?.[4] || DEFAULT_ACCENT
        const root = document.documentElement.style
        root.setProperty("--app-accent", accent)
        root.setProperty("--app-accent-text", readableTextOn(accent))
        root.setProperty("--app-accent-strong", `color-mix(in srgb, ${accent} 80%, black)`)
        root.setProperty("--app-accent-deep", `color-mix(in srgb, ${accent} 60%, black)`)
        root.setProperty("--app-accent-soft", `color-mix(in srgb, ${accent} 10%, white)`)
        root.setProperty("--app-accent-muted", `color-mix(in srgb, ${accent} 25%, white)`)
    })
}
