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
import { faChevronLeft, faSearch, faUser, faComments, faStar as faStarRegular, faPlus, faTimes, faComment } from "@fal"
import { faStar as faStarSolid } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import RailControls from "@/Layouts/Grp/RailControls.vue"
import FooterMessage from "@/Components/Footer/FooterMessage.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useTruncate } from "@/Composables/useTruncate"

library.add(faChevronLeft, faSearch, faUser, faComments, faStarRegular, faStarSolid, faPlus, faTimes, faComment)

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
const offlineCoworkers = computed(() =>
    coworkers.value.filter((c) => !isOnline(c.id) && !c.in_team).sort(byName)
)

const allOnlineCount = computed(() => useLiveUsers().count)
const orgOnlineCount = computed(() => coworkers.value.filter((c) => c.is_close && isOnline(c.id)).length)
const teamOnlineCount = computed(() => teamOnlineCoworkers.value.length)

const manyOnline = computed(() => onlineCoworkers.value.length > 10)
const showInput = computed(() => plusOpened.value || manyOnline.value)
const searchPlaceholder = computed(() => plusOpened.value ? trans('Find a coworker…') : trans('Filter…'))
const query = computed(() => search.value.trim().toLowerCase())
const clientFilterActive = computed(() => !plusOpened.value && query.value.length > 0)
const filteredTeamCoworkers = computed(() =>
    clientFilterActive.value ? teamCoworkers.value.filter((c) => c.name.toLowerCase().includes(query.value)) : teamCoworkers.value
)
const filteredOnlineCoworkers = computed(() =>
    clientFilterActive.value ? onlineCoworkers.value.filter((c) => c.name.toLowerCase().includes(query.value)) : onlineCoworkers.value
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

const conversationsSection = ref<HTMLElement | null>(null)
const openInternalChats = () => {
    if (!layout.messagingSidebar.show) {
        handleToggle()
    }
    setTimeout(() => conversationsSection.value?.scrollIntoView({ behavior: "smooth", block: "start" }), 50)
}

const myId = computed(() => layout.user?.id)
const conversationTitle = (conversation: any) =>
    conversation.name || conversation.participants.filter((p: any) => p.id !== myId.value).map((p: any) => p.name).join(", ")
const conversationOtherId = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.id ?? null
const conversationAvatar = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.avatar ?? null

const onlineCoworkerIds = computed(() => new Set(onlineCoworkers.value.map((c) => c.id)))

const extraConversations = computed(() =>
    store.conversations.filter((c) => c.type === "group" || !onlineCoworkerIds.value.has(conversationOtherId(c)))
)

const teamOfflineCoworkers = computed(() => teamCoworkers.value.filter((c) => !isOnline(c.id)))

const railOfflineWithConversation = computed(() => {
    const conversationUserIds = new Set(
        store.conversations.filter((c) => c.type === "dm").map((c) => conversationOtherId(c))
    )
    return offlineCoworkers.value.filter((c) => conversationUserIds.has(c.id))
})

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
            <div class="flex items-center gap-x-1" v-tooltip="trans('Online')">
                <span class="h-1.5 w-1.5 rounded-full bg-[#50fa7b]" />
                <span class="text-xxs tabular-nums text-[#f8f8f2]">{{ allOnlineCount }}</span>
            </div>
            <div class="flex items-center gap-x-1" v-tooltip="trans('Online in my organisation')">
                <span class="h-1.5 w-1.5 rounded-full bg-[#8be9fd]" />
                <span class="text-xxs tabular-nums text-[#f8f8f2]">{{ orgOnlineCount }}</span>
            </div>
            <div class="flex items-center gap-x-1 cursor-pointer" v-tooltip="trans('Online in my team')" @click="focusTeamSection">
                <span class="h-1.5 w-1.5 rounded-full bg-[#bd93f9]" />
                <span class="text-xxs tabular-nums text-[#f8f8f2]">{{ teamOnlineCount }}</span>
            </div>
        </div>

        <!-- EXPANDED: three counters -->
        <div v-else class="px-3 py-2 flex items-center gap-x-3 border-b border-[#44475a] text-xxs cursor-pointer" @click="focusTeamSection">
            <span class="flex items-center gap-x-1"><span class="h-1.5 w-1.5 rounded-full bg-[#50fa7b]" /><span class="tabular-nums text-[#f8f8f2]">{{ allOnlineCount }}</span> {{ trans('all') }}</span>
            <span class="flex items-center gap-x-1"><span class="h-1.5 w-1.5 rounded-full bg-[#8be9fd]" /><span class="tabular-nums text-[#f8f8f2]">{{ orgOnlineCount }}</span> {{ trans('org') }}</span>
            <span class="flex items-center gap-x-1"><span class="h-1.5 w-1.5 rounded-full bg-[#bd93f9]" /><span class="tabular-nums text-[#f8f8f2]">{{ teamOnlineCount }}</span> {{ trans('team') }}</span>
        </div>

        <!-- COLLAPSED: avatar rail -->
        <div v-if="!layout.messagingSidebar.show" class="flex-1 flex flex-col items-center gap-y-3 pt-4 overflow-y-auto custom-hide-scrollbar">
            <button
                v-for="coworker in teamOnlineCoworkers"
                :key="'rail-team-on-' + coworker.id"
                class="relative h-7 w-7 rounded-full overflow-hidden bg-[#44475a] shrink-0"
                v-tooltip="coworker.name"
                @click="openUser(coworker.id)">
                <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full bg-[#50fa7b] ring-1 ring-[#282a36]" />
                <span v-if="unreadForUser(coworker.id) > 0" class="absolute -top-1 -right-1 bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ unreadForUser(coworker.id) }}</span>
            </button>
            <button
                v-for="coworker in onlineCoworkers"
                :key="'rail-on-' + coworker.id"
                class="relative h-7 w-7 rounded-full overflow-hidden bg-[#44475a] shrink-0"
                v-tooltip="coworker.name"
                @click="openUser(coworker.id)">
                <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full bg-[#50fa7b] ring-1 ring-[#282a36]" />
                <span v-if="unreadForUser(coworker.id) > 0" class="absolute -top-1 -right-1 bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ unreadForUser(coworker.id) }}</span>
            </button>
            <button
                v-for="coworker in teamOfflineCoworkers"
                :key="'rail-team-off-' + coworker.id"
                class="relative h-7 w-7 rounded-full overflow-hidden bg-[#44475a] shrink-0 opacity-40"
                v-tooltip="coworker.name"
                @click="openUser(coworker.id)">
                <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full bg-[#6272a4] ring-1 ring-[#282a36]" />
                <span v-if="unreadForUser(coworker.id) > 0" class="absolute -top-1 -right-1 bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ unreadForUser(coworker.id) }}</span>
            </button>
            <button
                v-for="coworker in railOfflineWithConversation"
                :key="'rail-off-' + coworker.id"
                class="relative h-7 w-7 rounded-full overflow-hidden bg-[#44475a] shrink-0 opacity-40"
                v-tooltip="coworker.name"
                @click="openUser(coworker.id)">
                <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full bg-[#6272a4] ring-1 ring-[#282a36]" />
                <span v-if="unreadForUser(coworker.id) > 0" class="absolute -top-1 -right-1 bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ unreadForUser(coworker.id) }}</span>
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

            <div class="px-3 pt-2 pb-1 text-xs text-[#6272a4]">{{ trans('Online') }} ({{ onlineCoworkers.length }})</div>
            <div
                v-for="coworker in filteredOnlineCoworkers"
                :key="'exp-on-' + coworker.id"
                role="button" tabindex="0"
                class="group w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[#44475a] text-left cursor-pointer"
                @click="openUser(coworker.id)">
                <div class="relative h-6 w-6 rounded-full overflow-hidden bg-[#44475a] shrink-0">
                    <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                    <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[#6272a4]" fixed-width aria-hidden="true" />
                    <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full bg-[#50fa7b] ring-1 ring-[#282a36]" />
                </div>
                <span class="flex-1 text-xs truncate text-[#f8f8f2]">{{ coworker.name }}</span>
                <span v-if="unreadForUser(coworker.id) > 0" class="bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ unreadForUser(coworker.id) }}</span>
                <span role="button" tabindex="0" class="shrink-0 opacity-0 group-hover:opacity-100" @click.stop="store.openWithUser(coworker.id)" v-tooltip="trans('Message')">
                    <FontAwesomeIcon icon="fal fa-comment" class="text-[#6272a4] hover:text-[#f8f8f2]" fixed-width aria-hidden="true" />
                </span>
                <span role="button" tabindex="0" class="shrink-0 opacity-0 group-hover:opacity-100" @click="toggleTeam(coworker, $event)" v-tooltip="trans('Add to my team')">
                    <FontAwesomeIcon icon="fal fa-star" class="text-[#6272a4]" fixed-width aria-hidden="true" />
                </span>
            </div>
            </template>

            <div ref="conversationsSection" class="border-t border-[#44475a] mt-2">
                <div class="px-3 pt-2 pb-1 text-xs text-[#6272a4]">{{ trans('Conversations') }}</div>
                <button
                    v-for="conversation in extraConversations"
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
        </div>

        <!-- Bottom-pinned: internal + customer chats triggers -->
        <div class="mt-auto shrink-0 border-t border-[#44475a] pt-3 pb-8">
            <div
                class="cursor-pointer mb-1"
                @click="openInternalChats">
                <div v-if="layout.messagingSidebar.show" class="w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[#44475a] text-left">
                    <FontAwesomeIcon icon="fal fa-comments" class="text-[#6272a4]" fixed-width aria-hidden="true" />
                    <span class="flex-1 text-xs truncate text-[#f8f8f2]">{{ trans('Internal chats') }}</span>
                    <span v-if="store.totalUnread > 0" class="bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ store.totalUnread > 99 ? '99+' : store.totalUnread }}</span>
                </div>
                <div v-else class="relative h-9 w-9 mx-auto rounded flex items-center justify-center text-[#6272a4] hover:text-[#f8f8f2]">
                    <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                    <span v-if="store.totalUnread > 0" class="absolute -top-0.5 -right-0.5 bg-[#ff5555] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ store.totalUnread > 99 ? '99+' : store.totalUnread }}</span>
                </div>
            </div>

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
