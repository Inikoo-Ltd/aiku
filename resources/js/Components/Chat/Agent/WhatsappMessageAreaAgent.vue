<script setup lang="ts">
import { ref, watch, onMounted, inject, computed, nextTick } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
    faPaperPlane,
    faArrowLeft,
    faImage,
    faPaperclip,
    faXmark,
    faFilePdf,
    faFileLines,
} from "@fortawesome/free-solid-svg-icons"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import type { ChatMessage, SessionAPI } from "@/types/Chat/chat"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { faUser, faSpinner } from "@far"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"
import { notify } from "@kyvg/vue3-notification"
import { Dialog } from "primevue"

type LocalMessageStatus = "sending" | "sent" | "failed"

type LocalChatMessage = ChatMessage & {
    _status?: LocalMessageStatus
    _tempId?: string
}

interface WhatsappTemplate {
    id: number
    name: string
    language: string
    category: string | null
    body: string
    parameter_count: number
}

const props = defineProps<{
    messages: ChatMessage[]
    session: SessionAPI | null
    organisationSlug: string
}>()

const emit = defineEmits(["back", "messages-read"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const chatSession = computed(() => props.session)
const isClosed = computed(() => chatSession.value?.status === "closed")

const messagesLocal = ref<LocalChatMessage[]>([])
const newMessage = ref("")

const messageInput = ref<HTMLTextAreaElement>()
const messagesContainer = ref<HTMLDivElement>()

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

const isLoadingMore = ref(false)
const canLoadMore = ref(false)
const nextCursor = ref<string | null>(null)
const isSending = ref(false)

const selectedFile = ref<File | null>(null)
const previewUrl = ref<string | null>(null)
const previewType = ref<"image" | "file" | null>(null)

const handleImageSelect = (e: Event) => {
    const file = (e.target as HTMLInputElement)?.files?.[0]
    if (!file) return

    if (!IMAGE_TYPES.includes(file.type)) {
        notify({ title: trans("Failed"), text: trans("Image format not supported"), type: "error" })
        return
    }

    if (file.size > MAX_SIZE) {
        notify({ title: trans("Failed"), text: trans("Maximum image size 10MB"), type: "error" })
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
        notify({ title: trans("Failed"), text: trans("File format not supported"), type: "error" })
        return
    }

    if (file.size > MAX_SIZE) {
        notify({ title: trans("Failed"), text: trans("Maximum file size 10MB"), type: "error" })
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

        if (!loadMore) {
            messagesLocal.value = messages.map((m: ChatMessage) => ({ ...m, _status: "sent" }))
        } else {
            messagesLocal.value.unshift(
                ...messages.map((m: ChatMessage) => ({ ...m, _status: "sent" }))
            )
        }

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

const groupedMessages = computed(() => {
    const groups: Record<string, LocalChatMessage[]> = {}

    messagesLocal.value
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
            title: trans("Error"),
            text: e?.response?.data?.message ?? trans("Failed to send WhatsApp message"),
            type: "error",
        })
    } finally {
        isSending.value = false
    }
}

const sendMessage = async () => {
    const hasText = !!newMessage.value.trim()
    const hasFile = !!selectedFile.value

    if ((!hasText && !hasFile) || isSending.value) return

    const tempId = `tmp-${Date.now()}`
    const messageType = hasFile
        ? (IMAGE_TYPES.includes(selectedFile.value!.type) ? "image" : "file")
        : "text"

    const optimisticMessage: LocalChatMessage = {
        id: tempId as any,
        _tempId: tempId,
        message_text: newMessage.value ?? "",
        media_url: messageType === "image" ? previewUrl.value : null,
        sender_type: "agent",
        message_type: messageType,
        created_at: new Date().toISOString(),
        _status: "sending",
    }

    const formData = new FormData()
    formData.append("message_text", newMessage.value ?? "")
    if (selectedFile.value) {
        formData.append(messageType === "image" ? "image" : "file", selectedFile.value)
    }

    newMessage.value = ""
    removeFile()
    autoResize()

    await postMessage(formData, optimisticMessage)
}

// Template picker
const isTemplateDialogOpen = ref(false)
const templates = ref<WhatsappTemplate[]>([])
const isLoadingTemplates = ref(false)
const selectedTemplate = ref<WhatsappTemplate | null>(null)
const templateParameters = ref<string[]>([])

const openTemplateDialog = async () => {
    isTemplateDialogOpen.value = true
    selectedTemplate.value = null
    templateParameters.value = []

    if (templates.value.length || !chatSession.value?.shop?.id) return

    isLoadingTemplates.value = true
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/meta/templates`, {
            params: { shop_id: chatSession.value.shop.id },
        })
        templates.value = data?.data ?? []
    } catch (e) {
        notify({ title: trans("Error"), text: trans("Failed to load templates"), type: "error" })
    } finally {
        isLoadingTemplates.value = false
    }
}

const selectTemplate = (template: WhatsappTemplate) => {
    selectedTemplate.value = template
    templateParameters.value = Array(template.parameter_count).fill("")
}

const templatePreview = computed(() => {
    if (!selectedTemplate.value) return ""
    let body = selectedTemplate.value.body
    templateParameters.value.forEach((parameter, index) => {
        if (parameter) {
            body = body.replaceAll(`{{${index + 1}}}`, parameter)
        }
    })
    return body
})

const canSendTemplate = computed(() =>
    !!selectedTemplate.value && templateParameters.value.every((parameter) => parameter.trim() !== "")
)

const sendTemplate = async () => {
    if (!selectedTemplate.value || !canSendTemplate.value || isSending.value) return

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
    formData.append("template_name", selectedTemplate.value.name)
    formData.append("template_language", selectedTemplate.value.language)
    templateParameters.value.forEach((parameter, index) => {
        formData.append(`template_parameters[${index}]`, parameter)
    })

    isTemplateDialogOpen.value = false

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
    () => chatSession.value?.ulid,
    async () => {
        messagesLocal.value = []
        nextCursor.value = null
        canLoadMore.value = false
        await getMessages()
    }
)

onMounted(() => {
    getMessages()
})
</script>

<template>
    <div class="flex flex-col h-full bg-white overflow-hidden">
        <!-- Header -->
        <header class="flex items-center gap-3 px-3 py-2 border-b">
            <button @click="$emit('back')">
                <FontAwesomeIcon :icon="faArrowLeft" class="text-gray-400" />
            </button>

            <div
                class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-green-100 text-green-600">
                <Image v-if="session?.image" :src="session?.image" class="w-full h-full rounded-full object-cover" />
                <FontAwesomeIcon v-else :icon="faUser" class="text-sm" />
            </div>

            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold truncate">
                    {{ session?.guest_identifier || session?.contact_name }}
                </div>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <FontAwesomeIcon :icon="faWhatsapp" class="text-[11px] text-green-600" />
                    <span v-if="session?.phone_number" class="text-[11px] text-gray-400 truncate">
                        {{ session.phone_number }}
                    </span>
                    <span v-if="session?.status"
                        class="text-[10px] font-medium capitalize rounded-full px-1.5 py-0.5"
                        :class="statusBadgeClass">
                        {{ session.status }}
                    </span>
                    <span v-if="session?.shop?.name" class="text-[11px] text-gray-400 truncate">
                        {{ session.shop.name }}
                    </span>
                </div>
            </div>
        </header>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto px-3 py-2 space-y-3 bg-[#F0F4F8]">
            <div class="flex justify-center" v-if="canLoadMore && nextCursor">
                <button @click="getMessages(true)" :disabled="isLoadingMore" class="flex items-center gap-2 text-xs text-gray-600 px-4 py-1.5
               border rounded-full hover:bg-gray-100 disabled:opacity-50">
                    <FontAwesomeIcon v-if="isLoadingMore" :icon="faSpinner" class="animate-spin text-[10px]" />
                    <span>
                        {{ isLoadingMore ? trans('Loading messages…') : trans('Load older messages') }}
                    </span>
                </button>
            </div>

            <template v-for="(msgs, date) in groupedMessages" :key="date">
                <div class="text-center text-xs text-gray-400">{{ date }}</div>
                <div v-for="msg in msgs" :key="msg.id" class="flex"
                    :class="msg.sender_type === 'agent' ? 'justify-end' : 'justify-start'">
                    <BubbleChat :message="msg" viewerType="agent"
                        :contactName="session?.contact_name || session?.guest_identifier"
                        :canEdit="false" />
                </div>
            </template>
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

        <!-- Footer: closed banner -->
        <footer v-if="isClosed" class="px-3 py-3 bg-white border-t">
            <div class="px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200 text-xs text-gray-600 text-center">
                {{ trans('This chat has been closed') }}
            </div>
        </footer>

        <!-- Footer: composer -->
        <footer v-else class="px-3 py-2 bg-white">
            <input ref="imageInput" type="file" accept=".webp,.jpg,.jpeg,.png,.avif" class="hidden"
                @change="handleImageSelect" />
            <input ref="fileInput" type="file" accept=".pdf,.xls,.xlsx" class="hidden" @change="handleDocSelect" />

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm focus-within:border-gray-400 focus-within:shadow-md transition-shadow">
                <textarea ref="messageInput" v-model="newMessage" @input="autoResize"
                    @keydown.enter.exact.prevent="sendMessage" rows="1" :placeholder="trans('Type message...')"
                    class="w-full resize-none px-4 pt-3 pb-1 text-sm leading-5 outline-none border-none ring-0 focus:outline-none focus:ring-0 rounded-t-xl bg-transparent" />

                <div class="flex items-center justify-between px-2 pb-2 pt-1">
                    <div class="flex items-center gap-1">
                        <button @click="imageInput?.click()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors" :title="trans('Upload image')">
                            <FontAwesomeIcon :icon="faImage" class="text-sm" />
                        </button>
                        <button @click="fileInput?.click()"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors" :title="trans('Upload file')">
                            <FontAwesomeIcon :icon="faPaperclip" class="text-sm" />
                        </button>
                        <button @click="openTemplateDialog"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-green-50 text-gray-500 hover:text-green-600 transition-colors" :title="trans('Send template message')">
                            <FontAwesomeIcon :icon="faFileLines" class="text-sm" />
                        </button>
                    </div>
                    <Button @click="sendMessage" :loading="isSending" :icon="faPaperPlane"></Button>
                </div>
            </div>
        </footer>

        <!-- Template picker dialog -->
        <Dialog v-model:visible="isTemplateDialogOpen" modal :header="trans('WhatsApp templates')"
            :style="{ width: '28rem' }">
            <div v-if="isLoadingTemplates" class="flex items-center justify-center py-8 text-gray-400">
                <FontAwesomeIcon :icon="faSpinner" class="animate-spin" />
            </div>

            <div v-else-if="!templates.length" class="py-8 text-center text-sm text-gray-400">
                {{ trans('No approved templates found for this shop') }}
            </div>

            <div v-else class="space-y-3">
                <div class="max-h-48 overflow-y-auto border rounded-lg divide-y">
                    <button v-for="template in templates" :key="template.id"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50 transition-colors"
                        :class="{ 'bg-green-50': selectedTemplate?.id === template.id }"
                        @click="selectTemplate(template)">
                        <div class="text-sm font-medium">{{ template.name }}</div>
                        <div class="text-[11px] text-gray-400">
                            {{ template.language }}<span v-if="template.category"> · {{ template.category }}</span>
                        </div>
                    </button>
                </div>

                <div v-if="selectedTemplate" class="space-y-2">
                    <div class="text-xs text-gray-500 whitespace-pre-line border rounded-lg p-3 bg-gray-50">
                        {{ templatePreview }}
                    </div>

                    <input v-for="(parameter, index) in templateParameters" :key="index"
                        v-model="templateParameters[index]" type="text"
                        :placeholder="`{{${index + 1}}}`"
                        class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:border-green-500" />

                    <div class="flex justify-end">
                        <Button @click="sendTemplate" :disabled="!canSendTemplate" :loading="isSending"
                            :label="trans('Send template')" :icon="faPaperPlane" />
                    </div>
                </div>
            </div>
        </Dialog>
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
