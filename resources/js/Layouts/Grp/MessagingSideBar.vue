<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 22 Aug 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, defineAsyncComponent, inject, nextTick, onMounted, onUnmounted, ref } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { compactCount } from "@/Composables/useCompactCount"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronLeft, faChevronDoubleLeft, faChevronDoubleRight, faSearch, faUser, faComments, faStar as faStarRegular, faPlus, faTimes, faComment, faGopuram, faHomeAlt, faHeart, faExpandAlt, faPencil, faLifeRing, faShoppingCart, faCube, faCircle, faSpinner, faCheckCircle, faBan, faTasks, faEye, faEyeSlash } from "@fal"
import { faStar as faStarSolid, faEye as faEyeSolid } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { router } from "@inertiajs/vue3"
import Image from "@/Common/Components/Image.vue"
import RailControls from "@/Layouts/Grp/RailControls.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
const ManageTeamModal = defineAsyncComponent(() => import("@/Components/Messaging/ManageTeamModal.vue"))
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging, isAlerting, isWorkThread, isHiddenChat, canArchiveConversation, type StaffCoworker } from "@/Stores/staff-messaging"
import CustomersWaiting from "@/Layouts/Grp/CustomersWaiting.vue"
import WhatsappCallAlert from "@/Layouts/Grp/WhatsappCallAlert.vue"
import { useTruncate } from "@/Composables/useTruncate"

library.add(faChevronLeft, faChevronDoubleLeft, faChevronDoubleRight, faSearch, faUser, faComments, faStarRegular, faStarSolid, faPlus, faTimes, faComment, faGopuram, faHomeAlt, faHeart, faExpandAlt, faPencil, faLifeRing, faShoppingCart, faCube, faCircle, faSpinner, faCheckCircle, faBan, faTasks, faEye, faEyeSlash, faEyeSolid)

const openFullMessaging = () => router.visit(route("grp.chat.staff.index"))

const layout = inject("layout", layoutStructure)
const store = useStaffMessaging()

const persistSidebarState = () => {
    if (typeof window !== "undefined") {
        localStorage.setItem("messagingSideBar", layout.messagingSidebar.show.toString())
        localStorage.setItem("messagingSideBarMicro", (!!layout.messagingSidebar.micro).toString())
    }
}

const mobileQuery = globalThis.window?.matchMedia?.("(max-width: 767px)")
const isMobile = ref(mobileQuery?.matches ?? false)
const onMobileQueryChange = (event: MediaQueryListEvent) => {
    isMobile.value = event.matches
    if (event.matches) layout.messagingSidebar.show = false
}

const sumBadgeCounts = (rows: Record<string, { count: number }> | null | undefined, keys: string[]) =>
    Object.entries(rows ?? {}).filter(([key]) => keys.includes(key)).reduce((total, [, row]) => total + row.count, 0)

const stripBadgeGroups = computed(() => [
    {
        key: "tickets",
        icon: "fal fa-life-ring",
        label: ctrans("Tickets"),
        counts: [
            { key: "queue", label: ctrans("Tickets to fix"), class: "text-lime-300", value: layout.ticket_badges?.queue ? sumBadgeCounts(layout.ticket_badges.queue, ["assigned_to_me", "collaborating", "replied", "qa_to_check"]) : 0 },
            { key: "mine", label: ctrans("My tickets"), class: "text-lime-100", value: sumBadgeCounts(layout.ticket_badges?.mine, ["to_do", "in_progress", "waiting"]) },
        ],
    },
    {
        key: "tasks",
        icon: "fal fa-tasks",
        label: ctrans("Tasks"),
        counts: [
            { key: "mine", label: ctrans("My tasks"), class: "text-cyan-300", value: sumBadgeCounts(layout.task_badges?.mine, ["todo", "in_progress"]) },
            { key: "created", label: ctrans("Tasks I created"), class: "text-cyan-100", value: layout.task_badges?.created?.open ?? 0 },
        ],
    },
    {
        key: "orders",
        icon: "fal fa-shopping-cart",
        label: ctrans("Orders"),
        counts: [
            { key: "dispatching", label: ctrans("Orders waiting in the warehouse"), class: "text-amber-300", value: layout?.dispatching_waiting_count ?? 0 },
            { key: "crm_waiting", label: ctrans("Orders waiting in CRM"), class: "text-purple-300", value: layout?.crm_waiting_count ?? 0 },
            { key: "crm_return", label: ctrans("Orders with returns"), class: "text-blue-300", value: layout?.crm_return_count ?? 0 },
            { key: "faire", label: ctrans("Faire orders not imported"), class: "text-sky-300", value: layout?.faire_skipped_count ?? 0 },
        ],
    },
    {
        key: "catalogue",
        icon: "fal fa-cube",
        label: ctrans("Catalogue"),
        counts: [
            { key: "master", label: ctrans("Prices not matching master"), class: "text-rose-300", value: layout?.master_updated_count ?? 0 },
            { key: "review", label: ctrans("Master text changed"), class: "text-emerald-300", value: layout?.products_need_review_count ?? 0 },
        ],
    },
].map((group) => ({ ...group, counts: group.counts.filter((count) => count.value > 0) })).filter((group) => group.counts.length))

const isMicro = computed(() => (isMobile.value ? !layout.messagingSidebar.show : layout.messagingSidebar.micro))

const handleToggle = () => {
    const bar = layout.messagingSidebar
    if (isMobile.value) {
        bar.show = !bar.show
        if (bar.show) bar.micro = false
        return
    }
    if (bar.micro) {
        bar.micro = false
    } else {
        bar.show = !bar.show
    }
    persistSidebarState()
}

const enterMicro = () => {
    layout.messagingSidebar.show = false
    layout.messagingSidebar.micro = true
    persistSidebarState()
}

const expandSidebar = () => {
    layout.messagingSidebar.show = true
    layout.messagingSidebar.micro = false
    persistSidebarState()
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
    coworkers.value = data.data ?? []
}

const fetchSearchResults = async (q: string) => {
    if (!q) {
        searchResults.value = []
        return
    }
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params: { q } })
    searchResults.value = data.data ?? []
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
        expandSidebar()
        await nextTick()
    }
    setTimeout(() => openPlusSearch(), 50)
}

const closeSearch = () => {
    plusOpened.value = false
    search.value = ""
    searchResults.value = []
}

const isOnline = (id: number) => useLiveUsers().liveUsers[id]?.action === 'navigate'

const getCurrentPage = (coworkerId: number) => useLiveUsers().liveUsers[coworkerId]?.current_page

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

const allOnlineCount = computed(() => (nowTick.value, coworkers.value.filter((c) => presence(c) === 'online' && c.id !== myId.value).length))
const orgOnlineCount = computed(() => (nowTick.value, coworkers.value.filter((c) => inSelectedOrg(c) && presence(c) === 'online' && c.id !== myId.value).length))
const teamOnlineCount = computed(() => (nowTick.value, teamOnlineCoworkers.value.filter((c) => c.id !== myId.value).length))

const showInput = computed(() => plusOpened.value)
const searchPlaceholder = computed(() => ctrans('Find a coworker…'))
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

const myOrgs = computed(() => (layout.organisations?.data || []).filter((o: any) => o.label))
const selectedOrgId = ref<number | null>(null)
const orgPickerOpen = ref(false)
const selectedOrg = computed(() => myOrgs.value.find((o: any) => o.id === selectedOrgId.value) || myOrgs.value[0])

const orgTooltip = computed(() => selectedOrg.value?.slug || ctrans('Online in my organisation'))

const inSelectedOrg = (c: StaffCoworker) => selectedOrg.value
    ? (c.organisation_ids || []).includes(selectedOrg.value.id)
    : c.is_close

type SideBarTab = "all" | "org" | "team" | "messages"
const activeTab = ref<SideBarTab>("messages")
const selectTab = (tab: SideBarTab) => {
    if (!layout.messagingSidebar.show) expandSidebar()
    // Clicking the org tab while it is already open swaps which single org is shown
    if (tab === "org" && activeTab.value === "org" && myOrgs.value.length > 1) {
        orgPickerOpen.value = !orgPickerOpen.value
        return
    }
    orgPickerOpen.value = false
    activeTab.value = tab
}
const pickOrg = (orgId: number) => {
    selectedOrgId.value = orgId
    activeTab.value = "org"
    orgPickerOpen.value = false
}

const myId = computed(() => layout.user?.id)
const conversationTitle = (conversation: any) =>
    conversation.name || conversation.participants.filter((p: any) => p.id !== myId.value).map((p: any) => p.name).join(", ")
const conversationOtherId = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.id ?? null
const conversationAvatar = (conversation: any) =>
    conversation.participants.find((p: any) => p.id !== myId.value)?.avatar ?? null

const filteredPeopleList = computed(() => {
    if (activeTab.value === "all") {
        return coworkers.value.filter((c) => presence(c) === 'online' && c.id !== myId.value).sort(byName)
    }
    if (activeTab.value === "org") {
        return coworkers.value.filter((c) => inSelectedOrg(c) && presence(c) === 'online' && c.id !== myId.value).sort(byName)
    }
    if (activeTab.value === "team") {
        return filteredTeamCoworkers.value
    }
    return []
})

const tabHeader = computed(() => {
    if (activeTab.value === "all") return `${ctrans('Online now')} (${filteredPeopleList.value.length})`
    if (activeTab.value === "org") return `${selectedOrg.value?.slug ?? ctrans('My organisation')} (${filteredPeopleList.value.length})`
    if (activeTab.value === "team") return `${ctrans('My team')} (${teamOnlineCount.value}/${teamCoworkers.value.length} ${ctrans('online')})`
    return `${ctrans('Messages')} (${conversationsSummary.value.total}, ${conversationsSummary.value.unread} ${ctrans('unread')})`
})

const listedConversations = computed(() => store.showHiddenChats ? store.conversations : store.conversations.filter((conversation) => !isHiddenChat(conversation)))

const conversationsSummary = computed(() => ({
    total: listedConversations.value.length,
    unread: listedConversations.value.reduce((sum, c) => sum + (c.unread_count || 0), 0),
}))

const tabs = computed(() => [
    { key: "all" as SideBarTab, icon: "fal fa-gopuram", color: "text-[var(--chat-green)]", label: ctrans('Everyone online'), count: allOnlineCount.value },
    { key: "org" as SideBarTab, icon: "fal fa-home-alt", color: "text-[var(--chat-cyan)]", label: ctrans('Online in my organisation'), count: orgOnlineCount.value },
    { key: "team" as SideBarTab, icon: "fal fa-heart", color: "text-[var(--chat-accent)]", label: ctrans('My team'), count: teamOnlineCount.value },
    { key: "messages" as SideBarTab, icon: "fal fa-comments", color: "text-[var(--chat-label)]", label: ctrans('Messages'), count: conversationsSummary.value.total, badge: store.alertingUnread, quietBadge: store.totalUnread - store.alertingUnread },
])

const unreadBadgeClass = (conversation: any) =>
    conversation.has_mention ? 'bg-[var(--chat-accent)] text-white' : (isAlerting(conversation) ? 'bg-[var(--chat-red)] text-white' : 'bg-[var(--chat-line)] text-[var(--chat-text)]')

const WORK_THREAD_LABELS: Record<string, string> = {
    StaffTask: ctrans("Task"),
    Order: ctrans("Order"),
    DeliveryNote: ctrans("Delivery"),
    PickingSession: ctrans("Picking"),
    ChatSession: ctrans("CRM"),
}

const lastMessagePreview = (conversation: any) => {
    if (conversation.last_message) return useTruncate(conversation.last_message, 26)
    return conversation.last_message_at ? ctrans("Photo") : ctrans("No messages yet")
}

const workThreadLabel = (conversation: any) => (isWorkThread(conversation) ? WORK_THREAD_LABELS[conversation.context_type] ?? ctrans("Work") : null)

const RAIL_NEWEST_CHATS = 3

const newestConversationsAll = computed(() => [...listedConversations.value].sort((a, b) => Date.parse(b.last_message_at ?? "") - Date.parse(a.last_message_at ?? "") || 0))
const newestConversations = computed(() => newestConversationsAll.value.slice(0, RAIL_NEWEST_CHATS))
const newerConversationsHidden = computed(() => Math.max(0, newestConversationsAll.value.length - RAIL_NEWEST_CHATS))

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
    layout.messagingSidebar.micro = !layout.messagingSidebar.show && localStorage.getItem("messagingSideBarMicro") === "true"
    if (isMobile.value) layout.messagingSidebar.show = false
    mobileQuery?.addEventListener("change", onMobileQueryChange)
    fetchCoworkers("")
    store.fetchConversations()
    refreshInterval = setInterval(() => fetchCoworkers(search.value), 60000)
    tickInterval = setInterval(() => { nowTick.value++ }, 60000)
})

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval)
    if (tickInterval) clearInterval(tickInterval)
    mobileQuery?.removeEventListener("change", onMobileQueryChange)
    if (searchTimeout) clearTimeout(searchTimeout)
})
</script>

<template>
    <div v-if="isMobile && layout.messagingSidebar.show" class="fixed inset-0 z-[21] bg-gray-900/30 md:hidden" aria-hidden="true" @click="handleToggle" />
    <div
        class="flex flex-col fixed inset-y-0 right-0 h-full bg-[var(--chat-bg)] border-l border-[var(--chat-line)] z-[22] transition-all duration-300 ease-in-out"
        :class="[
            layout.messagingSidebar.show ? 'w-56' : 'w-6',
            layout.messagingSidebar.show ? 'md:w-56' : (layout.messagingSidebar.micro ? 'md:w-6' : 'md:w-12'),
        ]"
        id="messagingSidebar">
        <!-- Toggle: collapse-expand MessagingSideBar -->
        <div
            @click="handleToggle"
            class="absolute z-10 left-0 top-2/4 -translate-y-full -translate-x-2/3 lg:-translate-x-1/2 w-7 lg:w-5 aspect-square border border-[var(--chat-muted)] rounded-full bg-[var(--chat-line)] flex justify-center items-center cursor-pointer"
            :title="layout.messagingSidebar.show ? 'Collapse the bar' : 'Expand the bar'">
            <FontAwesomeIcon
                icon="far fa-chevron-left"
                class="h-3 lg:h-[10px] leading-none transition-all duration-300 ease-in-out text-[var(--chat-text)]"
                fixed-width aria-hidden="true"
                :class="layout.messagingSidebar.show ? 'rotate-180' : ''" />
        </div>

        <!-- MICRO: super-thin strip with the counts; click to grow back to the rail -->
        <div v-if="isMicro" class="flex-1 flex flex-col items-center gap-y-2 pt-3 cursor-pointer text-xxs tabular-nums leading-none" v-tooltip="ctrans('Show messaging bar')" @click="handleToggle">
            <WhatsappCallAlert micro />
            <CustomersWaiting v-if="layout?.user?.is_agent" micro />
            <template v-for="group in stripBadgeGroups" :key="'micro-badges-' + group.key">
                <div class="flex flex-col items-center gap-y-1.5" :title="group.label">
                    <FontAwesomeIcon :icon="group.icon" class="h-2.5 w-2.5 text-[var(--chat-text)] opacity-80" aria-hidden="true" />
                    <span v-for="count in group.counts" :key="count.key" :class="count.class" :title="count.label">{{ compactCount(count.value) }}</span>
                </div>
                <div class="w-3 border-t border-[var(--chat-line)]" />
            </template>

            <div v-for="tab in tabs" :key="'micro-people-' + tab.key" class="flex flex-col items-center gap-y-1" :title="tab.label">
                <FontAwesomeIcon :icon="tab.icon" class="h-2.5 w-2.5 opacity-80" :class="tab.color" aria-hidden="true" />
                <span v-if="tab.key === 'messages'" :class="store.alertingUnread > 0 ? 'text-white bg-[var(--chat-red)] rounded-full px-0.5 py-0.5 -mx-1' : (store.totalUnread > 0 ? 'text-[var(--chat-text)]' : 'text-[var(--chat-label)]')">{{ compactCount(store.alertingUnread || store.totalUnread) }}</span>
                <span v-else :class="tab.color">{{ compactCount(tab.count) }}</span>
            </div>


        </div>

        <template v-else>
        <RailControls />
        <div class="flex-1 min-h-0 flex flex-col" :class="!layout.messagingSidebar.show && 'overflow-y-auto custom-hide-scrollbar'">

        <!-- Section tabs: each one swaps the view below -->
        <!-- COLLAPSED -->
        <div v-if="!layout.messagingSidebar.show" class="flex flex-col items-center pt-2 border-b border-[var(--chat-line)]">
            <div
                v-for="tab in tabs"
                :key="'rail-tab-' + tab.key"
                class="w-full flex items-center justify-center gap-x-1 py-1.5 cursor-pointer border-l-2"
                :class="activeTab === tab.key ? 'border-[var(--chat-accent)] bg-[var(--chat-line)]' : 'border-transparent'"
                v-tooltip="tab.key === 'org' ? orgTooltip : tab.label"
                @click="selectTab(tab.key)">
                <span class="relative">
                    <FontAwesomeIcon :icon="tab.icon" :class="tab.color" class="text-xs" fixed-width aria-hidden="true" />
                    <span v-if="tab.badge" class="absolute -top-1.5 -right-1.5 bg-[var(--chat-red)] text-white rounded-full h-3 min-w-[0.75rem] px-0.5 flex items-center justify-center text-[8px] leading-none tabular-nums">{{ tab.badge > 99 ? 99 : tab.badge }}</span>
                    <span v-else-if="tab.quietBadge" class="absolute -top-1.5 -right-1.5 bg-[var(--chat-line)] text-[var(--chat-text)] rounded-full h-3 min-w-[0.75rem] px-0.5 flex items-center justify-center text-[8px] leading-none tabular-nums">{{ tab.quietBadge > 99 ? 99 : tab.quietBadge }}</span>
                </span>
                <span class="text-xxs tabular-nums text-[var(--chat-text)]">{{ tab.count }}</span>
            </div>
        </div>

        <!-- EXPANDED -->
        <div v-else class="relative flex items-stretch border-b border-[var(--chat-line)] text-xs">
            <div
                v-for="tab in tabs"
                :key="'tab-' + tab.key"
                class="flex-1 flex items-center justify-center gap-x-1 py-2 cursor-pointer border-b-2 -mb-px"
                :class="activeTab === tab.key ? 'border-[var(--chat-accent)] bg-[var(--chat-line)]' : 'border-[var(--chat-line)] hover:bg-[var(--chat-line)]/50'"
                v-tooltip="tab.key === 'org' ? orgTooltip : tab.label"
                @click="selectTab(tab.key)">
                <span class="relative">
                    <FontAwesomeIcon :icon="tab.icon" :class="tab.color" class="text-xs" fixed-width aria-hidden="true" />
                    <span v-if="tab.badge" class="absolute -top-1.5 -right-1.5 bg-[var(--chat-red)] text-white rounded-full h-3 min-w-[0.75rem] px-0.5 flex items-center justify-center text-[8px] leading-none tabular-nums">{{ tab.badge > 99 ? 99 : tab.badge }}</span>
                    <span v-else-if="tab.quietBadge" class="absolute -top-1.5 -right-1.5 bg-[var(--chat-line)] text-[var(--chat-text)] rounded-full h-3 min-w-[0.75rem] px-0.5 flex items-center justify-center text-[8px] leading-none tabular-nums">{{ tab.quietBadge > 99 ? 99 : tab.quietBadge }}</span>
                </span>
                <span class="tabular-nums" :class="activeTab === tab.key ? 'text-[var(--chat-text)]' : 'text-[var(--chat-label)]'">{{ tab.count }}</span>
            </div>

            <!-- Org quick-swap picker: click the org tab again to change which single org is shown -->
            <div v-if="orgPickerOpen" class="absolute left-3 top-full mt-1 z-20 min-w-[10rem] rounded-md border border-[var(--chat-line)] bg-[var(--chat-bg)] shadow-lg py-1">
                <button
                    v-for="org in myOrgs"
                    :key="'org-pick-' + org.id"
                    class="w-full text-left px-3 py-1.5 text-xs hover:bg-[var(--chat-line)]"
                    :class="selectedOrg?.id === org.id ? 'text-[var(--chat-accent)]' : 'text-[var(--chat-text)]'"
                    @click="pickOrg(org.id)">
                    {{ org.label }}
                </button>
            </div>
        </div>

        <!-- Messaging button -->
        <div v-if="!layout.messagingSidebar.show" class="pt-2 flex justify-center items-center">
            <button class="h-9 w-9 flex items-center justify-center text-[var(--chat-muted)] hover:text-[var(--chat-text)]" v-tooltip="ctrans('Open messaging')" @click="openFullMessaging">
                <FontAwesomeIcon icon="fal fa-expand-alt" fixed-width aria-hidden="true" />
            </button>
        </div>
        <div v-else-if="activeTab === 'messages'" class="pt-2 pb-1 px-3 flex items-center justify-between">
            <button class="flex items-center gap-x-3 text-[var(--chat-muted)] hover:text-[var(--chat-text)]" @click="openFullMessaging">
                <FontAwesomeIcon icon="fal fa-expand-alt" fixed-width aria-hidden="true" />
                <span class="text-xs text-[var(--chat-text)]">{{ ctrans('Messaging') }}</span>
            </button>
            <span class="flex shrink-0 items-center gap-x-2">
                <button
                    type="button"
                    class="shrink-0 transition-colors"
                    :class="store.showHiddenChats ? 'text-[var(--chat-accent)] hover:text-[var(--chat-text)]' : 'text-[var(--chat-muted)] hover:text-[var(--chat-text)]'"
                    :aria-pressed="store.showHiddenChats"
                    v-tooltip="store.showHiddenChats ? ctrans('Show hidden chat: on') : ctrans('Show hidden chat: off')"
                    @click="store.toggleShowHiddenChats()">
                    <FontAwesomeIcon :icon="store.showHiddenChats ? 'fal fa-eye' : 'fal fa-eye-slash'" fixed-width aria-hidden="true" />
                </button>
                <button v-if="!plusOpened" class="shrink-0 text-[var(--chat-accent)] hover:text-[var(--chat-text)]" @click="openPlusSearch" v-tooltip="ctrans('New message')">
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                </button>
            </span>
        </div>

        <!-- COLLAPSED: avatar rail -->
        <div v-if="!layout.messagingSidebar.show" class="flex-1 shrink-0 flex flex-col items-center gap-y-3 pt-4 pb-2">
            <template v-if="!store.fetched">
                <span v-for="placeholder in RAIL_NEWEST_CHATS" :key="'rail-chat-skeleton-' + placeholder" class="h-7 w-7 shrink-0 animate-pulse rounded-full bg-[var(--chat-line)]" aria-hidden="true" />
            </template>
            <button
                v-for="conversation in newestConversations"
                :key="'rail-chat-' + conversation.ulid"
                type="button"
                v-tooltip="{ content: conversationTitle(conversation), placement: 'left' }"
                :aria-label="conversationTitle(conversation)"
                class="relative shrink-0 rounded-full transition duration-200 hover:-translate-y-0.5"
                @click="store.openConversation(conversation.ulid)">
                <span class="flex rounded-full ring-2 ring-offset-1 ring-offset-[var(--chat-bg)]" :class="conversation.unread_count > 0 ? 'ring-[var(--chat-red)]' : 'ring-transparent'">
                    <span v-if="conversation.type === 'group'" class="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--chat-line)] text-[var(--chat-accent)]">
                        <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                    </span>
                    <TicketUserAvatar v-else :name="conversationTitle(conversation)" :avatar="conversationAvatar(conversation)" size="md" />
                </span>
                <span
                    v-if="conversation.type !== 'group'"
                    class="absolute bottom-0 right-0 h-2 w-2 rounded-full ring-1 ring-[var(--chat-bg)]"
                    :class="isOnline(conversationOtherId(conversation)) ? 'bg-[var(--chat-green)]' : 'bg-[var(--chat-muted)]'" />
                <span
                    v-if="conversation.unread_count > 0"
                    class="absolute -right-1.5 -top-1.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-[var(--chat-red)] px-1 text-[9px] font-semibold leading-none text-white ring-2 ring-[var(--chat-bg)]">
                    {{ conversation.unread_count > 99 ? "99+" : conversation.unread_count }}
                </span>
            </button>
            <button
                v-if="newerConversationsHidden > 0"
                type="button"
                class="relative h-7 w-7 rounded-full bg-[var(--chat-line)] shrink-0 flex items-center justify-center text-xxs text-[var(--chat-text)]"
                v-tooltip="ctrans('Show all')"
                @click="expandSidebar">
                +{{ newerConversationsHidden }}
            </button>
            <button
                class="h-9 w-9 rounded-full bg-transparent border border-dashed border-[var(--chat-muted)] shrink-0 flex items-center justify-center text-[var(--chat-muted)] hover:text-[var(--chat-text)] hover:border-[var(--chat-text)]"
                v-tooltip="ctrans('New message')"
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
                    <div class="relative">
                        <div class="relative h-6 w-6 rounded-full overflow-hidden bg-[var(--chat-line)] shrink-0">
                            <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                            <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[var(--chat-muted)]" fixed-width aria-hidden="true" />
                        </div>
                        <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[var(--chat-bg)]" :class="[presence(coworker) === 'online' ? 'bg-[var(--chat-green)]' : (presence(coworker) === 'idle' ? 'bg-[var(--chat-yellow)]' : 'bg-[var(--chat-muted)]')]" :title="presence(coworker) === 'idle' ? ctrans('Idle') : ''" />
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
                    <span role="button" tabindex="0" class="shrink-0" @click="toggleTeam(coworker, $event)" v-tooltip="coworker.in_team ? ctrans('In my team') : ctrans('Add to my team')">
                        <FontAwesomeIcon :icon="coworker.in_team ? 'fas fa-star' : 'fal fa-star'" :class="coworker.in_team ? 'text-[var(--chat-yellow)]' : 'text-[var(--chat-muted)]'" fixed-width aria-hidden="true" />
                    </span>
                </div>
            </template>

            <!-- MESSAGES view -->
            <template v-else-if="activeTab === 'messages'">
                <div class="px-3 pt-2 pb-1 flex items-center text-xs text-[var(--chat-muted)]">
                    <span>{{ tabHeader }}</span>
                </div>
                <button
                    v-for="conversation in newestConversationsAll"
                    :key="'conv-' + conversation.ulid"
                    class="group w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[var(--chat-line)] text-left"
                    @click="store.openConversation(conversation.ulid)">
                    <div v-if="conversation.type === 'group'" class="h-6 w-6 rounded-full bg-[var(--chat-line)] flex items-center justify-center shrink-0">
                        <FontAwesomeIcon icon="fal fa-comments" class="text-[var(--chat-accent)]" fixed-width aria-hidden="true" />
                    </div>
                    <div v-else class="relative shrink-0">
                        <TicketUserAvatar :name="conversationTitle(conversation)" :avatar="conversationAvatar(conversation)" size="sm" />
                        <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[var(--chat-bg)]" :class="isOnline(conversationOtherId(conversation)) ? 'bg-[var(--chat-green)]' : 'bg-[var(--chat-muted)]'" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs truncate" :class="conversation.unread_count > 0 ? 'font-semibold text-white' : 'text-[var(--chat-text)]'">{{ conversationTitle(conversation) }}</div>
                        <div class="flex min-w-0 items-center gap-1 text-xxs text-[var(--chat-muted)]">
                            <span
                                v-if="workThreadLabel(conversation)"
                                v-tooltip="ctrans('Rings only when you are mentioned')"
                                class="shrink-0 rounded bg-[var(--chat-line)] px-1 text-[9px] uppercase tracking-wide text-[var(--chat-label)]">
                                {{ workThreadLabel(conversation) }}
                            </span>
                            <span
                                v-if="isWorkThread(conversation)"
                                v-tooltip="conversation.is_watching ? ctrans('Watching: always listed') : ctrans('Not watching: only listed when hidden chats are shown')"
                                class="shrink-0"
                                :class="conversation.is_watching ? 'text-[var(--chat-accent)]' : 'text-[var(--chat-muted)] opacity-70'">
                                <FontAwesomeIcon :icon="conversation.is_watching ? 'fas fa-eye' : 'fal fa-eye-slash'" fixed-width aria-hidden="true" />
                            </span>
                            <span v-if="conversation.task?.status_label" v-tooltip="ctrans('Task status')" class="inline-flex shrink-0 items-center gap-0.5 text-[var(--chat-label)]">
                                <FontAwesomeIcon v-if="conversation.task.status_icon" :icon="conversation.task.status_icon.icon" :class="conversation.task.status_icon.class" fixed-width aria-hidden="true" />
                                {{ conversation.task.status_label }}
                            </span>
                            <span class="truncate" :class="!conversation.last_message && 'italic opacity-70'">{{ lastMessagePreview(conversation) }}</span>
                        </div>
                    </div>
                    <span v-if="conversation.unread_count > 0" class="rounded-full h-4 min-w-[1rem] px-1 flex items-center justify-center text-xxs shrink-0" :class="unreadBadgeClass(conversation)">{{ conversation.unread_count }}</span>
                    <span
                        v-if="canArchiveConversation(conversation)"
                        role="button" tabindex="0"
                        class="shrink-0 opacity-0 group-hover:opacity-100 text-[var(--chat-muted)] hover:text-[var(--chat-text)]"
                        v-tooltip="ctrans('Archive chat')"
                        @click.stop="store.closeConversation(conversation.ulid)">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    </span>
                </button>
                <div v-if="!store.fetched" class="space-y-1 px-3 py-1" :aria-label="ctrans('Loading…')" aria-busy="true">
                    <div v-for="row in 3" :key="'chat-skeleton-' + row" class="flex items-center gap-x-2 py-1">
                        <span class="h-6 w-6 shrink-0 animate-pulse rounded-full bg-[var(--chat-line)]" />
                        <span class="flex-1 space-y-1">
                            <span class="block h-2.5 animate-pulse rounded bg-[var(--chat-line)]" :class="row === 2 ? 'w-2/3' : 'w-4/5'" />
                            <span class="block h-2 w-1/2 animate-pulse rounded bg-[var(--chat-line)] opacity-60" />
                        </span>
                    </div>
                </div>
                <div v-else-if="!newestConversationsAll.length" class="px-3 py-2 text-xxs text-[var(--chat-muted)]">{{ ctrans('No conversations yet') }}</div>
            </template>

            <!-- PEOPLE views: all / org / team -->
            <template v-else>
            <div class="px-3 pt-2 pb-1 flex items-center justify-between text-xs text-[var(--chat-muted)]">
                <span class="truncate">{{ tabHeader }}</span>
                <span v-if="activeTab === 'team'" class="shrink-0 flex items-center gap-x-1.5">
                    <button class="text-[var(--chat-accent)] hover:text-[var(--chat-text)]" @click="isManageTeamOpen = true" v-tooltip="ctrans('Edit my team')">
                        <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                    </button>
                    <button class="text-[var(--chat-accent)] hover:text-[var(--chat-text)]" @click="isManageTeamOpen = true" v-tooltip="ctrans('Add to my team')">
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                    </button>
                </span>
                <button v-else-if="activeTab === 'org' && myOrgs.length > 1" class="shrink-0 text-[var(--chat-accent)] hover:text-[var(--chat-text)]" @click="orgPickerOpen = !orgPickerOpen" v-tooltip="ctrans('Change organisation')">
                    <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                </button>
            </div>
            <div
                v-for="coworker in filteredPeopleList"
                :key="'people-' + coworker.id"
                role="button" tabindex="0"
                class="group w-full flex items-center gap-x-2 px-3 py-1.5 hover:bg-[var(--chat-line)] text-left cursor-pointer"
                :class="presence(coworker) !== 'offline' ? '' : 'opacity-40'"
                @click="openUser(coworker.id)">
                <div class="relative">
                    <div class="relative h-6 w-6 rounded-full overflow-hidden bg-[var(--chat-line)] shrink-0">
                        <Image v-if="coworker.avatar" :src="coworker.avatar" :alt="coworker.name" image-cover />
                        <FontAwesomeIcon v-else icon="fal fa-user" class="flex items-center justify-center h-full text-[var(--chat-muted)]" fixed-width aria-hidden="true" />
                    </div>
                    <span class="absolute bottom-0 right-0 h-1.5 w-1.5 rounded-full ring-1 ring-[var(--chat-bg)]" :class="[presence(coworker) === 'online' ? 'bg-[var(--chat-green)]' : (presence(coworker) === 'idle' ? 'bg-[var(--chat-yellow)]' : 'bg-[var(--chat-muted)]')]" :title="presence(coworker) === 'idle' ? ctrans('Idle') : ''" />
                </div>
                <div class="flex-1 flex flex-col min-w-0">
                    <span class="text-xs truncate text-[var(--chat-text)] flex items-center gap-x-1">
                        {{ coworker.name }}
                        <FontAwesomeIcon v-if="coworker.on_call" icon="fas fa-phone"
                            class="text-[9px] text-[var(--chat-green)] shrink-0"
                            v-tooltip="ctrans('On a phone call')" fixed-width aria-hidden="true" />
                    </span>
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
                <span role="button" tabindex="0" class="shrink-0 opacity-0 group-hover:opacity-100" @click.stop="store.openWithUser(coworker.id)" v-tooltip="ctrans('Message')">
                    <FontAwesomeIcon icon="fal fa-comment" class="text-[var(--chat-muted)] hover:text-[var(--chat-text)]" fixed-width aria-hidden="true" />
                </span>
                <span v-if="activeTab !== 'team'" role="button" tabindex="0" class="shrink-0 opacity-0 group-hover:opacity-100" @click="toggleTeam(coworker, $event)" v-tooltip="coworker.in_team ? ctrans('In my team') : ctrans('Add to my team')">
                    <FontAwesomeIcon :icon="coworker.in_team ? 'fas fa-star' : 'fal fa-star'" :class="coworker.in_team ? 'text-[var(--chat-yellow)]' : 'text-[var(--chat-muted)]'" fixed-width aria-hidden="true" />
                </span>
            </div>
            <div v-if="!filteredPeopleList.length" class="px-3 py-2 text-xxs text-[var(--chat-muted)]">
                {{ activeTab === 'team' && !teamCoworkers.length ? ctrans('No team members yet') : ctrans('Nobody online') }}
            </div>
            </template>
        </div>
        </div>

        <!-- Bottom-pinned: micro-view buttons -->
        <div class="mt-auto shrink-0 flex flex-col pb-2">
            <div class="w-full border-t border-[var(--chat-line)] pt-1 flex items-center gap-x-1" :class="layout.messagingSidebar.show ? 'flex-row justify-end pr-2' : 'flex-col gap-y-1'">
                <button
                    v-if="!layout.messagingSidebar.show"
                    class="h-7 w-7 flex items-center justify-center text-[var(--chat-muted)] hover:text-[var(--chat-text)]"
                    v-tooltip="ctrans('Expand messaging bar')"
                    @click="expandSidebar">
                    <FontAwesomeIcon icon="fal fa-chevron-double-left" fixed-width aria-hidden="true" />
                </button>
                <button
                    v-else
                    class="h-7 w-7 flex items-center justify-center text-[var(--chat-muted)] hover:text-[var(--chat-text)]"
                    v-tooltip="ctrans('Collapse messaging bar')"
                    @click="handleToggle">
                    <FontAwesomeIcon icon="far fa-chevron-left" class="rotate-180" fixed-width aria-hidden="true" />
                </button>
                <button
                    class="h-7 w-7 flex items-center justify-center text-[var(--chat-muted)] hover:text-[var(--chat-text)]"
                    v-tooltip="ctrans('Hide messaging bar')"
                    @click="enterMicro">
                    <FontAwesomeIcon icon="fal fa-chevron-double-right" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>
        </template>
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
