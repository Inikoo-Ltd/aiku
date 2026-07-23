<script setup lang="ts">
import { ref, computed, inject, onMounted, onUnmounted, watch, nextTick } from "vue"
import { Head } from "@inertiajs/vue3"
import { watchDebounced } from "@vueuse/core"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import MessageAreaAgent from "@/Components/Chat/Agent/MessageAreaAgent.vue"
import ChatConversationSidePanel from "@/Components/Chat/ChatConversationSidePanel.vue"
import SettingChat from "@/Components/Chat/SettingChat.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Image from "@common/Components/Image.vue"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUser, faSearch, faTimes } from "@far"
import { faCog, faStar, faAngleLeft, faAngleRight } from "@fal"
import {
    Contact,
    SessionAPI,
    ChatMessage,
} from "@/types/Chat/chat"

const props = defineProps<{
    title: string
    pageHead: any
    breadcrumbs: any
    organisation: { id: number; slug: string; name: string }
    inboxes: Array<{ id: number; name: string; slug: string; type: string | null }>
    selectedSessionUlid?: string | null
    initialSession?: any | null
}>()

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const myAgentId = layout.user?.id
const myAgentShop = layout.user?.agent_shops ?? []

const PLUS_8_HOURS = layout.app?.environment === "local" ? 8 * 60 * 60 * 1000 : 0

const contacts = ref<Contact[]>([])
const activeTab = ref<"waiting" | "active" | "closed">("waiting")
const viewMode = ref<"my" | "team">("my")
const searchQuery = ref("")
const showSearch = ref(false)
const currentPage = ref(1)
const hasMore = ref(false)
const isLoadingMore = ref(false)
const isAssigning = ref<Record<string, boolean>>({})
const errorPerContact = ref<Record<string, string>>({})

const selectedSession = ref<SessionAPI | null>(null)
const messages = ref<ChatMessage[]>([])

const panelSession = computed(() => {
    const s = selectedSession.value
    if (!s) return null
    return {
        ulid: String(s.ulid),
        contact_name: s.contact_name || s.guest_identifier || "Guest",
        is_guest: !s.web_user?.id,
        shop_name: s.shop?.name ?? null,
        status: s.status,
        priority: s.priority ?? null,
        assigned_agent: s.assigned_agent?.name ?? null,
        started: s.created_at ?? null,
    }
})

const selectedItemStyle = {
    backgroundColor: "color-mix(in srgb, var(--theme-color-4) 18%, white)",
    boxShadow: "inset 3px 0 0 var(--theme-color-4)",
}

const sidePanelVisible = ref(false)

const chatSettingVisible = ref(false)
const settingInitialTab = ref<"general" | "jira">("general")
const openChatSettings = () => {
    settingInitialTab.value = "general"
    chatSettingVisible.value = true
}
const onOpenJiraSettings = () => {
    settingInitialTab.value = "jira"
    chatSettingVisible.value = true
}

const formatTime = (timestamp: number) => {
    const d = new Date(timestamp)
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
}

const mapSession = (s: SessionAPI): Contact => ({
    id: s.id,
    ulid: s.ulid,
    name: s.contact_name || s.guest_identifier || "",
    avatar: s.image ?? "",
    lastMessage: s.last_message?.message ?? "",
    lastMessageTime: s.last_message?.created_at
        ? formatTime(new Date(s.last_message.created_at).getTime() + PLUS_8_HOURS)
        : undefined,
    unread: s.unread_count,
    status: s.status,
    webUser: s.web_user,
    priority: s.priority,
    guest_profile: s.guest_profile,
    agent: s.assigned_agent,
    shop: s.shop,
    organisation: s.organisation,
})

const selectedShopId = ref<number | null>(props.inboxes?.[0]?.id ?? null)

const buildParams = (page: number) => ({
    statuses: [activeTab.value],
    assigned_to_me: myAgentId,
    organisation_id: props.organisation.id,
    page,
    ...(selectedShopId.value ? { shop_id: selectedShopId.value } : {}),
    ...(viewMode.value === "team" ? { view_team: 1 } : {}),
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
})

const reloadContacts = async () => {
    currentPage.value = 1
    hasMore.value = false
    try {
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, { params: buildParams(1) })
        contacts.value = res.data.data.sessions.map(mapSession)
        hasMore.value = res.data.data.pagination?.has_more ?? false
    } catch (e) {
        console.error("Failed to reload contacts:", e)
    }
}

const loadMore = async () => {
    if (isLoadingMore.value || !hasMore.value) return
    isLoadingMore.value = true
    try {
        const res = await axios.get(`${baseUrl}/app/api/chats/sessions`, { params: buildParams(currentPage.value + 1) })
        contacts.value = [...contacts.value, ...res.data.data.sessions.map(mapSession)]
        currentPage.value += 1
        hasMore.value = res.data.data.pagination?.has_more ?? false
    } catch (e) {
        console.error("Failed to load more contacts:", e)
    } finally {
        isLoadingMore.value = false
    }
}

const filteredContacts = computed(() =>
    contacts.value.filter(
        (c) => c.status === activeTab.value &&
            (!selectedShopId.value || c.shop?.id === selectedShopId.value)
    )
)

const selectedInbox = computed(() =>
    props.inboxes?.find((i) => i.id === selectedShopId.value) ?? props.inboxes?.[0] ?? null
)

const inboxRailCollapsed = ref(false)

const SHOP_COLORS = ["#6366f1", "#0ea5e9", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#ec4899", "#14b8a6"]

const shopInitials = (name: string) => {
    const words = (name || "?").trim().split(/\s+/).filter(Boolean)
    if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase()
    return (words[0] ?? "?").slice(0, 2).toUpperCase()
}

const shopAvatarStyle = (inbox: { id: number }) => {
    const color = SHOP_COLORS[(inbox.id ?? 0) % SHOP_COLORS.length]
    return { backgroundColor: color + "1A", color }
}

const selectShop = (shopId: number) => {
    if (selectedShopId.value === shopId) return
    selectedShopId.value = shopId
    selectedSession.value = null
    messages.value = []
    reloadContacts()
}

// Per-agent (My Chats) incoming-chat counts used for the badges.
const notifWaiting = ref<any[]>([])
const notifActive = ref<any[]>([])
const notifReopen = ref<any[]>([])

const fetchInboxNotifications = async () => {
    if (!myAgentId) return
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/users/${myAgentId}/agent-notifications`)
        notifWaiting.value = data?.data?.waiting ?? []
        notifActive.value = data?.data?.active ?? []
        notifReopen.value = data?.data?.reopen ?? []
    } catch (e) {
        // silent — badges are non-critical
    }
}

const tabUnread = computed(() => {
    const sid = selectedShopId.value
    const inShop = (arr: any[]) => (sid ? arr.filter((s) => s?.shop?.id === sid) : arr)
    return {
        waiting: inShop(notifWaiting.value).length,
        active: inShop(notifActive.value).length,
        closed: inShop(notifReopen.value).length,
    }
})

const shopUnread = computed<Record<number, number>>(() => {
    const map: Record<number, number> = {}
    for (const s of [...notifWaiting.value, ...notifActive.value, ...notifReopen.value]) {
        const sid = s?.shop?.id
        if (!sid) continue
        map[sid] = (map[sid] ?? 0) + 1
    }
    return map
})

const openChat = (c: Contact) => {
    selectedSession.value = {
        ulid: String(c.ulid),
        guest_identifier: c.name,
        status: c.status,
        priority: c.priority,
        web_user: c.webUser,
        guest_profile: c.guest_profile,
        assigned_agent: c.agent,
        shop: c.shop,
        organisation: c.organisation,
    } as SessionAPI
    messages.value = c.messages ?? []
    updateUrl(String(c.ulid))
}

const handleClickContact = (c: Contact) => {
    errorPerContact.value[c.ulid] = ""
    // Waiting chats open into an "Assign to me" step (no composer) until assigned.
    openChat(c)
}

const onMessagesRead = () => {
    // A chat was read → its unread badge should clear.
    reloadContacts()
    fetchInboxNotifications()
}

const onAssignSelfSuccess = async () => {
    // Covers assign-to-me, reopen, and take-over: the chat is now my active chat.
    // Refresh the lists and move it into My Chats › Active, keeping it open.
    const prev = selectedSession.value

    viewMode.value = "my"
    activeTab.value = "active"
    await reloadContacts()
    await nextTick()

    selectedSession.value = {
        ...(prev as SessionAPI),
        status: "active",
        assigned_agent: {
            id: (prev as any)?.assigned_agent?.id,
            user_id: myAgentId,
            name: layout.user?.contact_name ?? "",
        },
    } as SessionAPI
}

const updateUrl = (ulid: string) => {
    const url = route("grp.org.chat.inbox.conversation", [props.organisation.slug, ulid])
    window.history.replaceState(window.history.state, "", url)
}

const handleSendMessage = async ({ text, image, message_type, is_email_notif }: {
    text: string
    image?: File | null
    message_type: "text" | "image" | "file"
    tempId: number
    is_email_notif: boolean
}) => {
    if (!selectedSession.value?.ulid) return
    try {
        const formData = new FormData()
        formData.append("message_text", text ?? "")
        formData.append("message_type", message_type)
        formData.append("sender_type", "agent")
        formData.append("is_email_notif", String(is_email_notif ?? false))

        if (image) {
            formData.append(message_type === "image" ? "image" : "file", image)
        }

        await axios.post(
            route("grp.org.chat.agents.messages.send", [props.organisation.slug, selectedSession.value.ulid]),
            formData,
            { headers: { "Content-Type": "multipart/form-data" }, withCredentials: true }
        )
    } catch (error) {
        console.error("Error sending message:", error)
    }
}

const toggleSidePanel = () => { sidePanelVisible.value = !sidePanelVisible.value }
const showHistoryPanel = () => toggleSidePanel()
const showProfilePanel = () => toggleSidePanel()
const showMessageDetailsPanel = () => toggleSidePanel()
const closeSidePanel = () => { sidePanelVisible.value = false }

const closeSession = async () => {
    selectedSession.value = null
    await reloadContacts()
}

const onTransferAgentSuccess = async () => {
    sidePanelVisible.value = false
    selectedSession.value = null
    await reloadContacts()
}


watch([activeTab, viewMode], async () => {
    selectedSession.value = null
    messages.value = []
    if (viewMode.value === "team" && activeTab.value === "waiting") {
        activeTab.value = "active"
        return
    }
    await reloadContacts()
})

let scrollObserver: IntersectionObserver | null = null
const sentinelEl = ref<HTMLElement | null>(null)

const toggleSearch = () => {
    showSearch.value = !showSearch.value
    if (!showSearch.value) {
        searchQuery.value = ""
        reloadContacts()
    }
}

watchDebounced(searchQuery, () => reloadContacts(), { debounce: 400 })

const joinedChatListChannels: string[] = []

const buildInitialSession = () => {
    const init = props.initialSession
    if (!init) return
    selectedSession.value = {
        ulid: String(init.ulid),
        contact_name: init.contact_name,
        guest_identifier: init.guest_identifier ?? init.contact_name,
        status: init.status,
        priority: init.priority,
        web_user: init.web_user,
        guest_profile: init.guest_profile,
        assigned_agent: init.assigned_agent,
        shop: init.shop,
        organisation: init.organisation,
    } as SessionAPI
    messages.value = []
}

const openSelectedFromProp = () => {
    if (!props.selectedSessionUlid) return
    const c = contacts.value.find((ct) => String(ct.ulid) === String(props.selectedSessionUlid))
    if (c) {
        openChat(c)
    } else {
        selectedSession.value = { ulid: String(props.selectedSessionUlid) } as SessionAPI
        messages.value = []
    }
}

const onChatListEvent = (e: any) => {
    reloadContacts()
    fetchInboxNotifications()

    const s = e?.session
    const open = selectedSession.value
    if (!s || !open || String(s.ulid) !== String(open.ulid)) return

    // The currently-open chat changed elsewhere (assigned/taken over/closed).
    if (s.assigned_user_id && String(s.assigned_user_id) !== String(myAgentId)) {
        // Another agent took it over → disable the composer (take-over banner).
        selectedSession.value = {
            ...open,
            status: s.status ?? open.status,
            assigned_agent: {
                id: (open as any)?.assigned_agent?.id,
                user_id: s.assigned_user_id,
                name: s.assigned_agent_name ?? "Agent",
            } as any,
        } as SessionAPI
    } else if (s.status && s.status !== open.status) {
        selectedSession.value = { ...open, status: s.status } as SessionAPI
    }
}

onMounted(async () => {
    fetchInboxNotifications()

    const init = props.initialSession
    if (init && ["waiting", "active", "closed"].includes(init.status) && activeTab.value !== init.status) {
        // Triggers the tab watcher (which reloads the list and clears the selection).
        activeTab.value = init.status
    }

    await reloadContacts()
    await nextTick()

    if (init) {
        // Set the selection AFTER the tab watcher has flushed so it isn't cleared.
        buildInitialSession()
    } else {
        openSelectedFromProp()
    }

    if (window.Echo) {
        const shopIds: number[] = Array.isArray(myAgentShop) ? myAgentShop : []
        shopIds.forEach((shopId) => {
            const channel = `chat-list.${shopId}`
            joinedChatListChannels.push(channel)
            window.Echo.join(channel).listen(".chatlist", onChatListEvent)
        })
    }

    scrollObserver = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) loadMore() },
        { threshold: 0.1 }
    )
    watch(sentinelEl, (el) => {
        scrollObserver?.disconnect()
        if (el) scrollObserver?.observe(el)
    }, { immediate: true })
})

onUnmounted(() => {
    // Only detach this page's listener; do NOT Echo.leave() the shared
    // chat-list channel — the footer notification hub relies on it.
    joinedChatListChannels.forEach((channel) =>
        window.Echo?.join(channel).stopListening(".chatlist", onChatListEvent)
    )
    scrollObserver?.disconnect()
})
</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHead">
        <template #other>
            <button type="button" v-tooltip="trans('Chat settings')" @click="openChatSettings"
                class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors">
                <FontAwesomeIcon :icon="faCog" class="text-base" />
            </button>
        </template>
    </PageHeading>

    <Dialog v-model:visible="chatSettingVisible" modal :header="trans('Chat Settings')"
        :style="{ width: '90vw', maxWidth: '560px' }" :breakpoints="{ '640px': '95vw' }">
        <SettingChat :initial-tab="settingInitialTab" @close="chatSettingVisible = false" />
    </Dialog>

    <div class="flex border-t border-gray-200 h-[calc(100vh-10rem)] bg-white">
        <!-- PANEL 1: Inboxes (shops the agent handles) -->
        <div class="shrink-0 border-r border-gray-200 flex flex-col bg-gray-50 transition-all duration-200"
            :class="inboxRailCollapsed ? 'w-16' : 'w-52'">
            <!-- Header + collapse toggle -->
            <div class="border-b border-gray-200 flex items-center h-[41px]"
                :class="inboxRailCollapsed ? 'justify-center' : 'justify-between px-3'">
                <span v-if="!inboxRailCollapsed"
                    class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide">
                    {{ trans("Inboxes") }}
                </span>
                <button type="button" @click="inboxRailCollapsed = !inboxRailCollapsed"
                    v-tooltip="inboxRailCollapsed ? trans('Expand') : trans('Collapse')"
                    class="p-1 rounded hover:bg-gray-200 text-gray-400">
                    <FontAwesomeIcon :icon="inboxRailCollapsed ? faAngleRight : faAngleLeft" class="text-xs" />
                </button>
            </div>

            <!-- Shop list -->
            <div class="flex-1 overflow-y-auto py-1">
                <button v-for="inbox in inboxes" :key="inbox.id" type="button" @click="selectShop(inbox.id)"
                    v-tooltip="inboxRailCollapsed ? inbox.name : undefined"
                    class="w-full flex items-center transition-colors relative"
                    :class="[
                        inboxRailCollapsed ? 'justify-center py-2' : 'gap-2.5 px-3 py-2.5',
                        selectedShopId === inbox.id ? 'font-medium text-gray-800' : 'text-gray-700 hover:bg-gray-100',
                    ]"
                    :style="selectedShopId === inbox.id ? selectedItemStyle : {}">
                    <div class="relative shrink-0">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center text-[11px] font-bold"
                            :style="shopAvatarStyle(inbox)">
                            {{ shopInitials(inbox.name) }}
                        </div>
                        <span v-if="shopUnread[inbox.id]"
                            class="absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-red-500 ring-2 ring-gray-50">
                            {{ shopUnread[inbox.id] }}
                        </span>
                    </div>
                    <span v-if="!inboxRailCollapsed" class="truncate text-sm">{{ inbox.name }}</span>
                </button>
                <div v-if="!inboxes.length && !inboxRailCollapsed" class="px-3 py-6 text-xs text-gray-400 text-center">
                    {{ trans("No inboxes assigned") }}
                </div>
            </div>

            <!-- Future: Highlighted (placeholder) -->
            <div class="border-t border-gray-200 py-1">
                <button type="button" disabled v-tooltip="trans('Highlighted (coming soon)')"
                    class="w-full flex items-center text-sm text-gray-400 cursor-not-allowed"
                    :class="inboxRailCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2'">
                    <FontAwesomeIcon :icon="faStar" class="text-sm shrink-0" />
                    <span v-if="!inboxRailCollapsed">{{ trans("Highlighted") }}</span>
                </button>
            </div>
        </div>

        <!-- PANEL 2: conversation list for the selected inbox -->
        <div class="w-80 shrink-0 border-r border-gray-200 flex flex-col">
            <!-- Selected inbox + My/Team segmented toggle -->
            <div class="px-3 py-2.5 border-b flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-800 truncate mb-1.5">
                        {{ selectedInbox?.name ?? trans("Inbox") }}
                    </div>
                    <div class="inline-flex items-center bg-gray-100 rounded-lg p-0.5 text-[11px]">
                        <button type="button" class="px-2.5 py-1 rounded-md transition-all"
                            :class="viewMode === 'my' ? 'bg-white shadow-sm text-gray-800 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                            @click="viewMode = 'my'">
                            {{ trans("My Chats") }}
                        </button>
                        <button type="button" class="px-2.5 py-1 rounded-md transition-all"
                            :class="viewMode === 'team' ? 'bg-white shadow-sm text-gray-800 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                            @click="viewMode = 'team'">
                            {{ trans("Team Chats") }}
                        </button>
                    </div>
                </div>
                <button class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 shrink-0 self-start" @click="toggleSearch">
                    <FontAwesomeIcon :icon="showSearch ? faTimes : faSearch" class="text-xs" />
                </button>
            </div>

            <!-- Search -->
            <div v-if="showSearch" class="px-3 py-2 border-b">
                <input v-model="searchQuery" type="text" :placeholder="trans('Search…')"
                    class="w-full text-sm border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1" />
            </div>

            <!-- Status segmented tabs -->
            <div class="px-3 py-2 border-b">
                <div class="flex items-center bg-gray-100 rounded-lg p-1 text-xs">
                    <button v-if="viewMode === 'my'" type="button"
                        class="flex-1 py-1.5 rounded-md transition-all inline-flex items-center justify-center gap-1"
                        :class="activeTab === 'waiting' ? 'bg-white shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab === 'waiting' ? { color: 'var(--theme-color-4)' } : {}"
                        @click="activeTab = 'waiting'">
                        {{ trans("Waiting") }}
                        <span v-if="viewMode === 'my' && tabUnread.waiting"
                            class="min-w-[15px] px-1 text-[9px] leading-[15px] text-white rounded-full text-center"
                            :style="{ backgroundColor: 'var(--theme-color-4)' }">{{ tabUnread.waiting }}</span>
                    </button>
                    <button type="button"
                        class="flex-1 py-1.5 rounded-md transition-all inline-flex items-center justify-center gap-1"
                        :class="activeTab === 'active' ? 'bg-white shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab === 'active' ? { color: 'var(--theme-color-4)' } : {}"
                        @click="activeTab = 'active'">
                        {{ trans("Active") }}
                        <span v-if="viewMode === 'my' && tabUnread.active"
                            class="min-w-[15px] px-1 text-[9px] leading-[15px] text-white rounded-full text-center"
                            :style="{ backgroundColor: 'var(--theme-color-4)' }">{{ tabUnread.active }}</span>
                    </button>
                    <button type="button"
                        class="flex-1 py-1.5 rounded-md transition-all inline-flex items-center justify-center gap-1"
                        :class="activeTab === 'closed' ? 'bg-white shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab === 'closed' ? { color: 'var(--theme-color-4)' } : {}"
                        @click="activeTab = 'closed'">
                        {{ trans("Closed") }}
                        <span v-if="viewMode === 'my' && tabUnread.closed"
                            class="min-w-[15px] px-1 text-[9px] leading-[15px] text-white rounded-full text-center"
                            :style="{ backgroundColor: 'var(--theme-color-4)' }">{{ tabUnread.closed }}</span>
                    </button>
                </div>
            </div>

            <!-- List (flat, for the selected inbox) -->
            <div class="flex-1 overflow-y-auto">
                <div v-if="filteredContacts.length === 0"
                    class="h-full flex flex-col items-center justify-center gap-2 text-center px-4">
                    <div class="text-2xl">💬</div>
                    <div class="text-sm font-medium text-gray-700">{{ trans("No conversations") }}</div>
                </div>

                <div v-else>
                    <div v-for="c in filteredContacts" :key="c.ulid">
                        <div class="relative flex items-center gap-3 px-3 py-2 border-b cursor-pointer transition-colors"
                            :class="selectedSession?.ulid === c.ulid ? '' : 'hover:bg-gray-50'"
                            :style="selectedSession?.ulid === c.ulid ? selectedItemStyle : {}"
                            @click="handleClickContact(c)">
                            <div v-if="isAssigning[c.ulid]"
                                class="absolute inset-0 bg-black/30 flex items-center justify-center z-10">
                                <LoadingIcon class="w-8 h-8 text-white" />
                            </div>

                            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 bg-gray-100 text-gray-500">
                                <Image v-if="c.avatar" :src="c.avatar" class="w-full h-full rounded-full object-cover" />
                                <FontAwesomeIcon v-else :icon="faUser" class="text-sm" />
                            </div>

                            <div class="flex-1 min-w-0 flex flex-col gap-0.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-gray-800 truncate">{{ capitalize(c.name) }}</span>
                                    <span class="text-[10px] text-gray-400 shrink-0">{{ c.lastMessageTime }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2">
                                    <span v-if="c.agent?.name" class="text-[10px] text-gray-400 truncate">
                                        {{ c.agent.name.split(' ')[0] }}
                                    </span>
                                    <span v-if="c.unread && activeTab !== 'closed'"
                                        class="min-w-[16px] px-1.5 text-[10px] leading-4 text-white rounded-full text-center shrink-0"
                                        :style="{ backgroundColor: 'var(--theme-color-4)' }">
                                        {{ c.unread }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-gray-500 truncate flex-1 leading-snug">{{ c.lastMessage }}</span>
                                    <span class="shrink-0 text-[9px] px-1 py-0.5 border leading-none"
                                        :class="c.webUser?.id ? 'border-green-400 text-green-500' : 'border-blue-300 text-blue-400'">
                                        {{ c.webUser?.id ? 'C' : 'G' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="errorPerContact[c.ulid]"
                            class="px-3 py-1 text-xs text-red-600 bg-red-50 border-b">
                            {{ errorPerContact[c.ulid] }}
                        </div>
                    </div>

                    <div ref="sentinelEl" class="flex justify-center py-3">
                        <LoadingIcon v-if="isLoadingMore" class="w-5 h-5 text-gray-400" />
                    </div>
                </div>
            </div>
        </div>

        <!-- CENTER: thread + composer -->
        <div class="flex-1 min-w-0 relative">
            <div v-if="!selectedSession"
                class="h-full flex flex-col items-center justify-center gap-2 text-gray-400">
                <div class="text-4xl">💬</div>
                <div class="text-sm">{{ trans("Select a conversation") }}</div>
            </div>

            <div v-else class="h-full">
                <MessageAreaAgent :messages="messages" :session="selectedSession"
                    @back="selectedSession = null" @send-message="handleSendMessage"
                    @close-session="closeSession" @view-history="showHistoryPanel"
                    @view-user-profile="showProfilePanel" @view-message-details="showMessageDetailsPanel"
                    @transfer-agent-success="onTransferAgentSuccess"
                    @assign-self-success="onAssignSelfSuccess" @messages-read="onMessagesRead"
                    @open-jira-settings="onOpenJiraSettings" />
            </div>
        </div>

        <!-- RIGHT: conversation profile panel (Conversation-style) -->
        <ChatConversationSidePanel v-if="panelSession && sidePanelVisible"
            :session="panelSession" @close="closeSidePanel" />
    </div>
</template>
