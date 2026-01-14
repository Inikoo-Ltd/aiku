<script setup lang="ts">
import { inject, computed, ref, onMounted, watch } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faCheckDouble, faLanguage } from "@far"
import axios from "axios"
import { useChatLanguages } from "@/Composables/useLanguages"

type SenderType = "guest" | "user" | "agent" | "system"
type MessageStatus = "sending" | "sent" | "failed"
type ViewerType = "user" | "agent"

interface Message {
    sender_type: SenderType
    message_text: string
    created_at: string
    is_read?: boolean
    id?: number
    _status?: MessageStatus
    original?: Translation
    translations?: Translation[]
}

interface Translation {
    chat_translation_id: number
    language_id: number
    translated_text: string
    language_name: string
    language_code: string
    language_flag: string | null
    text: string
}

const props = defineProps<{
    message: Message
    viewerType: ViewerType
}>()

const layout = inject<any>("layout")
const baseUrl = layout?.appUrl ?? ""

const { languages, fetchLanguages, getLanguageIdByCode } = useChatLanguages(baseUrl)

const isUser = computed(() =>
    props.message.sender_type === "guest" ||
    props.message.sender_type === "user"
)

const isFromViewer = computed(() => {
    if (props.viewerType === "agent") {
        return props.message.sender_type === "agent"
    }

    return ["user", "guest"].includes(props.message.sender_type)
})

const isSending = computed(() => props.message._status === "sending")

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

onMounted(() => {
    if (canTranslate.value) fetchLanguages()
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

const canShowTranslation = computed(() => {
    if (
        props.viewerType === "user" &&
        props.message.sender_type === "guest"
    ) {
        return false
    }

    return true
})
</script>

<template>
    <div class="flex flex-col gap-0.5 text-sm leading-snug shadow-sm max-w-[78%] px-2.5 py-1.5 rounded-xl"
        :class="bubbleClass">
        <p class="whitespace-pre-wrap break-words">
            {{ activeMessage.original?.text || props.message.message_text }}
        </p>

        <div v-if="canShowTranslation && (latestTranslation || isTranslating)"
            class="mt-1 text-xs italic opacity-80 border-l-2 pl-2">
            <div v-if="isTranslating" class="flex items-center gap-1 text-[10px]">
                <LoadingIcon />
                <span>Translating…</span>
            </div>

            <template v-else>
                <div v-if="showTranslation">
                    {{ latestTranslation!.translated_text }}
                </div>

                <span v-else class="cursor-pointer underline text-gray-500" @click="showTranslation = true">
                    Show translation
                </span>

                <div v-if="showTranslation" class="flex items-center gap-1 mt-0.5 opacity-70 text-[10px] not-italic">
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
</template>


<style scoped>
.bubble-primary {
    background-color: v-bind("layout.app.theme[4]");
    color: v-bind("layout.app.theme[5]");
    border-bottom-right-radius: 4px;
}

.bubble-secondary {
    @apply bg-gray-200 text-gray-800;
    border-bottom-left-radius: 4px;
}

.bubble-system {
    @apply bg-amber-100 text-amber-800 italic text-xs;
}
</style>
