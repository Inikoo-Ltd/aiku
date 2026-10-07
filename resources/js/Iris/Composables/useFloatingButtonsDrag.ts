import { computed } from 'vue'
import { set } from 'lodash-es'

const savedOffsetKey = 'iris-floating-buttons-offset'

export const useFloatingButtonsDrag = (layout: any) => {
    if (typeof window !== 'undefined' && layout?.floatingButtonsOffset === undefined) {
        try {
            set(layout, 'floatingButtonsOffset', Number(localStorage.getItem(savedOffsetKey)) || 0)
        } catch {
            set(layout, 'floatingButtonsOffset', 0)
        }
    }

    const offset = computed<number>(() => layout?.floatingButtonsOffset ?? 0)
    let startY = 0
    let startOffset = 0
    let isDragging = false
    let wasDragged = false
    let maxOffset = 0

    const headerBottom = () => Math.max(0, ...[...document.querySelectorAll<HTMLElement>('[class*="sticky"]')]
        .filter((element) => !element.closest('main, aside, nav[aria-label]') && getComputedStyle(element).position === 'sticky')
        .map((element) => element.getBoundingClientRect())
        .filter((rect) => rect.height > 0 && rect.height < window.innerHeight / 2 && rect.top < window.innerHeight / 3)
        .map((rect) => rect.bottom))

    const onPointerDown = (event: PointerEvent) => {
        startY = event.clientY
        startOffset = offset.value
        maxOffset = Math.max(0, window.innerHeight - 136 - headerBottom() - 8)
        isDragging = true
        wasDragged = false
        ;(event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId)
    }

    const onPointerMove = (event: PointerEvent) => {
        if (!isDragging) {
            return
        }
        const movedBy = startY - event.clientY
        if (Math.abs(movedBy) > 4) {
            wasDragged = true
        }
        set(layout, 'floatingButtonsOffset', Math.min(Math.max(startOffset + movedBy, 0), maxOffset))
    }

    const onPointerUp = () => {
        isDragging = false
        if (wasDragged) {
            try {
                localStorage.setItem(savedOffsetKey, String(Math.round(offset.value)))
            } catch {
                return
            }
        }
    }

    const wasJustDragged = () => {
        const dragged = wasDragged
        wasDragged = false
        return dragged
    }

    return { offset, onPointerDown, onPointerMove, onPointerUp, wasJustDragged }
}
