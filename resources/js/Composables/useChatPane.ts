import { ref, watch } from "vue"

const PANE_WIDTH = "min(28rem, 40vw)"

export const chatPaneUrl = ref<string | null>(null)

export const openChatPane = (url: string | null | undefined) => {
    if (url) {
        chatPaneUrl.value = url
    }
}

export const closeChatPane = () => {
    chatPaneUrl.value = null
}

watch(chatPaneUrl, (url) => document.documentElement.style.setProperty("--chat-pane", url ? PANE_WIDTH : "0px"))
