/*
 * Author Louis Perez
 * Created on 24-11-2026-17h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
 */

import { computed, ref, watch, type Ref } from "vue"
import { useResizeObserver } from "@vueuse/core"

export const shortenedNames = (fullName: string): string[] => {
    const words = fullName.trim().split(/\s+/).filter(Boolean)
    const names = [words.join(" ")]

    for (let shortened = 1; shortened < words.length; shortened++) {
        const kept = words.slice(0, words.length - shortened)
        const initials = words.slice(words.length - shortened).map((word) => `${word.charAt(0).toUpperCase()}.`)
        names.push([...kept, ...initials].join(" "))
    }

    return names
}

let measuringContext: CanvasRenderingContext2D | null = null

const textWidth = (text: string, font: string) => {
    measuringContext ??= document.createElement("canvas").getContext("2d")
    if (!measuringContext) {
        return 0
    }
    measuringContext.font = font

    return measuringContext.measureText(text).width
}

export const useFittedName = (element: Ref<HTMLElement | null>, fullName: Ref<string>) => {
    const availableWidth = ref(0)
    const font = ref("")

    const measure = () => {
        if (!element.value) {
            return
        }
        availableWidth.value = element.value.clientWidth
        font.value = getComputedStyle(element.value).font
    }

    useResizeObserver(element, measure)
    watch(element, measure)
    document.fonts?.ready.then(measure)

    return computed(() => {
        const names = shortenedNames(fullName.value)
        if (!availableWidth.value || !font.value) {
            return names[0] ?? ""
        }

        return names.find((name) => textWidth(name, font.value) <= availableWidth.value) ?? names[names.length - 1] ?? ""
    })
}
