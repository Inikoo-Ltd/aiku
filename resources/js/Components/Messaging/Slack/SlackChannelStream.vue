<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue"
import axios from "axios"
import { Deferred, InfiniteScroll, router, usePage } from "@inertiajs/vue3"
import { Dialog, InputText, Popover } from "primevue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArchive, faArrowDown, faChevronLeft, faCommentDots, faHashtag, faLink, faPencil, faPlus, faThumbtack, faTimes, faTrashAlt, faUsers } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { isToday, isYesterday } from "date-fns"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import SlackNodes from "@/Components/Messaging/Slack/SlackNodes.vue"
import SlackMessageRow, { type SlackStreamMessage } from "@/Components/Messaging/Slack/SlackMessageRow.vue"
import SlackThreadPanel from "@/Components/Messaging/Slack/SlackThreadPanel.vue"
import SlackMessageComposer, { type SlackSentMessage, type SlackWriteState } from "@/Components/Messaging/Slack/SlackMessageComposer.vue"
import { isInSlackStream, myReactions, slackReloads, useSlackLive, type SlackLiveEvent } from "@/Components/Messaging/Slack/slackLive"

export type { SlackStreamMessage }

library.add(faArchive, faArrowDown, faChevronLeft, faCommentDots, faHashtag, faLink, faPencil, faPlus, faThumbtack, faTimes, faTrashAlt, faUsers)

export interface SlackBookmark {
    id: number
    title: string
    url: string | null
    emoji_name: string | null
    emoji: { unicode?: string; url?: string | null } | null
}

export interface SlackChannelHeader {
    id: number
    name: string | null
    topic: string | null
    purpose?: string | null
    is_archived: boolean
    member_count?: number
    pinned_count?: number
    bookmarks?: SlackBookmark[]
    last_read_ts?: string | null
    write?: SlackWriteState | null
}

const props = defineProps<{
    channel: SlackChannelHeader
    messages?: { data: SlackStreamMessage[] }
    channelLinks: Record<string, number>
    threadTs?: string | null
    aroundTs?: string | null
    replyTs?: string | null
    liveChannel?: string | null
}>()

const emit = defineEmits<{ (e: "back"): void; (e: "read", ts: string): void }>()

const myId = computed<number | null>(() => (usePage().props as any)?.auth?.user?.id ?? null)
const live = ref<Record<string, SlackStreamMessage | null>>({})
let liveAt: Record<string, number> = {}
const newBelow = ref(0)
watch(slackReloads, () => {
    live.value = {}
    liveAt = {}
    newBelow.value = 0
})
const unreadFromTs = props.channel.last_read_ts ?? null

const GROUP_WINDOW_MS = 5 * 60 * 1000

const dayKey = (iso: string) => new Date(iso).toDateString()

const dayLabel = (iso: string) => {
    const date = new Date(iso)
    if (isToday(date)) return ctrans("Today")
    if (isYesterday(date)) return ctrans("Yesterday")
    return useFormatTime(iso, { formatTime: "EEEE, d MMMM yyyy" })
}

const rows = computed(() => {
    const base = props.messages?.data ?? []
    const newestTs = base[0]?.ts
    const loaded = new Set(base.map((message) => message.ts))
    const merged = base.map((message) => (message.ts in live.value ? live.value[message.ts] : message)).filter((message): message is SlackStreamMessage => !!message).reverse()
    const arrived = props.aroundTs
        ? []
        : Object.values(live.value)
            .filter((message): message is SlackStreamMessage => !!message && !loaded.has(message.ts) && (!newestTs || message.ts > newestTs))
            .sort((a, b) => (a.ts < b.ts ? -1 : 1))
    const ordered = [...merged, ...arrived]
    const firstUnread = unreadFromTs ? ordered.findIndex((message) => loaded.has(message.ts) && message.ts > unreadFromTs && !message.is_system && message.author.user_id !== myId.value) : -1
    return ordered.map((message, index) => {
        const previous = ordered[index - 1]
        const startsDay = !previous || dayKey(previous.sent_at) !== dayKey(message.sent_at)
        const continues = !startsDay
            && !message.is_system
            && !previous.is_system
            && !previous.reply_count
            && !message.is_deleted
            && !previous.is_deleted
            && !message.is_broadcast
            && message.author.key !== null
            && previous.author.key === message.author.key
            && new Date(message.sent_at).getTime() - new Date(previous.sent_at).getTime() < GROUP_WINDOW_MS
        return { message, startsDay, continues, startsUnread: index === firstUnread }
    })
})

const openThreadTs = ref<string | null>(props.threadTs ?? null)
const focusReplyTs = ref<string | null>(props.threadTs ? props.replyTs ?? null : null)
watch(() => [props.threadTs, props.replyTs], () => {
    if (props.threadTs) {
        openThreadTs.value = props.threadTs
        focusReplyTs.value = props.replyTs ?? null
    }
})
const openThread = (ts: string, replyTs: string | null = null) => {
    openThreadTs.value = ts
    focusReplyTs.value = replyTs
}
const highlightedTs = ref<string | null>(null)
const streamEl = ref<HTMLElement | null>(null)

const showInStream = (ts: string) => {
    const element = streamEl.value?.querySelector<HTMLElement>(`[data-slack-ts="${CSS.escape(ts)}"]`)
    const container = streamEl.value
    if (!element || !container) return false
    container.scrollTop += element.getBoundingClientRect().top - container.getBoundingClientRect().top - (container.clientHeight - element.offsetHeight) / 2
    highlightedTs.value = ts
    setTimeout(() => (highlightedTs.value === ts ? (highlightedTs.value = null) : null), 2500)
    return true
}

const pendingAround = ref<string | null>(props.aroundTs ?? null)
watch(() => props.aroundTs, (ts) => (pendingAround.value = ts ?? null))
watch(() => [props.messages?.data.length, pendingAround.value], async () => {
    if (!pendingAround.value) return
    await nextTick()
    requestAnimationFrame(() => {
        if (pendingAround.value && showInStream(pendingAround.value)) pendingAround.value = null
    })
}, { immediate: true })

const bookmarks = ref<SlackBookmark[]>(props.channel.bookmarks ?? [])
watch(() => props.channel.bookmarks, (next) => (bookmarks.value = next ?? []))

const bookmarkDialog = ref(false)
const bookmarkId = ref<number | null>(null)
const bookmarkForm = ref({ title: "", link: "", emoji: "" })
const bookmarkBusy = ref(false)
const bookmarkError = ref<string | null>(null)
const bookmarkNeedsConnect = ref(false)

const openBookmark = (bookmark: SlackBookmark | null) => {
    bookmarkError.value = null
    bookmarkNeedsConnect.value = !!write.value?.reason
    if (bookmarkNeedsConnect.value) {
        bookmarkDialog.value = true
        return
    }
    bookmarkId.value = bookmark?.id ?? null
    bookmarkForm.value = { title: bookmark && bookmark.title !== bookmark.url ? bookmark.title : "", link: bookmark?.url ?? "", emoji: bookmark?.emoji_name ?? "" }
    bookmarkDialog.value = true
}

const sendBookmark = async (method: "post" | "patch" | "delete") => {
    if (bookmarkBusy.value) return
    bookmarkBusy.value = true
    bookmarkError.value = null
    const url = bookmarkId.value === null
        ? route("grp.chat.staff.slack.bookmarks.store", { slackChannel: props.channel.id })
        : route(method === "delete" ? "grp.chat.staff.slack.bookmarks.delete" : "grp.chat.staff.slack.bookmarks.update", { slackChannel: props.channel.id, bookmark: bookmarkId.value })
    try {
        const { data } = await axios.request({ method, url, data: method === "delete" ? undefined : { ...bookmarkForm.value, emoji: bookmarkForm.value.emoji || null } })
        bookmarks.value = data.bookmarks
        bookmarkDialog.value = false
    } catch (exception: any) {
        const errors = exception?.response?.data?.errors ?? {}
        bookmarkError.value = errors.slack?.[0] ?? errors.link?.[0] ?? errors.title?.[0] ?? errors.emoji?.[0]
            ?? (exception?.response?.status === 429 ? ctrans("Too many changes at once, wait a minute") : ctrans("Slack did not accept this, try again later"))
    } finally {
        bookmarkBusy.value = false
    }
}

watch(() => props.channel.pinned_count, () => (pins.value = null))

const pinsPopover = ref()
const pins = ref<SlackStreamMessage[] | null>(null)
const pinsLoading = ref(false)
const pinsError = ref<string | null>(null)

const togglePins = async (event: Event) => {
    pinsPopover.value?.toggle(event)
    if (pins.value !== null || pinsLoading.value) return
    pinsLoading.value = true
    pinsError.value = null
    try {
        const { data } = await axios.get(route("grp.chat.staff.slack.pins", { slackChannel: props.channel.id }))
        pins.value = data
    } catch {
        pinsError.value = ctrans("The pinned messages could not be loaded, try again")
    } finally {
        pinsLoading.value = false
    }
}

const jumpTo = (pin: SlackStreamMessage) => {
    pinsPopover.value?.hide()
    if (pin.thread_ts && pin.thread_ts !== pin.ts && !pin.is_broadcast) {
        openThread(pin.thread_ts, pin.ts)
        return
    }
    if (!showInStream(pin.ts)) {
        router.visit(route("grp.chat.staff.slack.show", { slackChannel: props.channel.id, around: pin.ts }))
    }
}

const isNearBottom = () => {
    const container = streamEl.value
    return !container || container.scrollHeight - container.scrollTop - container.clientHeight < 120
}

const scrollToBottom = () => {
    const container = streamEl.value
    if (container) container.scrollTop = container.scrollHeight
    newBelow.value = 0
}

let readUpTo = props.channel.last_read_ts ?? ""
let readTimer: ReturnType<typeof setTimeout> | null = null
const markRead = async () => {
    const newest = rows.value[rows.value.length - 1]?.message.ts
    if (!newest || newest <= readUpTo || document.visibilityState !== "visible" || !isNearBottom()) return
    const previous = readUpTo
    readUpTo = newest
    try {
        await axios.post(route("grp.chat.staff.slack.read", { slackChannel: props.channel.id }), { ts: newest })
        emit("read", newest)
    } catch {
        readUpTo = previous
    }
}
const markReadSoon = () => {
    if (readTimer) clearTimeout(readTimer)
    readTimer = setTimeout(markRead, 800)
}

const onStreamScroll = () => {
    if (!isNearBottom()) return
    newBelow.value = 0
    markReadSoon()
}

watch(() => props.messages?.data.length, markReadSoon, { immediate: true })
onMounted(() => document.addEventListener("visibilitychange", markReadSoon))
onUnmounted(() => {
    document.removeEventListener("visibilitychange", markReadSoon)
    if (readTimer) clearTimeout(readTimer)
})

const isHeld = (ts: string) => ts in live.value || !!props.messages?.data.some((message) => message.ts === ts)

const applyLive = (row: SlackStreamMessage, at: number, reactors?: Record<string, number[]>) => {
    if (!isInSlackStream(row) || (liveAt[row.ts] ?? 0) > at) return
    liveAt[row.ts] = at
    const held = live.value[row.ts] ?? props.messages?.data.find((message) => message.ts === row.ts)
    const next = { ...row, my_reactions: reactors ? myReactions(reactors, myId.value) : held?.my_reactions ?? [] }
    live.value = { ...live.value, [row.ts]: row.is_deleted && !row.reply_count ? null : next }
}

useSlackLive(() => props.liveChannel, (event: SlackLiveEvent) => {
    if (event.channel_id !== props.channel.id || !event.message) return
    const atBottom = isNearBottom()
    const arrives = event.kind === "new" && isInSlackStream(event.message) && !isHeld(event.message.ts) && !props.aroundTs
    applyLive(event.message, event.at, event.reactors)
    if (event.parent) applyLive(event.parent, event.at)
    if (atBottom) {
        nextTick(() => {
            scrollToBottom()
            markReadSoon()
        })
    } else if (arrives) {
        newBelow.value++
    }
})

const write = computed(() => (props.channel.is_archived ? null : props.channel.write ?? null))
const canWriteNow = computed(() => !!write.value?.allowed && !write.value.reason)

const onSent = (sent: SlackSentMessage) => {
    if (props.aroundTs) {
        router.visit(route("grp.chat.staff.slack.show", { slackChannel: props.channel.id }))
        return
    }
    if (sent.message) applyLive(sent.message, sent.at)
    if (sent.parent) applyLive(sent.parent, sent.at)
    nextTick(() => {
        scrollToBottom()
        markReadSoon()
    })
}

const onThreadSent = (sent: SlackSentMessage) => {
    if (sent.parent) applyLive(sent.parent, sent.at)
    if (sent.message && isInSlackStream(sent.message)) onSent({ ...sent, parent: null })
}

const showPurpose = computed(() => props.channel.purpose && props.channel.purpose !== props.channel.topic)
</script>

<template>
    <div class="relative flex h-full w-full min-w-0 bg-white text-gray-900">
        <div class="relative flex h-full min-w-0 flex-1 flex-col">
            <div class="shrink-0 border-b border-[#dddddd] bg-white">
                <div class="flex min-h-[64px] items-center gap-x-2 px-5 py-2.5">
                    <button type="button" :aria-label="ctrans('Back')" class="-ml-3 p-2 text-gray-600 md:hidden" @click="emit('back')">
                        <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-x-2">
                            <h2 class="truncate text-[18px] font-black leading-6 text-[#1d1c1d]"><span class="font-bold">#</span>{{ channel.name }}</h2>
                            <span v-if="channel.is_archived" class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-[#616061]">
                                <FontAwesomeIcon icon="fal fa-archive" fixed-width aria-hidden="true" />{{ ctrans("Archived") }}
                            </span>
                        </div>
                        <div class="flex min-w-0 items-center text-[13px] leading-5 text-[#616061]">
                            <span v-if="channel.member_count" class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap" v-tooltip="ctrans('Members in Slack')">
                                <FontAwesomeIcon icon="fal fa-users" fixed-width aria-hidden="true" />{{ channel.member_count }}
                            </span>
                            <template v-if="channel.pinned_count">
                                <span v-if="channel.member_count" class="mx-2 h-3 w-px shrink-0 bg-[#dddddd]" />
                                <button type="button" class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap hover:text-[#1d1c1d]" :aria-label="ctrans('Pinned (:count)', { count: String(channel.pinned_count) })" v-tooltip="ctrans('Pinned messages')" @click="togglePins">
                                    <FontAwesomeIcon icon="fal fa-thumbtack" fixed-width aria-hidden="true" />{{ channel.pinned_count }}
                                </button>
                            </template>
                            <template v-if="channel.topic || showPurpose">
                                <span v-if="channel.member_count || channel.pinned_count" class="mx-2 h-3 w-px shrink-0 bg-[#dddddd]" />
                                <span class="truncate" v-tooltip="showPurpose ? channel.purpose : undefined">{{ channel.topic || channel.purpose }}</span>
                            </template>
                        </div>
                    </div>
                    <span v-if="!canWriteNow" class="hidden shrink-0 whitespace-nowrap text-xs text-[#616061] sm:inline">{{ ctrans("Read only, from Slack") }}</span>
                </div>
                <div v-if="bookmarks.length || write?.allowed" class="flex items-center gap-x-1.5 overflow-x-auto px-3 pb-2">
                    <span v-for="bookmark in bookmarks" :key="bookmark.id" class="group/bookmark inline-flex shrink-0 items-center gap-x-0.5">
                        <a
                            v-if="bookmark.url"
                            :href="bookmark.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex max-w-[14rem] shrink-0 items-center gap-1 rounded border border-gray-200 bg-white px-2 py-0.5 text-xs text-gray-700 hover:border-gray-300 hover:underline"
                            v-tooltip="bookmark.url">
                            <span v-if="bookmark.emoji?.unicode" aria-hidden="true">{{ bookmark.emoji.unicode }}</span>
                            <img v-else-if="bookmark.emoji?.url" :src="bookmark.emoji.url" alt="" class="h-3.5 w-3.5 object-contain" loading="lazy" />
                            <FontAwesomeIcon v-else icon="fal fa-link" class="text-gray-400" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ bookmark.title }}</span>
                        </a>
                        <span
                            v-else
                            class="inline-flex max-w-[14rem] shrink-0 cursor-default items-center gap-1 rounded border border-dashed border-gray-200 px-2 py-0.5 text-xs text-gray-400"
                            v-tooltip="ctrans('Not opened from aiku: the link is not a web or email address')">
                            <span v-if="bookmark.emoji?.unicode" aria-hidden="true">{{ bookmark.emoji.unicode }}</span>
                            <span class="truncate">{{ bookmark.title }}</span>
                        </span>
                        <Button
                            v-if="write?.allowed && bookmark.url"
                            type="tertiary"
                            size="xs"
                            class="opacity-0 focus:opacity-100 group-hover/bookmark:opacity-100"
                            icon="fal fa-pencil"
                            :tooltip="ctrans('Edit bookmark')"
                            @click="openBookmark(bookmark)" />
                    </span>
                    <Button v-if="write?.allowed" type="tertiary" size="xs" class="shrink-0 whitespace-nowrap" icon="fal fa-plus" :label="ctrans('Add a bookmark')" @click="openBookmark(null)" />
                </div>
            </div>

            <div ref="streamEl" class="min-h-0 flex-1 overflow-y-auto px-3 pb-3" @scroll.passive="onStreamScroll">
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

                    <div v-if="!rows.length" class="flex h-full flex-col items-center justify-center gap-1 py-16 text-center text-sm text-gray-400">
                        <FontAwesomeIcon icon="fal fa-comment-dots" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                        {{ ctrans("No messages in this channel yet") }}
                    </div>

                    <InfiniteScroll v-else data="slack_messages" reverse preserve-url>
                        <template #next="{ loading, hasMore }">
                            <div v-if="loading" class="flex justify-center py-3"><LoadingIcon /></div>
                            <div v-else-if="!hasMore" class="py-4 text-center text-xs text-gray-400">{{ ctrans("This is the first message of #:channel", { channel: channel.name ?? "" }) }}</div>
                        </template>
                        <template #previous="{ loading }">
                            <div v-if="loading" class="flex justify-center py-3"><LoadingIcon /></div>
                        </template>

                        <div
                            v-for="{ message, startsDay, continues, startsUnread } in rows"
                            :key="message.id"
                            :data-slack-ts="message.ts"
                            class="rounded transition-colors duration-700"
                            :class="highlightedTs === message.ts ? 'bg-amber-100/70' : ''">
                            <div v-if="startsDay" class="flex items-center gap-x-2 py-2">
                                <span class="h-px flex-1 bg-gray-200" />
                                <span class="whitespace-nowrap rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-xxs font-medium text-gray-600">{{ dayLabel(message.sent_at) }}</span>
                                <span class="h-px flex-1 bg-gray-200" />
                            </div>
                            <div v-if="startsUnread" class="relative flex items-center py-1.5" role="separator" :aria-label="ctrans('New messages')">
                                <span class="h-px flex-1 bg-[#e01e5a]" />
                                <span class="absolute right-0 rounded-r bg-white pl-1.5 pr-1 text-xs font-bold text-[#e01e5a]">{{ ctrans("New") }}</span>
                            </div>
                            <SlackMessageRow :message="message" :continues="continues" :channel-links="channelLinks" @open-thread="openThread($event)" />
                        </div>
                    </InfiniteScroll>
                </Deferred>
            </div>
            <Button
                v-if="newBelow"
                type="tertiary"
                size="xs"
                icon="fal fa-arrow-down"
                class="absolute bottom-4 left-1/2 z-10 -translate-x-1/2 shadow"
                :label="newBelow === 1 ? ctrans('1 new message') : ctrans(':count new messages', { count: String(newBelow) })"
                @click="scrollToBottom" />
            <SlackMessageComposer v-if="write" :channel-id="channel.id" :write="write" :live-channel="liveChannel" :placeholder="ctrans('Message #:channel', { channel: channel.name ?? '' })" @sent="onSent" />
        </div>

        <SlackThreadPanel v-if="openThreadTs" :channel-id="channel.id" :live-channel="liveChannel" :ts="openThreadTs" :focus-ts="focusReplyTs" :channel-links="channelLinks" :write="write" @close="openThreadTs = null" @sent="onThreadSent" />

        <Dialog v-model:visible="bookmarkDialog" modal :header="bookmarkId === null ? ctrans('Add a bookmark') : ctrans('Edit bookmark')" :style="{ width: '24rem' }" :breakpoints="{ '640px': '92vw' }">
            <p v-if="bookmarkNeedsConnect && write?.reason" class="text-sm text-gray-700">
                {{ write.reason }}
                <a v-if="write.connect_url" :href="write.connect_url" class="ml-1 font-medium text-[var(--app-accent)] hover:underline">{{ ctrans("Connect Slack") }}</a>
            </p>
            <form v-else class="space-y-2" @submit.prevent="sendBookmark(bookmarkId === null ? 'post' : 'patch')">
                <label class="block text-xs text-gray-600">{{ ctrans("Name") }}
                    <InputText v-model="bookmarkForm.title" class="mt-0.5 w-full" maxlength="200" autofocus />
                </label>
                <label class="block text-xs text-gray-600">{{ ctrans("Link") }}
                    <InputText v-model="bookmarkForm.link" class="mt-0.5 w-full" placeholder="https://" inputmode="url" />
                </label>
                <label class="block text-xs text-gray-600">{{ ctrans("Emoji name (optional)") }}
                    <InputText v-model="bookmarkForm.emoji" class="mt-0.5 w-full" placeholder="books" />
                </label>
                <p v-if="bookmarkError" class="text-xs text-red-600">{{ bookmarkError }}</p>
                <div class="flex gap-2 pt-1">
                    <Button v-if="bookmarkId !== null" type="tertiary" class="shrink-0" :label="ctrans('Remove')" :disabled="bookmarkBusy" @click="sendBookmark('delete')">
                        <template #icon><FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" /></template>
                    </Button>
                    <Button type="tertiary" full :label="ctrans('Save')" :loading="bookmarkBusy" @click="sendBookmark(bookmarkId === null ? 'post' : 'patch')" />
                </div>
            </form>
        </Dialog>

        <Popover ref="pinsPopover">
            <div class="w-[min(24rem,calc(100vw-2rem))]">
                <div class="mb-1 flex items-center gap-1 text-xs font-semibold text-gray-600">
                    <FontAwesomeIcon icon="fal fa-thumbtack" fixed-width aria-hidden="true" />{{ ctrans("Pinned messages") }}
                </div>
                <div v-if="pinsLoading" class="flex justify-center py-4"><LoadingIcon /></div>
                <p v-else-if="pinsError" class="py-2 text-xs text-red-600">{{ pinsError }}</p>
                <div v-else-if="pins && !pins.length" class="py-3 text-center text-xs text-gray-400">{{ ctrans("No pinned messages") }}</div>
                <div v-else class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                    <button v-for="pin in pins ?? []" :key="pin.id" type="button" class="block w-full px-1 py-2 text-left hover:bg-gray-50" @click="jumpTo(pin)">
                        <div class="flex items-baseline gap-x-1.5 text-xs">
                            <span class="truncate font-semibold text-gray-800">{{ pin.author.name ?? ctrans("Unknown person") }}</span>
                            <span class="shrink-0 text-xxs text-gray-400">{{ useFormatTime(pin.sent_at, { formatTime: "short-datetime" }) }}</span>
                        </div>
                        <div class="line-clamp-3 text-xs text-gray-600"><SlackNodes :nodes="pin.nodes" :channel-links="channelLinks" /></div>
                    </button>
                </div>
            </div>
        </Popover>
    </div>
</template>
