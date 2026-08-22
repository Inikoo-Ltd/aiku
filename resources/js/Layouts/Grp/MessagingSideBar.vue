<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, nextTick, onMounted, onUnmounted, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faSearch, faUser, faComments, faStar as faStarRegular, faPlus, faTimes, faComment, faGopuram, faHomeAlt, faHeart, faExpandAlt } from "@fal"
import { faStar as faStarSolid } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { router } from "@inertiajs/vue3"
import Image from "@/Common/Components/Image.vue"
import RailControls from "@/Layouts/Grp/RailControls.vue"
import FooterMessage from "@/Components/Footer/FooterMessage.vue"
import ManageTeamModal from "@/Components/Messaging/ManageTeamModal.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, type StaffCoworker } from "@/Stores/staff-messaging"
import { useTruncate } from "@/Composables/useTruncate"

library.add(faChevronLeft, faSearch, faUser, faComments, faStarRegular, faStarSolid, faPlus, faTimes, faComment, faGopuram, faHomeAlt, faHeart, faExpandAlt)

const openFullMessaging = () => router.visit(route("grp.chat.staff.index"))

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
const nowTick = ref(0)
let searchTimeout: ReturnType<typeof setTimeout> | null = null
let refreshInterval: ReturnType<typeof setInterval> | null = null
let tickInterval: ReturnType<typeof setInterval> | null = null

const ACTIVE_WINDOW = 15 * 60
const isActive = (c: StaffCoworker) => !!c.last_active_at && (Date.now() / 1000 - c.last_active_at) < ACTIVE_WINDOW
const presence = (c: StaffCoworker) => isOnline(c.id) ? (isActive(c) ? 'online' : 'idle') : 'offline'

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

const openNewMessageFromCollapsed = async () => {
    if (!layout.messagingSidebar.show) {
        handleToggle()
        await nextTick()
    }
    setTimeout(() => openPlusSearch(), 50)
}

const closeSearch = () => {
    plusOpened.value = false
    search.value = ""
    searchResults.value = []
}

const isOnline = (id: number) => !!useLiveUsers().liveUsers[id]

const getCurrentPage = (coworkerId: number) => useLiveUsers().liveUsers[coworkerId]?.current_page

const getTooltipName = (name: string, coworkerId: number) => {
    const page = getCurrentPage(coworkerId)
    return page?.label ? `${name} · ${page.label}` : name
}

const byName = (a: StaffCoworker, b: StaffCoworker) => a.name.localeCompare(b.name)

const teamCoworkers = computed(() => {
    const presenceOrder = { 'online': 0, 'idle': 1, 'offline': 2 }
    return coworkers.value.filter((c) => c.in_team).sort((a, b) => {
        const presA = presence(a)
        const presB = presence(b)
        if (presenceOrder[presA as keyof typeof presenceOrder] !== presenceOrder[presB as keyof typeof presenceOrder]) {
            return presenceOrder[presA as keyof typeof presenceOrder] - presenceOrder[presB as keyof typeof presenceOrder]
        }
        return byName(a, b)
    })
})
const teamOnlineCoworkers = computed(() => teamCoworkers.value.filter((c) => presence(c) === 'online'))
const onlineCoworkers = computed(() =>
    coworkers.value.filter((c) => presence(c) === 'online' && !c.in_team).sort(byName)
)

const allOnlineCount = computed(() => (nowTick.value, coworkers.value.filter((c) => presence(c) === 'online' && c.id !== myId.value).length))
const orgOnlineCount = computed(() => (nowTick.value, coworkers.value.filter((c) => c.is_close && presence(c) === 'online' && c.id !== myId.value).length))
const teamOnlineCount = computed(() => (nowTick.value, teamOnlineCoworkers.value.filter((c) => c.id !== myId.value).length))

const showInput = computed(() => plusOpened.value)
const searchPlaceholder = computed(() => trans('Find a coworker…'))
const query = computed(() => search.value.trim().toLowerCase())
const clientFilterActive = computed(() => !plusOpened.value && query.value.length > 0)
const filteredTeamCoworkers = computed(() =>
    clientFilterActive.value ? teamCoworkers.value.filter((c) => c.name.toLowerCase().includes(query.value)) : teamCoworkers.value
)
const showSearchResults = computed(() => plusOpened.value && query.value.length > 0)
const sortedSearchResults = computed(() => {
    const presenceOrder = { 'online': 0, 'idle': 1, 'offline': 2 }
    return [...searchResults.value].sort((a, b) => {
        const presA = presence(a)
        const presB = presence(b)
        if (presenceOrder[presA as keyof typeof presenceOrder] !== presenceOrder[presB as keyof typeof presenceOrder]) {
            return presenceOrder[presA as keyof typeof presenceOrder] - presenceOrder[presB as keyof typeof presenceOrder]
        }
        return a.name.localeCompare(b.name)
    })
})

const openSearchResult = (coworker: StaffCoworker) => {
    store.openWithUser(coworker.id)
    closeSearch()
}

const toggleTeam = async (coworker: StaffCoworker, event: Event) => {
    event.stopPropagation()
    const { data } = await axios.post(route("grp.chat.staff.team.toggle"), { user_id: coworker.id })
    coworker.in_team = data.in_team
}

const isManageTeamOpen = ref(false)
const onTeamChanged = () => fetchCoworkers(search.value)

const peopleFilter = ref<null | "all" | "org" | "team">(null)
const togglePeopleFilter = (filter: "all" | "org" | "team") => {
    if (!layout.messagingSidebar.show) handleToggle()
    peopleFilter.value = peopleFilter.value === filter ? null : filter
}
const clearPeopleFilter = () => {
    peopleFilter.value = null
}

const myId = computed(() => layout.user?.id)
const conversationTitle = (conversation: any) =>
    conversation.name || conversation.participants.filter((p: any) => p.id !== myId.value).map((p: any) => p.name).join(", ")
const conversationOtherId = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.id ?? null
const conversationAvatar = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.avatar ?? null

const teamOfflineCoworkers = computed(() => teamCoworkers.value.filter((c) => !isOnline(c.id)))

const filteredPeopleList = computed(() => {
    if (peopleFilter.value === "all") {
        return coworkers.value.filter((c) => presence(c) === 'online' && c.id !== myId.value).sort(byName)
    }
    if (peopleFilter.value === "org") {
        return coworkers.value.filter((c) => c.is_close && presence(c) === 'online' && c.id !== myId.value).sort(byName)
    }
    if (peopleFilter.value === "team") {
        return teamCoworkers.value
    }
    return []
})

const peopleFilterHeader = computed(() => {
    if (peopleFilter.value === "all") return `${trans('Online now')} (${filteredPeopleList.value.length})`
    if (peopleFilter.value === "org") return `${trans('Online in my organisation')} (${filteredPeopleList.value.length})`
    if (peopleFilter.value === "team") return `${trans('My team')} (${teamOnlineCount.value}/${teamCoworkers.value.length} ${trans('online')})`
    return ""
})

const conversationsSummary = computed(() => ({
    total: store.conversations.length,
    unread: store.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0),
}))

const conversationUserIds = computed(() => new Set(
    store.conversations.filter((c) => c.type === "dm").map((c) => conversationOtherId(c))
))

const railOfflineWithConversation = computed(() => {
    return coworkers.value.filter((c) => !c.in_team && presence(c) === 'offline' && conversationUserIds.value.has(c.id))
})

const railOnlineWithConversation = computed(() => {
    return coworkers.value.filter((c) => !c.in_team && presence(c) === 'online' && conversationUserIds.value.has(c.id))
})

const getPresenceBool = (c: StaffCoworker) => presence(c) !== 'offline'
const railCandidates = computed(() => [
    ...teamOnlineCoworkers.value.map((coworker) => ({ coworker, online: getPresenceBool(coworker) })),
    ...teamOfflineCoworkers.value.map((coworker) => ({ coworker, online: false })),
    ...railOnlineWithConversation.value.map((coworker) => ({ coworker, online: getPresenceBool(coworker) })),
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
    tickInterval = setInterval(() => { nowTick.value++ }, 60000)
})

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval)
    if (tickInterval) clearInterval(tickInterval)
    if (searchTimeout) clearTimeout(searchTimeout)
})
</script>

<template>
    <div
        class="hidden md:flex md:flex-col fixed inset-y-0 right-0 h-full bg-[var(--chat-bg)] border-l border-[var(--chat-line)] z-[22] transition-all duration-300 ease-in-out"
        :class="[
            layout.messagingSidebar.show ? 'md:w-56' : 'md:w-12',
        ]"
        id="messagingSidebar">
        <!-- Toggle: collapse-expand MessagingSideBar -->
        <div
            @click="handleToggle"
            class="absolute z-10 left-0 top-2/4 -translate-y-full -translate-x-1/2 w-8 lg:w-5 aspect-square border border-[var(--chat-muted)] rounded-full bg-[var(--chat-line)] flex justify-center items-center cursor-pointer"
            :title="layout.messagingSidebar.show ? 'Collapse the bar' : 'Expand the bar'">
            <FontAwesomeIcon
                icon="far fa-chevron-left"
                class="h-[10px] leading-none transition-all duration-300 ease-in-out text-[var(--chat-text)]"
                aria-hidden="true"
                :class="layout.messagingSidebar.show ? 'rotate-180' : ''" />
        </div>

        <RailControls />

        <!-- Online counters -->
        <!-- COLLAPSED: three counters -->
        <div v-if="!layout.messagingSidebar.show" class="flex flex-col items-center gap-y-1 pt-2 pb-1 border-b border-[var(--chat-line)]">
            <div class="flex items-center gap-x-1 cursor-pointer rounded px-1" :class="peopleFilter === 'all' ? 'bg-[var(--chat-line)]' : ''" v-tooltip="trans('Everyone online')" @click="togglePeopleFilter('all')">
                <FontAwesomeIcon icon="fal fa-gopuram" class="text-[var(--chat-green)] text-xs" fixed-width aria-hidden="true" />
                <span class="text-xxs tabular-nums text-[var(--chat-text)]">{{ allOnlineCount }}</span>
            </div>
            <div class="flex items-center gap-x-1 cursor-pointer rounded px-1" :class="peopleFilter === 'org' ? 'bg-[var(--chat-line)]' : ''" v-tooltip="trans('Online in my organisation')" @click="togglePeopleFilter('org')">
                <FontAwesomeIcon icon="fal fa-home-alt" class="text-[var(--chat-cyan)] text-xs" fixed-width aria-hidden="true" />
                <span class="text-xxs tabular-nums text-[var(--chat-text)]">{{ orgOnlineCount }}</span>
            </div>
            <div class="flex items-center gap-x-1 cursor-pointer rounded px-1" :class="peopleFilter === 'team' ? 'bg-[var(--chat-line)]' : ''" v-tooltip="trans('Online in my team')" @click="togglePeopleFilter('team')">
                <FontAwesomeIcon icon="fal fa-heart" class="text-[var(--chat-accent)] text-xs" fixed-width aria-hidden="true" />
                <span class="text-xxs tabular-nums text-[var(--chat-text)]">{{ teamOnlineCount }}</span>
            </div>
        </div>

        <!-- EXPANDED: three counters -->
        <div v-else class="px-3 py-2 flex items-center gap-x-3 border-b border-[var(--chat-line)] text-xs">
            <span class="flex items-center gap-x-1 cursor-pointer rounded px-1" :class="peopleFilter === 'all' ? 'bg-[var(--chat-line)]' : ''" @click="togglePeopleFilter('all')"><FontAwesomeIcon icon="fal fa-gopuram" class="text-[var(--chat-green)] text-xs" fixed-width aria-hidden="true" /><span class="tabular-nums text-[var(--chat-text)]">{{ allOnlineCount }}</span><span class="text-[var(--chat-label)]">{{ trans('all') }}</span></span>
            <span class="flex items-center gap-x-1 cursor-pointer rounded px-1" :class="peopleFilter === 'org' ? 'bg-[var(--chat-line)]' : ''" @click="togglePeopleFilter('org')"><FontAwesomeIcon icon="fal fa-home-alt" class="text-[var(--chat-cyan)] text-xs" fixed-width aria-hidden="true" /><span class="tabular-nums text-[var(--chat-text)]">{{ orgOnlineCount }}</span><span class="text-[var(--chat-label)]">{{ trans('org') }}</span></span>
            <span class="flex items-center gap-x-1 cursor-pointer rounded px-1" :class="peopleFilter === 'team' ? 'bg-[var(--chat-line)]' : ''" @click="togglePeopleFilter('team')"><FontAwesomeIcon icon="fal fa-heart" class="text-[var(--chat-accent)] text-xs" fixed-width aria-hidden="true" /><span class="tabular-nums text-[var(--chat-text)]">{{ teamOnlineCount }}</span><span class="text-[var(--chat-label)]">{{ trans('team') }}</span></span>
        </div>

        <!-- Messaging button -->
        <div v-if="!layout.messagingSidebar.show" class="pt-2 flex justify-center items-center">
            <button class="h-9 w-9 flex items-center justify-center text-[var(--chat-muted)] hover:text-[var(--chat-text)]" v-tooltip="trans('Open messaging')" @click="openFullMessaging">
                <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
            </button>
        </div>
        <div v-else class="pt-2 pb-1 px-3">
            <button class="w-full flex items-center gap-x-3 text-[var(--chat-muted)] hover:text-[var(--chat-text)]" @click="openFullMessaging">
                <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                <span class="text-xs text-[var(--chat-text)]">{{ trans('Messaging') }}</span>
            </button>
        </div>

        <!-- Unread chats chip -->
        <div v-if="store.totalUnread > 0" class="border-b border-[var(--chat-line)] py-2 flex justify-center items-center" :class="layout.messagingSidebar.show ? 'gap-2' : ''">
            <div
                class="bg-[var(--chat-red)] text-white rounded-md min-w-[2rem] h-7 px-2 flex items-center justify-center text-xs font-medium tabular-nums cursor-pointer"
                v-tooltip="trans('Unread chats')"
                @click="!layout.messagingSidebar.show && handleToggle()">
                {{ store.totalUnread > 99 ? '99+' : store.totalUnread }}
            </div>
            <span v-if="layout.messagingSidebar.show" class="text-[var(--chat-text)] text-xs">{{ trans('unread') }}</span>
        </div>

        <!-- COLLAPSED: avatar rail -->
        <div v-if="!layout.messagingSidebar.show" class="flex-1 flex flex-col items-center gap-y-3 pt-4 overflow-y-auto custom-hide-scrollbar">
            <button
                v-for="item in railVisible"
                :key="'rail-' + item.coworker.id"
                class="relative h-7 w-7 rounded-full overflow-hidden bg-[var(--chat-line)] shrink-0"
                :class="item.online ? '' : 'opacity-40'"
                :title="getTooltipName(item.coworker.name, item.coworker.id)"
                v-tooltip
                @click="openUser(item.coworker.id)">
                <Image v-if="item.coworker.avatar" :src="item.coworker.avatar" :alt="item.coworker.name" image-cover />
                <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[var(--chat-muted)]" fixed-width aria-hidden="true" />
                <span class="absolute bottom-0 right-0 h-2 w-2 rounded-full ring-1 ring-[var(--chat-bg)]" :class="[presence(item.coworker) === 'online' ? 'bg-[var(--chat-green)]' : (presence(item.coworker) === 'idle' ? 'bg-[var(--chat-yellow)]' : 'bg-[var(--chat-muted)]')]" :title="presence(item.coworker) === 'idle' ? trans('Idle') : ''" />
                <span v-if="unreadForUser(item.coworker.id) > 0" class="absolute -top-1 -right-1 bg-[var(--chat-red)] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs">{{ unreadForUser(item.coworker.id) }}</span>
            </button>
            <button
                v-if="railOverflowCount > 0"
                class="relative h-7 w-7 rounded-full bg-[var(--chat-line)] shrink-0 flex items-center justify-center text-xxs text-[var(--chat-text)]"
                v-tooltip="trans('Show all')"
                @click="handleToggle">
                +{{ railOverflowCount }}
            </button>
            <button
                class="h-9 w-9 rounded-full bg-transparent border border-dashed border-[var(--chat-muted)] shrink-0 flex items-center justify-center text-[var(--chat-muted)] hover:text-[var(--chat-text)] hover:border-[var(--chat-text)]"
                v-tooltip="trans('New message')"
                @click="openNewMessageFromCollapsed">
                <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
            </button>
        </div>

        <!-- EXPANDED -->
        <div v-else class="flex-1 flex flex-col overflow-y-auto custom-hide-scrollbar pt-2 pb-3">
            <div v-if="showInput" class="p-3 pb-2 shrink-0">
                <div class="relative">
                    <FontAwesomeIcon icon="fal fa-search" class="absolute left-2 top-1/2 -translate-y-1/2 text-[var(--chat-muted)] text-xs" fixed-width aria-hidden="true" />
                    <input
                        ref="searchInput"
                        v-model="search"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="w-full pl-7 pr-7 py-1.5 text-xs bg-[var(--chat-bg-alt)] text-[var(--chat-text)] placeholder-[var(--chat-muted)] border border-[var(--chat-line)] rounded-md focus:outline-none focus:ring-1 focus:ring-[var(--chat-accent)]"
                        @input="onSearchInput"
                        @keydown.esc="closeSearch" />
                    <button v-if="plusOpened || search" class="absolute right-2 top-1/2 -translate-y-1/2 text-[var(--chat-muted)] hover:text-[var(--chat-text)]" @click="closeSearch">
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
                    class="group w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[var(--chat-line)] text-left cursor-pointer"
                    :class="presence(coworker) !== 'offline' ? '' : 'opacity-40'"
                    @click="openSearchResult(coworker)">
                    <div class="relative h-6 w-6 rounded-full overflow-hidden bg-[var(--chat-line)] shrink-0">
                        <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[var(--chat-muted)]" fixed-width aria-hidden="true" />
                        <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[var(--chat-bg)]" :class="[presence(coworker) === 'online' ? 'bg-[var(--chat-green)]' : (presence(coworker) === 'idle' ? 'bg-[var(--chat-yellow)]' : 'bg-[var(--chat-muted)]')]" :title="presence(coworker) === 'idle' ? trans('Idle') : ''" />
                    </div>
                    <div class="flex-1 flex flex-col min-w-0">
                        <span class="text-xs truncate text-[var(--chat-text)]">{{ coworker.name }}</span>
                        <template v-if="getCurrentPage(coworker.id)?.label">
                            <a v-if="getCurrentPage(coworker.id)?.url" :href="getCurrentPage(coworker.id)?.url" @click.stop class="text-xxs text-[var(--chat-label)] truncate hover:underline">
                                {{ useTruncate(getCurrentPage(coworker.id)?.label, 28) }}
                            </a>
                            <span v-else class="text-xxs text-[var(--chat-label)] truncate">
                                {{ useTruncate(getCurrentPage(coworker.id)?.label, 28) }}
                            </span>
                        </template>
                    </div>
                    <span role="button" tabindex="0" class="shrink-0" @click="toggleTeam(coworker, $event)" v-tooltip="coworker.in_team ? trans('In my team') : trans('Add to my team')">
                        <FontAwesomeIcon :icon="coworker.in_team ? 'fas fa-star' : 'fal fa-star'" :class="coworker.in_team ? 'text-[var(--chat-yellow)]' : 'text-[var(--chat-muted)]'" fixed-width aria-hidden="true" />
                    </span>
                </div>
            </template>

            <template v-else-if="!peopleFilter">
            <div class="px-3 pt-2 pb-1 flex items-center justify-between text-xs text-[var(--chat-muted)]">
                <span>{{ trans('My team') }} ({{ teamOnlineCount }}/{{ teamCoworkers.length }} {{ trans('online') }})</span>
                <button class="shrink-0 text-[var(--chat-accent)] hover:text-[var(--chat-text)]" @click="isManageTeamOpen = true" v-tooltip="trans('Manage my team')">
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div
                v-for="coworker in filteredTeamCoworkers"
                :key="'exp-team-' + coworker.id"
                role="button" tabindex="0"
                class="group w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[var(--chat-line)] text-left cursor-pointer"
                :class="presence(coworker) !== 'offline' ? '' : 'opacity-40'"
                @click="openUser(coworker.id)">
                <div class="relative h-6 w-6 rounded-full overflow-hidden bg-[var(--chat-line)] shrink-0">
                    <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                    <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[var(--chat-muted)]" fixed-width aria-hidden="true" />
                    <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[var(--chat-bg)]" :class="[presence(coworker) === 'online' ? 'bg-[var(--chat-green)]' : (presence(coworker) === 'idle' ? 'bg-[var(--chat-yellow)]' : 'bg-[var(--chat-muted)]')]" :title="presence(coworker) === 'idle' ? trans('Idle') : ''" />
                </div>
                <div class="flex-1 flex flex-col min-w-0">
                    <span class="text-xs truncate text-[var(--chat-text)]">{{ coworker.name }}</span>
                    <template v-if="getCurrentPage(coworker.id)?.label">
                        <a v-if="getCurrentPage(coworker.id)?.url" :href="getCurrentPage(coworker.id)?.url" @click.stop class="text-xxs text-[var(--chat-label)] truncate hover:underline">
                            {{ useTruncate(getCurrentPage(coworker.id)?.label, 28) }}
                        </a>
                        <span v-else class="text-xxs text-[var(--chat-label)] truncate">
                            {{ useTruncate(getCurrentPage(coworker.id)?.label, 28) }}
                        </span>
                    </template>
                </div>
                <span v-if="unreadForUser(coworker.id) > 0" class="bg-[var(--chat-red)] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ unreadForUser(coworker.id) }}</span>
                <span role="button" tabindex="0" class="shrink-0 opacity-0 group-hover:opacity-100" @click.stop="store.openWithUser(coworker.id)" v-tooltip="trans('Message')">
                    <FontAwesomeIcon icon="fal fa-comment" class="text-[var(--chat-muted)] hover:text-[var(--chat-text)]" fixed-width aria-hidden="true" />
                </span>
            </div>

            <div class="border-t border-[var(--chat-line)] mt-2">
                <div class="px-3 pt-2 pb-1 flex items-center justify-between text-xs text-[var(--chat-muted)]">
                    <span>{{ trans('Messages') }}</span>
                    <button v-if="!plusOpened" class="shrink-0 text-[var(--chat-accent)] hover:text-[var(--chat-text)]" @click="openPlusSearch" v-tooltip="trans('New message')">
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                    </button>
                </div>
                <button
                    v-for="conversation in sortedConversations"
                    :key="'conv-' + conversation.ulid"
                    class="w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[var(--chat-line)] text-left"
                    @click="store.openConversation(conversation.ulid)">
                    <div v-if="conversation.type === 'group'" class="h-6 w-6 rounded-full bg-[var(--chat-line)] flex items-center justify-center shrink-0">
                        <FontAwesomeIcon icon="fal fa-comments" class="text-[var(--chat-accent)]" fixed-width aria-hidden="true" />
                    </div>
                    <div v-else class="relative h-6 w-6 rounded-full overflow-hidden bg-[var(--chat-line)] shrink-0">
                        <Image v-if="conversationAvatar(conversation)" :src="conversationAvatar(conversation)" :alt="conversationTitle(conversation)" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[var(--chat-muted)]" fixed-width aria-hidden="true" />
                        <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[var(--chat-bg)]" :class="isOnline(conversationOtherId(conversation)) ? 'bg-[var(--chat-green)]' : 'bg-[var(--chat-muted)]'" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs truncate text-[var(--chat-text)]">{{ conversationTitle(conversation) }}</div>
                        <div class="text-xxs text-[var(--chat-muted)] truncate">{{ useTruncate(conversation.last_message ?? '', 26) }}</div>
                    </div>
                    <span v-if="conversation.unread_count > 0" class="bg-[var(--chat-red)] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ conversation.unread_count }}</span>
                </button>
            </div>
            </template>

            <template v-else>
            <div class="px-3 pt-2 pb-1 flex items-center justify-between text-xs text-[var(--chat-muted)]">
                <span>{{ peopleFilterHeader }}</span>
                <button class="shrink-0 text-[var(--chat-muted)] hover:text-[var(--chat-text)]" @click="clearPeopleFilter" v-tooltip="trans('Clear filter')">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div
                v-for="coworker in filteredPeopleList"
                :key="'filter-' + coworker.id"
                role="button" tabindex="0"
                class="w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[var(--chat-line)] text-left cursor-pointer"
                :class="presence(coworker) !== 'offline' ? '' : 'opacity-40'"
                @click="openUser(coworker.id)">
                <div class="relative h-6 w-6 rounded-full overflow-hidden bg-[var(--chat-line)] shrink-0">
                    <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                    <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[var(--chat-muted)]" fixed-width aria-hidden="true" />
                    <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[var(--chat-bg)]" :class="[presence(coworker) === 'online' ? 'bg-[var(--chat-green)]' : (presence(coworker) === 'idle' ? 'bg-[var(--chat-yellow)]' : 'bg-[var(--chat-muted)]')]" :title="presence(coworker) === 'idle' ? trans('Idle') : ''" />
                </div>
                <div class="flex-1 flex flex-col min-w-0">
                    <span class="text-xs truncate text-[var(--chat-text)]">{{ coworker.name }}</span>
                    <template v-if="getCurrentPage(coworker.id)?.label">
                        <a v-if="getCurrentPage(coworker.id)?.url" :href="getCurrentPage(coworker.id)?.url" @click.stop class="text-xxs text-[var(--chat-label)] truncate hover:underline">
                            {{ useTruncate(getCurrentPage(coworker.id)?.label, 28) }}
                        </a>
                        <span v-else class="text-xxs text-[var(--chat-label)] truncate">
                            {{ useTruncate(getCurrentPage(coworker.id)?.label, 28) }}
                        </span>
                    </template>
                </div>
                <span v-if="unreadForUser(coworker.id) > 0" class="bg-[var(--chat-red)] text-white rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0">{{ unreadForUser(coworker.id) }}</span>
                <span class="shrink-0" @click.stop="store.openWithUser(coworker.id)" v-tooltip="trans('Message')">
                    <FontAwesomeIcon icon="fal fa-comment" class="text-[var(--chat-muted)] hover:text-[var(--chat-text)]" fixed-width aria-hidden="true" />
                </span>
                <span v-if="peopleFilter !== 'team'" role="button" tabindex="0" class="shrink-0 opacity-0 group-hover:opacity-100" @click="toggleTeam(coworker, $event)" v-tooltip="coworker.in_team ? trans('In my team') : trans('Add to my team')">
                    <FontAwesomeIcon :icon="coworker.in_team ? 'fas fa-star' : 'fal fa-star'" :class="coworker.in_team ? 'text-[var(--chat-yellow)]' : 'text-[var(--chat-muted)]'" fixed-width aria-hidden="true" />
                </span>
            </div>

            <div class="border-t border-[var(--chat-line)] mt-2 flex items-center justify-between px-3 py-2">
                <button class="text-left text-xs text-[var(--chat-muted)] hover:text-[var(--chat-text)]" @click="clearPeopleFilter">
                    {{ trans('Messages') }} ({{ conversationsSummary.total }}, {{ conversationsSummary.unread }} {{ trans('unread') }})
                </button>
                <button v-if="!plusOpened" class="shrink-0 text-[var(--chat-accent)] hover:text-[var(--chat-text)]" @click="openPlusSearch" v-tooltip="trans('New message')">
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                </button>
            </div>
            </template>
        </div>

        <!-- Bottom-pinned: customer chats trigger -->
        <div v-if="layout?.user?.is_agent" class="mt-auto shrink-0 border-t border-[var(--chat-line)] pt-2 pb-7">
            <FooterMessage in-rail />
        </div>
    </div>

    <Teleport to="body">
        <ManageTeamModal
            v-if="isManageTeamOpen"
            :is-open="isManageTeamOpen"
            @close="isManageTeamOpen = false"
            @changed="onTeamChanged" />
    </Teleport>
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
