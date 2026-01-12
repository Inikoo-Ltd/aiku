<script setup lang="ts">
import { inject, computed, ref } from "vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faCheckDouble } from "@far"
import Image from "primevue/image"

type SenderType = "guest" | "user" | "agent" | "system"
type MessageStatus = "sending" | "sent" | "failed"
type ViewerType = "user" | "agent"

interface Message {
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
    _status?: MessageStatus
}

const props = defineProps<{
    message: Message
    viewerType: ViewerType
}>()

const layout = inject<any>("layout")

const isUser = computed(
    () => props.message.sender_type === "guest" || props.message.sender_type === "user"
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

const readIcon = computed(() => (props.message.is_read ? faCheckDouble : faCheck))

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
</script>

<template>
    <div class="flex flex-col gap-0.5 text-sm leading-snug shadow-sm max-w-[78%] px-2.5 py-1.5 rounded-xl"
        :class="bubbleClass">
        <p class="whitespace-pre-wrap break-words">{{ message.message_text }}</p>

        <Image v-if="message.message_type === 'image' && message.media_url" :src="message.media_url.webp" preview
            imageClass="rounded-lg max-w-full cursor-pointer" class="mt-1" />


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
                    Click to download
                </div>
            </div>
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
