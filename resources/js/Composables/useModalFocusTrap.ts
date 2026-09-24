/*
 * Author Louis Perez
 * Created on 15-09-2026-10h-58m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

import { nextTick, onBeforeUnmount, watch, type Ref } from "vue"

const openTraps: HTMLElement[] = []
let scrollLockCount = 0
let previousHtmlOverflow = ""
let previousBodyOverflow = ""

const floatingLayerSelector = ".p-popover, .p-dialog, .p-overlay-mask, .p-connected-overlay, [role='dialog'], [role='listbox'], [role='menu']"
const focusableSelector = "a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, video[controls], [tabindex]:not([tabindex='-1'])"

const lockPageScroll = () => {
    if (scrollLockCount === 0) {
        previousHtmlOverflow = document.documentElement.style.overflow
        previousBodyOverflow = document.body.style.overflow
        document.documentElement.style.overflow = "hidden"
        document.body.style.overflow = "hidden"
    }
    scrollLockCount++
}

const unlockPageScroll = () => {
    scrollLockCount = Math.max(0, scrollLockCount - 1)
    if (scrollLockCount === 0) {
        document.documentElement.style.overflow = previousHtmlOverflow
        document.body.style.overflow = previousBodyOverflow
    }
}

const isInsideFloatingLayer = (element: Element | null) => Boolean(element?.closest?.(floatingLayerSelector))

export const useModalFocusTrap = (isOpen: Ref<boolean>, container: Ref<HTMLElement | null>) => {
    let trapElement: HTMLElement | null = null
    let previouslyFocused: HTMLElement | null = null
    let isActive = false

    const isTopTrap = () => trapElement !== null && openTraps[openTraps.length - 1] === trapElement

    const onFocusIn = (event: FocusEvent) => {
        const target = event.target as HTMLElement | null
        if (!isTopTrap() || !trapElement || !target) return
        if (trapElement.contains(target) || isInsideFloatingLayer(target)) return
        trapElement.focus({ preventScroll: true })
    }

    const onKeydown = (event: KeyboardEvent) => {
        if (event.key !== "Tab" || !isTopTrap() || !trapElement) return

        const focused = document.activeElement as HTMLElement | null
        if (focused && !trapElement.contains(focused) && isInsideFloatingLayer(focused)) return

        const focusables = Array.from(trapElement.querySelectorAll<HTMLElement>(focusableSelector)).filter((element) => element.getClientRects().length > 0)
        if (!focusables.length) {
            event.preventDefault()
            trapElement.focus({ preventScroll: true })
            return
        }

        const first = focusables[0]
        const last = focusables[focusables.length - 1]
        const isOutside = !focused || !trapElement.contains(focused) || focused === trapElement

        if (event.shiftKey && (isOutside || focused === first)) {
            event.preventDefault()
            last.focus()
        } else if (!event.shiftKey && (isOutside || focused === last)) {
            event.preventDefault()
            first.focus()
        }
    }

    const activate = async () => {
        if (isActive) return
        isActive = true
        previouslyFocused = document.activeElement as HTMLElement | null
        lockPageScroll()
        document.addEventListener("focusin", onFocusIn, true)
        document.addEventListener("keydown", onKeydown, true)

        await nextTick()
        if (!isActive || !container.value) return
        trapElement = container.value
        openTraps.push(trapElement)
        trapElement.focus({ preventScroll: true })
    }

    const deactivate = () => {
        if (!isActive) return
        isActive = false
        unlockPageScroll()
        document.removeEventListener("focusin", onFocusIn, true)
        document.removeEventListener("keydown", onKeydown, true)

        if (trapElement) {
            const index = openTraps.lastIndexOf(trapElement)
            if (index !== -1) openTraps.splice(index, 1)
            trapElement = null
        }

        previouslyFocused?.focus?.({ preventScroll: true })
        previouslyFocused = null
    }

    watch(isOpen, (shouldTrap) => (shouldTrap ? activate() : deactivate()), { immediate: true })

    onBeforeUnmount(deactivate)
}
