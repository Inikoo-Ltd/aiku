<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, inject, computed, nextTick, defineAsyncComponent, getCurrentInstance } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon, FontAwesomeLayers } from "@fortawesome/vue-fontawesome"
import {
    faPaperPlane,
    faArrowLeft,
    faImage,
    faEllipsisVertical,
    faLanguage,
    faTimesCircle,
    faMessage,
    faPaperclip, faXmark, faFilePdf, faEnvelope, faRotateRight, faBan, faRotateLeft, faFaceSmile,
    faLifeRing,
    faEye,
    faArchive,
    faAngleDown,
    faLock,
    faExclamationCircle,
    faCircle,
    faShare,
} from "@fortawesome/free-solid-svg-icons"
import { faSlack } from "@fortawesome/free-brands-svg-icons"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import TicketModal from "@/Components/Chat/Agent/TicketModal.vue"
import SlackShareModal from "@/Components/Chat/Agent/SlackShareModal.vue"
import ForwardToColleagueModal from "@/Components/Chat/Agent/ForwardToColleagueModal.vue"
import type { ChatMessage, SessionAPI } from "@/types/Chat/chat"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { faUser, faSpinner } from "@far"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"
import { useJumpToMessage } from "@/Composables/useJumpToMessage"
import ChatTimelineEvent from "@/Components/Chat/ChatTimelineEvent.vue"
import { useChatLanguages } from "@/Composables/useLanguages"
import { useUploadLimits } from "@/Composables/useUploadLimits"
import { notify } from "@kyvg/vue3-notification"

const EmojiPicker = defineAsyncComponent(() => import("@/Components/Messaging/EmojiPicker.vue"))

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

import { formatChatTime, formatChatAge } from "@/Composables/chatTime"
import { faGlobe } from "@fal"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"

const props = defineProps<{
    messages: ChatMessage[]
    session: SessionAPI | null
    readOnly?: boolean
    showShop?: boolean
    ignoreReasons?: Array<{ value: string; label: string }>
}>()

const isCustomer = computed(() => Boolean((props.session as any)?.web_user?.customer_id || (props.session as any)?.customer_id))

const channelIcon = computed(() => {
    const channel = (props.session as any)?.channel

    return channel === "whatsapp" ? faWhatsapp : channel === "email" ? faEnvelope : faGlobe
})

const channelIconClass = computed(() => {
    const channel = (props.session as any)?.channel

    return channel === "whatsapp" ? "text-green-600" : channel === "email" ? "text-blue-500" : "text-gray-400"
})

const messagesLocal = ref<LocalChatMessage[]>([])

// When the last word was said, and how long ago: a waiting conversation is judged by its age.
const lastMessageStamp = computed(() => {
    const at = messagesLocal.value[messagesLocal.value.length - 1]?.created_at

    if (!at) {
        return null
    }

    const stamp = new Date(at).getTime()

    return { time: formatChatTime(stamp), age: formatChatAge(stamp) }
})

const emit = defineEmits([
    "send-message",
    "back",
    "close-session",
    "view-history",
    "view-user-profile",
    "view-message-details",
    "transfer-agent-success",
    "assign-self-success",
    "messages-read",
    "open-slack-settings",
    "spam-success",
    "restore-success",
    "view-tickets",
])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const isMyChat = computed(() => {
    if (!props.session?.assigned_agent) return true
    return String(props.session.assigned_agent.user_id ?? "") === String(layout?.user?.id ?? "")
})

const isTakingOver = ref(false)
const takeoverChat = async () => {
    if (!props.session?.ulid || isTakingOver.value) return
    isTakingOver.value = true
    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        await axios.patch(
            route("grp.org.chat.agents.takeover", [organisation, props.session.ulid]),
            {},
            { withCredentials: true }
        )
        props.session.status = "active"
        if (props.session.assigned_agent) {
            props.session.assigned_agent.user_id = layout?.user?.id
            props.session.assigned_agent.name = layout?.user?.contact_name ?? ""
        }
        emit("assign-self-success")
    } catch {
        notify({ title: ctrans("Error"), text: ctrans("Failed to take over chat"), type: "error" })
    } finally {
        isTakingOver.value = false
    }
}

const currentOrganisation = computed(
    () => String((route().params as Record<string, any>)?.organisation ?? "aw")
)

const isTicketModalOpen = ref(false)
const openTicketModal = () => {
    isMenuOpen.value = false
    isTicketModalOpen.value = true
}

// Putting a conversation down again. Opening one takes it, and an agent who cannot answer it —
// wrong language, not their decision — was left holding it while it looked answered to everyone else.
const isReleasing = ref(false)
const canRelease = computed(() =>
    !props.readOnly && !isClosed.value && !isTrashed.value && !isWaiting.value && isMyChat.value
)
const releaseChat = async () => {
    if (!props.session?.ulid || isReleasing.value) return
    isMenuOpen.value = false
    isReleasing.value = true
    try {
        await axios.patch(
            route("grp.org.chat.agents.sessions.release", [currentOrganisation.value, props.session.ulid]),
            {},
            { withCredentials: true }
        )
        props.session.status = "waiting"
        if (props.session.assigned_agent) {
            props.session.assigned_agent = null
        }
        emit("assign-self-success")
        notify({ title: ctrans("Given back"), text: ctrans("Anybody can pick this up now"), type: "success" })
    } catch (e: any) {
        notify({
            title: ctrans("Error"),
            text: e?.response?.data?.message ?? ctrans("Failed to give this conversation back"),
            type: "error",
        })
    } finally {
        isReleasing.value = false
    }
}

const isForwardModalOpen = ref(false)
const openForwardModal = () => {
    isMenuOpen.value = false
    isForwardModalOpen.value = true
}

const isSlackModalOpen = ref(false)
const openSlackModal = () => {
    isMenuOpen.value = false
    isSlackModalOpen.value = true
}
const onOpenSlackSettings = () => {
    isSlackModalOpen.value = false
    emit("open-slack-settings")
}

const isSpamMarking = ref(false)
// Ignored is the conversation nobody has to answer: an out of office, a circular, a newsletter.
// Unlike spam it never blocks the sender, since the same address writes properly next week.
// A guest is a stranger: their address can be blocked. A customer's never is, so all they get
// is Ignore, which puts this one conversation aside and nothing else.
const isGuest = computed(() => !(props.session as any)?.web_user?.customer_id && !(props.session as any)?.customer?.id)

// Disposing of a conversation somebody else is holding is theirs to do, not ours. The server
// says whether this viewer may, since supervisors keep the override and the page cannot know
// who supervises what.
const canDispose = computed(() => {
    const flag = (props.session as any)?.can_dispose
    return typeof flag === "boolean" ? flag : isMyChat.value
})

// The tickets panel lives in the page around us, and not every page that shows a thread has
// one, so the button only appears where pressing it would go somewhere.
const instance = getCurrentInstance()
const hasTicketsPanel = computed(() => Boolean((instance?.vnode?.props as any)?.onViewTickets))

// Same for the message details view: the inbox folded it into the profile panel's Session
// section, so offering it there opened the profile panel twice under two names.
const hasMessageDetailsPanel = computed(() => Boolean((instance?.vnode?.props as any)?.onViewMessageDetails))

const openTicketsCount = computed(() => Number((props.session as any)?.open_tickets_count ?? 0))
const blockingTicketsCount = computed(() => Number((props.session as any)?.blocking_tickets_count ?? 0))

const openTicketsTooltip = computed(() =>
    blockingTicketsCount.value
        ? ctrans("This chat is waiting on :blocked of :total open tickets. It cannot be closed until they are resolved or cancelled.", {
            blocked: String(blockingTicketsCount.value),
            total: String(openTicketsCount.value),
        })
        : ctrans(":total open :ticket raised from this chat.", {
            total: String(openTicketsCount.value),
            ticket: openTicketsCount.value === 1 ? ctrans("ticket") : ctrans("tickets"),
        })
)

// The count in the header comes with the conversation, so a ticket raised here has to be added
// to it by hand; the list is only refetched when the inbox reloads.
const onTicketCreated = (ticket: { blocks_source?: boolean }) => {
    const session = props.session as any
    if (!session) return
    session.open_tickets_count = Number(session.open_tickets_count ?? 0) + 1
    if (ticket?.blocks_source) {
        session.blocking_tickets_count = Number(session.blocking_tickets_count ?? 0) + 1
    }
}

const onViewTickets = () => {
    isMenuOpen.value = false
    emit("view-tickets")
}

const heldByAnotherAgent = computed(() =>
    ctrans(":agent is handling this chat. Take it over first, or ask a supervisor.", {
        agent: props.session?.assigned_agent?.name || ctrans("Another agent"),
    })
)

const canIgnore = computed(() =>
    (props.session as any)?.channel !== "whatsapp" && !isClosed.value && !isTrashed.value && !props.readOnly && canDispose.value
)

const canReportSpam = computed(() => isGuest.value && !isClosed.value && !isTrashed.value && !props.readOnly && canDispose.value)

// Ending a conversation nobody ever answered is rude: from the other side it reads as being
// shown the door for writing in. Until somebody here has replied, the way to clear it is Ignore.
const hasBeenAnswered = computed(() =>
    messagesLocal.value.some((message) => message.sender_type === "agent")
)

const canEndChat = computed(() => hasBeenAnswered.value && !isClosed.value && !isTrashed.value && !props.readOnly)

// The reason is picked, never typed: clearing an imported mailbox is a bulk job, and what has
// to be written becomes blank or inconsistent within a day. Picked from a list it can be counted.
const isIgnoreMenuOpen = ref(false)
const ignoreMenuRef = ref<HTMLElement | null>(null)

const markRubbish = async (rubbish: boolean, reason?: string) => {
    if (!props.session?.ulid || isSpamMarking.value) return
    isMenuOpen.value = false
    isSpamMarking.value = true
    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        const routeName = rubbish
            ? "grp.org.chat.agents.sessions.rubbish"
            : "grp.org.chat.agents.sessions.not_rubbish"
        await axios.patch(
            route(routeName, [organisation, props.session.ulid]),
            reason ? { reason } : {},
            { withCredentials: true }
        )
        emit("spam-success")
    } catch (e: any) {
        notify({
            title: ctrans("Error"),
            text: e?.response?.data?.message ?? ctrans("Failed to update"),
            type: "error",
        })
    } finally {
        isSpamMarking.value = false
    }
}

const markSpam = async (spam: boolean) => {
    if (!props.session?.ulid || isSpamMarking.value) return
    isMenuOpen.value = false
    isSpamMarking.value = true
    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        const routeName = spam
            ? "grp.org.chat.agents.sessions.spam"
            : "grp.org.chat.agents.sessions.not_spam"
        await axios.patch(route(routeName, [organisation, props.session.ulid]), {}, { withCredentials: true })
        emit("spam-success")
    } catch (e: any) {
        notify({
            title: ctrans("Error"),
            text: e?.response?.data?.message ?? ctrans("Failed to update spam status"),
            type: "error",
        })
    } finally {
        isSpamMarking.value = false
    }
}

const isAssigningSelf = ref(false)
const assignSelf = async () => {
    if (!props.session?.ulid || isAssigningSelf.value) return
    isAssigningSelf.value = true
    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        await axios.post(
            route("grp.org.chat.agents.assign.self", [organisation, props.session.ulid]),
            {},
            { withCredentials: true }
        )
        props.session.status = "active"
        if (props.session.assigned_agent) {
            props.session.assigned_agent.user_id = layout?.user?.id
            props.session.assigned_agent.name = layout?.user?.contact_name ?? ""
        }
        emit("assign-self-success")
    } catch {
        notify({ title: ctrans("Error"), text: ctrans("Failed to assign chat"), type: "error" })
    } finally {
        isAssigningSelf.value = false
    }
}

const isRestoring = ref(false)
const restoreChat = async () => {
    if (!props.session?.ulid || isRestoring.value) return
    isRestoring.value = true
    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        await axios.patch(
            route("grp.org.chat.agents.sessions.restore", [organisation, props.session.ulid]),
            {},
            { withCredentials: true }
        )
        emit("restore-success")
    } catch {
        notify({ title: ctrans("Error"), text: ctrans("Failed to restore chat"), type: "error" })
    } finally {
        isRestoring.value = false
    }
}

const isReopening = ref(false)
const reopenChat = async () => {
    if (!props.session?.ulid || isReopening.value) return
    isReopening.value = true
    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        await axios.patch(
            route("grp.org.chat.agents.sessions.reopen", [organisation, props.session.ulid]),
            {},
            { withCredentials: true }
        )
        props.session.status = "active"
        if (props.session.assigned_agent) {
            props.session.assigned_agent.user_id = layout?.user?.id
            props.session.assigned_agent.name = layout?.user?.contact_name ?? ""
        }
        emit("assign-self-success")
    } catch {
        notify({ title: ctrans("Error"), text: ctrans("Failed to reopen chat"), type: "error" })
    } finally {
        isReopening.value = false
    }
}

const eventsLocal = ref<any[]>([])
const newMessage = ref("")

const handleRetractMessage = async ({ id, reason }: { id: number; reason: string }) => {
    if (!props.session?.ulid) return
    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        const { data } = await axios.delete(
            route("grp.org.chat.agents.messages.retract", [organisation, props.session.ulid, id]),
            { data: { reason }, withCredentials: true }
        )

        const msg: any = messagesLocal.value.find((m) => String(m.id) === String(id))
        if (msg) {
            msg.is_retracted = true
            msg.retracted_at = data?.data?.retracted_at ?? new Date().toISOString()
            msg.retraction_reason = data?.data?.retraction_reason ?? null
        }
    } catch (e: any) {
        notify({
            title: ctrans("Error"),
            text: e?.response?.data?.message ?? ctrans("Failed to take back message"),
            type: "error",
        })
    }
}

const handleRedactMessage = async ({ id, fragment }: { id: number; fragment: string }) => {
    if (!props.session?.ulid) return

    if (!fragment) {
        notify({
            title: ctrans("Nothing selected"),
            text: ctrans("Select the text to strike out inside the message, then click Redact"),
            type: "warning",
        })
        return
    }

    if (!window.confirm(ctrans("Strike this text out of the conversation for good? It cannot be brought back."))) {
        return
    }

    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        const { data } = await axios.patch(
            route("grp.org.chat.agents.messages.redact", [organisation, props.session.ulid, id]),
            { fragment },
            { withCredentials: true }
        )

        const updated = data?.data
        const msg: any = messagesLocal.value.find((m) => String(m.id) === String(id))
        if (msg && updated) {
            msg.message_text = updated.message_text
            msg.original = updated.original
            msg.translations = updated.translations
            msg.is_redacted = true
        }
    } catch (e: any) {
        notify({
            title: ctrans("Error"),
            text: e?.response?.data?.message ?? ctrans("Failed to redact message"),
            type: "error",
        })
    }
}

const handleRedactAttachment = async ({ id }: { id: number }) => {
    if (!props.session?.ulid) return

    if (!window.confirm(ctrans("Remove this file from the conversation for good? It cannot be brought back."))) {
        return
    }

    try {
        const organisation = (route().params as Record<string, any>)?.organisation ?? "aw"
        const { data } = await axios.delete(
            route("grp.org.chat.agents.messages.redact_attachment", [organisation, props.session.ulid, id]),
            { withCredentials: true }
        )

        const updated = data?.data
        const msg: any = messagesLocal.value.find((m) => String(m.id) === String(id))
        if (msg && updated) {
            Object.assign(msg, updated)
        }
    } catch (e: any) {
        notify({
            title: ctrans("Error"),
            text: e?.response?.data?.message ?? ctrans("Failed to remove file"),
            type: "error",
        })
    }
}

const messageInput = ref<HTMLTextAreaElement>()
const messagesContainer = ref<HTMLDivElement>()

const { jumpToMessage } = useJumpToMessage(messagesContainer)

const showEmojiPicker = ref(false)
const emojiPickerContainer = ref<HTMLElement | null>(null)

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

const handleClickOutsideEmoji = (event: MouseEvent) => {
    if (showEmojiPicker.value && emojiPickerContainer.value && !emojiPickerContainer.value.contains(event.target as Node)) {
        showEmojiPicker.value = false
    }
}

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

const MAX_SIZE = 10 * 1024 * 1024

const isMenuOpen = ref(false)
const isLoadingMore = ref(false)
const canLoadMore = ref(false)
const nextCursor = ref<string | null>(null)

const chatSession = computed(() => props.session)
const isTrashed = computed(() => !!(chatSession.value as any)?.is_trashed)
const isClosed = computed(() => chatSession.value?.status === "closed")
const isWaiting = computed(() => chatSession.value?.status === "waiting")
const menuRef = ref<HTMLElement | null>(null)

const isTyping = ref(false)
let typingTimeout: ReturnType<typeof setTimeout> | null = null
const remoteTypingUser = ref<string | null>(null)
let remoteTypingTimeout: ReturnType<typeof setTimeout> | null = null
const typingUser = ref<string | null>(null)

const { languages, fetchLanguages, getLanguageIdByCode } = useChatLanguages(baseUrl)

const MAX_ATTACHMENTS = 10

interface SelectedAttachment {
    file: File
    previewUrl: string | null
    isImage: boolean
}

const selectedFiles = ref<SelectedAttachment[]>([])
const isEmailNotif = ref(false)

const isEmailChat = computed(() => (props.session as any)?.channel === "email")

// An email is written, not chatted: Enter opens a line and the message goes when it is finished.
// A live chat is the other way round, a line at a time, so Enter still sends there.
const onEnterKey = (event: KeyboardEvent) => {
    if (isEmailChat.value) return

    event.preventDefault()
    sendMessage()
}

// Only worth offering where there is somebody to email and something to say: an email
// conversation is already an email, and a stranger who left no address cannot be written to.
const canEmailNotify = computed(() => {
    if (props.readOnly || isClosed.value || isTrashed.value || !isMyChat.value) return false
    if ((props.session as any)?.channel === "email") return false

    const session = props.session as any

    return !!session?.web_user?.customer_id
        || !!session?.guest_profile?.email
        || !!session?.metadata?.email
})

const { rejectionFor } = useUploadLimits()

const addAttachment = (file: File, isImage: boolean) => {
    if (selectedFiles.value.length >= MAX_ATTACHMENTS) {
        notify({ title: "Failed", text: "Maximum 10 attachments", type: "error" })
        return
    }

    if (isImage && !IMAGE_TYPES.includes(file.type)) {
        notify({ title: "Failed", text: "Image format not supported", type: "error" })
        return
    }

    if (!isImage && !FILE_TYPES.includes(file.type)) {
        notify({ title: "Failed", text: "File format not supported", type: "error" })
        return
    }

    // The server's own limit as well as ours, and the batch as well as the file: a batch that is
    // refused whole takes the files that were fine down with the one that was not, which is the
    // opposite of what dropping several at once should do.
    const rejection = rejectionFor(file, selectedFiles.value.map((a) => a.file), MAX_SIZE)

    if (rejection) {
        notify({ title: ctrans("File not attached"), text: `${file.name} - ${rejection}`, type: "error" })

        return
    }

    selectedFiles.value.push({
        file,
        isImage,
        previewUrl: isImage ? URL.createObjectURL(file) : null,
    })
}

const handleImageSelect = (e: Event) => {
    const files = (e.target as HTMLInputElement)?.files
    Array.from(files ?? []).forEach((file) => addAttachment(file, true))
    if (imageInput.value) imageInput.value.value = ""
}

const selectImage = (file: File) => addAttachment(file, true)

const handleDocSelect = (e: Event) => {
    const files = (e.target as HTMLInputElement)?.files
    Array.from(files ?? []).forEach((file) => addAttachment(file, false))
    if (fileInput.value) fileInput.value.value = ""
}

const selectDoc = (file: File) => addAttachment(file, false)

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

// Dropping a file on the conversation is the paperclip by another route: the same formats, the
// same ten-file limit, the same preview strip, and nothing leaves the browser until Send.
const isDraggingFile = ref(false)
let dragDepth = 0

const canAttach = computed(
    () => !props.readOnly && !isTrashed.value && !isClosed.value && !isWaiting.value && isMyChat.value
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

    Array.from(event.dataTransfer?.files ?? []).forEach((file) =>
        file.type.startsWith("image/") ? selectImage(file) : selectDoc(file)
    )
}

const removeAttachment = (index: number) => {
    const removed = selectedFiles.value[index]
    if (removed?.previewUrl) URL.revokeObjectURL(removed.previewUrl)
    selectedFiles.value.splice(index, 1)
}

const removeFile = () => {
    selectedFiles.value.forEach((attachment) => {
        if (attachment.previewUrl) URL.revokeObjectURL(attachment.previewUrl)
    })
    selectedFiles.value = []
    isEmailNotif.value = false

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
    const hasFiles = selectedFiles.value.length > 0

    if (!hasText && !hasFiles) return

    sendTypingStatus(false)
    isTyping.value = false

    const tempId = `tmp-${Date.now()}`

    const allImages = hasFiles && selectedFiles.value.every((a) => a.isImage)
    const messageType = hasFiles ? (allImages ? "image" : "file") : "text"

    const optimisticMessage: LocalChatMessage = {
        id: tempId as any,
        _tempId: tempId,
        message_text: newMessage.value ?? "",
        media_url:
            messageType === "image" ? selectedFiles.value[0]?.previewUrl : null,
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

    // The request itself is made by whoever owns this thread, so the bubble can only be told how
    // it went by being handed a way to say so. Wrapping the emit in a try/catch caught nothing:
    // emit returns before the upload has begun, and a refused upload left the bubble saying
    // "sending" for the rest of the day.
    const markFailed = (message: string) => {
        const failed = messagesLocal.value.find((m) => m._tempId === tempId)

        if (failed) {
            failed._status = "failed"
        }

        notify({ title: ctrans("Failed to send"), text: message, type: "error" })
    }

    emit("send-message", {
        text: text,
        files: selectedFiles.value.map((a) => a.file),
        message_type: messageType,
        tempId,
        is_email_notif: isEmailNotif.value,
        onFailed: markFailed,
    })

    removeFile()
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
    const events = data?.data?.events ?? []

    if (!loadMore) {
        messagesLocal.value = messages.map((m: ChatMessage) => ({
            ...m,
            _status: "sent",
        }))
        eventsLocal.value = events
    } else {
        messagesLocal.value.unshift(
            ...messages.map((m: ChatMessage) => ({ ...m, _status: "sent" }))
        )
        const known = new Set(eventsLocal.value.map((e: any) => e.id))
        eventsLocal.value = [...events.filter((e: any) => !known.has(e.id)), ...eventsLocal.value]
    }

    const page = data?.data?.pagination ?? data?.pagination
    canLoadMore.value = !!page?.has_more
    nextCursor.value = page?.next_cursor ?? null

    isLoadingMore.value = false
    if (!loadMore) {
        scrollBottom()
    }
}

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
            key: `m-${message.id}`,
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

let chatChannel: any = null

// Echo hands back the same channel object for a name already subscribed, so this pane
// and a mini chat window on the same conversation share one channel. Dropping a
// listener by event name alone would take the other component's with it, which is why
// each handler is kept and removed individually.
let onMessage: ((payload: any) => void) | null = null
let onReaction: ((payload: any) => void) | null = null
let onMessagesRead: ((payload: any) => void) | null = null
let onTyping: ((payload: any) => void) | null = null
let onTranslation: ((payload: any) => void) | null = null
let onRetracted: ((payload: any) => void) | null = null

const stopSocket = () => {
    if (onMessage) chatChannel?.stopListening(".message", onMessage)
    if (onReaction) chatChannel?.stopListening(".reaction", onReaction)
    if (onMessagesRead) chatChannel?.stopListening(".messages.read", onMessagesRead)
    if (onTyping) chatChannel?.stopListening(".typing", onTyping)
    if (onTranslation) chatChannel?.stopListening(".translation", onTranslation)
    if (onRetracted) chatChannel?.stopListening(".message.retracted", onRetracted)
    onMessage = null
    onReaction = null
    onMessagesRead = null
    onTyping = null
    onTranslation = null
    onRetracted = null
    chatChannel = null
}

const notifiedMessageIds = new Set<number>()

const initSocket = () => {
    if (!chatSession.value?.ulid || !window.Echo) return

    stopSocket()

    chatChannel = window.Echo.channel(`chat-session.${chatSession.value.ulid}`)

    // Message
    onMessage = ({ message }: any) => {
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
            notifiedMessageIds.add(message.id)
        }

        if (message.sender_type !== "agent") {
            markAsRead()
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

    onMessagesRead = (event: any) => {
        if (event.reader_type !== "agent") {
            messagesLocal.value.forEach((msg) => {
                if (event.message_ids.includes(msg.id)) {
                    msg.is_read = true
                }
            })
        }
    }

    onTyping = (payload: any) => {
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
    }

    onTranslation = async () => {
        await getMessages()
    }

    chatChannel.listen(".message", onMessage)
    chatChannel.listen(".reaction", onReaction)
    chatChannel.listen(".messages.read", onMessagesRead)
    chatChannel.listen(".typing", onTyping)
    // The broadcast carries no text: this side already holds it and keeps showing it.
    onRetracted = (payload: any) => {
        const msg: any = messagesLocal.value.find((m) => String(m.id) === String(payload?.id))
        if (msg) {
            msg.is_retracted = true
            msg.retracted_at = payload?.retracted_at ?? new Date().toISOString()
            msg.retraction_reason = payload?.retraction_reason ?? null
        }
    }

    chatChannel.listen(".translation", onTranslation)
    chatChannel.listen(".message.retracted", onRetracted)
}

const markAsRead = async () => {
    if (!chatSession.value?.ulid || props.readOnly) return
    try {
        const requestFrom = "agent"
        await axios.post(`${baseUrl}/app/api/chats/read`, {
            session_ulid: chatSession.value.ulid,
            request_from: requestFrom,
        })
        emit("messages-read")
    } catch (e) {
        console.error("Failed to mark read", e)
    }
}

const onViewMessageDetails = () => {
    isMenuOpen.value = false
    emit("view-message-details")
}

const translateConversation = async () => {
    isMenuOpen.value = false
    const languageId = layout.user?.language_id
    if (!chatSession.value?.ulid || !languageId) return

    try {
        await axios.post(`${baseUrl}/app/api/chats/sessions/${chatSession.value.ulid}/translate`, {
            target_language_id: languageId,
        })
        await getMessages()
    } catch (e) {
        console.error("Translate failed", e)
    }
}

const onViewUserProfile = () => {
    isMenuOpen.value = false
    emit("view-user-profile")
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

const selectedLanguageId = computed(() =>
    getLanguageIdByCode(selectedLanguage.value)
)

onMounted(async () => {
    await getMessages()
    await fetchLanguages()
    await getMediaUrl(chatSession.value!.ulid)
    initSocket()
    document.addEventListener("click", handleClickOutside)
    document.addEventListener("click", handleClickOutsideEmoji)
})

onUnmounted(() => {
    stopSocket()
    document.removeEventListener("click", handleClickOutside)
    document.removeEventListener("click", handleClickOutsideEmoji)
})

const handleClickOutside = (e: MouseEvent) => {
    if (isMenuOpen.value && menuRef.value && !menuRef.value.contains(e.target as Node)) {
        isMenuOpen.value = false
    }

    if (isIgnoreMenuOpen.value && ignoreMenuRef.value && !ignoreMenuRef.value.contains(e.target as Node)) {
        isIgnoreMenuOpen.value = false
    }
}
</script>

<template>
    <div class="relative flex flex-col h-full bg-white overflow-hidden"
        @dragenter="onDragEnterAttachment" @dragover="onDragOverAttachment"
        @dragleave="onDragLeaveAttachment" @drop="onDropAttachment">
        <!-- Header -->
        <header class="flex items-center gap-3 px-3 py-2 border-b">
            <button @click="$emit('back')" :aria-label="ctrans('Back')">
                <FontAwesomeIcon :icon="faArrowLeft" class="text-gray-400" fixed-width />
            </button>

            <div class="flex-1 min-w-0 cursor-pointer" @click="onViewMessageDetails">
                <div class="text-sm font-semibold truncate primary-text hover:primary-text-hover transition-colors">
                    {{ session?.guest_identifier || session?.contact_name }}
                </div>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span v-if="session?.status"
                        class="text-[10px] font-medium capitalize rounded-full px-1.5 py-0.5"
                        :class="statusBadgeClass">
                        {{ session.status }}
                    </span>
                    <span class="shrink-0 text-[9px] px-1 py-0.5 border leading-none"
                        :class="isCustomer ? 'border-green-300 text-green-500' : 'border-blue-300 text-blue-400'"
                        v-tooltip="isCustomer ? ctrans('Customer') : ctrans('Guest')">
                        {{ isCustomer ? 'C' : 'G' }}
                    </span>
                    <FontAwesomeIcon :icon="channelIcon" class="shrink-0 text-[11px]" :class="channelIconClass" fixed-width />
                    <!-- Narrow, only the age survives: it is what a waiting conversation is
                         judged by, and the full date crowded out the buttons beside it. -->
                    <span v-if="lastMessageStamp" class="shrink-0 text-[11px] text-gray-400"
                        v-tooltip="ctrans('Last message') + ': ' + lastMessageStamp.time">
                        <span class="hidden xl:inline">{{ lastMessageStamp.time }} </span>
                        <span class="text-gray-300">{{ lastMessageStamp.age }}</span>
                    </span>
                    <span v-if="showShop && session?.shop?.name" class="text-[11px] text-gray-400 truncate">
                        {{ session.shop.name }}
                    </span>
                </div>
            </div>

            <!-- Also offered on a conversation nobody has taken: an out of office reply or a
                 supplier's newsletter needs disposing of, and having to assign it to yourself
                 first to close it is why they pile up in the waiting queue. -->
            <ModalConfirmationDelete v-if="canEndChat" :routeDelete="{
                name: 'grp.org.chat.agents.sessions.close',
                parameters: [session?.organisation.id, session?.ulid],
                method: 'patch',
            }" :title="ctrans('Are you sure you want to end this chat?')"
                :noLabel="ctrans('End chat')"
                :noIcon="faTimesCircle"
                :description="ctrans('This closes the chat. Nothing is deleted, and it can be reopened.')"
                @success="$emit('close-session')">
                <template #default="{ changeModel }">
                    <Button
                        type="red"
                        :label="ctrans('End chat')"
                        @click="changeModel"
                        icon="fal fa-times-circle"
                        size="xs"
                        key="3"
                    />
                    <!-- <button @click="changeModel"
                        class="inline-flex items-center justify-center gap-1.5 shrink-0 h-7 px-2.5 text-[11px] font-medium rounded-md transition hover:opacity-90"
                        :class="isMyChat ? '' : 'border border-gray-300 text-gray-600 hover:bg-gray-100'"
                        :style="isMyChat ? { backgroundColor: 'var(--theme-color-4)', color: 'var(--theme-color-5)' } : {}">
                        <FontAwesomeIcon :icon="faTimesCircle" class="text-[11px]" fixed-width />
                        {{ ctrans("End chat") }}
                    </button> -->
                </template>
            </ModalConfirmationDelete>

            <!-- Out in the open, not behind the dots: clearing the queue is most of the work on
                 an imported mailbox, and a choice nobody finds does not get made. -->
            <button v-if="canIgnore && (session as any)?.is_rubbish" type="button" :disabled="isSpamMarking"
                class="inline-flex items-center gap-1.5 shrink-0 h-7 px-2.5 text-[11px] font-medium rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-100 disabled:opacity-50"
                @click="markRubbish(false)">
                <FontAwesomeIcon :icon="faRotateLeft" class="text-[11px]" fixed-width />
                {{ ctrans("Not ignored") }}
            </button>

            <div v-else-if="canIgnore" class="relative shrink-0" ref="ignoreMenuRef">
                <button type="button" :disabled="isSpamMarking"
                    v-tooltip="ctrans('Nothing to answer here. Only this conversation, and it can be undone.')"
                    class="inline-flex items-center gap-1.5 h-7 px-2.5 text-[11px] font-medium rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-100 disabled:opacity-50"
                    @click.stop="isIgnoreMenuOpen = !isIgnoreMenuOpen">
                    <FontAwesomeIcon :icon="faArchive" class="text-[11px]" fixed-width />
                    {{ ctrans("Ignore") }}
                    <FontAwesomeIcon :icon="faAngleDown" class="text-[9px] text-gray-400" fixed-width />
                </button>

                <div v-if="isIgnoreMenuOpen" class="absolute right-0 mt-1 w-56 bg-white border rounded-md shadow z-50 py-1">
                    <div class="px-3 pb-1 text-[10px] uppercase tracking-wide text-gray-400">
                        {{ ctrans("Ignore as") }}
                    </div>
                    <button v-for="reason in (ignoreReasons ?? [])" :key="reason.value" type="button"
                        class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                        @click="isIgnoreMenuOpen = false; markRubbish(true, reason.value)">
                        {{ reason.label }}
                    </button>
                </div>
            </div>

            <!-- Spam is never offered on a customer: it blocks the address for good, and the same
                 customer writes again next week. -->
            <button v-if="canReportSpam" type="button" :disabled="isSpamMarking"
                v-tooltip="ctrans('Blocks this sender. Everything they send from now on goes to spam.')"
                class="inline-flex items-center gap-1.5 shrink-0 h-7 px-2.5 text-[11px] font-medium rounded-md border border-red-200 text-red-600 transition hover:bg-red-50 disabled:opacity-50"
                @click="markSpam(!(session as any)?.is_spam)">
                <FontAwesomeIcon :icon="(session as any)?.is_spam ? faRotateLeft : faBan" class="text-[11px]" fixed-width />
                {{ (session as any)?.is_spam ? ctrans("Not spam") : ctrans("Spam") }}
            </button>

            <!-- What is still outstanding on this conversation, one click from the thread: an agent
                 about to close a chat should not have to go looking for what is holding it. -->
            <button v-if="openTicketsCount && hasTicketsPanel" type="button" v-tooltip="openTicketsTooltip"
                :aria-label="openTicketsTooltip"
                class="inline-flex items-center gap-1.5 shrink-0 h-7 px-2.5 text-[11px] font-medium rounded-md border transition"
                :class="blockingTicketsCount ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' : 'border-gray-300 text-gray-600 hover:bg-gray-100'"
                @click="onViewTickets">
                <FontAwesomeIcon :icon="blockingTicketsCount ? faLock : faLifeRing" class="text-[11px]" fixed-width />
                {{ openTicketsCount }}
            </button>

            <button type="button" v-tooltip="ctrans('Customer details')" :aria-label="ctrans('Customer details')"
                class="inline-flex items-center justify-center shrink-0 h-7 w-7 rounded-md border border-gray-300 text-gray-600 transition hover:bg-gray-100"
                @click="onViewUserProfile">
                <FontAwesomeIcon :icon="faUser" class="text-[11px]" fixed-width />
            </button>

            <div class="relative" ref="menuRef">
                <button @click.stop="isMenuOpen = !isMenuOpen" :aria-label="ctrans('Toggle menu')">
                    <FontAwesomeIcon :icon="faEllipsisVertical" class="text-gray-400" fixed-width />
                </button>

                <div v-if="isMenuOpen"
                    class="absolute right-0 mt-2 w-56 bg-white border rounded-md shadow z-50">
                    <button class="menu-item" @click="onViewUserProfile">
                        <FontAwesomeIcon :icon="faUser" fixed-width /> {{ ctrans("View Profile") }}
                    </button>

                    <button class="menu-item" @click="translateConversation">
                        <FontAwesomeIcon :icon="faLanguage" fixed-width /> {{ ctrans("Translate conversation") }}
                    </button>

                    <button v-if="hasMessageDetailsPanel" class="menu-item" @click="onViewMessageDetails">
                        <FontAwesomeIcon :icon="faMessage" fixed-width /> {{ ctrans("Message Details") }}
                    </button>

                    <template v-if="!readOnly && !isTrashed">
                        <button v-if="canEmailNotify" class="menu-item" @click="isEmailNotif = !isEmailNotif">
                            <!-- The badge sits on the envelope's corner, with a white disc behind it so
                                 the two shapes stay separate instead of bleeding into one another. -->
                            <!-- Two tones and a white disc between them: the envelope pale, the badge
                                 dark, or the two shapes read as one blot at this size. -->
                            <FontAwesomeLayers class="h-4 w-4 shrink-0">
                                <FontAwesomeIcon :icon="faEnvelope" class="text-[0.9em]"
                                    :class="isEmailNotif ? 'text-green-400' : 'text-red-300'" fixed-width />
                                <FontAwesomeIcon :icon="faCircle" class="text-[0.7em] text-white translate-x-[0.5em] -translate-y-[0.4em]" fixed-width />
                                <FontAwesomeIcon :icon="faExclamationCircle" class="text-[0.55em] translate-x-[0.5em] -translate-y-[0.4em]"
                                    :class="isEmailNotif ? 'text-green-700' : 'text-red-600'" fixed-width />
                            </FontAwesomeLayers>
                            {{ ctrans("Email notification:") }}
                            <span :class="isEmailNotif ? 'font-medium text-green-600' : 'text-gray-500'">
                                {{ isEmailNotif ? ctrans("On") : ctrans("Off") }}
                            </span>
                        </button>

                        <button class="menu-item disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canDispose"
                            v-tooltip="canDispose ? undefined : heldByAnotherAgent" @click="openTicketModal">
                            <FontAwesomeIcon :icon="faLifeRing" class="text-blue-600" fixed-width /> {{ ctrans("Create Ticket") }}
                        </button>

                        <button v-if="canRelease" class="menu-item" :disabled="isReleasing" @click="releaseChat">
                            <FontAwesomeIcon :icon="faRotateLeft" class="text-amber-600" fixed-width />
                            <span class="text-left">{{ ctrans("Give it back to the queue") }}</span>
                        </button>

                        <button class="menu-item" @click="openForwardModal">
                            <FontAwesomeIcon :icon="faShare" class="text-teal-600" fixed-width /> {{ ctrans("Forward to a colleague") }}
                        </button>

                        <button class="menu-item" @click="openSlackModal">
                            <FontAwesomeIcon :icon="faSlack" class="text-purple-600" fixed-width /> {{ ctrans("Share to Slack") }}
                        </button>

                    </template>
                </div>
            </div>
        </header>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto px-3 py-2 space-y-3 bg-[#F0F4F8]">
            <div class="flex justify-center" v-if="canLoadMore && nextCursor">
                <button @click="getMessages(true)" :disabled="isLoadingMore" class="flex items-center gap-2 text-xs text-gray-600 px-4 py-1.5
               border rounded-full hover:bg-gray-100 disabled:opacity-50">
                    <FontAwesomeIcon v-if="isLoadingMore" :icon="faSpinner" class="animate-spin text-[10px]" fixed-width />
                    <span>
                        {{ isLoadingMore ? 'Loading messages…' : 'Load older messages' }}
                    </span>
                </button>
            </div>

            <template v-for="(entries, date) in groupedTimeline" :key="date">
                <div class="text-center text-xs text-gray-400">{{ date }}</div>
                <template v-for="entry in entries" :key="entry.key">
                    <ChatTimelineEvent v-if="entry.kind === 'event'" :event="entry.event" />
                    <div v-else class="flex rounded-lg transition-colors"
                        :data-message-id="entry.message.id"
                        :class="entry.message.sender_type === 'agent' ? 'justify-end' : 'justify-start'">
                        <BubbleChat :message="entry.message" viewerType="agent"
                            :contactName="session?.contact_name || session?.guest_identifier"
                            :agentName="session?.assigned_agent?.name"
                            :canEdit="isMyChat && !isClosed && !isWaiting"
                            :sessionUlid="session?.ulid"
                            :viewerReactorId="layout?.user?.id"
                            @retract-message="handleRetractMessage"
                            @redact-message="handleRedactMessage"
                            @redact-attachment="handleRedactAttachment"
                            @jump-to-message="jumpToMessage"
                            @open-slack-settings="onOpenSlackSettings" />
                    </div>
                </template>
            </template>
        </div>
        <div v-if="remoteTypingUser" class="text-xs text-gray-400 italic px-2 py-1">
            {{ remoteTypingUser }} {{ ctrans("is typing...") }}
        </div>

        <div v-if="selectedFiles.length" class="px-3 pb-2 flex flex-wrap gap-2">
            <div v-for="(attachment, index) in selectedFiles" :key="index" class="relative">
                <template v-if="attachment.isImage && attachment.previewUrl">
                    <img :src="attachment.previewUrl" class="h-24 rounded-lg border object-cover" />
                    <button @click="removeAttachment(index)" class="absolute -top-2 -right-2 bg-white rounded-full shadow p-1" :aria-label="ctrans('Remove image')">
                        <FontAwesomeIcon :icon="faXmark" fixed-width />
                    </button>
                </template>

                <div v-else class="flex items-center gap-3 border rounded-lg p-3 bg-gray-50 min-w-0 max-w-[220px]">
                    <div class="text-2xl">
                        <FontAwesomeIcon :icon="faFilePdf" fixed-width />
                    </div>
                    <div class="flex-1 min-w-0 overflow-hidden">
                        <div class="text-sm font-medium truncate">
                            {{ attachment.file.name }}
                        </div>
                        <div class="text-xs text-gray-400">
                            {{ (attachment.file.size / 1024).toFixed(1) }} KB
                        </div>
                    </div>
                    <button @click="removeAttachment(index)" class="text-gray-400 hover:text-red-500 shrink-0 ml-2" :aria-label="ctrans('Remove file')">
                        <FontAwesomeIcon :icon="faXmark" fixed-width />
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer: Restore banner for trashed chats -->
        <footer v-if="readOnly" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-center gap-2 text-xs text-gray-500">
                <FontAwesomeIcon :icon="faEye" class="text-gray-400" fixed-width aria-hidden="true" />
                {{ ctrans("You are viewing this conversation in read-only mode") }}
            </div>
        </footer>

        <footer v-else-if="isTrashed" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200">
                <div class="text-xs text-gray-600">
                    {{ ctrans('This chat is in trash') }}
                </div>
                <Button
                    @click="restoreChat"
                    :loading="isRestoring"
                    style="primary"
                    size="xs"
                    :label="ctrans('Restore')"
                    :icon="faRotateRight"
                />
            </div>
        </footer>

        <!-- Footer: Reopen banner for closed chats -->
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
                    :icon="['far', 'fa-user']"
                />
            </div>
        </footer>

        <!-- Footer: Takeover banner for team chats -->
        <footer v-else-if="!isMyChat" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-indigo-50 border border-indigo-200">
                <div class="text-xs text-indigo-600">
                    <span class="font-semibold">{{ props.session?.assigned_agent?.name }}</span>
                    {{ ctrans(' is handling this chat') }}
                </div>
                <Button
                    @click="takeoverChat"
                    :loading="isTakingOver"
                    style="primary"
                    size="xs"
                    :label="ctrans('Take Over')"
                    :icon="['far', 'fa-user']"
                />
            </div>
        </footer>

        <!-- Footer: Normal message input -->
        <footer v-else class="px-3 py-2 bg-white">
            <input ref="imageInput" type="file" accept=".webp,.jpg,.jpeg,.png,.avif" multiple class="hidden"
                @change="handleImageSelect" />
            <input ref="fileInput" type="file" accept=".pdf,.xls,.xlsx" multiple class="hidden" @change="handleDocSelect" />

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm focus-within:border-gray-400 focus-within:shadow-md transition-shadow">
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
                " @paste="onPasteAttachment" @keydown.enter.exact="onEnterKey"
                    @keydown.enter.meta.prevent="sendMessage" @keydown.enter.ctrl.prevent="sendMessage" rows="1"
                    :placeholder="isEmailChat ? ctrans('Type your reply, Ctrl+Enter to send') : 'Type message...'"
                    class="w-full resize-none px-4 pt-3 pb-1 text-sm leading-5 outline-none border-none ring-0 focus:outline-none focus:ring-0 rounded-t-xl bg-transparent" />

                <div class="flex items-center justify-between px-2 pb-2 pt-1">
                    <div class="flex items-center gap-1">
                        <button @click="imageInput?.click()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors" v-tooltip="ctrans('Upload image')" :aria-label="ctrans('Upload image')">
                            <FontAwesomeIcon :icon="faImage" class="text-sm" fixed-width />
                        </button>
                        <button @click="fileInput?.click()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors" v-tooltip="ctrans('Upload file')" :aria-label="ctrans('Upload file')">
                            <FontAwesomeIcon :icon="faPaperclip" class="text-sm" fixed-width />
                        </button>
                        <div ref="emojiPickerContainer" class="relative">
                            <button type="button" @click.stop="showEmojiPicker = !showEmojiPicker"
                                class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors"
                                :class="showEmojiPicker ? 'text-indigo-600 bg-gray-100' : 'text-gray-500'"
                                v-tooltip="ctrans('Emoji')" :aria-label="ctrans('Emoji')">
                                <FontAwesomeIcon :icon="faFaceSmile" class="text-sm" fixed-width />
                            </button>

                            <div v-if="showEmojiPicker" class="absolute bottom-full left-0 mb-1 z-30">
                                <EmojiPicker @pick="pickEmoji" />
                            </div>
                        </div>
                        <button @click="openTicketModal"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-blue-50 text-gray-500 hover:text-blue-600 transition-colors" v-tooltip="ctrans('Create ticket')" :aria-label="ctrans('Create ticket')">
                            <FontAwesomeIcon :icon="faLifeRing" class="text-sm" fixed-width />
                        </button>
                    </div>
                    <Button @click="sendMessage" :icon="faPaperPlane" :tooltip="ctrans('Send message')"></Button>
                </div>
            </div>
        </footer>

        <TicketModal
            :is-open="isTicketModalOpen"
            :session="session"
            :organisation="currentOrganisation"
            @created="onTicketCreated"
            @close="isTicketModalOpen = false"
        />

        <ForwardToColleagueModal
            :is-open="isForwardModalOpen"
            :organisation="currentOrganisation"
            :session-ulid="session?.ulid"
            @close="isForwardModalOpen = false"
        />

        <SlackShareModal
            :is-open="isSlackModalOpen"
            mode="session"
            :organisation="currentOrganisation"
            :session-ulid="session?.ulid"
            @close="isSlackModalOpen = false"
            @open-settings="onOpenSlackSettings"
        />

        <!-- Nothing here takes the pointer, so the drag keeps reaching the pane underneath and
             the drop still lands. -->
        <div v-if="isDraggingFile"
            class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center bg-white/75 p-6">
            <div class="flex flex-col items-center gap-2 rounded-xl border-2 border-dashed border-sky-400 bg-white px-10 py-8 shadow-sm">
                <FontAwesomeIcon :icon="faPaperclip" class="text-2xl text-sky-500" fixed-width />
                <div class="text-sm font-medium text-gray-700">{{ ctrans("Drop the files here") }}</div>
                <div class="text-xs text-gray-400">
                    {{ ctrans("Images, PDF and spreadsheets, up to 10 at a time, 10MB each") }}
                </div>
            </div>
        </div>
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
