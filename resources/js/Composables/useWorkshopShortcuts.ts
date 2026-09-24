import { onUnmounted } from "vue"

export type ShortcutCombo = string[]

export interface CopiedWebBlock {
    id: number
    name: string
    webpageId: number
}

export interface WorkshopShortcut {
    id: string
    group: string
    label: string
    combos: ShortcutCombo[]
    run: (event: KeyboardEvent) => void
    isAvailable?: () => boolean
    allowWhileTyping?: boolean
    allowRepeat?: boolean
    skipWhenTextSelected?: boolean
}

export const isMacPlatform = typeof navigator !== "undefined"
    && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)

const MODIFIER_TOKENS = ["Mod", "Shift", "Alt"]

const INPUT_LIKE_ROLES = ["textbox", "combobox", "listbox", "option", "slider", "spinbutton", "searchbox"]

const KEY_LABELS: Record<string, { mac: string, other: string }> = {
    Mod: { mac: "⌘", other: "Ctrl" },
    Shift: { mac: "⇧", other: "Shift" },
    Alt: { mac: "⌥", other: "Alt" },
    ArrowUp: { mac: "↑", other: "↑" },
    ArrowDown: { mac: "↓", other: "↓" },
    Delete: { mac: "Del", other: "Del" },
    Backspace: { mac: "⌫", other: "Backspace" },
    Escape: { mac: "Esc", other: "Esc" },
}

export const formatShortcutKey = (token: string): string => {
    const label = KEY_LABELS[token]
    if (label) {
        return isMacPlatform ? label.mac : label.other
    }

    return token.length === 1 ? token.toUpperCase() : token
}

export const formatShortcutCombo = (combo: ShortcutCombo): string =>
    combo.map(formatShortcutKey).join(isMacPlatform ? "" : "+")

const isShiftSensitiveKey = (key: string) => key.length > 1 || /^[a-z0-9]$/i.test(key)

const matchesCombo = (event: KeyboardEvent, combo: ShortcutCombo): boolean => {
    const key = combo[combo.length - 1]
    const modifiers = combo.filter(token => MODIFIER_TOKENS.includes(token))

    if ((event.ctrlKey || event.metaKey) !== modifiers.includes("Mod")) {
        return false
    }
    if (event.altKey !== modifiers.includes("Alt")) {
        return false
    }
    if (isShiftSensitiveKey(key) && event.shiftKey !== modifiers.includes("Shift")) {
        return false
    }

    return event.key.toLowerCase() === key.toLowerCase()
}

const isTypingTarget = (target: EventTarget | null): boolean => {
    const element = target as HTMLElement | null
    if (!element?.tagName) {
        return false
    }

    return ["INPUT", "TEXTAREA", "SELECT"].includes(element.tagName)
        || element.isContentEditable
        || INPUT_LIKE_ROLES.includes(element.getAttribute?.("role") ?? "")
}

const hasTextSelection = (event: KeyboardEvent): boolean => {
    const sourceWindow = (event.view as Window | null) ?? window

    return !!sourceWindow.getSelection?.()?.toString()
}

export const useWorkshopShortcuts = (shortcuts: WorkshopShortcut[], isBlocked: () => boolean) => {
    const attachedWindows = new Set<Window>()

    const onKeydown = (event: KeyboardEvent) => {
        if (event.defaultPrevented || event.isComposing || isBlocked()) {
            return
        }

        const isTyping = isTypingTarget(event.target)

        const shortcut = shortcuts.find(candidate =>
            candidate.combos.some(combo => matchesCombo(event, combo))
            && (!isTyping || candidate.allowWhileTyping)
            && (!event.repeat || candidate.allowRepeat)
            && (!candidate.skipWhenTextSelected || !hasTextSelection(event))
            && (candidate.isAvailable?.() ?? true)
        )

        if (!shortcut) {
            return
        }

        event.preventDefault()
        event.stopPropagation()
        shortcut.run(event)
    }

    const listenTo = (targetWindow: Window | null | undefined) => {
        if (!targetWindow || attachedWindows.has(targetWindow)) {
            return
        }

        try {
            targetWindow.addEventListener("keydown", onKeydown)
            attachedWindows.add(targetWindow)
        } catch (error) {
            console.warn("Unable to listen for workshop shortcuts in this window", error)
        }
    }

    const stopListening = () => {
        attachedWindows.forEach(targetWindow => {
            try {
                targetWindow.removeEventListener("keydown", onKeydown)
            } catch {
                return
            }
        })
        attachedWindows.clear()
    }

    onUnmounted(stopListening)

    return { listenTo, stopListening }
}
