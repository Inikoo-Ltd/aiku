<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, inject, computed, nextTick } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faPaperPlane,
    faArrowLeft,
    faImage,
    faEllipsisVertical,
    faTimesCircle,
    faMessage,
    faPaperclip, faXmark, faFilePdf
} from "@fortawesome/free-solid-svg-icons"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import type { ChatMessage, SessionAPI } from "@/types/Chat/chat"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"
import { faUser, faSpinner } from "@far"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"
import { useChatLanguages } from "@/Composables/useLanguages"
import { notify } from "@kyvg/vue3-notification"
import { playNotificationSoundFile, buildStorageUrl } from "@/Composables/useNotificationSound"

type LocalMessageStatus = "sending" | "sent" | "failed"

type LocalChatMessage = ChatMessage & {
    _status?: LocalMessageStatus
    _tempId?: string
}

interface GetMessagesParams {
    limit: number
    request_from: string
    cursor?: string | null
    translation_language_id?: number
    media_url?: string | null
}

const props = defineProps<{
    messages: ChatMessage[]
    session: SessionAPI | null
}>()

const emit = defineEmits([
    "send-message",
    "back",
    "close-session",
    "view-history",
    "view-user-profile",
    "view-message-details",
])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const messagesLocal = ref<LocalChatMessage[]>([])
const newMessage = ref("")

const messageInput = ref<HTMLTextAreaElement>()
const messagesContainer = ref<HTMLDivElement>()

const soundUrl = buildStorageUrl("sound/notification.mp3", baseUrl)
// file upload
const imageInput = ref<HTMLInputElement>()
const fileInput = ref<HTMLInputElement>()

const IMAGE_TYPES = [
    "image/webp",
    "image/jpeg",
    "image/jpg",
    "image/png",
    "image/avif",
]

const FILE_TYPES = [
    "application/pdf",
    "application/vnd.ms-excel",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
]

const getMessageTypeFromFile = (file: File): "image" | "file" => {
    return IMAGE_TYPES.includes(file.type) ? "image" : "file"
}

const MAX_SIZE = 10 * 1024 * 1024

const isMenuOpen = ref(false)
const isLoadingMore = ref(false)
const canLoadMore = ref(false)
const nextCursor = ref<string | null>(null)

const chatSession = computed(() => props.session)
const isClosed = computed(() => chatSession.value?.status === "closed")
const menuRef = ref<HTMLElement | null>(null)

const isTyping = ref(false)
let typingTimeout: ReturnType<typeof setTimeout> | null = null
const remoteTypingUser = ref<string | null>(null)
let remoteTypingTimeout: ReturnType<typeof setTimeout> | null = null
const typingUser = ref<string | null>(null)

const { languages, fetchLanguages, getLanguageIdByCode } = useChatLanguages(baseUrl)

const selectedFile = ref<File | null>(null)
const previewUrl = ref<string | null>(null)
const previewType = ref<"image" | "file" | null>(null)

const handleImageSelect = (e: Event) => {
    const file = (e.target as HTMLInputElement)?.files?.[0]
    if (!file) return

    if (!IMAGE_TYPES.includes(file.type)) {
        notify({
            title: "Failed",
            text: "Image format not supported",
            type: "error",
        })
        return
    }

    if (file.size > MAX_SIZE) {
        notify({
            title: "Failed",
            text: "Maximum image size 10MB",
            type: "error",
        })
        return
    }

    selectedFile.value = file
    previewType.value = "image"
    previewUrl.value = URL.createObjectURL(file)
}

const handleDocSelect = (e: Event) => {
    const file = (e.target as HTMLInputElement)?.files?.[0]
    if (!file) return

    if (!FILE_TYPES.includes(file.type)) {
        notify({
            title: "Failed",
            text: "File format not supported",
            type: "error",
        })
        return
    }

    if (file.size > MAX_SIZE) {
        notify({
            title: "Failed",
            text: "Maximum file size 10MB",
            type: "error",
        })
        return
    }

    selectedFile.value = file
    previewType.value = "file"
    previewUrl.value = null
}

const removeFile = () => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
    }

    selectedFile.value = null
    previewUrl.value = null
    previewType.value = null

    if (imageInput.value) imageInput.value.value = ""
    if (fileInput.value) fileInput.value.value = ""
}

const scrollBottom = () =>
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })

const autoResize = () => {
    if (!messageInput.value) return
    messageInput.value.style.height = "auto"
    messageInput.value.style.height = Math.min(messageInput.value.scrollHeight, 120) + "px"
}

const sendMessage = async () => {
    const hasText = !!newMessage.value.trim()
    const hasFile = !!selectedFile.value

    if (!hasText && !hasFile) return

    sendTypingStatus(false)
    isTyping.value = false

    const tempId = `tmp-${Date.now()}`

    const messageType = hasFile
        ? getMessageTypeFromFile(selectedFile.value!)
        : "text"

    const optimisticMessage: LocalChatMessage = {
        id: tempId as any,
        _tempId: tempId,
        message_text: newMessage.value ?? "",
        media_url:
            messageType === "image" ? previewUrl.value : null,
        sender_type: "agent",
        message_type: messageType,
        created_at: new Date().toISOString(),
        _status: "sending",
    }

    messagesLocal.value.push(optimisticMessage)
    scrollBottom()

    const text = newMessage.value
    newMessage.value = ""
    autoResize()
    typingUser.value = null
    try {
        emit("send-message", {
            text: text,
            image: selectedFile.value,
            message_type: messageType,
            tempId,
        })
        removeFile()
        const msg = messagesLocal.value.find((m) => m._tempId === tempId)

        if (msg) msg._status = "sending"
        // const index = messagesLocal.value.findIndex(
        //     (m) => m._tempId === tempId
        // )

        // if (index !== -1) {
        //     messagesLocal.value.splice(index, 1)
        // }
    } catch {
        const msg = messagesLocal.value.find((m) => m._tempId === tempId)
        if (msg) msg._status = "failed"
    }
}

const resendMessage = async (msg: LocalChatMessage) => {
    if (msg._status === "sending") return

    msg._status = "sending"

    try {
        await emit("send-message", msg.message_text)
        msg._status = "sent"
    } catch {
        msg._status = "failed"
    }
}

const getMediaUrl = async (sessionUlid: string) => {
    const { data } = await axios.get(`${baseUrl}/app/api/chats/sessions/${sessionUlid}/messages`, {
        params: {
            limit: 10,
            request_from: "agent",
        },
    })

    const messages = data?.data?.messages ?? []

    const imageMessage = messages
        .filter((m: any) => m.message_type === "image" && m.sender_type === "agent" && m.media_url)
        .sort((a: any, b: any) => +new Date(b.created_at) - +new Date(a.created_at))[0]

    if (!imageMessage) return null

    const media = imageMessage.media_url.webp || null

    return {
        ...imageMessage,
        image_url: media,
    }
}

const getMessages = async (loadMore = false) => {
    if (!chatSession.value?.ulid || (loadMore && !canLoadMore.value)) return

    isLoadingMore.value = loadMore

    const params: GetMessagesParams = {
        limit: loadMore && nextCursor.value ? 50 : 10,
        request_from: "agent",
    }

    if (loadMore && nextCursor.value) {
        params.cursor = nextCursor.value
    }

    if (selectedLanguageId.value) {
        params.translation_language_id = selectedLanguageId.value
    }

    const { data } = await axios.get(
        `${baseUrl}/app/api/chats/sessions/${chatSession.value.ulid}/messages`,
        { params }
    )

    const messages = data?.data?.messages ?? data?.messages ?? []

    if (!loadMore) {
        messagesLocal.value = messages.map((m: ChatMessage) => ({
            ...m,
            _status: "sent",
        }))
    } else {
        messagesLocal.value.unshift(
            ...messages.map((m: ChatMessage) => ({ ...m, _status: "sent" }))
        )
    }

    const page = data?.data?.pagination ?? data?.pagination
    canLoadMore.value = !!page?.has_more
    nextCursor.value = page?.next_cursor ?? null

    isLoadingMore.value = false
    if (!loadMore) {
        scrollBottom()
    }
}

const groupedMessages = computed(() => {
    const groups: Record<string, LocalChatMessage[]> = {}

    messagesLocal.value
        .slice()
        .sort((a, b) => +new Date(a.created_at) - +new Date(b.created_at))
        .forEach((msg) => {
            const label = new Intl.DateTimeFormat("id-ID", {
                day: "2-digit",
                month: "long",
                year: "numeric",
            }).format(new Date(msg.created_at))

                ; (groups[label] ??= []).push(msg)
        })

    return groups
})

let chatChannel: any = null

const stopSocket = () => {
    chatChannel?.stopListening(".message")
    chatChannel?.stopListening(".typing")
    chatChannel?.stopListening(".messages.read")
    chatChannel?.stopListening(".translation")
    chatChannel = null
}

const isTranslatingAll = ref(false)
const notifiedMessageIds = new Set<number>()

const initSocket = () => {
    if (!chatSession.value?.ulid || !window.Echo) return

    stopSocket()

    chatChannel = window.Echo.channel(`chat-session.${chatSession.value.ulid}`)

    // Message
    chatChannel.listen(".message", ({ message }: any) => {
        messagesLocal.value = messagesLocal.value.filter(
            (m) => !(m._status === "sending" && m.sender_type === "agent")
        )

        const index = messagesLocal.value.findIndex(
            (m) => m.id === message.id
        )

        if (index !== -1) {
            messagesLocal.value[index] = {
                ...messagesLocal.value[index],
                ...message,
                _status: "sent",
            }
        } else {
            messagesLocal.value.push({
                ...message,
                _status: "sent",
            })
        }

        const isNewMessage = index === -1

        if (
            isNewMessage &&
            message.sender_type !== "agent" &&
            !notifiedMessageIds.has(message.id)
        ) {
            playNotificationSoundFile(soundUrl)
            notifiedMessageIds.add(message.id)
        }

        if (message.sender_type !== "agent") {
            markAsRead()
        }

        scrollBottom()
    })
    chatChannel.listen(".messages.read", (event: any) => {
        if (event.reader_type !== "agent") {
            messagesLocal.value.forEach((msg) => {
                if (event.message_ids.includes(msg.id)) {
                    msg.is_read = true
                }
            })
        }
    })

    chatChannel.listen(".typing", (payload: any) => {
        if (payload.user_name === "agent") return

        if (payload.is_typing) {
            remoteTypingUser.value = payload.user_name

            if (remoteTypingTimeout) clearTimeout(remoteTypingTimeout)

            remoteTypingTimeout = setTimeout(() => {
                remoteTypingUser.value = null
            }, 1500)

            return
        }

        if (remoteTypingTimeout) clearTimeout(remoteTypingTimeout)

        remoteTypingTimeout = setTimeout(() => {
            remoteTypingUser.value = null
        }, 800)
    })

    chatChannel.listen(".translation", async (event: any) => {
        isTranslatingAll.value = false

        await getMessages()
    })
}

const markAsRead = async () => {
    if (!chatSession.value?.ulid) return
    try {
        const requestFrom = "agent"
        await axios.post(`${baseUrl}/app/api/chats/read`, {
            session_ulid: chatSession.value.ulid,
            request_from: requestFrom,
        })
    } catch (e) {
        console.error("Failed to mark read", e)
    }
}

const onViewMessageDetails = () => {
    isMenuOpen.value = false
    emit("view-message-details")
}

watch(
    () => chatSession.value?.ulid,
    async () => {
        typingUser.value = null
        stopSocket()
        messagesLocal.value = []
        await getMessages()
        await getMediaUrl(chatSession.value!.ulid)
        initSocket()
    }
)

const sendTypingStatus = async (status: boolean) => {
    if (!chatSession.value?.ulid) return

    try {
        await axios.post(`${baseUrl}/app/api/chats/typing`, {
            session_ulid: chatSession.value.ulid,
            user_name: "agent",
            is_typing: status,
        })
    } catch (e) {
        console.error("Typing status error", e)
    }
}

const handleTyping = () => {
    if (!isTyping.value) {
        isTyping.value = true
        sendTypingStatus(true)
    }

    if (typingTimeout) clearTimeout(typingTimeout)

    typingTimeout = setTimeout(() => {
        isTyping.value = false
        sendTypingStatus(false)
    }, 500)

    typingUser.value = "agent"
}

const selectedLanguage = ref("")
const isTranslating = ref(false)

const selectedLanguageId = computed(() =>
    getLanguageIdByCode(selectedLanguage.value)
)

const translateAllMessage = async () => {
    if (!chatSession.value?.ulid || !selectedLanguageId.value) return

    isTranslating.value = true

    try {
        await axios.post(
            `${baseUrl}/app/api/chats/sessions/${chatSession.value?.ulid}/translate`,
            {
                target_language_id: selectedLanguageId.value,
            }
        )

        messagesLocal.value = []

        await getMessages()
        isTranslatingAll.value = true
    } catch (e) {
        console.error("Translate failed", e)
    } finally {
        isTranslating.value = false
    }
}

onMounted(async () => {
    await getMessages()
    await fetchLanguages()
    await getMediaUrl(chatSession.value!.ulid)
    initSocket()
    document.addEventListener("click", handleClickOutside)
})

onUnmounted(() => {
    stopSocket()
    document.removeEventListener("click", handleClickOutside)
})

watch(selectedLanguage, (code) => {
    if (!code) return
    initSocket()
    translateAllMessage()
})

const handleClickOutside = (e: MouseEvent) => {
    if (isMenuOpen.value && menuRef.value && !menuRef.value.contains(e.target as Node)) {
        isMenuOpen.value = false
    }
}
</script>

<template>
    <div class="flex flex-col h-full bg-white overflow-hidden">
        <!-- Header -->
        <header class="flex items-center gap-3 px-3 py-2 border-b bg-gray-50">
            <button @click="$emit('back')">
                <FontAwesomeIcon :icon="faArrowLeft" class="text-gray-400" />
            </button>

            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-gray-100 text-gray-500">
                <Image v-if="session?.image" :src="session?.image" class="w-full h-full rounded-full object-cover" />

                <FontAwesomeIcon v-else :icon="faUser" class="text-sm" />
            </div>

            <span
                class="flex-1 text-sm font-semibold truncate cursor-pointer primary-text hover:primary-text-hover transition-colors"
                @click="onViewMessageDetails">
                {{ session?.guest_identifier || session?.contact_name }}
            </span>

            <select v-if="languages.length" v-model="selectedLanguage" :disabled="isTranslating"
                class="h-[20px] text-[10px] px-1.5 py-0 rounded border border-gray-300 bg-white text-gray-600 leading-none focus:outline-none focus:ring-0 disabled:opacity-50">
                <option value="" disabled>
                    Translate To..
                </option>

                <option v-for="lang in languages" :key="lang.id" :value="lang.code">
                    {{ lang.native_name }}
                </option>
            </select>

            <FontAwesomeIcon v-if="isTranslating" :icon="faSpinner" class="text-gray-400 text-xs animate-spin" />

            <div class="relative" ref="menuRef">
                <button @click.stop="isMenuOpen = !isMenuOpen">
                    <FontAwesomeIcon :icon="faEllipsisVertical" class="text-gray-400" />
                </button>

                <div v-if="isMenuOpen && !isClosed"
                    class="absolute right-0 mt-2 w-56 bg-white border rounded-md shadow z-50">
                    <ModalConfirmationDelete :routeDelete="{
                        name: 'grp.org.crm.agents.sessions.close',
                        parameters: [session?.organisation.id, session?.ulid],
                        method: 'patch',
                    }" :title="trans('Are you sure you want to close this session?')"
                        @success="$emit('close-session')">
                        <template #default="{ changeModel }">
                            <button @click="changeModel" class="menu-item text-red-600">
                                <FontAwesomeIcon :icon="faTimesCircle" />
                                {{ trans("Close Chat Session") }}
                            </button>
                        </template>
                    </ModalConfirmationDelete>

                    <button class="menu-item" @click="onViewMessageDetails">
                        <FontAwesomeIcon :icon="faMessage" /> {{ trans("Message Details") }}
                    </button>
                </div>
            </div>
        </header>

        <div v-if="isTranslatingAll" class="sticky top-0 z-10 bg-white/90 backdrop-blur
            border-b border-gray-200 px-4 py-3">

            <div class="flex items-center justify-center gap-3 text-sm text-gray-600">
                <LoadingIcon class="w-4 h-4 animate-spin" />
                <div class="flex flex-col leading-tight">
                    <span class="font-medium">Updating translations</span>
                    <span class="text-xs text-gray-400">
                        Messages will refresh automatically
                    </span>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto px-3 py-2 space-y-3 bg-[#f6f6f7]">
            <div class="flex justify-center" v-if="canLoadMore && nextCursor">
                <button @click="getMessages(true)" :disabled="isLoadingMore" class="flex items-center gap-2 text-xs text-gray-600 px-4 py-1.5
               border rounded-full hover:bg-gray-100 disabled:opacity-50">
                    <FontAwesomeIcon v-if="isLoadingMore" :icon="faSpinner" class="animate-spin text-[10px]" />
                    <span>
                        {{ isLoadingMore ? 'Loading messages…' : 'Load older messages' }}
                    </span>
                </button>
            </div>

            <template v-for="(msgs, date) in groupedMessages" :key="date">
                <div class="text-center text-xs text-gray-400">{{ date }}</div>
                <div v-for="msg in msgs" :key="msg.id" class="flex"
                    :class="msg.sender_type === 'agent' ? 'justify-end' : 'justify-start'">
                    <BubbleChat :message="msg" viewerType="agent" />
                </div>
            </template>
        </div>
        <div v-if="remoteTypingUser" class="text-xs text-gray-400 italic px-2 py-1">
            {{ remoteTypingUser }} {{ trans("is typing...") }}
        </div>

        <div v-if="previewType === 'image' && previewUrl" class="px-3 pb-2">
            <div class="relative inline-block">
                <img :src="previewUrl" class="h-24 rounded-lg border object-cover" />
                <button @click="removeFile" class="absolute -top-2 -right-2 bg-white rounded-full shadow p-1">
                    <FontAwesomeIcon :icon="faXmark" />
                </button>
            </div>
        </div>

        <div v-if="previewType === 'file' && selectedFile" class="px-3 pb-2">
            <div class="flex items-center gap-3 border rounded-lg p-3 bg-gray-50 min-w-0">
                <div class="text-2xl">
                    <FontAwesomeIcon :icon="faFilePdf" />
                </div>
                <div class="flex-1 min-w-0 overflow-hidden">
                    <div class="text-sm font-medium truncate">
                        {{ selectedFile.name }}
                    </div>
                    <div class="text-xs text-gray-400">
                        {{ (selectedFile.size / 1024).toFixed(1) }} KB
                    </div>
                </div>
                <button @click="removeFile" class="text-gray-400 hover:text-red-500 shrink-0 ml-2">
                    <FontAwesomeIcon :icon="faXmark" />
                </button>
            </div>
        </div>

        <!-- Footer -->
        <footer v-if="!isClosed" class="flex items-center gap-2 px-3 py-2 border-t bg-white">
            <button @click="imageInput?.click()"
                class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100" title="Upload image">
                <FontAwesomeIcon :icon="faImage" />
            </button>

            <button @click="fileInput?.click()"
                class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100" title="Upload file">
                <FontAwesomeIcon :icon="faPaperclip" />
            </button>

            <input ref="imageInput" type="file" accept=".webp,.jpg,.jpeg,.png,.avif" class="hidden"
                @change="handleImageSelect" />

            <input ref="fileInput" type="file" accept=".pdf,.xls,.xlsx" class="hidden" @change="handleDocSelect" />

            <textarea ref="messageInput" v-model="newMessage" @input="
                () => {
                    autoResize()
                    handleTyping()
                }
            " @blur="
                () => {
                    isTyping = false
                    sendTypingStatus(false)
                }
            " @keydown.enter.exact.prevent="sendMessage" rows="1" placeholder="Type message..."
                class="flex-1 resize-none border rounded-lg px-3 py-2 text-sm leading-5 focus:outline-none" />

            <Button @click="sendMessage" :icon="faPaperPlane"></Button>
        </footer>
    </div>
</template>
<style scoped>
.menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    width: 100%;
    font-size: 14px;
}

.menu-item:hover {
    background: #f3f4f6;
}

::-webkit-scrollbar {
    width: 5px;
}

::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 4px;
}
</style>
