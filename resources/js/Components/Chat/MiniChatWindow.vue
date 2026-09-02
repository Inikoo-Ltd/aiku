<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick, defineAsyncComponent } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faXmark,
    faChevronDown,
    faPaperPlane,
    faArrowUpRightFromSquare,
    faImage,
    faPaperclip,
    faFileLines,
    faTimesCircle,
    faRotateRight,
    faFaceSmile,
    faEllipsisVertical,
    faSpinner,
    faEnvelope,
} from "@fortawesome/free-solid-svg-icons"
import { faJira } from "@fortawesome/free-brands-svg-icons"
import { faUser } from "@fal"
import { faCheck, faCheckDouble, faExclamationCircle } from "@far"
import { notify } from "@kyvg/vue3-notification"
import { useLayoutStore } from "@/Stores/layout"
import Image from "@common/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ChatTimelineEvent from "@/Components/Chat/ChatTimelineEvent.vue"
import JiraTicketModal from "@/Components/Chat/Agent/JiraTicketModal.vue"
import { Dialog } from "primevue"
import type { MiniChat } from "@/Composables/useMiniChats"

const EmojiPicker = defineAsyncComponent(() => import("@/Components/Messaging/EmojiPicker.vue"))

type LocalMessageStatus = "sending" | "sent" | "failed"

type LocalChatMessage = Record<string, any> & {
    _status?: LocalMessageStatus
    _tempId?: string
}

const props = defineProps<{ chat: MiniChat }>()

const emit = defineEmits(["close", "toggle", "read"])

const layout: any = useLayoutStore()
const baseUrl = layout?.appUrl ?? ""

// The two channels store their conversations in separate tables behind separate
// endpoints, so every call the window makes has to pick the matching one.
const isWhatsapp = computed(() => props.chat.channel === "whatsapp")

const sessionApiBase = computed(() =>
    `${baseUrl}/app/api/chats${isWhatsapp.value ? "/meta" : ""}/sessions/${props.chat.ulid}`
)

const messages = ref<LocalChatMessage[]>([])
const hasMore = ref(false)
const nextCursor = ref<string | null>(null)
const isLoadingOlder = ref(false)
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
const isJiraModalOpen = ref(false)
const isEmailNotif = ref(false)

const showEmojiPicker = ref(false)
const emojiButtonRef = ref<HTMLElement | null>(null)
const emojiPickerRef = ref<HTMLElement | null>(null)
const emojiPickerStyle = ref<Record<string, string>>({})

const showHeaderMenu = ref(false)
const headerMenuRef = ref<HTMLElement | null>(null)

const canSendNonTemplate = ref<boolean | undefined>(undefined)
const templateOnly = computed(() => isWhatsapp.value && canSendNonTemplate.value === false)
const isTemplateDialogOpen = ref(false)
const templates = ref<any[]>([])
const isLoadingTemplates = ref(false)
const selectedTemplate = ref<any>(null)
const hoveredTemplate = ref<any>(null)
const templateParameters = ref<string[]>([])
const popupStyle = ref<Record<string, string>>({})
const hasTemplate = computed(() => !!selectedTemplate.value)
const templateAutoFills = computed(() => !!selectedTemplate.value?.auto_fill)
const templateMissingTags = computed(() => selectedTemplate.value?.missing_tags ?? [])

const templatePreview = computed(() => {
    if (!selectedTemplate.value) return ""
    if (selectedTemplate.value.auto_fill) return selectedTemplate.value.preview ?? selectedTemplate.value.body
    let body = selectedTemplate.value.body
    templateParameters.value.forEach((parameter, index) => {
        if (parameter) {
            body = body.replaceAll(`{{${index + 1}}}`, parameter)
        }
    })
    return body
})

const canSendTemplate = computed(() => {
    if (!selectedTemplate.value) return false
    if (selectedTemplate.value.auto_fill) {
        return templateMissingTags.value.length === 0
    }
    return templateParameters.value.every((p) => p.trim() !== "")
})

const IMAGE_TYPES = ["image/webp", "image/jpeg", "image/jpg", "image/png", "image/avif"]

const FILE_TYPES = [
    "application/pdf",
    "application/vnd.ms-excel",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
]

const MAX_SIZE = 10 * 1024 * 1024

const waReadIcon = (message: LocalChatMessage) => {
    const waStatus = message.metadata?.wa_status
    if (waStatus === 'failed') return faExclamationCircle
    if (waStatus === 'delivered' || waStatus === 'read') return faCheckDouble
    if (waStatus === 'sent') return faCheck
    return message.is_read ? faCheckDouble : faCheck
}

const waReadIconClass = (message: LocalChatMessage): string => {
    const waStatus = message.metadata?.wa_status
    if (waStatus === 'failed') return 'text-red-500'
    if (waStatus === 'read') return 'text-sky-400'
    return ''
}

const isClosed = computed(() => props.chat.status === "closed")
const isWaiting = computed(() => props.chat.status === "waiting")
const canSend = computed(() => !isClosed.value && !isWaiting.value && !templateOnly.value)
const hasAttachment = computed(() => !!selectedFile.value)

const isEndingChat = ref(false)
const showEndConfirm = ref(false)
const endChat = async () => {
    if (isEndingChat.value) return
    isEndingChat.value = true
    try {
        const routeName = isWhatsapp.value
            ? "grp.org.chat.agents.whatsapp.sessions.close"
            : "grp.org.chat.agents.sessions.close"
        const url = route(routeName, [props.chat.organisationSlug, props.chat.ulid])
        await axios.patch(url, {}, {
            withCredentials: true,
            headers: { Accept: "application/json" },
        })
    } catch (e: any) {
        const status = e?.response?.status ?? 0
        if (status >= 400) {
            notify({
                title: trans("Error"),
                text: e?.response?.data?.message ?? trans("Failed to end chat"),
                type: "error",
            })
            isEndingChat.value = false
            return
        }
    }
    props.chat.status = "closed"
    showHeaderMenu.value = false
    showEndConfirm.value = false
    await getMessages()
    isEndingChat.value = false
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

const isReopening = ref(false)
const reopenChat = async () => {
    if (!props.chat.organisationSlug || !props.chat.ulid || isReopening.value) return
    isReopening.value = true
    try {
        const routeName = isWhatsapp.value
            ? "grp.org.chat.agents.whatsapp.sessions.reopen"
            : "grp.org.chat.agents.sessions.reopen"
        await axios.patch(
            route(routeName, [props.chat.organisationSlug, props.chat.ulid]),
            {},
            { withCredentials: true }
        )
        props.chat.status = "active"
        await getMessages()
    } catch (e: any) {
        notify({
            title: trans("Error"),
            text: e?.response?.data?.message ?? trans("Failed to reopen chat"),
            type: "error",
        })
    } finally {
        isReopening.value = false
    }
}

const jiraSession = computed(() => ({
    ulid: props.chat.ulid,
    contact_name: props.chat.contactName,
}))

const messagesReady = ref(false)

const scrollBottom = (instant = false) =>
    nextTick(() => {
        const el = messagesContainer.value
        if (!el) return
        el.scrollTop = el.scrollHeight
        if (!messagesReady.value) {
            messagesReady.value = true
        }
        if (!instant) {
            setTimeout(() => { if (el) el.scrollTop = el.scrollHeight }, 350)
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
            `${sessionApiBase.value}/messages`,
            { params: { limit: 20, request_from: "agent" } }
        )
        messages.value = (data?.data?.messages ?? []).map((message: any) => ({
            ...message,
            _status: "sent",
        }))

        hasMore.value = data?.data?.pagination?.has_more ?? false
        nextCursor.value = data?.data?.pagination?.next_cursor ?? null

        if (data?.data?.can_send_non_template_message !== undefined) {
            canSendNonTemplate.value = data.data.can_send_non_template_message
        }

        const serverStatus = data?.data?.session_status
        if (serverStatus) {
            props.chat.status = serverStatus
        }

        scrollBottom()
    } catch (e) {
        console.error("Failed to fetch mini chat messages", e)
    } finally {
        isLoading.value = false
    }
}

const loadOlderMessages = async () => {
    if (isLoadingOlder.value || !hasMore.value || !nextCursor.value) return
    isLoadingOlder.value = true

    const container = messagesContainer.value
    const prevHeight = container?.scrollHeight ?? 0

    try {
        const { data } = await axios.get(
            `${sessionApiBase.value}/messages`,
            { params: { limit: 20, cursor: nextCursor.value, request_from: "agent" } }
        )
        const older = (data?.data?.messages ?? []).map((message: any) => ({
            ...message,
            _status: "sent",
        }))

        const existingIds = new Set(messages.value.map((m) => m.id))
        const newMessages = older.filter((m: any) => !existingIds.has(m.id))
        messages.value = [...newMessages, ...messages.value]

        hasMore.value = data?.data?.pagination?.has_more ?? false
        nextCursor.value = data?.data?.pagination?.next_cursor ?? null

        nextTick(() => {
            if (container) {
                container.scrollTop = container.scrollHeight - prevHeight
            }
        })
    } catch (e) {
        console.error("Failed to load older messages", e)
    } finally {
        isLoadingOlder.value = false
    }
}

const markAsRead = async () => {
    try {
        if (isWhatsapp.value) {
            await axios.post(`${sessionApiBase.value}/read`)
        } else {
            await axios.post(`${baseUrl}/app/api/chats/read`, {
                session_ulid: props.chat.ulid,
                request_from: "agent",
            })
        }
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

const toggleEmojiPicker = () => {
    showEmojiPicker.value = !showEmojiPicker.value
    if (showEmojiPicker.value && emojiButtonRef.value) {
        const rect = emojiButtonRef.value.getBoundingClientRect()
        const pickerWidth = 288
        const pickerHeight = 320
        let left = rect.left
        if (left + pickerWidth > window.innerWidth) {
            left = window.innerWidth - pickerWidth - 8
        }
        left = Math.max(8, left)
        emojiPickerStyle.value = {
            position: 'fixed',
            bottom: `${window.innerHeight - rect.top + 4}px`,
            left: `${left}px`,
            zIndex: '9999',
        }
    }
}

const pickEmoji = (emoji: string) => {
    const el = messageInput.value
    if (!el) {
        newMessage.value += emoji
        return
    }
    const start = el.selectionStart ?? newMessage.value.length
    const end = el.selectionEnd ?? newMessage.value.length
    newMessage.value = newMessage.value.slice(0, start) + emoji + newMessage.value.slice(end)
    nextTick(() => {
        el.focus()
        const pos = start + emoji.length
        el.setSelectionRange(pos, pos)
        autoResize()
    })
}

const openTemplateDialog = async () => {
    if (!props.chat.shopId) return
    isTemplateDialogOpen.value = true
    isLoadingTemplates.value = true
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/meta/templates`, {
            params: { shop_id: props.chat.shopId, session_ulid: props.chat.ulid },
        })
        templates.value = data?.data ?? []
    } catch {
        notify({ title: trans("Error"), text: trans("Failed to load templates"), type: "error" })
    } finally {
        isLoadingTemplates.value = false
    }
}

const POPUP_WIDTH = 288
const showTemplatePopup = (template: any, event: MouseEvent) => {
    const row = (event.currentTarget as HTMLElement).getBoundingClientRect()
    const fitsRight = row.right + 12 + POPUP_WIDTH <= window.innerWidth
    popupStyle.value = {
        top: `${Math.min(row.top, window.innerHeight - 240)}px`,
        left: fitsRight ? `${row.right + 12}px` : `${Math.max(8, row.left - 12 - POPUP_WIDTH)}px`,
    }
    hoveredTemplate.value = template
}

const selectTemplate = (template: any) => {
    selectedTemplate.value = template
    templateParameters.value = template.auto_fill ? [] : Array(template.parameter_count).fill("")
    hoveredTemplate.value = null
    isTemplateDialogOpen.value = false
    newMessage.value = ""
    clearAttachment()
}

const clearTemplate = () => {
    selectedTemplate.value = null
    templateParameters.value = []
}

const handleClickOutside = (event: MouseEvent) => {
    if (showEmojiPicker.value) {
        const target = event.target as Node
        const insideButton = emojiButtonRef.value?.contains(target)
        const insidePicker = emojiPickerRef.value?.contains(target)
        if (!insideButton && !insidePicker) {
            showEmojiPicker.value = false
        }
    }
    if (showHeaderMenu.value && headerMenuRef.value && !headerMenuRef.value.contains(event.target as Node)) {
        showHeaderMenu.value = false
    }
}

const sendTemplateMessage = async () => {
    if (!selectedTemplate.value || !canSendTemplate.value) return

    const tempId = `tmp-${Date.now()}`
    messages.value.push({
        id: tempId,
        _tempId: tempId,
        message_text: templatePreview.value,
        sender_type: "agent",
        message_type: "text",
        created_at: new Date().toISOString(),
        _status: "sending",
    })

    isSending.value = true
    scrollBottom()

    const formData = new FormData()
    formData.append("template_name", selectedTemplate.value.name)
    formData.append("template_language", selectedTemplate.value.language)
    templateParameters.value.forEach((parameter, index) => {
        formData.append(`template_parameters[${index}]`, parameter)
    })

    clearTemplate()

    try {
        await axios.post(
            route("grp.org.chat.agents.whatsapp.messages.send", [props.chat.organisationSlug, props.chat.ulid]),
            formData,
            { headers: { "Content-Type": "multipart/form-data" } }
        )
    } catch (error: any) {
        const failed = messages.value.find((m) => m._tempId === tempId)
        if (failed) failed._status = "failed"
        notify({
            title: trans("Error"),
            text: error?.response?.data?.message ?? trans("Failed to send template"),
            type: "error",
        })
    } finally {
        isSending.value = false
    }
}

const sendMessage = async () => {
    if (isSending.value) return

    if (hasTemplate.value) {
        await sendTemplateMessage()
        return
    }

    if (templateOnly.value) {
        notify({
            title: trans("Error"),
            text: trans("The customer has not messaged in the last 24 hours. Only template messages can be sent."),
            type: "error",
        })
        return
    }

    const text = newMessage.value.trim()
    const file = selectedFile.value

    if ((!text && !file) || !canSend.value) return

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

        if (!isWhatsapp.value) {
            formData.append("is_email_notif", String(isEmailNotif.value))
        }

        if (file) {
            formData.append(messageType === "image" ? "image" : "file", file)
        }

        await axios.post(
            route(
                isWhatsapp.value
                    ? "grp.org.chat.agents.whatsapp.messages.send"
                    : "grp.org.chat.agents.messages.send",
                [props.chat.organisationSlug, props.chat.ulid]
            ),
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
    if (isWhatsapp.value) return

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

    chatChannel = isWhatsapp.value
        ? window.Echo.private(`meta-chat-session.${props.chat.ulid}`)
        : window.Echo.channel(`chat-session.${props.chat.ulid}`)

    chatChannel.listen(".message", ({ message, session_status }: any) => {
        messages.value = messages.value.filter(
            (item) => !(item._status === "sending" && item.sender_type === "agent")
        )

        const index = messages.value.findIndex((item) => item.id === message.id)

        if (index !== -1) {
            messages.value[index] = { ...messages.value[index], ...message, _status: "sent" }
        } else {
            messages.value.push({ ...message, _status: "sent" })
        }

        if (session_status) {
            props.chat.status = typeof session_status === "object" ? session_status.value : session_status
        }

        if (message.sender_type !== "agent" && message.sender_type !== "system") {
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
    if (!props.chat.organisationSlug) return

    // WhatsApp has no standalone conversation page yet, so it opens the inbox on the
    // right shop and channel instead.
    if (isWhatsapp.value) {
        if (!props.chat.shopSlug) return

        router.visit(
            route("grp.org.shops.show.chat.inbox", [props.chat.organisationSlug, props.chat.shopSlug])
            + `?channel=whatsapp&session=${props.chat.ulid}`
        )

        return
    }

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
    document.addEventListener("click", handleClickOutside)
    await getMessages()
    initSocket()
    await markAsRead()
})

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutside)
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

            <div ref="headerMenuRef" class="relative shrink-0">
                <button class="w-5 h-5 rounded hover:bg-gray-100 text-gray-400 hover:text-gray-600"
                    v-tooltip="trans('More options')" @click.stop="showHeaderMenu = !showHeaderMenu">
                    <FontAwesomeIcon :icon="faEllipsisVertical" class="text-[9px]" />
                </button>

                <div v-if="showHeaderMenu" @click.stop
                    class="absolute right-0 top-full mt-0.5 w-36 py-1 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                    <button v-if="chat.organisationSlug" type="button"
                        class="w-full flex items-center gap-2 px-2 py-1 text-[10px] text-gray-700 hover:bg-gray-100"
                        @click="showHeaderMenu = false; isJiraModalOpen = true">
                        <FontAwesomeIcon :icon="faJira" class="text-[9px] text-gray-400" />
                        {{ trans('Create Jira ticket') }}
                    </button>

                    <template v-if="!isClosed && !isWaiting">
                        <div class="my-0.5 border-t border-gray-100"></div>
                        <template v-if="!showEndConfirm">
                            <button type="button"
                                class="w-full flex items-center gap-2 px-2 py-1 text-[10px] text-red-600 hover:bg-red-50"
                                @click="showEndConfirm = true">
                                <FontAwesomeIcon :icon="faTimesCircle" class="text-[9px]" />
                                {{ trans('End chat') }}
                            </button>
                        </template>
                        <template v-else>
                            <div class="px-2 py-1 space-y-1">
                                <p class="text-[10px] text-gray-600">{{ trans('End this chat session?') }}</p>
                                <div class="flex gap-1">
                                    <button type="button"
                                        class="flex-1 text-[10px] py-0.5 rounded bg-red-500 text-white hover:bg-red-600 disabled:opacity-60"
                                        :disabled="isEndingChat" @click="endChat">
                                        <LoadingIcon v-if="isEndingChat" class="w-2.5 h-2.5 inline" />
                                        {{ trans('Yes') }}
                                    </button>
                                    <button type="button"
                                        class="flex-1 text-[10px] py-0.5 rounded bg-gray-100 text-gray-600 hover:bg-gray-200"
                                        @click="showEndConfirm = false">
                                        {{ trans('No') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                    </template>
                </div>
            </div>

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
                class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden px-2 py-1.5 space-y-1 bg-[#F0F4F8]"
                :class="{ 'invisible': !isLoading && sortedMessages.length > 0 && !messagesReady }">
                <div v-if="isLoading" class="h-full flex items-center justify-center">
                    <LoadingIcon class="w-4 h-4 text-gray-400" />
                </div>

                <div v-else-if="sortedMessages.length === 0"
                    class="h-full flex items-center justify-center text-[11px] text-gray-500">
                    {{ trans('No messages yet') }}
                </div>

                <template v-else>
                    <div v-if="hasMore" class="flex justify-center py-1">
                        <button type="button"
                            class="text-[10px] text-gray-500 hover:text-gray-700 border border-gray-300 rounded-full px-3 py-0.5 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="isLoadingOlder" @click="loadOlderMessages">
                            <LoadingIcon v-if="isLoadingOlder" class="w-3 h-3 inline mr-1" />
                            {{ trans('Load older messages') }}
                        </button>
                    </div>
                    <template v-for="message in sortedMessages" :key="message.id">
                        <ChatTimelineEvent
                            v-if="message.sender_type === 'system'"
                            :event="{ description: trans(message.message_text), created_at: message.created_at }"
                        />

                        <div v-else class="flex"
                            :class="['guest', 'user'].includes(message.sender_type) ? 'justify-start' : 'justify-end'">
                            <div class="max-w-[85%] min-w-0 px-2 py-1 rounded-lg text-[11px] leading-snug shadow-sm"
                                :class="['guest', 'user'].includes(message.sender_type)
                                    ? 'bg-white text-gray-800 rounded-bl-sm'
                                    : 'bg-indigo-500 text-white rounded-br-sm'">
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
                                    :class="['guest', 'user'].includes(message.sender_type) ? 'text-gray-400' : 'text-white/70'">
                                    <span>{{ formatTime(message.created_at) }}</span>
                                    <LoadingIcon v-if="message._status === 'sending'" class="w-2.5 h-2.5" />
                                    <span v-else-if="message._status === 'failed'" class="text-red-300">
                                        <FontAwesomeIcon :icon="faExclamationCircle" class="text-[9px]" />
                                    </span>
                                    <span v-else-if="!['guest', 'user'].includes(message.sender_type)" class="leading-none">
                                        <FontAwesomeIcon
                                            :icon="waReadIcon(message)"
                                            class="text-[9px]"
                                            :class="waReadIconClass(message)" />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </template>
            </div>

            <div v-if="remoteTypingUser" class="px-2 py-0.5 text-[10px] text-gray-400 italic truncate shrink-0">
                {{ remoteTypingUser }} {{ trans('is typing...') }}
            </div>

            <div v-if="isClosed" class="px-2 py-1.5 border-t border-gray-200 shrink-0 space-y-1.5">
                <span class="block text-center text-[10px] text-gray-500">{{ trans('This chat has been closed') }}</span>
                <button type="button"
                    class="w-full flex items-center justify-center gap-1.5 h-7 rounded-md text-[11px] font-medium text-white transition hover:opacity-90 disabled:opacity-60"
                    :style="{ backgroundColor: 'var(--theme-color-4)' }"
                    :disabled="isReopening" @click="reopenChat">
                    <LoadingIcon v-if="isReopening" class="w-3 h-3" />
                    <FontAwesomeIcon v-else :icon="faRotateRight" class="text-[9px]" />
                    {{ trans('Reopen chat') }}
                </button>
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
                <div class="border-t border-gray-200 shrink-0">
                    <input ref="imageInput" type="file" :accept="isWhatsapp ? '.jpg,.jpeg,.png' : '.webp,.jpg,.jpeg,.png,.avif'" class="hidden"
                        @change="handleImageSelect" />
                    <input ref="fileInput" type="file" :accept="isWhatsapp ? '.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx' : '.pdf,.xls,.xlsx'" class="hidden"
                        @change="handleDocSelect" />

                    <div v-if="templateOnly && !hasTemplate"
                        class="flex items-center gap-1.5 mx-2 mt-1.5 px-2 py-1 rounded bg-amber-50 border border-amber-200 text-amber-700 text-[9px]">
                        <FontAwesomeIcon :icon="faFileLines" class="text-[8px]" />
                        <span>{{ trans('24h window closed. Send a template.') }}</span>
                    </div>

                    <div v-if="hasTemplate" class="mx-2 mt-1.5">
                        <div class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-50 text-green-700 text-[10px]">
                            <FontAwesomeIcon :icon="faFileLines" class="text-[9px]" />
                            <span class="font-medium truncate">{{ selectedTemplate?.name }}</span>
                            <span class="text-green-600/70 text-[9px]">{{ selectedTemplate?.language }}</span>
                            <button @click="clearTemplate" class="ml-auto text-green-600 hover:text-red-500">
                                <FontAwesomeIcon :icon="faXmark" class="text-[8px]" />
                            </button>
                        </div>
                        <div class="px-1 pt-1 text-[10px] text-gray-500 whitespace-pre-line max-h-16 overflow-y-auto leading-snug">
                            {{ templatePreview }}
                        </div>
                        <div v-if="templateAutoFills && templateMissingTags.length"
                            class="mt-1 px-2 py-1 rounded bg-amber-50 border border-amber-200 text-amber-700 text-[9px]">
                            {{ trans("Cannot send yet — no value for:") }} {{ templateMissingTags.join(", ") }}
                        </div>
                        <div v-if="hasTemplate && !templateAutoFills && templateParameters.length"
                            class="mt-1 space-y-1">
                            <input v-for="(_, index) in templateParameters" :key="index"
                                v-model="templateParameters[index]" type="text"
                                :placeholder="trans('Value for :placeholder', { placeholder: `{{${index + 1}}}` })"
                                class="w-full text-[10px] border rounded px-2 py-1 focus:outline-none focus:border-green-500" />
                        </div>
                    </div>

                    <div v-if="previewType === 'image' && previewUrl" class="px-2 pt-1.5">
                        <div class="relative inline-block">
                            <img :src="previewUrl" class="h-12 rounded border border-gray-200 object-cover" />
                            <button type="button"
                                class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-white shadow border border-gray-200 text-gray-500 hover:text-red-500"
                                @click="clearAttachment()">
                                <FontAwesomeIcon :icon="faXmark" class="text-[8px]" />
                            </button>
                        </div>
                    </div>

                    <div v-else-if="previewType === 'file' && selectedFile" class="px-2 pt-1.5">
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

                    <textarea v-if="!hasTemplate" ref="messageInput" v-model="newMessage" rows="1"
                        :disabled="templateOnly"
                        :placeholder="templateOnly ? trans('Send a template message') : trans('Type message...')"
                        style="max-height: 96px;"
                        class="w-full resize-none overflow-y-auto text-[11px] leading-tight px-2 pt-1.5 pb-0.5 border-none focus:outline-none focus:ring-0 bg-transparent disabled:bg-gray-50 disabled:text-gray-400"
                        @input="handleTyping(); autoResize()" @keydown.enter.exact.prevent="sendMessage" />

                    <div class="flex items-center justify-between px-1.5 pb-1.5 pt-0.5">
                        <div class="flex items-center gap-0.5">
                            <button type="button" @click="imageInput?.click()" :disabled="hasTemplate || templateOnly"
                                class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-500 transition-colors disabled:opacity-40"
                                :title="trans('Upload image')">
                                <FontAwesomeIcon :icon="faImage" class="text-[10px]" />
                            </button>
                            <button type="button" @click="fileInput?.click()" :disabled="hasTemplate || templateOnly"
                                class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-500 transition-colors disabled:opacity-40"
                                :title="trans('Upload file')">
                                <FontAwesomeIcon :icon="faPaperclip" class="text-[10px]" />
                            </button>
                            <div class="relative">
                                <button ref="emojiButtonRef" type="button" @click.stop="toggleEmojiPicker"
                                    :disabled="hasTemplate || templateOnly"
                                    class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 transition-colors disabled:opacity-40"
                                    :class="showEmojiPicker ? 'text-green-600 bg-gray-100' : 'text-gray-500'"
                                    :title="trans('Emoji')">
                                    <FontAwesomeIcon :icon="faFaceSmile" class="text-[10px]" />
                                </button>
                                <Teleport to="body">
                                    <div v-if="showEmojiPicker" ref="emojiPickerRef" :style="emojiPickerStyle"
                                        @click.stop>
                                        <EmojiPicker @pick="pickEmoji" />
                                    </div>
                                </Teleport>
                            </div>
                            <button v-if="!isWhatsapp" type="button" @click="isEmailNotif = !isEmailNotif"
                                class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 transition-colors"
                                :class="isEmailNotif ? 'text-green-600 bg-green-50' : 'text-gray-500'"
                                :title="isEmailNotif ? trans('Email notification ON') : trans('Email notification OFF')">
                                <FontAwesomeIcon :icon="faEnvelope" class="text-[10px]" />
                            </button>
                            <button v-if="isWhatsapp" type="button" @click="openTemplateDialog"
                                class="w-6 h-6 flex items-center justify-center rounded hover:bg-green-50 text-gray-500 hover:text-green-600 transition-colors"
                                :title="trans('Send template message')">
                                <FontAwesomeIcon :icon="faFileLines" class="text-[10px]" />
                            </button>
                        </div>
                        <button
                            class="w-6 h-6 shrink-0 rounded-md flex items-center justify-center text-white disabled:opacity-50"
                            :style="{ backgroundColor: 'var(--theme-color-4)' }"
                            :disabled="isSending || (hasTemplate ? !canSendTemplate : (templateOnly || (!newMessage.trim() && !hasAttachment)))"
                            @click="sendMessage">
                            <FontAwesomeIcon :icon="faPaperPlane" class="text-[9px]" />
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <JiraTicketModal v-if="chat.organisationSlug" :is-open="isJiraModalOpen" :session="(jiraSession as any)"
            :organisation="chat.organisationSlug" @close="isJiraModalOpen = false" />

        <Dialog v-if="isWhatsapp" v-model:visible="isTemplateDialogOpen" modal :header="trans('WhatsApp templates')"
            :style="{ width: '24rem' }">
            <div v-if="isLoadingTemplates" class="flex items-center justify-center py-8 text-gray-400">
                <FontAwesomeIcon :icon="faSpinner" class="animate-spin" />
            </div>
            <div v-else-if="!templates.length" class="py-8 text-center text-sm text-gray-400">
                {{ trans('No approved templates found for this shop') }}
            </div>
            <div v-else class="space-y-3">
                <div class="max-h-48 overflow-y-auto border rounded-lg divide-y" @scroll="hoveredTemplate = null">
                    <button v-for="template in templates" :key="template.id"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50 transition-colors"
                        :class="{ 'bg-green-50': selectedTemplate?.id === template.id }"
                        @mouseenter="showTemplatePopup(template, $event)" @mouseleave="hoveredTemplate = null"
                        @click="selectTemplate(template)">
                        <div class="text-sm font-medium">{{ template.name }}</div>
                        <div class="text-[11px] text-gray-400">
                            {{ template.language }}<span v-if="template.category"> &middot; {{ template.category }}</span>
                        </div>
                    </button>
                </div>
            </div>
            <Teleport to="body">
                <div v-if="hoveredTemplate" :style="popupStyle"
                    class="fixed z-[2000] w-72 rounded-lg border bg-white p-3 shadow-xl pointer-events-none">
                    <div class="text-sm font-medium mb-1">{{ hoveredTemplate.name }}</div>
                    <div class="text-xs text-gray-600 whitespace-pre-line max-h-60 overflow-y-auto">
                        {{ hoveredTemplate.body }}
                    </div>
                    <div class="mt-2 pt-2 border-t text-[10px] text-gray-400">
                        {{ hoveredTemplate.language }}<span v-if="hoveredTemplate.category"> &middot; {{ hoveredTemplate.category }}</span>
                        &middot; {{ trans(':count parameters', { count: hoveredTemplate.parameter_count }) }}
                    </div>
                </div>
            </Teleport>
        </Dialog>
    </div>
</template>
