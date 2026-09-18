/*
 * Author Louis Perez
 * Created on 18-09-2026-16h-15m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

import { onBeforeUnmount, onMounted, ref, type Ref } from "vue"

export const useScrollArrows = (scroller: Ref<HTMLElement | null>) => {
    const canScrollLeft = ref(false)
    const canScrollRight = ref(false)

    const update = () => {
        const element = scroller.value
        if (!element) return
        canScrollLeft.value = element.scrollLeft > 1
        canScrollRight.value = element.scrollLeft + element.clientWidth < element.scrollWidth - 1
    }

    const scrollBy = (direction: 1 | -1) => {
        scroller.value?.scrollBy({ left: direction * scroller.value.clientWidth * 0.7, behavior: "smooth" })
    }

    let resizeObserver: ResizeObserver | null = null
    let mutationObserver: MutationObserver | null = null

    const observeChildren = () => {
        if (!resizeObserver || !scroller.value) return
        resizeObserver.disconnect()
        resizeObserver.observe(scroller.value)
        Array.from(scroller.value.children).forEach((child) => resizeObserver?.observe(child))
        update()
    }

    onMounted(() => {
        update()
        scroller.value?.addEventListener("scroll", update, { passive: true })
        if (typeof ResizeObserver === "undefined" || !scroller.value) return
        resizeObserver = new ResizeObserver(update)
        observeChildren()
        mutationObserver = new MutationObserver(observeChildren)
        mutationObserver.observe(scroller.value, { childList: true })
    })

    onBeforeUnmount(() => {
        scroller.value?.removeEventListener("scroll", update)
        resizeObserver?.disconnect()
        mutationObserver?.disconnect()
    })

    return { canScrollLeft, canScrollRight, scrollBy }
}
