<script setup lang="ts">
import { inject, computed, ref, onMounted, onUnmounted, watch } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faCheckDouble, faExclamationCircle, faLanguage, faRobot, faShieldCheck } from "@far"
import { faShare, faFaceSmile, faReply, faLocationDot, faPhone, faCopy, faCircleExclamation } from "@fortawesome/free-solid-svg-icons"
import axios from "axios"
import { useChatLanguages } from "@/Composables/useLanguages"
import Image from "primevue/image"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import SlackShareModal from "@/Components/Chat/Agent/SlackShareModal.vue"
import ChatTimelineEvent from "@/Components/Chat/ChatTimelineEvent.vue"
import AudioPlayer from "@/Components/Chat/AudioPlayer.vue"
import { formatWhatsappMarkup } from "@/Composables/useWhatsappMarkup"
import { useCopyText } from "@/Composables/useCopyText"

type SenderType = "guest" | "user" | "agent" | "system"
type MessageStatus = "sending" | "sent" | "failed"
type ViewerType = "user" | "agent"

interface Message {
    is_offline_message: boolean
    sender_type: SenderType
    message_text: string
    created_at: string
    media_url?: {
        original: string
        mime: string
        name?: string
        size?: number
    } | null
    message_type?: "text" | "image" | "file"
    file_name?: string | null
    file_mime?: string | null
    download_route?: {
        url: string
    } | null
    is_read?: boolean
    metadata?: Record<string, any> | null
    replied_to?: {
        id: number
        message_text?: string | null
        message_type?: string
        sender_type?: string
        file_name?: string | null
    } | null
    id?: number
    sender_name?: string | null
    _status?: MessageStatus
    original?: Translation
    translations?: Translation[]
    edited_at?: string | null
    is_ai_generated?: boolean | null
    is_validated?: boolean | null
    is_verifiable_image?: boolean
    ai_verification?: {
        model_verdict: boolean
        confidence: number
        reasoning: string
    } | null
    reactions?: ReactionGroup[]
}

interface ReactionReactor {
    type: string
    id: number | null
}

interface ReactionGroup {
    emoji: string
    count: number
    reactors: ReactionReactor[]
}

interface Translation {
    language_flag: any
    chat_translation_id: number
    language_id: number
    translated_text: string
    language_name: string
    language_code: string
    text: string
}

const props = defineProps<{
    message: Message
    viewerType: ViewerType
    agentName?: string | null
    contactName?: string | null
    canEdit?: boolean
    readonly?: boolean
    sessionUlid?: string | null
    reactionUrlBase?: string
    apiBase?: string
    viewerReactorId?: number | null
    canReply?: boolean
    formatMarkup?: boolean
    translateUrlBase?: string
    disableSlackForward?: boolean
    disableImageVerification?: boolean
}>()

const emit = defineEmits<{
    (e: "edit-message", payload: { id: number; text: string }): void
    (e: "open-slack-settings"): void
    (e: "reply", message: Message): void
}>()

const EDIT_WINDOW_MS = 30 * 60 * 1000

const isEditableMessage = computed(() =>
    props.canEdit === true &&
    props.viewerType === "agent" &&
    props.message.sender_type === "agent" &&
    (props.message.message_type ?? "text") === "text" &&
    !!props.message.id &&
    props.message._status !== "sending" &&
    Date.now() - new Date(props.message.created_at).getTime() < EDIT_WINDOW_MS
)

const isEditingMessage = ref(false)
const editText = ref("")

const startEditMessage = () => {
    editText.value = props.message.original?.text || props.message.message_text
    isEditingMessage.value = true
}

const cancelEditMessage = () => {
    isEditingMessage.value = false
}

const saveEditMessage = () => {
    const text = editText.value.trim()
    if (text && props.message.id && text !== (props.message.original?.text || props.message.message_text)) {
        emit("edit-message", { id: props.message.id, text })
    }
    isEditingMessage.value = false
}

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const currentOrganisation = computed(
    () => String((route().params as Record<string, any>)?.organisation ?? "aw")
)

const { languages, fetchLanguages, getLanguageIdByCode } = useChatLanguages(baseUrl)

const isFromViewer = computed(() => {
    if (props.viewerType === "agent") {
        return props.message.sender_type === "agent"
    }

    return ["user", "guest"].includes(props.message.sender_type)
})

const isSending = computed(() => props.message._status === "sending")

const shouldHideTranslationBlock = computed(() =>
    props.viewerType === "user" &&
    props.message.sender_type === "user"
)

const canShowTranslation = computed(() => {
    if (
        props.viewerType === "user" &&
        props.message.sender_type === "guest"
    ) {
        return false
    }

    return true
})

const bubbleClass = computed(() => ({
    "bubble-primary": isFromViewer.value,
    "bubble-secondary": !isFromViewer.value,
}))

const time = computed(() =>
    new Date(props.message.created_at).toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    })
)

// WhatsApp reports a full delivery lifecycle (sent → delivered → read); website
// chat only knows read/unread, so it keeps the original two-state tick.
const waStatus = computed<string | null>(() => props.message.metadata?.wa_status ?? null)

const isFailed = computed(() => waStatus.value === "failed" || props.message._status === "failed")

const readIcon = computed(() => {
    if (isFailed.value) {
        return faExclamationCircle
    }

    if (waStatus.value) {
        return ["delivered", "read"].includes(waStatus.value) ? faCheckDouble : faCheck
    }

    return props.message.is_read ? faCheckDouble : faCheck
})

const readIconClass = computed(() => {
    if (isFailed.value) {
        return "text-red-500"
    }

    return waStatus.value === "read" ? "text-sky-400" : ""
})

const readIconLabel = computed(() => {
    if (isFailed.value) {
        return props.message.metadata?.wa_error?.message ?? trans("Failed to send")
    }

    return waStatus.value ? trans(waStatus.value) : ""
})

const agentDisplayName = computed(() => {
    return props.agentName ?? "Agent"
})

const showSenderLabel = computed(() =>
    props.viewerType === "agent" && props.message.sender_type !== "system"
)

const firstName = (name?: string | null): string =>
    (name ?? "").trim().split(/\s+/)[0] || ""

const senderLabel = computed(() => {
    if (props.message.sender_type === "agent") {
        const fullName = props.message.sender_name ?? props.agentName ?? layout?.user?.contact_name ?? "Agent"
        return firstName(fullName) || "Agent"
    }
    return props.contactName ?? "Customer"
})

// A status notice is a timeline marker, not something anyone replies to or reacts to,
// so it renders as the same chip the event stream uses.
const isSystemNotice = computed(() => props.message.sender_type === "system")

// Quoting is opt-in: only channels that can carry a reply upstream ask for the button.
const canReplyToMessage = computed(() => props.canReply === true && !!props.message.id)

const quotedLabel = computed(() => {
    const quoted = props.message.replied_to

    if (!quoted) return ""

    if (quoted.message_text) return quoted.message_text

    return quoted.file_name || trans(quoted.message_type === "image" ? "Photo" : "Attachment")
})

const quotedAuthor = computed(() =>
    props.message.replied_to?.sender_type === "agent"
        ? props.agentName ?? trans("Agent")
        : props.contactName ?? trans("Customer")
)

const isFile = computed(() => props.message.message_type === "file")

const fileMime = computed(() => props.message.file_mime ?? props.message.media_url?.mime ?? "")

// Ogg/Opus voice notes are sometimes sniffed as `application/ogg`, so the WhatsApp
// message type is trusted alongside the stored mime.
const isAudio = computed(() =>
    isFile.value &&
    (fileMime.value.startsWith("audio/") || props.message.metadata?.wa_type === "audio")
)

// WhatsApp voice notes arrive as ordinary audio with a `voice` flag; naming them
// as such reads better than "whatsapp-<media id>.ogg".
const audioLabel = computed(() =>
    props.message.metadata?.wa_payload?.voice
        ? trans("Voice message")
        : props.message.file_name ?? trans("Audio")
)

const audioUrl = computed(() => {
    const url = props.message.download_route?.url

    if (!url) return null

    return url + (url.includes("?") ? "&" : "?") + "inline=1"
})

const fileIcon = computed(() => {
    const mime = fileMime.value

    if (mime.includes("pdf")) return "📕"
    if (mime.includes("excel") || mime.includes("spreadsheet")) return "📊"
    if (mime.startsWith("audio/")) return "🎧"
    if (mime.startsWith("video/")) return "🎬"
    return "📄"
})

const isOpening = ref(false)

const openFile = () => {
    if (isOpening.value) return

    isOpening.value = true

    const url = props.message.download_route?.url
    if (url) {
        window.open(url, "_blank")
    }

    setTimeout(() => {
        isOpening.value = false
    }, 1500)
}

// feature translation
const localMessage = ref<Message | null>(null)
const selectedLanguage = ref("")
const isTranslating = ref<boolean>(false)
const showTranslation = ref(true)
const showLanguageSelect = ref(false)

const selectedLanguageId = computed(() =>
    getLanguageIdByCode(selectedLanguage.value)
)

const activeMessage = computed<Message>(() => {
    return localMessage.value ?? props.message
})

const displayText = computed(() => {
    if (props.message.sender_type === "system") {
        return trans(props.message.message_text)
    }

    return activeMessage.value.original?.text || props.message.message_text
})

const formattedText = computed(() => formatWhatsappMarkup(displayText.value))

const location = computed(() => {
    if (props.message.metadata?.wa_type !== "location") return null

    const payload = props.message.metadata?.wa_payload
    const latitude = Number(payload?.latitude)
    const longitude = Number(payload?.longitude)

    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return null

    return {
        latitude,
        longitude,
        name: payload?.name || trans("Shared location"),
        address: payload?.address || `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`,
    }
})

const sharedContacts = computed(() => {
    if (props.message.metadata?.wa_type !== "contacts") return []

    const payload = props.message.metadata?.wa_payload

    if (!Array.isArray(payload)) return []

    return payload.map((contact: any, index: number) => {
        const name = contact?.name?.formatted_name
            || [contact?.name?.first_name, contact?.name?.last_name].filter(Boolean).join(" ")
            || trans("Shared contact")

        return {
            key: `${index}-${name}`,
            name,
            initial: name.trim().charAt(0).toUpperCase(),
            phones: (contact?.phones ?? []).map((phone: any) => ({
                number: phone?.phone,
                label: phone?.type,
            })).filter((phone: any) => !!phone.number),
        }
    })
})

// WhatsApp delivers no content for these, so the bubble states what arrived rather than
// pretending the customer wrote that sentence.
const isUnsupportedMessage = computed(() => props.message.metadata?.wa_type === "unsupported")

const MAP = { tile: 256, zoom: 16, width: 240, height: 112 }

/**
 * Painting the map from raw tiles rather than OpenStreetMap's embed keeps its chrome —
 * the report link, donation banner and zoom buttons — out of a bubble that has no room
 * for them, and needs no API key the way a hosted static-map service would.
 */
const mapTiles = computed(() => {
    if (!location.value) return []

    const count = 2 ** MAP.zoom
    const latitude = (location.value.latitude * Math.PI) / 180
    const worldX = ((location.value.longitude + 180) / 360) * count * MAP.tile
    const worldY =
        ((1 - Math.log(Math.tan(latitude) + 1 / Math.cos(latitude)) / Math.PI) / 2) * count * MAP.tile

    // Offset of the visible box within the world, so the pin lands dead centre.
    const originX = worldX - MAP.width / 2
    const originY = worldY - MAP.height / 2

    const tiles = []

    for (let x = Math.floor(originX / MAP.tile); x <= Math.floor((originX + MAP.width - 1) / MAP.tile); x++) {
        for (let y = Math.floor(originY / MAP.tile); y <= Math.floor((originY + MAP.height - 1) / MAP.tile); y++) {
            if (y < 0 || y >= count) continue

            tiles.push({
                key: `${x}-${y}`,
                src: `https://tile.openstreetmap.org/${MAP.zoom}/${((x % count) + count) % count}/${y}.png`,
                left: x * MAP.tile - originX,
                top: y * MAP.tile - originY,
            })
        }
    }

    return tiles
})

const locationMapsUrl = computed(() =>
    location.value
        ? `https://www.google.com/maps/search/?api=1&query=${location.value.latitude},${location.value.longitude}`
        : ""
)

const canTranslate = computed(() =>
    props.viewerType === "agent" &&
    !isUnsupportedMessage.value &&
    (
        props.message.sender_type === "guest" ||
        props.message.sender_type === "user"
    )
)

const latestTranslation = computed<Translation | null>(() => {
    const list = activeMessage.value.translations
    if (!Array.isArray(list) || list.length === 0) return null
    return list[list.length - 1]
})

const isLongText = computed(() =>
    latestTranslation.value?.translated_text.length
        ? latestTranslation.value.translated_text.length > 120
        : false
)

watch(latestTranslation, () => {
    showTranslation.value = !isLongText.value
})

const translateMessage = async () => {
    if (!props.message.id || !selectedLanguageId.value) return

    isTranslating.value = true

    try {
        const { data } = await axios.post(
            `${baseUrl}${props.translateUrlBase ?? "/app/api/chats/messages"}/${props.message.id}/translate`,
            {
                target_language_id: selectedLanguageId.value,
            }
        )

        const translations = data?.data?.translations
        if (!Array.isArray(translations) || translations.length === 0) return

        const lastTranslation = translations[translations.length - 1]

        localMessage.value = {
            ...props.message,
            translations: [lastTranslation],
        }

        showTranslation.value = true
    } catch (e) {
        console.error("Translate failed", e)
    } finally {
        isTranslating.value = false
    }
}

// feature verify image
const isVerifyingImage = ref(false)

// The verify route resolves a website ChatMessage, so a WhatsApp id would land on an
// unrelated row. Opting out here rather than relying on the resource omitting the flag,
// which would arm the button the moment that field is copied across.
const canVerifyImage = computed(() =>
    props.viewerType === "agent" &&
    !props.disableImageVerification &&
    props.message.message_type === "image" &&
    !!props.message.is_verifiable_image &&
    activeMessage.value.is_validated == null
)

// feature forward to Slack
// Opting out rather than in: an absent Boolean prop is false, so an opt-in flag would
// silently strip forwarding from the website chat that already relies on it.
const canForwardToSlack = computed(() =>
    props.viewerType === "agent" && !!props.message.id && !props.disableSlackForward
)
const isForwardModalOpen = ref(false)

// feature hover toolbar (quick reactions) — persisted per message reactor
const showHoverToolbar = computed(() => !props.readonly && !!props.message.id)
const quickReactions = ["✅", "👀", "👏"] as const

const reactionState = ref<ReactionGroup[]>([])

watch(
    () => props.message.reactions,
    (val) => {
        reactionState.value = Array.isArray(val)
            ? val.map((g) => ({ emoji: g.emoji, count: g.count, reactors: [...g.reactors] }))
            : []
    },
    { immediate: true, deep: true }
)

const isMyReactor = (reactor: ReactionReactor): boolean => {
    if (props.viewerType === "agent") {
        return reactor.type === "agent" && reactor.id === (props.viewerReactorId ?? null)
    }
    return reactor.type !== "agent"
}

const hasMyReaction = (group: ReactionGroup): boolean =>
    group.reactors.some(isMyReactor)

const myReactedEmojis = computed(
    () => new Set(reactionState.value.filter(hasMyReaction).map((g) => g.emoji))
)

const myReactorStub = (): ReactionReactor =>
    props.viewerType === "agent"
        ? { type: "agent", id: props.viewerReactorId ?? null }
        : { type: "__me__", id: null }

const applyOptimisticToggle = (emoji: string): void => {
    const group = reactionState.value.find((g) => g.emoji === emoji)

    if (group && hasMyReaction(group)) {
        const idx = group.reactors.findIndex(isMyReactor)
        if (idx !== -1) {
            group.reactors.splice(idx, 1)
            group.count = Math.max(0, group.count - 1)
        }
        if (group.count === 0) {
            reactionState.value = reactionState.value.filter((g) => g.emoji !== emoji)
        }
        return
    }

    if (group) {
        group.reactors.push(myReactorStub())
        group.count += 1
        return
    }

    reactionState.value = [
        ...reactionState.value,
        { emoji, count: 1, reactors: [myReactorStub()] },
    ]
}

const isReacting = ref(false)

const toggleReaction = async (emoji: string) => {
    if (!props.message.id || isReacting.value) return

    const previous = reactionState.value.map((g) => ({
        emoji: g.emoji,
        count: g.count,
        reactors: [...g.reactors],
    }))

    applyOptimisticToggle(emoji)
    isReacting.value = true

    try {
        const { data } = await axios.post(
            `${props.apiBase ?? ""}${props.reactionUrlBase ?? "/app/api/chats/messages"}/${props.message.id}/reactions`,
            {
                emoji,
                reactor: props.viewerType === "agent" ? "agent" : "customer",
                session_ulid: props.sessionUlid ?? undefined,
            }
        )

        const serverReactions = data?.data?.reactions
        reactionState.value = Array.isArray(serverReactions)
            ? serverReactions.map((g: ReactionGroup) => ({ emoji: g.emoji, count: g.count, reactors: [...g.reactors] }))
            : reactionState.value
    } catch (e) {
        reactionState.value = previous
        notify({ title: trans("Failed"), text: trans("Could not update reaction."), type: "error" })
    } finally {
        isReacting.value = false
    }
}

// full emoji picker for "Add reaction" — reuses the persisted toggleReaction
const isEmojiPickerOpen = ref(false)
const emojiPickerRef = ref<HTMLElement | null>(null)
const emojiPalette = [
    "😀", "😃", "😄", "😁", "😆", "😅", "🤣", "😂", "🙂", "🙃",
    "😉", "😊", "😇", "🥰", "😍", "😘", "😗", "😋", "😛", "😜",
    "🤪", "🤨", "🧐", "🤓", "😎", "🥳", "😏", "😒", "😞", "😔",
    "😢", "😭", "😤", "😠", "😡", "🤯", "😳", "🥵", "🥶", "😱",
    "😨", "😰", "😅", "🤗", "🤔", "🤫", "🤭", "🤐", "😴", "🤤",
    "👍", "👎", "👏", "🙌", "🙏", "👀", "🎉", "🔥", "💯", "✅",
]

const selectEmoji = (emoji: string) => {
    toggleReaction(emoji)
    isEmojiPickerOpen.value = false
}

const handleClickOutsideEmojiPicker = (e: MouseEvent) => {
    if (isEmojiPickerOpen.value && emojiPickerRef.value && !emojiPickerRef.value.contains(e.target as Node)) {
        isEmojiPickerOpen.value = false
    }
}

const verifyImage = async () => {
    if (!props.message.id || isVerifyingImage.value) return

    isVerifyingImage.value = true

    try {
        const { data } = await axios.post(
            route("grp.org.chat.agents.messages.verify_image", [currentOrganisation.value, props.message.id])
        )

        if (data?.data) {
            localMessage.value = {
                ...props.message,
                is_ai_generated: data.data.is_ai_generated,
                is_validated: data.data.is_validated,
                ai_verification: data.data.ai_verification ?? null,
            }
        }
    } catch (e) {
        console.error("Verify image failed", e)
    } finally {
        isVerifyingImage.value = false
    }
}

const verificationReasoning = computed(() => activeMessage.value.ai_verification?.reasoning ?? "")

onMounted(() => {
    if (canTranslate.value) fetchLanguages()
    document.addEventListener("click", handleClickOutsideEmojiPicker)
})

onUnmounted(() => {
    document.removeEventListener("click", handleClickOutsideEmojiPicker)
})

watch(
    () => props.message,
    (val) => (localMessage.value = val),
    { immediate: true }
)

watch(selectedLanguage, async (val) => {
    if (!val) return
    await translateMessage()
    showLanguageSelect.value = false
})
</script>

<template>
    <div v-if="isSystemNotice" class="w-full flex justify-center">
        <ChatTimelineEvent :event="{ description: displayText, created_at: message.created_at }" />
    </div>

    <div v-else class="flex flex-col w-full group/msg" :class="isFromViewer ? 'items-end' : 'items-start'">
        <div class="mb-0.5 text-[11px] text-gray-500 px-1 max-w-[78%]"
            v-if="props.message.sender_type === 'agent' && props.viewerType === 'user'">
            {{ agentDisplayName }} (Agent)
        </div>
        <div class="relative max-w-[70%]">
            <div v-if="showHoverToolbar"
                class="absolute -top-5 z-20 flex items-center gap-0.5 p-1 rounded-full bg-white border border-gray-200 shadow-lg whitespace-nowrap opacity-0 scale-95 pointer-events-none group-hover/msg:opacity-100 group-hover/msg:scale-100 group-hover/msg:pointer-events-auto transition-all duration-150"
                :class="isFromViewer ? 'right-0' : 'left-0'">
                <button
                    v-for="emoji in quickReactions"
                    :key="emoji"
                    type="button"
                    v-tooltip.top="trans('React')"
                    class="w-[33px] h-[33px] flex items-center justify-center text-lg rounded-full hover:bg-gray-100 hover:scale-110 transition-all"
                    :class="myReactedEmojis.has(emoji) ? 'bg-indigo-50 ring-1 ring-indigo-200' : ''"
                    @click="toggleReaction(emoji)"
                >
                    {{ emoji }}
                </button>

                <span class="w-px h-5 bg-gray-200 mx-0.5"></span>

                <button v-if="canReplyToMessage" type="button" v-tooltip.top="trans('Reply')"
                    class="w-[33px] h-[33px] flex items-center justify-center text-gray-500 rounded-full hover:bg-gray-100 hover:!text-indigo-600 hover:scale-110 transition-all"
                    @click="emit('reply', message)">
                    <FontAwesomeIcon :icon="faReply" class="text-sm" />
                </button>

                <div class="relative" ref="emojiPickerRef">
                    <button type="button" v-tooltip.top="trans('Add reaction')"
                        class="w-[33px] h-[33px] flex items-center justify-center text-gray-500 rounded-full hover:bg-gray-100 hover:!text-indigo-600 hover:scale-110 transition-all"
                        @click="isEmojiPickerOpen = !isEmojiPickerOpen">
                        <FontAwesomeIcon :icon="faFaceSmile" class="text-sm" />
                    </button>

                    <div v-if="isEmojiPickerOpen"
                        class="absolute top-full mt-1.5 z-20 w-56 max-h-48 overflow-y-auto grid grid-cols-8 gap-0.5 p-2 rounded-lg bg-white border border-gray-200 shadow-lg"
                        :class="isFromViewer ? 'right-0' : 'left-0'">
                        <button
                            v-for="emoji in emojiPalette"
                            :key="emoji"
                            type="button"
                            class="w-6 h-6 flex items-center justify-center text-base rounded hover:bg-gray-100 transition-colors"
                            :class="myReactedEmojis.has(emoji) ? 'bg-indigo-50' : ''"
                            @click="selectEmoji(emoji)"
                        >
                            {{ emoji }}
                        </button>
                    </div>
                </div>

                <button v-if="canForwardToSlack" type="button" v-tooltip.top="trans('Forward message…')"
                    class="w-[33px] h-[33px] flex items-center justify-center text-gray-500 rounded-full hover:bg-gray-100 hover:!text-indigo-600 hover:scale-110 transition-all"
                    @click="isForwardModalOpen = true">
                    <FontAwesomeIcon :icon="faShare" class="text-sm" />
                </button>
            </div>

            <div class="flex flex-col gap-0.5 text-sm leading-relaxed shadow-sm px-3.5 py-2.5 rounded-2xl"
                :class="[bubbleClass, showHoverToolbar && viewerType === 'agent' ? 'min-w-[260px]' : '']">

            <div v-if="showSenderLabel" class="text-[11px] font-semibold mb-0.5 opacity-70">
                {{ senderLabel }}
            </div>

            <div v-if="message.replied_to"
                class="mb-1 rounded-md border-l-[3px] border-current bg-black/5 px-2 py-1 text-[11px] leading-snug opacity-90">
                <div class="font-semibold opacity-70">{{ quotedAuthor }}</div>
                <div class="opacity-70 line-clamp-2 break-words">{{ quotedLabel }}</div>
            </div>

            <div v-if="sharedContacts.length" class="mb-1 flex w-[240px] max-w-full flex-col gap-1.5">
                <div v-for="contact in sharedContacts" :key="contact.key"
                    class="rounded-lg border border-black/10 bg-white px-2.5 py-2">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-200 text-[11px] font-semibold text-gray-600">
                            {{ contact.initial }}
                        </span>
                        <span class="min-w-0 truncate text-xs font-semibold text-gray-800">{{ contact.name }}</span>
                    </div>

                    <div v-for="phone in contact.phones" :key="phone.number"
                        class="mt-1.5 flex items-center gap-2 border-t border-gray-100 pt-1.5">
                        <FontAwesomeIcon :icon="faPhone" class="text-[10px] text-gray-400" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[11px] text-gray-700">{{ phone.number }}</div>
                            <div v-if="phone.label" class="text-[10px] text-gray-400">{{ phone.label }}</div>
                        </div>
                        <button type="button" v-tooltip.top="trans('Copy number')" @click="useCopyText(phone.number)"
                            class="shrink-0 rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                            <FontAwesomeIcon :icon="faCopy" class="text-[10px]" />
                        </button>
                    </div>
                </div>
            </div>

            <a v-if="location" :href="locationMapsUrl" target="_blank" rel="noopener noreferrer"
                class="mb-1 block w-[240px] max-w-full overflow-hidden rounded-lg border border-black/10 bg-white transition hover:border-black/25">
                <div class="relative h-28 overflow-hidden bg-gray-100">
                    <img v-for="tile in mapTiles" :key="tile.key" :src="tile.src" alt="" loading="lazy"
                        class="absolute max-w-none"
                        :style="{ left: `${tile.left}px`, top: `${tile.top}px`, width: '256px', height: '256px' }" />

                    <FontAwesomeIcon :icon="faLocationDot"
                        class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-full text-xl text-red-500 drop-shadow" />

                    <span class="absolute bottom-0 right-0 bg-white/75 px-1 text-[9px] leading-tight text-gray-500">
                        © OpenStreetMap
                    </span>
                </div>

                <div class="flex items-start gap-1.5 px-2.5 py-2">
                    <FontAwesomeIcon :icon="faLocationDot" class="mt-0.5 text-[11px] text-red-500" />
                    <div class="min-w-0">
                        <div class="truncate text-xs font-semibold text-gray-800">{{ location.name }}</div>
                        <div class="text-[11px] leading-snug text-gray-500">{{ location.address }}</div>
                    </div>
                </div>
            </a>

            <AudioPlayer v-if="isAudio && audioUrl" :src="audioUrl"
                :is-voice="!!message.metadata?.wa_payload?.voice" :label="audioLabel"
                :download-url="message.download_route?.url" />

            <div v-if="isFile && !isAudio && message.media_url" @click="openFile"
                class="mb-1 flex items-center gap-3 p-3 rounded-lg border bg-white max-w-xs transition" :class="isOpening
                    ? 'opacity-60 cursor-not-allowed'
                    : 'cursor-pointer hover:bg-gray-50'">
                <div class="text-2xl">
                    {{ fileIcon }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate text-gray-400">
                        {{ message.file_name || message.media_url.name }}
                    </div>
                    <div class="text-xs opacity-60 text-red-600">
                        {{ trans("Click to download") }}
                    </div>
                </div>
            </div>

            <Image v-if="message.message_type === 'image' && message.media_url" :src="message.media_url.webp" preview
                imageClass="rounded-lg max-w-full max-h-64 min-h-[96px] min-w-[96px] object-contain cursor-pointer bg-gray-50"
                class="mb-1 block" />

            <div v-if="viewerType === 'agent' && message.message_type === 'image' && activeMessage.is_validated === true"
                class="mt-1" :title="verificationReasoning">
                <span v-if="activeMessage.is_ai_generated"
                    class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">
                    <FontAwesomeIcon :icon="faRobot" class="text-[10px]" />
                    {{ trans("AI generated") }}
                </span>
                <span v-else
                    class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700">
                    <FontAwesomeIcon :icon="faShieldCheck" class="text-[10px]" />
                    {{ trans("Verified") }}
                </span>
            </div>

            <div v-if="canVerifyImage" class="mt-1">
                <button type="button" :disabled="isVerifyingImage" @click="verifyImage"
                    class="flex items-center gap-1 text-[10px] text-gray-500 hover:text-gray-700 underline disabled:opacity-50">
                    <LoadingIcon v-if="isVerifyingImage" />
                    <FontAwesomeIcon v-else :icon="faShieldCheck" class="text-[10px]" />
                    {{ isVerifyingImage ? trans("Verifying…") : trans("Verify image") }}
                </button>
            </div>

            <div v-if="isUnsupportedMessage"
                class="inline-flex w-fit items-center gap-1.5 text-[11px] italic opacity-60">
                <FontAwesomeIcon :icon="faCircleExclamation" class="text-[10px]" />
                <span>{{ displayText || trans("Unsupported message") }}</span>
            </div>

            <p v-else-if="!isEditingMessage && !location && !sharedContacts.length && formatMarkup" class="whitespace-pre-wrap break-words"
                v-html="formattedText" />

            <p v-else-if="!isEditingMessage && !location && !sharedContacts.length" class="whitespace-pre-wrap break-words">
                {{ displayText }}
            </p>

            <div v-else-if="isEditingMessage" class="flex flex-col gap-1.5 min-w-[220px]">
                <textarea v-model="editText" rows="2"
                    class="w-full text-sm rounded-md border border-gray-300 px-2 py-1.5 text-gray-800 bg-white focus:outline-none focus:ring-1 resize-y"
                    @keydown.enter.exact.prevent="saveEditMessage" @keydown.esc="cancelEditMessage" />
                <div class="flex items-center justify-end gap-2">
                    <button type="button" class="text-[11px] underline opacity-80" @click="cancelEditMessage">
                        {{ trans("Cancel") }}
                    </button>
                    <button type="button"
                        class="text-[11px] font-semibold px-2 py-0.5 rounded bg-white text-gray-800 border border-gray-300 hover:bg-gray-50"
                        @click="saveEditMessage">
                        {{ trans("Save") }}
                    </button>
                </div>
            </div>
            <div v-if="
                message?.is_offline_message &&
                !(props.message.sender_type === 'guest' && props.viewerType === 'user')
            " class="text-[10px] text-amber-600 mb-1 font-medium">
                {{ trans('Offline message') }}
            </div>

            <div v-if="canShowTranslation && (latestTranslation || isTranslating)"
                class="mt-1 text-xs italic opacity-80 border-l-2 pl-2">
                <div v-if="isTranslating" class="flex items-center gap-1 text-[10px]">
                    <LoadingIcon />
                    <span>Translating…</span>
                </div>

                <template v-else>
                    <div v-if="showTranslation && !shouldHideTranslationBlock">
                        {{ latestTranslation!.translated_text }}
                    </div>

                    <span v-else-if="!shouldHideTranslationBlock" class="cursor-pointer underline text-gray-500"
                        @click="showTranslation = true">
                        Show translation
                    </span>

                    <div v-if="showTranslation && !shouldHideTranslationBlock"
                        class="flex items-center gap-1 mt-0.5 opacity-70 text-[10px] not-italic">
                        <img v-if="latestTranslation!.language_flag" :src="latestTranslation!.language_flag"
                            class="w-3 h-3 rounded-sm" />
                        <FontAwesomeIcon :icon="faLanguage" />
                        <span>{{ latestTranslation!.language_name }}</span>

                        <span v-if="isLongText" class="ml-2 cursor-pointer underline" @click="showTranslation = false">
                            Hide
                        </span>
                    </div>
                </template>
            </div>

            <div v-if="canTranslate" class="mt-1">
                <button v-if="!showLanguageSelect" @click="showLanguageSelect = true"
                    class="flex items-center gap-1 text-[10px] text-gray-500 hover:text-gray-700 underline">
                    <FontAwesomeIcon :icon="faLanguage" class="text-[10px]" />
                    Translate
                </button>
                <select v-else v-model="selectedLanguage" :disabled="isTranslating"
                    class="h-[20px] text-[10px] px-1.5 py-0 rounded border border-gray-300 bg-transparent text-gray-600 leading-none focus:outline-none focus:ring-0 disabled:opacity-50">
                    <option value="" disabled>
                        Translate To..
                    </option>
                    <option v-for="lang in languages" :key="lang.id" :value="lang.code">
                        {{ lang.native_name }}
                    </option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-1 text-[10px] opacity-70 min-h-[14px]">
                <button v-if="isEditableMessage && !isEditingMessage" type="button"
                    class="mr-auto underline leading-none" @click="startEditMessage">
                    {{ trans("Edit") }}
                </button>

                <span v-if="message.edited_at" class="italic leading-none">
                    {{ trans("edited") }}
                </span>
                <span v-if="!isSending" class="leading-none">
                    {{ time }}
                </span>

                <span v-else class="flex items-center animate-pulse">
                    <LoadingIcon />
                </span>

                <span v-if="isFromViewer && !isSending" class="leading-none" :title="readIconLabel">
                    <FontAwesomeIcon :icon="readIcon" :class="readIconClass" />
                </span>
            </div>
        </div>
        </div>

        <div v-if="reactionState.length" class="flex flex-wrap gap-1 mt-1 px-1"
            :class="isFromViewer ? 'justify-end' : 'justify-start'">
            <button
                v-for="group in reactionState"
                :key="group.emoji"
                type="button"
                class="flex items-center gap-1 text-xs px-1.5 py-0.5 rounded-full border transition-colors"
                :class="myReactedEmojis.has(group.emoji)
                    ? 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
                    : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-gray-100'"
                @click="toggleReaction(group.emoji)"
            >
                <span>{{ group.emoji }}</span>
                <span v-if="group.count > 1" class="font-semibold">{{ group.count }}</span>
            </button>
        </div>

        <SlackShareModal
            v-if="canForwardToSlack"
            :is-open="isForwardModalOpen"
            mode="message"
            :organisation="currentOrganisation"
            :message-id="message.id"
            @close="isForwardModalOpen = false"
            @open-settings="emit('open-slack-settings')"
        />
    </div>
</template>

<style scoped>
.bubble-primary {
    background-color: v-bind("layout.app.theme[4]");
    color: v-bind("layout.app.theme[5]");
    border-bottom-right-radius: 4px;
}

.bubble-secondary {
    @apply bg-white text-gray-800 border border-gray-200;
    border-bottom-left-radius: 4px;
}

</style>
