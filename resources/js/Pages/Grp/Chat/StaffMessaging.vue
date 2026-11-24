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
import { faArchive, faCaretDown, faCheckDouble, faComments, faEdit, faHashtag, faKeyboard, faSearch, faUser, faUsers, faTasks } from "@fal"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import MessagingConversation from "@/Components/Messaging/MessagingConversation.vue"
import DeskIcon from "@/Components/Messaging/DeskIcon.vue"
import DeskJumpDialog, { type DeskJumpTarget } from "@/Components/Messaging/DeskJumpDialog.vue"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { deskShortcutList, isMac, useDeskShortcuts } from "@/Composables/useDeskShortcuts"
import SlackChannelStream, { type SlackChannelHeader, type SlackStreamMessage } from "@/Components/Messaging/Slack/SlackChannelStream.vue"
import SlackMessageSearch from "@/Components/Messaging/Slack/SlackMessageSearch.vue"
import { isInSlackStream, reloadSlack, useSlackLive, type SlackLiveEvent } from "@/Components/Messaging/Slack/slackLive"

library.add(faArchive, faCaretDown, faCheckDouble, faComments, faEdit, faHashtag, faKeyboard, faSearch, faUser, faUsers, faTasks)

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
const SECTIONS_KEY = "desk.collapsed-sections"
const readCollapsed = (): string[] => {
    try {
        const stored = JSON.parse(localStorage.getItem(SECTIONS_KEY) ?? '["archived"]')
        return Array.isArray(stored) ? stored.filter((name) => typeof name === "string") : []
    } catch {
        return []
    }
}
const collapsedSections = ref<string[]>(readCollapsed())
const isCollapsed = (section: string) => collapsedSections.value.includes(section)
const toggleSection = (section: string) => {
    collapsedSections.value = isCollapsed(section) ? collapsedSections.value.filter((name) => name !== section) : [...collapsedSections.value, section]
    localStorage.setItem(SECTIONS_KEY, JSON.stringify(collapsedSections.value))
}
const openSlackChannelId = ref<number | null>(props.slack_channel?.id ?? null)
const slackChannelLinks = computed(() => Object.fromEntries((knownSlackChannels.value ?? []).map((channel) => [channel.slack_id, channel.id])))
const byName = (a: SlackChannelListItem, b: SlackChannelListItem) => (a.name ?? "").localeCompare(b.name ?? "")
const activeSlackChannels = computed(() => (knownSlackChannels.value ?? []).filter((channel) => !channel.is_archived).sort(byName))
const archivedSlackChannels = computed(() => (knownSlackChannels.value ?? []).filter((channel) => channel.is_archived).sort(byName))
const slackUnread = ref<Record<number, { count: number; lastReadTs: string | null; counted: string[] }>>({})
const slackReadError = ref<string | null>(null)
watch(knownSlackChannels, (channels) => {
    slackUnread.value = Object.fromEntries((channels ?? []).map((channel) => [channel.id, { count: channel.unread_count ?? 0, lastReadTs: channel.last_read_ts ?? null, counted: [] }]))
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

const isSelectedChannel = (channelId: number) => showSlackChannel.value && props.slack_channel?.id === channelId
const showArchived = computed(() => !isCollapsed("archived") || (showSlackChannel.value && !!props.slack_channel?.is_archived))

const store = useStaffMessaging()
const myId = computed(() => usePage().props?.auth?.user?.id)

const selectedUlid = ref<string | null>(window.innerWidth < 768 ? null : props.selected_ulid)
const isMobile = ref(window.innerWidth < 768)

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

const isJumpOpen = ref(false)
const isShortcutsOpen = ref(false)

const visitSlackChannel = (channelId: number) => {
    router.visit(route("grp.chat.staff.slack.show", channelId), { preserveState: true, preserveScroll: true })
}

const jumpTargets = computed<DeskJumpTarget[]>(() => [
    ...[...activeSlackChannels.value, ...archivedSlackChannels.value].map((channel) => ({
        key: "slack-" + channel.id,
        kind: "channel" as const,
        label: channel.name ?? "",
        unread: unreadOf(channel.id),
        archived: channel.is_archived,
        channelId: channel.id,
    })),
    ...messagingConversations.value.map((conversation) => ({
        key: "dm-" + conversation.ulid,
        kind: "conversation" as const,
        label: conversationTitle(conversation),
        avatar: conversationAvatar(conversation),
        isGroup: conversation.type === "group",
        unread: conversation.unread_count ?? 0,
        ulid: conversation.ulid,
    })),
])

const onJump = (target: DeskJumpTarget) => {
    if (target.channelId) visitSlackChannel(target.channelId)
    else if (target.ulid) selectConversation(target.ulid)
    else if (target.coworker) startChatWith(target.coworker)
}

const isChannelRowShown = (channelId: number) => !isCollapsed("channels") || isSelectedChannel(channelId) || unreadOf(channelId) > 0
const isConversationRowShown = (conversation: { ulid: string; unread_count: number }) => !isCollapsed("dms") || selectedUlid.value === conversation.ulid || conversation.unread_count > 0

const sidebarOrder = computed(() => [
    ...activeSlackChannels.value.filter((channel) => isChannelRowShown(channel.id)).map((channel) => ({ channelId: channel.id })),
    ...(showArchived.value ? archivedSlackChannels.value.map((channel) => ({ channelId: channel.id })) : []),
    ...messagingConversations.value.filter(isConversationRowShown).map((conversation) => ({ ulid: conversation.ulid })),
])

const moveInSidebar = (step: 1 | -1) => {
    const order = sidebarOrder.value
    if (!order.length) return
    const current = order.findIndex((item) => ("ulid" in item ? item.ulid === selectedUlid.value : isSelectedChannel(item.channelId)))
    const next = order[current === -1 ? (step === 1 ? 0 : order.length - 1) : (current + step + order.length) % order.length]
    if ("ulid" in next) selectConversation(next.ulid)
    else visitSlackChannel(next.channelId)
}

const markCurrentRead = () => {
    if (selectedUlid.value) store.markRead(selectedUlid.value)
    else if (showSlackChannel.value && props.slack_channel && unreadOf(props.slack_channel.id)) markSlackChannelRead(props.slack_channel.id)
}

const markEverythingRead = () => {
    messagingConversations.value.filter((conversation) => conversation.unread_count > 0).forEach((conversation) => store.markRead(conversation.ulid))
    if (hasSlackUnread.value) markAllSlackChannelsRead()
}

useDeskShortcuts({
    jump: () => (isJumpOpen.value = true),
    showShortcuts: () => (isShortcutsOpen.value = !isShortcutsOpen.value),
    markRead: markCurrentRead,
    markAllRead: markEverythingRead,
    move: moveInSidebar,
    isShortcutsOpen: () => isShortcutsOpen.value,
})

const shortcutList = deskShortcutList()

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
    if (slackReconnectTimeout) clearTimeout(slackReconnectTimeout)
})
</script>

<template>
    <Head :title="title" />

    <div class="desk-palette flex min-h-[50vh] md:min-h-[70vh] h-[calc(100vh-6.5rem)] font-desk text-[15px]">
        <nav
            class="w-full md:w-64 shrink-0 flex-col bg-[var(--desk-sidebar)] text-[var(--desk-text)]"
            :class="selectedUlid || showSlackChannel ? 'hidden md:flex' : 'flex'"
            :aria-label="ctrans('Desk')">
            <div class="flex h-[50px] shrink-0 items-center gap-x-2 border-b border-[var(--desk-border)] pl-4 pr-3">
                <DeskIcon class="text-lg" />
                <h1 class="flex-1 truncate text-[18px] font-black leading-none text-white">{{ ctrans("Desk") }}</h1>
                <button
                    type="button"
                    class="flex h-[34px] w-[34px] items-center justify-center rounded-full bg-white text-[var(--desk-sidebar)] transition hover:opacity-90"
                    :aria-label="ctrans('New message')"
                    v-tooltip="ctrans('New message')"
                    @click="isJumpOpen = true">
                    <FontAwesomeIcon icon="fal fa-edit" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto py-2">
                <button
                    type="button"
                    class="mx-2 mb-2 flex h-7 w-[calc(100%-1rem)] items-center gap-x-2 rounded-md bg-white/10 px-2 text-[13px] text-[var(--desk-text)] hover:bg-white/20"
                    @click="isJumpOpen = true">
                    <FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" />
                    <span class="flex-1 truncate text-left">{{ ctrans("Jump to…") }}</span>
                    <kbd class="font-sans text-xs opacity-70">{{ isMac ? "⌘K" : "Ctrl+K" }}</kbd>
                </button>

                <div v-if="slackReadError" class="px-4 py-1 text-[13px] text-red-300">{{ slackReadError }}</div>

                <div v-if="isSlackLoading" class="space-y-2 px-4 pt-3" aria-hidden="true">
                    <div class="h-3 w-20 rounded bg-white/10" />
                    <div class="h-5 rounded bg-white/10" />
                    <div class="h-5 rounded bg-white/10" />
                </div>
                <div v-else-if="isSlackIndexWithoutAccess" class="space-y-1 px-4 py-6 text-center text-[13px]">
                    <FontAwesomeIcon icon="fal fa-hashtag" class="text-2xl opacity-50" fixed-width aria-hidden="true" />
                    <p class="font-bold text-white">{{ ctrans("No Slack channels to show") }}</p>
                    <p>{{ ctrans("Public Slack channels show here once your aiku account is linked to your Slack account by the same email address.") }}</p>
                </div>

                <section v-if="hasSlack" class="mt-1">
                    <div class="group flex h-7 items-center pr-2">
                        <button type="button" class="flex flex-1 items-center gap-x-1 rounded px-2 py-0.5 text-left hover:bg-[var(--desk-hover)]" :aria-expanded="!isCollapsed('channels')" @click="toggleSection('channels')">
                            <FontAwesomeIcon icon="fal fa-caret-down" class="text-xs transition-transform" :class="isCollapsed('channels') && '-rotate-90'" fixed-width aria-hidden="true" />
                            <span>{{ ctrans("Channels") }}</span>
                        </button>
                        <button
                            v-if="hasSlackUnread"
                            type="button"
                            class="flex h-6 w-6 items-center justify-center rounded opacity-0 hover:bg-[var(--desk-hover)] focus:opacity-100 group-hover:opacity-100"
                            :aria-label="ctrans('Mark all as read')"
                            v-tooltip="ctrans('Mark all as read') + ' (Shift+Esc)'"
                            @click="markAllSlackChannelsRead">
                            <FontAwesomeIcon icon="fal fa-check-double" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                    <template v-for="channel in activeSlackChannels" :key="'slack-' + channel.id">
                        <Link
                            v-if="isChannelRowShown(channel.id)"
                            :href="route('grp.chat.staff.slack.show', channel.id)"
                            preserve-state
                            preserve-scroll
                            class="mx-2 flex h-7 items-center gap-x-2 rounded-md px-4"
                            :class="isSelectedChannel(channel.id) ? 'bg-[var(--desk-active)] text-[var(--desk-active-text)]' : unreadOf(channel.id) ? 'font-bold text-white hover:bg-[var(--desk-hover)]' : 'hover:bg-[var(--desk-hover)]'">
                            <FontAwesomeIcon icon="fal fa-hashtag" class="shrink-0 text-[13px]" fixed-width aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate">{{ channel.name }}</span>
                            <span
                                v-if="unreadOf(channel.id) && !isSelectedChannel(channel.id)"
                                class="shrink-0 rounded-full bg-[var(--desk-badge)] px-2 text-xs font-bold leading-[18px] text-white"
                                :aria-label="ctrans(':count unread', { count: unreadLabel(channel.id) })">{{ unreadLabel(channel.id) }}</span>
                        </Link>
                    </template>
                </section>

                <section v-if="hasSlack && archivedSlackChannels.length" class="mt-3">
                    <button type="button" class="mx-2 flex h-7 w-[calc(100%-1rem)] items-center gap-x-1 rounded px-2 text-left hover:bg-[var(--desk-hover)]" :aria-expanded="showArchived" @click="toggleSection('archived')">
                        <FontAwesomeIcon icon="fal fa-caret-down" class="text-xs transition-transform" :class="!showArchived && '-rotate-90'" fixed-width aria-hidden="true" />
                        <span>{{ ctrans("Archived (:count)", { count: String(archivedSlackChannels.length) }) }}</span>
                    </button>
                    <template v-if="showArchived">
                        <Link
                            v-for="channel in archivedSlackChannels"
                            :key="'slack-' + channel.id"
                            :href="route('grp.chat.staff.slack.show', channel.id)"
                            preserve-state
                            preserve-scroll
                            class="mx-2 flex h-7 items-center gap-x-2 rounded-md px-4"
                            :class="isSelectedChannel(channel.id) ? 'bg-[var(--desk-active)] text-[var(--desk-active-text)]' : unreadOf(channel.id) ? 'font-bold text-white hover:bg-[var(--desk-hover)]' : 'opacity-70 hover:bg-[var(--desk-hover)]'">
                            <FontAwesomeIcon icon="fal fa-archive" class="shrink-0 text-[13px]" fixed-width aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate">{{ channel.name }}</span>
                            <span
                                v-if="unreadOf(channel.id) && !isSelectedChannel(channel.id)"
                                class="shrink-0 rounded-full bg-[var(--desk-badge)] px-2 text-xs font-bold leading-[18px] text-white"
                                :aria-label="ctrans(':count unread', { count: unreadLabel(channel.id) })">{{ unreadLabel(channel.id) }}</span>
                        </Link>
                    </template>
                </section>

                <section class="mt-3">
                    <div class="flex h-7 items-center pr-2">
                        <button type="button" class="flex flex-1 items-center gap-x-1 rounded px-2 py-0.5 text-left hover:bg-[var(--desk-hover)]" :aria-expanded="!isCollapsed('dms')" @click="toggleSection('dms')">
                            <FontAwesomeIcon icon="fal fa-caret-down" class="text-xs transition-transform" :class="isCollapsed('dms') && '-rotate-90'" fixed-width aria-hidden="true" />
                            <span>{{ ctrans("Direct messages") }}</span>
                        </button>
                    </div>
                    <template v-for="conversation in messagingConversations" :key="conversation.ulid">
                        <button
                            v-if="isConversationRowShown(conversation)"
                            type="button"
                            class="mx-2 flex h-7 w-[calc(100%-1rem)] items-center gap-x-2 rounded-md px-4 text-left"
                            :class="selectedUlid === conversation.ulid ? 'bg-[var(--desk-active)] text-[var(--desk-active-text)]' : conversation.unread_count > 0 ? 'font-bold text-white hover:bg-[var(--desk-hover)]' : 'hover:bg-[var(--desk-hover)]'"
                            @click="selectConversation(conversation.ulid)">
                            <span v-if="conversation.type === 'group'" class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-white/20 text-[11px] font-bold text-white">{{ conversation.participants.length - 1 }}</span>
                            <span v-else class="h-5 w-5 shrink-0 overflow-hidden rounded bg-white/20">
                                <Image v-if="conversationAvatar(conversation)" :src="conversationAvatar(conversation)" :alt="conversationTitle(conversation)" image-cover />
                                <FontAwesomeIcon v-else icon="fal fa-user" class="flex h-full items-center justify-center text-xs" fixed-width aria-hidden="true" />
                            </span>
                            <span class="min-w-0 flex-1 truncate">{{ conversationTitle(conversation) }}</span>
                            <span
                                v-if="conversation.unread_count > 0 && selectedUlid !== conversation.ulid"
                                class="shrink-0 rounded-full bg-[var(--desk-badge)] px-2 text-xs font-bold leading-[18px] text-white"
                                :aria-label="ctrans(':count unread', { count: String(conversation.unread_count) })">{{ conversation.unread_count > 99 ? "99+" : conversation.unread_count }}</span>
                        </button>
                    </template>
                    <button
                        type="button"
                        class="mx-2 flex h-7 w-[calc(100%-1rem)] items-center gap-x-2 rounded-md px-4 text-left hover:bg-[var(--desk-hover)]"
                        @click="isJumpOpen = true">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-white/10 text-sm">+</span>
                        <span class="truncate">{{ ctrans("Add coworkers") }}</span>
                    </button>
                </section>

                <Link
                    v-if="taskThreadCount"
                    :href="route('grp.tasks.index')"
                    class="mx-2 mt-3 flex h-7 items-center gap-x-2 rounded-md px-4 text-[13px] hover:bg-[var(--desk-hover)]">
                    <FontAwesomeIcon icon="fal fa-tasks" fixed-width aria-hidden="true" />
                    <span class="truncate">{{ ctrans(':count task chats live with their tasks', { count: String(taskThreadCount) }) }}</span>
                </Link>
            </div>

            <button
                type="button"
                class="flex h-9 shrink-0 items-center gap-x-2 border-t border-[var(--desk-border)] px-4 text-[13px] hover:bg-[var(--desk-hover)]"
                @click="isShortcutsOpen = true">
                <FontAwesomeIcon icon="fal fa-keyboard" fixed-width aria-hidden="true" />
                <span class="flex-1 truncate text-left">{{ ctrans("Keyboard shortcuts") }}</span>
                <kbd class="font-sans text-xs opacity-70">{{ isMac ? "⌘/" : "Ctrl+/" }}</kbd>
            </button>
        </nav>

        <div class="flex-1 h-full min-w-0 border-t border-gray-200" :class="selectedUlid || showSlackChannel ? 'flex' : 'hidden md:flex'">
            <MessagingConversation
                v-if="selectedConversation"
                :conversation="selectedConversation"
                full-screen
                desk
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

        <DeskJumpDialog v-model:visible="isJumpOpen" :targets="jumpTargets" @select="onJump" />

        <Dialog v-model:visible="isShortcutsOpen" modal dismissable-mask :header="ctrans('Keyboard shortcuts')" :style="{ width: '28rem' }" :breakpoints="{ '640px': '92vw' }">
            <ul class="divide-y divide-gray-100 font-desk text-sm">
                <li v-for="shortcut in shortcutList" :key="shortcut.label" class="flex items-center justify-between gap-x-4 py-2">
                    <span class="text-gray-700">{{ shortcut.label }}</span>
                    <span class="flex shrink-0 gap-x-1">
                        <kbd v-for="key in shortcut.keys" :key="key" class="min-w-[1.5rem] rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 text-center font-sans text-xs font-semibold text-gray-700 shadow-[0_1px_0_rgba(0,0,0,0.15)]">{{ key }}</kbd>
                    </span>
                </li>
            </ul>
        </Dialog>
    </div>
</template>

<style scoped>
.desk-palette {
    --desk-sidebar: #3f0e40;
    --desk-hover: #350d36;
    --desk-active: rgba(255, 255, 255, 0.92);
    --desk-active-text: #39063a;
    --desk-border: #522653;
    --desk-badge: #cd2553;
    --desk-text: rgba(255, 255, 255, 0.7);
}
</style>
