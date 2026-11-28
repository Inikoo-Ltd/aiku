<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import Checkbox from "primevue/checkbox"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faRedo, faTimes } from "@fal"
import DeskMessageEditor, { type DeskPerson } from "@/Components/Messaging/DeskMessageEditor.vue"
import { mentionsEveryone } from "@/Components/Messaging/deskMarkup"
import { formatBytes } from "@/Composables/useUploadLimits"
import { useSlackLive, type SlackLiveEvent } from "@/Components/Messaging/Slack/slackLive"
import { loadDraft, saveDraft } from "@/Components/Messaging/deskDrafts"
import type { SlackStreamMessage } from "@/Components/Messaging/Slack/SlackMessageRow.vue"

export interface SlackWriteState {
    allowed: boolean
    reason: string | null
    stand_in?: boolean
    direct?: boolean
    connect_url: string | null
    people?: DeskPerson[]
    channels?: DeskPerson[]
    member_count?: number
    max_file_bytes?: number
}

export interface SlackSentMessage {
    ts: string
    at: number
    message: SlackStreamMessage | null
    parent: SlackStreamMessage | null
    stand_in?: boolean
}

const props = defineProps<{
    channelId: number
    write: SlackWriteState
    threadTs?: string | null
    channelName?: string | null
    placeholder?: string
    liveChannel?: string | null
}>()

const emit = defineEmits<{ (e: "sent", sent: SlackSentMessage): void }>()

const text = ref(loadDraft(props.channelId, props.threadTs))
const rootEl = ref<HTMLElement | null>(null)
const editorRef = ref<InstanceType<typeof DeskMessageEditor> | null>(null)
const alsoToChannel = ref(false)
const sending = ref(false)
const error = ref<string | null>(null)
const confirmingEveryone = ref(false)
const offerConnect = ref(false)
const EVERYONE_CONFIRM_ABOVE = 6
let idempotencyKey: string | null = null

library.add(faCheck, faRedo, faTimes)

interface AttachedFile {
    key: string
    file: File
    progress: number
    status: "uploading" | "done" | "failed"
    error: string | null
    fileId: string | null
}

const PENDING_MESSAGE_SECONDS = 60
const fileInput = ref<HTMLInputElement | null>(null)
const attached = ref<AttachedFile[]>([])
const pendingFiles = ref<{ ids: string[]; names: string[] } | null>(null)
let pendingTimer: ReturnType<typeof setTimeout> | null = null

const canAttach = computed(() => props.write.allowed && !props.write.reason && !sending.value)
const attachLabel = computed(() => props.write.stand_in ? ctrans("Connect your Slack to send files") : ctrans("Attach files"))
const isUploading = computed(() => attached.value.some((item) => item.status === "uploading"))
const hasFailed = computed(() => attached.value.some((item) => item.status === "failed"))
const readyFileIds = computed(() => attached.value.filter((item) => item.status === "done").map((item) => item.fileId as string))
const maxBytes = computed(() => props.write.max_file_bytes ?? 95 * 1024 * 1024)

const uploadErrorOf = (e: any): string =>
    e?.response?.status === 413
        ? ctrans("This file is larger than the server accepts.")
        : e?.response?.status === 429
            ? ctrans("You are sending too fast. Try again in a moment.")
            : e?.response?.data?.errors?.slack?.[0] ?? e?.response?.data?.errors?.file?.[0] ?? e?.response?.data?.message ?? ctrans("The file was not uploaded. Try again.")

const upload = async (item: AttachedFile) => {
    item.status = "uploading"
    item.progress = 0
    item.error = null
    const form = new FormData()
    form.append("file", item.file)
    try {
        const { data } = await axios.post(route("grp.chat.staff.slack.files.upload", { slackChannel: props.channelId }), form, {
            onUploadProgress: (event) => {
                item.progress = event.total ? event.loaded / event.total : 0
            },
        })
        item.fileId = data.file_id
        item.status = "done"
    } catch (e: any) {
        item.status = "failed"
        item.error = uploadErrorOf(e)
    }
}

const pad = (value: number) => String(value).padStart(2, "0")

const addFiles = (files: File[]) => {
    if (!canAttach.value) return
    for (const original of files) {
        const now = new Date()
        const isPastedImage = original.type.startsWith("image/") && /^image\.\w+$/i.test(original.name)
        const file = isPastedImage
            ? new File([original], `image-${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}.${original.type.split("/")[1] === "jpeg" ? "jpg" : original.type.split("/")[1]}`, { type: original.type })
            : original
        const item: AttachedFile = { key: crypto.randomUUID(), file, progress: 0, status: "uploading", error: null, fileId: null }
        attached.value.push(item)
        const reactive = attached.value[attached.value.length - 1]
        if (file.size > maxBytes.value) {
            reactive.status = "failed"
            reactive.error = ctrans("Larger than the :size aiku can send to Slack.", { size: formatBytes(maxBytes.value) })
        } else {
            upload(reactive)
        }
    }
}

const onPick = (event: Event) => {
    const input = event.target as HTMLInputElement
    addFiles(Array.from(input.files ?? []))
    input.value = ""
}

const onPaste = (event: ClipboardEvent) => {
    const files = Array.from(event.clipboardData?.files ?? [])
    if (!files.length || !canAttach.value) return
    event.preventDefault()
    addFiles(files)
}

const onDrop = (event: DragEvent) => {
    if (!event.dataTransfer?.types.includes("Files")) return
    event.preventDefault()
    addFiles(Array.from(event.dataTransfer.files))
}

const onDragOver = (event: DragEvent) => {
    if (event.dataTransfer?.types.includes("Files")) event.preventDefault()
}

const removeFile = (item: AttachedFile) => {
    attached.value = attached.value.filter((candidate) => candidate.key !== item.key)
}

const clearPending = () => {
    pendingFiles.value = null
    if (pendingTimer) clearTimeout(pendingTimer)
}

useSlackLive(() => props.liveChannel, (event: SlackLiveEvent) => {
    const arrived = (event.message?.files ?? []).map((file) => file.id)
    if (pendingFiles.value && pendingFiles.value.ids.some((id) => arrived.includes(id))) clearPending()
})

let dropTarget: HTMLElement | null = null
onMounted(() => {
    dropTarget = rootEl.value?.parentElement ?? null
    dropTarget?.addEventListener("dragover", onDragOver)
    dropTarget?.addEventListener("drop", onDrop)
})
onBeforeUnmount(() => {
    dropTarget?.removeEventListener("dragover", onDragOver)
    dropTarget?.removeEventListener("drop", onDrop)
    if (pendingTimer) clearTimeout(pendingTimer)
})

const sendFiles = async (notifyEveryone: boolean) => {
    idempotencyKey ??= crypto.randomUUID()
    const names = attached.value.filter((item) => item.status === "done").map((item) => item.file.name)
    const { data } = await axios.post(route("grp.chat.staff.slack.files.send", { slackChannel: props.channelId }), {
        file_ids: readyFileIds.value,
        text: text.value,
        thread_ts: props.threadTs ?? null,
        idempotency_key: idempotencyKey,
        notify_everyone: notifyEveryone,
    })
    pendingFiles.value = { ids: data.file_ids, names }
    pendingTimer = setTimeout(() => {
        clearPending()
        error.value = ctrans("Slack has not shown the files yet. Check the channel before sending again.")
    }, PENDING_MESSAGE_SECONDS * 1000)
    attached.value = []
    return data
}

watch([() => props.channelId, () => props.threadTs], () => {
    attached.value = []
    text.value = loadDraft(props.channelId, props.threadTs)
}, { flush: "sync" })

watch(text, (value) => {
    error.value = null
    saveDraft(props.channelId, props.threadTs, value)
})

const send = async (notifyEveryone = false) => {
    if (sending.value || isUploading.value || hasFailed.value || (!text.value.trim() && !readyFileIds.value.length)) return
    const members = props.write.member_count ?? 0
    if (!notifyEveryone && mentionsEveryone(text.value) && (members === 0 || members > EVERYONE_CONFIRM_ABOVE)) {
        confirmingEveryone.value = true
        return
    }
    confirmingEveryone.value = false
    sending.value = true
    error.value = null
    idempotencyKey ??= crypto.randomUUID()
    const sentFrom = { channelId: props.channelId, threadTs: props.threadTs ?? null }
    try {
        const hasFiles = readyFileIds.value.length > 0
        const data = hasFiles
            ? await sendFiles(notifyEveryone)
            : (await axios.post(route("grp.chat.staff.slack.messages.store", { slackChannel: props.channelId }), {
                text: text.value,
                thread_ts: props.threadTs ?? null,
                also_to_channel: Boolean(props.threadTs) && alsoToChannel.value,
                idempotency_key: idempotencyKey,
                notify_everyone: notifyEveryone,
            })).data
        idempotencyKey = null
        saveDraft(sentFrom.channelId, sentFrom.threadTs, "")
        if (sentFrom.channelId === props.channelId && sentFrom.threadTs === (props.threadTs ?? null)) text.value = ""
        alsoToChannel.value = false
        if (!hasFiles) emit("sent", data)
        offerConnect.value = Boolean(data.stand_in && props.write.connect_url)
        editorRef.value?.focus()
    } catch (e: any) {
        if (e?.response?.status === 422 && e?.response?.data?.errors?.notify_everyone) {
            confirmingEveryone.value = true
            return
        }
        error.value = e?.response?.status === 429
            ? ctrans("You are sending too fast. Try again in a moment.")
            : e?.response?.status === 403
                ? ctrans("You cannot send messages to this channel.")
                : e?.response?.data?.message ?? ctrans("The message was not sent. Try again.")
    } finally {
        sending.value = false
    }
}

const editLast = () => {
    const pencils = rootEl.value?.parentElement?.querySelectorAll<HTMLElement>("[data-desk-own-edit]")
    pencils?.[pencils.length - 1]?.click()
}
</script>

<template>
    <div v-if="write.allowed" ref="rootEl" class="shrink-0 bg-white px-4 pb-4 pt-1">
        <p v-if="write.reason && !write.stand_in" class="text-xs text-gray-500">
            {{ write.reason }}
            <a v-if="write.connect_url" :href="write.connect_url" class="font-medium text-gray-700 underline hover:text-gray-900">{{ write.direct ? ctrans("Open Slack settings") : ctrans("Connect Slack") }}</a>
        </p>
        <template v-else>
            <div v-if="offerConnect" class="mb-1.5 flex items-start gap-x-2 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                <p class="flex-1">
                    {{ ctrans("Sent under your name through the Desk app, so Slack shows it with an APP label. Want it to show fully as you?") }}
                    <a :href="write.connect_url ?? undefined" class="ml-1 font-medium text-gray-700 underline hover:text-gray-900">{{ ctrans("Connect your Slack") }}</a>
                </p>
                <button type="button" class="text-gray-400 hover:text-gray-600" :aria-label="ctrans('Close')" @click="offerConnect = false">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>
            <DeskMessageEditor
                ref="editorRef"
                v-model="text"
                :placeholder="placeholder ?? ctrans('Message')"
                :sending="sending"
                :disabled="sending"
                :people="write.people ?? []"
                :channels="write.channels ?? []"
                mention-tokens
                styled-content
                can-edit-last
                :can-send="readyFileIds.length > 0 && !isUploading && !hasFailed"
                :attach-disabled="!canAttach || Boolean(write.stand_in)"
                :attach-label="attachLabel"
                @attach="fileInput?.click()"
                @paste="onPaste"
                @submit="send()"
                @edit-last="editLast" />
            <input ref="fileInput" type="file" multiple class="hidden" @change="onPick">
            <ul v-if="attached.length" class="mt-1.5 space-y-1">
                <li v-for="item in attached" :key="item.key" class="rounded border border-gray-200 px-2.5 py-1.5 text-xs">
                    <div class="flex items-center gap-x-2">
                        <span class="min-w-0 flex-1 truncate font-medium text-gray-800">{{ item.file.name }}</span>
                        <span class="shrink-0 whitespace-nowrap text-gray-400">{{ formatBytes(item.file.size) }}</span>
                        <Button v-if="item.status === 'failed' && item.file.size <= maxBytes" type="tertiary" :label="ctrans('Retry')" @click="upload(item)">
                            <template #icon><FontAwesomeIcon icon="fal fa-redo" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                        </Button>
                        <Button type="tertiary" :label="ctrans('Remove')" :disabled="sending" @click="removeFile(item)">
                            <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                    <div v-if="item.status === 'uploading'" class="mt-1">
                        <div class="h-1 overflow-hidden rounded bg-gray-100" role="progressbar" :aria-valuenow="Math.round(item.progress * 100)" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full bg-[var(--app-accent)] transition-[width]" :style="{ width: `${Math.round(item.progress * 100)}%` }" />
                        </div>
                        <p v-if="item.progress >= 1" class="mt-0.5 text-gray-500">{{ ctrans("Sending to Slack…") }}</p>
                    </div>
                    <p v-else-if="item.status === 'failed'" class="mt-0.5 text-red-600" role="alert">{{ item.error }}</p>
                </li>
            </ul>
            <p v-if="hasFailed" class="mt-1 text-xs text-gray-500">{{ ctrans("Retry or remove the files that failed, then send.") }}</p>
            <p v-if="pendingFiles" class="mt-1.5 text-xs text-gray-500">{{ ctrans("Sending :files to Slack…", { files: pendingFiles.names.join(", ") }) }}</p>
            <div class="flex items-center gap-x-3 pt-1.5">
                <label v-if="threadTs && !attached.length" class="inline-flex cursor-pointer items-center gap-x-2 text-[13px] text-[#1d1c1d]">
                    <Checkbox v-model="alsoToChannel" binary size="small" :disabled="sending" input-id="desk-also-to-channel" />
                    {{ write.direct ? ctrans("Also send to the conversation") : channelName ? ctrans("Also send to #:channel", { channel: channelName }) : ctrans("Also send to channel") }}
                </label>
                <p v-if="error" class="ml-auto text-xs text-red-600" role="alert">{{ error }}</p>
                <p v-else class="ml-auto hidden text-xxs text-gray-400 sm:block">{{ ctrans("Shift + Enter to add a new line") }}</p>
            </div>
        </template>
        <Dialog v-model:visible="confirmingEveryone" modal :header="ctrans('Notify everyone?')" :style="{ width: '28rem' }">
            <p class="text-sm text-gray-700">{{ ctrans("This will notify everyone in this channel.") }}</p>
            <template #footer>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="confirmingEveryone = false">
                        <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="primary" full :label="ctrans('Notify everyone')" @click="send(true)">
                        <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
                    </Button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
