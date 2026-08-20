import { ref } from "vue"
import { router } from "@inertiajs/vue3"

export interface MiniChat {
    ulid: string
    contactName: string
    avatar: any
    status: string
    organisationSlug: string | null
    shopName: string | null
    isMinimised: boolean
}

export const MAX_MINI_CHATS = 3

const miniChats = ref<MiniChat[]>([])

export const useMiniChats = () => {
    const findMiniChat = (ulid: string) => miniChats.value.find((chat) => chat.ulid === ulid)

    const openMiniChat = (chat: Omit<MiniChat, "isMinimised">) => {
        const alreadyOpen = findMiniChat(chat.ulid)

        if (alreadyOpen) {
            alreadyOpen.isMinimised = false
            return
        }

        if (miniChats.value.length >= MAX_MINI_CHATS) {
            miniChats.value.shift()
        }

        miniChats.value.push({ ...chat, isMinimised: false })
    }

    const closeMiniChat = (ulid: string) => {
        miniChats.value = miniChats.value.filter((chat) => chat.ulid !== ulid)
    }

    const toggleMiniChat = (ulid: string) => {
        const chat = findMiniChat(ulid)
        if (chat) {
            chat.isMinimised = !chat.isMinimised
        }
    }

    const closeMiniChatsOpenedInPage = (url: string) => {
        const segments = url.split(/[?#]/)[0].split("/")
        miniChats.value = miniChats.value.filter((chat) => !segments.includes(chat.ulid))
    }

    const trackNavigation = () =>
        router.on("navigate", (event) => closeMiniChatsOpenedInPage(event.detail.page.url))

    return { miniChats, openMiniChat, closeMiniChat, toggleMiniChat, trackNavigation }
}
