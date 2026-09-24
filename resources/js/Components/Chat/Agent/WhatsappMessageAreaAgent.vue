<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, inject, computed, nextTick, defineAsyncComponent } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { chatSendErrorText } from "@/Composables/chatSendError"
import { useUploadLimits } from "@/Composables/useUploadLimits"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useComposerDraft } from "@/Composables/useComposerDraft"
import {
    faPaperPlane,
    faArrowLeft,
    faImage,
    faPaperclip,
    faXmark,
    faFilePdf,
    faFileLines,
    faCircleQuestion,
    faTimesCircle,
    faRotateRight,
    faFaceSmile,
    faLifeRing,
    faEye,
    faBan,
} from "@fortawesome/free-solid-svg-icons"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { formatChatTime, formatChatAge } from "@/Composables/chatTime"
import type { ChatMessage, SessionAPI } from "@/types/Chat/chat"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ChatAiDraftBox from "@/Components/Chat/Agent/ChatAiDraftBox.vue"
import Image from "@common/Components/Image.vue"
import { faUser, faSpinner } from "@far"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"
import ChatFormattingToolbar from "@/Components/Chat/ChatFormattingToolbar.vue"
import ChatMessageEditor from "@/Components/Chat/ChatMessageEditor.vue"
import { useJumpToMessage } from "@/Composables/useJumpToMessage"
import ChatTimelineEvent from "@/Components/Chat/ChatTimelineEvent.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"

const EmojiPicker = defineAsyncComponent(() => import("@/Components/Messaging/EmojiPicker.vue"))
import { notify } from "@kyvg/vue3-notification"
import WhatsappTemplatePicker from "@/Components/Chat/WhatsappTemplatePicker.vue"
import TicketModal from "@/Components/Chat/Agent/TicketModal.vue"
import WhatsappCallBar from "@/Components/Chat/WhatsappCallBar.vue"
import { useWhatsappCall } from "@/Composables/useWhatsappCall"

type LocalMessageStatus = "sending" | "sent" | "failed"

type LocalChatMessage = ChatMessage & {
    _status?: LocalMessageStatus
    _tempId?: string
    metadata?: Record<string, any>
    message_type?: string
    file_name?: string | null
    replied_to?: {
        id: number
        message_text?: string | null
        message_type?: string
        sender_type?: string
        file_name?: string | null
    } | null
}

interface WhatsappTemplate {
    id: number
    name: string
    language: string
    category: string | null
    body: string
    parameter_count: number
    merge_tags?: string[]
    auto_fill?: boolean
    missing_tags?: string[]
    resolved_values?: (string | null)[]
    preview?: string | null
}

const props = defineProps<{
    messages: ChatMessage[]
    session: SessionAPI | null
    organisationSlug: string
    readOnly?: boolean
    showShop?: boolean
}>()

const isCustomer = computed(() => Boolean((props.session as any)?.customer_id || (props.session as any)?.web_user?.customer_id))

// When the last word was said, and how long ago: a waiting conversation is judged by its age.
const lastMessageStamp = computed(() => {
    const at = props.messages?.[props.messages.length - 1]?.created_at

    if (!at) {
        return null
    }

    const stamp = new Date(at).getTime()

    return { time: formatChatTime(stamp), age: formatChatAge(stamp) }
})

const emit = defineEmits(["back", "messages-read", "assign-self-success", "close-session", "view-profile", "spam-success"])

const isSpamMarking = ref(false)

const { applyBroadcast: applyCallBroadcast } = useWhatsappCall()

const markSpam = async () => {
    if (!props.session?.ulid || isSpamMarking.value) return

    isSpamMarking.value = true

    try {
        await axios.patch(route("grp.org.chat.agents.whatsapp.sessions.spam", [props.organisationSlug, props.session.ulid]))
        emit("spam-success")
    } catch (e: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: e?.response?.data?.message ?? ctrans("Failed to update spam status"),
            type: "error",
        })
    } finally {
        isSpamMarking.value = false
    }
}

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const isTicketModalOpen = ref(false)

const chatSession = computed(() => props.session)
const isClosed = computed(() => chatSession.value?.status === "closed")
const isWaiting = computed(() => !chatSession.value?.assigned_agent)
const isMyChat = computed(() => {
    if (!chatSession.value?.assigned_agent) return true
    return String(chatSession.value.assigned_agent.user_id ?? "") === String(layout?.user?.id ?? "")
})

// Disposing of a chat another agent is holding is theirs to do. The server decides, since
// supervisors keep the override and the page cannot know who supervises what.
const canDispose = computed(() => {
    const flag = (chatSession.value as any)?.can_dispose
    return typeof flag === "boolean" ? flag : isMyChat.value
})

// Spam is never offered on a customer: it blocks the number for good.
const canReportSpam = computed(() =>
    !(chatSession.value as any)?.customer?.id && !isClosed.value && !(chatSession.value as any)?.is_spam && !props.readOnly && canDispose.value
)

const isAssigningSelf = ref(false)
const isTakingOver = ref(false)
const isReopening = ref(false)

const claimChat = async (
    routeName: string,
    method: "post" | "patch",
    flag: { value: boolean },
    errorText: string
) => {
    if (!chatSession.value?.ulid || flag.value) return
    flag.value = true
    try {
        await axios[method](
            route(routeName, [props.organisationSlug, chatSession.value.ulid]),
            {},
            { withCredentials: true }
        )
        emit("assign-self-success")
    } catch (e: any) {
        notify({
            title: ctrans("Error"),
            text: e?.response?.data?.message ?? errorText,
            type: "error",
        })
    } finally {
        flag.value = false
    }
}

const assignSelf = () =>
    claimChat(
        "grp.org.chat.agents.whatsapp.assign.self",
        "post",
        isAssigningSelf,
        ctrans("Failed to assign chat")
    )

const takeoverChat = () =>
    claimChat(
        "grp.org.chat.agents.whatsapp.takeover",
        "patch",
        isTakingOver,
        ctrans("Failed to assign chat")
    )

const reopenChat = () =>
    claimChat(
        "grp.org.chat.agents.whatsapp.sessions.reopen",
        "patch",
        isReopening,
        ctrans("Failed to reopen chat")
    )

const canSendNonTemplate = ref<boolean | undefined>(undefined)
const templateOnly = computed(() => canSendNonTemplate.value === false)

const messagesLocal = ref<LocalChatMessage[]>([])
const eventsLocal = ref<any[]>([])
const newMessage = ref("")
useComposerDraft(() => props.session?.ulid, newMessage)

const messageEditor = ref<InstanceType<typeof ChatMessageEditor> | null>(null)
const messagesContainer = ref<HTMLDivElement>()

const imageInput = ref<HTMLInputElement>()
const fileInput = ref<HTMLInputElement>()

const IMAGE_TYPES = [
    "image/jpeg",
    "image/jpg",
    "image/png",
]

const FILE_TYPES = [
    "application/pdf",
    "text/plain",
    "application/msword",
    "application/vnd.ms-excel",
    "application/vnd.ms-powerpoint",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "application/vnd.openxmlformats-officedocument.presentationml.presentation",
]

// Meta's own ceilings; anything larger is refused by WhatsApp after upload.
const MAX_IMAGE_SIZE = 5 * 1024 * 1024
const MAX_FILE_SIZE = 100 * 1024 * 1024

const isLoadingMore = ref(false)
const canLoadMore = ref(false)
const nextCursor = ref<string | null>(null)
const isSending = ref(false)

const { rejectionFor } = useUploadLimits()

const selectedFile = ref<File | null>(null)
const previewUrl = ref<string | null>(null)
const previewType = ref<"image" | "file" | null>(null)

const handleImageSelect = (e: Event) => {
    const file = (e.target as HTMLInputElement)?.files?.[0]
    if (file) selectImage(file)
}

const selectImage = (file: File) => {
    if (!IMAGE_TYPES.includes(file.type)) {
        notify({
            title: ctrans("Failed"),
            text: ctrans("WhatsApp only accepts JPG and PNG images."),
            type: "error",
        })
        return
    }

    const imageRejection = rejectionFor(file, [], MAX_IMAGE_SIZE)

    if (imageRejection) {
        notify({ title: ctrans("Failed"), text: imageRejection, type: "error" })
        return
    }

    selectedFile.value = file
    previewType.value = "image"
    previewUrl.value = URL.createObjectURL(file)
}

const handleDocSelect = (e: Event) => {
    const file = (e.target as HTMLInputElement)?.files?.[0]
    if (file) selectDoc(file)
}

const selectDoc = (file: File) => {
    if (!FILE_TYPES.includes(file.type)) {
        notify({
            title: ctrans("Failed"),
            text: ctrans("WhatsApp accepts PDF, Word, Excel, PowerPoint and plain text."),
            type: "error",
        })
        return
    }

    // WhatsApp itself allows 100MB, but this server has its own word on what it will take, and
    // it is the one that answers the upload.
    const fileRejection = rejectionFor(file, [], MAX_FILE_SIZE)

    if (fileRejection) {
        notify({ title: ctrans("Failed"), text: fileRejection, type: "error" })
        return
    }

    selectedFile.value = file
    previewType.value = "file"
    previewUrl.value = null
}

const onPasteAttachment = (event: ClipboardEvent) => {
    const clipboard = event.clipboardData
    if (clipboard?.types.includes("text/html") && clipboard.types.includes("text/plain")) return
    const file = clipboard?.files?.[0]
        ?? Array.from(clipboard?.items ?? []).find((item) => item.kind === "file")?.getAsFile()
        ?? null
    if (!file) return

    event.preventDefault()
    if (file.type.startsWith("image/")) {
        selectImage(file)
    } else {
        selectDoc(file)
    }
}

// Dropping a file is the paperclip by another route, and refuses what the paperclip refuses:
// WhatsApp carries one attachment per message, and none at all once the 24 hour window shuts or
// a template has been chosen.
const isDraggingFile = ref(false)
let dragDepth = 0

const canAttach = computed(
    () => !props.readOnly
        && !isClosed.value
        && !isWaiting.value
        && isMyChat.value
        && !hasTemplate.value
        && !templateOnly.value
)

// Dragging a selection of text around the page carries no files, and lighting the whole pane up
// for it would be wrong every time somebody moves a quote from one message to another.
const carriesFiles = (event: DragEvent) =>
    Array.from(event.dataTransfer?.types ?? []).includes("Files")

const onDragEnterAttachment = (event: DragEvent) => {
    if (!canAttach.value || !carriesFiles(event)) return

    event.preventDefault()
    dragDepth += 1
    isDraggingFile.value = true
}

const onDragOverAttachment = (event: DragEvent) => {
    if (!canAttach.value || !carriesFiles(event)) return

    // Without this the browser takes the drop itself and opens the file over the inbox.
    event.preventDefault()

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = "copy"
    }
}

const onDragLeaveAttachment = () => {
    if (!isDraggingFile.value) return

    dragDepth = Math.max(0, dragDepth - 1)

    if (dragDepth === 0) {
        isDraggingFile.value = false
    }
}

const onDropAttachment = (event: DragEvent) => {
    if (!canAttach.value || !carriesFiles(event)) return

    event.preventDefault()
    dragDepth = 0
    isDraggingFile.value = false

    const file = event.dataTransfer?.files?.[0]

    if (!file) return

    if (file.type.startsWith("image/")) {
        selectImage(file)
    } else {
        selectDoc(file)
    }
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

const { jumpToMessage } = useJumpToMessage(messagesContainer)

const replyingTo = ref<LocalChatMessage | null>(null)

const startReply = (message: LocalChatMessage) => {
    replyingTo.value = message
    nextTick(() => messageEditor.value?.focus())
}

const cancelReply = () => {
    replyingTo.value = null
}

const replyPreviewText = (message: LocalChatMessage) =>
    message.message_text
    || message.file_name
    || ctrans(message.message_type === "image" ? "Photo" : "Attachment")

const showEmojiPicker = ref(false)
const emojiPickerContainer = ref<HTMLElement | null>(null)

const pickEmoji = (emoji: string) => {
    messageEditor.value?.insertText(emoji)
}

const handleClickOutsideEmoji = (event: MouseEvent) => {
    if (showEmojiPicker.value && emojiPickerContainer.value && !emojiPickerContainer.value.contains(event.target as Node)) {
        showEmojiPicker.value = false
    }
}

const getMessages = async (loadMore = false) => {
    if (!chatSession.value?.ulid || (loadMore && !canLoadMore.value)) return

    isLoadingMore.value = loadMore

    const params: Record<string, any> = {
        limit: loadMore && nextCursor.value ? 50 : 20,
    }

    if (loadMore && nextCursor.value) {
        params.cursor = nextCursor.value
    }

    try {
        const { data } = await axios.get(
            `${baseUrl}/app/api/chats/meta/sessions/${chatSession.value.ulid}/messages`,
            { params }
        )

        const messages = data?.data?.messages ?? []

        const events = data?.data?.events ?? []

        if (!loadMore) {
            messagesLocal.value = messages.map((m: ChatMessage) => ({ ...m, _status: "sent" }))
            eventsLocal.value = events
        } else {
            messagesLocal.value.unshift(
                ...messages.map((m: ChatMessage) => ({ ...m, _status: "sent" }))
            )
            const known = new Set(eventsLocal.value.map((e: any) => e.id))
            eventsLocal.value = [...events.filter((e: any) => !known.has(e.id)), ...eventsLocal.value]
        }

        canSendNonTemplate.value = data?.data?.can_send_non_template_message

        const page = data?.data?.pagination
        canLoadMore.value = !!page?.has_more
        nextCursor.value = page?.next_cursor ?? null

        if (!loadMore) {
            scrollBottom()
            emit("messages-read")
        }
    } catch (e) {
        console.error("Failed to load WhatsApp messages", e)
    } finally {
        isLoadingMore.value = false
    }
}

// Agent replies and campaign blasts both left this side of the conversation, so both sit
// on the outgoing side of the thread.
const isOutgoing = (message: { sender_type?: string }) =>
    message.sender_type === "agent" || message.sender_type === "system_campaign"

type TimelineEntry =
    | { kind: "message"; at: number; key: string; message: LocalChatMessage }
    | { kind: "event"; at: number; key: string; event: any }

// Messages and status events share one chronological stream so the agent can see what
// happened to the conversation exactly where it happened.
const groupedTimeline = computed(() => {
    const entries: TimelineEntry[] = [
        ...messagesLocal.value.map((message) => ({
            kind: "message" as const,
            at: +new Date(message.created_at),
            key: `m-${message._tempId ?? message.id}`,
            message,
        })),
        ...eventsLocal.value.map((event: any) => ({
            kind: "event" as const,
            at: +new Date(event.created_at),
            key: `e-${event.id}`,
            event,
        })),
    ].sort((a, b) => a.at - b.at)

    const groups: Record<string, TimelineEntry[]> = {}

    entries.forEach((entry) => {
        const label = new Intl.DateTimeFormat("id-ID", {
            day: "2-digit",
            month: "long",
            year: "numeric",
        }).format(new Date(entry.at))

            ; (groups[label] ??= []).push(entry)
    })

    return groups
})

const postMessage = async (formData: FormData, optimisticMessage: LocalChatMessage) => {
    messagesLocal.value.push(optimisticMessage)
    scrollBottom()
    isSending.value = true

    try {
        const { data } = await axios.post(
            route("grp.org.chat.agents.whatsapp.messages.send", [
                props.organisationSlug,
                chatSession.value!.ulid,
            ]),
            formData,
            { headers: { "Content-Type": "multipart/form-data" }, withCredentials: true }
        )

        const index = messagesLocal.value.findIndex((m) => m._tempId === optimisticMessage._tempId)
        if (index !== -1 && data?.data) {
            messagesLocal.value[index] = { ...data.data, _status: "sent" }
        }
    } catch (e: any) {
        const msg = messagesLocal.value.find((m) => m._tempId === optimisticMessage._tempId)
        if (msg) msg._status = "failed"
        notify({
            title: ctrans("Error"),
            text: chatSendErrorText(e, ctrans("Failed to send WhatsApp message")),
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
            title: ctrans("Error"),
            text: ctrans("The customer has not messaged in the last 24 hours. Only template messages can be sent."),
            type: "error",
        })
        return
    }

    const hasText = !!newMessage.value.trim()
    const hasFile = !!selectedFile.value

    if (!hasText && !hasFile) return

    const tempId = `tmp-${Date.now()}`
    const messageType = hasFile
        ? (IMAGE_TYPES.includes(selectedFile.value!.type) ? "image" : "file")
        : "text"

    const quoted = replyingTo.value

    const optimisticMessage: LocalChatMessage = {
        id: tempId as any,
        _tempId: tempId,
        message_text: newMessage.value ?? "",
        media_url: messageType === "image" ? previewUrl.value : null,
        sender_type: "agent",
        message_type: messageType,
        created_at: new Date().toISOString(),
        _status: "sending",
        replied_to: quoted
            ? {
                id: quoted.id as any,
                message_text: quoted.message_text,
                message_type: quoted.message_type,
                sender_type: quoted.sender_type,
            }
            : null,
    }

    const formData = new FormData()
    formData.append("message_text", newMessage.value ?? "")
    if (selectedFile.value) {
        formData.append(messageType === "image" ? "image" : "file", selectedFile.value)
    }
    if (quoted?.id) {
        formData.append("replied_to_id", String(quoted.id))
    }

    newMessage.value = ""
    removeFile()
    cancelReply()

    await postMessage(formData, optimisticMessage)
}

// Template picker
const isTemplateDialogOpen = ref(false)
const templates = ref<WhatsappTemplate[]>([])
const isLoadingTemplates = ref(false)
const selectedTemplate = ref<WhatsappTemplate | null>(null)
const templateParameters = ref<string[]>([])

const escapeHtml = (str: string) =>
    str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;")

const parameterLabel = (index: number): string => {
    const tags = selectedTemplate.value?.merge_tags ?? []
    if (tags[index]) {
        return String(tags[index]).replace(/_/g, " ")
    }
    return `{{${index + 1}}}`
}

const tagBadge = (label: string) =>
    `<span class="inline-flex items-center px-1.5 py-0.5 mx-0.5 rounded bg-blue-50 text-blue-600 text-[10px] font-medium align-baseline whitespace-nowrap">${escapeHtml(label)}</span>`

const formatBodyWithTags = (body: string, tags: string[] = [], values: string[] = []) => {
    return escapeHtml(body).replace(/\{\{(\d+)\}\}/g, (_match, num) => {
        const index = parseInt(num) - 1
        if (values[index]?.trim()) return escapeHtml(values[index])
        const label = tags[index] ? String(tags[index]).replace(/_/g, " ") : `{{${num}}}`
        return tagBadge(label)
    })
}

const openTemplateDialog = async () => {
    isTemplateDialogOpen.value = true

    if (!chatSession.value?.shop?.id) return

    isLoadingTemplates.value = true
    try {
        // Values are resolved per conversation, so the list cannot be cached across chats.
        const { data } = await axios.get(`${baseUrl}/app/api/chats/meta/templates`, {
            params: { shop_id: chatSession.value.shop.id, session_ulid: chatSession.value.ulid },
        })
        templates.value = data?.data ?? []
    } catch (e) {
        notify({ title: ctrans("Error"), text: ctrans("Failed to load templates"), type: "error" })
    } finally {
        isLoadingTemplates.value = false
    }
}

const hasTemplate = computed(() => !!selectedTemplate.value)

const selectTemplate = (template: WhatsappTemplate) => {
    selectedTemplate.value = template
    if (template.auto_fill && template.resolved_values) {
        templateParameters.value = template.resolved_values.map((v) => v ?? "")
    } else {
        templateParameters.value = Array(template.parameter_count).fill("")
    }
    isTemplateDialogOpen.value = false
    newMessage.value = ""
    removeFile()
}

const clearTemplate = () => {
    selectedTemplate.value = null
    templateParameters.value = []
}

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

const templatePreviewHtml = computed(() => {
    if (!selectedTemplate.value) return ""
    if (selectedTemplate.value.auto_fill) {
        return formatBodyWithTags(
            selectedTemplate.value.preview ?? selectedTemplate.value.body,
            selectedTemplate.value.merge_tags ?? []
        )
    }
    return formatBodyWithTags(
        selectedTemplate.value.body,
        selectedTemplate.value.merge_tags ?? [],
        templateParameters.value
    )
})

const canSendTemplate = computed(() => {
    if (!selectedTemplate.value) return false
    return templateParameters.value.every((parameter) => parameter.trim() !== "")
})

const sendTemplateMessage = async () => {
    if (!selectedTemplate.value || !canSendTemplate.value) return

    const tempId = `tmp-${Date.now()}`
    const optimisticMessage: LocalChatMessage = {
        id: tempId as any,
        _tempId: tempId,
        message_text: templatePreview.value,
        sender_type: "agent",
        message_type: "text",
        created_at: new Date().toISOString(),
        _status: "sending",
    }

    const formData = new FormData()
    formData.append("template_id", String(selectedTemplate.value.id))
    formData.append("template_name", selectedTemplate.value.name)
    formData.append("template_language", selectedTemplate.value.language)
    templateParameters.value.forEach((parameter, index) => {
        formData.append(`template_parameters[${index}]`, parameter)
    })

    clearTemplate()

    await postMessage(formData, optimisticMessage)
}

const statusBadgeClass = computed(() => {
    const map: Record<string, string> = {
        active:      "bg-green-100 text-green-700",
        waiting:     "bg-yellow-100 text-yellow-700",
        resolved:    "bg-blue-100 text-blue-700",
        transferred: "bg-purple-100 text-purple-700",
        closed:      "bg-gray-100 text-gray-600",
    }
    return map[chatSession.value?.status ?? ""] ?? "bg-gray-100 text-gray-600"
})

watch(
    () => chatSession.value?.can_send_non_template_message,
    (value: boolean | undefined) => {
        if (value !== undefined) {
            canSendNonTemplate.value = value
        }
    },
    { immediate: true }
)

let chatChannel: any = null

// Echo hands back the same channel object for a name already subscribed, so this pane
// and a mini chat window on the same conversation share one channel. Dropping a
// listener by event name alone would take the other component's with it, which is why
// each handler is kept and removed individually.
let onMessage: ((payload: any) => void) | null = null
let onReaction: ((payload: any) => void) | null = null
let onStatus: ((payload: any) => void) | null = null
let onCall: ((payload: any) => void) | null = null

const stopSocket = () => {
    if (onMessage) chatChannel?.stopListening(".message", onMessage)
    if (onReaction) chatChannel?.stopListening(".reaction", onReaction)
    if (onStatus) chatChannel?.stopListening(".status", onStatus)
    if (onCall) chatChannel?.stopListening(".call", onCall)
    onMessage = null
    onReaction = null
    onStatus = null
    onCall = null
    chatChannel = null
}

// Marks the thread read on our side and sends the receipt that lights up the
// customer's blue ticks; opening the thread does the same through the messages endpoint.
const markIncomingAsRead = async () => {
    if (!chatSession.value?.ulid || props.readOnly) return

    try {
        await axios.post(
            `${baseUrl}/app/api/chats/meta/sessions/${chatSession.value.ulid}/read`,
            {},
            { withCredentials: true }
        )
        emit("messages-read")
    } catch (e) {
        console.error("Failed to mark WhatsApp messages as read", e)
    }
}

const initSocket = () => {
    if (!chatSession.value?.ulid || !window.Echo) return

    stopSocket()

    chatChannel = window.Echo.private(`meta-chat-session.${chatSession.value.ulid}`)

    onMessage = ({ message, can_send_non_template_message }: any) => {
        if (!message?.id) return

        if (can_send_non_template_message !== undefined) {
            canSendNonTemplate.value = can_send_non_template_message
        }

        // Our own optimistic bubble is superseded by the broadcast that follows the send.
        messagesLocal.value = messagesLocal.value.filter(
            (m) => !(m._status === "sending" && m.sender_type === "agent")
        )

        const index = messagesLocal.value.findIndex((m) => m.id === message.id)

        if (index !== -1) {
            messagesLocal.value[index] = { ...messagesLocal.value[index], ...message, _status: "sent" }
        } else {
            messagesLocal.value.push({ ...message, _status: "sent" })
        }

        if (!isOutgoing(message) && message.sender_type !== "system") {
            markIncomingAsRead()
        }

        scrollBottom()
    }

    onReaction = ({ message }: any) => {
        if (!message?.id) return

        const index = messagesLocal.value.findIndex((m) => m.id === message.id)

        if (index !== -1) {
            messagesLocal.value[index] = {
                ...messagesLocal.value[index],
                reactions: message.reactions ?? [],
            }
        }
    }

    onStatus = (payload: any) => {
        const index = messagesLocal.value.findIndex((m) => m.id === payload?.message_id)

        if (index === -1) return

        messagesLocal.value[index] = {
            ...messagesLocal.value[index],
            is_read: payload.is_read ?? messagesLocal.value[index].is_read,
            metadata: {
                ...(messagesLocal.value[index].metadata ?? {}),
                wa_status: payload.status,
                wa_error: payload.error,
            },
            _status: payload.status === "failed" ? "failed" : "sent",
        }
    }

    onCall = (payload: any) => {
        if (payload?.id) applyCallBroadcast(payload)
    }

    chatChannel.listen(".message", onMessage)
    chatChannel.listen(".reaction", onReaction)
    chatChannel.listen(".status", onStatus)
    chatChannel.listen(".call", onCall)
}

watch(
    () => chatSession.value?.ulid,
    async () => {
        messagesLocal.value = []
        nextCursor.value = null
        canLoadMore.value = false
        clearTemplate()
        initSocket()
        await getMessages()
    }
)

onMounted(() => {
    initSocket()
    getMessages()
    document.addEventListener("click", handleClickOutsideEmoji)
})

onUnmounted(() => {
    stopSocket()
    document.removeEventListener("click", handleClickOutsideEmoji)
})
</script>

<template>
    <div class="relative flex flex-col h-full bg-white overflow-hidden"
        @dragenter="onDragEnterAttachment" @dragover="onDragOverAttachment"
        @dragleave="onDragLeaveAttachment" @drop="onDropAttachment">
        <!-- Header -->
        <header class="flex items-center gap-3 px-3 py-2 border-b">
            <button @click="$emit('back')">
                <FontAwesomeIcon :icon="faArrowLeft" class="text-gray-400" fixed-width />
            </button>

            <button type="button"
                class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-green-100 text-green-600 hover:ring-2 hover:ring-green-200 transition"
                @click="emit('view-profile')">
                <Image v-if="session?.image" :src="session?.image" class="w-full h-full rounded-full object-cover" />
                <FontAwesomeIcon v-else :icon="faUser" class="text-sm" fixed-width />
            </button>

            <div class="flex-1 min-w-0 cursor-pointer" @click="emit('view-profile')">
                <div class="text-sm font-semibold truncate hover:text-green-700 transition-colors">
                    {{ session?.guest_identifier || session?.contact_name }}
                </div>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <FontAwesomeIcon :icon="faWhatsapp" class="text-[11px] text-green-600" fixed-width />
                    <img v-if="(session as any)?.country_code" :src="`/flags/${(session as any).country_code.toLowerCase()}.png`"
                        :alt="(session as any).country_code" v-tooltip="(session as any).country_code" class="h-3 w-auto shrink-0" />
                    <span v-if="session?.phone_number" class="text-[11px] text-gray-400 truncate">
                        {{ session.phone_number }}
                    </span>
                    <span v-if="session?.status"
                        class="text-[10px] font-medium capitalize rounded-full px-1.5 py-0.5"
                        :class="statusBadgeClass">
                        {{ session.status }}
                    </span>
                    <span v-if="showShop && session?.shop?.name" class="text-[11px] text-gray-400 truncate">
                        {{ session.shop.name }}
                    </span>
                    <span class="shrink-0 text-[9px] px-1 py-0.5 border leading-none"
                        :class="isCustomer ? 'border-green-300 text-green-500' : 'border-blue-300 text-blue-400'"
                        v-tooltip="isCustomer ? ctrans('Customer') : ctrans('Guest')">
                        {{ isCustomer ? 'C' : 'G' }}
                    </span>
                    <FontAwesomeIcon :icon="faWhatsapp" class="shrink-0 text-[11px] text-green-600" fixed-width />
                    <span v-if="lastMessageStamp" class="text-[11px] text-gray-400 shrink-0">
                        {{ lastMessageStamp.time }} <span class="text-gray-300">({{ lastMessageStamp.age }})</span>
                    </span>
                </div>
            </div>

            <button v-if="canReportSpam" type="button" :disabled="isSpamMarking"
                v-tooltip="ctrans('Blocks this sender. Everything they send from now on goes to spam.')"
                class="inline-flex items-center gap-1.5 shrink-0 h-7 px-2.5 text-[11px] font-medium rounded-md border border-red-200 text-red-600 transition hover:bg-red-50 disabled:opacity-50"
                @click="markSpam">
                <FontAwesomeIcon :icon="faBan" class="text-[11px]" fixed-width />
                {{ ctrans("Spam") }}
            </button>

            <ModalConfirmationDelete v-if="!isClosed && !readOnly" :routeDelete="{
                name: 'grp.org.chat.agents.whatsapp.sessions.close',
                parameters: [organisationSlug, session?.ulid],
                method: 'patch',
            }" :title="ctrans('Are you sure you want to end this chat?')"
                :noLabel="ctrans('End chat')"
                :noIcon="faTimesCircle"
                :description="ctrans('This will close the chat session. The conversation history will be preserved.')"
                @success="$emit('close-session')">
                <template #default="{ changeModel }">
                    <button @click="changeModel"
                        class="inline-flex items-center justify-center gap-1.5 shrink-0 h-7 px-2.5 text-[11px] font-medium rounded-md transition hover:opacity-90"
                        :style="{ backgroundColor: 'var(--theme-color-4)', color: 'var(--theme-color-5)' }">
                        <FontAwesomeIcon :icon="faTimesCircle" class="text-[11px]" fixed-width />
                        {{ ctrans("End chat") }}
                    </button>
                </template>
            </ModalConfirmationDelete>
        </header>

        <div v-if="!readOnly" class="px-3 pt-2 empty:hidden">
            <WhatsappCallBar :organisation="props.organisationSlug" />
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto px-3 py-2 space-y-3 bg-[#F0F4F8]">
            <div class="flex justify-center" v-if="canLoadMore && nextCursor">
                <button @click="getMessages(true)" :disabled="isLoadingMore" class="flex items-center gap-2 text-xs text-gray-600 px-4 py-1.5
               border rounded-full hover:bg-gray-100 disabled:opacity-50">
                    <FontAwesomeIcon v-if="isLoadingMore" :icon="faSpinner" class="animate-spin text-[10px]" fixed-width />
                    <span>
                        {{ isLoadingMore ? ctrans('Loading messages…') : ctrans('Load older messages') }}
                    </span>
                </button>
            </div>

            <template v-for="(entries, date) in groupedTimeline" :key="date">
                <div class="text-center text-xs text-gray-400">{{ date }}</div>
                <template v-for="entry in entries" :key="entry.key">
                    <ChatTimelineEvent v-if="entry.kind === 'event'" :event="entry.event" />
                    <div v-else class="flex rounded-lg transition-colors"
                        :data-message-id="entry.message.id"
                        :class="isOutgoing(entry.message) ? 'justify-end' : 'justify-start'">
                        <BubbleChat :message="entry.message" viewerType="agent"
                            :contactName="session?.contact_name || session?.guest_identifier"
                            :canEdit="false"
                            reactionUrlBase="/app/api/chats/meta/messages"
                            translateUrlBase="/app/api/chats/meta/messages"
                            disable-slack-forward
                            disable-image-verification
                            :viewerReactorId="layout?.user?.id"
                            :canReply="!isClosed && !templateOnly"
                            format-markup
                            @reply="startReply"
                            @jump-to-message="jumpToMessage" />
                    </div>
                </template>
            </template>
        </div>

        <div v-if="previewType === 'image' && previewUrl" class="px-3 pb-2">
            <div class="relative inline-block">
                <img :src="previewUrl" class="h-24 rounded-lg border object-cover" />
                <button @click="removeFile" class="absolute -top-2 -right-2 bg-white rounded-full shadow p-1">
                    <FontAwesomeIcon :icon="faXmark" fixed-width />
                </button>
            </div>
        </div>

        <div v-if="previewType === 'file' && selectedFile" class="px-3 pb-2">
            <div class="flex items-center gap-3 border rounded-lg p-3 bg-gray-50 min-w-0">
                <div class="text-2xl">
                    <FontAwesomeIcon :icon="faFilePdf" fixed-width />
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
                    <FontAwesomeIcon :icon="faXmark" fixed-width />
                </button>
            </div>
        </div>

        <!-- Footer: closed banner -->
        <footer v-if="readOnly" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-center gap-2 text-xs text-gray-500">
                <FontAwesomeIcon :icon="faEye" class="text-gray-400" fixed-width aria-hidden="true" />
                {{ ctrans("You are viewing this conversation in read-only mode") }}
            </div>
        </footer>

        <footer v-else-if="isClosed" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200">
                <div class="text-xs text-gray-600">
                    {{ ctrans('This chat has been closed') }}
                </div>
                <Button
                    @click="reopenChat"
                    :loading="isReopening"
                    style="primary"
                    size="xs"
                    :label="ctrans('Reopen')"
                    :icon="faRotateRight"
                />
            </div>
        </footer>

        <!-- Footer: Assign-to-me banner for waiting (unassigned) chats -->
        <footer v-else-if="isWaiting" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200">
                <div class="text-xs text-gray-600">
                    {{ ctrans('Assign this chat to yourself to start the conversation') }}
                </div>
                <Button
                    @click="assignSelf"
                    :loading="isAssigningSelf"
                    style="primary"
                    size="xs"
                    :label="ctrans('Assign to me')"
                    :icon="faUser"
                />
            </div>
        </footer>

        <!-- Footer: Takeover banner for team chats -->
        <footer v-else-if="!isMyChat" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-indigo-50 border border-indigo-200">
                <div class="text-xs text-indigo-600">
                    <span class="font-semibold">{{ session?.assigned_agent?.name }}</span>
                    {{ ctrans(' is handling this chat') }}
                </div>
                <Button
                    @click="takeoverChat"
                    :loading="isTakingOver"
                    style="primary"
                    size="xs"
                    :label="ctrans('Take Over')"
                    :icon="faUser"
                />
            </div>
        </footer>

        <!-- Footer: composer -->
        <footer v-else class="px-3 py-2 bg-white">
            <input ref="imageInput" type="file" accept=".jpg,.jpeg,.png" class="hidden"
                @change="handleImageSelect" />
            <input ref="fileInput" type="file" accept=".pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="hidden" @change="handleDocSelect" />

            <div v-if="templateOnly && !hasTemplate"
                class="flex items-center gap-2 mb-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-[11px]">
                <FontAwesomeIcon :icon="faFileLines" class="text-[10px]" fixed-width />
                <span>{{ ctrans('The customer has not messaged in the last 24 hours. Only template messages can be sent.') }}</span>
                <button type="button" @click="openTemplateDialog"
                    class="ml-auto shrink-0 px-2 py-1 rounded-md bg-amber-600 text-white font-medium hover:bg-amber-700">
                    {{ ctrans('Pick the message') }}
                </button>
                <a href="https://aiku.io/docs/replying-on-whatsapp-after-24-hours" target="_blank" rel="noopener"
                    class="shrink-0 w-5 h-5 text-[11px] flex items-center justify-center text-amber-600 hover:text-amber-800"
                    v-tooltip="ctrans('Why can I not type? The WhatsApp 24 hour rule, explained')"
                    :aria-label="ctrans('Guide: the WhatsApp 24 hour rule')">
                    <FontAwesomeIcon :icon="faCircleQuestion" fixed-width />
                </a>
            </div>

            <div v-if="replyingTo"
                class="flex items-start gap-2 mb-2 px-3 py-2 rounded-lg bg-gray-100">
                <div class="flex-1 min-w-0">
                    <div class="text-[11px] font-semibold text-gray-600">
                        {{ ctrans('Replying to') }}
                        {{ replyingTo.sender_type === 'agent' ? ctrans('yourself') : (session?.contact_name || session?.guest_identifier) }}
                    </div>
                    <div class="text-[11px] text-gray-500 truncate">{{ replyPreviewText(replyingTo) }}</div>
                </div>
                <button type="button" @click="cancelReply" :aria-label="ctrans('Cancel reply')"
                    class="shrink-0 w-6 h-6 flex items-center justify-center rounded hover:bg-gray-200 text-gray-400">
                    <FontAwesomeIcon :icon="faXmark" class="text-xs" fixed-width />
                </button>
            </div>

            <ChatAiDraftBox whatsapp :session-ulid="chatSession?.ulid" :read-only="readOnly" @use="(text) => newMessage = text" />

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm focus-within:border-gray-400 focus-within:shadow-md transition-shadow">
                <div v-if="hasTemplate"
                    class="flex items-center gap-2 mx-3 mt-2 px-2 py-1 rounded-lg bg-green-50 text-green-700 text-[11px]">
                    <FontAwesomeIcon :icon="faFileLines" class="text-[10px]" fixed-width />
                    <span class="font-medium truncate">{{ selectedTemplate?.name }}</span>
                    <span class="text-green-600/70">{{ selectedTemplate?.language }}</span>
                    <button @click="clearTemplate" class="ml-auto text-green-600 hover:text-red-500"
                        v-tooltip="ctrans('Remove template')" :aria-label="ctrans('Remove template')">
                        <FontAwesomeIcon :icon="faXmark" class="text-[10px]" fixed-width />
                    </button>
                </div>

                <div v-if="hasTemplate"
                    class="px-4 pt-2 pb-1 text-sm leading-relaxed text-gray-500 whitespace-pre-line max-h-[120px] overflow-y-auto"
                    v-html="templatePreviewHtml"></div>

                <div v-if="hasTemplate && templateAutoFills && !templateMissingTags.length"
                    class="mx-3 mb-1 text-[11px] text-gray-400">
                    {{ ctrans("Values filled automatically from this contact.") }}
                </div>

                <div v-if="hasTemplate && templateParameters.length" class="px-3 pb-1 space-y-1.5">
                    <template v-for="(parameter, index) in templateParameters" :key="index">
                        <input v-if="!selectedTemplate?.resolved_values || selectedTemplate.resolved_values[index] == null"
                            v-model="templateParameters[index]" type="text"
                            :placeholder="ctrans('Value for :placeholder', { placeholder: parameterLabel(index) })"
                            class="w-full text-xs border rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-green-500" />
                    </template>
                </div>

                <ChatMessageEditor v-if="!hasTemplate" ref="messageEditor" v-model="newMessage"
                    @paste="onPasteAttachment" @submit="sendMessage" enter-sends :disabled="templateOnly"
                    :placeholder="templateOnly ? ctrans('24h window closed, send a template message') : ctrans('Type message...')"
                    class="px-4 pt-3 pb-1 rounded-t-xl [&_.ProseMirror]:max-h-[120px]" />

                <div class="flex items-center justify-between px-2 pb-2 pt-1">
                    <div class="flex items-center gap-1">
                        <button @click="imageInput?.click()" :disabled="hasTemplate || templateOnly"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors disabled:opacity-40 disabled:hover:bg-transparent" v-tooltip="ctrans('Upload image')" :aria-label="ctrans('Upload image')">
                            <FontAwesomeIcon :icon="faImage" class="text-sm" fixed-width />
                        </button>
                        <button @click="fileInput?.click()" :disabled="hasTemplate || templateOnly"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors disabled:opacity-40 disabled:hover:bg-transparent" v-tooltip="ctrans('Upload file')" :aria-label="ctrans('Upload file')">
                            <FontAwesomeIcon :icon="faPaperclip" class="text-sm" fixed-width />
                        </button>
                        <div ref="emojiPickerContainer" class="relative">
                            <button type="button" @click.stop="showEmojiPicker = !showEmojiPicker"
                                :disabled="hasTemplate || templateOnly"
                                class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors disabled:opacity-40 disabled:hover:bg-transparent"
                                :class="showEmojiPicker ? 'text-green-600 bg-gray-100' : 'text-gray-500'"
                                v-tooltip="ctrans('Emoji')" :aria-label="ctrans('Emoji')">
                                <FontAwesomeIcon :icon="faFaceSmile" class="text-sm" fixed-width />
                            </button>

                            <div v-if="showEmojiPicker" class="absolute bottom-full left-0 mb-1 z-30">
                                <EmojiPicker @pick="pickEmoji" />
                            </div>
                        </div>
                        <button @click="openTemplateDialog"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-green-50 text-gray-500 hover:text-green-600 transition-colors" v-tooltip="ctrans('Send template message')" :aria-label="ctrans('Send template message')">
                            <FontAwesomeIcon :icon="faFileLines" class="text-sm" fixed-width />
                        </button>
                        <button @click="isTicketModalOpen = true"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-gray-500 hover:text-blue-600 transition-colors" v-tooltip="ctrans('Create ticket from this chat')" :aria-label="ctrans('Create ticket from this chat')">
                            <FontAwesomeIcon :icon="faLifeRing" class="text-sm" fixed-width />
                        </button>
                        <template v-if="!hasTemplate && !templateOnly">
                            <div class="mx-1 h-5 w-px bg-gray-200" />
                            <ChatFormattingToolbar :editor="messageEditor?.editor" />
                        </template>
                    </div>
                    <Button @click="sendMessage" :loading="isSending"
                        :disabled="hasTemplate ? !canSendTemplate : templateOnly"
                        :icon="faPaperPlane"></Button>
                </div>
            </div>
        </footer>

        <WhatsappTemplatePicker
            :visible="isTemplateDialogOpen"
            @update:visible="isTemplateDialogOpen = $event"
            :templates="templates"
            :is-loading="isLoadingTemplates"
            :selected-template-id="selectedTemplate?.id"
            :organisation-slug="organisationSlug"
            :shop-slug="session?.shop?.slug"
            @select="selectTemplate" />

        <TicketModal
            :is-open="isTicketModalOpen"
            :session="session"
            :organisation="organisationSlug"
            channel="whatsapp"
            @close="isTicketModalOpen = false" />

        <!-- Nothing here takes the pointer, so the drag keeps reaching the pane underneath and
             the drop still lands. -->
        <div v-if="isDraggingFile"
            class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center bg-white/75 p-6">
            <div class="flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-green-500 bg-white px-10 py-8 shadow-sm">
                <FontAwesomeIcon :icon="faPaperclip" class="text-2xl text-green-600" fixed-width />
                <div class="text-sm font-medium text-gray-700">{{ ctrans("Drop the file here") }}</div>
                <div class="text-xs text-gray-400">
                    {{ ctrans("One file per message: JPG or PNG up to 5MB, or a document up to 100MB") }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
::-webkit-scrollbar {
    width: 5px;
}

::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 4px;
}
</style>
