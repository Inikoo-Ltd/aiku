<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faRobot } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    sessionUlid?: string | null
    whatsapp?: boolean
    readOnly?: boolean
}>()

const emit = defineEmits<{ (e: "use", text: string): void }>()

interface Draft {
    id: number
    text: string
    topic_label: string
}

const draft = ref<Draft | null>(null)
const busy = ref(false)
let channel: any = null
let channelName: string | null = null

const load = async () => {
    draft.value = null
    if (!props.sessionUlid || props.readOnly) return

    try {
        const url = props.whatsapp
            ? route("grp.api.chats.meta.sessions.ai_draft.show", [props.sessionUlid])
            : route("grp.api.chats.sessions.ai_draft.show", [props.sessionUlid])
        const { data } = await axios.get(url)
        draft.value = data?.data ?? null
    } catch {
        draft.value = null
    }
}

const decide = async (action: "take" | "discard") => {
    if (!draft.value || busy.value) return
    busy.value = true

    try {
        const { data } = await axios.post(route(`grp.api.chats.ai_drafts.${action}`, [draft.value.id]))
        if (action === "take" && data?.data?.text) {
            emit("use", data.data.text)
        }
        draft.value = null
    } catch {
        await load()
    } finally {
        busy.value = false
    }
}

const listen = () => {
    if (!props.sessionUlid || !(window as any).Echo) return

    channelName = (props.whatsapp ? "meta-chat-session." : "chat-session.") + props.sessionUlid
    channel = props.whatsapp ? (window as any).Echo.private(channelName) : (window as any).Echo.channel(channelName)
    channel.listen(".ai-draft", load)
    channel.listen(".message", load)
}

const stopListening = () => {
    if (channel) {
        channel.stopListening(".ai-draft", load)
        channel.stopListening(".message", load)
    }
    channel = null
    channelName = null
}

watch(() => props.sessionUlid, () => {
    stopListening()
    load()
    listen()
}, { immediate: true })

onBeforeUnmount(stopListening)
</script>

<template>
    <div v-if="draft" class="mb-1.5 rounded-xl border border-indigo-200 bg-indigo-50/60 px-3 py-2 text-sm">
        <div class="flex items-center gap-1.5 text-xs text-indigo-700">
            <FontAwesomeIcon :icon="faRobot" fixed-width />
            <span>{{ ctrans("Draft written by AI from aiku data") }} · {{ draft.topic_label }}</span>
        </div>
        <p class="mt-1 whitespace-pre-line text-gray-800">{{ draft.text }}</p>
        <div class="mt-2 flex gap-2">
            <button type="button" :disabled="busy" @click="decide('take')"
                class="rounded-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
                {{ ctrans("Use") }}
            </button>
            <button type="button" :disabled="busy" @click="decide('discard')"
                class="rounded-md px-3 py-1 text-xs text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-white disabled:opacity-50">
                {{ ctrans("Discard") }}
            </button>
        </div>
    </div>
</template>
