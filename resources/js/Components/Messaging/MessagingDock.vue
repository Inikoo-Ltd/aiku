<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, onMounted, onUnmounted, ref, watch } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { usePage } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faComments, faSearch, faUser, faChevronLeft, faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Common/Components/Image.vue"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, bubblesStorageKey, type StaffConversation, type StaffCoworker } from "@/Stores/staff-messaging"
import { useTruncate } from "@/Composables/useTruncate"
import MessagingConversation from "@/Components/Messaging/MessagingConversation.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"

library.add(faComments, faSearch, faUser, faChevronLeft, faTimes)

const layout = inject("layout", layoutStructure)
const store = useStaffMessaging()
const visibleConversationWindows = computed(() =>
    store.openWindowsVisible
        .map((openWindow) => ({ ulid: openWindow.ulid, conversation: store.conversationByUlid(openWindow.ulid) }))
        .filter((entry): entry is { ulid: string; conversation: StaffConversation } => !!entry.conversation)
)
// Two thresholds on purpose: the floating button only makes sense where the rail is hidden,
// while a conversation is a full-screen sheet on anything tablet-sized or smaller.
const isMobile = ref(window.innerWidth < 768)
const isCompact = ref(window.innerWidth < 1024)
const onResize = () => {
    isMobile.value = window.innerWidth < 768
    isCompact.value = window.innerWidth < 1024
}

const mobilePanelOpen = ref(false)

// ponytail: iOS moves fixed sheets when the keyboard opens; pin the sheet to the visual viewport and freeze the page behind it
const sheetStyle = ref<Record<string, string>>({})
const SHEET_TOP_GAP = 140
const SHEET_MIN_HEIGHT_FOR_GAP = 560
const syncSheetToViewport = () => {
    const vv = window.visualViewport
    const viewportTop = vv ? vv.offsetTop : 0
    const viewportHeight = vv ? vv.height : window.innerHeight
    const gap = viewportHeight > SHEET_MIN_HEIGHT_FOR_GAP ? SHEET_TOP_GAP : 0
    sheetStyle.value = { top: `${viewportTop + gap}px`, height: `${viewportHeight - gap}px` }
}
const hasMobileOverlay = computed(() => (isMobile.value && mobilePanelOpen.value) || (isCompact.value && visibleConversationWindows.value.length > 0))
watch(hasMobileOverlay, (open) => {
    document.documentElement.style.overflow = open ? "hidden" : ""
    document.body.style.overflow = open ? "hidden" : ""
    if (open) syncSheetToViewport()
})
const search = ref("")
const coworkers = ref<StaffCoworker[]>([])
const showAllCoworkers = ref(false)
let searchTimeout: ReturnType<typeof setTimeout> | null = null

const fetchCoworkers = async (q: string) => {
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: q ? { q } : {} })
    coworkers.value = data.data
}

watch(search, (value) => {
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchCoworkers(value), 300)
})

const sortedCoworkers = computed(() => {
    const online = coworkers.value.filter((c) => !!useLiveUsers().liveUsers[c.id])
    const offline = coworkers.value.filter((c) => !useLiveUsers().liveUsers[c.id])
    const closeOnly = coworkers.value.filter((c) => c.is_close)
    const list = search.value ? [...online, ...offline] : [...online.filter((c) => c.is_close), ...offline.filter((c) => c.is_close), ...online.filter((c) => !c.is_close), ...offline.filter((c) => !c.is_close)]
    return list
})

const visibleCoworkers = computed(() => (showAllCoworkers.value ? sortedCoworkers.value : sortedCoworkers.value.slice(0, 8)))

const isCoworkerOnline = (id: number) => !!useLiveUsers().liveUsers[id]

const lastMessagePreview = (text: string | null) => (text ? useTruncate(text, 40) : "")

const isChatOpenedFromPanel = ref(false)

const openConversationFromList = (ulid: string) => {
    store.openConversation(ulid)
}

const openCoworker = async (userId: number) => {
    await store.openWithUser(userId)
}

const openFromPanel = (open: () => unknown) => {
    isChatOpenedFromPanel.value = true
    mobilePanelOpen.value = false
    open()
}

const goBackFromChat = (ulid: string) => {
    store.minimiseConversation(ulid, true)
    if (isMobile.value && isChatOpenedFromPanel.value) mobilePanelOpen.value = true
    isChatOpenedFromPanel.value = false
}

const closeChat = (ulid: string) => {
    isChatOpenedFromPanel.value = false
    store.dismissWindow(ulid)
}

const myId = computed(() => usePage().props?.auth?.user?.id)
const others = (conversation: any) => (conversation?.participants ?? []).filter((p: any) => p.id !== myId.value)
const otherName = (conversation: any) => conversation?.name || others(conversation).map((p: any) => p.name).join(", ")
const otherAvatar = (conversation: any) => others(conversation)[0]?.avatar ?? null
const otherOnline = (conversation: any) => isCoworkerOnline(others(conversation)[0]?.id)

const unreadOf = (ulid: string) => store.conversationByUlid(ulid)?.unread_count ?? 0


const MAX_SINGLE_BUBBLES = 3
const OVERFLOW_LEAVE_GRACE_MS = 150
const CLUSTER_POSITION_KEY = "staff-chat-bubble-cluster"

const orderedBubbles = computed(() => [...store.openWindowsMinimised].reverse().sort((a, b) => Number(unreadOf(b.ulid) > 0) - Number(unreadOf(a.ulid) > 0)))
const singleBubbles = computed(() => orderedBubbles.value.slice(0, MAX_SINGLE_BUBBLES))
const overflowBubbles = computed(() => orderedBubbles.value.slice(MAX_SINGLE_BUBBLES))
const overflowUnread = computed(() => overflowBubbles.value.reduce((total, w) => total + unreadOf(w.ulid), 0))

const isHoveringOverflow = ref(false)
const isOverflowPinned = ref(false)
const overflowElement = ref<HTMLElement | null>(null)
const isOverflowListOpen = computed(() => isHoveringOverflow.value || isOverflowPinned.value)
let overflowLeaveTimer: ReturnType<typeof setTimeout> | null = null

const onOverflowEnter = () => {
    if (overflowLeaveTimer) clearTimeout(overflowLeaveTimer)
    overflowLeaveTimer = null
    isHoveringOverflow.value = true
}

const onOverflowLeave = () => {
    if (overflowLeaveTimer) clearTimeout(overflowLeaveTimer)
    overflowLeaveTimer = setTimeout(() => (isHoveringOverflow.value = false), OVERFLOW_LEAVE_GRACE_MS)
}

const closeOverflowList = () => {
    isHoveringOverflow.value = false
    isOverflowPinned.value = false
}

const onDocumentPointerDown = (event: PointerEvent) => {
    if (isOverflowPinned.value && !overflowElement.value?.contains(event.target as Node)) {
        closeOverflowList()
    }
}

watch(() => overflowBubbles.value.length, (count) => {
    if (!count) closeOverflowList()
})

const openBubble = (ulid: string) => {
    closeOverflowList()
    store.minimiseConversation(ulid, false)
}

const readClusterPosition = (): { x: number; y: number } | null => {
    try {
        return JSON.parse(localStorage.getItem(CLUSTER_POSITION_KEY) ?? "null")
    } catch {
        return null
    }
}

const clusterPosition = ref<{ x: number; y: number } | null>(readClusterPosition())
const clusterElement = ref<HTMLElement | null>(null)
const clusterDrag = ref<{ startX: number; startY: number; origX: number; origY: number; moved: boolean; bubbleUlid: string | null } | null>(null)

const isSidebarMinimised = computed(() => (isMobile.value ? !layout.messagingSidebar.show : Boolean(layout.messagingSidebar.micro)))

const isDockShown = computed(() => orderedBubbles.value.length > 0
    && isSidebarMinimised.value
    && !(isCompact.value && (mobilePanelOpen.value || visibleConversationWindows.value.length > 0)))
const hasDockButton = computed(() => isMobile.value && !mobilePanelOpen.value && !visibleConversationWindows.value.length)

const dockWidth = ref(0)
const DOCK_GAP_PX = 12

watch(clusterElement, (element, _previous, onCleanup) => {
    if (!element) {
        dockWidth.value = 0
        return
    }
    const observer = new ResizeObserver(() => (dockWidth.value = element.offsetWidth))
    observer.observe(element)
    onCleanup(() => observer.disconnect())
}, { flush: "post" })

const desktopAnchorRem = computed(() => (layout.messagingSidebar.show ? 15 : (layout.messagingSidebar.micro ? 2.5 : 4)))

const hideIfMakingRoom = (element: Element) => {
    const ulid = (element as HTMLElement).dataset.windowUlid
    if (!ulid || !store.instantlyMinimised.includes(ulid)) return

    ;(element as HTMLElement).style.display = "none"
    store.instantlyMinimised = store.instantlyMinimised.filter((minimisedUlid) => minimisedUlid !== ulid)
}

const windowsRowStyle = computed(() => {
    const besideDock = isDockShown.value && !clusterPosition.value && dockWidth.value > 0 ? dockWidth.value + DOCK_GAP_PX : 0

    return { right: `calc(${desktopAnchorRem.value}rem + ${besideDock}px)` }
})

const clusterStyle = computed(() => {
    if (clusterPosition.value) {
        return { left: `${clusterPosition.value.x}px`, top: `${clusterPosition.value.y}px`, right: "auto", bottom: "auto" }
    }
    if (isMobile.value) {
        return { bottom: "3rem" }
    }

    return {
        right: `calc(${desktopAnchorRem.value}rem + var(--chat-pane, 0px))`,
        bottom: isCompact.value ? "4.5rem" : "1.5rem",
    }
})

const onClusterPointerDown = (event: PointerEvent) => {
    if (!clusterElement.value) return
    const rect = clusterElement.value.getBoundingClientRect()
    const bubbleUlid = (event.target as HTMLElement | null)?.closest<HTMLElement>("[data-bubble-ulid]")?.dataset.bubbleUlid ?? null
    clusterDrag.value = { startX: event.clientX, startY: event.clientY, origX: rect.left, origY: rect.top, moved: false, bubbleUlid }
    clusterElement.value.setPointerCapture(event.pointerId)
}

const onClusterPointerMove = (event: PointerEvent) => {
    if (!clusterDrag.value || !clusterElement.value) return
    const dx = event.clientX - clusterDrag.value.startX
    const dy = event.clientY - clusterDrag.value.startY
    if (Math.abs(dx) + Math.abs(dy) > 5) clusterDrag.value.moved = true
    if (!clusterDrag.value.moved) return
    const { width, height } = clusterElement.value.getBoundingClientRect()
    clusterPosition.value = {
        x: Math.max(4, Math.min(window.innerWidth - width - 4, clusterDrag.value.origX + dx)),
        y: Math.max(4, Math.min(window.innerHeight - height - 4, clusterDrag.value.origY + dy)),
    }
}

const onClusterPointerUp = () => {
    const moved = clusterDrag.value?.moved
    const ulid = clusterDrag.value?.bubbleUlid
    clusterDrag.value = null
    if (moved) {
        try {
            localStorage.setItem(CLUSTER_POSITION_KEY, JSON.stringify(clusterPosition.value))
        } catch { }
        return
    }
    if (ulid) openBubble(ulid)
}

const bubbleUlidsKey = computed(() => store.openWindows.map((w) => `${w.ulid}:${w.minimised ? 1 : 0}`).join(","))
let areBubblesRestored = false

watch(bubbleUlidsKey, () => {
    if (areBubblesRestored) store.persistBubbles()
})

const onStorageChange = (event: StorageEvent) => {
    if (event.key === bubblesStorageKey()) store.restoreBubbles()
}

onMounted(async () => {
    document.addEventListener("pointerdown", onDocumentPointerDown, true)
    window.addEventListener("storage", onStorageChange)
    window.addEventListener("resize", onResize)
    window.visualViewport?.addEventListener("resize", syncSheetToViewport)
    window.visualViewport?.addEventListener("scroll", syncSheetToViewport)
    fetchCoworkers("")
    try {
        await store.fetchConversations()
    } finally {
        store.restoreBubbles({ reopenWindows: !isCompact.value })
        areBubblesRestored = true
    }
})

onUnmounted(() => {
    if (overflowLeaveTimer) clearTimeout(overflowLeaveTimer)
    document.removeEventListener("pointerdown", onDocumentPointerDown, true)
    window.removeEventListener("storage", onStorageChange)
    window.removeEventListener("resize", onResize)
    window.visualViewport?.removeEventListener("resize", syncSheetToViewport)
    window.visualViewport?.removeEventListener("scroll", syncSheetToViewport)
})
</script>

<template>
    <div>
        <!-- Mobile: floating button + full-screen panel sheet -->
        <template v-if="isMobile">
            <button
                v-if="!mobilePanelOpen && !visibleConversationWindows.length && !orderedBubbles.length"
                class="fixed bottom-12 right-3 z-40 h-14 w-14 rounded-full bg-[--app-accent] text-[--app-accent-text] shadow-lg flex items-center justify-center"
                @click="mobilePanelOpen = true"
            >
                <FontAwesomeIcon icon="fal fa-comments" class="text-xl" fixed-width aria-hidden="true" />
                <span v-if="store.totalUnread > 0" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center text-xxs">{{ store.totalUnread }}</span>
            </button>
            <Teleport to="body">
            <Transition enter-active-class="transition-transform duration-300 ease-out" enter-from-class="translate-y-full" leave-active-class="transition-transform duration-200 ease-in" leave-to-class="translate-y-full">
            <div v-if="mobilePanelOpen" class="fixed left-0 right-0 z-[60] bg-white text-gray-900 flex flex-col overflow-hidden rounded-t-2xl shadow-[0_-8px_24px_rgba(0,0,0,0.15)]" :style="sheetStyle">
                <div class="p-2 border-b border-gray-200 shrink-0 flex items-center gap-x-2">
                    <button class="p-3 -ml-1 text-gray-600" @click="mobilePanelOpen = false">
                        <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                    </button>
                    <input v-model="search" type="text" :placeholder="ctrans('Search coworkers…')" autocapitalize="none" autocorrect="off" spellcheck="false"
                        class="w-full px-3 py-2.5 text-base border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" />
                </div>
                <div class="flex-1 overflow-y-auto">
                    <button v-if="!search" v-for="conversation in store.conversations" :key="conversation.ulid"
                        class="w-full flex items-center gap-x-3 px-4 py-3 hover:bg-gray-50 text-left"
                        @click="openFromPanel(() => openConversationFromList(conversation.ulid))">
                        <div class="relative h-10 w-10 rounded-full overflow-hidden bg-gray-200 shrink-0">
                            <Image v-if="otherAvatar(conversation)" :src="otherAvatar(conversation)" :alt="otherName(conversation)" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-500" fixed-width aria-hidden="true" />
                            <span v-if="conversation.type === 'dm'" class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-1 ring-white" :class="otherOnline(conversation) ? 'bg-green-500' : 'bg-gray-400'" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-base truncate">{{ otherName(conversation) }}</div>
                            <div class="text-sm text-gray-500 truncate">{{ lastMessagePreview(conversation.last_message) }}</div>
                        </div>
                        <span v-if="conversation.unread_count > 0" class="bg-[--app-accent] text-[--app-accent-text] rounded-full h-6 min-w-[1.5rem] px-1.5 flex items-center justify-center text-xs shrink-0">{{ conversation.unread_count }}</span>
                    </button>
                    <div class="px-4 pt-3 pb-1 text-sm text-gray-400">{{ ctrans('Coworkers') }}</div>
                    <button v-for="coworker in visibleCoworkers" :key="coworker.id"
                        class="w-full flex items-center gap-x-3 px-4 py-3 hover:bg-gray-50 text-left"
                        @click="openFromPanel(() => openCoworker(coworker.id))">
                        <div class="relative h-10 w-10 rounded-full overflow-hidden bg-gray-200 shrink-0">
                            <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-500" fixed-width aria-hidden="true" />
                            <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-1 ring-white" :class="isCoworkerOnline(coworker.id) ? 'bg-green-500' : 'bg-gray-400'" />
                        </div>
                        <div class="text-base truncate">{{ coworker.name }}</div>
                    </button>
                    <button v-if="!showAllCoworkers && sortedCoworkers.length > 8" class="w-full text-left px-4 py-3 text-sm text-[--app-accent-strong]" @click="showAllCoworkers = true">{{ ctrans('Show more') }}</button>
                </div>
            </div>
            </Transition>
            </Teleport>
        </template>

        <Teleport to="body">
        <!-- Mobile + tablet: single full-screen sheet -->
        <template v-if="isCompact">
            <Transition enter-active-class="transition-transform duration-300 ease-out" enter-from-class="translate-y-full" leave-active-class="transition-transform duration-200 ease-in" leave-to-class="translate-y-full">
            <div v-if="visibleConversationWindows[0]" class="fixed left-0 right-0 z-[60] rounded-t-2xl bg-white shadow-[0_-8px_24px_rgba(0,0,0,0.15)] [&>div]:rounded-t-2xl [&>div>div:first-child]:rounded-t-2xl" :style="sheetStyle">
                <MessagingConversation
                    :conversation="visibleConversationWindows[0].conversation"
                    full-screen
                    @back="goBackFromChat(visibleConversationWindows[0].ulid)"
                    @close="closeChat(visibleConversationWindows[0].ulid)"
                    @minimise="store.minimiseConversation(visibleConversationWindows[0].ulid, true)"
                />
            </div>
            </Transition>
        </template>

        <!-- Desktop: mini windows stacked right-to-left -->
        <template v-else>
            <TransitionGroup
                tag="div"
                class="fixed bottom-6 z-[30] mr-[var(--chat-pane,0px)] flex flex-row-reverse items-end gap-x-3 text-gray-900 transition-[right] duration-200"
                :style="windowsRowStyle"
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-4 opacity-0"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="translate-y-4 opacity-0"
                move-class="transition-transform duration-200 ease-out"
                @before-leave="hideIfMakingRoom"
            >
                <div v-for="w in visibleConversationWindows" :key="w.ulid" :data-window-ulid="w.ulid" class="w-[22rem] lg:w-[28rem] h-[26rem] lg:h-[38rem] max-h-[calc(100dvh-6rem)] origin-bottom-right">
                    <MessagingConversation
                        :conversation="w.conversation"
                        @close="store.dismissWindow(w.ulid)"
                        @minimise="store.minimiseConversation(w.ulid, true)"
                    />
                </div>
            </TransitionGroup>
        </template>

        <!-- Minimised chat-head bubbles: up to three, the rest folded into one; the whole row drags -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-6 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-x-6 opacity-0"
        >
        <div
            v-if="isDockShown"
            ref="clusterElement"
            class="fixed z-[40] flex touch-none select-none items-center rounded-full border border-gray-200/80 bg-white/95 py-1 pl-1.5 shadow-[0_6px_24px_rgba(15,23,42,0.18)] backdrop-blur"
            :class="[!clusterPosition && isMobile && 'right-3', hasDockButton ? 'pr-1' : 'pr-1.5']"
            :style="clusterStyle"
            @pointerdown="onClusterPointerDown"
            @pointermove="onClusterPointerMove"
            @pointerup="onClusterPointerUp"
        >
            <div
                v-for="(w, bubbleIndex) in singleBubbles"
                :key="w.ulid"
                :data-bubble-ulid="w.ulid"
                v-tooltip="{ content: otherName(store.conversationByUlid(w.ulid)), placement: 'top' }"
                class="group/bubble relative shrink-0 cursor-pointer rounded-full transition-transform duration-200 hover:z-10 hover:-translate-y-1"
                :class="bubbleIndex > 0 && '-ml-2'"
            >
                <div class="flex rounded-full ring-2" :class="unreadOf(w.ulid) ? 'ring-red-500' : 'ring-white'">
                    <span v-if="store.conversationByUlid(w.ulid)?.type === 'group'" class="flex h-10 w-10 items-center justify-center rounded-full bg-[--app-accent-soft] text-[--app-accent-strong]">
                        <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                    </span>
                    <TicketUserAvatar v-else :name="otherName(store.conversationByUlid(w.ulid))" :avatar="otherAvatar(store.conversationByUlid(w.ulid))" size="xl" />
                </div>
                <span
                    v-if="unreadOf(w.ulid)"
                    class="pointer-events-none absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white"
                >
                    {{ unreadOf(w.ulid) > 99 ? "99+" : unreadOf(w.ulid) }}
                </span>
                <button
                    type="button"
                    :aria-label="ctrans('Dismiss')"
                    class="absolute -left-1 -top-1 hidden h-[18px] w-[18px] items-center justify-center rounded-full bg-gray-800 text-[9px] text-white ring-2 ring-white group-hover/bubble:flex"
                    @pointerdown.stop
                    @pointerup.stop
                    @click.stop="store.dismissWindow(w.ulid)"
                >
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div
                v-if="overflowBubbles.length"
                ref="overflowElement"
                class="relative -ml-2 shrink-0"
                @mouseenter="onOverflowEnter"
                @mouseleave="onOverflowLeave"
            >
                <button
                    type="button"
                    :aria-label="ctrans(':count more chats', { count: String(overflowBubbles.length) })"
                    :aria-expanded="isOverflowListOpen"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700 ring-2 transition duration-200 hover:-translate-y-1 hover:bg-gray-200"
                    :class="[overflowUnread ? 'ring-red-500' : 'ring-white', isOverflowListOpen && '!bg-gray-200']"
                    @pointerdown.stop
                    @pointerup.stop
                    @click.stop="isOverflowPinned ? closeOverflowList() : (isOverflowPinned = true)"
                >
                    +{{ overflowBubbles.length }}
                </button>
                <span
                    v-if="overflowUnread"
                    class="pointer-events-none absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white"
                >
                    {{ overflowUnread > 99 ? "99+" : overflowUnread }}
                </span>

                <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="translate-y-1 opacity-0" leave-active-class="transition duration-100 ease-in" leave-to-class="translate-y-1 opacity-0">
                    <div
                        v-if="isOverflowListOpen"
                        class="absolute bottom-full right-0 w-72 pb-3"
                        @pointerdown.stop
                        @pointerup.stop
                    >
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white text-gray-900 shadow-xl">
                        <p class="border-b border-gray-100 px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("More chats") }}</p>
                        <ul class="max-h-80 divide-y divide-gray-50 overflow-y-auto">
                            <li v-for="w in overflowBubbles" :key="w.ulid" class="group/row flex items-center">
                                <button type="button" class="flex min-w-0 flex-1 items-center gap-x-2.5 px-3 py-2 text-left transition duration-200 hover:bg-gray-50 active:!bg-gray-100" @click="openBubble(w.ulid)">
                                    <span class="flex shrink-0 rounded-full" :class="unreadOf(w.ulid) ? 'ring-2 ring-red-500' : ''">
                                        <span v-if="store.conversationByUlid(w.ulid)?.type === 'group'" class="flex h-9 w-9 items-center justify-center rounded-full bg-[--app-accent-soft] text-[--app-accent-strong]">
                                            <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                                        </span>
                                        <TicketUserAvatar v-else :name="otherName(store.conversationByUlid(w.ulid))" :avatar="otherAvatar(store.conversationByUlid(w.ulid))" size="lg" />
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm" :class="unreadOf(w.ulid) ? 'font-semibold text-gray-900' : 'text-gray-700'">{{ otherName(store.conversationByUlid(w.ulid)) }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ lastMessagePreview(store.conversationByUlid(w.ulid)?.last_message ?? null) }}</span>
                                    </span>
                                    <span v-if="unreadOf(w.ulid)" class="flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-red-500 px-1 text-xxs font-semibold text-white">{{ unreadOf(w.ulid) }}</span>
                                </button>
                                <button
                                    type="button"
                                    v-tooltip="ctrans('Dismiss')"
                                    :aria-label="ctrans('Dismiss')"
                                    class="mr-1.5 shrink-0 rounded p-1.5 text-gray-300 opacity-0 transition duration-200 hover:bg-gray-100 hover:text-gray-600 focus:opacity-100 group-hover/row:opacity-100"
                                    @click.stop="store.dismissWindow(w.ulid)"
                                >
                                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                                </button>
                            </li>
                        </ul>
                    </div>
                    </div>
                </Transition>
            </div>

            <template v-if="hasDockButton">
                <span class="mx-1.5 h-7 w-px shrink-0 bg-gray-200" aria-hidden="true" />
                <button
                    type="button"
                    :aria-label="ctrans('Messages')"
                    class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[--app-accent] text-[--app-accent-text] shadow-md transition duration-200 hover:bg-[--app-accent-strong] active:!bg-[--app-accent-deep]"
                    @pointerdown.stop
                    @pointerup.stop
                    @click.stop="mobilePanelOpen = true"
                >
                    <FontAwesomeIcon icon="fal fa-comments" class="text-lg" fixed-width aria-hidden="true" />
                    <span v-if="store.totalUnread > 0" class="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white">{{ store.totalUnread > 99 ? "99+" : store.totalUnread }}</span>
                </button>
            </template>
        </div>
        </Transition>
        </Teleport>
    </div>
</template>
