<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import Checkbox from "primevue/checkbox"
import { ctrans } from "@/Composables/useTrans"
import DeskMessageEditor from "@/Components/Messaging/DeskMessageEditor.vue"
import type { SlackStreamMessage } from "@/Components/Messaging/Slack/SlackMessageRow.vue"

export interface SlackWriteState {
    allowed: boolean
    reason: string | null
    connect_url: string | null
}

export interface SlackSentMessage {
    ts: string
    at: number
    message: SlackStreamMessage | null
    parent: SlackStreamMessage | null
}

const props = defineProps<{
    channelId: number
    write: SlackWriteState
    threadTs?: string | null
    channelName?: string | null
    placeholder?: string
}>()

const emit = defineEmits<{ (e: "sent", sent: SlackSentMessage): void }>()

const text = ref("")
const rootEl = ref<HTMLElement | null>(null)
const editorRef = ref<InstanceType<typeof DeskMessageEditor> | null>(null)
const alsoToChannel = ref(false)
const sending = ref(false)
const error = ref<string | null>(null)
let idempotencyKey: string | null = null

watch(text, () => {
    error.value = null
})

const send = async () => {
    if (sending.value || !text.value.trim()) return
    sending.value = true
    error.value = null
    idempotencyKey ??= crypto.randomUUID()
    try {
        const { data } = await axios.post(route("grp.chat.staff.slack.messages.store", { slackChannel: props.channelId }), {
            text: text.value,
            thread_ts: props.threadTs ?? null,
            also_to_channel: Boolean(props.threadTs) && alsoToChannel.value,
            idempotency_key: idempotencyKey,
        })
        idempotencyKey = null
        text.value = ""
        alsoToChannel.value = false
        emit("sent", data)
        editorRef.value?.focus()
    } catch (e: any) {
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
        <p v-if="write.reason" class="text-xs text-gray-500">
            {{ write.reason }}
            <a v-if="write.connect_url" :href="write.connect_url" class="font-medium text-gray-700 underline hover:text-gray-900">{{ ctrans("Connect Slack") }}</a>
        </p>
        <template v-else>
            <DeskMessageEditor
                ref="editorRef"
                v-model="text"
                :placeholder="placeholder ?? ctrans('Message')"
                :sending="sending"
                :disabled="sending"
                can-edit-last
                attach-disabled
                :attach-label="ctrans('Sending files from Desk is coming soon')"
                @submit="send"
                @edit-last="editLast" />
            <div class="flex items-center gap-x-3 pt-1.5">
                <label v-if="threadTs" class="inline-flex cursor-pointer items-center gap-x-2 text-[13px] text-[#1d1c1d]">
                    <Checkbox v-model="alsoToChannel" binary size="small" :disabled="sending" input-id="desk-also-to-channel" />
                    {{ channelName ? ctrans("Also send to #:channel", { channel: channelName }) : ctrans("Also send to channel") }}
                </label>
                <p v-if="error" class="ml-auto text-xs text-red-600" role="alert">{{ error }}</p>
                <p v-else class="ml-auto hidden text-xxs text-gray-400 sm:block">{{ ctrans("Shift + Enter to add a new line") }}</p>
            </div>
        </template>
    </div>
</template>
