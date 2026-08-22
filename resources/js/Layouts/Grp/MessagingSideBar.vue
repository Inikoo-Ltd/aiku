<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, onMounted, onUnmounted, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faSearch, faUser, faComments, faStar as faStarRegular, faPlus, faTimes, faComment, faGopuram, faHomeAlt, faHeart } from "@fal"
import { faStar as faStarSolid } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import RailControls from "@/Layouts/Grp/RailControls.vue"
import FooterMessage from "@/Components/Footer/FooterMessage.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useTruncate } from "@/Composables/useTruncate"

library.add(faChevronLeft, faSearch, faUser, faComments, faStarRegular, faStarSolid, faPlus, faTimes, faComment, faGopuram, faHomeAlt, faHeart)

const layout = inject("layout", layoutStructure)
const store = useStaffMessaging()

const handleToggle = () => {
    if (typeof window !== "undefined") {
        localStorage.setItem("messagingSideBar", (!layout.messagingSidebar.show).toString())
    }
    layout.messagingSidebar.show = !layout.messagingSidebar.show
}

const searchInput = ref<HTMLInputElement | null>(null)

const search = ref("")
const coworkers = ref<StaffCoworker[]>([])
const searchResults = ref<StaffCoworker[]>([])
const plusOpened = ref(false)
const teamSection = ref<HTMLElement | null>(null)
let searchTimeout: ReturnType<typeof setTimeout> | null = null
let refreshInterval: ReturnType<typeof setInterval> | null = null

const fetchCoworkers = async (q: string) => {
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: q ? { q } : {} })
    coworkers.value = data.data
}

const fetchSearchResults = async (q: string) => {
    if (!q) {
        searchResults.value = []
        return
    }
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q } })
    searchResults.value = data.data
}

// ponytail: "+" search hits the server (needs offline matches too); the auto filter (>10 online) just narrows the already-fetched list client side
const onSearchInput = () => {
    if (!plusOpened.value) return
    if (searchTimeout) clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => fetchSearchResults(search.value), 300)
}

const openPlusSearch = () => {
    plusOpened.value = true
    setTimeout(() => searchInput.value?.focus(), 50)
}

const closeSearch = () => {
    plusOpened.value = false
    search.value = ""
    searchResults.value = []
}

const isOnline = (id: number) => !!useLiveUsers().liveUsers[id]

const byName = (a: StaffCoworker, b: StaffCoworker) => a.name.localeCompare(b.name)

const teamCoworkers = computed(() =>
    coworkers.value.filter((c) => c.in_team).sort((a, b) => (isOnline(a.id) === isOnline(b.id) ? 0 : isOnline(a.id) ? -1 : 1) || byName(a, b))
)
const teamOnlineCoworkers = computed(() => teamCoworkers.value.filter((c) => isOnline(c.id)))
const onlineCoworkers = computed(() =>
    coworkers.value.filter((c) => isOnline(c.id) && !c.in_team).sort(byName)
)

const allOnlineCount = computed(() => useLiveUsers().count)
const orgOnlineCount = computed(() => coworkers.value.filter((c) => c.is_close && isOnline(c.id)).length)
const teamOnlineCount = computed(() => teamOnlineCoworkers.value.length)

const showInput = computed(() => plusOpened.value)
const searchPlaceholder = computed(() => trans('Find a coworker…'))
const query = computed(() => search.value.trim().toLowerCase())
const clientFilterActive = computed(() => !plusOpened.value && query.value.length > 0)
const filteredTeamCoworkers = computed(() =>
    clientFilterActive.value ? teamCoworkers.value.filter((c) => c.name.toLowerCase().includes(query.value)) : teamCoworkers.value
)
const showSearchResults = computed(() => plusOpened.value && query.value.length > 0)
const sortedSearchResults = computed(() =>
    [...searchResults.value].sort((a, b) => (isOnline(a.id) === isOnline(b.id) ? 0 : isOnline(a.id) ? -1 : 1) || a.name.localeCompare(b.name))
)

const openSearchResult = (coworker: StaffCoworker) => {
    store.openWithUser(coworker.id)
    closeSearch()
}

const toggleTeam = async (coworker: StaffCoworker, event: Event) => {
    event.stopPropagation()
    const { data } = await axios.post(route("grp.chat.staff.team.toggle"), { user_id: coworker.id })
    coworker.in_team = data.in_team
}

const focusTeamSection = () => {
    if (!layout.messagingSidebar.show) handleToggle()
    setTimeout(() => teamSection.value?.scrollIntoView({ behavior: "smooth", block: "start" }), 50)
}

const myId = computed(() => layout.user?.id)
const conversationTitle = (conversation: any) =>
    conversation.name || conversation.participants.filter((p: any) => p.id !== myId.value).map((p: any) => p.name).join(", ")
const conversationOtherId = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.id ?? null
const conversationAvatar = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.avatar ?? null

const teamOfflineCoworkers = computed(() => teamCoworkers.value.filter((c) => !isOnline(c.id)))

const conversationUserIds = computed(() => new Set(
    store.conversations.filter((c) => c.type === "dm").map((c) => conversationOtherId(c))
))

const railOfflineWithConversation = computed(() => {
    return coworkers.value.filter((c) => !c.in_team && !isOnline(c.id) && conversationUserIds.value.has(c.id))
})

const railOnlineWithConversation = computed(() => {
    return onlineCoworkers.value.filter((c) => conversationUserIds.value.has(c.id))
})

const railCandidates = computed(() => [
    ...teamOnlineCoworkers.value.map((coworker) => ({ coworker, online: true })),
    ...teamOfflineCoworkers.value.map((coworker) => ({ coworker, online: false })),
    ...railOnlineWithConversation.value.map((coworker) => ({ coworker, online: true })),
    ...railOfflineWithConversation.value.map((coworker) => ({ coworker, online: false })),
])

const sortedConversations = computed(() => {
    return [...store.conversations].sort((a, b) => {
        if (a.unread_count !== b.unread_count) {
            return b.unread_count - a.unread_count
        }
        return conversationTitle(a).localeCompare(conversationTitle(b))
    })
})

const railOrdered = computed(() => {
    const withUnread = railCandidates.value
        .filter((item) => unreadForUser(item.coworker.id) > 0)
        .sort((a, b) => unreadForUser(b.coworker.id) - unreadForUser(a.coworker.id))
    const withUnreadIds = new Set(withUnread.map((item) => item.coworker.id))
    const rest = railCandidates.value.filter((item) => !withUnreadIds.has(item.coworker.id))
    return [...withUnread, ...rest]
})

const RAIL_AVATAR_LIMIT = 8
const railOverflowCount = computed(() => Math.max(0, railOrdered.value.length - RAIL_AVATAR_LIMIT))
const railVisible = computed(() => railOrdered.value.slice(0, railOverflowCount.value > 0 ? RAIL_AVATAR_LIMIT - 1 : RAIL_AVATAR_LIMIT))

const unreadForUser = (userId: number) => {
    const conversation = store.conversations.find((c) => c.type === "dm" && conversationOtherId(c) === userId)
    return conversation?.unread_count ?? 0
}

const openUser = (userId: number) => {
    const conversation = store.conversations.find((c) => c.type === "dm" && conversationOtherId(c) === userId)
    if (conversation) {
        store.openConversation(conversation.ulid)
    } else {
        store.openWithUser(userId)
    }
}

onMounted(() => {
    if (localStorage.getItem("messagingSideBar")) {
        layout.messagingSidebar.show = JSON.parse(localStorage.getItem("messagingSideBar") ?? "false")
    }
    fetchCoworkers("")
    store.fetchConversations()
    refreshInterval = setInterval(() => fetchCoworkers(search.value), 60000)
})

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval)
    if (searchTimeout) clearTimeout(searchTimeout)
})
</script>

<template>
    <div
        class="hidden md:flex md:flex-col fixed inset-y-0 right-0 h-full bg-[#282a36] border-l border-[#44475a] z-[22] transition-all duration-300 ease-in-out"
        :class="[
            layout.messagingSidebar.show ? 'md:w-56' : 'md:w-12',
        ]"
        id="messagingSidebar">
        <!-- Toggle: collapse-expand MessagingSideBar -->
        <div
            @click="handleToggle"
            class="absolute z-10 left-0 top-2/4 -translate-y-full -translate-x-1/2 w-8 lg:w-5 aspect-square border border-[#6272a4] rounded-full bg-[#44475a] flex justify-center items-center cursor-pointer"
            :title="layout.messagingSidebar.show ? 'Collapse the bar' : 'Expand the bar'">
            <FontAwesomeIcon
                icon="far fa-chevron-left"
                class="h-[10px] leading-none transition-all duration-300 ease-in-out text-[#f8f8f2]"
                aria-hidden="true"
                :class="layout.messagingSidebar.show ? 'rotate-180' : ''" />
        </div>

        <RailControls />

        <!-- Online counters -->
        <!-- COLLAPSED: three counters -->
        <div v-if="!layout.messagingSidebar.show" class="flex flex-col items-center gap-y-1 pt-2 pb-1 border-b border-[#44475a]">
            <div class="flex items-center gap-x-1" v-tooltip="trans('Everyone online')">
                <FontAwesomeIcon icon="fal fa-gopuram" class="text-[#50fa7b] text-xs" fixed-width aria-hidden="true" />
                <span class="text-xxs tabular-nums text-[#f8f8f2]">{{ allOnlineCount }}</span>
            </div>
            <div class="flex items-center gap-x-1" v-tooltip="trans('Online in my organisation')">
                <FontAwesomeIcon icon="fal fa-home-alt" class="text-[#8be9fd] text-xs" fixed-width aria-hidden="true" />
                <span class="text-xxs tabular-nums text-[#f8f8f2]">{{ orgOnlineCount }}</span>
            </div>
            <div class="flex items-center gap-x-1 cursor-pointer" v-tooltip="trans('Online in my team')" @click="focusTeamSection">
                <FontAwesomeIcon icon="fal fa-heart" class="text-[#bd93f9] text-xs" fixed-width aria-hidden="true" />
                <span class="text-xxs tabular-nums text-[#f8f8f2]">{{ teamOnlineCount }}</span>
            </div>
        </div>

        <!-- EXPANDED: three counters -->
        <div v-else class="px-3 py-2 flex items-center gap-x-3 border-b border-[#44475a] text-xxs cursor-pointer" @click="focusTeamSection">
            <span class="flex items-center gap-x-1" v-tooltip="trans('Everyone online')"><FontAwesomeIcon icon="fal fa-gopuram" class="text-[#50fa7b] text-xs" fixed-width aria-hidden="true" /><span class="tabular-nums">{{ allOnlineCount }}</span><span class="text-[#bfc4d8]">{{ trans('all') }}</span></span>
            <span class="flex items-center gap-x-1" v-tooltip="trans('Online in my organisation')"><FontAwesomeIcon icon="fal fa-home-alt" class="text-[#8be9fd] text-xs" fixed-width aria-hidden="true" /><span class="tabular-nums">{{ orgOnlineCount }}</span><span class="text-[#bfc4d8]">{{ trans('org') }}</span></span>
            <span class="flex items-center gap-x-1 cursor-pointer" v-tooltip="trans('Online in my team')" @click="focusTeamSection"><FontAwesomeIcon icon="fal fa-heart" class="text-[#bd93f9] text-xs" fixed-width aria-hidden="true" /><span class="tabular-nums">{{ teamOnlineCount }}</span><span class="text-[#bfc4d8]">{{ trans('team') }}</span></span>
        </div>

        <!-- Unread chats chip -->
        <div v-if="store.totalUnread > 0" class="border-b border-[#44475a] py-2 flex justify-center items-center" :class="layout.messagingSidebar.show ? 'gap-2' : ''">
            <div
                class="bg-[#ff5555] text-white rounded-md min-w-[2rem] h-7 px-2 flex items-center justify-center text-xs font-medium tabular-nums cursor-pointer"
                v-tooltip="trans('Unread chats')"
                @click="!layout.messagingSidebar.show && handleToggle()">
                {{ store.totalUnread > 99 ? '99+' : store.totalUnread }}
            </div>
            <span v-if="layout.messagingSidebar.show" class="text-[#f8f8f2] text-xs">{{ trans('unread') }}</span>
        </div>

        <!-- COLLAPSED: avatar rail -->
        <div v-if="!layout.messagingSidebar.show" class="flex-1 flex flex-col items-center gap-y-3 pt-4 overflow-y-auto custom-hide-scrollbar">
            <button
                v-for="item in railVisible"
                :key="'rail-' + item.coworker.id"
                class="relative h-7 w-7 rounded-full overflow-hidden bg-[#44475a] shrink-0"
                :class="item.online ? '' : 'opacity-40'"
                v-tooltip="item.coworker.name"
                @click="openUser(item.coworker.id)">
                <Image v-if="item.coworker.avatar" :src="item.coworker.avatar" :alt="item.coworker.name" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full ring-1 ring-[#282a36]" :class="item.online ? 'bg-[#50fa7b]' : 'bg-[#6272a4]'" />
                <span v-if="unreadForUser(item.coworker.id) > 0" class="absolute -top-1 -right-1 bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ unreadForUser(item.coworker.id) }}</span>
            </button>
            <button
                v-if="railOverflowCount > 0"
                class="relative h-7 w-7 rounded-full bg-[#44475a] shrink-0 flex items-center justify-center text-xxs text-[#f8f8f2]"
                v-tooltip="trans('Show all')"
                @click="handleToggle">
                +{{ railOverflowCount }}
            </button>
        </div>

        <!-- EXPANDED -->
        <div v-else class="flex-1 flex flex-col overflow-y-auto custom-hide-scrollbar pt-4 pb-3">
            <div class="px-3 py-2 flex items-center justify-end border-b border-[#44475a] text-xxs">
                <button v-if="!plusOpened" class="shrink-0 text-[#bd93f9] hover:text-[#f8f8f2]" @click="openPlusSearch" v-tooltip="trans('New message')">
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div v-if="showInput" class="p-3 pb-2 shrink-0">
                <div class="relative">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2 top-1/2 -translate-y-1/2 text-[#6272a4] text-xs" fixed-width aria-hidden="true" />
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="w-full pl-7 pr-7 py-1.5 text-xs bg-[#21222c] text-[#f8f8f2] placeholder-[#6272a4] border border-[#44475a] rounded-md focus:outline-none focus:ring-1 focus:ring-[#bd93f9]"
                        @input="onSearchInput"
                        @keydown.esc="closeSearch" />
                    <button v-if="plusOpened || search" class="absolute right-2 top-1/2 -translate-y-1/2 text-[#6272a4] hover:text-[#f8f8f2]" @click="closeSearch">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>

            <!-- Search results (replaces sections while "+" search has a query) -->
            <template v-if="showSearchResults">
                <div
                    v-for="coworker in sortedSearchResults"
                    :key="'search-' + coworker.id"
                    role="button" tabindex="0"
                    class="group w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[#44475a] text-left cursor-pointer"
                    :class="isOnline(coworker.id) ? '' : 'opacity-40'"
                    @click="openSearchResult(coworker)">
                    <div class="relative h-6 w-6 rounded-full overflow-hidden bg-[#44475a] shrink-0">
                        <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                        <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[#282a36]" :class="isOnline(coworker.id) ? 'bg-[#50fa7b]' : 'bg-[#6272a4]'" />
                    </div>
                    <span class="flex-1 text-xs truncate text-[#f8f8f2]">{{ coworker.name }}</span>
                    <span role="button" tabindex="0" class="shrink-0" @click="toggleTeam(coworker, $event)" v-tooltip="coworker.in_team ? trans('In my team') : trans('Add to my team')">
                        <FontAwesomeIcon :icon="coworker.in_team ? 'fas fa-star' : 'fal fa-star'" :class="coworker.in_team ? 'text-[#f1fa8c]' : 'text-[#6272a4]'" fixed-width aria-hidden="true" />
                    </span>
                </div>
            </template>

            <template v-else>
            <div ref="teamSection" class="px-3 pt-2 pb-1 text-xs text-[#6272a4]">{{ trans('My team') }} ({{ teamOnlineCount }} {{ trans('online') }})</div>
            <div
                v-for="coworker in filteredTeamCoworkers"
                :key="'exp-team-' + coworker.id"
                role="button" tabindex="0"
                class="group w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[#44475a] text-left cursor-pointer"
                :class="isOnline(coworker.id) ? '' : 'opacity-40'"
                @click="openUser(coworker.id)">
                <div class="relative h-6 w-6 rounded-full overflow-hidden bg-[#44475a] shrink-0">
                    <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                    <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                    <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[#282a36]" :class="isOnline(coworker.id) ? 'bg-[#50fa7b]' : 'bg-[#6272a4]'" />
                </div>
                <span class="flex-1 text-xs truncate text-[#f8f8f2]">{{ coworker.name }}</span>
                <span v-if="unreadForUser(coworker.id) > 0" class="bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ unreadForUser(coworker.id) }}</span>
                <span role="button" tabindex="0" class="shrink-0 opacity-0 group-hover:opacity-100" @click.stop="store.openWithUser(coworker.id)" v-tooltip="trans('Message')">
                    <FontAwesomeIcon icon="fal fa-comment" class="text-[#6272a4] hover:text-[#f8f8f2]" fixed-width aria-hidden="true" />
                </span>
                <span role="button" tabindex="0" class="shrink-0 opacity-100 group-hover:opacity-100" @click="toggleTeam(coworker, $event)" v-tooltip="trans('In my team')">
                    <FontAwesomeIcon :icon="coworker.in_team ? 'fas fa-star' : 'fal fa-star'" :class="coworker.in_team ? 'text-[#f1fa8c]' : 'text-[#6272a4]'" fixed-width aria-hidden="true" />
                </span>
            </div>

            <div class="border-t border-[#44475a] mt-2">
                <div class="px-3 pt-2 pb-1 text-xs text-[#6272a4]">{{ trans('Conversations') }}</div>
                <button
                    v-for="conversation in sortedConversations"
                    :key="'conv-' + conversation.ulid"
                    class="w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[#44475a] text-left"
                    @click="store.openConversation(conversation.ulid)">
                    <div v-if="conversation.type === 'group'" class="h-6 w-6 rounded-full bg-[#44475a] flex items-center justify-center shrink-0">
                        <FontAwesomeIcon icon="fal fa-comments" class="text-[#bd93f9]" fixed-width aria-hidden="true" />
                    </div>
                    <div v-else class="relative h-6 w-6 rounded-full overflow-hidden bg-[#44475a] shrink-0">
                        <Image v-if="conversationAvatar(conversation)" :src="conversationAvatar(conversation)" :alt="conversationTitle(conversation)" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                        <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[#282a36]" :class="isOnline(conversationOtherId(conversation)) ? 'bg-[#50fa7b]' : 'bg-[#6272a4]'" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs truncate text-[#f8f8f2]">{{ conversationTitle(conversation) }}</div>
                        <div class="text-xxs text-[#6272a4] truncate">{{ useTruncate(conversation.last_message ?? '', 26) }}</div>
                    </div>
                    <span v-if="conversation.unread_count > 0" class="bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ conversation.unread_count }}</span>
                </button>
            </div>
            </template>
        </div>

        <!-- Bottom-pinned: customer chats trigger -->
        <div class="mt-auto shrink-0 border-t border-[#44475a] pt-2 pb-7">
            <FooterMessage v-if="layout?.user?.is_agent" in-rail />
        </div>
    </div>
</template>

<style>
.custom-hide-scrollbar::-webkit-scrollbar {
    display: none;
}

.custom-hide-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
