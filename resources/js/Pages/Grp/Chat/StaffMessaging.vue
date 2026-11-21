<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArchive, faCheck, faCheckDouble, faChevronRight, faComments, faHashtag, faPlus, faSearch, faUser, faTasks } from "@fal"
import InputText from "primevue/inputtext"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Image from "@/Common/Components/Image.vue"
import MessagingConversation from "@/Components/Messaging/MessagingConversation.vue"
import StaffConversationPreview from "@/Components/Messaging/StaffConversationPreview.vue"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useTruncate } from "@/Composables/useTruncate"
import SlackChannelStream, { type SlackChannelHeader, type SlackStreamMessage } from "@/Components/Messaging/Slack/SlackChannelStream.vue"
import SlackMessageSearch from "@/Components/Messaging/Slack/SlackMessageSearch.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { isInSlackStream, reloadSlack, useSlackLive, type SlackLiveEvent } from "@/Components/Messaging/Slack/slackLive"

library.add(faArchive, faCheck, faCheckDouble, faChevronRight, faComments, faHashtag, faPlus, faSearch, faUser, faTasks)

interface SlackChannelListItem {
    id: number
    slack_id: string
    name: string | null
    topic: string | null
    is_archived: boolean
    last_message_at: string | null
    unread_count?: number
    last_read_ts?: string | null
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    selected_ulid: string | null
    slack_channels?: SlackChannelListItem[]
    slack_live_channel?: string | null
    slack_channel?: SlackChannelHeader | null
    slack_messages?: { data: SlackStreamMessage[] } | null
    slack_thread_ts?: string | null
    slack_around_ts?: string | null
    slack_reply_ts?: string | null
}>()

const knownSlackChannels = ref<SlackChannelListItem[] | null>(props.slack_channels ?? null)
const knownSlackLiveChannel = ref<string | null>(props.slack_live_channel ?? null)
watch(() => props.slack_channels, (channels) => {
    if (channels !== undefined) knownSlackChannels.value = channels
})
watch(() => props.slack_live_channel, (channel) => {
    if (channel !== undefined) knownSlackLiveChannel.value = channel
})
const isSlackLoading = computed(() => knownSlackChannels.value === null)
const hasSlack = computed(() => (knownSlackChannels.value?.length ?? 0) > 0)
const isSlackIndexWithoutAccess = computed(() => knownSlackChannels.value?.length === 0 && route().current("grp.chat.staff.slack.index"))
const isArchivedOpen = ref(false)
const query = ref("")
const openSlackChannelId = ref<number | null>(props.slack_channel?.id ?? null)
const slackChannelLinks = computed(() => Object.fromEntries((knownSlackChannels.value ?? []).map((channel) => [channel.slack_id, channel.id])))
const filteredSlackChannels = computed(() => {
    const q = query.value.trim().toLowerCase().replace(/^#/, "")
    return (knownSlackChannels.value ?? []).filter((channel) => !q || (channel.name ?? "").toLowerCase().includes(q) || (channel.topic ?? "").toLowerCase().includes(q))
})
const activeSlackChannels = computed(() => filteredSlackChannels.value.filter((channel) => !channel.is_archived))
const archivedSlackChannels = computed(() => filteredSlackChannels.value.filter((channel) => channel.is_archived))
const slackUnread = ref<Record<number, { count: number; lastReadTs: string | null; counted: string[] }>>({})
const slackLastMessageAt = ref<Record<number, string>>({})
const slackReadError = ref<string | null>(null)
watch(knownSlackChannels, (channels) => {
    slackUnread.value = Object.fromEntries((channels ?? []).map((channel) => [channel.id, { count: channel.unread_count ?? 0, lastReadTs: channel.last_read_ts ?? null, counted: [] }]))
    slackLastMessageAt.value = {}
}, { immediate: true })
const unreadOf = (channelId: number) => slackUnread.value[channelId]?.count ?? 0
const unreadLabel = (channelId: number) => (unreadOf(channelId) > 99 ? "99+" : String(unreadOf(channelId)))
const hasSlackUnread = computed(() => Object.values(slackUnread.value).some((state) => state.count > 0))

const onSlackLive = (event: SlackLiveEvent) => {
    const state = slackUnread.value[event.channel_id]
    const message = event.message
    if (!message) {
        if (state && event.kind === "new" && !(state.lastReadTs && event.ts <= state.lastReadTs) && !state.counted.includes(event.ts)) {
            state.counted.push(event.ts)
            state.count++
        }
        if (showSlackChannel.value && props.slack_channel?.id === event.channel_id) reloadSlack(["slack_messages"])
        return
    }
    if (!state || !isInSlackStream(message) || message.is_system) return
    if (event.kind === "deleted") {
        if (state.counted.includes(message.ts)) {
            state.counted = state.counted.filter((ts) => ts !== message.ts)
            state.count = Math.max(0, state.count - 1)
        }
        return
    }
    if (event.kind !== "new" || message.is_deleted) return
    slackLastMessageAt.value[event.channel_id] = message.sent_at
    if (message.author.user_id === myId.value || (state.lastReadTs && message.ts <= state.lastReadTs) || state.counted.includes(message.ts)) return
    state.counted.push(message.ts)
    state.count++
}
useSlackLive(() => knownSlackLiveChannel.value, onSlackLive)

const onSlackChannelRead = (channelId: number, ts: string | null) => {
    const state = slackUnread.value[channelId]
    if (!state) return
    state.count = ts ? state.counted.filter((counted) => counted > ts).length : 0
    state.counted = ts ? state.counted.filter((counted) => counted > ts) : []
    state.lastReadTs = ts
}

const markSlackChannelRead = async (channelId: number) => {
    slackReadError.value = null
    try {
        await axios.post(route("grp.chat.staff.slack.read", { slackChannel: channelId }))
        onSlackChannelRead(channelId, null)
    } catch {
        slackReadError.value = ctrans("The channel could not be marked as read, try again")
    }
}

const markAllSlackChannelsRead = async () => {
    slackReadError.value = null
    try {
        await axios.post(route("grp.chat.staff.slack.read-all"))
        Object.keys(slackUnread.value).forEach((channelId) => onSlackChannelRead(Number(channelId), null))
    } catch {
        slackReadError.value = ctrans("The channels could not be marked as read, try again")
    }
}

let slackConnectionDropped = false
let slackReconnectTimeout: ReturnType<typeof setTimeout> | null = null
const onSlackConnectionState = ({ previous, current }: { previous: string; current: string }) => {
    if (previous === "connected" && current !== "connected") {
        slackConnectionDropped = true
        return
    }
    if (current !== "connected" || !slackConnectionDropped || !hasSlack.value) return
    slackConnectionDropped = false
    if (slackReconnectTimeout) clearTimeout(slackReconnectTimeout)
    slackReconnectTimeout = setTimeout(() => {
        slackReconnectTimeout = null
        reloadSlack(showSlackChannel.value ? ["slack_channels", "slack_messages"] : ["slack_channels"])
    }, 2000 + Math.random() * 6000)
}

const showArchived = computed(() => isArchivedOpen.value || (query.value.trim() !== "" && archivedSlackChannels.value.length > 0) || (showSlackChannel.value && !!props.slack_channel?.is_archived))

const store = useStaffMessaging()
const myId = computed(() => usePage().props?.auth?.user?.id)

const selectedUlid = ref<string | null>(window.innerWidth < 768 ? null : props.selected_ulid)
const isMobile = ref(window.innerWidth < 768)
const coworkerResults = ref<StaffCoworker[]>([])
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const handleResize = () => {
    isMobile.value = window.innerWidth < 768
}

const conversationTitle = (conversation: any) =>
    conversation.name || conversation.participants.filter((p: any) => p.id !== myId.value).map((p: any) => p.name).join(", ")
const conversationAvatar = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.avatar ?? null

const isTaskThread = (conversation: { context_type?: string | null }) => conversation.context_type === "StaffTask"

const messagingConversations = computed(() => store.conversations.filter((c) => !isTaskThread(c)))
const taskThreadCount = computed(() => store.conversations.length - messagingConversations.value.length)
const showSlackChannel = computed(() => !!props.slack_channel && openSlackChannelId.value === props.slack_channel.id && !selectedUlid.value)
const totalUnread = computed(() =>
    messagingConversations.value.reduce((sum, conversation) => sum + (conversation.unread_count ?? 0), 0)
    + Object.values(slackUnread.value).reduce((sum, state) => sum + state.count, 0))

const filteredConversations = computed(() => {
    const q = query.value.trim().toLowerCase()
    if (!q) return messagingConversations.value
    return messagingConversations.value.filter((c) =>
        conversationTitle(c).toLowerCase().includes(q) || (c.last_message ?? "").toLowerCase().includes(q)
    )
})

const openSlackChannel = (channelId: number) => {
    store.fullViewUlid = null
    selectedUlid.value = null
    openSlackChannelId.value = channelId
}

watch(() => props.slack_channel, (channel) => {
    if (!channel) return
    openSlackChannel(channel.id)
})

watch(() => props.selected_ulid, (ulid) => {
    if (!ulid || ulid === selectedUlid.value) return
    openSlackChannelId.value = null
    store.loadMessages(ulid)
    store.markRead(ulid)
    if (isMobile.value) {
        store.openConversation(ulid)
        return
    }
    store.fullViewUlid = ulid
    selectedUlid.value = ulid
})

const openTaskThreadPage = (ulid: string) => {
    const conversation = store.conversationByUlid(ulid)
    if (!conversation || !isTaskThread(conversation) || !conversation.context_url) return false
    router.visit(conversation.context_url)
    return true
}

const showCoworkerResults = computed(() => query.value.trim().length >= 2)

const onSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout)
    if (query.value.trim().length < 2) {
        coworkerResults.value = []
        return
    }
    searchTimeout = setTimeout(async () => {
        const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q: query.value.trim() } })
        coworkerResults.value = data.data
    }, 300)
}

const showConversationUrl = (ulid: string) => {
    if (!props.slack_channel) {
        router.replace({
            url: route("grp.chat.staff.show", ulid),
            preserveState: true,
            preserveScroll: true,
            props: (current) => ({ ...current, selected_ulid: ulid }),
        })
        return
    }
    router.visit(route("grp.chat.staff.show", ulid), {
        only: ["breadcrumbs", "title", "selected_ulid", "slack_channel"],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const selectConversation = (ulid: string) => {
    store.fullViewUlid = ulid
    store.loadMessages(ulid)
    store.markRead(ulid)
    openSlackChannelId.value = null

    if (isMobile.value) {
        store.openConversation(ulid)
    } else {
        selectedUlid.value = ulid
        showConversationUrl(ulid)
    }
}

const startChatWith = async (coworker: StaffCoworker) => {
    await store.openWithUser(coworker.id)
    const conversation = store.conversations.find((c) => c.type === "dm" && c.participants.some((p) => p.id === coworker.id))
    query.value = ""
    coworkerResults.value = []
    if (conversation) selectConversation(conversation.ulid)
}

const closeConversation = () => {
    store.fullViewUlid = null
    selectedUlid.value = null
    router.replace({
        url: route("grp.chat.staff.index"),
        preserveState: true,
        preserveScroll: true,
        props: (current) => ({ ...current, selected_ulid: null }),
    })
}

const selectedConversation = computed(() => selectedUlid.value ? store.conversationByUlid(selectedUlid.value) : null)

watch(() => store.fullViewUlid, (ulid) => {
    if (!ulid || ulid === selectedUlid.value) {
        return
    }
    if (openTaskThreadPage(ulid)) {
        store.fullViewUlid = null
        return
    }
    if (isMobile.value) {
        store.openConversation(ulid)
        return
    }
    selectedUlid.value = ulid
    openSlackChannelId.value = null
    store.loadMessages(ulid)
    store.markRead(ulid)
    showConversationUrl(ulid)
})

onMounted(async () => {
    window.addEventListener('resize', handleResize)
    window.Echo?.connector?.pusher?.connection?.bind("state_change", onSlackConnectionState)
    await store.fetchConversations()

    if (isMobile.value && props.selected_ulid) {
        store.openConversation(props.selected_ulid)
    } else if (selectedUlid.value) {
        store.fullViewUlid = selectedUlid.value
        store.loadMessages(selectedUlid.value)
        store.markRead(selectedUlid.value)
    }
})

onUnmounted(() => {
    window.Echo?.connector?.pusher?.connection?.unbind("state_change", onSlackConnectionState)
    store.fullViewUlid = null
    window.removeEventListener('resize', handleResize)
    if (searchTimeout) clearTimeout(searchTimeout)
    if (slackReconnectTimeout) clearTimeout(slackReconnectTimeout)
})
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <div class="flex min-h-[50vh] md:min-h-[70vh] h-[calc(100vh-10rem)] border-t border-gray-200">
        <div class="w-full md:w-80 shrink-0 border-r border-gray-200 bg-white flex flex-col" :class="selectedUlid || showSlackChannel ? 'hidden md:flex' : 'flex'">
            <div class="p-3 border-b border-gray-200 space-y-2">
                <div class="relative">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs" fixed-width aria-hidden="true" />
                    <InputText
                        v-model="query"
                        :placeholder="ctrans('Search or start a new chat…')"
                        size="small"
                        class="w-full !pl-8"
                        @input="onSearchInput" />
                </div>
                <div v-if="hasSlack && (totalUnread || slackReadError)" class="flex items-center gap-x-2 text-xs">
                    <span v-if="slackReadError" class="flex-1 text-red-600">{{ slackReadError }}</span>
                    <span v-else class="flex-1 font-semibold text-gray-700">{{ ctrans(":count unread", { count: totalUnread > 999 ? "999+" : String(totalUnread) }) }}</span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto" :class="hasSlack && 'flex flex-col'">
                <template v-if="showCoworkerResults">
                    <div class="px-3 pt-2 pb-1 text-xs text-gray-500">{{ ctrans('Start a chat with…') }}</div>
                    <button
                        v-for="coworker in coworkerResults"
                        :key="'cw-' + coworker.id"
                        class="w-full flex items-center gap-x-2 px-3 py-2 hover:bg-gray-50 text-left"
                        @click="startChatWith(coworker)">
                        <div class="relative h-8 w-8 rounded-full overflow-hidden bg-gray-200 shrink-0">
                            <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-400" fixed-width aria-hidden="true" />
                        </div>
                        <span class="text-sm text-gray-900 truncate">{{ coworker.name }}</span>
                    </button>
                    <div v-if="!coworkerResults.length" class="px-3 py-2 text-xs text-gray-400">{{ ctrans('No matches') }}</div>
                </template>

                <template v-else>
                    <div v-if="hasSlack && filteredConversations.length" class="px-3 pt-3 pb-1 text-xxs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Conversations") }}</div>
                    <template v-for="conversation in filteredConversations" :key="conversation.ulid">
                        <button
                            class="w-full flex items-center gap-x-3 px-3 py-2.5 hover:bg-gray-50 text-left border-b border-gray-50"
                            :class="selectedUlid === conversation.ulid ? 'bg-[var(--app-accent-soft)]' : ''"
                            @click="selectConversation(conversation.ulid)">
                            <div v-if="conversation.type === 'group'" class="h-9 w-9 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                                <FontAwesomeIcon icon="fal fa-comments" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                            </div>
                            <div v-else class="relative h-9 w-9 rounded-full overflow-hidden bg-gray-200 shrink-0">
                                <Image v-if="conversationAvatar(conversation)" :src="conversationAvatar(conversation)" :alt="conversationTitle(conversation)" image-cover />
                                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-400" fixed-width aria-hidden="true" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-900 truncate">{{ conversationTitle(conversation) }}</span>
                                    <span v-if="conversation.last_message_at" class="text-xxs text-gray-400 shrink-0 ml-1">{{ useFormatTime(conversation.last_message_at, { formatTime: 'hm' }) }}</span>
                                </div>
                                <div class="text-xs text-gray-500 truncate"><StaffConversationPreview :conversation="conversation" :text="useTruncate(conversation.last_message ?? '', 40)" /></div>
                            </div>
                            <span v-if="conversation.unread_count > 0" class="bg-[var(--app-accent)] text-[var(--app-accent-text)] rounded-full h-5 min-w-[1.25rem] px-1.5 flex items-center justify-center text-xxs shrink-0">{{ conversation.unread_count }}</span>
                        </button>
                    </template>
                    <div v-if="!filteredConversations.length && !hasSlack" class="px-3 py-6 text-center text-xs text-gray-400">{{ ctrans('No messages yet') }}</div>
                    <Link
                        v-if="taskThreadCount"
                        :href="route('grp.tasks.index')"
                        class="flex items-center gap-x-2 px-3 py-2.5 text-xs text-gray-500 transition duration-200 hover:bg-gray-50 hover:text-gray-700">
                        <FontAwesomeIcon icon="fal fa-tasks" fixed-width aria-hidden="true" />
                        {{ ctrans(':count task chats live with their tasks', { count: String(taskThreadCount) }) }}
                    </Link>
                </template>

                <div v-if="isSlackLoading" class="space-y-2 px-3 pt-3" aria-hidden="true">
                    <div class="skeleton h-3 w-20 rounded" />
                    <div class="skeleton h-10 rounded" />
                    <div class="skeleton h-10 rounded" />
                </div>
                <div v-else-if="isSlackIndexWithoutAccess" class="px-4 py-8 text-center text-xs text-gray-500 space-y-1">
                    <FontAwesomeIcon icon="fal fa-hashtag" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                    <p class="font-medium text-gray-700">{{ ctrans("No Slack channels to show") }}</p>
                    <p>{{ ctrans("Public Slack channels show here once your aiku account is linked to your Slack account by the same email address.") }}</p>
                </div>

                <div v-if="hasSlack && (!showCoworkerResults || filteredSlackChannels.length)" :class="showCoworkerResults && 'order-first'">
                    <div class="flex items-center justify-between gap-x-2 px-3 pt-3 pb-1">
                        <span class="text-xxs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Channels") }}</span>
                        <Button v-if="hasSlackUnread" type="tertiary" size="xxs" :label="ctrans('Mark all as read')" @click="markAllSlackChannelsRead">
                            <template #icon><FontAwesomeIcon icon="fal fa-check-double" class="text-green-600" fixed-width aria-hidden="true" /></template>
                        </Button>
                    </div>
                    <Link
                        v-for="channel in activeSlackChannels"
                        :key="'slack-' + channel.id"
                        :href="route('grp.chat.staff.slack.show', channel.id)"
                        preserve-state
                        preserve-scroll
                        class="group flex items-start gap-x-2 px-3 py-2 border-b border-gray-50 hover:bg-gray-50"
                        :class="showSlackChannel && slack_channel?.id === channel.id ? 'bg-[var(--app-accent-soft)]' : ''">
                        <FontAwesomeIcon icon="fal fa-hashtag" class="mt-1 text-gray-400 text-xs" fixed-width aria-hidden="true" />
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between gap-x-1">
                                <span class="text-sm truncate" :class="unreadOf(channel.id) ? 'font-semibold text-gray-900' : 'font-medium text-gray-900'">{{ channel.name }}</span>
                                <span v-if="slackLastMessageAt[channel.id] ?? channel.last_message_at" class="text-xxs text-gray-400 shrink-0 whitespace-nowrap">{{ useFormatTime(slackLastMessageAt[channel.id] ?? channel.last_message_at, { formatTime: 'dd MMM' }) }}</span>
                            </div>
                            <div class="flex items-center gap-x-1">
                                <span class="min-w-0 flex-1 text-xs text-gray-500 truncate">{{ channel.topic }}</span>
                                <template v-if="unreadOf(channel.id)">
                                    <Button
                                        type="tertiary"
                                        size="xxs"
                                        class="hidden shrink-0 group-hover:inline-flex"
                                        :aria-label="ctrans('Mark as read')"
                                        v-tooltip="ctrans('Mark as read')"
                                        @click.prevent.stop="markSlackChannelRead(channel.id)">
                                        <template #icon><FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" /></template>
                                    </Button>
                                    <span class="shrink-0 whitespace-nowrap rounded-full bg-[var(--app-accent)] px-1.5 text-xxs font-semibold leading-4 text-[var(--app-accent-text)]" :aria-label="ctrans(':count unread', { count: unreadLabel(channel.id) })">{{ unreadLabel(channel.id) }}</span>
                                </template>
                            </div>
                        </div>
                    </Link>
                    <div v-if="!filteredSlackChannels.length && !filteredConversations.length" class="px-3 py-6 text-center text-xs text-gray-400">{{ ctrans('No matches') }}</div>
                    <template v-if="archivedSlackChannels.length">
                        <button
                            type="button"
                            class="w-full flex items-center gap-x-2 px-3 py-2 text-xs text-gray-500 hover:bg-gray-50"
                            :aria-expanded="showArchived"
                            @click="isArchivedOpen = !isArchivedOpen">
                            <FontAwesomeIcon icon="fal fa-chevron-right" class="text-[10px] transition-transform" :class="showArchived && 'rotate-90'" fixed-width aria-hidden="true" />
                            <FontAwesomeIcon icon="fal fa-archive" fixed-width aria-hidden="true" />
                            {{ ctrans("Archived (:count)", { count: String(archivedSlackChannels.length) }) }}
                        </button>
                        <template v-if="showArchived">
                            <Link
                                v-for="channel in archivedSlackChannels"
                                :key="'slack-' + channel.id"
                                :href="route('grp.chat.staff.slack.show', channel.id)"
                                preserve-state
                                preserve-scroll
                                class="flex items-center gap-x-2 px-3 py-1.5 pl-8 text-gray-500 hover:bg-gray-50"
                                :class="showSlackChannel && slack_channel?.id === channel.id ? 'bg-[var(--app-accent-soft)]' : ''">
                                <FontAwesomeIcon icon="fal fa-hashtag" class="text-xs" fixed-width aria-hidden="true" />
                                <span class="text-sm truncate flex-1" :class="unreadOf(channel.id) && 'font-semibold text-gray-900'">{{ channel.name }}</span>
                                <span v-if="unreadOf(channel.id)" class="shrink-0 whitespace-nowrap rounded-full bg-[var(--app-accent)] px-1.5 text-xxs font-semibold leading-4 text-[var(--app-accent-text)]">{{ unreadLabel(channel.id) }}</span>
                            </Link>
                        </template>
                    </template>
                </div>
            </div>
        </div>

        <div class="flex-1 h-full min-w-0" :class="selectedUlid || showSlackChannel ? 'flex' : 'hidden md:flex'">
            <MessagingConversation
                v-if="selectedConversation"
                :conversation="selectedConversation"
                full-screen
                @back="closeConversation"
                @close="closeConversation" />
            <SlackMessageSearch v-else-if="hasSlack && !selectedUlid" :channels="knownSlackChannels ?? []">
                <SlackChannelStream
                    v-if="showSlackChannel && slack_channel"
                    :key="'slack-' + slack_channel.id"
                    :channel="slack_channel"
                    :messages="slack_messages ?? undefined"
                    :channel-links="slackChannelLinks"
                    :thread-ts="slack_thread_ts"
                    :around-ts="slack_around_ts"
                    :reply-ts="slack_reply_ts"
                    :live-channel="knownSlackLiveChannel"
                    @read="onSlackChannelRead(slack_channel.id, $event)"
                    @back="router.visit(route('grp.chat.staff.slack.index'))" />
                <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-400">
                    <FontAwesomeIcon icon="fal fa-comments" class="text-5xl mb-3" fixed-width aria-hidden="true" />
                    <span class="text-sm">{{ ctrans('Select a conversation') }}</span>
                </div>
            </SlackMessageSearch>
            <div v-else class="flex-1 flex flex-col items-center justify-center text-gray-400">
                <FontAwesomeIcon icon="fal fa-comments" class="text-5xl mb-3" fixed-width aria-hidden="true" />
                <span class="text-sm">{{ ctrans('Select a conversation') }}</span>
            </div>
        </div>
    </div>
</template>
