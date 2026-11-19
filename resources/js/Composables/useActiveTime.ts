/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { onBeforeUnmount, watch, type Ref } from "vue"

const MAX_GAP_MS = 15_000

const ACTIVITY_EVENTS = ["pointermove", "pointerdown", "wheel", "focusin", "keydown"] as const

export type ActiveTime = {
    writing_seconds: number
    typing_seconds: number
}

/**
 * Time a person actually spends on an element: the gaps between their interactions with it, each
 * gap capped at MAX_GAP_MS, so a window left open while they do something else costs nothing.
 * Typing is the same, counted between key presses only.
 */
export const useActiveTime = (element: Ref<HTMLElement | null | undefined>) => {
    let activeMs = 0
    let typingMs = 0
    let lastActivity: number | null = null
    let lastKey: number | null = null

    const onActivity = (event: Event) => {
        const now = performance.now()
        if (lastActivity !== null && !document.hidden) {
            activeMs += Math.min(now - lastActivity, MAX_GAP_MS)
        }
        lastActivity = now

        if (event.type === "keydown") {
            if (lastKey !== null) {
                typingMs += Math.min(now - lastKey, MAX_GAP_MS)
            }
            lastKey = now
        }
    }

    const attach = (target: HTMLElement) => ACTIVITY_EVENTS.forEach((type) => target.addEventListener(type, onActivity, { passive: true }))
    const detach = (target: HTMLElement) => ACTIVITY_EVENTS.forEach((type) => target.removeEventListener(type, onActivity))

    const stopWatching = watch(element, (target, previous) => {
        if (previous) {
            detach(previous)
        }
        if (target) {
            attach(target)
        }
    }, { immediate: true })

    onBeforeUnmount(() => {
        stopWatching()
        if (element.value) {
            detach(element.value)
        }
    })

    const read = (): ActiveTime => {
        const now = performance.now()
        const tail = lastActivity !== null && !document.hidden ? Math.min(now - lastActivity, MAX_GAP_MS) : 0

        return {
            writing_seconds: Math.round((activeMs + tail) / 1000),
            typing_seconds: Math.round(typingMs / 1000),
        }
    }

    const reset = () => {
        activeMs = 0
        typingMs = 0
        lastActivity = null
        lastKey = null
    }

    return { read, reset }
}
