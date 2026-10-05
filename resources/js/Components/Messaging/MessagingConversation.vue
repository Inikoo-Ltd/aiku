<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, defineAsyncComponent, nextTick, onMounted, onUnmounted, ref, watch } from "vue"
import { usePage, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { formatDistanceToNow } from "date-fns"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes, faChevronDown, faPaperPlane, faChevronLeft, faQuoteLeft, faPaperclip, faSmile, faExpandAlt, faCheck, faCheckDouble, faExclamationCircle, faComments, faCheckCircle, faCircle, faSpinner, faBan } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Common/Components/Image.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, type StaffConversation, type StaffConversationTask, type StaffMessage, type StaffParticipant } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useStaffTaskMembers } from "@/Composables/useStaffTaskMembers"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { Drawer, Popover } from "primevue"
import StaffTaskSubtaskProgress from "@/Components/Tasks/StaffTaskSubtaskProgress.vue"
import StaffTaskChatMembers from "@/Components/Tasks/StaffTaskChatMembers.vue"

library.add(faTimes, faChevronDown, faPaperPlane, faChevronLeft, faQuoteLeft, faPaperclip, faSmile, faExpandAlt, faCheck, faCheckDouble, faExclamationCircle, faComments, faCheckCircle, faCircle, faSpinner, faBan)

const GifPicker = defineAsyncComponent(() => import("./GifPicker.vue"))
const EmojiPicker = defineAsyncComponent(() => import("./EmojiPicker.vue"))
const StaffTaskDialog = defineAsyncComponent(() => import("@/Components/Tasks/StaffTaskDialog.vue"))

const props = defineProps<{
    conversation: StaffConversation
    fullScreen?: boolean
    embedded?: boolean
}>()

const emit = defineEmits<{
    close: []
    minimise: []
    back: []
}>()

const store = useStaffMessaging()
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

const isReadByEveryone = (message: StaffMessage) => otherParticipants.value.length > 0 && readersOf(message).length === otherParticipants.value.length

const showsMessageMeta = (message: StaffMessage, index: number) => !isGroupChat.value || endsRun(index) || !!message.client_status

const ticksTooltip = (message: StaffMessage) => {
    if (!isGroupChat.value) return isReadByEveryone(message) ? ctrans("Read") : ctrans("Sent")
    if (isReadByEveryone(message)) return ctrans("Read by everyone · click to see")
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
    if (!readInfoMessage.value) return { read: [], unread: [] }
    const readers = readersOf(readInfoMessage.value)
    const readerIds = new Set(readers.map((participant) => participant.id))

    return {
        read: [...readers].sort((a, b) => Date.parse(a.last_read_at!) - Date.parse(b.last_read_at!)),
        unread: otherParticipants.value.filter((participant) => !readerIds.has(participant.id)),
    }
})

const shortSenderName = (name: string) => {
    const [firstName, ...otherNames] = name.trim().split(/\s+/)
    return otherNames.length ? `${firstName} ${otherNames.map((part) => `${part.charAt(0).toUpperCase()}.`).join("")}` : firstName
}
const isOnline = computed(() =>
    participants.value.some((p) => p.id !== myId.value && !!useLiveUsers().liveUsers[p.id])
)
const typingUser = computed(() => store.typingByUlid[props.conversation.ulid]?.user_name ?? null)

const otherParticipants = computed(() => participants.value.filter((p) => p.id !== myId.value))
const displayName = computed(() => props.conversation.name || otherParticipants.value.map((p) => p.name).join(", "))
const displayAvatar = computed(() => otherParticipants.value[0]?.avatar ?? null)
const lastSeenAt = computed(() => props.conversation.type === "dm" && otherParticipants.value[0]?.last_seen_at ? otherParticipants.value[0].last_seen_at : null)

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

const escapeHtml = (text: string) =>
    text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")

const renderBody = (message: StaffMessage, text: string) => {
    const handles = new Set(
        participants.value.map((p) => p.handle).filter((h): h is string => !!h)
    )
    const own = message.user_id === myId.value
    const cls = own ? "text-yellow-200" : "text-[--app-accent-strong]"
    return escapeHtml(text).replace(/@([\p{L}\p{N}._-]+)/gu, (full, handle) =>
        handles.has(handle) ? `<span class="font-medium ${cls}">@${escapeHtml(handle)}</span>` : full
    )
}
const pendingImage = ref<File | null>(null)
const pendingImagePreview = ref<string | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const parentMessage = ref<StaffMessage | null>(null)
const showOriginal = ref<Record<number, boolean>>({})
const messagesContainer = ref<HTMLElement | null>(null)
const textarea = ref<HTMLTextAreaElement | null>(null)
const activeReactionFor = ref<number | null>(null)
const showGifPicker = ref(false)
const showEmojiPicker = ref(false)
const REACTION_EMOJIS = ["👍", "✅", "❌", "👀", "🙏", "🔥"]

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

const autoResize = () => {
    const el = textarea.value
    if (!el) return
    el.style.height = "auto"
    el.style.height = Math.min(el.scrollHeight, 120) + "px"
}

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

const emojiPickerContainer = ref<HTMLElement | null>(null)

const closePickers = () => {
    showGifPicker.value = false
    showEmojiPicker.value = false
}

const handleEscapeKey = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        closePickers()
    }
}

const handleClickOutside = (event: MouseEvent) => {
    if (!emojiPickerContainer.value?.contains(event.target as Node)) {
        showEmojiPicker.value = false
    }
}

onMounted(() => {
    scrollBottom()
    window.addEventListener("keydown", handleEscapeKey)
    document.addEventListener("click", handleClickOutside)
})

onUnmounted(() => {
    window.removeEventListener("keydown", handleEscapeKey)
    document.removeEventListener("click", handleClickOutside)
})

let typingTimeout: ReturnType<typeof setTimeout> | null = null
const handleTypingInput = () => {
    autoResize()
    updateMentionQuery()
    if (typingTimeout) return
    window.Echo.join("grp.live.users").whisper("staffTyping", {
        conversation_ulid: props.conversation.ulid,
        user_id: myId.value,
        user_name: usePage().props?.auth?.user?.username,
    })
    typingTimeout = setTimeout(() => {
        typingTimeout = null
    }, 2000)
}

const setReply = (message: StaffMessage) => {
    parentMessage.value = message
    textarea.value?.focus()
}

const clearReply = () => {
    parentMessage.value = null
}

const sendCurrent = async () => {
    const body = newMessage.value.trim()
    if (!body && !pendingImage.value) return
    newMessage.value = ""
    const image = pendingImage.value
    const parentId = parentMessage.value?.id ?? null
    clearPendingImage()
    parentMessage.value = null
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

const sendQuickReply = async (text: string) => {
    const parentId = parentMessage.value?.id ?? null
    parentMessage.value = null
    await store.send(props.conversation.ulid, text, parentId)
}

const onEnter = (event: KeyboardEvent) => {
    if (event.shiftKey) return
    event.preventDefault()
    sendCurrent()
}

const onTextareaKeydown = (event: KeyboardEvent) => {
    if (onMentionKeydown(event)) return
    if (event.key === "Enter") onEnter(event)
}

const toggleReaction = async (message: StaffMessage, emoji: string) => {
    activeReactionFor.value = null
    await store.toggleReaction(message.id, emoji)
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
</script>

<template>
    <div class="flex flex-col bg-white text-gray-900 text-left h-full w-full" :class="fullScreen ? '' : 'rounded-t-lg border border-gray-200 shadow-lg'">
        <!-- Header -->
        <div class="flex items-center gap-x-2 px-3 py-2 border-b" :class="fullScreen ? 'shrink-0 border-gray-200 bg-gray-50' : 'rounded-t-lg border-[var(--chat-line)] bg-[var(--chat-bg)]'">
            <button v-if="fullScreen && !embedded" :aria-label="ctrans('Back')" class="p-2 -ml-2 text-gray-600" @click="emit('back')">
                <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
            </button>
            <div class="relative h-7 w-7 rounded-full overflow-hidden shrink-0" :class="fullScreen ? 'bg-gray-200' : 'bg-[var(--chat-line)]'">
                <Image v-if="displayAvatar" :src="displayAvatar" :alt="displayName" image-cover />
                <span v-else class="flex items-center justify-center h-full text-xs" :class="fullScreen ? 'text-gray-600' : 'text-[var(--chat-text)]'">{{ displayName?.[0] }}</span>
                <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full ring-1" :class="[fullScreen ? 'ring-white' : 'ring-[var(--chat-bg)]', isOnline ? (fullScreen ? 'bg-green-500' : 'bg-[var(--chat-green)]') : (fullScreen ? 'bg-gray-400' : 'bg-[var(--chat-muted)]')]" />
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate" :class="fullScreen ? '' : 'text-[var(--chat-text)]'">{{ displayName }}</div>
                <div class="text-xs h-4" :class="fullScreen ? 'text-gray-500' : 'text-[var(--chat-muted)]'">
                    <span v-if="typingUser">{{ typingUser }} {{ ctrans('is typing…') }}</span>
                    <span v-else-if="lastSeenAt && !isOnline">{{ ctrans('Last seen') }} {{ formatDistanceToNow(new Date(lastSeenAt), { addSuffix: true }) }}</span>
                    <span v-else-if="conversation.context_url" class="flex min-w-0 items-center gap-1.5">
                        <a :href="conversation.context_url" class="truncate" :class="fullScreen ? 'text-[--app-accent-strong] hover:underline' : 'text-[var(--chat-accent)] hover:underline'">{{ conversation.context_label }}</a>
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
            <button v-if="!embedded" v-tooltip="conversation.type === 'dm' ? ctrans('Done talking to :name', { name: displayName }) : ctrans('Leave this conversation for now')" class="p-2" :class="fullScreen ? 'text-gray-500 hover:text-gray-800' : 'text-[var(--chat-muted)] hover:text-[var(--chat-text)]'" @click="emit('close')">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div v-if="isTaskClosed && !embedded" class="flex shrink-0 items-center gap-2 border-b border-gray-200 bg-gray-50 px-3 py-1.5 text-xs text-gray-600">
            <FontAwesomeIcon v-if="taskInfo?.status_icon" :icon="taskInfo.status_icon.icon" :class="taskInfo.status_icon.class" fixed-width aria-hidden="true" />
            <span class="min-w-0 flex-1 truncate">{{ ctrans(":reference is :status", { reference: taskInfo!.reference, status: (taskInfo!.status_label ?? ctrans("closed")).toLowerCase() }) }}</span>
            <button type="button" class="shrink-0 rounded-md bg-white px-2 py-0.5 font-medium text-gray-700 ring-1 ring-gray-300 transition duration-200 hover:bg-gray-100 disabled:opacity-50" :disabled="isEndingChat" @click="endChat">
                {{ ctrans("End chat") }}
            </button>
        </div>

        <StaffTaskSubtaskProgress v-if="taskInfo?.subtasks.length && !embedded" :subtasks="taskInfo.subtasks" @opened="refreshTaskInfo" />

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto px-3 py-2" @scroll="onScroll">
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
                class="flex"
                :class="[
                    message.user_id === myId ? 'justify-end' : 'justify-start',
                    messageIndex === 0 ? '' : isGroupChat && !startsRun(messageIndex) ? 'mt-0.5' : 'mt-2.5',
                ]"
            >
            <div v-if="isGroupChat && message.user_id !== myId" class="mr-1.5 w-7 shrink-0">
                <TicketUserAvatar
                    v-if="startsRun(messageIndex)"
                    v-tooltip="message.user_name"
                    :name="message.user_name"
                    :avatar="senderAvatar(message)"
                    size="md" />
            </div>
            <div
                class="group flex min-w-0 flex-1 flex-col"
                :class="message.user_id === myId ? 'items-end' : 'items-start'"
            >
                <div v-if="parentById(message.parent_id)" class="max-w-[80%] mb-0.5 px-2 py-1 rounded bg-gray-100 text-xxs text-gray-500 border-l-2 border-gray-300">
                    <FontAwesomeIcon icon="fal fa-quote-left" class="mr-1" fixed-width aria-hidden="true" />
                    {{ parentById(message.parent_id)?.body }}
                </div>

                <div class="flex w-full items-end gap-x-1" :class="message.user_id === myId ? 'flex-row-reverse' : ''">
                    <div
                        class="relative max-w-[80%] rounded-lg text-sm"
                        :class="[
                            message.gif_url ? 'p-1' : 'px-3 py-2',
                            message.user_id === myId ? 'bg-[--app-accent] text-[--app-accent-text]' : 'bg-gray-100 text-gray-900',
                            message.client_status === 'failed' && 'opacity-60 ring-1 ring-red-400',
                            isGroupChat && startsRun(messageIndex) && (message.user_id === myId ? 'rounded-tr-sm' : 'rounded-tl-sm'),
                        ]"
                        v-tooltip="isGroupChat && !endsRun(messageIndex) ? { content: useFormatTime(message.created_at, { formatTime: 'hm' }), placement: message.user_id === myId ? 'left' : 'right' } : undefined"
                        @mouseenter="reactsOnHover && !message.client_status && (activeReactionFor = message.id)"
                        @mouseleave="reactsOnHover && (activeReactionFor = null)"
                        @click="!reactsOnHover && !message.client_status && (activeReactionFor = activeReactionFor === message.id ? null : message.id)"
                    >
                        <div v-if="isGroupChat && message.user_id !== myId && startsRun(messageIndex)" class="mb-0.5 text-xs font-medium whitespace-nowrap" :class="senderNameClass(message.user_id)">
                            <span v-tooltip="message.user_name" class="cursor-default">{{ shortSenderName(message.user_name) }}</span>
                        </div>
                        <Image v-if="message.image" :src="message.image" alt="" image-cover class="max-w-[220px] rounded mb-1" />
                        <img v-if="message.gif_url" :src="message.gif_url" loading="lazy" class="max-w-full rounded max-h-[240px]" />
                        <div v-else-if="messageText(message)" class="whitespace-pre-wrap break-words" v-html="renderBody(message, messageText(message))" />
                        <button
                            v-if="!message.gif_url && hasTranslation(message)"
                            class="text-xxs underline opacity-70 mt-1"
                            :class="message.user_id === myId ? 'text-[--app-accent-text] opacity-80' : 'text-gray-500'"
                            @click="showOriginal[message.id] = !showOriginal[message.id]"
                        >
                            {{ showOriginal[message.id] ? ctrans('translated') : ctrans('original') }}
                        </button>

                        <div v-if="activeReactionFor === message.id" class="absolute flex gap-x-1 bg-white border border-gray-200 rounded-full shadow px-2 py-1 z-10" :class="[message.user_id === myId ? 'right-0' : 'left-0', messageIndex === 0 ? 'top-full mt-1' : '-top-9']">
                            <button v-for="emoji in REACTION_EMOJIS" :key="emoji" class="text-base leading-none p-1" @click="toggleReaction(message, emoji)">
                                {{ emoji }}
                            </button>
                        </div>
                    </div>

                    <button v-if="!message.client_status" class="opacity-0 group-hover:opacity-100 text-xxs text-gray-400 px-1" @click="setReply(message)">
                        {{ ctrans('reply') }}
                    </button>
                    <button v-if="!message.client_status && !message.gif_url && messageText(message) && conversation.context_type !== 'StaffTask'" class="opacity-0 group-hover:opacity-100 text-xxs text-gray-400 px-1" @click="taskFromMessage(message)">
                        {{ ctrans('task') }}
                    </button>
                </div>

                <div v-if="reactionEntries(message).length" class="flex gap-x-1 mt-0.5">
                    <span
                        v-for="[emoji, userIds] in reactionEntries(message)"
                        :key="emoji"
                        class="text-xxs px-1.5 py-0.5 rounded-full border flex items-center gap-x-0.5"
                        :class="hasMyReaction(message, emoji) ? 'bg-[--app-accent-soft] border-[--app-accent]' : 'bg-gray-50 border-gray-200'"
                    >
                        {{ emoji }} {{ (userIds as number[]).length }}
                    </span>
                </div>

                <div v-if="showsMessageMeta(message, messageIndex)" class="mt-0.5 flex items-center gap-1 text-xxs text-gray-400">
                    <template v-if="message.client_status === 'failed'">
                        <span class="flex items-center gap-1 font-medium text-red-600">
                            <FontAwesomeIcon icon="fal fa-exclamation-circle" fixed-width aria-hidden="true" />
                            {{ ctrans("Not sent") }}
                        </span>
                        <button type="button" class="rounded px-1 font-medium text-red-600 underline hover:text-red-700" @click="store.retrySend(message)">{{ ctrans("Retry") }}</button>
                        <button type="button" class="rounded px-1 text-gray-400 hover:text-gray-600" @click="store.discardFailed(message)">{{ ctrans("Discard") }}</button>
                    </template>
                    <template v-else>
                        <span>{{ useFormatTime(message.created_at, { formatTime: 'hm' }) }}</span>
                        <template v-if="isMine(message)">
                            <FontAwesomeIcon v-if="message.client_status === 'sending'" v-tooltip="ctrans('Sending')" icon="fal fa-check" class="text-gray-400" fixed-width :aria-label="ctrans('Sending')" />
                            <button
                                v-else
                                type="button"
                                v-tooltip="ticksTooltip(message)"
                                :aria-label="ticksTooltip(message)"
                                class="rounded leading-none transition duration-200"
                                :class="[isReadByEveryone(message) ? 'text-[--app-accent]' : 'text-gray-400', isGroupChat ? 'cursor-pointer hover:bg-gray-100 active:!bg-gray-200' : 'cursor-default']"
                                @click.stop="openReadInfo($event, message)">
                                <FontAwesomeIcon icon="fal fa-check-double" fixed-width aria-hidden="true" />
                            </button>
                        </template>
                    </template>
                </div>
            </div>
            </div>

            <div
                v-for="note in store.notesByUlid[conversation.ulid]"
                :key="note.id"
                class="text-center text-xxs text-gray-500 italic py-1 opacity-60"
            >
                {{ note.text }}
            </div>
        </div>

        <Popover ref="readInfoPopover" @hide="readInfoMessage = null">
            <div class="w-60 text-sm">
                <p class="mb-1.5 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-[--app-accent-strong]">
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

                <template v-if="readInfo.unread.length">
                    <p class="mb-1.5 flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                        <FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
                        {{ ctrans("Not read yet") }}
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

        <div v-if="leftAt" class="shrink-0 border-t border-gray-200 bg-gray-50 px-3 py-3 text-center text-xs text-gray-500" :style="fullScreen ? { paddingBottom: 'calc(0.75rem + env(safe-area-inset-bottom))' } : {}">
            <FontAwesomeIcon icon="fal fa-exclamation-circle" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />
            {{ ctrans("You were taken off this on :date. You can read the chat up to then; you will see everything again if you are added back.", { date: useFormatTime(leftAt, { formatTime: 'hm' }) }) }}
        </div>

        <!-- Composer -->
        <div v-else class="border-t border-gray-200 px-2 pt-2 shrink-0" :style="fullScreen ? { paddingBottom: 'env(safe-area-inset-bottom)' } : {}">
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
                        v-tooltip="ctrans('Send a GIF')"
                        class="rounded-full border border-gray-300 text-gray-500 flex items-center justify-center hover:bg-gray-100"
                        :class="fullScreen ? 'h-8 min-w-[36px] text-xs' : 'h-[22px] min-w-[28px] px-1.5 text-xxs'"
                        @click.stop="showGifPicker = !showGifPicker; showEmojiPicker = false"
                    >
                        <span :class="fullScreen ? 'text-xs' : 'text-xxs'" class="font-medium">GIF</span>
                    </button>
                    <button
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
                </div>
            </div>

            <div v-if="pendingImagePreview" class="relative inline-block mb-1.5">
                <img :src="pendingImagePreview" class="h-16 w-16 object-cover rounded border border-gray-200" />
                <button class="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-gray-800 text-white flex items-center justify-center" @click="clearPendingImage">
                    <FontAwesomeIcon icon="fal fa-times" size="xs" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="relative flex items-end gap-x-2 pb-2">
                <div v-if="showGifPicker" class="absolute bottom-full left-0 mb-1 z-20" :class="fullScreen ? 'w-full max-w-md' : ''">
                    <GifPicker class="w-full" @pick="pickGif" @close="showGifPicker = false" />
                </div>
                <div v-if="showEmojiPicker" ref="emojiPickerContainer" class="absolute bottom-full left-0 mb-1 z-20">
                    <EmojiPicker @pick="pickEmoji" />
                </div>
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
                    class="flex-1 resize-none border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[--app-accent]"
                    :class="fullScreen ? 'min-h-[44px] text-base' : 'text-sm'"
                    :placeholder="ctrans('Write a message…')"
                    @input="handleTypingInput"
                    @keydown="onTextareaKeydown"
                    @paste="onPaste"
                />
                <button
                    class="shrink-0 rounded-full bg-[--app-accent] text-[--app-accent-text] flex items-center justify-center hover:bg-[--app-accent-strong] disabled:opacity-40"
                    :class="fullScreen ? 'h-11 w-11' : 'h-9 w-9'"
                    :disabled="!newMessage.trim() && !pendingImage"
                    @click="sendCurrent"
                >
                    <FontAwesomeIcon icon="fal fa-paper-plane" fixed-width aria-hidden="true" />
                </button>
            </div>
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
