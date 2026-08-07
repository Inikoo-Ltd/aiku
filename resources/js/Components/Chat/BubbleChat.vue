<script setup lang="ts">
import { inject, computed, ref, onMounted, onUnmounted, watch } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faCheckDouble, faLanguage, faRobot, faShieldCheck, faBookmark } from "@far"
import { faShare, faFaceSmile, faEllipsisVertical } from "@fortawesome/free-solid-svg-icons"
import axios from "axios"
import { useChatLanguages } from "@/Composables/useLanguages"
import Image from "primevue/image"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import SlackShareModal from "@/Components/Chat/Agent/SlackShareModal.vue"

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
    download_route?: {
        url: string
    } | null
    is_read?: boolean
    id?: number
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
}>()

const emit = defineEmits<{
    (e: "edit-message", payload: { id: number; text: string }): void
    (e: "open-slack-settings"): void
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
    "bubble-system": props.message.sender_type === "system",
}))

const time = computed(() =>
    new Date(props.message.created_at).toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
    })
)

const readIcon = computed(() =>
    props.message.is_read ? faCheckDouble : faCheck
)

const agentDisplayName = computed(() => {
    return props.agentName ?? "Agent"
})

const showSenderLabel = computed(() =>
    props.viewerType === "agent" && props.message.sender_type !== "system"
)

const senderLabel = computed(() => {
    if (props.message.sender_type === "agent") {
        return props.agentName ?? layout?.user?.contact_name ?? "Agent"
    }
    return props.contactName ?? "Customer"
})

const isFile = computed(() => props.message.message_type === "file")

const fileIcon = computed(() => {
    const mime = props.message.media_url?.mime ?? ""

    if (mime.includes("pdf")) return "📕"
    if (mime.includes("excel") || mime.includes("spreadsheet")) return "📊"
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

const canTranslate = computed(() =>
    props.viewerType === "agent" &&
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
            `${baseUrl}/app/api/chats/messages/${props.message.id}/translate`,
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

const canVerifyImage = computed(() =>
    props.viewerType === "agent" &&
    props.message.message_type === "image" &&
    !!props.message.is_verifiable_image &&
    activeMessage.value.is_validated == null
)

// feature forward to Slack
const canForwardToSlack = computed(() => props.viewerType === "agent" && !!props.message.id)
const isForwardModalOpen = ref(false)

// feature hover toolbar (quick reactions) — UI only for now, no persistence/backend yet
const showHoverToolbar = computed(() => props.viewerType === "agent" && !!props.message.id)
const quickReactions = ["✅", "👀", "👏"] as const
const activeReactions = ref<Set<string>>(new Set())

const toggleReaction = (emoji: string) => {
    const next = new Set(activeReactions.value)
    if (next.has(emoji)) {
        next.delete(emoji)
    } else {
        next.add(emoji)
    }
    activeReactions.value = next
}

const notImplementedYet = () => {
    notify({ title: trans("Coming soon"), text: trans("This action isn't wired up yet."), type: "info" })
}

// full emoji picker for "Add reaction" — UI only, reuses the same local toggleReaction state
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
    <div class="flex flex-col w-full group/msg" :class="isFromViewer ? 'items-end' : 'items-start'">
        <div class="mb-0.5 text-[11px] text-gray-500 px-1 max-w-[78%]"
            v-if="props.message.sender_type === 'agent' && props.viewerType === 'user'">
            {{ agentDisplayName }} (Agent)
        </div>
        <div class="relative max-w-[70%]">
            <div v-if="showHoverToolbar"
                class="absolute -top-5 right-1 z-20 flex items-center gap-0.5 p-1 rounded-xl bg-white border border-gray-200 shadow-lg opacity-0 scale-95 pointer-events-none group-hover/msg:opacity-100 group-hover/msg:scale-100 group-hover/msg:pointer-events-auto transition-all duration-150">
                <button
                    v-for="emoji in quickReactions"
                    :key="emoji"
                    type="button"
                    v-tooltip.top="trans('React')"
                    class="w-[33px] h-[33px] flex items-center justify-center text-lg rounded-lg hover:bg-gray-100 hover:scale-110 transition-all"
                    :class="activeReactions.has(emoji) ? 'bg-indigo-50 ring-1 ring-indigo-200' : ''"
                    @click="toggleReaction(emoji)"
                >
                    {{ emoji }}
                </button>

                <span class="w-px h-5 bg-gray-200 mx-0.5"></span>

                <div class="relative" ref="emojiPickerRef">
                    <button type="button" v-tooltip.top="trans('Add reaction')"
                        class="w-[33px] h-[33px] flex items-center justify-center text-gray-500 rounded-lg hover:bg-gray-100 hover:!text-indigo-600 hover:scale-110 transition-all"
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
                            :class="activeReactions.has(emoji) ? 'bg-indigo-50' : ''"
                            @click="selectEmoji(emoji)"
                        >
                            {{ emoji }}
                        </button>
                    </div>
                </div>

                <button v-if="canForwardToSlack" type="button" v-tooltip.top="trans('Forward message…')"
                    class="w-[33px] h-[33px] flex items-center justify-center text-gray-500 rounded-lg hover:bg-gray-100 hover:!text-indigo-600 hover:scale-110 transition-all"
                    @click="isForwardModalOpen = true">
                    <FontAwesomeIcon :icon="faShare" class="text-sm" />
                </button>
                <button type="button" v-tooltip.top="trans('Save message')"
                    class="w-[33px] h-[33px] flex items-center justify-center text-gray-500 rounded-lg hover:bg-gray-100 hover:!text-indigo-600 hover:scale-110 transition-all"
                    @click="notImplementedYet">
                    <FontAwesomeIcon :icon="faBookmark" class="text-sm" />
                </button>
                <button type="button" v-tooltip.top="trans('More actions')"
                    class="w-[33px] h-[33px] flex items-center justify-center text-gray-500 rounded-lg hover:bg-gray-100 hover:!text-indigo-600 hover:scale-110 transition-all"
                    @click="notImplementedYet">
                    <FontAwesomeIcon :icon="faEllipsisVertical" class="text-sm" />
                </button>
            </div>

            <div class="flex flex-col gap-0.5 text-sm leading-relaxed shadow-sm px-3.5 py-2.5 rounded-2xl"
                :class="[bubbleClass, showHoverToolbar ? 'min-w-[260px]' : '']">

            <div v-if="showSenderLabel" class="text-[11px] font-semibold mb-0.5 opacity-70">
                {{ senderLabel }}
            </div>

            <p v-if="!isEditingMessage" class="whitespace-pre-wrap break-words">
                {{ displayText }}
            </p>

            <div v-else class="flex flex-col gap-1.5 min-w-[220px]">
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

            <Image v-if="message.message_type === 'image' && message.media_url" :src="message.media_url.webp" preview
                imageClass="rounded-lg max-w-full max-h-64 min-h-[96px] min-w-[96px] object-contain cursor-pointer bg-gray-50"
                class="mt-1 block" />

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

            <div v-if="isFile && message.media_url" @click="openFile"
                class="mt-1 flex items-center gap-3 p-3 rounded-lg border bg-white max-w-xs transition" :class="isOpening
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

                <span v-if="isFromViewer && !isSending" class="leading-none">
                    <FontAwesomeIcon :icon="readIcon" />
                </span>
            </div>
        </div>
        </div>

        <div v-if="activeReactions.size" class="flex flex-wrap gap-1 mt-1 px-1">
            <button
                v-for="emoji in activeReactions"
                :key="emoji"
                type="button"
                class="flex items-center gap-1 text-xs px-1.5 py-0.5 rounded-full border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors"
                @click="toggleReaction(emoji)"
            >
                {{ emoji }}
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

.bubble-system {
    @apply bg-amber-100 text-amber-800 italic text-xs;
}
</style>
