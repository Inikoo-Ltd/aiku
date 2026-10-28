import { ref, watch } from "vue"
import { useLocalStorage, useSessionStorage } from "@vueuse/core"

const MIN_WIDTH = 320
const MAX_SHARE = 0.7

export const chatPaneUrl = ref<string | null>(null)
const chatPaneWidth = useLocalStorage("chat-pane-width", 448)

// Where the pane was when it closed, so opening it again from the rail carries on with the same
// conversation instead of jumping to whoever waited longest.
export const lastChatPaneUrl = useSessionStorage<string | null>("chat-pane-last-url", null)

export const openChatPane = (url: string | null | undefined) => {
    if (url) {
        chatPaneUrl.value = url
    }
}

export const closeChatPane = () => {
    chatPaneUrl.value = null
}

export const resizeChatPane = (width: number) => {
    chatPaneWidth.value = Math.round(Math.min(window.innerWidth * MAX_SHARE, Math.max(MIN_WIDTH, width)))
}

watch([chatPaneUrl, chatPaneWidth], ([url, width]) => {
    document.documentElement.style.setProperty("--chat-pane", url ? `min(${width}px, ${MAX_SHARE * 100}vw)` : "0px")
})
