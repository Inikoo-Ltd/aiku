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

    const dragThresholdInPixels = 5
    let isPointerDown = false
    let hasDragged = false
    let dragStartX = 0
    let dragStartScrollLeft = 0

    const onPointerDown = (event: PointerEvent) => {
        const element = scroller.value
        if (!element || event.pointerType !== "mouse" || event.button !== 0) return
        if (element.scrollWidth <= element.clientWidth) return

        isPointerDown = true
        hasDragged = false
        dragStartX = event.clientX
        dragStartScrollLeft = element.scrollLeft
    }

    const onPointerMove = (event: PointerEvent) => {
        const element = scroller.value
        if (!isPointerDown || !element) return

        const distance = event.clientX - dragStartX
        if (!hasDragged && Math.abs(distance) < dragThresholdInPixels) return

        hasDragged = true
        element.style.cursor = "grabbing"
        element.style.userSelect = "none"
        element.scrollLeft = dragStartScrollLeft - distance
    }

    const onPointerUp = () => {
        if (!isPointerDown) return

        isPointerDown = false
        if (scroller.value) {
            scroller.value.style.cursor = ""
            scroller.value.style.userSelect = ""
        }
    }

    const onClickCapture = (event: MouseEvent) => {
        if (!hasDragged) return

        hasDragged = false
        event.preventDefault()
        event.stopPropagation()
    }

    const onDragStart = (event: DragEvent) => {
        event.preventDefault()
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
        scroller.value?.addEventListener("pointerdown", onPointerDown)
        scroller.value?.addEventListener("click", onClickCapture, true)
        scroller.value?.addEventListener("dragstart", onDragStart)
        window.addEventListener("pointermove", onPointerMove)
        window.addEventListener("pointerup", onPointerUp)
        window.addEventListener("pointercancel", onPointerUp)
        if (typeof ResizeObserver === "undefined" || !scroller.value) return
        resizeObserver = new ResizeObserver(update)
        observeChildren()
        mutationObserver = new MutationObserver(observeChildren)
        mutationObserver.observe(scroller.value, { childList: true })
    })

    onBeforeUnmount(() => {
        scroller.value?.removeEventListener("scroll", update)
        scroller.value?.removeEventListener("pointerdown", onPointerDown)
        scroller.value?.removeEventListener("click", onClickCapture, true)
        scroller.value?.removeEventListener("dragstart", onDragStart)
        window.removeEventListener("pointermove", onPointerMove)
        window.removeEventListener("pointerup", onPointerUp)
        window.removeEventListener("pointercancel", onPointerUp)
        resizeObserver?.disconnect()
        mutationObserver?.disconnect()
    })

    return { canScrollLeft, canScrollRight, scrollBy }
}
