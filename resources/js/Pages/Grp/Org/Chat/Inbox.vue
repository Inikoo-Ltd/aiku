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
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Image from "@common/Components/Image.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUser, faSearch, faTimes } from "@far"
import { faChevronUp, faChevronDown } from "@fal"
import {
    Contact,
    SessionAPI,
    ChatMessage,
    ChatInboxGroup,
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

const buildParams = (page: number) => ({
    statuses: [activeTab.value],
    assigned_to_me: myAgentId,
    organisation_id: props.organisation.id,
    page,
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

const filteredContacts = computed(() => contacts.value.filter((c) => c.status === activeTab.value))

const groupedContacts = computed<ChatInboxGroup[]>(() => {
    const groups = new Map<number | string, ChatInboxGroup>()

    for (const c of filteredContacts.value) {
        const key = c.shop?.id ?? "other"

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                shopName: c.shop?.name ?? trans("Other"),
                organisationName: c.organisation?.name ?? "",
                unread: 0,
                contacts: [],
            })
        }

        const group = groups.get(key)!
        group.contacts.push(c)

        if (activeTab.value !== "closed") {
            group.unread += c.unread ?? 0
        }
    }

    return Array.from(groups.values())
})

const collapsedInboxes = ref<Set<number | string>>(new Set())
const toggleInbox = (key: number | string) => {
    const next = new Set(collapsedInboxes.value)
    next.has(key) ? next.delete(key) : next.add(key)
    collapsedInboxes.value = next
}
const isInboxCollapsed = (key: number | string) => collapsedInboxes.value.has(key)

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

const onOpenJiraSettings = () => {
    // Jira credentials are configured from the mini-chat widget settings (right sidebar).
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

    const s = e?.session
    const open = selectedSession.value
    if (!s || !open || String(s.ulid) !== String(open.ulid)) return

    // The currently-open chat changed elsewhere (assigned/taken over/closed).
    if (s.assigned_user_id && String(s.assigned_user_id) !== String(myAgentId)) {
        // Another agent took it over → disable the composer (take-over banner).
        selectedSession.value = {
            ...open,
            status: s.status ?? open.status,
            assigned_agent: { user_id: s.assigned_user_id, name: s.assigned_agent_name ?? "Agent" },
        } as SessionAPI
    } else if (s.status && s.status !== open.status) {
        selectedSession.value = { ...open, status: s.status } as SessionAPI
    }
}

onMounted(async () => {
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
    <PageHeading :data="pageHead" />

    <div class="flex border-t border-gray-200 h-[calc(100vh-10rem)] bg-white">
        <!-- LEFT: inbox + conversation list -->
        <div class="w-80 shrink-0 border-r border-gray-200 flex flex-col">
            <!-- Tabs -->
            <div class="px-3 py-2 border-b flex items-center justify-between gap-2">
                <div class="flex items-center gap-1 text-xs">
                    <button
                        :class="viewMode === 'my' ? 'font-semibold text-gray-800' : 'text-gray-400'"
                        @click="viewMode = 'my'">
                        {{ trans("My Chats") }}
                    </button>
                    <span class="text-gray-300">·</span>
                    <button
                        :class="viewMode === 'team' ? 'font-semibold text-gray-800' : 'text-gray-400'"
                        @click="viewMode = 'team'">
                        {{ trans("Team Chats") }}
                    </button>
                </div>
                <button class="p-1.5 rounded hover:bg-gray-100 text-gray-500" @click="toggleSearch">
                    <FontAwesomeIcon :icon="showSearch ? faTimes : faSearch" class="text-xs" />
                </button>
            </div>

            <!-- Search -->
            <div v-if="showSearch" class="px-3 py-2 border-b">
                <input v-model="searchQuery" type="text" :placeholder="trans('Search…')"
                    class="w-full text-sm border rounded px-2 py-1 focus:outline-none focus:ring-1" />
            </div>

            <!-- Status tabs -->
            <div class="flex border-b text-xs">
                <button v-if="viewMode === 'my'" class="flex-1 py-2"
                    :class="activeTab === 'waiting' ? 'font-semibold border-b-2' : 'text-gray-400'"
                    :style="activeTab === 'waiting' ? { borderColor: 'var(--theme-color-4)' } : {}"
                    @click="activeTab = 'waiting'">
                    {{ trans("Waiting") }}
                </button>
                <button class="flex-1 py-2"
                    :class="activeTab === 'active' ? 'font-semibold border-b-2' : 'text-gray-400'"
                    :style="activeTab === 'active' ? { borderColor: 'var(--theme-color-4)' } : {}"
                    @click="activeTab = 'active'">
                    {{ trans("Active") }}
                </button>
                <button class="flex-1 py-2"
                    :class="activeTab === 'closed' ? 'font-semibold border-b-2' : 'text-gray-400'"
                    :style="activeTab === 'closed' ? { borderColor: 'var(--theme-color-4)' } : {}"
                    @click="activeTab = 'closed'">
                    {{ trans("Closed") }}
                </button>
            </div>

            <!-- List -->
            <div class="flex-1 overflow-y-auto">
                <div v-if="filteredContacts.length === 0"
                    class="h-full flex flex-col items-center justify-center gap-2 text-center px-4">
                    <div class="text-2xl">💬</div>
                    <div class="text-sm font-medium text-gray-700">{{ trans("No conversations") }}</div>
                </div>

                <div v-else>
                    <div v-for="group in groupedContacts" :key="group.key">
                        <!-- Inbox header -->
                        <button type="button"
                            class="w-full flex items-center justify-between gap-2 px-3 py-2 bg-gray-50 hover:bg-gray-100 border-b sticky top-0 z-[1]"
                            @click="toggleInbox(group.key)">
                            <div class="flex items-center gap-2 min-w-0">
                                <FontAwesomeIcon
                                    :icon="isInboxCollapsed(group.key) ? faChevronDown : faChevronUp"
                                    class="text-[10px] text-gray-400 shrink-0" />
                                <span class="text-xs font-semibold text-gray-700 truncate">{{ group.shopName }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span v-if="group.unread"
                                    class="min-w-[16px] px-1.5 text-[10px] leading-4 text-white rounded-full text-center"
                                    :style="{ backgroundColor: 'var(--theme-color-4)' }">
                                    {{ group.unread }}
                                </span>
                                <span class="text-[10px] text-gray-400">{{ group.contacts.length }}</span>
                            </div>
                        </button>

                        <div v-show="!isInboxCollapsed(group.key)">
                            <div v-for="c in group.contacts" :key="c.ulid">
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
                    @assign-self-success="onAssignSelfSuccess" @open-jira-settings="onOpenJiraSettings" />
            </div>
        </div>

        <!-- RIGHT: conversation profile panel (Conversation-style) -->
        <ChatConversationSidePanel v-if="panelSession && sidePanelVisible"
            :session="panelSession" @close="closeSidePanel" />
    </div>
</template>
