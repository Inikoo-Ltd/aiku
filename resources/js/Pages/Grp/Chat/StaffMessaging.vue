<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { useDeskSidebar } from "@/Composables/useDeskSidebar"
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArchive, faCaretDown, faCheckDouble, faComments, faEdit, faFilter, faHashtag, faKeyboard, faTimes, faUser, faUsers, faTasks } from "@fal"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import { faChevronLeft } from "@far"
import { faStar as faStarSolid } from "@fas"
import { faSlack } from "@fortawesome/free-brands-svg-icons"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import MessagingConversation from "@/Components/Messaging/MessagingConversation.vue"
import DeskSidebarRow from "@/Components/Messaging/DeskSidebarRow.vue"
import { draftChannelIds } from "@/Components/Messaging/deskDrafts"
import DeskJumpDialog, { type DeskJumpTarget } from "@/Components/Messaging/DeskJumpDialog.vue"
import NewVerbrationDialog from "@/Components/Messaging/NewVerbrationDialog.vue"
import VerbaHome from "@/Components/Messaging/VerbaHome.vue"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useLayoutStore } from "@/Stores/layout"
import { deskShortcutList, isMac, useDeskShortcuts } from "@/Composables/useDeskShortcuts"
import SlackChannelStream, { type SlackChannelHeader, type SlackStreamMessage } from "@/Components/Messaging/Slack/SlackChannelStream.vue"
import SlackMessageSearch from "@/Components/Messaging/Slack/SlackMessageSearch.vue"
import { isInSlackStream, reloadSlack, useSlackLive, type SlackLiveEvent } from "@/Components/Messaging/Slack/slackLive"

library.add(faSlack, faArchive, faCaretDown, faCheckDouble, faComments, faEdit, faFilter, faHashtag, faKeyboard, faTimes, faUser, faUsers, faTasks, faChevronLeft, faStarSolid)

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
    slack_private_channels?: SlackChannelListItem[]
    slack_direct_channels?: SlackChannelListItem[]
    slack_live_channel?: string | null
    slack_channel?: SlackChannelHeader | null
    slack_messages?: { data: SlackStreamMessage[] } | null
    slack_thread_ts?: string | null
    slack_around_ts?: string | null
    slack_reply_ts?: string | null
    desk_starred?: string[]
}>()

const knownSlackChannels = ref<SlackChannelListItem[] | null>(props.slack_channels ?? null)
const knownSlackLiveChannel = ref<string | null>(props.slack_live_channel ?? null)
watch(() => props.slack_channels, (channels) => {
    if (channels !== undefined) knownSlackChannels.value = channels
})
const knownPrivateChannels = ref<SlackChannelListItem[]>(props.slack_private_channels ?? [])
watch(() => props.slack_private_channels, (channels) => {
    if (channels !== undefined) knownPrivateChannels.value = channels
})
const privateChannels = computed(() => [...knownPrivateChannels.value].sort((a, b) => (a.name ?? "").localeCompare(b.name ?? "")))
const isPrivateChannel = (channelId: number) => knownPrivateChannels.value.some((channel) => channel.id === channelId)
const knownDirectChannels = ref<SlackChannelListItem[]>(props.slack_direct_channels ?? [])
watch(() => props.slack_direct_channels, (channels) => {
    if (channels !== undefined) knownDirectChannels.value = channels
})
const isDirectChannel = (channelId: number) => knownDirectChannels.value.some((channel) => channel.id === channelId)
const channelShowRoute = (channelId: number) => route(isDirectChannel(channelId) ? "grp.chat.staff.direct.slack.show" : isPrivateChannel(channelId) ? "grp.chat.staff.groups.slack.show" : "grp.chat.staff.slack.show", channelId)
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
const { isSidebarCollapsed, toggleSidebar } = useDeskSidebar()
const filterInput = ref<{ $el: HTMLInputElement } | null>(null)
const openFilter = async () => {
    if (isSidebarCollapsed.value) toggleSidebar()
    await nextTick()
    filterInput.value?.$el.focus()
}
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
watch([knownSlackChannels, knownPrivateChannels, knownDirectChannels], ([channels, privates, directs]) => {
    slackUnread.value = Object.fromEntries([...(channels ?? []), ...privates, ...directs].map((channel) => [channel.id, { count: channel.unread_count ?? 0, lastReadTs: channel.last_read_ts ?? null, counted: [] }]))
}, { immediate: true })
const unreadOf = (channelId: number) => slackUnread.value[channelId]?.count ?? 0
const hasSlackUnread = computed(() => (knownSlackChannels.value ?? []).some((channel) => unreadOf(channel.id) > 0))

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
        ;(knownSlackChannels.value ?? []).forEach((channel) => onSlackChannelRead(channel.id, null))
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
const showLegacy = computed(() => isCollapsed("legacy-open") || legacyConversations.value.some((conversation) => conversation.ulid === selectedUlid.value))
const legacyUnread = computed(() => legacyConversations.value.reduce((sum, conversation) => sum + (conversation.unread_count || 0), 0))
const shownLegacyConversations = computed(() => legacyConversations.value.filter((conversation) => (filterText.value ? matchesFilter(conversationTitle(conversation)) : showLegacy.value)))
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

const isRecordChat = (conversation: { context_type?: string | null }) => !!conversation.context_type && !isTaskThread(conversation)
const messagingConversations = computed(() => store.conversations.filter((c) => !isTaskThread(c) && !isRecordChat(c)))
const legacyConversations = computed(() => store.conversations.filter(isRecordChat))
const isVerbration = (conversation: { type?: string; context_type?: string | null; name?: string | null } | null | undefined) => !!conversation && conversation.type === "group" && !conversation.context_type && !!conversation.name
const conversationRoute = (ulid: string) => (isVerbration(store.conversationByUlid(ulid)) ? "grp.chat.staff.groups.show" : "grp.chat.staff.show")
const taskThreadCount = computed(() => store.conversations.filter(isTaskThread).length)
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
            url: route(conversationRoute(ulid), ulid),
            preserveState: true,
            preserveScroll: true,
            props: (current) => ({ ...current, selected_ulid: ulid }),
        })
        return
    }
    router.visit(route(conversationRoute(ulid), ulid), {
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
    const conversation = await store.openWithUser(coworker.id, { openWindow: false })
    selectConversation(conversation.ulid)
}

const closeConversation = () => {
    store.fullViewUlid = null
    selectedUlid.value = null
    router.replace({
        url: route(isGroups.value ? "grp.chat.staff.groups.index" : "grp.chat.staff.index"),
        preserveState: true,
        preserveScroll: true,
        props: (current) => ({ ...current, selected_ulid: null }),
    })
}

const goHome = () => {
    store.fullViewUlid = null
    selectedUlid.value = null
    openSlackChannelId.value = null
    router.visit(homeRoute.value, { preserveState: true, preserveScroll: true })
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
    router.visit(channelShowRoute(channelId), { preserveState: true, preserveScroll: true })
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

const sidebarFilter = ref("")
const filterText = computed(() => sidebarFilter.value.trim().toLowerCase())
const matchesFilter = (label: string | null) => (label ?? "").toLowerCase().includes(filterText.value)

const layoutStore = useLayoutStore()
const isDesks = computed(() => (layoutStore.currentRoute ?? "").startsWith("grp.chat.staff.slack."))
const isGroups = computed(() => (layoutStore.currentRoute ?? "").startsWith("grp.chat.staff.groups."))
const isNothingSelected = computed(() => !selectedUlid.value && !showSlackChannel.value)
const homeRoute = computed(() => route(isDesks.value ? "grp.chat.staff.slack.index" : isGroups.value ? "grp.chat.staff.groups.index" : "grp.chat.staff.index"))
const tabConversations = computed(() => messagingConversations.value.filter((conversation) => isVerbration(conversation) === isGroups.value))
const isNewVerbrationOpen = ref(false)
const filterLabel = computed(() => (isDesks.value ? ctrans("Filter V·Desks") : isGroups.value ? ctrans("Filter Verbrations") : ctrans("Filter V·Ds")))

const isChannelRowShown = (channelId: number) => isDesks.value || isSelectedChannel(channelId) || unreadOf(channelId) > 0
const isConversationRowShown = (conversation: { ulid: string; unread_count: number }) => !isDesks.value || selectedUlid.value === conversation.ulid || conversation.unread_count > 0

const starredKeys = ref<string[]>(props.desk_starred ?? [])
const isStarred = (key: string) => starredKeys.value.includes(key)
const toggleStar = (key: string) => {
    const before = starredKeys.value
    starredKeys.value = isStarred(key) ? before.filter((starred) => starred !== key) : [...before, key]
    axios.patch(route("grp.models.profile.update"), { desk_starred: starredKeys.value }).catch(() => (starredKeys.value = before))
}

const starredChannels = computed(() => [...activeSlackChannels.value, ...archivedSlackChannels.value].filter((channel) => isStarred("slack-" + channel.id)))
const starredConversations = computed(() => tabConversations.value.filter((conversation) => isStarred("dm-" + conversation.ulid)))
const shownStarredChannels = computed(() => starredChannels.value.filter((channel) => (filterText.value ? matchesFilter(channel.name) : !isCollapsed("starred") || isSelectedChannel(channel.id) || unreadOf(channel.id) > 0)))
const shownStarredConversations = computed(() => starredConversations.value.filter((conversation) => (filterText.value ? matchesFilter(conversationTitle(conversation)) : !isCollapsed("starred") || selectedUlid.value === conversation.ulid || conversation.unread_count > 0)))
const starredPrivateChannels = computed(() => (isGroups.value ? privateChannels.value.filter((channel) => isStarred("slack-" + channel.id)) : []))
const shownStarredPrivateChannels = computed(() => starredPrivateChannels.value.filter((channel) => (filterText.value ? matchesFilter(channel.name) : !isCollapsed("starred") || isSelectedChannel(channel.id) || unreadOf(channel.id) > 0)))
const hasStarred = computed(() =>
    isDesks.value
        ? (filterText.value ? shownStarredChannels.value.length : starredChannels.value.length) > 0
        : (filterText.value ? shownStarredConversations.value.length + shownStarredPrivateChannels.value.length : starredConversations.value.length + starredPrivateChannels.value.length) > 0,
)

const shownActiveChannels = computed(() => activeSlackChannels.value.filter((channel) => !isStarred("slack-" + channel.id) && (filterText.value ? matchesFilter(channel.name) : isChannelRowShown(channel.id))))
const shownArchivedChannels = computed(() => archivedSlackChannels.value.filter((channel) => !isStarred("slack-" + channel.id) && (filterText.value ? matchesFilter(channel.name) : showArchived.value)))
const shownConversations = computed(() => tabConversations.value.filter((conversation) => !isStarred("dm-" + conversation.ulid) && (filterText.value ? matchesFilter(conversationTitle(conversation)) : isConversationRowShown(conversation))))
const shownPrivateChannels = computed(() => (isGroups.value ? privateChannels.value.filter((channel) => !isStarred("slack-" + channel.id) && (!filterText.value || matchesFilter(channel.name))) : []))
const hasNoFilterMatch = computed(() => !!filterText.value && !hasStarred.value && (isDesks.value ? !shownActiveChannels.value.length && !shownArchivedChannels.value.length : !shownConversations.value.length && !shownPrivateChannels.value.length && !shownDirectChannels.value.length))

const compactChannels = computed(() => isDesks.value
    ? [...activeSlackChannels.value, ...archivedSlackChannels.value.filter((channel) => isSelectedChannel(channel.id))].filter((channel) => !isStarred("slack-" + channel.id))
    : [])
const compactStarredChannels = computed(() => isDesks.value ? starredChannels.value : starredPrivateChannels.value)
const isDirectsTab = computed(() => !isDesks.value && !isGroups.value)
const shownDirectChannels = computed(() => (isDirectsTab.value ? knownDirectChannels.value.filter((channel) => !filterText.value || matchesFilter(channel.name)) : []))
const compactDirectChannels = computed(() => (isDirectsTab.value ? knownDirectChannels.value : []))
const compactPrivateChannels = computed(() => (isGroups.value ? privateChannels.value.filter((channel) => !isStarred("slack-" + channel.id)) : []))
const compactStarredConversations = computed(() => isDesks.value ? [] : starredConversations.value)
const compactConversations = computed(() => isDesks.value ? [] : tabConversations.value.filter((conversation) => !isStarred("dm-" + conversation.ulid)))

const sidebarOrder = computed(() => [
    ...shownStarredChannels.value.map((channel) => ({ channelId: channel.id })),
    ...shownStarredConversations.value.map((conversation) => ({ ulid: conversation.ulid })),
    ...shownStarredPrivateChannels.value.map((channel) => ({ channelId: channel.id })),
    ...shownPrivateChannels.value.map((channel) => ({ channelId: channel.id })),
    ...shownActiveChannels.value.map((channel) => ({ channelId: channel.id })),
    ...shownArchivedChannels.value.map((channel) => ({ channelId: channel.id })),
    ...shownConversations.value.map((conversation) => ({ ulid: conversation.ulid })),
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

const footerHeight = ref(0)
let footerObserver: ResizeObserver | null = null

onMounted(async () => {
    window.addEventListener('resize', handleResize)
    const footer = document.getElementById("grp_footer")
    if (footer) {
        footerObserver = new ResizeObserver(() => { footerHeight.value = footer.getBoundingClientRect().height })
        footerObserver.observe(footer)
    }
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
    footerObserver?.disconnect()
    window.removeEventListener('resize', handleResize)
    if (slackReconnectTimeout) clearTimeout(slackReconnectTimeout)
})
</script>

<template>
    <Head :title="title" />

    <div class="desk-palette relative flex min-h-[50vh] md:min-h-[70vh] h-[calc(100dvh_-_2.75rem_-_var(--desk-footer,0px))] lg:h-[calc(100dvh_-_2.5rem_-_var(--desk-footer,0px))] font-desk text-[15px]" :style="{ '--desk-footer': footerHeight + 'px' }">
        <div
            class="relative shrink-0 bg-[var(--desk-sidebar)] md:overflow-hidden md:transition-[width] md:duration-200 md:ease-in-out"
            :class="[selectedUlid || showSlackChannel ? 'hidden md:block' : 'w-full md:block', isSidebarCollapsed ? 'md:w-14' : 'md:w-64']">
            <nav
                class="flex h-full w-full md:w-64 flex-col bg-[var(--desk-sidebar)] text-[var(--desk-text)] md:transition-opacity md:duration-200"
                :class="isSidebarCollapsed && 'md:pointer-events-none md:opacity-0'"
                :inert="(isSidebarCollapsed && !isMobile) || undefined"
                :aria-label="ctrans('Verba')">
                <div class="min-h-0 flex-1 overflow-y-auto py-2">
                    <label class="mx-2 mb-2 flex h-7 items-center gap-x-2 rounded-md bg-white/10 px-2 text-[13px] text-[var(--desk-text)] focus-within:bg-white/20 focus-within:ring-1 focus-within:ring-white/60 hover:bg-white/20">
                        <FontAwesomeIcon icon="fal fa-filter" fixed-width aria-hidden="true" />
                        <InputText
                            ref="filterInput"
                            v-model="sidebarFilter"
                            unstyled
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-white shadow-none outline-none ring-0 placeholder:text-[var(--desk-text)] focus:border-0 focus:shadow-none focus:outline-none focus:ring-0"
                            :placeholder="filterLabel"
                            :aria-label="filterLabel"
                            @keydown.esc.stop="sidebarFilter = ''" />
                        <button v-if="sidebarFilter" type="button" class="flex h-5 w-5 items-center justify-center rounded-full bg-white text-[10px] text-[var(--desk-sidebar)]" :aria-label="ctrans('Clear filter')" @click="sidebarFilter = ''">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                        </button>
                        <button
                            v-if="isDesks && hasSlackUnread && !sidebarFilter"
                            type="button"
                            class="flex h-5 w-5 items-center justify-center rounded hover:bg-[var(--desk-hover)]"
                            :aria-label="ctrans('Mark all as read')"
                            v-tooltip="ctrans('Mark all as read') + ' (Shift+Esc)'"
                            @click.prevent="markAllSlackChannelsRead">
                            <FontAwesomeIcon icon="fal fa-check-double" fixed-width aria-hidden="true" />
                        </button>
                    </label>

                    <div v-if="!sidebarFilter" class="mb-3">
                        <DeskSidebarRow :label="ctrans('Home')" icon="fal fa-newspaper" :is-active="isNothingSelected" no-star @select="goHome" />
                    </div>
    
                    <p v-if="hasNoFilterMatch" class="px-4 py-2 text-[13px]">{{ isDesks ? ctrans("No V·Desks match “:filter”", { filter: sidebarFilter.trim() }) : (isGroups ? ctrans("No Verbrations match “:filter”", { filter: sidebarFilter.trim() }) : ctrans("No people match “:filter”", { filter: sidebarFilter.trim() })) }}</p>
    
                    <div v-if="slackReadError" class="px-4 py-1 text-[13px] text-red-300">{{ slackReadError }}</div>
    
                    <div v-if="isDesks && isSlackLoading" class="space-y-2 px-4 pt-3" aria-hidden="true">
                        <div class="h-3 w-20 rounded bg-white/10" />
                        <div class="h-5 rounded bg-white/10" />
                        <div class="h-5 rounded bg-white/10" />
                    </div>
                    <div v-else-if="isSlackIndexWithoutAccess" class="space-y-1 px-4 py-6 text-center text-[13px]">
                        <FontAwesomeIcon icon="fal fa-hashtag" class="text-2xl opacity-50" fixed-width aria-hidden="true" />
                        <p class="font-bold text-white">{{ ctrans("No Slack channels to show") }}</p>
                        <p>{{ ctrans("Public Slack channels show here once your aiku account is linked to your Slack account by the same email address.") }}</p>
                    </div>
    
                    <section v-if="hasStarred" class="mt-1 mb-3">
                        <div class="flex h-7 items-center pr-2">
                            <button type="button" class="flex flex-1 items-center gap-x-1 rounded px-2 py-0.5 text-left hover:bg-[var(--desk-hover)]" :aria-expanded="!isCollapsed('starred')" @click="toggleSection('starred')">
                                <FontAwesomeIcon icon="fal fa-caret-down" class="text-xs transition-transform" :class="isCollapsed('starred') && '-rotate-90'" fixed-width aria-hidden="true" />
                                <span>{{ ctrans("Starred") }}</span>
                            </button>
                        </div>
                        <DeskSidebarRow
                            v-if="isDesks"
                            v-for="channel in shownStarredChannels"
                            :key="'starred-slack-' + channel.id"
                            :label="channel.name ?? ''"
                            :href="route('grp.chat.staff.slack.show', channel.id)"
                            :icon="channel.is_archived ? 'fal fa-archive' : 'fal fa-hashtag'"
                            :unread="unreadOf(channel.id)"
                            :has-draft="draftChannelIds.has(channel.id)"
                            :is-active="isSelectedChannel(channel.id)"
                            :is-muted="channel.is_archived"
                            is-starred
                            @toggle-star="toggleStar('slack-' + channel.id)" />
                        <DeskSidebarRow
                            v-for="channel in shownStarredPrivateChannels"
                            :key="'starred-private-' + channel.id"
                            :label="channel.name ?? ''"
                            :href="route('grp.chat.staff.groups.slack.show', channel.id)"
                            icon="fal fa-lock"
                            :unread="unreadOf(channel.id)"
                            :is-active="isSelectedChannel(channel.id)"
                            is-starred
                            @toggle-star="toggleStar('slack-' + channel.id)" />
                        <DeskSidebarRow
                            v-if="!isDesks"
                            v-for="conversation in shownStarredConversations"
                            :key="'starred-' + conversation.ulid"
                            :label="conversationTitle(conversation)"
                            :avatar="conversationAvatar(conversation)"
                            :group-size="conversation.type === 'group' ? conversation.participants.length - 1 : undefined"
                            :unread="conversation.unread_count"
                            :is-active="selectedUlid === conversation.ulid"
                            is-starred
                            @select="selectConversation(conversation.ulid)"
                            @toggle-star="toggleStar('dm-' + conversation.ulid)" />
                    </section>
    
                    <section v-if="isDesks && hasSlack && (!filterText || shownActiveChannels.length)" class="mt-1">
                        <DeskSidebarRow
                            v-for="channel in shownActiveChannels"
                            :key="'slack-' + channel.id"
                            :label="channel.name ?? ''"
                            :href="route('grp.chat.staff.slack.show', channel.id)"
                            icon="fal fa-hashtag"
                            :unread="unreadOf(channel.id)"
                            :has-draft="draftChannelIds.has(channel.id)"
                            :is-active="isSelectedChannel(channel.id)"
                            :is-starred="false"
                            @toggle-star="toggleStar('slack-' + channel.id)" />
                    </section>
    
                    <section v-if="isDesks && hasSlack && (filterText ? shownArchivedChannels.length : archivedSlackChannels.length)" class="mt-3">
                        <button type="button" class="mx-2 flex h-7 w-[calc(100%-1rem)] items-center gap-x-1 rounded px-2 text-left hover:bg-[var(--desk-hover)]" v-if="!filterText" :aria-expanded="showArchived" @click="toggleSection('archived')">
                            <FontAwesomeIcon icon="fal fa-caret-down" class="text-xs transition-transform" :class="!showArchived && '-rotate-90'" fixed-width aria-hidden="true" />
                            <span>{{ ctrans("Archived (:count)", { count: String(archivedSlackChannels.length) }) }}</span>
                        </button>
                        <template v-if="shownArchivedChannels.length">
                            <DeskSidebarRow
                                v-for="channel in shownArchivedChannels"
                                :key="'slack-' + channel.id"
                                :label="channel.name ?? ''"
                                :href="route('grp.chat.staff.slack.show', channel.id)"
                                icon="fal fa-archive"
                                :unread="unreadOf(channel.id)"
                                :is-active="isSelectedChannel(channel.id)"
                                is-muted
                                :is-starred="false"
                                @toggle-star="toggleStar('slack-' + channel.id)" />
                        </template>
                    </section>
    
                    <section v-if="!isDesks && (!filterText || shownConversations.length || shownPrivateChannels.length || shownDirectChannels.length)" class="mt-1">
                        <DeskSidebarRow
                            v-for="channel in shownDirectChannels"
                            :key="'direct-' + channel.id"
                            :label="channel.name ?? ''"
                            :href="route('grp.chat.staff.direct.slack.show', channel.id)"
                            icon="fab fa-slack"
                            :unread="unreadOf(channel.id)"
                            :is-active="isSelectedChannel(channel.id)"
                            :is-starred="false" />
                        <DeskSidebarRow
                            v-for="channel in shownPrivateChannels"
                            :key="'private-' + channel.id"
                            :label="channel.name ?? ''"
                            :href="route('grp.chat.staff.groups.slack.show', channel.id)"
                            icon="fal fa-lock"
                            :unread="unreadOf(channel.id)"
                            :is-active="isSelectedChannel(channel.id)"
                            :is-muted="channel.is_archived"
                            :is-starred="false"
                            @toggle-star="toggleStar('slack-' + channel.id)" />
                        <DeskSidebarRow
                            v-for="conversation in shownConversations"
                            :key="conversation.ulid"
                            :label="conversationTitle(conversation)"
                            :avatar="conversationAvatar(conversation)"
                            :group-size="conversation.type === 'group' ? conversation.participants.length - 1 : undefined"
                            :unread="conversation.unread_count"
                            :is-active="selectedUlid === conversation.ulid"
                            :is-starred="false"
                            @select="selectConversation(conversation.ulid)"
                            @toggle-star="toggleStar('dm-' + conversation.ulid)" />
                        <p v-if="isGroups && !tabConversations.length && !privateChannels.length && !filterText" class="px-4 py-2 text-[13px]">{{ ctrans("Your Slack private channels will show here once they sync.") }}</p>
                        <button
                            v-if="!filterText"
                            type="button"
                            class="mx-2 flex h-7 w-[calc(100%-1rem)] items-center gap-x-2 rounded-md px-4 text-left hover:bg-[var(--desk-hover)]"
                            @click="isGroups ? (isNewVerbrationOpen = true) : (isJumpOpen = true)">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-white/10 text-sm">+</span>
                            <span class="truncate">{{ isGroups ? ctrans("New Verbration") : ctrans("Add coworkers") }}</span>
                        </button>
                    </section>
    
                    <section v-if="!isDesks && !isGroups && (filterText ? shownLegacyConversations.length : legacyConversations.length)" class="mt-3">
                        <button v-if="!filterText" type="button" class="mx-2 flex h-7 w-[calc(100%-1rem)] items-center gap-x-1 rounded px-2 text-left hover:bg-[var(--desk-hover)]" :aria-expanded="showLegacy" @click="toggleSection('legacy-open')">
                            <FontAwesomeIcon icon="fal fa-caret-down" class="text-xs transition-transform" :class="!showLegacy && '-rotate-90'" fixed-width aria-hidden="true" />
                            <span class="flex-1 truncate">{{ ctrans("Legacy chats (:count)", { count: String(legacyConversations.length) }) }}</span>
                            <span v-if="!showLegacy && legacyUnread" class="text-xs opacity-70">{{ legacyUnread }}</span>
                        </button>
                        <DeskSidebarRow
                            v-for="conversation in shownLegacyConversations"
                            :key="conversation.ulid"
                            :label="conversationTitle(conversation)"
                            :avatar="conversationAvatar(conversation)"
                            :group-size="conversation.participants.length - 1"
                            :unread="conversation.unread_count"
                            :is-active="selectedUlid === conversation.ulid"
                            is-muted
                            :is-starred="false"
                            @select="selectConversation(conversation.ulid)"
                            @toggle-star="toggleStar('dm-' + conversation.ulid)" />
                    </section>

                    <Link
                        v-if="!isDesks && !isGroups && taskThreadCount && !filterText"
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
    
            <nav
                class="absolute inset-y-0 left-0 hidden w-14 flex-col items-center bg-[var(--desk-sidebar)] text-[var(--desk-text)] transition-opacity duration-200 md:flex"
                :class="!isSidebarCollapsed && 'pointer-events-none opacity-0'"
                :inert="!isSidebarCollapsed || undefined"
                :aria-label="ctrans('Verba')">
                <div class="flex h-[50px] w-full shrink-0 items-center justify-center border-b border-[var(--desk-border)]">
                    <button
                        type="button"
                        class="flex h-[34px] w-[34px] items-center justify-center rounded-full bg-white text-[var(--desk-sidebar)] transition hover:opacity-90"
                        :aria-label="ctrans('New message')"
                        v-tooltip.right="ctrans('New message')"
                        @click="isJumpOpen = true">
                        <FontAwesomeIcon icon="fal fa-edit" fixed-width aria-hidden="true" />
                    </button>
                </div>
                <div class="flex min-h-0 w-full flex-1 flex-col gap-y-2 overflow-y-auto py-2">
                    <button type="button" class="mx-auto flex h-9 w-9 shrink-0 items-center justify-center rounded-md hover:bg-[var(--desk-hover)]" :aria-label="ctrans('Find a conversation…')" v-tooltip.right="ctrans('Find a conversation…')" @click="openFilter">
                        <FontAwesomeIcon icon="fal fa-filter" fixed-width aria-hidden="true" />
                    </button>
                    <DeskSidebarRow compact :label="ctrans('Home')" icon="fal fa-newspaper" :is-active="isNothingSelected" no-star @select="goHome" />
                    <template v-if="compactStarredChannels.length || compactStarredConversations.length">
                        <FontAwesomeIcon icon="fas fa-star" class="mx-auto text-[10px] opacity-60" fixed-width :aria-label="ctrans('Starred')" />
                        <DeskSidebarRow
                            v-for="channel in compactStarredChannels"
                            :key="'compact-starred-slack-' + channel.id"
                            compact
                            :label="channel.name ?? ''"
                            :href="channelShowRoute(channel.id)"
                            :icon="isPrivateChannel(channel.id) ? 'fal fa-lock' : 'fal fa-hashtag'"
                            :unread="unreadOf(channel.id)"
                            :has-draft="draftChannelIds.has(channel.id)"
                            :is-active="isSelectedChannel(channel.id)"
                            :is-muted="channel.is_archived" />
                        <DeskSidebarRow
                            v-for="conversation in compactStarredConversations"
                            :key="'compact-starred-' + conversation.ulid"
                            compact
                            :label="conversationTitle(conversation)"
                            :avatar="conversationAvatar(conversation)"
                            :group-size="conversation.type === 'group' ? conversation.participants.length - 1 : undefined"
                            :unread="conversation.unread_count"
                            :is-active="selectedUlid === conversation.ulid"
                            @select="selectConversation(conversation.ulid)" />
                        <div class="mx-3 h-px shrink-0 bg-[var(--desk-border)]" />
                    </template>
                    <DeskSidebarRow
                        v-for="channel in compactChannels"
                        :key="'compact-slack-' + channel.id"
                        compact
                        :label="channel.name ?? ''"
                        :href="route('grp.chat.staff.slack.show', channel.id)"
                        icon="fal fa-hashtag"
                        :unread="unreadOf(channel.id)"
                        :is-active="isSelectedChannel(channel.id)"
                        :is-muted="channel.is_archived" />
                    <DeskSidebarRow
                        v-for="channel in compactDirectChannels"
                        :key="'compact-direct-' + channel.id"
                        compact
                        :label="channel.name ?? ''"
                        :href="route('grp.chat.staff.direct.slack.show', channel.id)"
                        icon="fab fa-slack"
                        :unread="unreadOf(channel.id)"
                        :is-active="isSelectedChannel(channel.id)" />
                    <DeskSidebarRow
                        v-for="channel in compactPrivateChannels"
                        :key="'compact-private-' + channel.id"
                        compact
                        :label="channel.name ?? ''"
                        :href="route('grp.chat.staff.groups.slack.show', channel.id)"
                        icon="fal fa-lock"
                        :unread="unreadOf(channel.id)"
                        :is-active="isSelectedChannel(channel.id)" />
                    <DeskSidebarRow
                        v-for="conversation in compactConversations"
                        :key="'compact-' + conversation.ulid"
                        compact
                        :label="conversationTitle(conversation)"
                        :avatar="conversationAvatar(conversation)"
                        :group-size="conversation.type === 'group' ? conversation.participants.length - 1 : undefined"
                        :unread="conversation.unread_count"
                        :is-active="selectedUlid === conversation.ulid"
                        @select="selectConversation(conversation.ulid)" />
                </div>
                <button type="button" class="flex h-9 w-full shrink-0 items-center justify-center border-t border-[var(--desk-border)] hover:bg-[var(--desk-hover)]" :aria-label="ctrans('Keyboard shortcuts')" v-tooltip.right="ctrans('Keyboard shortcuts')" @click="isShortcutsOpen = true">
                    <FontAwesomeIcon icon="fal fa-keyboard" fixed-width aria-hidden="true" />
                </button>
            </nav>
        </div>

        <button
            type="button"
            class="absolute md:top-[calc(50dvh-33px)] lg:top-[calc(50dvh-2.5rem)] z-10 hidden aspect-square w-8 lg:w-5 -translate-x-1/2 -translate-y-1/2 items-center md:transition-[left] md:duration-200 md:ease-in-out justify-center rounded-full border border-gray-300 bg-[var(--desk-sidebar)] text-[var(--desk-text)] hover:text-white md:flex"
            :class="isSidebarCollapsed ? 'left-14' : 'left-64'"
            :aria-label="isSidebarCollapsed ? ctrans('Show the Verba sidebar') : ctrans('Hide the Verba sidebar')"
            v-tooltip="isSidebarCollapsed ? ctrans('Show the Verba sidebar') : ctrans('Hide the Verba sidebar')"
            :aria-expanded="!isSidebarCollapsed"
            @click="toggleSidebar">
            <FontAwesomeIcon icon="far fa-chevron-left" class="h-[10px] leading-none transition-transform duration-300" :class="isSidebarCollapsed && 'rotate-180'" fixed-width aria-hidden="true" />
        </button>

        <div class="flex-1 h-full min-w-0 border-t border-gray-200" :class="selectedUlid || showSlackChannel ? 'flex' : 'hidden md:flex'">
            <MessagingConversation
                v-if="selectedConversation"
                :conversation="selectedConversation"
                full-screen
                desk
                @back="closeConversation"
                @close="closeConversation" />
            <SlackChannelStream
                v-else-if="showSlackChannel && slack_channel?.is_private"
                :key="'slack-' + slack_channel.id"
                :channel="slack_channel"
                :messages="slack_messages ?? undefined"
                :channel-links="slackChannelLinks"
                :thread-ts="slack_thread_ts"
                :around-ts="slack_around_ts"
                :reply-ts="slack_reply_ts"
                :live-channel="null"
                @read="onSlackChannelRead(slack_channel.id, $event)"
                @back="router.visit(route(slack_channel.is_direct ? 'grp.chat.staff.index' : 'grp.chat.staff.groups.index'))" />
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
                <VerbaHome v-else @new-verb="isJumpOpen = true" @new-verbration="isNewVerbrationOpen = true" />
            </SlackMessageSearch>
            <VerbaHome v-else @new-verb="isJumpOpen = true" @new-verbration="isNewVerbrationOpen = true" />
        </div>

        <DeskJumpDialog v-model:visible="isJumpOpen" :targets="jumpTargets" @select="onJump" />
        <NewVerbrationDialog v-model:visible="isNewVerbrationOpen" @created="(url) => router.visit(url)" />

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

.desk-palette nav :deep(*) {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.25) var(--desk-sidebar);
}

.desk-palette nav :deep(*::-webkit-scrollbar) {
    width: 8px;
}

.desk-palette nav :deep(*::-webkit-scrollbar-track) {
    background: var(--desk-sidebar);
}

.desk-palette nav :deep(*::-webkit-scrollbar-thumb) {
    background: rgba(255, 255, 255, 0.25);
    border-radius: 4px;
}

.desk-palette nav :deep(*::-webkit-scrollbar-thumb:hover) {
    background: rgba(255, 255, 255, 0.4);
}
</style>
