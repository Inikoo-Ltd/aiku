<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Deferred, InfiniteScroll } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArchive, faChevronLeft, faCommentDots, faFile, faHashtag, faRobot, faThumbtack } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { isToday, isYesterday } from "date-fns"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SlackNodes, { type SlackNode } from "@/Components/Messaging/Slack/SlackNodes.vue"

library.add(faArchive, faChevronLeft, faCommentDots, faFile, faHashtag, faRobot, faThumbtack)

export interface SlackStreamMessage {
    id: number
    ts: string
    is_system: boolean
    author: { key: string | null; user_id: number | null; name: string | null; avatar: Record<string, string> | null; is_bot: boolean }
    sent_at: string
    is_edited: boolean
    is_pinned: boolean
    nodes: SlackNode[]
    reactions: { name: string; unicode: string | null; url: string | null; count: number; people: string[] }[]
    reply_count: number
    latest_reply_at: string | null
    files: { name: string }[]
}

const props = defineProps<{
    channel: { id: number; name: string | null; topic: string | null; is_archived: boolean }
    messages?: { data: SlackStreamMessage[] }
    channelLinks: Record<string, number>
}>()

const emit = defineEmits<{ (e: "back"): void }>()

const GROUP_WINDOW_MS = 5 * 60 * 1000

const dayKey = (iso: string) => new Date(iso).toDateString()

const dayLabel = (iso: string) => {
    const date = new Date(iso)
    if (isToday(date)) return ctrans("Today")
    if (isYesterday(date)) return ctrans("Yesterday")
    return useFormatTime(iso, { formatTime: "EEEE, d MMMM yyyy" })
}

const rows = computed(() => {
    const ordered = [...(props.messages?.data ?? [])].reverse()
    return ordered.map((message, index) => {
        const previous = ordered[index - 1]
        const startsDay = !previous || dayKey(previous.sent_at) !== dayKey(message.sent_at)
        const continues = !startsDay
            && !message.is_system
            && !previous.is_system
            && !previous.reply_count
            && message.author.key !== null
            && previous.author.key === message.author.key
            && new Date(message.sent_at).getTime() - new Date(previous.sent_at).getTime() < GROUP_WINDOW_MS
        return { message, startsDay, continues }
    })
})

const inlineNodes = (nodes: SlackNode[]) => nodes.flatMap((node) => (node.type === "paragraph" ? node.children : [node]))
const authorName =(message: SlackStreamMessage) => message.author.name ?? ctrans("Unknown person")
const time = (iso: string) => useFormatTime(iso, { formatTime: "HH:mm" })
const reactionTooltip = (reaction: SlackStreamMessage["reactions"][number]) =>
    reaction.people.length ? reaction.people.join(", ") : ctrans(":count people", { count: String(reaction.count) })
</script>

<template>
    <div class="flex h-full w-full min-w-0 flex-col bg-white text-gray-900">
        <div class="flex shrink-0 items-center gap-x-2 border-b border-gray-200 bg-gray-50 px-3 py-2">
            <button type="button" :aria-label="ctrans('Back')" class="-ml-2 p-2 text-gray-600 md:hidden" @click="emit('back')">
                <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
            </button>
            <FontAwesomeIcon icon="fal fa-hashtag" class="text-gray-400" fixed-width aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-x-2">
                    <span class="truncate text-sm font-medium">{{ channel.name }}</span>
                    <span v-if="channel.is_archived" class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded bg-gray-200 px-1.5 py-0.5 text-xxs text-gray-600">
                        <FontAwesomeIcon icon="fal fa-archive" fixed-width aria-hidden="true" />{{ ctrans("Archived") }}
                    </span>
                </div>
                <div v-if="channel.topic" class="truncate text-xs text-gray-500">{{ channel.topic }}</div>
            </div>
            <span class="shrink-0 whitespace-nowrap text-xxs text-gray-400">{{ ctrans("Read only, from Slack") }}</span>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
            <Deferred data="slack_messages">
                <template #fallback>
                    <div class="space-y-4 py-4" :aria-label="ctrans('Loading…')" aria-busy="true">
                        <div v-for="row in 6" :key="'slack-skeleton-' + row" class="flex items-start gap-2">
                            <span class="skeleton h-9 w-9 shrink-0 rounded-full" />
                            <div class="flex-1 space-y-1.5">
                                <span class="skeleton block h-3 w-28 rounded" />
                                <span class="skeleton block h-3 rounded" :class="row % 2 ? 'w-2/3' : 'w-1/2'" />
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="!messages?.data.length" class="flex h-full flex-col items-center justify-center gap-1 py-16 text-center text-sm text-gray-400">
                    <FontAwesomeIcon icon="fal fa-comment-dots" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                    {{ ctrans("No messages in this channel yet") }}
                </div>

                <InfiniteScroll v-else data="slack_messages" reverse preserve-url>
                    <template #next="{ loading, hasMore }">
                        <div v-if="loading" class="flex justify-center py-3"><LoadingIcon /></div>
                        <div v-else-if="!hasMore" class="py-4 text-center text-xs text-gray-400">{{ ctrans("This is the first message of #:channel", { channel: channel.name ?? "" }) }}</div>
                    </template>

                    <div v-for="{ message, startsDay, continues } in rows" :key="message.id">
                        <div v-if="startsDay" class="flex items-center gap-x-2 py-2">
                            <span class="h-px flex-1 bg-gray-200" />
                            <span class="whitespace-nowrap rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-xxs font-medium text-gray-600">{{ dayLabel(message.sent_at) }}</span>
                            <span class="h-px flex-1 bg-gray-200" />
                        </div>

                        <div v-if="message.is_system" class="flex items-baseline gap-x-2 py-0.5 pl-11 text-xs text-gray-500">
                            <span class="min-w-0 break-words"><SlackNodes :nodes="inlineNodes(message.nodes)" :channel-links="channelLinks" /></span>
                            <span class="shrink-0 text-xxs text-gray-400">{{ time(message.sent_at) }}</span>
                        </div>

                        <div v-else class="group flex gap-x-2 rounded px-1 hover:bg-gray-50" :class="[continues ? 'py-0.5' : 'mt-2 pt-1 pb-0.5', message.is_pinned ? 'bg-amber-50/60' : '']">
                            <div class="w-9 shrink-0">
                                <TicketUserAvatar v-if="!continues" :name="message.author.name" :avatar="message.author.avatar" size="lg" />
                                <span v-else class="block pt-0.5 text-right text-xxs leading-5 text-gray-400 opacity-0 group-hover:opacity-100">{{ time(message.sent_at) }}</span>
                            </div>
                            <div class="min-w-0 flex-1 text-sm leading-relaxed">
                                <div v-if="!continues" class="flex flex-wrap items-baseline gap-x-1.5">
                                    <span class="font-semibold" :class="message.author.name ? 'text-gray-900' : 'italic text-gray-500'">{{ authorName(message) }}</span>
                                    <span v-if="message.author.is_bot" class="inline-flex items-center gap-0.5 rounded bg-gray-200 px-1 text-xxs font-medium uppercase text-gray-600">
                                        <FontAwesomeIcon icon="fal fa-robot" fixed-width aria-hidden="true" />{{ ctrans("Bot") }}
                                    </span>
                                    <span class="text-xxs text-gray-400" v-tooltip="useFormatTime(message.sent_at, { formatTime: 'hm' })">{{ time(message.sent_at) }}</span>
                                </div>
                                <div v-if="message.is_pinned" class="flex items-center gap-1 text-xxs font-medium text-amber-700">
                                    <FontAwesomeIcon icon="fal fa-thumbtack" fixed-width aria-hidden="true" />{{ ctrans("Pinned") }}
                                </div>

                                <div class="text-gray-800" :class="message.is_edited && '[&>p:last-of-type]:inline'">
                                    <SlackNodes :nodes="message.nodes" :channel-links="channelLinks" />
                                    <span v-if="message.is_edited" class="ml-1 text-xxs text-gray-400" v-tooltip="ctrans('Edited in Slack')">({{ ctrans("edited") }})</span>
                                </div>

                                <div v-if="message.files.length" class="mt-1 flex flex-wrap gap-1.5">
                                    <span v-for="(file, fileIndex) in message.files" :key="fileIndex" class="inline-flex max-w-full items-center gap-1 rounded border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs text-gray-600">
                                        <FontAwesomeIcon icon="fal fa-file" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                                        <span class="truncate">{{ file.name }}</span>
                                    </span>
                                </div>

                                <div v-if="message.reactions.length" class="mt-1 flex flex-wrap gap-1">
                                    <span
                                        v-for="reaction in message.reactions"
                                        :key="reaction.name"
                                        v-tooltip="reactionTooltip(reaction)"
                                        class="inline-flex cursor-default items-center gap-x-1 rounded-full border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-xs text-gray-700">
                                        <span v-if="reaction.unicode">{{ reaction.unicode }}</span>
                                        <img v-else-if="reaction.url" :src="reaction.url" :alt="':' + reaction.name + ':'" class="h-4 w-4 object-contain" loading="lazy" />
                                        <span v-else class="text-xxs">:{{ reaction.name }}:</span>
                                        <span class="tabular-nums">{{ reaction.count }}</span>
                                    </span>
                                </div>

                                <div v-if="message.reply_count" class="mt-1 flex items-center gap-x-1.5 text-xs">
                                    <span class="font-medium text-gray-700">{{ message.reply_count === 1 ? ctrans("1 reply") : ctrans(":count replies", { count: String(message.reply_count) }) }}</span>
                                    <span v-if="message.latest_reply_at" class="text-gray-400">{{ ctrans("Last reply :time", { time: useFormatTime(message.latest_reply_at, { formatTime: "short-datetime" }) }) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </InfiniteScroll>
            </Deferred>
        </div>
    </div>
</template>
