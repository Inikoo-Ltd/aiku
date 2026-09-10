import { nextTick, type Ref } from "vue"

/**
 * Scrolls a conversation to the message a reply quotes and flashes it, the way tapping a
 * quote in WhatsApp does. Shared so the inbox panes and the mini chat window behave
 * identically.
 *
 * The message is found by its `data-message-id`, so a pane only has to stamp that on each
 * bubble wrapper. Nothing happens when the message is not loaded — it sits further back
 * than the pages fetched so far, and silently scrolling somewhere else would be worse
 * than not moving at all.
 */
export const useJumpToMessage = (container: Ref<HTMLElement | null | undefined>) => {
    const HIGHLIGHT_MS = 1600

    let clearHighlight: ReturnType<typeof setTimeout> | null = null

    const jumpToMessage = async (id: number | string) => {
        if (id === null || id === undefined) return

        await nextTick()

        const root = container.value
        if (!root) return

        const target = root.querySelector<HTMLElement>(`[data-message-id="${String(id)}"]`)
        if (!target) return

        target.scrollIntoView({ behavior: "smooth", block: "center" })

        if (clearHighlight) clearTimeout(clearHighlight)

        target.classList.add("chat-jump-highlight")

        clearHighlight = setTimeout(() => {
            target.classList.remove("chat-jump-highlight")
            clearHighlight = null
        }, HIGHLIGHT_MS)
    }

    return { jumpToMessage }
}
