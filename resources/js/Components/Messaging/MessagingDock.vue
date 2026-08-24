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
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faComments, faSearch, faUser, faChevronLeft } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Common/Components/Image.vue"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useTruncate } from "@/Composables/useTruncate"
import MessagingConversation from "@/Components/Messaging/MessagingConversation.vue"

library.add(faComments, faSearch, faUser, faChevronLeft)

const layout = inject("layout", layoutStructure)
const store = useStaffMessaging()
const desktopAnchor = computed(() => (layout.messagingSidebar.show ? "right-60" : "right-16"))
const isMobile = ref(window.innerWidth < 768)
const maxVisible = computed(() => (window.innerWidth < 1280 ? 2 : 3))
const onResize = () => {
    isMobile.value = window.innerWidth < 768
    store.maxVisible = window.innerWidth < 1280 ? 2 : 3
    const visible = store.openWindows.filter((w) => !w.minimised)
    while (visible.length > store.maxVisible) {
        const oldest = visible.shift()
        if (oldest) oldest.minimised = true
    }
}

const mobilePanelOpen = ref(false)

// ponytail: iOS moves fixed sheets when the keyboard opens; pin the sheet to the visual viewport and freeze the page behind it
const sheetStyle = ref<Record<string, string>>({})
const syncSheetToViewport = () => {
    const vv = window.visualViewport
    sheetStyle.value = vv
        ? { top: `${vv.offsetTop}px`, height: `${vv.height}px` }
        : { top: "0px", height: `${window.innerHeight}px` }
}
const hasMobileOverlay = computed(() => isMobile.value && (mobilePanelOpen.value || store.openWindowsVisible.length > 0))
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

const openConversationFromList = (ulid: string) => {
    store.openConversation(ulid)
}

const openCoworker = async (userId: number) => {
    await store.openWithUser(userId)
}

const myId = computed(() => usePage().props?.auth?.user?.id)
const others = (conversation: any) => conversation.participants.filter((p: any) => p.id !== myId.value)
const otherName = (conversation: any) => conversation.name || others(conversation).map((p: any) => p.name).join(", ")
const otherAvatar = (conversation: any) => others(conversation)[0]?.avatar ?? null
const otherOnline = (conversation: any) => isCoworkerOnline(others(conversation)[0]?.id)

// Draggable bubbles
const bubbleDrag = ref<{ ulid: string; startX: number; startY: number; origX: number; origY: number; moved: boolean } | null>(null)

const bubbleStyle = (ulid: string) => {
    const w = store.openWindows.find((w) => w.ulid === ulid)
    if (w?.x != null && w?.y != null) {
        return { left: `${w.x}px`, top: `${w.y}px`, right: "auto", bottom: "auto" }
    }
    return {}
}

const onBubblePointerDown = (event: PointerEvent, ulid: string) => {
    const target = event.currentTarget as HTMLElement
    const rect = target.getBoundingClientRect()
    bubbleDrag.value = { ulid, startX: event.clientX, startY: event.clientY, origX: rect.left, origY: rect.top, moved: false }
    target.setPointerCapture(event.pointerId)
}

const onBubblePointerMove = (event: PointerEvent) => {
    if (!bubbleDrag.value) return
    const dx = event.clientX - bubbleDrag.value.startX
    const dy = event.clientY - bubbleDrag.value.startY
    if (Math.abs(dx) + Math.abs(dy) > 5) bubbleDrag.value.moved = true
    if (!bubbleDrag.value.moved) return
    let x = bubbleDrag.value.origX + dx
    let y = bubbleDrag.value.origY + dy
    x = Math.max(4, Math.min(window.innerWidth - 52, x))
    y = Math.max(4, Math.min(window.innerHeight - 52, y))
    store.setBubblePosition(bubbleDrag.value.ulid, x, y)
}

const onBubblePointerUp = (ulid: string) => {
    const moved = bubbleDrag.value?.moved
    bubbleDrag.value = null
    if (!moved) store.minimiseConversation(ulid, false)
}

watch(
    () => store.totalUnread,
    (count) => {
        const base = document.title.replace(/^\(\d+\)\s/, "")
        document.title = count > 0 ? `(${count}) ${base}` : base
    }
)

onMounted(() => {
    store.maxVisible = maxVisible.value
    window.addEventListener("resize", onResize)
    window.visualViewport?.addEventListener("resize", syncSheetToViewport)
    window.visualViewport?.addEventListener("scroll", syncSheetToViewport)
    store.fetchConversations()
    fetchCoworkers("")
})

onUnmounted(() => {
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
                v-if="!mobilePanelOpen && !store.openWindowsVisible.length"
                class="fixed bottom-12 right-3 z-40 h-14 w-14 rounded-full bg-indigo-600 text-white shadow-lg flex items-center justify-center"
                @click="mobilePanelOpen = true"
            >
                <FontAwesomeIcon icon="fal fa-comments" class="text-xl" fixed-width aria-hidden="true" />
                <span v-if="store.totalUnread > 0" class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center text-xxs">{{ store.totalUnread }}</span>
            </button>
            <Teleport to="body">
            <div v-if="mobilePanelOpen" class="fixed left-0 right-0 z-[60] bg-white text-gray-900 flex flex-col" :style="sheetStyle">
                <div class="p-2 border-b border-gray-200 shrink-0 flex items-center gap-x-2">
                    <button class="p-3 -ml-1 text-gray-600" @click="mobilePanelOpen = false">
                        <FontAwesomeIcon icon="fal fa-chevron-left" fixed-width aria-hidden="true" />
                    </button>
                    <input v-model="search" type="text" :placeholder="trans('Search coworkers…')" autocapitalize="none" autocorrect="off" spellcheck="false"
                        class="w-full px-3 py-2.5 text-base border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500" />
                </div>
                <div class="flex-1 overflow-y-auto">
                    <button v-if="!search" v-for="conversation in store.conversations" :key="conversation.ulid"
                        class="w-full flex items-center gap-x-3 px-4 py-3 hover:bg-gray-50 text-left"
                        @click="openConversationFromList(conversation.ulid); mobilePanelOpen = false">
                        <div class="relative h-10 w-10 rounded-full overflow-hidden bg-gray-200 shrink-0">
                            <Image v-if="otherAvatar(conversation)" :src="otherAvatar(conversation)" :alt="otherName(conversation)" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-500" fixed-width aria-hidden="true" />
                            <span v-if="conversation.type === 'dm'" class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-1 ring-white" :class="otherOnline(conversation) ? 'bg-green-500' : 'bg-gray-400'" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-base truncate">{{ otherName(conversation) }}</div>
                            <div class="text-sm text-gray-500 truncate">{{ lastMessagePreview(conversation.last_message) }}</div>
                        </div>
                        <span v-if="conversation.unread_count > 0" class="bg-indigo-600 text-white rounded-full h-6 min-w-[1.5rem] px-1.5 flex items-center justify-center text-xs shrink-0">{{ conversation.unread_count }}</span>
                    </button>
                    <div class="px-4 pt-3 pb-1 text-sm text-gray-400">{{ trans('Coworkers') }}</div>
                    <button v-for="coworker in visibleCoworkers" :key="coworker.id"
                        class="w-full flex items-center gap-x-3 px-4 py-3 hover:bg-gray-50 text-left"
                        @click="openCoworker(coworker.id); mobilePanelOpen = false">
                        <div class="relative h-10 w-10 rounded-full overflow-hidden bg-gray-200 shrink-0">
                            <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-500" fixed-width aria-hidden="true" />
                            <span class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full ring-1 ring-white" :class="isCoworkerOnline(coworker.id) ? 'bg-green-500' : 'bg-gray-400'" />
                        </div>
                        <div class="text-base truncate">{{ coworker.name }}</div>
                    </button>
                    <button v-if="!showAllCoworkers && sortedCoworkers.length > 8" class="w-full text-left px-4 py-3 text-sm text-indigo-600" @click="showAllCoworkers = true">{{ trans('Show more') }}</button>
                </div>
            </div>
            </Teleport>
        </template>

        <Teleport to="body">
        <!-- Mobile: single full-screen sheet -->
        <template v-if="isMobile">
            <div v-if="store.openWindowsVisible[0]" class="fixed left-0 right-0 z-[60] bg-white" :style="sheetStyle">
                <MessagingConversation
                    :conversation="store.conversationByUlid(store.openWindowsVisible[0].ulid)!"
                    full-screen
                    @close="store.closeConversation(store.openWindowsVisible[0].ulid)"
                    @minimise="store.minimiseConversation(store.openWindowsVisible[0].ulid, true)"
                />
            </div>
        </template>

        <!-- Desktop: mini windows stacked right-to-left -->
        <template v-else>
            <div class="fixed bottom-6 z-[30] flex flex-row-reverse items-end gap-x-3 text-gray-900" :class="desktopAnchor">
                <div v-for="(w, index) in store.openWindowsVisible" :key="w.ulid" class="w-[28rem] h-[38rem] max-h-[calc(100vh-6rem)]">
                    <MessagingConversation
                        :conversation="store.conversationByUlid(w.ulid)!"
                        @close="store.closeConversation(w.ulid)"
                        @minimise="store.minimiseConversation(w.ulid, true)"
                    />
                </div>
            </div>
        </template>

        <!-- Minimised chat-head bubbles (draggable) -->
        <div
            v-for="w in store.openWindowsMinimised"
            :key="w.ulid"
            class="fixed z-[30] h-12 w-12 rounded-full shadow-lg cursor-pointer touch-none"
            :class="w.x == null ? desktopAnchor : ''"
            :style="{ ...bubbleStyle(w.ulid), bottom: w.x == null ? '4.5rem' : undefined }"
            @pointerdown="onBubblePointerDown($event, w.ulid)"
            @pointermove="onBubblePointerMove"
            @pointerup="onBubblePointerUp(w.ulid)"
        >
            <div class="relative h-12 w-12 rounded-full overflow-hidden bg-gray-200 border-2 border-white shadow">
                <Image v-if="otherAvatar(store.conversationByUlid(w.ulid))" :src="otherAvatar(store.conversationByUlid(w.ulid))" alt="" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-gray-500" fixed-width aria-hidden="true" />
                <span
                    v-if="(store.conversationByUlid(w.ulid)?.unread_count ?? 0) > 0"
                    class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs"
                >
                    {{ store.conversationByUlid(w.ulid)?.unread_count }}
                </span>
            </div>
        </div>
        </Teleport>
    </div>
</template>
