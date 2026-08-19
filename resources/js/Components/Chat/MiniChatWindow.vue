<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faXmark,
    faChevronDown,
    faPaperPlane,
    faArrowUpRightFromSquare,
    faPlus,
    faImage,
    faPaperclip,
    faEnvelope,
    faFileLines,
    faTimesCircle,
} from "@fortawesome/free-solid-svg-icons"
import { faJira } from "@fortawesome/free-brands-svg-icons"
import { faUser } from "@fal"
import { faCheck, faCheckDouble } from "@far"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { notify } from "@kyvg/vue3-notification"
import { useLayoutStore } from "@/Stores/layout"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import JiraTicketModal from "@/Components/Chat/Agent/JiraTicketModal.vue"
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
const messageInput = ref<HTMLTextAreaElement | null>(null)

const autoResize = () => {
    const el = messageInput.value
    if (!el) return
    el.style.height = "auto"
    el.style.height = Math.min(el.scrollHeight, 96) + "px"
}
const isLoading = ref(false)
const isSending = ref(false)
const unreadCount = ref(0)
const remoteTypingUser = ref<string | null>(null)
const messagesContainer = ref<HTMLElement | null>(null)

const imageInput = ref<HTMLInputElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const previewUrl = ref<string | null>(null)
const previewType = ref<"image" | "file" | null>(null)
const isEmailNotif = ref(false)
const isOptionMenuOpen = ref(false)
const optionMenuRef = ref<HTMLElement | null>(null)
const isJiraModalOpen = ref(false)

const IMAGE_TYPES = ["image/webp", "image/jpeg", "image/jpg", "image/png", "image/avif"]

const FILE_TYPES = [
    "application/pdf",
    "application/vnd.ms-excel",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
]

const MAX_SIZE = 10 * 1024 * 1024

const isClosed = computed(() => props.chat.status === "closed")
const isWaiting = computed(() => props.chat.status === "waiting")
const canSend = computed(() => !isClosed.value && !isWaiting.value)
const hasAttachment = computed(() => !!selectedFile.value)

const onChatEnded = () => {
    isOptionMenuOpen.value = false
    props.chat.status = "closed"
}

const isAssigningSelf = ref(false)
const assignSelf = async () => {
    if (!props.chat.organisationSlug || !props.chat.ulid || isAssigningSelf.value) return
    isAssigningSelf.value = true
    try {
        await axios.post(
            route("grp.org.chat.agents.assign.self", [props.chat.organisationSlug, props.chat.ulid]),
            {},
            { withCredentials: true }
        )
        props.chat.status = "active"
    } catch (e: any) {
        notify({
            title: trans("Error"),
            text: e?.response?.data?.message ?? trans("Failed to assign chat"),
            type: "error",
        })
    } finally {
        isAssigningSelf.value = false
    }
}

const jiraSession = computed(() => ({
    ulid: props.chat.ulid,
    contact_name: props.chat.contactName,
}))

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

const notifyRejectedFile = (text: string) =>
    notify({ title: trans("Failed"), text, type: "error" })

const handleImageSelect = (event: Event) => {
    const file = (event.target as HTMLInputElement)?.files?.[0]
    if (!file) return

    if (!IMAGE_TYPES.includes(file.type)) {
        notifyRejectedFile(trans("Image format not supported"))
        return
    }

    if (file.size > MAX_SIZE) {
        notifyRejectedFile(trans("Maximum image size 10MB"))
        return
    }

    clearAttachment()
    selectedFile.value = file
    previewType.value = "image"
    previewUrl.value = URL.createObjectURL(file)
}

const handleDocSelect = (event: Event) => {
    const file = (event.target as HTMLInputElement)?.files?.[0]
    if (!file) return

    if (!FILE_TYPES.includes(file.type)) {
        notifyRejectedFile(trans("File format not supported"))
        return
    }

    if (file.size > MAX_SIZE) {
        notifyRejectedFile(trans("Maximum file size 10MB"))
        return
    }

    clearAttachment()
    selectedFile.value = file
    previewType.value = "file"
    previewUrl.value = null
}

const clearAttachment = (revokePreview = true) => {
    if (revokePreview && previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
    }

    selectedFile.value = null
    previewUrl.value = null
    previewType.value = null

    if (imageInput.value) imageInput.value.value = ""
    if (fileInput.value) fileInput.value.value = ""
}

const pickAttachment = (input: HTMLInputElement | null) => {
    isOptionMenuOpen.value = false
    input?.click()
}

const toggleEmailNotif = () => {
    isEmailNotif.value = !isEmailNotif.value
    isOptionMenuOpen.value = false
}

const openJiraModal = () => {
    isOptionMenuOpen.value = false
    isJiraModalOpen.value = true
}

const closeOptionMenu = (event: MouseEvent) => {
    if (isOptionMenuOpen.value && optionMenuRef.value && !optionMenuRef.value.contains(event.target as Node)) {
        isOptionMenuOpen.value = false
    }
}

const sendMessage = async () => {
    const text = newMessage.value.trim()
    const file = selectedFile.value

    if ((!text && !file) || isSending.value || !canSend.value) return

    const messageType = file ? previewType.value ?? "file" : "text"
    const tempId = `tmp-${Date.now()}`
    messages.value.push({
        id: tempId,
        _tempId: tempId,
        message_text: text,
        message_type: messageType,
        media_url: messageType === "image" ? { original: previewUrl.value } : null,
        file_name: file?.name,
        sender_type: "agent",
        created_at: new Date().toISOString(),
        _status: "sending",
    })

    newMessage.value = ""
    nextTick(autoResize)
    isSending.value = true
    clearAttachment(false)
    scrollBottom()

    try {
        const formData = new FormData()
        formData.append("message_text", text)
        formData.append("message_type", messageType)
        formData.append("sender_type", "agent")
        formData.append("is_email_notif", String(isEmailNotif.value))

        if (file) {
            formData.append(messageType === "image" ? "image" : "file", file)
        }

        await axios.post(
            route("grp.org.chat.agents.messages.send", [props.chat.organisationSlug, props.chat.ulid]),
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
        )

        isEmailNotif.value = false
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
    document.addEventListener("click", closeOptionMenu)
    await getMessages()
    initSocket()
    await markAsRead()
})

onUnmounted(() => {
    document.removeEventListener("click", closeOptionMenu)
    stopSocket()
    clearAttachment()
    if (typingTimeout) clearTimeout(typingTimeout)
    if (remoteTypingTimeout) clearTimeout(remoteTypingTimeout)
})
</script>

<template>
    <div class="w-60 max-w-[92vw] bg-white text-gray-800 rounded-t-lg shadow-2xl border border-gray-200 flex flex-col overflow-hidden">
        <div class="flex items-center gap-1.5 px-2 py-1.5 border-b border-gray-200 bg-white cursor-pointer shrink-0"
            @click="emit('toggle')">
            <div class="relative w-5 h-5 shrink-0">
                <div class="w-5 h-5 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center text-gray-400">
                    <Image v-if="chat.avatar" :src="chat.avatar" class="w-full h-full object-cover" />
                    <FontAwesomeIcon v-else :icon="faUser" class="text-[9px]" />
                </div>
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
                <FontAwesomeIcon :icon="faChevronDown" class="text-[9px] transition-transform duration-300 ease-in-out"
                    :class="chat.isMinimised ? 'rotate-180' : 'rotate-0'" />
            </button>

            <button class="w-5 h-5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600"
                v-tooltip="trans('Close')" @click.stop="emit('close')">
                <FontAwesomeIcon :icon="faXmark" class="text-[10px]" />
            </button>
        </div>

        <div :inert="chat.isMinimised"
            class="flex flex-col min-h-0 overflow-hidden transition-[height,opacity] duration-300 ease-in-out"
            :class="chat.isMinimised ? 'h-0 opacity-0' : 'h-[320px] max-h-[60vh] opacity-100'">
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

            <div v-else-if="isWaiting" class="px-2 py-1.5 border-t border-gray-200 shrink-0 space-y-1.5">
                <div class="flex items-center justify-between gap-1">
                    <span class="text-[10px] text-gray-500 truncate">{{ trans('Assign this chat to reply') }}</span>
                    <button class="text-[10px] font-medium hover:underline shrink-0"
                        :style="{ color: 'var(--theme-color-4)' }" @click="openFullConversation">
                        {{ trans('Open in inbox') }}
                    </button>
                </div>
                <button type="button"
                    class="w-full flex items-center justify-center gap-1.5 h-7 rounded-md text-[11px] font-medium text-white transition hover:opacity-90 disabled:opacity-60"
                    :style="{ backgroundColor: 'var(--theme-color-4)' }"
                    :disabled="isAssigningSelf" @click="assignSelf">
                    <LoadingIcon v-if="isAssigningSelf" class="w-3 h-3" />
                    <FontAwesomeIcon v-else :icon="faUser" class="text-[9px]" />
                    {{ trans('Assign to me') }}
                </button>
            </div>

            <template v-else>
                <div v-if="previewType === 'image' && previewUrl" class="px-2 pt-1.5 shrink-0">
                    <div class="relative inline-block">
                        <img :src="previewUrl" class="h-12 rounded border border-gray-200 object-cover" />
                        <button type="button"
                            class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-white shadow border border-gray-200 text-gray-500 hover:text-red-500"
                            @click="clearAttachment()">
                            <FontAwesomeIcon :icon="faXmark" class="text-[8px]" />
                        </button>
                    </div>
                </div>

                <div v-else-if="previewType === 'file' && selectedFile" class="px-2 pt-1.5 shrink-0">
                    <div class="flex items-center gap-1.5 px-1.5 py-1 rounded border border-gray-200 bg-gray-50 min-w-0">
                        <FontAwesomeIcon :icon="faFileLines" class="text-[10px] text-gray-400 shrink-0" />
                        <div class="flex-1 min-w-0">
                            <div class="text-[10px] text-gray-700 truncate">{{ selectedFile.name }}</div>
                            <div class="text-[9px] text-gray-400">{{ (selectedFile.size / 1024).toFixed(1) }} KB</div>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-red-500 shrink-0"
                            @click="clearAttachment()">
                            <FontAwesomeIcon :icon="faXmark" class="text-[9px]" />
                        </button>
                    </div>
                </div>

                <div class="flex items-end gap-1.5 px-2 py-1.5 border-t border-gray-200 shrink-0">
                    <input ref="imageInput" type="file" accept=".webp,.jpg,.jpeg,.png,.avif" class="hidden"
                        @change="handleImageSelect" />
                    <input ref="fileInput" type="file" accept=".pdf,.xls,.xlsx" class="hidden"
                        @change="handleDocSelect" />

                    <textarea ref="messageInput" v-model="newMessage" rows="1" :placeholder="trans('Type message...')"
                        style="max-height: 96px;"
                        class="flex-1 min-w-0 resize-none overflow-y-auto text-[11px] leading-tight px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:border-gray-400 focus:ring-0"
                        @input="handleTyping(); autoResize()" @keydown.enter.exact.prevent="sendMessage" />

                    <div ref="optionMenuRef" class="relative shrink-0">
                        <button type="button"
                            class="w-6 h-6 rounded-md flex items-center justify-center border border-gray-300 text-gray-500 hover:bg-gray-100"
                            :class="{ 'bg-gray-100 text-gray-700': isOptionMenuOpen }" v-tooltip="trans('More options')"
                            @click.stop="isOptionMenuOpen = !isOptionMenuOpen">
                            <FontAwesomeIcon :icon="faPlus" class="text-[9px]" />
                        </button>

                        <div v-if="isOptionMenuOpen"
                            class="absolute bottom-full right-0 mb-1 w-36 py-1 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                            <button type="button"
                                class="w-full flex items-center gap-2 px-2 py-1 text-[10px] text-gray-700 hover:bg-gray-100"
                                @click="pickAttachment(imageInput)">
                                <FontAwesomeIcon :icon="faImage" class="text-[9px] text-gray-400" />
                                {{ trans('Upload image') }}
                            </button>

                            <button type="button"
                                class="w-full flex items-center gap-2 px-2 py-1 text-[10px] text-gray-700 hover:bg-gray-100"
                                @click="pickAttachment(fileInput)">
                                <FontAwesomeIcon :icon="faPaperclip" class="text-[9px] text-gray-400" />
                                {{ trans('Upload file') }}
                            </button>

                            <button type="button"
                                class="w-full flex items-center justify-between gap-2 px-2 py-1 text-[10px] text-gray-700 hover:bg-gray-100"
                                @click="toggleEmailNotif">
                                <span class="flex items-center gap-2">
                                    <FontAwesomeIcon :icon="faEnvelope" class="text-[9px] text-gray-400" />
                                    {{ trans('Email notification') }}
                                </span>
                                <span class="text-[8px] font-semibold"
                                    :class="isEmailNotif ? 'text-green-600' : 'text-gray-400'">
                                    {{ isEmailNotif ? trans('ON') : trans('OFF') }}
                                </span>
                            </button>

                            <button v-if="chat.organisationSlug" type="button"
                                class="w-full flex items-center gap-2 px-2 py-1 text-[10px] text-gray-700 hover:bg-gray-100"
                                @click="openJiraModal">
                                <FontAwesomeIcon :icon="faJira" class="text-[9px] text-gray-400" />
                                {{ trans('Create Jira ticket') }}
                            </button>

                            <div class="my-1 border-t border-gray-100"></div>

                            <ModalConfirmationDelete
                                :routeDelete="{
                                    name: 'grp.org.chat.agents.sessions.close',
                                    parameters: [chat.organisationSlug, chat.ulid],
                                    method: 'patch',
                                }"
                                :title="trans('Are you sure you want to end this chat?')"
                                :description="trans('This will close the chat session. The conversation history will be preserved.')"
                                :noLabel="trans('End chat')"
                                :noIcon="faTimesCircle"
                                @success="onChatEnded">
                                <template #default="{ changeModel }">
                                    <button type="button"
                                        class="w-full flex items-center gap-2 px-2 py-1 text-[10px] text-red-600 hover:bg-red-50"
                                        @click="changeModel">
                                        <FontAwesomeIcon :icon="faTimesCircle" class="text-[9px]" />
                                        {{ trans('End chat') }}
                                    </button>
                                </template>
                            </ModalConfirmationDelete>
                        </div>
                    </div>

                    <button
                        class="w-6 h-6 shrink-0 rounded-md flex items-center justify-center text-white disabled:opacity-50"
                        :style="{ backgroundColor: 'var(--theme-color-4)' }"
                        :disabled="isSending || (!newMessage.trim() && !hasAttachment)" @click="sendMessage">
                        <FontAwesomeIcon :icon="faPaperPlane" class="text-[9px]" />
                    </button>
                </div>
            </template>
        </div>

        <JiraTicketModal v-if="chat.organisationSlug" :is-open="isJiraModalOpen" :session="(jiraSession as any)"
            :organisation="chat.organisationSlug" @close="isJiraModalOpen = false" />
    </div>
</template>
