<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, onMounted, onUnmounted, reactive, ref, watch } from "vue"
import { usePage, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { formatDistanceToNow } from "date-fns"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faChevronDown, faPaperPlane, faChevronLeft, faQuoteLeft, faPaperclip, faSmile, faExpandAlt, faCheck, faCheckDouble, faExclamationCircle, faComments, faCheckCircle, faCircle, faSpinner, faBan, faEye, faEyeSlash, faChevronRight, faLink, faExternalLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Common/Components/Image.vue"
import FilesAndLinksPanel, { type SharedItem } from "@/Components/Messaging/FilesAndLinksPanel.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { useLiveUsers } from "@/Stores/active-users"
import { callEntryText as callEntryTextFor, deliveredAtOf, tickStateOf, useStaffMessaging, type StaffConversation, type StaffConversationTask, type StaffMessage, type StaffParticipant } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"
import { formatWhatsappMarkup } from "@/Composables/useWhatsappMarkup"
import { useStaffTaskMembers } from "@/Composables/useStaffTaskMembers"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { Drawer, Popover } from "primevue"
import StaffProfileTrigger from "@/Components/Messaging/StaffProfileTrigger.vue"
import StaffTaskSubtaskProgress from "@/Components/Tasks/StaffTaskSubtaskProgress.vue"
import StaffTaskChatMembers from "@/Components/Tasks/StaffTaskChatMembers.vue"
import { canShareScreen, pickScreen, useStaffCalls } from "@/Stores/staff-calls"
import StaffMessageTicks from "@/Components/Messaging/StaffMessageTicks.vue"
import DeskMessageEditor, { type DeskPerson } from "@/Components/Messaging/DeskMessageEditor.vue"
import DeskMessageToolbar, { type DeskToolbarMenuItem } from "@/Components/Messaging/DeskMessageToolbar.vue"
import { STAFF_MESSAGE_COUNT_SHOWN_FROM, STAFF_MESSAGE_MAX_CHARACTERS, staffMessageCharacters } from "@/Components/Messaging/staffMessageLimit"
import { useLocaleStore } from "@/Stores/locale"
import { faTasks, faCommentDots, faFolder } from "@fal"
import { faPhone, faPhoneSlash, faVideo, faDesktop } from "@fal"
import ChatWallpaperButton from "@/Components/Messaging/ChatWallpaperButton.vue"
import { hasWallpaper, useBubbles, wallpaperStyle } from "@/Components/Messaging/chatWallpaper"

library.add(faTimes, faChevronDown, faPaperPlane, faChevronLeft, faQuoteLeft, faPaperclip, faSmile, faExpandAlt, faCheck, faCheckDouble, faExclamationCircle, faComments, faCheckCircle, faCircle, faSpinner, faBan, faEye, faEyeSlash, faChevronRight, faLink, faExternalLink)

const GifPicker = defineAsyncComponent(() => import("./GifPicker.vue"))
const EmojiPicker = defineAsyncComponent(() => import("./EmojiPicker.vue"))
const StaffTaskDialog = defineAsyncComponent(() => import("@/Components/Tasks/StaffTaskDialog.vue"))

const props = defineProps<{
    conversation: StaffConversation
    fullScreen?: boolean
    embedded?: boolean
    desk?: boolean
}>()

const emit = defineEmits<{
    close: []
    minimise: []
    back: []
}>()

const store = useStaffMessaging()
const calls = useStaffCalls()
library.add(faPhone, faPhoneSlash, faVideo, faDesktop, faTasks, faCommentDots, faFolder)

const isDesk = computed(() => !!props.desk)

const liveCall = computed(() => calls.liveCallIn(props.conversation.ulid))
const isInThisCall = computed(() => calls.active?.call.conversation_ulid === props.conversation.ulid)
const canStartCall = computed(() => !!props.conversation.can_call && !props.embedded && !calls.active && !calls.joining)
const callError = computed(() => calls.error?.conversationUlid === props.conversation.ulid ? calls.error.text : null)
const showScreenCallButton = canShareScreen()

const startScreenCall = () => {
    if (!canStartCall.value) return
    const ulid = props.conversation.ulid
    pickScreen().then((stream) => calls.start(ulid, "screen", stream)).catch(() => { })
}

const callEntryText = (call: NonNullable<StaffMessage["call"]>) => callEntryTextFor(call, myId.value)

const isDescriptionOpen = ref(false)

const isListedWorkChat = computed(() => !!props.conversation?.context_type && store.conversations.some((listed) => listed.ulid === props.conversation?.ulid))
const canToggleWatch = computed(() => !props.embedded && isListedWorkChat.value)
const listedConversation = computed(() => store.conversations.find((listed) => listed.ulid === props.conversation?.ulid))
const myId = computed(() => usePage().props?.auth?.user?.id)
const myLanguageId = computed(() => usePage().props?.auth?.user?.language_id ?? null)

const participants = computed<StaffParticipant[]>(() => props.conversation?.participants ?? [])

const messages = computed(() => store.messagesByUlid[props.conversation.ulid] ?? [])

const isGroupChat = computed(() => props.conversation.type === "group")

const isLoadingFirstMessages = computed(() => !messages.value.length && (store.loadingMessages[props.conversation.ulid] || store.messagesByUlid[props.conversation.ulid] === undefined))
const hasNoMessages = computed(() => !messages.value.length && !isLoadingFirstMessages.value)
const RUN_GAP_MS = 5 * 60 * 1000

const isSameRun = (previous: StaffMessage | undefined, message: StaffMessage | undefined) =>
    !!previous && !!message && previous.user_id === message.user_id
    && new Date(message.created_at).getTime() - new Date(previous.created_at).getTime() < RUN_GAP_MS

const startsRun = (index: number) => !isSameRun(messages.value[index - 1], messages.value[index])
const endsRun = (index: number) => !isSameRun(messages.value[index], messages.value[index + 1])

const participantById = computed(() => new Map(participants.value.map((participant) => [participant.id, participant])))
const senderAvatar = (message: StaffMessage) => participantById.value.get(message.user_id)?.avatar ?? null

const SENDER_NAME_COLOURS = ["text-teal-700", "text-violet-700", "text-orange-700", "text-sky-700", "text-rose-700", "text-emerald-700", "text-fuchsia-700", "text-amber-700"]
const senderNameClass = (userId: number) => SENDER_NAME_COLOURS[userId % SENDER_NAME_COLOURS.length]

const isMine = (message: StaffMessage) => message.user_id === myId.value

const readersOf = (message: StaffMessage) => {
    const sentAt = Date.parse(message.created_at)
    return otherParticipants.value.filter((participant) => participant.last_read_at && Date.parse(participant.last_read_at) >= sentAt)
}

const receiversOf = (message: StaffMessage) => {
    const sentAt = Date.parse(message.created_at)
    return otherParticipants.value.filter((participant) => deliveredAtOf(participant) >= sentAt)
}

const tickOf = (message: StaffMessage) => tickStateOf(otherParticipants.value, message.created_at)

const showsMessageMeta = (message: StaffMessage, index: number) => !isGroupChat.value || endsRun(index) || !!message.client_status

const ticksTooltip = (message: StaffMessage) => {
    const state = tickOf(message)
    if (!isGroupChat.value) return state === "read" ? ctrans("Read") : state === "received" ? ctrans("Received") : ctrans("Sent")
    if (state === "read") return ctrans("Read by everyone · click to see")
    if (state === "received") return ctrans("Received by everyone, :read of :total read · click to see who", { read: String(readersOf(message).length), total: String(otherParticipants.value.length) })
    return ctrans(":read of :total read · click to see who", { read: String(readersOf(message).length), total: String(otherParticipants.value.length) })
}

const readInfoPopover = ref()
const readInfoMessage = ref<StaffMessage | null>(null)

const openReadInfo = (event: Event, message: StaffMessage) => {
    if (!isGroupChat.value) return
    readInfoMessage.value = message
    readInfoPopover.value?.toggle(event)
}

const readInfo = computed(() => {
    if (!readInfoMessage.value) return { read: [], received: [], unread: [] }
    const readers = readersOf(readInfoMessage.value)
    const readerIds = new Set(readers.map((participant) => participant.id))
    const receiverIds = new Set(receiversOf(readInfoMessage.value).map((participant) => participant.id))

    return {
        read: [...readers].sort((a, b) => Date.parse(a.last_read_at!) - Date.parse(b.last_read_at!)),
        received: otherParticipants.value.filter((participant) => !readerIds.has(participant.id) && receiverIds.has(participant.id)),
        unread: otherParticipants.value.filter((participant) => !readerIds.has(participant.id) && !receiverIds.has(participant.id)),
    }
})

const shortSenderName = (name: string) => {
    const [firstName, ...otherNames] = name.trim().split(/\s+/)
    return otherNames.length ? `${firstName} ${otherNames.map((part) => `${part.charAt(0).toUpperCase()}.`).join("")}` : firstName
}
const isOnline = computed(() =>
    participants.value.some((p) => p.id !== myId.value && !!useLiveUsers().liveUsers[p.id])
)

const otherParticipants = computed(() => participants.value.filter((p) => p.id !== myId.value))
const displayName = computed(() => props.conversation.name || otherParticipants.value.map((p) => p.name).join(", "))
const displayAvatar = computed(() => otherParticipants.value[0]?.avatar ?? null)
const lastSeenAt = computed(() => props.conversation.type === "dm" && otherParticipants.value[0]?.last_seen_at ? otherParticipants.value[0].last_seen_at : null)

const ACTIVE_WINDOW_MS = 15 * 60 * 1000
const now = ref(Date.now())
let nowInterval: ReturnType<typeof setInterval> | null = null
const isIdle = computed(() => isOnline.value && !!lastSeenAt.value && now.value - new Date(lastSeenAt.value).getTime() >= ACTIVE_WINDOW_MS)

const freshTaskInfo = ref<StaffConversationTask | null>(null)
const taskInfo = computed(() => freshTaskInfo.value ?? props.conversation.task ?? null)
watch(() => props.conversation.ulid, () => (freshTaskInfo.value = null))

const refreshTaskInfo = async () => {
    if (!taskInfo.value) return
    const { data } = await axios.get(route("grp.tasks.conversation", taskInfo.value.reference))
    freshTaskInfo.value = data.data.task ?? null
    store.mergeConversation(data.data)
}

const leftAt = computed(() => props.conversation.my_left_at ?? null)

const isTaskClosed = computed(() => !!taskInfo.value && !taskInfo.value.is_open)
const isEndingChat = ref(false)

// A task that is done no longer needs its chat in everybody's list (INI-045): ending it clears it from mine, the
// thread itself stays on the task page.
const endChat = async () => {
    isEndingChat.value = true
    try {
        await refreshTaskInfo()
    } catch {
        // The archive call checks the task again on the server.
    }
    store.closeConversation(props.conversation.ulid)
    isEndingChat.value = false
    if (props.embedded) {
        notify({ title: ctrans("Chat ended"), text: ctrans("It is cleared from your chats and stays here on the task."), type: "success" })
    }
}

useLiveStaffTasks(refreshTaskInfo, (event) => !props.embedded && event.reference === taskInfo.value?.reference)

const isWallpapered = computed(() => hasWallpaper(props.conversation.wallpaper))
const bubbles = useBubbles()
const floatsHeader = computed(() => isWallpapered.value && !!props.fullScreen && !props.embedded)
const theirBubbleClass = computed(() => (isWallpapered.value ? "bg-white text-gray-900 shadow-sm" : "bg-gray-100 text-gray-900"))

const onWallpaperSaved = (wallpaper: StaffConversation["wallpaper"], isSet: boolean) => {
    Object.assign(props.conversation, { wallpaper, wallpaper_is_set: isSet })
    const listed = store.conversations.find((conversation) => conversation.ulid === props.conversation.ulid)
    if (listed && listed !== props.conversation) Object.assign(listed, { wallpaper, wallpaper_is_set: isSet })
}

const reactsOnHover = computed(() => !props.fullScreen || (props.embedded && window.matchMedia("(hover: hover)").matches))

const isTaskMembersOpen = ref(false)

const { members: taskMembers, onlineCount: taskOnlineCount } = useStaffTaskMembers(
    () => participants.value,
    () => ({
        requesterId: taskInfo.value?.requester_id ?? null,
        assigneeId: taskInfo.value?.assignee_id ?? null,
        collaboratorIds: taskInfo.value?.collaborator_ids ?? [],
    })
)

const newMessage = ref("")
const locale = useLocaleStore()
const messageCharacters = computed(() => staffMessageCharacters(newMessage.value))
const isMessageTooLong = computed(() => messageCharacters.value > STAFF_MESSAGE_MAX_CHARACTERS)
const showsMessageLength = computed(() => messageCharacters.value >= STAFF_MESSAGE_COUNT_SHOWN_FROM)
const messageLengthId = computed(() => `staff-message-length-${props.conversation.ulid}`)
const messageLengthText = computed(() => {
    const replacements = { count: locale.number(messageCharacters.value), max: locale.number(STAFF_MESSAGE_MAX_CHARACTERS) }

    return isMessageTooLong.value
        ? ctrans("Too long to send: :count of :max characters. Shorten it or send it in parts.", replacements)
        : ctrans(":count of :max characters. Longer messages cannot be sent.", replacements)
})
const mentionPeople = computed<DeskPerson[]>(() =>
    participants.value
        .filter((participant) => participant.id !== myId.value)
        .map((participant) => ({ id: String(participant.id), label: participant.handle ?? participant.name ?? "", avatar: participant.avatar ?? null }))
        .filter((person) => person.label))
const composer = ref<InstanceType<typeof DeskMessageEditor> | null>(null)
const textarea = ref<HTMLTextAreaElement | null>(null)
const mentionQuery = ref<string | null>(null)
const mentionActiveIndex = ref(0)
const mentionMatches = computed(() => {
    if (mentionQuery.value === null) return []
    const q = mentionQuery.value.toLowerCase()
    return participants.value
        .filter((p) => p.id !== myId.value && (p.name?.toLowerCase().startsWith(q) || p.handle?.toLowerCase().startsWith(q)))
        .slice(0, 6)
})
const showMentionPopup = computed(() => mentionMatches.value.length > 0)

const updateMentionQuery = () => {
    const el = textarea.value
    if (!el) {
        mentionQuery.value = null
        return
    }
    const caret = el.selectionStart ?? newMessage.value.length
    const uptoCaret = newMessage.value.slice(0, caret)
    const match = uptoCaret.match(/(?:^|\s)@([\p{L}\p{N}._-]*)$/u)
    mentionQuery.value = match ? match[1] : null
    mentionActiveIndex.value = 0
}

const selectMention = (participant: StaffConversation["participants"][number]) => {
    const el = textarea.value
    const handle = participant.handle ?? participant.name
    if (!el) return
    const caret = el.selectionStart ?? newMessage.value.length
    const uptoCaret = newMessage.value.slice(0, caret)
    const replaced = uptoCaret.replace(/@([\p{L}\p{N}._-]*)$/u, `@${handle} `)
    newMessage.value = replaced + newMessage.value.slice(caret)
    mentionQuery.value = null
    nextTick(() => {
        el.focus()
        const pos = replaced.length
        el.setSelectionRange(pos, pos)
    })
}

const onMentionKeydown = (event: KeyboardEvent) => {
    if (!showMentionPopup.value) return false
    if (event.key === "ArrowDown") {
        event.preventDefault()
        mentionActiveIndex.value = (mentionActiveIndex.value + 1) % mentionMatches.value.length
        return true
    }
    if (event.key === "ArrowUp") {
        event.preventDefault()
        mentionActiveIndex.value = (mentionActiveIndex.value - 1 + mentionMatches.value.length) % mentionMatches.value.length
        return true
    }
    if (event.key === "Enter" || event.key === "Tab") {
        event.preventDefault()
        selectMention(mentionMatches.value[mentionActiveIndex.value])
        return true
    }
    if (event.key === "Escape") {
        mentionQuery.value = null
        return true
    }
    return false
}

const autoResize = () => {
    const el = textarea.value
    if (!el) return
    el.style.height = "auto"
    el.style.height = Math.min(el.scrollHeight, 120) + "px"
}

const focusBox = () => (isDesk.value ? composer.value?.focus() : textarea.value?.focus())

const escapeHtml = (text: string) =>
    text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")

const renderBody = (message: StaffMessage, text: string) => {
    const handles = new Set(
        participants.value.map((p) => p.handle).filter((h): h is string => !!h)
    )
    const own = message.user_id === myId.value && !isDesk.value
    const cls = isDesk.value ? "rounded bg-[#1d9bd11a] px-0.5 text-[#1264a3]" : own ? "font-medium text-yellow-200" : "font-medium text-[--app-accent-strong]"
    const linkCls = isDesk.value
        ? "text-[#1264a3] hover:underline"
        : own
            ? "text-sky-200 underline decoration-sky-200/60 underline-offset-2 hover:text-sky-100 hover:decoration-sky-100"
            : "text-sky-600 underline decoration-sky-600/50 underline-offset-2 hover:text-sky-700"
    const withMentions = (segment: string) => segment.replace(/@([\p{L}\p{N}._-]+)/gu, (full, handle) =>
        handles.has(handle) ? `<span class="${cls}">@${escapeHtml(handle)}</span>` : full
    )

    if (isDesk.value) {
        const codes: string[] = []
        const keep = (html: string) => `\u0001${codes.push(html) - 1}\u0001`
        const withoutCode = text
            .replace(/\n?```([\s\S]+?)```\n?/g, (_, code: string) => keep(`<pre class="my-1 whitespace-pre-wrap break-words rounded border border-[#1d1c1d21] bg-[#f8f8f8] px-2 py-1.5 font-mono text-[13px]">${escapeHtml(code.replace(/^\n|\n$/g, ""))}</pre>`))
            .replace(/`([^`\n]+)`/g, (_, code: string) => keep(`<code class="rounded border border-[#1d1c1d21] bg-[#f8f8f8] px-1 font-mono text-[0.85em] text-[#e01e5a]">${escapeHtml(code)}</code>`))
        const html = formatWhatsappMarkup(withoutCode)
            .split(/(<a [^>]*>.*?<\/a>)/g)
            .map((segment, index) => (index % 2 === 0 ? withMentions(segment) : segment.replace('class="underline break-all"', `class="break-all ${linkCls}"`)))
            .join("")
            .replace(/(?:^|\n)(?:&gt; ?[^\n]*(?:\n|$))+/g, (block: string) => {
                const lead = block.startsWith("\n") ? "\n" : ""
                const lines = block.replace(/^\n/, "").replace(/\n$/, "").split("\n").map((line) => line.replace(/^&gt; ?/, ""))
                return `${lead}<blockquote class="border-l-4 border-[#dddddd] pl-3">${lines.join("\n")}</blockquote>`
            })
        return html.replace(/\u0001(\d+)\u0001/g, (_, index) => codes[Number(index)])
    }

    return escapeHtml(text)
        .split(/(https?:\/\/[^\s<>"']+)/g)
        .map((segment, index) => {
            if (index % 2 === 0) return withMentions(segment)
            const trailing = segment.match(/[.,;:!?)\]]+$/)?.[0] ?? ""
            const url = trailing ? segment.slice(0, -trailing.length) : segment
            return `<a href="${url}" target="_blank" rel="noopener noreferrer" class="break-all ${linkCls}">${url}</a>${trailing}`
        })
        .join("")
}
const pendingImage = ref<File | null>(null)
const pendingImagePreview = ref<string | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const parentMessage = ref<StaffMessage | null>(null)
const showOriginal = ref<Record<number, boolean>>({})
const messagesContainer = ref<HTMLElement | null>(null)
const activeReactionFor = ref<number | null>(null)
const showGifPicker = ref(false)
const showEmojiPicker = ref(false)
const REACTION_EMOJIS = ["👍", "✅", "❌", "👀", "🙏", "🔥"]
const gifState = reactive<Record<string, "loaded" | "failed">>({})

const quickReplies = computed(
    () =>
        usePage().props?.layout?.staff_chat?.quick_replies ?? [
            ctrans("Done"),
            ctrans("Help!"),
            ctrans("Call me"),
            ctrans("OK"),
            ctrans("Thanks"),
        ]
)

const scrollBottom = () =>
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
        }
    })

const parentById = (id: number | null) => (id ? messages.value.find((m) => m.id === id) : null)

const pickImage = () => imageInput.value?.click()

const setPendingImage = (file: File | null) => {
    if (pendingImagePreview.value) URL.revokeObjectURL(pendingImagePreview.value)
    pendingImage.value = file
    pendingImagePreview.value = file ? URL.createObjectURL(file) : null
}

const onImageSelect = (event: Event) => {
    const file = (event.target as HTMLInputElement)?.files?.[0]
    if (file) setPendingImage(file)
    if (imageInput.value) imageInput.value.value = ""
}

const clearPendingImage = () => setPendingImage(null)

const onPaste = (event: ClipboardEvent) => {
    const file = Array.from(event.clipboardData?.files ?? []).find((f) => f.type.startsWith("image/"))
    if (file) {
        event.preventDefault()
        setPendingImage(file)
    }
}

const messageText = (message: StaffMessage) => {
    if (!showOriginal.value[message.id] && myLanguageId.value && message.translations?.[myLanguageId.value]) {
        return message.translations[myLanguageId.value]
    }
    return message.body
}

const hasTranslation = (message: StaffMessage) =>
    !!(myLanguageId.value && message.translations?.[myLanguageId.value] && message.translations[myLanguageId.value] !== message.body)

let isNearBottom = true
const onScroll = () => {
    const el = messagesContainer.value
    if (!el) return
    isNearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 80
    if (el.scrollTop < 40 && el.scrollHeight > el.clientHeight + 10) {
        loadOlder()
    }
}

let isLoadingOlder = false
let hasOlder = true

const activeTab = ref<"messages" | "files">("messages")
const hasOpenedFiles = ref(false)
const showsTabs = computed(() => isDesk.value && !props.embedded)
const highlightedMessageId = ref<number | null>(null)
watch(activeTab, (tab) => (hasOpenedFiles.value ||= tab === "files"))
watch(() => props.conversation.ulid, () => {
    activeTab.value = "messages"
    hasOpenedFiles.value = false
})

const MAX_PAGES_TO_FIND = 40
const showMessage = async (item: SharedItem) => {
    activeTab.value = "messages"
    isNearBottom = false
    await nextTick()
    const find = () => messagesContainer.value?.querySelector<HTMLElement>(`[data-message-id="${item.message_id}"]`)
    for (let page = 0; !find() && hasOlder && page < MAX_PAGES_TO_FIND; page++) {
        const before = messages.value.length
        await store.loadMessages(props.conversation.ulid, messages.value[0]?.id)
        hasOlder = messages.value.length > before
        await nextTick()
    }
    const element = find()
    const container = messagesContainer.value
    if (!element || !container) return
    container.scrollTop += element.getBoundingClientRect().top - container.getBoundingClientRect().top - (container.clientHeight - element.offsetHeight) / 2
    highlightedMessageId.value = item.message_id
    setTimeout(() => (highlightedMessageId.value === item.message_id ? (highlightedMessageId.value = null) : null), 2500)
}
const loadOlder = async () => {
    if (isLoadingOlder || !hasOlder || !messages.value.length) return
    isLoadingOlder = true
    const el = messagesContainer.value
    const prevHeight = el?.scrollHeight ?? 0
    const prevCount = messages.value.length
    await store.loadMessages(props.conversation.ulid, messages.value[0].id)
    hasOlder = messages.value.length > prevCount
    nextTick(() => {
        if (el && hasOlder) el.scrollTop = el.scrollHeight - prevHeight
    })
    isLoadingOlder = false
}

watch(
    () => messages.value.length,
    () => {
        if (isNearBottom) scrollBottom()
    }
)

watch(
    () => store.notesByUlid[props.conversation.ulid],
    () => {
        if (isNearBottom) scrollBottom()
    },
    { deep: true }
)

const gifPickerContainer = ref<HTMLElement | null>(null)
const gifButton = ref<HTMLElement | null>(null)
const emojiPickerContainer = ref<HTMLElement | null>(null)
const emojiButton = ref<HTMLElement | null>(null)

const closePickers = () => {
    showGifPicker.value = false
    showEmojiPicker.value = false
}

const handleEscapeKey = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        closePickers()
    }
}

const isInside = (element: HTMLElement | null, target: Node) => !!element?.contains(target)

const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as Node
    if (showEmojiPicker.value && !isInside(emojiPickerContainer.value, target) && !isInside(emojiButton.value, target)) {
        showEmojiPicker.value = false
    }
    if (showGifPicker.value && !isInside(gifPickerContainer.value, target) && !isInside(gifButton.value, target)) {
        showGifPicker.value = false
    }
}

onMounted(() => {
    scrollBottom()
    nowInterval = setInterval(() => (now.value = Date.now()), 60 * 1000)
    window.addEventListener("keydown", handleEscapeKey)
    document.addEventListener("click", handleClickOutside, true)
})

onUnmounted(() => {
    if (nowInterval) clearInterval(nowInterval)
    window.removeEventListener("keydown", handleEscapeKey)
    document.removeEventListener("click", handleClickOutside, true)
})

watch(newMessage, (text) => {
    closePickers()
    if (text.trim() && !leftAt.value) store.sendTyping(props.conversation.ulid)
})

const typingText = computed(() => store.typingText(props.conversation.ulid))

watch(
    () => !!typingText.value,
    (isTyping) => {
        if (isTyping && isNearBottom) scrollBottom()
    },
    { flush: "post" }
)

const setReply = (message: StaffMessage) => {
    parentMessage.value = message
    focusBox()
}

const clearReply = () => {
    parentMessage.value = null
}

const sendCurrent = async () => {
    const body = newMessage.value.trim()
    if ((!body && !pendingImage.value) || isMessageTooLong.value) return
    newMessage.value = ""
    const image = pendingImage.value
    const parentId = parentMessage.value?.id ?? null
    clearPendingImage()
    parentMessage.value = null
    focusBox()
    nextTick(autoResize)
    await store.send(props.conversation.ulid, body, parentId, image)
}

const pickGif = async (url: string) => {
    showGifPicker.value = false
    await store.send(props.conversation.ulid, url)
}

const pickEmoji = (emoji: string) => {
    if (!textarea.value) return
    const start = textarea.value.selectionStart
    const end = textarea.value.selectionEnd
    newMessage.value = newMessage.value.slice(0, start) + emoji + newMessage.value.slice(end)
    nextTick(() => {
        textarea.value?.focus()
        const newPos = start + emoji.length
        textarea.value!.setSelectionRange(newPos, newPos)
    })
}

const onTextareaInput = () => {
    autoResize()
    updateMentionQuery()
}

const onTextareaKeydown = (event: KeyboardEvent) => {
    if (onMentionKeydown(event)) return
    if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault()
        sendCurrent()
    }
}

const sendQuickReply = async (text: string) => {
    const parentId = parentMessage.value?.id ?? null
    parentMessage.value = null
    await store.send(props.conversation.ulid, text, parentId)
}

const toggleReaction = async (message: StaffMessage, emoji: string) => {
    activeReactionFor.value = null
    await store.toggleReaction(message, emoji, myId.value)
}

const taskDialogOpen = ref(false)
const taskSource = ref<StaffMessage | null>(null)
const taskFromMessage = (message: StaffMessage) => {
    taskSource.value = message
    taskDialogOpen.value = true
}

const reactionEntries = (message: StaffMessage) => Object.entries(message.reactions ?? {})
const hasMyReaction = (message: StaffMessage, emoji: string) =>
    (message.reactions?.[emoji] ?? []).includes(myId.value)

const myReactionsOf = (message: StaffMessage) => reactionEntries(message).filter(([, userIds]) => (userIds as number[]).includes(myId.value)).map(([emoji]) => emoji)
const canCreateTaskFrom = (message: StaffMessage) => !message.client_status && !message.gif_url && !!messageText(message) && props.conversation.context_type !== "StaffTask"
const deskMenuOf = (message: StaffMessage): DeskToolbarMenuItem[] =>
    canCreateTaskFrom(message) ? [{ label: ctrans("Create a task from this message"), icon: "fal fa-tasks", command: () => taskFromMessage(message) }] : []

const reactionPicker = ref()
const reactionPickerFor = ref<StaffMessage | null>(null)
const openReactionPicker = (event: Event, message: StaffMessage) => {
    reactionPickerFor.value = message
    reactionPicker.value?.toggle(event)
}
const onReactionPick = (emoji: string) => {
    reactionPicker.value?.hide()
    if (reactionPickerFor.value && !hasMyReaction(reactionPickerFor.value, emoji)) toggleReaction(reactionPickerFor.value, emoji)
}
</script>

<template>
    <div class="flex flex-col bg-white text-gray-900 text-left h-full w-full font-desk" :class="fullScreen ? '' : 'rounded-t-lg border border-gray-200 shadow-lg'" :style="floatsHeader ? wallpaperStyle(conversation.wallpaper) : undefined">
        <div :class="floatsHeader ? 'm-2 mb-0 shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm' : 'contents'">
        <!-- Header -->
        <div class="flex items-center gap-x-2 px-3 py-2 border-b" :class="fullScreen ? (isDesk ? 'shrink-0 border-[var(--vx-head-line,#e5e7eb)] bg-[var(--vx-head-bg,#f9fafb)]' : 'shrink-0 border-gray-200 bg-gray-50') : 'rounded-t-lg border-[var(--chat-line)] bg-[var(--chat-bg)]'">
            <button v-if="fullScreen && !embedded" :aria-label="ctrans('Back')" class="p-2 -ml-2" :class="isDesk ? 'text-[var(--vx-head-muted,#4b5563)]' : 'text-gray-600'" @click="emit('back')">
                <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
            </button>
            <div class="relative h-7 w-7 shrink-0">
                <div class="h-full w-full rounded-full overflow-hidden" :class="fullScreen ? 'bg-gray-200' : 'bg-[var(--chat-line)]'">
                    <Image v-if="displayAvatar" :src="displayAvatar" :alt="displayName" image-cover />
                    <span v-else class="flex items-center justify-center h-full text-xs" :class="fullScreen ? 'text-gray-600' : 'text-[var(--chat-text)]'">{{ displayName?.[0] }}</span>
                </div>
                <span class="absolute bottom-0 right-0 z-[1] h-2 w-2 rounded-full ring-1" :class="[fullScreen ? 'ring-white' : 'ring-[var(--chat-bg)]', !isOnline ? (fullScreen ? 'bg-gray-400' : 'bg-[var(--chat-muted)]') : isIdle ? (fullScreen ? 'bg-yellow-400' : 'bg-[var(--chat-yellow)]') : (fullScreen ? 'bg-green-500' : 'bg-[var(--chat-green)]')]" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate" :class="fullScreen ? (isDesk ? 'text-[var(--vx-head-text,#111827)]' : '') : 'text-[var(--chat-text)]'">{{ displayName }}</div>
                <div class="text-xs h-4" :class="fullScreen ? (isDesk ? 'text-[var(--vx-head-muted,#6b7280)]' : 'text-gray-500') : 'text-[var(--chat-muted)]'">
                    <span v-if="lastSeenAt && !isOnline">{{ ctrans('Last seen') }} {{ formatDistanceToNow(new Date(lastSeenAt), { addSuffix: true }) }}</span>
                    <span v-else-if="conversation.type === 'dm' && isOnline">{{ isIdle ? ctrans('Idle') : ctrans('Active') }}</span>
                    <span v-else-if="conversation.context_url" class="flex min-w-0 items-center gap-1.5">
                        <a :href="conversation.context_url" class="truncate" :class="fullScreen ? (isDesk ? 'text-[var(--vx-head-accent,var(--app-accent-strong))] hover:underline' : 'text-[--app-accent-strong] hover:underline') : 'text-[var(--chat-accent)] hover:underline'">{{ conversation.context_label }}</a>
                        <span v-if="taskInfo?.status_label" class="inline-flex shrink-0 items-center gap-0.5 whitespace-nowrap">
                            <FontAwesomeIcon v-if="taskInfo.status_icon" :icon="taskInfo.status_icon.icon" :class="taskInfo.status_icon.class" fixed-width aria-hidden="true" />
                            {{ taskInfo.status_label }}
                        </span>
                    </span>
                </div>
            </div>
            <button
                v-if="embedded && isTaskClosed"
                type="button"
                v-tooltip="ctrans('Clear this chat from your chats. It stays here on the task.')"
                class="flex shrink-0 items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 transition duration-200 hover:bg-gray-50 active:!bg-gray-100 disabled:opacity-50"
                :disabled="isEndingChat"
                @click="endChat">
                <FontAwesomeIcon icon="fal fa-check-circle" class="text-green-600" fixed-width aria-hidden="true" />
                {{ ctrans("End chat") }}
            </button>
            <button
                v-if="embedded && isListedWorkChat"
                type="button"
                v-tooltip="listedConversation?.is_watching ? ctrans('Watching: always in your chat list. Click to stop watching') : ctrans('Not watching: only listed when hidden chats are shown. Click to watch')"
                :aria-pressed="!!listedConversation?.is_watching"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition duration-200 hover:bg-gray-100"
                :class="listedConversation?.is_watching ? 'text-[--app-accent-strong]' : 'text-gray-400'"
                @click="store.toggleWatch(conversation.ulid)">
                <FontAwesomeIcon :icon="listedConversation?.is_watching ? 'fal fa-eye' : 'fal fa-eye-slash'" fixed-width aria-hidden="true" />
            </button>
            <template v-if="conversation.can_call && !embedded">
                <button
                    type="button"
                    v-tooltip="ctrans('Call')"
                    :aria-label="ctrans('Call')"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition duration-200 disabled:opacity-40"
                    :class="fullScreen ? (isDesk ? 'text-[var(--vx-head-muted,#4b5563)] hover:bg-black/5' : 'text-gray-600 hover:bg-gray-100') : 'text-[var(--chat-muted)] hover:text-[var(--chat-text)]'"
                    :disabled="!canStartCall"
                    @click="calls.start(conversation.ulid, 'voice')">
                    <FontAwesomeIcon icon="fal fa-phone" fixed-width aria-hidden="true" />
                </button>
                <button
                    type="button"
                    v-tooltip="ctrans('Video call')"
                    :aria-label="ctrans('Video call')"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition duration-200 disabled:opacity-40"
                    :class="fullScreen ? (isDesk ? 'text-[var(--vx-head-muted,#4b5563)] hover:bg-black/5' : 'text-gray-600 hover:bg-gray-100') : 'text-[var(--chat-muted)] hover:text-[var(--chat-text)]'"
                    :disabled="!canStartCall"
                    @click="calls.start(conversation.ulid, 'video')">
                    <FontAwesomeIcon icon="fal fa-video" fixed-width aria-hidden="true" />
                </button>
                <button
                    v-if="showScreenCallButton"
                    type="button"
                    v-tooltip="ctrans('Call and share your screen')"
                    :aria-label="ctrans('Call and share your screen')"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md transition duration-200 disabled:opacity-40"
                    :class="fullScreen ? (isDesk ? 'text-[var(--vx-head-muted,#4b5563)] hover:bg-black/5' : 'text-gray-600 hover:bg-gray-100') : 'text-[var(--chat-muted)] hover:text-[var(--chat-text)]'"
                    :disabled="!canStartCall"
                    @click="startScreenCall">
                    <FontAwesomeIcon icon="fal fa-desktop" fixed-width aria-hidden="true" />
                </button>
            </template>
            <ChatWallpaperButton
                v-if="!embedded"
                :conversation-ulid="conversation.ulid"
                :wallpaper="conversation.wallpaper"
                :is-set="!!conversation.wallpaper_is_set"
                :button-class="fullScreen ? 'text-gray-600 hover:bg-gray-100' : 'text-[var(--chat-muted)] hover:text-[var(--chat-text)]'"
                @saved="onWallpaperSaved" />
            <slot name="header-actions" />
            <button
                v-if="taskInfo && !embedded"
                type="button"
                v-tooltip="ctrans('Who is in this chat')"
                class="flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs ring-1 transition duration-200"
                :class="fullScreen ? 'bg-white text-gray-600 ring-gray-200 hover:bg-gray-50' : 'text-[var(--chat-muted)] ring-[var(--chat-line)] hover:text-[var(--chat-text)]'"
                @click="isTaskMembersOpen = true">
                <span class="h-2 w-2 rounded-full" :class="taskOnlineCount ? 'bg-green-500' : 'bg-gray-300'" />
                {{ fullScreen ? ctrans(":online of :total online", { online: taskOnlineCount, total: taskMembers.length }) : `${taskOnlineCount}/${taskMembers.length}` }}
            </button>
            <button v-if="!fullScreen" v-tooltip="ctrans('Open full view')" class="p-2 text-[var(--chat-muted)] hover:text-[var(--chat-text)]" @click="emit('close'); router.visit(route('grp.chat.staff.show', conversation.ulid))">
                <FontAwesomeIcon icon="fal fa-expand-alt" fixed-width aria-hidden="true" />
            </button>
            <button v-if="!fullScreen" v-tooltip="ctrans('Minimize')" class="p-2 text-[var(--chat-muted)] hover:text-[var(--chat-text)]" @click="emit('minimise')">
                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width aria-hidden="true" />
            </button>
            <button v-if="!embedded" v-tooltip="conversation.type === 'dm' ? ctrans('Done talking to :name', { name: displayName }) : ctrans('Leave this conversation for now')" class="p-2" :class="fullScreen ? (isDesk ? 'text-[var(--vx-head-muted,#6b7280)] hover:text-[var(--vx-head-text,#1f2937)]' : 'text-gray-500 hover:text-gray-800') : 'text-[var(--chat-muted)] hover:text-[var(--chat-text)]'" @click="emit('close')">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div v-if="callError" class="shrink-0 border-b border-gray-200 bg-white px-3 py-1.5 text-xs text-red-600" role="alert">{{ callError }}</div>
        <div
            v-if="liveCall && !isInThisCall && !embedded"
            class="flex shrink-0 items-center justify-between gap-2 border-b border-gray-200 bg-[var(--app-accent-soft)] px-3 py-1.5 text-xs text-gray-700">
            <span class="flex min-w-0 items-center gap-1.5 truncate">
                <FontAwesomeIcon icon="fal fa-phone" class="text-[var(--app-accent-strong)]" fixed-width aria-hidden="true" />
                {{ liveCall.kind === "screen" ? ctrans(":name is sharing their screen", { name: liveCall.started_by_name }) : ctrans(":name started a call", { name: liveCall.started_by_name }) }}
            </span>
            <button
                type="button"
                class="flex shrink-0 items-center gap-1 rounded-md bg-green-600 px-2.5 py-1 font-medium text-white transition duration-200 hover:bg-green-700 disabled:opacity-50"
                :disabled="!!calls.joining"
                @click="calls.accept(liveCall, false)">
                {{ ctrans("Join") }}
            </button>
        </div>
        <div
            v-if="canToggleWatch"
            class="flex shrink-0 items-center justify-between gap-2 border-b px-3 py-1 text-xs"
            :class="fullScreen ? 'border-gray-200 bg-white text-gray-500' : 'border-[var(--chat-line)] bg-[var(--chat-bg)] text-[var(--chat-muted)]'">
            <span class="truncate">
                {{ conversation.is_watching ? ctrans("Watching: always in your chat list") : ctrans("Not watching: only listed when hidden chats are shown") }}
            </span>
            <button
                type="button"
                class="flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 ring-1 transition duration-200"
                :class="conversation.is_watching
                    ? (fullScreen ? 'bg-[--app-accent] text-[--app-accent-text] ring-transparent' : 'bg-[var(--chat-accent)] text-white ring-transparent')
                    : (fullScreen ? 'ring-gray-200 hover:bg-gray-50' : 'ring-[var(--chat-line)] hover:text-[var(--chat-text)]')"
                @click="store.toggleWatch(conversation.ulid)">
                <FontAwesomeIcon :icon="conversation.is_watching ? 'fal fa-eye' : 'fal fa-eye-slash'" fixed-width aria-hidden="true" />
                {{ conversation.is_watching ? ctrans("Watching") : ctrans("Watch") }}
            </button>
        </div>

        <div
            v-if="taskInfo && !embedded && (taskInfo.description || taskInfo.model_label)"
            class="shrink-0 border-b text-xs"
            :class="fullScreen ? 'border-gray-200 bg-white text-gray-600' : 'border-[var(--chat-line)] bg-[var(--chat-bg)] text-[var(--chat-muted)]'">
            <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-1 text-left"
                :aria-expanded="isDescriptionOpen"
                @click="isDescriptionOpen = !isDescriptionOpen">
                <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width class="shrink-0 text-[10px] transition-transform" :class="isDescriptionOpen && 'rotate-90'" aria-hidden="true" />
                <span class="shrink-0 font-medium" :class="fullScreen ? 'text-gray-700' : 'text-[var(--chat-text)]'">{{ ctrans("Description") }}</span>
                <span v-if="!isDescriptionOpen" class="min-w-0 truncate opacity-80">{{ taskInfo.description || taskInfo.model_label }}</span>
            </button>
            <div v-if="isDescriptionOpen" class="max-h-40 overflow-y-auto px-3 pb-2 pl-8 [scrollbar-width:thin]">
                <p v-if="taskInfo.description" class="whitespace-pre-line break-words" :class="fullScreen ? 'text-gray-700' : 'text-[var(--chat-text)]'">{{ taskInfo.description }}</p>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <a v-if="taskInfo.model_label" :href="taskInfo.model_url ?? undefined" class="inline-flex items-center gap-1" :class="taskInfo.model_url ? (fullScreen ? 'text-[--app-accent-strong] hover:underline' : 'text-[var(--chat-accent)] hover:underline') : ''">
                        <FontAwesomeIcon icon="fal fa-link" fixed-width aria-hidden="true" />{{ taskInfo.model_label }}
                    </a>
                    <a v-if="conversation.context_url" :href="conversation.context_url" class="inline-flex items-center gap-1" :class="fullScreen ? 'text-[--app-accent-strong] hover:underline' : 'text-[var(--chat-accent)] hover:underline'">
                        <FontAwesomeIcon icon="fal fa-external-link" fixed-width aria-hidden="true" />{{ ctrans("Open :reference", { reference: taskInfo.reference }) }}
                    </a>
                </div>
            </div>
        </div>

        <div v-if="isTaskClosed && !embedded" class="flex shrink-0 items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-600">
            <FontAwesomeIcon v-if="taskInfo?.status_icon" :icon="taskInfo.status_icon.icon" :class="taskInfo.status_icon.class" fixed-width aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate">{{ ctrans(":reference is :status", { reference: taskInfo!.reference, status: (taskInfo!.status_label ?? ctrans("closed")).toLowerCase() }) }}</span>
            <button type="button" class="shrink-0 rounded-md bg-white px-2 py-0.5 font-medium text-gray-700 ring-1 ring-gray-300 transition duration-200 hover:bg-gray-100 disabled:opacity-50" :disabled="isEndingChat" @click="endChat">
                {{ ctrans("End chat") }}
            </button>
        </div>

        <StaffTaskSubtaskProgress v-if="taskInfo?.subtasks.length && !embedded" :subtasks="taskInfo.subtasks" @opened="refreshTaskInfo" />

        <div v-if="showsTabs" class="flex shrink-0 items-center gap-x-1 border-b px-3" :class="isDesk ? 'border-[var(--vx-head-line,#e5e7eb)] bg-[var(--vx-head-bg,#ffffff)]' : 'border-gray-200'" role="tablist" :aria-label="ctrans('Conversation views')">
            <button
                v-for="tab in [{ key: 'messages', label: ctrans('Messages'), icon: 'fal fa-comment-dots' }, { key: 'files', label: ctrans('Files & links'), icon: 'fal fa-folder' }]"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="activeTab === tab.key"
                class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-2.5 py-1.5 text-[13px] font-semibold transition-colors"
                :class="isDesk
                    ? (activeTab === tab.key ? 'border-[var(--vx-head-accent,var(--app-accent))] text-[var(--vx-head-text,#1d1c1d)]' : 'border-transparent text-[var(--vx-head-muted,#616061)] hover:text-[var(--vx-head-text,#1d1c1d)]')
                    : (activeTab === tab.key ? 'border-[var(--app-accent)] text-[#1d1c1d]' : 'border-transparent text-[#616061] hover:text-[#1d1c1d]')"
                @click="activeTab = tab.key as 'messages' | 'files'">
                <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />{{ tab.label }}
            </button>
        </div>
        </div>

        <FilesAndLinksPanel
            v-if="showsTabs && hasOpenedFiles"
            v-show="activeTab === 'files'"
            :url="route('grp.chat.staff.conversations.files-links', { staffConversation: conversation.ulid })"
            :kinds="['all', 'media', 'links']"
            class="min-h-0 flex-1"
            @jump="showMessage" />

        <!-- Messages -->
        <div v-show="activeTab === 'messages'" ref="messagesContainer" class="flex-1 overflow-y-auto py-2" :class="isDesk ? 'text-[#1d1c1d]' : 'px-3'" :style="floatsHeader ? undefined : wallpaperStyle(conversation.wallpaper)" @scroll="onScroll">
            <div v-if="isLoadingFirstMessages" class="space-y-3 py-2" :aria-label="ctrans('Loading…')" aria-busy="true">
                <div v-for="(side, row) in ['left', 'right', 'left', 'right']" :key="'message-skeleton-' + row" class="flex items-end gap-2" :class="side === 'right' ? 'justify-end' : 'justify-start'">
                    <span v-if="side === 'left' && isGroupChat" class="h-7 w-7 shrink-0 animate-pulse rounded-full bg-gray-200" />
                    <span class="h-9 animate-pulse rounded-lg" :class="[side === 'right' ? 'bg-[--app-accent-soft]' : 'bg-gray-100', row % 2 ? 'w-32' : 'w-48']" />
                </div>
            </div>
            <div v-else-if="hasNoMessages" class="flex h-full flex-col items-center justify-center gap-1 text-center text-sm text-gray-400">
                <FontAwesomeIcon icon="fal fa-comments" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                {{ ctrans("No messages yet") }}
                <span class="text-xs">{{ ctrans("Say hello, or tap a quick reply below") }}</span>
            </div>
            <div
                v-for="(message, messageIndex) in messages"
                :key="message.id"
                :data-message-id="message.id"
                class="flex transition-colors duration-700"
                :class="isDesk
                    ? ['group relative px-5', bubbles ? '' : isWallpapered ? 'hover:bg-black/[0.04]' : 'hover:bg-[#f8f8f8]', highlightedMessageId === message.id && '!bg-amber-100/70', message.call ? 'justify-center py-1' : startsRun(messageIndex) ? 'mt-2 pt-1 pb-0.5' : 'py-0.5']
                    : [
                        message.call ? 'justify-center' : message.user_id === myId ? 'justify-end' : 'justify-start',
                        messageIndex === 0 ? '' : isGroupChat && !startsRun(messageIndex) ? 'mt-0.5' : 'mt-2.5',
                    ]"
            >
            <div v-if="message.call" class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs text-gray-600" :class="isWallpapered ? 'bg-white shadow-sm' : 'bg-gray-100'">
                <FontAwesomeIcon :icon="message.call.outcome === 'answered' ? 'fal fa-phone' : 'fal fa-phone-slash'" :class="message.call.outcome === 'missed' && message.call.started_by_user_id !== myId ? 'text-red-500' : 'text-gray-400'" fixed-width aria-hidden="true" />
                <span v-tooltip="message.call.joined.length > 1 ? message.call.joined.join(', ') : undefined">{{ callEntryText(message.call) }}</span>
                <span v-if="isGroupChat || message.call.started_by_user_id !== myId" class="text-gray-400">· {{ message.user_name }}</span>
                <span class="text-gray-400">· {{ useFormatTime(message.created_at, { formatTime: 'hm' }) }}</span>
            </div>
            <div v-else-if="isDesk" class="mr-2 w-9 shrink-0">
                <StaffProfileTrigger v-if="startsRun(messageIndex)" :user-id="message.user_id">
                    <TicketUserAvatar :name="message.user_name" :avatar="senderAvatar(message)" size="lg" square />
                </StaffProfileTrigger>
                <span v-else class="block pt-0.5 text-right text-xs leading-5 text-[#616061] opacity-0 group-hover:opacity-100">{{ useFormatTime(message.created_at, { formatTime: 'HH:mm' }) }}</span>
            </div>
            <div v-else-if="isGroupChat && message.user_id !== myId" class="mr-1.5 w-7 shrink-0">
                <TicketUserAvatar
                    v-if="startsRun(messageIndex)"
                    v-tooltip="message.user_name"
                    :name="message.user_name"
                    :avatar="senderAvatar(message)"
                    size="md" />
            </div>
            <div
                v-if="!message.call"
                class="flex min-w-0 flex-1 flex-col"
                :class="isDesk ? (bubbles ? ['flex-none max-w-[85%] items-start rounded-lg px-3 py-1.5 shadow-sm', isMine(message) ? 'bg-[var(--app-accent-soft)]' : isWallpapered ? 'bg-white' : 'bg-gray-100'] : 'items-start') : ['group', message.user_id === myId ? 'items-end' : 'items-start']"
            >
                <div v-if="isDesk && startsRun(messageIndex)" class="flex flex-wrap items-baseline gap-x-2">
                    <StaffProfileTrigger :user-id="message.user_id">
                        <span class="font-black text-[#1d1c1d] hover:underline">{{ message.user_name }}</span>
                    </StaffProfileTrigger>
                    <span class="text-xs text-[#616061] hover:underline" v-tooltip="useFormatTime(message.created_at, { formatTime: 'hm' })">{{ useFormatTime(message.created_at, { formatTime: 'HH:mm' }) }}</span>
                </div>
                <div v-if="parentById(message.parent_id)" class="mb-0.5 border-l-4 px-2 py-0.5" :class="isDesk ? 'max-w-full border-[#dddddd] text-[13px] text-[#616061]' : ['max-w-[80%] rounded border-l-2 border-gray-300 text-xxs text-gray-500', isWallpapered ? 'bg-white/90' : 'bg-gray-100']">
                    <FontAwesomeIcon icon="fal fa-quote-left" class="mr-1" fixed-width aria-hidden="true" />
                    {{ parentById(message.parent_id)?.body }}
                </div>

                <div class="flex w-full items-end gap-x-1" :class="!isDesk && message.user_id === myId ? 'flex-row-reverse' : ''">
                    <div
                        class="relative"
                        :class="isDesk
                            ? ['max-w-full text-[15px] leading-[1.46668] text-[#1d1c1d]', message.client_status === 'failed' && 'opacity-60']
                            : [
                                'max-w-[80%] rounded-lg',
                                fullScreen ? 'text-[15px] leading-[1.46668]' : 'text-sm',
                                message.gif_url ? 'p-1' : 'px-3 py-2',
                                message.user_id === myId ? 'bg-[--app-accent] text-[--app-accent-text]' : theirBubbleClass,
                                message.client_status === 'failed' && 'opacity-60 ring-1 ring-red-400',
                                isGroupChat && startsRun(messageIndex) && (message.user_id === myId ? 'rounded-tr-sm' : 'rounded-tl-sm'),
                            ]"
                        v-tooltip="!isDesk && isGroupChat && !endsRun(messageIndex) ? { content: useFormatTime(message.created_at, { formatTime: 'hm' }), placement: message.user_id === myId ? 'left' : 'right' } : undefined"
                        @mouseenter="reactsOnHover && !message.client_status && (activeReactionFor = message.id)"
                        @mouseleave="reactsOnHover && (activeReactionFor = null)"
                        @click="!reactsOnHover && !message.client_status && (activeReactionFor = activeReactionFor === message.id ? null : message.id)"
                    >
                        <div v-if="!isDesk && isGroupChat && message.user_id !== myId && startsRun(messageIndex)" class="mb-0.5 text-xs font-medium whitespace-nowrap" :class="senderNameClass(message.user_id)">
                            <span v-tooltip="message.user_name" class="cursor-default">{{ shortSenderName(message.user_name) }}</span>
                        </div>
                        <Image v-if="message.image" :src="message.image" alt="" image-cover class="max-w-[220px] rounded mb-1" />
                        <div v-if="message.gif_url" class="relative">
                            <div
                                v-if="gifState[message.gif_url] !== 'loaded'"
                                class="flex h-[150px] w-[200px] max-w-full items-center justify-center rounded text-xs"
                                :class="[gifState[message.gif_url] === 'failed' ? 'bg-black/5' : 'animate-pulse', !isDesk && message.user_id === myId ? 'bg-white/20' : 'bg-gray-200']">
                                <span v-if="gifState[message.gif_url] === 'failed'" class="opacity-70">{{ ctrans("GIF unavailable") }}</span>
                            </div>
                            <img
                                v-if="gifState[message.gif_url] !== 'failed'"
                                :src="message.gif_url"
                                alt="GIF"
                                class="max-w-full rounded max-h-[240px]"
                                :class="gifState[message.gif_url] !== 'loaded' && 'pointer-events-none absolute inset-0 opacity-0'"
                                @load="gifState[message.gif_url] = 'loaded'"
                                @error="gifState[message.gif_url] = 'failed'" />
                        </div>
                        <div v-else-if="messageText(message)" class="whitespace-pre-wrap break-words" v-html="renderBody(message, messageText(message))" />
                        <button
                            v-if="!message.gif_url && hasTranslation(message)"
                            class="text-xxs underline opacity-70 mt-1"
                            :class="isDesk ? 'text-[#616061]' : message.user_id === myId ? 'text-[--app-accent-text] opacity-80' : 'text-gray-500'"
                            @click="showOriginal[message.id] = !showOriginal[message.id]"
                        >
                            {{ showOriginal[message.id] ? ctrans('translated') : ctrans('original') }}
                        </button>

                        <div v-if="!isDesk && activeReactionFor === message.id" class="absolute z-10" :class="[message.user_id === myId ? 'right-0' : 'left-0', messageIndex === 0 ? 'top-full pt-1.5' : 'bottom-full pb-1.5']">
                            <div class="flex gap-x-1 rounded-full border border-gray-200 bg-white px-2 py-1 shadow">
                                <button v-for="emoji in REACTION_EMOJIS" :key="emoji" class="text-base leading-none p-1" @click="toggleReaction(message, emoji)">
                                    {{ emoji }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <button v-if="!isDesk && !message.client_status" class="opacity-0 group-hover:opacity-100 text-xxs text-gray-400 px-1" @click="setReply(message)">
                        {{ ctrans('reply') }}
                    </button>
                    <button v-if="!isDesk && canCreateTaskFrom(message)" class="opacity-0 group-hover:opacity-100 text-xxs text-gray-400 px-1" @click="taskFromMessage(message)">
                        {{ ctrans('task') }}
                    </button>
                </div>

                <TransitionGroup tag="div" name="reaction-pop" class="flex flex-wrap gap-1 mt-0.5 empty:hidden">
                    <button
                        v-for="[emoji, userIds] in reactionEntries(message)"
                        :key="emoji"
                        type="button"
                        class="flex items-center border transition"
                        :class="isDesk
                            ? ['h-6 gap-x-1 rounded-xl px-1.5 text-xs', hasMyReaction(message, emoji) ? 'border-[#1d9bd1] bg-[#e8f5fa] text-[#1264a3]' : 'border-transparent bg-[#1d1c1d0f] text-[#1d1c1d] hover:border-[#1d1c1d80] hover:bg-white']
                            : ['gap-x-0.5 rounded-full px-1.5 py-0.5 text-xxs hover:border-[--app-accent]', hasMyReaction(message, emoji) ? 'bg-[--app-accent-soft] border-[--app-accent]' : 'bg-gray-50 border-gray-200']"
                        :title="hasMyReaction(message, emoji) ? ctrans('Remove reaction') : ctrans('React with :emoji', { emoji })"
                        @click="toggleReaction(message, emoji)"
                    >
                        {{ emoji }} {{ (userIds as number[]).length }}
                    </button>
                </TransitionGroup>

                <div v-if="isDesk ? !!message.client_status || (isMine(message) && endsRun(messageIndex)) : showsMessageMeta(message, messageIndex)" class="mt-0.5 flex items-center gap-1 text-xxs" :class="isWallpapered ? 'text-gray-600' : 'text-gray-400'">
                    <template v-if="message.client_status === 'failed'">
                        <span class="flex items-center gap-1 font-medium text-red-600">
                            <FontAwesomeIcon icon="fal fa-exclamation-circle" fixed-width aria-hidden="true" />
                            {{ ctrans("Not sent") }}
                        </span>
                        <button type="button" class="rounded px-1 font-medium text-red-600 underline hover:text-red-700" @click="store.retrySend(message)">{{ ctrans("Retry") }}</button>
                        <button type="button" class="rounded px-1 text-gray-400 hover:text-gray-600" @click="store.discardFailed(message)">{{ ctrans("Discard") }}</button>
                    </template>
                    <template v-else>
                        <span v-if="!isDesk">{{ useFormatTime(message.created_at, { formatTime: 'hm' }) }}</span>
                        <template v-if="isMine(message)">
                            <FontAwesomeIcon v-if="message.client_status === 'sending'" v-tooltip="ctrans('Sending')" icon="fal fa-check" class="text-gray-400" fixed-width :aria-label="ctrans('Sending')" />
                            <button
                                v-else
                                type="button"
                                v-tooltip="ticksTooltip(message)"
                                :aria-label="ticksTooltip(message)"
                                class="rounded leading-none transition duration-200"
                                :class="isGroupChat ? 'cursor-pointer hover:bg-gray-100 active:!bg-gray-200' : 'cursor-default'"
                                @click.stop="openReadInfo($event, message)">
                                <StaffMessageTicks :state="tickOf(message)" />
                            </button>
                        </template>
                    </template>
                </div>
            </div>
            <div
                v-if="isDesk && !message.call && !message.client_status"
                class="absolute -top-4 right-4 z-10 group-hover:block group-focus-within:block"
                :class="activeReactionFor === message.id ? 'block' : 'hidden'">
                <DeskMessageToolbar
                    can-react
                    :mine="myReactionsOf(message)"
                    :reply-label="ctrans('Reply')"
                    :menu="deskMenuOf(message)"
                    @react="toggleReaction(message, $event)"
                    @add-reaction="openReactionPicker($event, message)"
                    @reply="setReply(message)" />
            </div>
            </div>

            <div v-if="typingText" class="mt-2 flex items-center gap-1.5 px-1 text-xs" :class="isWallpapered ? 'text-gray-700' : 'text-gray-500'" aria-live="polite">
                <span class="typing-dots" aria-hidden="true"><span /><span /><span /></span>
                {{ typingText }}
            </div>
            <div
                v-for="note in store.notesByUlid[conversation.ulid]"
                :key="note.id"
                class="text-center text-xxs text-gray-500 italic py-1 opacity-60"
            >
                {{ note.text }}
            </div>
        </div>

        <Popover v-if="isDesk" ref="reactionPicker" @hide="reactionPickerFor = null">
            <EmojiPicker @pick="onReactionPick" />
        </Popover>

        <Popover ref="readInfoPopover" @hide="readInfoMessage = null">
            <div class="w-60 text-sm">
                <p class="mb-1.5 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-sky-600">
                    <FontAwesomeIcon icon="fal fa-check-double" fixed-width aria-hidden="true" />
                    {{ ctrans("Read by") }}
                </p>
                <ul v-if="readInfo.read.length" class="mb-3 space-y-1.5">
                    <li v-for="participant in readInfo.read" :key="participant.id" class="flex items-center gap-2">
                        <TicketUserAvatar :name="participant.name" :avatar="participant.avatar" size="sm" />
                        <span class="min-w-0 flex-1 truncate text-gray-800" :title="participant.name">{{ participant.name }}</span>
                        <span class="shrink-0 text-xxs text-gray-400">{{ useFormatTime(participant.last_read_at!, { formatTime: 'hm' }) }}</span>
                    </li>
                </ul>
                <p v-else class="mb-3 text-xs text-gray-400">{{ ctrans("Nobody yet") }}</p>

                <template v-if="readInfo.received.length">
                    <p class="mb-1.5 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                        <FontAwesomeIcon icon="fal fa-check-double" fixed-width aria-hidden="true" />
                        {{ ctrans("Received, not read yet") }}
                    </p>
                    <ul class="mb-3 space-y-1.5">
                        <li v-for="participant in readInfo.received" :key="participant.id" class="flex items-center gap-2">
                            <TicketUserAvatar :name="participant.name" :avatar="participant.avatar" size="sm" />
                            <span class="min-w-0 flex-1 truncate text-gray-700" :title="participant.name">{{ participant.name }}</span>
                        </li>
                    </ul>
                </template>
                <template v-if="readInfo.unread.length">
                    <p class="mb-1.5 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                        <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        {{ ctrans("Not received yet") }}
                    </p>
                    <ul class="space-y-1.5">
                        <li v-for="participant in readInfo.unread" :key="participant.id" class="flex items-center gap-2 opacity-70">
                            <TicketUserAvatar :name="participant.name" :avatar="participant.avatar" size="sm" />
                            <span class="min-w-0 flex-1 truncate text-gray-700" :title="participant.name">{{ participant.name }}</span>
                        </li>
                    </ul>
                </template>
            </div>
        </Popover>

        <div v-if="leftAt" v-show="activeTab === 'messages'" class="shrink-0 border-t border-gray-200 bg-gray-50 px-3 py-3 text-center text-xs text-gray-500" :style="fullScreen ? { paddingBottom: 'calc(0.75rem + env(safe-area-inset-bottom))' } : {}">
            <FontAwesomeIcon icon="fal fa-exclamation-circle" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />
            {{ ctrans("You were taken off this on :date. You can read the chat up to then; you will see everything again if you are added back.", { date: useFormatTime(leftAt, { formatTime: 'hm' }) }) }}
        </div>

        <!-- Composer -->
        <div v-else v-show="activeTab === 'messages'" class="border-t border-gray-200 px-2 pt-2 shrink-0" :style="fullScreen ? { paddingBottom: 'env(safe-area-inset-bottom)' } : {}">
            <div v-if="parentMessage" class="flex items-center justify-between px-2 py-1 mb-1 rounded bg-gray-100 text-xs text-gray-600">
                <span class="truncate">{{ ctrans('Replying to') }}: {{ parentMessage.body }}</span>
                <button class="ml-2 shrink-0" @click="clearReply">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="flex items-start gap-1.5 pb-1.5">
                <div class="flex flex-wrap gap-1.5 flex-1">
                    <button
                        v-for="reply in quickReplies"
                        :key="reply"
                        class="rounded-full border border-gray-300 text-gray-700 hover:bg-gray-100 whitespace-nowrap"
                        :class="fullScreen ? 'text-xs px-3 py-1.5' : 'text-xxs px-2 py-0.5'"
                        @click="sendQuickReply(reply)"
                    >
                        {{ reply }}
                    </button>
                </div>
                <div class="flex items-center gap-x-1 shrink-0">
                    <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="onImageSelect" />
                    <button
                        ref="gifButton"
                        v-tooltip="ctrans('Send a GIF')"
                        class="rounded-full border border-gray-300 text-gray-500 flex items-center justify-center hover:bg-gray-100"
                        :class="fullScreen ? 'h-8 min-w-[36px] text-xs' : 'h-[22px] min-w-[28px] px-1.5 text-xxs'"
                        @click.stop="showGifPicker = !showGifPicker; showEmojiPicker = false"
                    >
                        <span :class="fullScreen ? 'text-xs' : 'text-xxs'" class="font-medium">GIF</span>
                    </button>
                    <template v-if="!isDesk">
                        <button
                            ref="emojiButton"
                            v-tooltip="ctrans('Emoji')"
                            class="rounded-full border border-gray-300 text-gray-500 flex items-center justify-center hover:bg-gray-100"
                            :class="fullScreen ? 'h-8 min-w-[36px] text-xs' : 'h-[22px] min-w-[28px] px-1.5 text-xxs'"
                            @click.stop="showEmojiPicker = !showEmojiPicker; showGifPicker = false"
                        >
                            <FontAwesomeIcon icon="fal fa-smile" fixed-width aria-hidden="true" />
                        </button>
                        <button
                            class="rounded-full border border-gray-300 text-gray-500 flex items-center justify-center hover:bg-gray-100"
                            :class="fullScreen ? 'h-8 min-w-[36px] text-xs' : 'h-[22px] min-w-[28px] px-1.5 text-xxs'"
                            @click="pickImage"
                        >
                            <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
                        </button>
                    </template>
                </div>
            </div>

            <div v-if="pendingImagePreview" class="relative inline-block mb-1.5">
                <img :src="pendingImagePreview" class="h-16 w-16 object-cover rounded border border-gray-200" />
                <button class="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-gray-800 text-white flex items-center justify-center" @click="clearPendingImage">
                    <FontAwesomeIcon icon="fal fa-times" size="xs" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="relative flex items-end gap-x-2 pb-2">
                <div v-if="showGifPicker" ref="gifPickerContainer" class="absolute bottom-full left-0 mb-1 z-20" :class="fullScreen ? 'w-full max-w-md' : ''">
                    <GifPicker class="w-full" @pick="pickGif" @close="showGifPicker = false" />
                </div>
                <div v-if="showEmojiPicker" ref="emojiPickerContainer" class="absolute bottom-full left-0 mb-1 z-20">
                    <EmojiPicker @pick="pickEmoji" />
                </div>
                <DeskMessageEditor
                    v-if="isDesk"
                    ref="composer"
                    v-model="newMessage"
                    class="min-w-0 flex-1"
                    :placeholder="ctrans('Write a message…')"
                    :people="mentionPeople"
                    :can-send="!!pendingImage"
                    :sending="isMessageTooLong"
                    :attach-label="ctrans('Attach a picture')"
                    @attach="pickImage"
                    @submit="sendCurrent"
                    @paste="onPaste" />
                <template v-else>
                    <div v-if="showMentionPopup" class="absolute bottom-full left-0 mb-1 z-20 bg-white border border-gray-200 rounded-lg shadow-lg py-1 w-56 max-h-48 overflow-y-auto">
                        <button
                            v-for="(participant, index) in mentionMatches"
                            :key="participant.id"
                            class="w-full flex items-center gap-x-2 px-2 py-1 text-left text-sm"
                            :class="index === mentionActiveIndex ? 'bg-[--app-accent-soft]' : 'hover:bg-gray-50'"
                            @mousedown.prevent="selectMention(participant)"
                        >
                            <div class="h-5 w-5 rounded-full overflow-hidden bg-gray-200 shrink-0">
                                <Image v-if="participant.avatar" :src="participant.avatar" :alt="participant.name" image-cover />
                            </div>
                            <span class="truncate">{{ participant.name }}</span>
                        </button>
                    </div>
                    <textarea
                        ref="textarea"
                        v-model="newMessage"
                        rows="1"
                        class="flex-1 resize-none border rounded-lg px-3 py-2 focus:outline-none focus:ring-1"
                        :class="[fullScreen ? 'min-h-[44px] text-base' : 'text-sm', isMessageTooLong ? 'border-red-400 focus:ring-red-400' : 'border-gray-300 focus:ring-[var(--app-accent)]']"
                        :placeholder="ctrans('Write a message…')"
                        :aria-invalid="isMessageTooLong"
                        :aria-describedby="showsMessageLength ? messageLengthId : undefined"
                        @input="onTextareaInput"
                        @keydown="onTextareaKeydown"
                        @paste="onPaste"
                    />
                    <button
                        class="shrink-0 rounded-full bg-[var(--app-accent)] text-[var(--app-accent-text)] flex items-center justify-center hover:bg-[var(--app-accent-strong)] disabled:opacity-40"
                        :class="fullScreen ? 'h-11 w-11' : 'h-9 w-9'"
                        :disabled="(!newMessage.trim() && !pendingImage) || isMessageTooLong"
                        @click="sendCurrent"
                    >
                        <FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
                    </button>
                </template>
            </div>

            <p
                v-if="showsMessageLength"
                :id="messageLengthId"
                class="-mt-1 flex items-start gap-1 px-1 pb-2 text-xs"
                :class="isMessageTooLong ? 'font-medium text-red-600' : 'text-gray-500'">
                <FontAwesomeIcon v-if="isMessageTooLong" icon="fal fa-exclamation-circle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                <span>{{ messageLengthText }}</span>
            </p>
        </div>

        <StaffTaskDialog
            :is-open="taskDialogOpen"
            :subject="taskSource ? messageText(taskSource).slice(0, 255) : ''"
            :source-message-id="taskSource?.id ?? null"
            @close="taskDialogOpen = false" />

        <Drawer v-if="taskInfo && !embedded" v-model:visible="isTaskMembersOpen" position="right" :header="ctrans('In this chat')" class="!w-80 !max-w-[85vw]" :pt="{ content: { class: '!p-0' } }">
            <p class="border-b border-gray-200 px-3 pb-2 text-xs text-gray-500">{{ ctrans(":online of :total online", { online: taskOnlineCount, total: taskMembers.length }) }}</p>
            <StaffTaskChatMembers :members="taskMembers" :my-id="myId ?? null" />
        </Drawer>
    </div>
</template>

<style scoped>
.typing-dots {
    display: inline-flex;
    gap: 2px;
}
.typing-dots span {
    width: 4px;
    height: 4px;
    border-radius: 9999px;
    background: currentColor;
    animation: typing-dot 1.2s infinite ease-in-out;
}
.typing-dots span:nth-child(2) {
    animation-delay: 0.15s;
}
.typing-dots span:nth-child(3) {
    animation-delay: 0.3s;
}
@keyframes typing-dot {
    0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
    30% { opacity: 1; transform: translateY(-2px); }
}
.reaction-pop-enter-active {
    animation: reaction-pop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.reaction-pop-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}

.reaction-pop-leave-to {
    opacity: 0;
    transform: scale(0.6);
}

@keyframes reaction-pop {
    0% {
        opacity: 0;
        transform: scale(0.4);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
</style>