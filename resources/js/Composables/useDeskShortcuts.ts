/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

import { onMounted, onUnmounted } from "vue"
import { ctrans } from "@/Composables/useTrans"

export const isMac = typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform)

interface DeskShortcutHandlers {
    jump: () => void
    showShortcuts: () => void
    markRead: () => void
    markAllRead: () => void
    move: (step: 1 | -1) => void
    isShortcutsOpen: () => boolean
}

const OVERLAYS = [
    ".p-dialog-mask",
    ".p-dialog",
    ".p-popover",
    ".p-overlay-mask",
    ".p-menu-overlay",
    ".p-tieredmenu-overlay",
    ".p-contextmenu",
    ".p-select-overlay",
    ".p-multiselect-overlay",
    ".p-autocomplete-overlay",
    ".p-datepicker-panel:not(.p-datepicker-panel-inline)",
    "[role='menu']",
    "[role='listbox']",
].join(", ")

const hasOverlayOpen = () => !!document.querySelector(OVERLAYS)

const isTyping = (target: EventTarget | null) =>
    target instanceof HTMLElement && (target.isContentEditable || target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement)

const typedValue = (target: EventTarget | null) => {
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) return target.value
    if (target instanceof HTMLElement && target.isContentEditable) return target.textContent ?? ""
    return ""
}

export const deskShortcutList = () => {
    const mod = isMac ? "⌘" : "Ctrl"
    const alt = isMac ? "⌥" : "Alt"
    return [
        { keys: [mod, "K"], label: ctrans("Jump to a channel or person") },
        { keys: ["↑"], label: ctrans("Edit your last message (in an empty message box)") },
        { keys: ["Esc"], label: ctrans("Mark the current channel as read") },
        { keys: ["Shift", "Esc"], label: ctrans("Mark all messages as read") },
        { keys: [alt, "↑"], label: ctrans("Previous channel or direct message") },
        { keys: [alt, "↓"], label: ctrans("Next channel or direct message") },
        { keys: [mod, "/"], label: ctrans("Show keyboard shortcuts") },
    ]
}

export function useDeskShortcuts(handlers: DeskShortcutHandlers) {
    let overlayAtKeydown = false

    const onCapture = (event: KeyboardEvent) => {
        overlayAtKeydown = hasOverlayOpen()
        const mod = isMac ? event.metaKey : event.ctrlKey
        if (mod && !event.shiftKey && !event.altKey && event.key.toLowerCase() === "k") {
            event.preventDefault()
            event.stopPropagation()
            if (!overlayAtKeydown) handlers.jump()
            return
        }
        if (mod && (event.code === "Slash" || event.key === "/")) {
            event.preventDefault()
            event.stopPropagation()
            if (!overlayAtKeydown || handlers.isShortcutsOpen()) handlers.showShortcuts()
        }
    }

    const onKey = (event: KeyboardEvent) => {
        if (event.defaultPrevented || event.isComposing || overlayAtKeydown) return
        const hasDraft = isTyping(event.target) && typedValue(event.target).trim() !== ""

        if (event.altKey && !event.metaKey && !event.ctrlKey && !event.shiftKey && (event.key === "ArrowUp" || event.key === "ArrowDown")) {
            if (hasDraft) return
            event.preventDefault()
            handlers.move(event.key === "ArrowUp" ? -1 : 1)
            return
        }

        if (event.key !== "Escape" || event.metaKey || event.ctrlKey || event.altKey || hasDraft) return
        if (event.shiftKey) {
            handlers.markAllRead()
            return
        }
        handlers.markRead()
    }

    onMounted(() => {
        window.addEventListener("keydown", onCapture, true)
        window.addEventListener("keydown", onKey)
    })
    onUnmounted(() => {
        window.removeEventListener("keydown", onCapture, true)
        window.removeEventListener("keydown", onKey)
    })
}
