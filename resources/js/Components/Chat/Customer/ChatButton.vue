<script setup lang="ts">
import { ref, inject, onMounted, onBeforeUnmount, watch } from "vue"
import MessageArea from "@/Components/Chat/Customer/MessageArea.vue"
import MessageHistory from "@/Components/Chat/MessageHistory.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMessage } from "@fortawesome/free-solid-svg-icons"
import { playNotificationSoundFile, buildStorageUrl } from "@/Composables/useNotificationSound"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import HistoryChatList from "@/Components/Chat/HistoryChatList.vue"
import OfflineChatForm from "../OfflineChatForm.vue"
import { router } from "@inertiajs/vue3"
import { faSpinner } from "@fal"

interface ChatMessage {
    id: number
    chat_session_id?: number
    message_type: string
    sender_type: "guest" | "agent" | "system" | "user"
    sender_id?: number
    message_text: string
    is_read?: boolean
    created_at: string
}

interface ChatSessionData {
    ulid: string
    guest_identifier?: string
    session_id?: number
    contact_name?: string
}

type LocalMessageStatus = "sending" | "sent" | "failed"

type LocalChatMessage = ChatMessage & {
    _tempId?: string
    _status?: LocalMessageStatus
}

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const open = ref(false)
const buttonRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)

const loading = ref(false)
const isSending = ref(false)
const isInitialLoad = ref(true)
const isLoadingMore = ref(false)

const activeMenu = ref<"chat" | "history">("chat")
const isLoggedIn = ref(false)

const messagesLocal = ref<LocalChatMessage[]>([])
const messages = messagesLocal

const chatSession = ref<ChatSessionData | null>(null)

const isRating = ref(false)
const rating = ref(0)

/* history */
const userSessions = ref<any[]>([])
const selectedHistory = ref<any | null>(null)
const isLoadingHistory = ref(false)

/* websocket */
let chatChannel: any = null
let currentChannelName: string | null = null
let websocketInitialized = false

// count notif
const unreadCount = ref(0)
const unreadMessageIds = new Set<number>()

const soundUrl = buildStorageUrl("sound/notification.mp3", baseUrl)

const syncLoginState = () => {
    const iris = JSON.parse(localStorage.getItem("iris") || "{}")
    isLoggedIn.value = iris?.is_logged_in === true
}

const saveChatSession = (sessionData: ChatSessionData) => {
    localStorage.setItem(
        "chat",
        JSON.stringify({ ...sessionData, saved_at: new Date().toISOString() })
    )
}

const loadChatSession = () => {
    try {
        const raw = localStorage.getItem("chat")
        if (!raw) return null
        const parsed = JSON.parse(raw)
        return parsed?.ulid ? parsed : null
    } catch {
        return null
    }
}

const createSession = async (): Promise<ChatSessionData | null> => {
    syncLoginState()

    const existing = loadChatSession()
    if (existing) {
        chatSession.value = existing
        return existing
    }

    loading.value = true
    try {
        const payload: any = {
            language_id: 68,
            priority: "normal",
            shop_id: layout?.iris?.shop?.id,
        }

        if (isLoggedIn.value && layout.user?.id) {
            payload.web_user_id = layout.user?.id
        }

        const res = await axios.post(`${baseUrl}/app/api/chats/sessions`, payload)
        if (res.data?.data?.ulid) {
            saveChatSession(res.data.data)
            chatSession.value = res.data.data
            return res.data.data
        }
        return null
    } finally {
        loading.value = false
    }
}

const markAsRead = async () => {
    if (!chatSession.value?.ulid) return
    try {
        const requestFrom = isLoggedIn.value ? "user" : "guest"
        await axios.post(`${baseUrl}/app/api/chats/read`, {
            session_ulid: chatSession.value.ulid,
            request_from: requestFrom,
        })
    } catch (e) {
        console.error("Failed to mark read", e)
    }
}

const assignedAgent = ref<string | null>(null)

const getMessages = async (loadMore = false) => {
    if (!chatSession.value?.ulid) return

    try {
        if (loadMore) isLoadingMore.value = true

        const requestFrom = isLoggedIn.value ? "user" : "guest"
        let url = `${baseUrl}/app/api/chats/sessions/${chatSession.value.ulid}/messages?request_from=${requestFrom}`

        if (loadMore && messagesLocal.value.length) {
            url += `?cursor=${messagesLocal.value[0].created_at}&limit=100`
        }

        const res = await axios.get(url)

        assignedAgent.value = res.data?.data?.assigned_agent ?? null

        const fetched =
            res.data?.data?.messages?.map((m: ChatMessage) => ({
                ...m,
                _status: "sent",
            })) ?? []

        if (!loadMore) {
            messagesLocal.value = fetched
        } else {
            messagesLocal.value.unshift(...fetched)
        }

        const sessionStatus = res.data?.data?.session_status

        if (sessionStatus === "closed") {
            isRating.value = true
            rating.value = res.data?.data?.rating ?? 0
        } else {
            isRating.value = false
            rating.value = 0
        }
    } finally {
        isLoadingMore.value = false
    }
}

const sendMessage = async ({
    text,
    type,
    file,
}: {
    text: string
    type: "text" | "image" | "file"
    file?: File | null
}) => {
    if (!chatSession.value?.ulid) return
    const tempId = `tmp-${crypto.randomUUID()}`

    const localMessage: LocalChatMessage = {
        id: -1,
        _tempId: tempId,
        message_text: text ?? "",
        message_type: type,
        media_url:
            type === "image" && file ? URL.createObjectURL(file) : null,
        sender_type: isLoggedIn.value ? "user" : "guest",
        created_at: new Date().toISOString(),
        _status: "sending",
    }
    messagesLocal.value.push(localMessage)

    try {
        const formData = new FormData()
        formData.append("message_text", text ?? "")
        formData.append("message_type", type)
        formData.append("sender_type", isLoggedIn.value ? "user" : "guest")
        if (isLoggedIn.value && layout.user?.id) {
            formData.append("sender_id", layout.user.id)
        }
        if (file) {
            formData.append(type === "image" ? "image" : "file", file)
        }

        await axios.post(
            `${baseUrl}/app/api/chats/messages/${chatSession.value.ulid}/send`,
            formData
        )

        const index = messagesLocal.value.findIndex((m) => m._tempId === tempId)
        if (index !== -1) {
            messagesLocal.value[index]._status = "sent"
            messagesLocal.value.splice(index, 1)
        }
    } catch {
        const index = messagesLocal.value.findIndex((m) => m._tempId === tempId)
        if (index !== -1) {
            messagesLocal.value[index]._status = "failed"
        }
    }
}

const stopChatWebSocket = () => {
    if (currentChannelName && window.Echo) {
        window.Echo.leave(currentChannelName)
    }
    chatChannel = null
    websocketInitialized = false
}

const initWebSocket = () => {
    if (!chatSession.value?.ulid || !window.Echo) return

    const channelName = `chat-session.${chatSession.value.ulid}`

    if (currentChannelName === channelName && websocketInitialized) return

    stopChatWebSocket()

    currentChannelName = channelName
    chatChannel = window.Echo.channel(channelName)
    websocketInitialized = true

    const notifiedMessageIds = new Set<number>()

    chatChannel.listen(".message", (e: any) => {
        const msg = e.message
        if (!msg) return

        messagesLocal.value = messagesLocal.value.filter(
            (m) => !(m._status === "sending" && m.sender_type === msg.sender_type)
        )

        const index = messagesLocal.value.findIndex(
            (m) => m.id === msg.id
        )

        const isNewMessage = index === -1

        if (index !== -1) {
            messagesLocal.value[index] = {
                ...messagesLocal.value[index],
                ...msg,
                _status: "sent",
            }
        } else {
            messagesLocal.value.push({
                ...msg,
                _status: "sent",
            })
        }

        if (
            isNewMessage &&
            msg.sender_type === "agent" &&
            !msg.is_read
        ) {
            unreadMessageIds.add(msg.id)
            unreadCount.value = unreadMessageIds.size
        }

        if (
            isNewMessage &&
            msg.sender_type === "agent" &&
            !notifiedMessageIds.has(msg.id)
        ) {
            playNotificationSoundFile(soundUrl)
            notifiedMessageIds.add(msg.id)
            markAsRead()
        }

        if (e.session_status === "closed") {
            isRating.value = true
        }

        forceScrollBottom()
    })

    chatChannel.listen(".messages.read", (event: any) => {
        if (event.reader_type === "agent") {
            messagesLocal.value.forEach((msg) => {
                if (event.message_ids.includes(msg.id)) {
                    msg.is_read = true
                }
            })
        }
    })
}

const forceScrollBottom = () => {
    setTimeout(() => {
        const el = document.querySelector(".messages-container")
        if (el) el.scrollTop = el.scrollHeight
    }, 120)
}

const initChat = async () => {
    if (!chatSession.value?.ulid) {
        console.error("No session available")
        return
    }
    await getMessages()
    initWebSocket()
    forceScrollBottom()
}

const statusChat = ref(false)
const chatHours = ref({
    start: '',
    end: ''
})

const isUser = ref<boolean>(false)
const isCheckingStatus = ref(false)
const toggle = async () => {
    open.value = !open.value
    if (open.value) {
        unreadMessageIds.clear()
        unreadCount.value = 0

        isCheckingStatus.value = true
        try {
            let session = loadChatSession()

            if (!session) {
                session = await createSession()
                if (!session) return
            } else {
                chatSession.value = session
            }
            await checkChatStatus(session.ulid)

            if (statusChat.value) {
                await initChat()
            }

        } catch (e) {
            console.error("Chat status fetch failed", e)
        } finally {
            isCheckingStatus.value = false
        }
    }
}

const loadUserSessions = async () => {
    if (!layout?.user?.id) return
    isLoadingHistory.value = true
    try {
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, {
            params: { web_user_id: layout.user.id },
        })
        userSessions.value = res.data?.data?.sessions ?? []
    } finally {
        isLoadingHistory.value = false
    }
}

const openSessionFromHistory = async (ulid: string) => {
    stopChatWebSocket()
    messages.value = []
    isRating.value = false
    chatSession.value = { ulid }
    localStorage.setItem("chat", JSON.stringify({ ulid, saved_at: new Date().toISOString() }))
    await getMessages(false)
    initWebSocket()
    forceScrollBottom()
}

const checkChatStatus = async (sessionUlid: string) => {
    isCheckingStatus.value = true

    try {
        const res = await axios.get(`${baseUrl}/app/api/chats/status`, {
            params: {
                shop_id: layout?.iris?.shop?.id,
                ulid: sessionUlid
            },
        })

        const config = res.data.chat_config

        statusChat.value = config?.is_online ?? false
        isUser.value = config?.is_metadata ?? false

        if (config?.schedule) {
            chatHours.value = {
                start: config.schedule.start,
                end: config.schedule.end
            }
        }

    } catch (e) {
        console.error("Chat status fetch failed", e)
        statusChat.value = false
    } finally {
        isCheckingStatus.value = false
    }
}


const startNewSession = async () => {
    localStorage.removeItem("chat")
    stopChatWebSocket()
    messages.value = []
    isRating.value = false

    const session = await createSession()
    if (!session) return

    await checkChatStatus(session.ulid)

    if (statusChat.value) {
        await getMessages()
        initWebSocket()
        forceScrollBottom()
    }
}

const handleOfflineSession = (sessionData: ChatSessionData) => {
    chatSession.value = sessionData
    saveChatSession(sessionData)
}

watch(activeMenu, (v) => v === "history" && loadUserSessions())

onMounted(() => {
    syncLoginState()
    window.addEventListener("storage", syncLoginState)

    document.addEventListener("mousedown", (e) => {
        if (
            open.value &&
            panelRef.value &&
            !panelRef.value.contains(e.target as Node) &&
            !buttonRef.value?.contains(e.target as Node)
        ) {
            open.value = false
        }
    })
})

onBeforeUnmount(stopChatWebSocket)

defineExpose({
    messages,
    sendMessage,
    chatSession,
    loading,
    isInitialLoad,
    isLoadingMore,
})

</script>

<template>
    <div>
        <button ref="buttonRef" @click="toggle"
            class="fixed bottom-20 right-5 z-[60] flex items-center gap-2 px-4 py-4 rounded-xl shadow-lg buttonPrimary">
            <FontAwesomeIcon :icon="faMessage" class="text-base" />
            <span v-if="unreadCount > 0" class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1
               bg-red-500 text-white text-[10px] font-semibold
               rounded-full flex items-center justify-center">
                {{ unreadCount }}
            </span>
        </button>

        <transition enter-active-class="transition duration-150" enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150"
            leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95" id="chat">
            <div v-if="open" ref="panelRef"
                class="fixed right-3 z-[70] w-[calc(100vw-1.5rem)] sm:w-[350px] bg-[#f6f6f7] rounded-md overflow-hidden border shadow-xl bottom-32 sm:bottom-[9rem] max-h-[calc(100dvh-7rem)] sm:max-h-[calc(100dvh-12rem)] flex flex-col">
                <div class="flex justify-between items-center px-3 py-2 border-b text-sm font-semibold">
                    <span>{{ trans("Chat Support") }}</span>

                    <div v-if="isLoggedIn" class="flex gap-1 capitalize">
                        <button v-for="m in ['chat', 'history']" :key="m" @click="activeMenu = m"
                            class="px-2 py-1 rounded text-xs transition"
                            :class="activeMenu === m ? 'text-white' : 'bg-gray-100 text-gray-600'" :style="activeMenu === m
                                ? {
                                    backgroundColor: layout.app.theme[4],
                                    color: layout.app.theme[5],
                                }
                                : {}
                                ">
                            {{ m }}
                        </button>
                    </div>
                </div>

                <div v-if="isCheckingStatus" class="flex flex-col items-center bg-white">
                    <FontAwesomeIcon :icon="faSpinner" class="animate-spin text-2xl" />
                    <span class="text-sm">{{ trans("Connecting...") }}</span>
                </div>

                <MessageArea v-if="activeMenu == 'chat' && !isCheckingStatus && statusChat" :messages="messagesLocal"
                    :session="chatSession" :loading="loading" :isRating="isRating" :rating="rating" :isUser="isUser"
                    :isLoggedIn="isLoggedIn" @send-message="sendMessage"
                    @reload="(loadMore: any) => getMessages(loadMore)" @mounted="forceScrollBottom"
                    @new-session="startNewSession" :assignedAgent="assignedAgent" />

                <OfflineChatForm v-else-if="activeMenu == 'chat' && !isCheckingStatus && !statusChat" :hours="chatHours"
                    :session="chatSession" :isLoggedIn="isLoggedIn" @session-created="handleOfflineSession" />

                <div v-if="activeMenu === 'history'"
                    class="bg-gray-50 px-3 py-2 space-y-2 overflow-y-auto min-h-[350px] max-h-[calc(100vh-400px)] scroll-smooth">
                    <MessageHistory v-if="selectedHistory" :sessionUlid="selectedHistory.ulid"
                        :session="selectedHistory" @back="selectedHistory = null" viewerType="user" />

                    <div v-else>
                        <HistoryChatList :data="userSessions" :loading="isLoadingHistory"
                            @click-session="selectedHistory = $event" />
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.buttonPrimary {
    background-color: v-bind("layout?.app?.theme[4]") !important;
    color: v-bind("layout?.app?.theme[5]") !important;
    border: v-bind("`1px solid color-mix(in srgb, ${layout?.app?.theme[4]} 80%, black)`");

    &:hover {
        background-color: v-bind("`color-mix(in srgb, ${layout?.app?.theme[4]} 85%, black)`"
        ) !important;
    }

    &:focus {
        box-shadow: 0 0 0 2px v-bind("layout?.app?.theme[4]") !important;
    }
}
</style>
