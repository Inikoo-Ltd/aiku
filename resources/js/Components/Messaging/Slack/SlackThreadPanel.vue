<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, nextTick, ref, watch } from "vue"
import axios from "axios"
import { usePage } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faChevronDown, faLink, faTimes } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SlackMessageRow, { type SlackStreamMessage } from "@/Components/Messaging/Slack/SlackMessageRow.vue"
import { myReactions, slackReloads, useSlackLive, type SlackLiveEvent } from "@/Components/Messaging/Slack/slackLive"
import SlackMessageComposer, { type SlackSentMessage, type SlackWriteState } from "@/Components/Messaging/Slack/SlackMessageComposer.vue"

library.add(faCheck, faChevronDown, faLink, faTimes)

const props = defineProps<{
    channelId: number
    ts: string
    focusTs?: string | null
    channelLinks: Record<string, number>
    liveChannel?: string | null
    write?: SlackWriteState | null
}>()

const emit = defineEmits<{ (e: "close"): void; (e: "sent", sent: SlackSentMessage): void }>()

const parent = ref<SlackStreamMessage | null>(null)
const replies = ref<SlackStreamMessage[]>([])
const nextCursor = ref<string | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const copied = ref(false)
const bodyEl = ref<HTMLElement | null>(null)
const highlightedTs = ref<string | null>(null)
const MAX_PAGES_TO_FOCUS = 40

const focusReply = async () => {
    const target = props.focusTs
    if (!target || target === props.ts) return
    for (let page = 0; page < MAX_PAGES_TO_FOCUS && nextCursor.value && !replies.value.some((reply) => reply.ts === target); page++) {
        await load(nextCursor.value)
        if (error.value) return
    }
    await nextTick()
    const element = bodyEl.value?.querySelector<HTMLElement>(`[data-thread-ts="${CSS.escape(target)}"]`)
    const container = bodyEl.value
    if (!element || !container) return
    container.scrollTop += element.getBoundingClientRect().top - container.getBoundingClientRect().top - (container.clientHeight - element.offsetHeight) / 2
    highlightedTs.value = target
    setTimeout(() => (highlightedTs.value === target ? (highlightedTs.value = null) : null), 2500)
}

const load = async (cursor: string | null = null) => {
    loading.value = true
    error.value = null
    try {
        const { data } = await axios.get(route("grp.chat.staff.slack.thread", { slackChannel: props.channelId }), { params: { ts: props.ts, ...(cursor ? { cursor } : {}) } })
        if (!cursor) {
            parent.value = data.parent
            replies.value = data.replies
        } else {
            replies.value = [...replies.value, ...data.replies]
        }
        nextCursor.value = data.next_cursor
    } catch (e: any) {
        error.value = e?.response?.status === 403 ? ctrans("You can no longer see this channel") : e?.response?.status === 404 ? ctrans("This thread is not in aiku") : ctrans("The thread could not be loaded, try again")
    } finally {
        loading.value = false
    }
}

watch(() => props.ts, async () => {
    parent.value = null
    replies.value = []
    nextCursor.value = null
    await load()
    await focusReply()
}, { immediate: true })
watch(() => props.focusTs, (ts, previous) => {
    if (ts && ts !== previous && parent.value) focusReply()
})

const myId = computed<number | null>(() => (usePage().props as any)?.auth?.user?.id ?? null)
let liveAt: Record<string, number> = {}
watch(slackReloads, async () => {
    liveAt = {}
    await load()
})

const applyLive = async (event: SlackLiveEvent, scroll = false) => {
    const message = event.message
    if (event.channel_id !== props.channelId || !message || !parent.value || (liveAt[message.ts] ?? 0) > event.at) return
    liveAt[message.ts] = event.at
    const row = { ...message, my_reactions: myReactions(event.reactors, myId.value) }
    if (message.ts === props.ts) {
        parent.value = row
        return
    }
    if (message.thread_ts !== props.ts) return
    if (event.parent) parent.value = { ...event.parent, my_reactions: parent.value.my_reactions }
    const index = replies.value.findIndex((reply) => reply.ts === message.ts)
    if (message.is_deleted) {
        if (index >= 0) replies.value.splice(index, 1)
        return
    }
    if (index >= 0) {
        replies.value[index] = row
        return
    }
    if (nextCursor.value || loading.value) return
    const container = bodyEl.value
    const atBottom = scroll || !container || container.scrollHeight - container.scrollTop - container.clientHeight < 120
    replies.value = [...replies.value, row].sort((a, b) => (a.ts < b.ts ? -1 : 1))
    if (atBottom) {
        await nextTick()
        if (bodyEl.value) bodyEl.value.scrollTop = bodyEl.value.scrollHeight
    }
}

useSlackLive(() => props.liveChannel, (event: SlackLiveEvent) => applyLive(event))

const onSent = async (sent: SlackSentMessage) => {
    for (let page = 0; page < MAX_PAGES_TO_FOCUS && nextCursor.value && !error.value; page++) {
        await load(nextCursor.value)
    }
    await applyLive({ channel_id: props.channelId, kind: "new", ts: sent.ts, at: sent.at, message: sent.message, parent: sent.parent, reactors: {} }, true)
    await nextTick()
    if (bodyEl.value) bodyEl.value.scrollTop = bodyEl.value.scrollHeight
    emit("sent", sent)
}

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(route("grp.chat.staff.slack.show", { slackChannel: props.channelId, thread: props.ts }))
        copied.value = true
        setTimeout(() => (copied.value = false), 2000)
    } catch {
        copied.value = false
    }
}
</script>

<template>
    <aside class="absolute inset-0 z-20 flex flex-col bg-white md:static md:z-auto md:w-96 md:shrink-0 md:border-l md:border-gray-200" :aria-label="ctrans('Thread')">
        <div class="flex shrink-0 items-center gap-x-2 border-b border-gray-200 bg-gray-50 px-3 py-2">
            <span class="flex-1 text-sm font-medium text-gray-700">{{ ctrans("Thread") }}</span>
            <Button type="tertiary" size="xs" :icon="copied ? 'fal fa-check' : 'fal fa-link'" :label="copied ? ctrans('Copied') : ctrans('Copy link')" @click="copyLink" />
            <button type="button" :aria-label="ctrans('Close')" class="flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-200/70" @click="emit('close')">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </button>
        </div>

        <div ref="bodyEl" class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
            <div v-if="loading && !parent" class="space-y-4 py-4" aria-busy="true">
                <div v-for="row in 4" :key="'thread-skeleton-' + row" class="flex items-start gap-2">
                    <span class="skeleton h-9 w-9 shrink-0 rounded-full" />
                    <div class="flex-1 space-y-1.5">
                        <span class="skeleton block h-3 w-28 rounded" />
                        <span class="skeleton block h-3 w-2/3 rounded" />
                    </div>
                </div>
            </div>

            <template v-if="parent">
                <SlackMessageRow :message="parent" :channel-links="channelLinks" in-thread />
                <div class="flex items-center gap-x-2 py-2">
                    <span class="whitespace-nowrap text-xxs font-medium text-gray-500">{{ parent.reply_count === 1 ? ctrans("1 reply") : ctrans(":count replies", { count: String(parent.reply_count) }) }}</span>
                    <span class="h-px flex-1 bg-gray-200" />
                </div>
                <div
                    v-for="reply in replies"
                    :key="reply.id"
                    :data-thread-ts="reply.ts"
                    class="rounded transition-colors duration-700"
                    :class="highlightedTs === reply.ts ? 'bg-amber-100/70' : ''">
                    <SlackMessageRow :message="reply" :channel-links="channelLinks" in-thread />
                </div>
                <div v-if="nextCursor" class="flex justify-center pt-3">
                    <LoadingIcon v-if="loading" />
                    <Button v-else type="tertiary" size="xs" icon="fal fa-chevron-down" :label="ctrans('Show more replies')" @click="load(nextCursor)" />
                </div>
            </template>

            <p v-if="error" class="py-3 text-xs text-red-600">{{ error }}</p>
        </div>

        <SlackMessageComposer v-if="write && parent && !parent.is_deleted" :channel-id="channelId" :write="write" :thread-ts="ts" :placeholder="ctrans('Reply…')" @sent="onSent" />
    </aside>
</template>
