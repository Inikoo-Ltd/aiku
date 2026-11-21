<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCommentDots, faFile, faReply, faRobot, faThumbtack } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import SlackNodes, { type SlackNode } from "@/Components/Messaging/Slack/SlackNodes.vue"
import SlackMessageVersions from "@/Components/Messaging/Slack/SlackMessageVersions.vue"
import SlackMessageFiles, { type SlackMessageFile } from "@/Components/Messaging/Slack/SlackMessageFiles.vue"

library.add(faCommentDots, faFile, faReply, faRobot, faThumbtack)

export interface SlackStreamMessage {
    id: number
    ts: string
    thread_ts: string | null
    is_broadcast: boolean
    is_deleted: boolean
    is_system: boolean
    author: { key: string | null; user_id: number | null; name: string | null; avatar: Record<string, string> | null; is_bot: boolean }
    sent_at: string
    is_edited: boolean
    versions_url: string | null
    is_pinned: boolean
    nodes: SlackNode[]
    reactions: { name: string; unicode: string | null; url: string | null; count: number; people: string[] }[]
    my_reactions: string[]
    reply_count: number
    latest_reply_at: string | null
    reply_users: { name: string | null; avatar: Record<string, string> | null }[]
    files: SlackMessageFile[]
}

const props = withDefaults(defineProps<{
    message: SlackStreamMessage
    channelLinks: Record<string, number>
    continues?: boolean
    inThread?: boolean
}>(), { continues: false, inThread: false })

const emit = defineEmits<{ (e: "openThread", ts: string): void }>()

const inlineNodes = (nodes: SlackNode[]) => nodes.flatMap((node) => (node.type === "paragraph" ? node.children : [node]))
const authorName = (message: SlackStreamMessage) => message.author.name ?? ctrans("Unknown person")
const time = (iso: string) => useFormatTime(iso, { formatTime: "HH:mm" })
const reactionTooltip = (reaction: SlackStreamMessage["reactions"][number]) =>
    reaction.people.length ? reaction.people.join(", ") : ctrans(":count people", { count: String(reaction.count) })
const isMine = (name: string) => props.message.my_reactions?.includes(name)
</script>

<template>
    <div v-if="message.is_system" class="flex items-baseline gap-x-2 py-0.5 pl-11 text-xs text-gray-500">
        <span class="min-w-0 break-words"><SlackNodes :nodes="inlineNodes(message.nodes)" :channel-links="channelLinks" /></span>
        <span class="shrink-0 text-xxs text-gray-400">{{ time(message.sent_at) }}</span>
    </div>

    <div v-else class="group flex gap-x-2 rounded px-1 hover:bg-gray-50" :class="[continues ? 'py-0.5' : 'mt-2 pt-1 pb-0.5', message.is_pinned ? 'bg-amber-50/60' : '']">
        <div class="w-9 shrink-0">
            <span v-if="message.is_deleted && !continues" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                <FontAwesomeIcon icon="fal fa-comment-dots" fixed-width aria-hidden="true" />
            </span>
            <TicketUserAvatar v-else-if="!continues" :name="message.author.name" :avatar="message.author.avatar" size="lg" />
            <span v-else class="block pt-0.5 text-right text-xxs leading-5 text-gray-400 opacity-0 group-hover:opacity-100">{{ time(message.sent_at) }}</span>
        </div>
        <div class="min-w-0 flex-1 text-sm leading-relaxed">
            <template v-if="message.is_deleted">
                <div class="flex flex-wrap items-baseline gap-x-1.5">
                    <span class="italic text-gray-500">{{ ctrans("This message was deleted.") }}</span>
                    <span class="text-xxs text-gray-400">{{ time(message.sent_at) }}</span>
                </div>
            </template>
            <template v-else>
                <button
                    v-if="message.is_broadcast && message.thread_ts && !inThread"
                    type="button"
                    class="flex items-center gap-1 text-xxs text-gray-500 hover:underline"
                    @click="emit('openThread', message.thread_ts)">
                    <FontAwesomeIcon icon="fal fa-reply" fixed-width aria-hidden="true" />{{ ctrans("Replied to a thread") }}
                </button>
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
                <div v-if="message.is_broadcast && inThread" class="flex items-center gap-1 text-xxs text-gray-500">
                    <FontAwesomeIcon icon="fal fa-reply" fixed-width aria-hidden="true" />{{ ctrans("Also sent to the channel") }}
                </div>

                <div class="text-gray-800" :class="message.is_edited && '[&>p:last-of-type]:inline'">
                    <SlackNodes :nodes="message.nodes" :channel-links="channelLinks" />
                    <SlackMessageVersions v-if="message.is_edited && message.versions_url" :url="message.versions_url" :channel-links="channelLinks" />
                    <span v-else-if="message.is_edited" class="ml-1 text-xxs text-gray-400" v-tooltip="ctrans('Edited in Slack')">({{ ctrans("edited") }})</span>
                </div>

                <SlackMessageFiles v-if="message.files.length" :files="message.files" />

                <div v-if="message.reactions.length" class="mt-1 flex flex-wrap gap-1">
                    <span
                        v-for="reaction in message.reactions"
                        :key="reaction.name"
                        v-tooltip="reactionTooltip(reaction)"
                        class="inline-flex cursor-default items-center gap-x-1 rounded-full border px-1.5 py-0.5 text-xs"
                        :class="isMine(reaction.name) ? 'border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] text-[var(--app-accent-deep)]' : 'border-gray-200 bg-gray-50 text-gray-700'">
                        <span v-if="reaction.unicode">{{ reaction.unicode }}</span>
                        <img v-else-if="reaction.url" :src="reaction.url" :alt="':' + reaction.name + ':'" class="h-4 w-4 object-contain" loading="lazy" />
                        <span v-else class="text-xxs">:{{ reaction.name }}:</span>
                        <span class="tabular-nums">{{ reaction.count }}</span>
                    </span>
                </div>
            </template>

            <button
                v-if="message.reply_count && !inThread"
                type="button"
                class="mt-1 flex max-w-full items-center gap-x-1.5 rounded border border-transparent px-1 py-0.5 -ml-1 text-xs hover:border-gray-200 hover:bg-white"
                @click="emit('openThread', message.ts)">
                <span v-if="message.reply_users.length" class="flex shrink-0 -space-x-1">
                    <TicketUserAvatar v-for="(person, personIndex) in message.reply_users" :key="personIndex" :name="person.name" :avatar="person.avatar" size="xs" class="ring-1 ring-white" />
                </span>
                <span class="whitespace-nowrap font-medium text-gray-700 hover:underline">{{ message.reply_count === 1 ? ctrans("1 reply") : ctrans(":count replies", { count: String(message.reply_count) }) }}</span>
                <span v-if="message.latest_reply_at" class="truncate text-gray-400">{{ ctrans("Last reply :time", { time: useFormatTime(message.latest_reply_at, { formatTime: "short-datetime" }) }) }}</span>
            </button>
        </div>
    </div>
</template>
