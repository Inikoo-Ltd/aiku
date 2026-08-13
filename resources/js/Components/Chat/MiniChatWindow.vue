<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faXmark,
    faChevronDown,
    faChevronUp,
    faPaperPlane,
    faArrowUpRightFromSquare,
} from "@fortawesome/free-solid-svg-icons"
import { faUser } from "@fal"
import { faCheck, faCheckDouble } from "@far"
import { notify } from "@kyvg/vue3-notification"
import { useLayoutStore } from "@/Stores/layout"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import type { MiniChat } from "@/Composables/useMiniChats"

type LocalMessageStatus = "sending" | "sent" | "failed"

type LocalChatMessage = Record<string, any> & {
    _status?: LocalMessageStatus
    _tempId?: string
}

const props = defineProps<{ chat: MiniChat }>()

const emit = defineEmits(["close", "toggle", "read"])

const layout: any = useLayoutStore()
const baseUrl = layout?.appUrl ?? ""

const messages = ref<LocalChatMessage[]>([])
const newMessage = ref("")
const isLoading = ref(false)
const isSending = ref(false)
const unreadCount = ref(0)
const remoteTypingUser = ref<string | null>(null)
const messagesContainer = ref<HTMLElement | null>(null)

const isClosed = computed(() => props.chat.status === "closed")
const isWaiting = computed(() => props.chat.status === "waiting")
const canSend = computed(() => !isClosed.value && !isWaiting.value)

const scrollBottom = () =>
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })

const sortedMessages = computed(() =>
    [...messages.value].sort((a, b) => +new Date(a.created_at) - +new Date(b.created_at))
)

const messageText = (message: LocalChatMessage) =>
    message.original?.text ?? message.message_text ?? ""

const formatTime = (value?: string | null) =>
    value ? new Date(value).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : ""

const openAttachment = (message: LocalChatMessage) => {
    const url = message.download_route?.url ?? message.media_url?.original
    if (url) {
        window.open(url, "_blank")
    }
}

const getMessages = async () => {
    isLoading.value = true
    try {
        const { data } = await axios.get(
            `${baseUrl}/app/api/chats/sessions/${props.chat.ulid}/messages`,
            { params: { limit: 20, request_from: "agent" } }
        )
        messages.value = (data?.data?.messages ?? []).map((message: any) => ({
            ...message,
            _status: "sent",
        }))
        scrollBottom()
    } catch (e) {
        console.error("Failed to fetch mini chat messages", e)
    } finally {
        isLoading.value = false
    }
}

const markAsRead = async () => {
    try {
        await axios.post(`${baseUrl}/app/api/chats/read`, {
            session_ulid: props.chat.ulid,
            request_from: "agent",
        })
        unreadCount.value = 0
        emit("read")
    } catch (e) {
        console.error("Failed to mark mini chat as read", e)
    }
}

const sendMessage = async () => {
    const text = newMessage.value.trim()
    if (!text || isSending.value || !canSend.value) return

    const tempId = `tmp-${Date.now()}`
    messages.value.push({
        id: tempId,
        _tempId: tempId,
        message_text: text,
        sender_type: "agent",
        message_type: "text",
        created_at: new Date().toISOString(),
        _status: "sending",
    })

    newMessage.value = ""
    isSending.value = true
    scrollBottom()

    try {
        await axios.post(`${baseUrl}/app/api/chats/messages/${props.chat.ulid}/send`, {
            message_text: text,
            message_type: "text",
            sender_type: "agent",
        })
    } catch (error: any) {
        const failed = messages.value.find((message) => message._tempId === tempId)
        if (failed) {
            failed._status = "failed"
        }
        notify({
            title: trans("Error"),
            text: error?.response?.data?.message ?? trans("Failed to send message"),
            type: "error",
        })
    } finally {
        isSending.value = false
    }
}

let typingTimeout: ReturnType<typeof setTimeout> | null = null
let remoteTypingTimeout: ReturnType<typeof setTimeout> | null = null
let isTyping = false

const sendTypingStatus = async (status: boolean) => {
    try {
        await axios.post(`${baseUrl}/app/api/chats/typing`, {
            session_ulid: props.chat.ulid,
            user_name: "agent",
            is_typing: status,
        })
    } catch (e) {
        console.error("Typing status error", e)
    }
}

const handleTyping = () => {
    if (!isTyping) {
        isTyping = true
        sendTypingStatus(true)
    }

    if (typingTimeout) clearTimeout(typingTimeout)

    typingTimeout = setTimeout(() => {
        isTyping = false
        sendTypingStatus(false)
    }, 800)
}

let chatChannel: any = null

const stopSocket = () => {
    chatChannel?.stopListening(".message")
    chatChannel?.stopListening(".typing")
    chatChannel = null
}

const initSocket = () => {
    if (!window.Echo) return

    stopSocket()

    chatChannel = window.Echo.channel(`chat-session.${props.chat.ulid}`)

    chatChannel.listen(".message", ({ message }: any) => {
        messages.value = messages.value.filter(
            (item) => !(item._status === "sending" && item.sender_type === "agent")
        )

        const index = messages.value.findIndex((item) => item.id === message.id)

        if (index !== -1) {
            messages.value[index] = { ...messages.value[index], ...message, _status: "sent" }
            return
        }

        messages.value.push({ ...message, _status: "sent" })

        if (message.sender_type !== "agent") {
            if (props.chat.isMinimised) {
                unreadCount.value += 1
            } else {
                markAsRead()
            }
        }

        scrollBottom()
    })

    chatChannel.listen(".typing", (payload: any) => {
        if (payload.user_name === "agent") return

        remoteTypingUser.value = payload.is_typing ? payload.user_name : null

        if (remoteTypingTimeout) clearTimeout(remoteTypingTimeout)

        remoteTypingTimeout = setTimeout(() => {
            remoteTypingUser.value = null
        }, 1500)
    })
}

const openFullConversation = () => {
    console.log("openFullConversation", props.chat)
    if (!props.chat.organisationSlug) return
    router.visit(route("grp.org.chat.inbox.conversation", [props.chat.organisationSlug, props.chat.ulid]))
}

watch(
    () => props.chat.isMinimised,
    (isMinimised) => {
        if (isMinimised) return
        unreadCount.value = 0
        markAsRead()
        scrollBottom()
    }
)

onMounted(async () => {
    await getMessages()
    initSocket()
    await markAsRead()
})

onUnmounted(() => {
    stopSocket()
    if (typingTimeout) clearTimeout(typingTimeout)
    if (remoteTypingTimeout) clearTimeout(remoteTypingTimeout)
})
</script>

<template>
    <div class="w-60 max-w-[92vw] bg-white text-gray-800 rounded-t-lg shadow-2xl border border-gray-200 flex flex-col overflow-hidden"
        :class="chat.isMinimised ? '' : 'h-[320px] max-h-[60vh]'">
        <div class="flex items-center gap-1.5 px-2 py-1.5 border-b border-gray-200 bg-white cursor-pointer shrink-0"
            @click="emit('toggle')">
            <div class="relative w-5 h-5 shrink-0">
                <div class="w-5 h-5 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center text-gray-400">
                    <Image v-if="chat.avatar" :src="chat.avatar" class="w-full h-full object-cover" />
                    <FontAwesomeIcon v-else :icon="faUser" class="text-[9px]" />
                </div>
                <span v-if="chat.status === 'active'"
                    class="absolute -bottom-0.5 -right-0.5 w-1.5 h-1.5 rounded-full bg-green-500 ring-1 ring-white" />
            </div>

            <div class="flex-1 min-w-0 text-[11px] font-semibold text-gray-900 truncate">
                {{ chat.contactName }}
            </div>

            <span v-if="unreadCount > 0"
                class="px-1 rounded-full bg-red-500 text-white text-[9px] font-semibold leading-4">
                {{ unreadCount }}
            </span>

            <button class="w-5 h-5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600"
                v-tooltip="trans('Open in inbox')" @click.stop="openFullConversation">
                <FontAwesomeIcon :icon="faArrowUpRightFromSquare" class="text-[9px]" />
            </button>

            <button class="w-5 h-5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600"
                v-tooltip="chat.isMinimised ? trans('Expand') : trans('Minimise')" @click.stop="emit('toggle')">
                <FontAwesomeIcon :icon="chat.isMinimised ? faChevronUp : faChevronDown" class="text-[9px]" />
            </button>

            <button class="w-5 h-5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600"
                v-tooltip="trans('Close')" @click.stop="emit('close')">
                <FontAwesomeIcon :icon="faXmark" class="text-[10px]" />
            </button>
        </div>

        <template v-if="!chat.isMinimised">
            <div ref="messagesContainer"
                class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden px-2 py-1.5 space-y-1 bg-[#F0F4F8]">
                <div v-if="isLoading" class="h-full flex items-center justify-center">
                    <LoadingIcon class="w-4 h-4 text-gray-400" />
                </div>

                <div v-else-if="sortedMessages.length === 0"
                    class="h-full flex items-center justify-center text-[11px] text-gray-500">
                    {{ trans('No messages yet') }}
                </div>

                <template v-else>
                    <div v-for="message in sortedMessages" :key="message.id" class="flex"
                        :class="message.sender_type === 'agent' ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[85%] min-w-0 px-2 py-1 rounded-lg text-[11px] leading-snug shadow-sm"
                            :class="message.sender_type === 'agent'
                                ? 'bg-indigo-500 text-white rounded-br-sm'
                                : 'bg-white text-gray-800 rounded-bl-sm'">
                            <p v-if="messageText(message)" class="whitespace-pre-wrap break-words">
                                {{ messageText(message) }}
                            </p>

                            <img v-if="message.message_type === 'image' && message.media_url"
                                :src="message.media_url.webp ?? message.media_url.original" :alt="trans('Attachment')"
                                class="mt-1 rounded max-w-full max-h-28 object-contain cursor-pointer bg-gray-50"
                                @click="openAttachment(message)" />

                            <button v-else-if="message.message_type === 'file' && message.media_url" type="button"
                                class="mt-1 flex items-center gap-1 max-w-full text-[10px] underline truncate"
                                @click="openAttachment(message)">
                                📄 {{ message.file_name || message.media_url.name || trans('Attachment') }}
                            </button>

                            <div class="flex items-center justify-end gap-1 mt-0.5 text-[9px]"
                                :class="message.sender_type === 'agent' ? 'text-white/70' : 'text-gray-400'">
                                <span>{{ formatTime(message.created_at) }}</span>
                                <LoadingIcon v-if="message._status === 'sending'" class="w-2.5 h-2.5" />
                                <span v-else-if="message._status === 'failed'" class="text-red-300">!</span>
                                <FontAwesomeIcon v-else-if="message.sender_type === 'agent'"
                                    :icon="message.is_read ? faCheckDouble : faCheck" class="text-[9px]" />
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div v-if="remoteTypingUser" class="px-2 py-0.5 text-[10px] text-gray-400 italic truncate shrink-0">
                {{ remoteTypingUser }} {{ trans('is typing...') }}
            </div>

            <div v-if="isClosed" class="px-2 py-1.5 border-t border-gray-200 text-[10px] text-gray-500 shrink-0">
                {{ trans('This chat has been closed') }}
            </div>

            <div v-else-if="isWaiting"
                class="flex items-center justify-between gap-1 px-2 py-1.5 border-t border-gray-200 shrink-0">
                <span class="text-[10px] text-gray-500 truncate">{{ trans('Assign to reply') }}</span>
                <button class="text-[10px] font-medium hover:underline shrink-0"
                    :style="{ color: 'var(--theme-color-4)' }" @click="openFullConversation">
                    {{ trans('Open in inbox') }}
                </button>
            </div>

            <div v-else class="flex items-end gap-1.5 px-2 py-1.5 border-t border-gray-200 shrink-0">
                <textarea v-model="newMessage" rows="1" :placeholder="trans('Type message...')"
                    class="flex-1 min-w-0 resize-none text-[11px] leading-tight px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:border-gray-400 focus:ring-0"
                    @input="handleTyping" @keydown.enter.exact.prevent="sendMessage" />
                <button class="w-6 h-6 shrink-0 rounded-md flex items-center justify-center text-white disabled:opacity-50"
                    :style="{ backgroundColor: 'var(--theme-color-4)' }" :disabled="isSending || !newMessage.trim()"
                    @click="sendMessage">
                    <FontAwesomeIcon :icon="faPaperPlane" class="text-[9px]" />
                </button>
            </div>
        </template>
    </div>
</template>
