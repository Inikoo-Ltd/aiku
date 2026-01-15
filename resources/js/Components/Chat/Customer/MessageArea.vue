<script setup lang="ts">
import { ref, inject, onMounted, watch, computed, onUnmounted } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStar, faPlus, faSpinner, faPaperPlane, faImage, faPaperclip, faXmark, faFilePdf } from "@fortawesome/free-solid-svg-icons"
import GuestProfileForm from "@/Components/Chat/Customer/GuestProfileForm.vue"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"
import { notify } from "@kyvg/vue3-notification"

const props = defineProps({
    messages: {
        type: Array as () => any[],
        default: () => [],
    },
    session: {
        type: Object as () => any,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },

    assignedAgent: {
        type: String,
        default: null,
    },

    isInitialLoad: Boolean,
    isLoadingMore: Boolean,
    isRating: Boolean,
    rating: Number,
    isLoggedIn: Boolean,
})

const emit = defineEmits(["send-message", "reload", "mounted", "new-session"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const input = ref("")
const isSending = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)
const selectedRating = ref<number | null>(null)
const starPop = ref<number | null>(null)

const localMessages = ref<any[]>([])

watch(
    () => props.messages,
    (newVal) => {
        localMessages.value = [...newVal]
    },
    { immediate: true, deep: true }
)

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

const chatSession = computed(() => props.session)

const isTyping = ref<boolean>(false)
let typingTimeout: ReturnType<typeof setTimeout> | null = null

const agentTypingUser = ref<string | null>(null)
let agentTypingTimeout: ReturnType<typeof setTimeout> | null = null

let chatChannel: any = null

const getGuestProfileSubmitted = (): boolean => {
    try {
        const raw = localStorage.getItem("chat")
        if (!raw) return false
        const data = JSON.parse(raw)
        return data?.guest_profile_submitted === true
    } catch (e) {
        return false
    }
}

const guestProfileSubmitted = ref<boolean>(getGuestProfileSubmitted())
const onGuestProfileSubmitted = () => {
    guestProfileSubmitted.value = true
}

watch(
    () => props.session?.ulid,
    () => {
        guestProfileSubmitted.value = getGuestProfileSubmitted()
    }
)

const updateRating = async (r: number) => {
    starPop.value = r

    selectedRating.value = r

    if (props.session?.ulid) {
        await axios.put(`${baseUrl}/app/api/chats/sessions/${props.session.ulid}/update`, {
            rating: r,
        })
    }

    setTimeout(() => {
        starPop.value = null
    }, 300)
}

const groupedMessages = computed(() => {
    const groups: Record<string, any[]> = {}

    localMessages.value
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

const sendMessage = async () => {
    if (!props.isLoggedIn && !guestProfileSubmitted.value) return
    if (props.isRating) return

    const text = input.value.trim()
    const hasText = !!text
    const hasFile = !!selectedFile.value

    if (!hasText && !hasFile) return

    isSending.value = true
    input.value = ""

    if (typingTimeout) {
        clearTimeout(typingTimeout)
        typingTimeout = null
    }

    if (isTyping.value) {
        isTyping.value = false
        sendTypingStatus(false)
    }

    try {
        const payload: {
            text: string
            type: "text" | "image" | "file"
            image?: File
        } = {
            text,
            type: "text",
        }

        if (selectedFile.value) {
            payload.type = getMessageTypeFromFile(selectedFile.value)
            payload.file = selectedFile.value
        }

        emit("send-message", payload)
        removeFile()
    } catch (error) {
        console.error("❌ Error sending message:", error)
    } finally {
        isSending.value = false
    }
}

const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault()
        sendMessage()
    }
}

const onScroll = (e: any) => {
    const el = e.target
    if (el.scrollTop === 0) {
        emit("reload", true)
    }
}

const scrollToBottom = () => {
    if (messagesContainer.value) {
        setTimeout(() => {
            messagesContainer.value!.scrollTop = messagesContainer.value!.scrollHeight
        }, 50)
    }
}

const isUserMessage = (message: any) => {
    return message.sender_type === "guest" || message.sender_type === "user"
}

watch(
    () => props.messages,
    () => {
        if (props.isLoadingMore) return
        if (props.isInitialLoad) {
            scrollToBottom()
            return
        }

        const el = messagesContainer.value
        if (el) {
            const threshold = 150
            const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight

            if (distanceFromBottom < threshold) {
                scrollToBottom()
            }
        }
    },
    { deep: true }
)

const myUserName = props.isLoggedIn ? "user" : "guest"

const sendTypingStatus = async (status: boolean) => {
    if (!chatSession.value?.ulid) return

    try {
        await axios.post(`${baseUrl}/app/api/chats/typing`, {
            session_ulid: chatSession.value.ulid,
            user_name: myUserName,
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
    }, 2000)
}

const initSocket = () => {
    if (!chatSession.value?.ulid || !window.Echo) return

    if (chatChannel) {
        chatChannel.stopListening(".typing")
    }

    chatChannel = window.Echo.channel(`chat-session.${chatSession.value.ulid}`)

    chatChannel.listen(".typing", (payload: any) => {
        if (payload.user_name === myUserName) return

        if (payload.is_typing) {
            agentTypingUser.value = payload.user_name

            if (agentTypingTimeout) clearTimeout(agentTypingTimeout)

            agentTypingTimeout = setTimeout(() => {
                agentTypingUser.value = null
            }, 1500)

            return
        }

        if (agentTypingTimeout) clearTimeout(agentTypingTimeout)

        agentTypingTimeout = setTimeout(() => {
            agentTypingUser.value = null
        }, 800)
    })
}

watch(
    () => chatSession.value?.ulid,
    async () => {
        agentTypingUser.value = null
        if (chatChannel) chatChannel.stopListening(".typing")
        initSocket()
        await getMediaUrl(chatSession.value!.ulid)
    }
)

onMounted(async () => {
    initSocket()
    await getMediaUrl(chatSession.value!.ulid)
    emit("mounted")
    scrollToBottom()
})

onUnmounted(() => {
    if (chatChannel) chatChannel.stopListening(".typing")
})

defineExpose({
    removeFile,
})
</script>

<template>
    <div class="flex flex-col bg-white">
        <!-- Messages -->
        <div v-if="messages.length" ref="messagesContainer" @scroll="onScroll"
            class="bg-gray-50 px-3 py-2 space-y-2 overflow-y-auto min-h-[350px] max-h-[calc(100vh-400px)] scroll-smooth">
            <template v-for="(group, date) in groupedMessages" :key="date">
                <div class="mx-auto text-xs text-gray-400 flex justify-center">
                    {{ date }}
                </div>

                <div v-for="m in group" :key="m.id" class="flex"
                    :class="isUserMessage(m) ? 'justify-end' : 'justify-start'">
                    <BubbleChat :message="m" viewerType="user" :agentName="props.assignedAgent" />
                </div>
            </template>

            <div v-if="loading" class="flex justify-center py-3">
                <div class="w-5 h-5 rounded-full border-2 border-transparent animate-spin"
                    :style="{ borderTopColor: layout.app.theme[4] }" />
            </div>
        </div>

        <div v-if="agentTypingUser" class="text-xs text-gray-400 italic px-2 py-1">
            {{ agentTypingUser }} {{ trans("is typing...") }}
        </div>

        <!-- Empty -->
        <div v-if="!messages.length && isLoggedIn"
            class="flex-1 grid place-content-center text-gray-400 text-sm min-h-[350px] max-h-[calc(100vh-400px)]">
            {{ trans("Start the conversation") }}
        </div>

        <!-- Rating -->
        <div v-if="isRating" class="flex justify-between items-center border-t px-3 py-2">
            <div class="flex gap-1">
                <button v-for="n in 5" :key="n" @click="updateRating(n)">
                    <FontAwesomeIcon :icon="faStar" :class="n <= (selectedRating ?? rating ?? 0)
                        ? 'text-yellow-400'
                        : 'text-gray-300'
                        " />
                </button>
            </div>

            <button @click="$emit('new-session')" class="flex items-center gap-2 text-sm px-3 py-1 rounded border"
                :style="{
                    borderColor: layout.app.theme[4],
                    color: layout.app.theme[4],
                }">
                <FontAwesomeIcon :icon="faPlus" />
                New Chat
            </button>
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

        <!-- Input -->
        <div v-if="!isRating" class="border-t p-2 flex gap-2 items-end">
            <GuestProfileForm v-if="!isLoggedIn && !guestProfileSubmitted" :sessionUlid="session?.ulid"
                @submitted="onGuestProfileSubmitted" />

            <template v-else>
                <template v-if="isLoggedIn">
                    <button @click="imageInput?.click()"
                        class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100"
                        title="Upload image">
                        <FontAwesomeIcon :icon="faImage" />
                    </button>

                    <button @click="fileInput?.click()"
                        class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-gray-100"
                        title="Upload file">
                        <FontAwesomeIcon :icon="faPaperclip" />
                    </button>

                    <input ref="imageInput" type="file" accept=".webp,.jpg,.jpeg,.png,.avif" class="hidden"
                        @change="handleImageSelect" />

                    <input ref="fileInput" type="file" accept=".pdf,.xls,.xlsx" class="hidden"
                        @change="handleDocSelect" />
                </template>
                <textarea v-model="input" rows="1" @input="
                    () => {
                        handleTyping()
                    }
                " @keydown="handleKeyDown" placeholder="Type a message..."
                    class="flex-1 resize-none px-3 py-2 rounded-lg text-sm outline-none border"
                    :style="{ borderColor: layout.app.theme[4] }" />

                <Button :icon="isSending ? faSpinner : faPaperPlane" :loading="isSending" @click="sendMessage" />
            </template>

        </div>
    </div>
</template>

<style scoped>
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
