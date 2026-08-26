<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted, inject, computed, nextTick } from "vue"
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
    faTimesCircle,
    faRotateRight,
} from "@fortawesome/free-solid-svg-icons"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import type { ChatMessage, SessionAPI } from "@/types/Chat/chat"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { faUser, faSpinner } from "@far"
import BubbleChat from "@/Components/Chat/BubbleChat.vue"
import ChatTimelineEvent from "@/Components/Chat/ChatTimelineEvent.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { notify } from "@kyvg/vue3-notification"
import { Dialog } from "primevue"

type LocalMessageStatus = "sending" | "sent" | "failed"

type LocalChatMessage = ChatMessage & {
    _status?: LocalMessageStatus
    _tempId?: string
    metadata?: Record<string, any>
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

const emit = defineEmits(["back", "messages-read", "assign-self-success", "close-session"])

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""

const chatSession = computed(() => props.session)
const isClosed = computed(() => chatSession.value?.status === "closed")
const isWaiting = computed(() => !chatSession.value?.assigned_agent)
const isMyChat = computed(() => {
    if (!chatSession.value?.assigned_agent) return true
    return String(chatSession.value.assigned_agent.user_id ?? "") === String(layout?.user?.id ?? "")
})

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
            title: trans("Error"),
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
        trans("Failed to assign chat")
    )

const takeoverChat = () =>
    claimChat(
        "grp.org.chat.agents.whatsapp.takeover",
        "patch",
        isTakingOver,
        trans("Failed to assign chat")
    )

const reopenChat = () =>
    claimChat(
        "grp.org.chat.agents.whatsapp.sessions.reopen",
        "patch",
        isReopening,
        trans("Failed to reopen chat")
    )

const canSendNonTemplate = ref<boolean | undefined>(undefined)
const templateOnly = computed(() => canSendNonTemplate.value === false)

const messagesLocal = ref<LocalChatMessage[]>([])
const eventsLocal = ref<any[]>([])
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
            title: trans("Error"),
            text: e?.response?.data?.message ?? trans("Failed to send WhatsApp message"),
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
            title: trans("Error"),
            text: trans("The customer has not messaged in the last 24 hours. Only template messages can be sent."),
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
const hoveredTemplate = ref<WhatsappTemplate | null>(null)
const templateParameters = ref<string[]>([])

const openTemplateDialog = async () => {
    isTemplateDialogOpen.value = true

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

const POPUP_WIDTH = 288

const popupStyle = ref<Record<string, string>>({})

const showTemplatePopup = (template: WhatsappTemplate, event: MouseEvent) => {
    const row = (event.currentTarget as HTMLElement).getBoundingClientRect()
    const fitsRight = row.right + 12 + POPUP_WIDTH <= window.innerWidth

    popupStyle.value = {
        top: `${Math.min(row.top, window.innerHeight - 240)}px`,
        left: fitsRight ? `${row.right + 12}px` : `${Math.max(8, row.left - 12 - POPUP_WIDTH)}px`,
    }

    hoveredTemplate.value = template
}

const hasTemplate = computed(() => !!selectedTemplate.value)

const selectTemplate = (template: WhatsappTemplate) => {
    selectedTemplate.value = template
    templateParameters.value = Array(template.parameter_count).fill("")
    hoveredTemplate.value = null
    isTemplateDialogOpen.value = false
    newMessage.value = ""
    removeFile()
}

const clearTemplate = () => {
    selectedTemplate.value = null
    templateParameters.value = []
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

const stopSocket = () => {
    chatChannel?.stopListening(".message")
    chatChannel?.stopListening(".reaction")
    chatChannel?.stopListening(".status")
    chatChannel = null
}

// Marks the thread read on our side and sends the receipt that lights up the
// customer's blue ticks; opening the thread does the same through the messages endpoint.
const markIncomingAsRead = async () => {
    if (!chatSession.value?.ulid) return

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

    chatChannel.listen(".message", ({ message, can_send_non_template_message }: any) => {
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

        if (message.sender_type !== "agent" && message.sender_type !== "system") {
            markIncomingAsRead()
        }

        scrollBottom()
    })

    chatChannel.listen(".reaction", ({ message }: any) => {
        if (!message?.id) return

        const index = messagesLocal.value.findIndex((m) => m.id === message.id)

        if (index !== -1) {
            messagesLocal.value[index] = {
                ...messagesLocal.value[index],
                reactions: message.reactions ?? [],
            }
        }
    })

    chatChannel.listen(".status", (payload: any) => {
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
    })
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
})

onUnmounted(() => {
    stopSocket()
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

            <ModalConfirmationDelete v-if="!isClosed" :routeDelete="{
                name: 'grp.org.chat.agents.whatsapp.sessions.close',
                parameters: [organisationSlug, session?.ulid],
                method: 'patch',
            }" :title="trans('Are you sure you want to end this chat?')"
                :noLabel="trans('End chat')"
                :noIcon="faTimesCircle"
                :description="trans('This will close the chat session. The conversation history will be preserved.')"
                @success="$emit('close-session')">
                <template #default="{ changeModel }">
                    <button @click="changeModel"
                        class="inline-flex items-center justify-center gap-1.5 shrink-0 h-7 px-2.5 text-[11px] font-medium rounded-md transition hover:opacity-90"
                        :style="{ backgroundColor: 'var(--theme-color-4)', color: 'var(--theme-color-5)' }">
                        <FontAwesomeIcon :icon="faTimesCircle" class="text-[11px]" />
                        {{ trans("End chat") }}
                    </button>
                </template>
            </ModalConfirmationDelete>
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

            <template v-for="(entries, date) in groupedTimeline" :key="date">
                <div class="text-center text-xs text-gray-400">{{ date }}</div>
                <template v-for="entry in entries" :key="entry.key">
                    <ChatTimelineEvent v-if="entry.kind === 'event'" :event="entry.event" />
                    <div v-else class="flex"
                        :class="entry.message.sender_type === 'agent' ? 'justify-end' : 'justify-start'">
                        <BubbleChat :message="entry.message" viewerType="agent"
                            :contactName="session?.contact_name || session?.guest_identifier"
                            :canEdit="false"
                            reactionUrlBase="/app/api/chats/meta/messages"
                            :viewerReactorId="layout?.user?.id" />
                    </div>
                </template>
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
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200">
                <div class="text-xs text-gray-600">
                    {{ trans('This chat has been closed') }}
                </div>
                <Button
                    @click="reopenChat"
                    :loading="isReopening"
                    style="primary"
                    size="xs"
                    :label="trans('Reopen')"
                    :icon="faRotateRight"
                />
            </div>
        </footer>

        <!-- Footer: Assign-to-me banner for waiting (unassigned) chats -->
        <footer v-else-if="isWaiting" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-gray-50 border border-gray-200">
                <div class="text-xs text-gray-600">
                    {{ trans('Assign this chat to yourself to start the conversation') }}
                </div>
                <Button
                    @click="assignSelf"
                    :loading="isAssigningSelf"
                    style="primary"
                    size="xs"
                    :label="trans('Assign to me')"
                    :icon="faUser"
                />
            </div>
        </footer>

        <!-- Footer: Takeover banner for team chats -->
        <footer v-else-if="!isMyChat" class="px-3 py-3 bg-white border-t">
            <div class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg bg-indigo-50 border border-indigo-200">
                <div class="text-xs text-indigo-600">
                    <span class="font-semibold">{{ session?.assigned_agent?.name }}</span>
                    {{ trans(' is handling this chat') }}
                </div>
                <Button
                    @click="takeoverChat"
                    :loading="isTakingOver"
                    style="primary"
                    size="xs"
                    :label="trans('Take Over')"
                    :icon="faUser"
                />
            </div>
        </footer>

        <!-- Footer: composer -->
        <footer v-else class="px-3 py-2 bg-white">
            <input ref="imageInput" type="file" accept=".webp,.jpg,.jpeg,.png,.avif" class="hidden"
                @change="handleImageSelect" />
            <input ref="fileInput" type="file" accept=".pdf,.xls,.xlsx" class="hidden" @change="handleDocSelect" />

            <div v-if="templateOnly && !hasTemplate"
                class="flex items-center gap-2 mb-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-[11px]">
                <FontAwesomeIcon :icon="faFileLines" class="text-[10px]" />
                <span>{{ trans('The customer has not messaged in the last 24 hours. Only template messages can be sent.') }}</span>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white shadow-sm focus-within:border-gray-400 focus-within:shadow-md transition-shadow">
                <div v-if="hasTemplate"
                    class="flex items-center gap-2 mx-3 mt-2 px-2 py-1 rounded-lg bg-green-50 text-green-700 text-[11px]">
                    <FontAwesomeIcon :icon="faFileLines" class="text-[10px]" />
                    <span class="font-medium truncate">{{ selectedTemplate?.name }}</span>
                    <span class="text-green-600/70">{{ selectedTemplate?.language }}</span>
                    <button @click="clearTemplate" class="ml-auto text-green-600 hover:text-red-500"
                        :title="trans('Remove template')">
                        <FontAwesomeIcon :icon="faXmark" class="text-[10px]" />
                    </button>
                </div>

                <div v-if="hasTemplate"
                    class="px-4 pt-2 pb-1 text-sm leading-5 text-gray-500 whitespace-pre-line max-h-[120px] overflow-y-auto">
                    {{ templatePreview }}
                </div>

                <div v-if="hasTemplate && templateParameters.length" class="px-3 pb-1 space-y-1.5">
                    <input v-for="(parameter, index) in templateParameters" :key="index"
                        v-model="templateParameters[index]" type="text"
                        :placeholder="trans('Value for :placeholder', { placeholder: `{{${index + 1}}}` })"
                        class="w-full text-xs border rounded-lg px-2.5 py-1.5 focus:outline-none focus:border-green-500" />
                </div>

                <textarea v-if="!hasTemplate" ref="messageInput" v-model="newMessage" @input="autoResize"
                    @keydown.enter.exact.prevent="sendMessage" rows="1" :disabled="templateOnly"
                    :placeholder="templateOnly ? trans('24h window closed, send a template message') : trans('Type message...')"
                    class="w-full resize-none px-4 pt-3 pb-1 text-sm leading-5 outline-none border-none ring-0 focus:outline-none focus:ring-0 rounded-t-xl bg-transparent disabled:bg-gray-50 disabled:text-gray-400 disabled:cursor-not-allowed" />

                <div class="flex items-center justify-between px-2 pb-2 pt-1">
                    <div class="flex items-center gap-1">
                        <button @click="imageInput?.click()" :disabled="hasTemplate || templateOnly"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors disabled:opacity-40 disabled:hover:bg-transparent" :title="trans('Upload image')">
                            <FontAwesomeIcon :icon="faImage" class="text-sm" />
                        </button>
                        <button @click="fileInput?.click()" :disabled="hasTemplate || templateOnly"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors disabled:opacity-40 disabled:hover:bg-transparent" :title="trans('Upload file')">
                            <FontAwesomeIcon :icon="faPaperclip" class="text-sm" />
                        </button>
                        <button @click="openTemplateDialog"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-green-50 text-gray-500 hover:text-green-600 transition-colors" :title="trans('Send template message')">
                            <FontAwesomeIcon :icon="faFileLines" class="text-sm" />
                        </button>
                    </div>
                    <Button @click="sendMessage" :loading="isSending"
                        :disabled="hasTemplate ? !canSendTemplate : templateOnly"
                        :icon="faPaperPlane"></Button>
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
                <div class="max-h-48 overflow-y-auto border rounded-lg divide-y" @scroll="hoveredTemplate = null">
                    <button v-for="template in templates" :key="template.id"
                        class="w-full text-left px-3 py-2 hover:bg-gray-50 transition-colors"
                        :class="{ 'bg-green-50': selectedTemplate?.id === template.id }"
                        @mouseenter="showTemplatePopup(template, $event)" @mouseleave="hoveredTemplate = null"
                        @click="selectTemplate(template)">
                        <div class="text-sm font-medium">{{ template.name }}</div>
                        <div class="text-[11px] text-gray-400">
                            {{ template.language }}<span v-if="template.category"> · {{ template.category }}</span>
                        </div>
                    </button>
                </div>

            </div>

            <Teleport to="body">
                <div v-if="hoveredTemplate" :style="popupStyle"
                    class="fixed z-[2000] w-72 rounded-lg border bg-white p-3 shadow-xl pointer-events-none">
                    <div class="text-sm font-medium mb-1">{{ hoveredTemplate.name }}</div>
                    <div class="text-xs text-gray-600 whitespace-pre-line max-h-60 overflow-y-auto">
                        {{ hoveredTemplate.body }}
                    </div>
                    <div class="mt-2 pt-2 border-t text-[10px] text-gray-400">
                        {{ hoveredTemplate.language }}<span v-if="hoveredTemplate.category"> · {{ hoveredTemplate.category }}</span>
                        · {{ trans(':count parameters', { count: hoveredTemplate.parameter_count }) }}
                    </div>
                </div>
            </Teleport>
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
