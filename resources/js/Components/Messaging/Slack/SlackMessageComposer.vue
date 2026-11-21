<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { nextTick, ref, watch } from "vue"
import axios from "axios"
import Textarea from "primevue/textarea"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperPlane } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import type { SlackStreamMessage } from "@/Components/Messaging/Slack/SlackMessageRow.vue"

library.add(faPaperPlane)

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
    placeholder?: string
}>()

const emit = defineEmits<{ (e: "sent", sent: SlackSentMessage): void }>()

const text = ref("")
const boxEl = ref<HTMLElement | null>(null)
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
            idempotency_key: idempotencyKey,
        })
        idempotencyKey = null
        text.value = ""
        emit("sent", data)
        nextTick(() => boxEl.value?.querySelector("textarea")?.focus())
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

const onKeydown = (event: KeyboardEvent) => {
    if (event.key !== "Enter" || event.shiftKey || event.isComposing) return
    event.preventDefault()
    send()
}
</script>

<template>
    <div v-if="write.allowed" class="shrink-0 border-t border-gray-200 bg-white px-3 py-2">
        <p v-if="write.reason" class="text-xs text-gray-500">
            {{ write.reason }}
            <a v-if="write.connect_url" :href="write.connect_url" class="font-medium text-gray-700 underline hover:text-gray-900">{{ ctrans("Connect Slack") }}</a>
        </p>
        <template v-else>
            <div ref="boxEl" class="flex items-end gap-x-2">
                <Textarea
                    v-model="text"
                    auto-resize
                    rows="1"
                    :disabled="sending"
                    :placeholder="placeholder ?? ctrans('Message')"
                    :aria-label="placeholder ?? ctrans('Message')"
                    class="max-h-40 min-w-0 flex-1 !overflow-y-auto text-sm"
                    @keydown="onKeydown" />
                <Button type="tertiary" icon="fal fa-paper-plane" :label="ctrans('Send')" :loading="sending" :disabled="sending || !text.trim()" @click="send" />
            </div>
            <p v-if="error" class="pt-1 text-xs text-red-600" role="alert">{{ error }}</p>
            <p v-else class="hidden pt-1 text-xxs text-gray-400 sm:block">{{ ctrans("Enter to send, Shift+Enter for a new line. Posted in Slack as you.") }}</p>
        </template>
    </div>
</template>
