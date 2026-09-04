<script setup lang="ts">
import { ref, computed, inject, onMounted, onUnmounted, watch, nextTick } from "vue"
import { Head } from "@inertiajs/vue3"
import { watchDebounced } from "@vueuse/core"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import MessageAreaAgent from "@/Components/Chat/Agent/MessageAreaAgent.vue"
import WhatsappMessageAreaAgent from "@/Components/Chat/Agent/WhatsappMessageAreaAgent.vue"
import ChatConversationSidePanel from "@/Components/Chat/ChatConversationSidePanel.vue"
import SettingChat from "@/Components/Chat/SettingChat.vue"
import NewWhatsappChatDialog from "@/Components/Chat/NewWhatsappChatDialog.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Image from "@common/Components/Image.vue"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUser, faSearch, faTimes } from "@far"
import { faCog, faStar, faAngleLeft, faAngleRight, faAngleDown, faFilter, faStoreAlt, faGlobe, faPlus } from "@fal"
import { faEllipsisVertical, faBan, faRotateLeft, faTrash, faTrashArrowUp, faAnglesUp, faAngleUp, faEquals, faChevronRight, faStar as faStarSolid } from "@fortawesome/free-solid-svg-icons"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
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
    inboxes: Array<{
        id: number
        name: string
        slug: string
        type: string | null
        channels: Array<{ key: string; name: string; unread: number }>
    }>
    selectedSessionUlid?: string | null
    initialSession?: any | null
    preselectShopId?: number | null
}>()

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const myAgentId = layout.user?.id
const myAgentShop = layout.user?.agent_shops ?? []

const PLUS_8_HOURS = layout.app?.environment === "local" ? 8 * 60 * 60 * 1000 : 0

const contacts = ref<Contact[]>([])
const activeTab = ref<"waiting" | "active" | "closed">("waiting")
const spamView = ref(false)
const trashView = ref(false)
const highlightView = ref(false)
const openMenuUlid = ref<string | null>(null)
const confirmDeleteUlid = ref<string | null>(null)
const menuPos = ref({ top: 0, left: 0 })
const isSpamming = ref<Record<string, boolean>>({})

const PRIORITIES: Array<{ value: string; label: string; color: string; icon: any }> = [
    { value: "urgent", label: "Urgent", color: "#ef4444", icon: faAnglesUp },
    { value: "high", label: "High", color: "#f59e0b", icon: faAngleUp },
    { value: "normal", label: "Normal", color: "#3b82f6", icon: faEquals },
    { value: "low", label: "Low", color: "#6b7280", icon: faAngleDown },
]

const priorityMeta = (val?: string | null) =>
    PRIORITIES.find((p) => p.value === val) ?? PRIORITIES.find((p) => p.value === "normal")!
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
    const channel = (s as any).channel ?? (selectedChannel.value === "whatsapp" ? "whatsapp" : "website")
    const isWhatsapp = channel === "whatsapp"

    // A WhatsApp thread is keyed to a customer, but the list mapper stores that customer
    // in the shared `web_user` slot, so read it from whichever side carries it.
    const webUserId = isWhatsapp ? null : (s.web_user?.id ?? null)
    const customerId = isWhatsapp
        ? ((s as any).customer?.id ?? s.web_user?.id ?? null)
        : ((s as any).customer?.id ?? null)

    return {
        ulid: String(s.ulid),
        channel,
        contact_name: (webUserId || customerId)
            ? (s.contact_name || s.guest_identifier || "Customer")
            : ((s as any).metadata?.name || s.guest_profile?.name || s.guest_identifier || "Guest"),
        is_guest: !(webUserId || customerId),
        web_user_id: webUserId,
        customer_id: customerId,
        guest_email: (s as any).metadata?.email ?? s.guest_profile?.email ?? null,
        guest_phone: (s as any).metadata?.phone ?? s.guest_profile?.phone ?? null,
        phone_number: (s as any).phone_number ?? null,
        shop_name: s.shop?.name ?? null,
        status: s.status,
        priority: s.priority ?? null,
        assigned_agent: s.assigned_agent?.name ?? null,
        started: s.created_at ?? null,
        ai_summary: s.ai_summary ?? null,
    }
})

const selectedItemStyle = {
    backgroundColor: "color-mix(in srgb, var(--theme-color-4) 18%, white)",
    boxShadow: "inset 3px 0 0 var(--theme-color-4)",
}

const sidePanelVisible = ref(false)

const chatSettingVisible = ref(false)
const settingInitialTab = ref<"general" | "jira" | "slack">("general")

const newChatVisible = ref(false)
const openChatSettings = () => {
    settingInitialTab.value = "general"
    chatSettingVisible.value = true
}
const onOpenJiraSettings = () => {
    settingInitialTab.value = "jira"
    chatSettingVisible.value = true
}
const onOpenSlackSettings = () => {
    settingInitialTab.value = "slack"
    chatSettingVisible.value = true
}

const formatTime = (timestamp: number) => {
    const d = new Date(timestamp)
    return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
}

const mapSession = (s: SessionAPI): Contact => ({
    id: s.id,
    ulid: s.ulid,
    // Merged views tag every row; single-channel views inherit the selected channel.
    channel: (s as any).channel ?? (selectedChannel.value === "whatsapp" ? "whatsapp" : "website"),
    name: s.contact_name || s.guest_identifier || "",
    avatar: s.image ?? "",
    lastMessage: s.last_message?.message ?? "",
    lastMessageTime: s.last_message?.created_at
        ? formatTime(new Date(s.last_message.created_at).getTime() + PLUS_8_HOURS)
        : undefined,
    unread: s.unread_count,
    status: s.status,
    is_spam: (s as any).is_spam ?? false,
    is_highlighted: (s as any).is_highlighted ?? false,
    webUser: s.web_user ?? (s as any).customer,
    priority: s.priority,
    guest_profile: s.guest_profile,
    metadata: (s as any).metadata ?? null,
    phone_number: (s as any).phone_number ?? null,
    agent: s.assigned_agent,
    shop: s.shop,
    organisation: s.organisation,
    ai_summary: s.ai_summary ?? null,
})

const selectedShopId = ref<number | null>(props.inboxes?.[0]?.id ?? null)
const selectedChannel = ref<string | null>(props.inboxes?.[0]?.channels?.[0]?.key ?? null)
const expandedInboxIds = ref<number[]>(props.inboxes?.[0] ? [props.inboxes[0].id] : [])

const buildParams = (page: number) => ({
    ...(trashView.value
        ? { trashed: 1 }
        : spamView.value
            ? { is_spam: 1 }
            : highlightView.value
                ? { highlighted: 1, statuses: [activeTab.value] }
                : { statuses: [activeTab.value] }),
    assigned_to_me: myAgentId,
    organisation_id: props.organisation.id,
    page,
    ...(selectedShopId.value && !highlightView.value ? { shop_id: selectedShopId.value } : {}),
    // ponytail: the API ignores `channel` until chat sessions carry one; sent so the intent is visible.
    ...(selectedChannel.value ? { channel: selectedChannel.value } : {}),
    ...(viewMode.value === "team" ? { view_team: 1 } : {}),
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
})

// Spam, trash and highlight are cross-channel clean-up views, so they read from the
// merged endpoint instead of whichever channel happens to be selected.
const isMergedView = computed(() => spamView.value || trashView.value || highlightView.value)

const sessionsUrl = computed(() => {
    if (isMergedView.value) {
        return `${baseUrl}/app/api/chats/all/sessions`
    }

    return selectedChannel.value === "whatsapp"
        ? `${baseUrl}/app/api/chats/meta/sessions`
        : `${baseUrl}/app/api/chats/sessions`
})

const reloadContacts = async () => {
    currentPage.value = 1
    hasMore.value = false
    try {
        const res = await axios.get(sessionsUrl.value, { params: buildParams(1) })
        contacts.value = res.data.data.sessions.map(mapSession)
        hasMore.value = res.data.data.pagination?.has_more ?? false
        await openPendingSession()
    } catch (e) {
        console.error("Failed to reload contacts:", e)
    }
}

const loadMore = async () => {
    if (isLoadingMore.value || !hasMore.value) return
    isLoadingMore.value = true
    try {
        const res = await axios.get(sessionsUrl.value, { params: buildParams(currentPage.value + 1) })
        contacts.value = [...contacts.value, ...res.data.data.sessions.map(mapSession)]
        currentPage.value += 1
        hasMore.value = res.data.data.pagination?.has_more ?? false
    } catch (e) {
        console.error("Failed to load more contacts:", e)
    } finally {
        isLoadingMore.value = false
    }
}

const menuContact = computed(() => contacts.value.find((c) => c.ulid === openMenuUlid.value) ?? null)

const closeRowMenu = () => {
    openMenuUlid.value = null
    confirmDeleteUlid.value = null
}

const toggleRowMenu = (ulid: string, ev?: MouseEvent) => {
    confirmDeleteUlid.value = null
    if (openMenuUlid.value === ulid) {
        openMenuUlid.value = null
        return
    }
    if (ev) {
        const MENU_W = 192
        const MENU_H = 200
        let left = ev.clientX - MENU_W
        if (left < 8) left = ev.clientX + 4
        let top = ev.clientY + 4
        if (top + MENU_H > window.innerHeight) top = Math.max(8, window.innerHeight - MENU_H - 8)
        menuPos.value = { top, left: Math.max(8, left) }
    }
    openMenuUlid.value = ulid
}

const onSpamFromThread = () => {
    const ulid = selectedSession.value?.ulid
    if (ulid) {
        contacts.value = contacts.value.filter((x) => x.ulid !== ulid)
    }
    selectedSession.value = null
    messages.value = []
    fetchInboxNotifications()
}

const markSpam = async (c: Contact, spam: boolean) => {
    if (isSpamming.value[c.ulid]) return
    openMenuUlid.value = null
    isSpamming.value = { ...isSpamming.value, [c.ulid]: true }
    try {
        const routeName = sessionRoute(spam ? "spam" : "not_spam", c)
        await axios.patch(route(routeName, [props.organisation.slug, c.ulid]), {}, { withCredentials: true })
        // It moved to (or out of) the Spam tab — drop it from the current list.
        contacts.value = contacts.value.filter((x) => x.ulid !== c.ulid)
        if (selectedSession.value?.ulid === c.ulid) {
            selectedSession.value = null
            messages.value = []
        }
        fetchInboxNotifications()
    } catch (e: any) {
        errorPerContact.value[c.ulid] = e?.response?.data?.message ?? "Failed to update spam"
    } finally {
        isSpamming.value = { ...isSpamming.value, [c.ulid]: false }
    }
}

const showAgentFilter = ref(false)
const selectedAgentIds = ref<Array<number | string>>([])

const availableAgents = computed(() => {
    const map = new Map<number | string, { id: number | string; name: string }>()
    for (const c of contacts.value) {
        if (c.agent?.id && !map.has(c.agent.id)) {
            map.set(c.agent.id, { id: c.agent.id, name: c.agent.name })
        }
    }
    return Array.from(map.values())
})

const toggleAgentFilter = (id: number | string) => {
    const idx = selectedAgentIds.value.indexOf(id)
    if (idx >= 0) {
        selectedAgentIds.value.splice(idx, 1)
    } else {
        selectedAgentIds.value.push(id)
    }
}

const clearAgentFilter = () => {
    selectedAgentIds.value = []
}

const filteredContacts = computed(() =>
    contacts.value.filter(
        (c) => (spamView.value || trashView.value ? true : c.status === activeTab.value) &&
            (highlightView.value || !selectedShopId.value || c.shop?.id === selectedShopId.value) &&
            (!selectedAgentIds.value.length ||
                (c.agent?.id && selectedAgentIds.value.includes(c.agent.id)))
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

const toggleInbox = (shopId: number) => {
    if (inboxRailCollapsed.value) {
        inboxRailCollapsed.value = false
    }
    const idx = expandedInboxIds.value.indexOf(shopId)
    if (idx >= 0) {
        expandedInboxIds.value.splice(idx, 1)
    } else {
        expandedInboxIds.value.push(shopId)
    }
}

// Select + expand an inbox without reloading — callers on mount reload once afterwards.
// When `channelKey` is provided (e.g. from URL params) it takes precedence over the
// default first channel so that deep-links like `?channel=whatsapp` are honoured.
const revealInbox = (shopId: number, channelKey?: string | null) => {
    selectedShopId.value = shopId
    const inbox = props.inboxes?.find((i) => i.id === shopId)
    const resolved = channelKey && inbox?.channels?.some((ch) => ch.key === channelKey)
        ? channelKey
        : inbox?.channels?.[0]?.key ?? null
    selectedChannel.value = resolved
    if (!expandedInboxIds.value.includes(shopId)) {
        expandedInboxIds.value.push(shopId)
    }
}

const selectChannel = (shopId: number, channelKey: string) => {
    if (selectedShopId.value === shopId && selectedChannel.value === channelKey && !spamView.value && !trashView.value && !highlightView.value) return
    spamView.value = false
    trashView.value = false
    highlightView.value = false
    selectedShopId.value = shopId
    selectedChannel.value = channelKey
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()

    const baseUrl = route("grp.org.chat.inbox", [props.organisation.slug])
    window.history.replaceState(window.history.state, "", baseUrl)

    reloadContacts()
}

const selectSpam = () => {
    if (spamView.value) return
    spamView.value = true
    trashView.value = false
    highlightView.value = false
    selectedShopId.value = null
    selectedChannel.value = null
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectTrash = () => {
    if (trashView.value) return
    trashView.value = true
    spamView.value = false
    highlightView.value = false
    selectedShopId.value = null
    selectedChannel.value = null
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectHighlight = () => {
    if (highlightView.value) return
    highlightView.value = true
    spamView.value = false
    trashView.value = false
    selectedShopId.value = null
    selectedChannel.value = null
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    if (viewMode.value === "team" && activeTab.value === "waiting") {
        activeTab.value = "active"
    }
    clearAgentFilter()
    reloadContacts()
}

// Website chats and WhatsApp threads live in different tables, so the row actions
// resolve to the matching route family for whichever channel is being shown.
const sessionRoute = (action: string, contact?: Contact) => {
    const channel = contact?.channel ?? (selectedChannel.value === "whatsapp" ? "whatsapp" : "website")

    return channel === "whatsapp"
        ? `grp.org.chat.agents.whatsapp.sessions.${action}`
        : `grp.org.chat.agents.sessions.${action}`
}

const patchSession = async (c: Contact, routeName: string, method: "patch" | "delete", body: Record<string, any> = {}) => {
    if (isSpamming.value[c.ulid]) return
    isSpamming.value = { ...isSpamming.value, [c.ulid]: true }
    try {
        const url = route(routeName, [props.organisation.slug, c.ulid])
        await (method === "delete" ? axios.delete(url, { withCredentials: true }) : axios.patch(url, body, { withCredentials: true }))
        return true
    } catch (e: any) {
        errorPerContact.value[c.ulid] = e?.response?.data?.message ?? "Action failed"
        return false
    } finally {
        isSpamming.value = { ...isSpamming.value, [c.ulid]: false }
    }
}

const removeFromList = (ulid: string) => {
    contacts.value = contacts.value.filter((x) => x.ulid !== ulid)
    if (selectedSession.value?.ulid === ulid) {
        selectedSession.value = null
        messages.value = []
    }
}

const setPriority = async (c: Contact, priority: string) => {
    openMenuUlid.value = null
    const ok = await patchSession(c, sessionRoute("priority", c), "patch", { priority })
    if (ok) {
        const found = contacts.value.find((x) => x.ulid === c.ulid)
        if (found) found.priority = priority
        if (selectedSession.value?.ulid === c.ulid) selectedSession.value.priority = priority
    }
}

const onPriorityUpdated = (value: string) => {
    const ulid = selectedSession.value?.ulid
    if (selectedSession.value) selectedSession.value.priority = value
    const found = contacts.value.find((x) => x.ulid === ulid)
    if (found) found.priority = value
}

// A guest was matched to a registered Aiku customer by email: promote it to a customer.
const onSessionSynced = (webUser: { id: number; name: string; email: string | null }) => {
    if (!selectedSession.value || !webUser) return
    const ulid = selectedSession.value.ulid
    selectedSession.value = {
        ...selectedSession.value,
        web_user: { id: webUser.id, name: webUser.name, email: webUser.email } as any,
        contact_name: webUser.name || (selectedSession.value as any).contact_name,
    } as SessionAPI
    const found = contacts.value.find((x) => x.ulid === ulid)
    if (found) {
        found.webUser = { id: webUser.id, name: webUser.name } as any
        found.name = webUser.name || found.name
    }
}

const onCustomerSynced = (customer: { id: number; name: string; email: string | null; phone: string | null }) => {
    if (!selectedSession.value || !customer) return
    const ulid = selectedSession.value.ulid
    selectedSession.value = {
        ...selectedSession.value,
        customer: { id: customer.id, name: customer.name, email: customer.email, phone: customer.phone } as any,
        contact_name: customer.name || (selectedSession.value as any).contact_name,
    } as SessionAPI
    const found = contacts.value.find((x) => x.ulid === ulid)
    if (found) {
        found.webUser = { id: customer.id, name: customer.name } as any
        found.name = customer.name || found.name
    }
}

const trashChat = async (c: Contact) => {
    openMenuUlid.value = null
    if (await patchSession(c, sessionRoute("trash", c), "delete")) {
        removeFromList(c.ulid)
        fetchInboxNotifications()
    }
}

const toggleHighlight = async (c: Contact) => {
    openMenuUlid.value = null
    const next = !c.is_highlighted
    if (await patchSession(c, sessionRoute("highlight", c), "patch")) {
        const found = contacts.value.find((x) => x.ulid === c.ulid)
        if (found) found.is_highlighted = next
        if (selectedSession.value?.ulid === c.ulid) selectedSession.value.is_highlighted = next
        if (highlightView.value && !next) {
            removeFromList(c.ulid)
        }
    }
}

const restoreChat = async (c: Contact) => {
    openMenuUlid.value = null
    if (await patchSession(c, sessionRoute("restore", c), "patch")) {
        removeFromList(c.ulid)
        fetchInboxNotifications()
    }
}

const forceDeleteChat = async (c: Contact) => {
    confirmDeleteUlid.value = null
    openMenuUlid.value = null
    if (await patchSession(c, sessionRoute("force_delete", c), "delete")) {
        removeFromList(c.ulid)
    }
}

// Per-agent (My Chats) incoming-chat counts used for the badges.
const notifWaiting = ref<any[]>([])
const notifActive = ref<any[]>([])
const notifReopen = ref<any[]>([])
const teamUnreadByShop = ref<Record<number, number>>({})

const notifWaWaiting = ref<any[]>([])
const notifWaActive = ref<any[]>([])
const notifWaReopen = ref<any[]>([])

// The messages widget links here with the channel it came from, so a WhatsApp row does
// not land the agent on the website tab of the right shop.
const pendingSessionUlid = ref<string | null>(null)

const applyChannelFromUrl = () => {
    const params = new URLSearchParams(window.location.search)
    const channel = params.get("channel")

    if (channel === "whatsapp" || channel === "website") {
        selectedChannel.value = channel
    }

    pendingSessionUlid.value = params.get("session")
}

// The linked conversation can only be opened once its list has arrived, so the ulid waits
// here and is consumed by the first load that contains it.
const openPendingSession = async () => {
    if (!pendingSessionUlid.value) return

    const contact = contacts.value.find((c) => String(c.ulid) === pendingSessionUlid.value)

    if (contact) {
        pendingSessionUlid.value = null
        openChat(contact)
        return
    }

    // Session not in the current tab — fetch all statuses from the API to find it
    // and switch to the matching tab before opening.
    const ulid = pendingSessionUlid.value
    try {
        const url = selectedChannel.value === "whatsapp"
            ? `${baseUrl}/app/api/chats/meta/sessions`
            : `${baseUrl}/app/api/chats/sessions`

        const { data } = await axios.get(url, {
            params: {
                assigned_to_me: myAgentId,
                organisation_id: props.organisation.id,
                ...(selectedShopId.value ? { shop_id: selectedShopId.value } : {}),
                page: 1,
                limit: 50,
            },
        })

        const sessions = data?.data?.sessions ?? []
        const found = sessions.find((s: any) => String(s.ulid) === ulid)

        if (found) {
            const mapped = mapSession(found)

            if (mapped.status && ["waiting", "active", "closed"].includes(mapped.status) && activeTab.value !== mapped.status) {
                activeTab.value = mapped.status as "waiting" | "active" | "closed"
                await reloadContacts()
            }

            pendingSessionUlid.value = null
            openChat(mapped)
        }
    } catch (e) {
        console.error("Failed to fetch pending session:", e)
    }
}

const fetchInboxNotifications = async () => {
    if (!myAgentId) return
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/users/${myAgentId}/agent-notifications`)
        notifWaiting.value = data?.data?.waiting ?? []
        notifActive.value = data?.data?.active ?? []
        notifReopen.value = data?.data?.reopen ?? []
        teamUnreadByShop.value = data?.data?.team_unread ?? {}
        notifWaWaiting.value = data?.data?.whatsapp?.waiting ?? []
        notifWaActive.value = data?.data?.whatsapp?.active ?? []
        notifWaReopen.value = data?.data?.whatsapp?.reopen ?? []
    } catch (e) {
        // silent — badges are non-critical
    }
}

// Team-unread badge for the currently selected inbox + channel. team_unread is a website
// ChatSession count, so it only applies to the website channel (WhatsApp has no count yet).
const teamUnreadForShop = computed(() =>
    selectedShopId.value && selectedChannel.value !== "whatsapp"
        ? (teamUnreadByShop.value[selectedShopId.value] ?? 0)
        : 0
)

// Each channel keeps its own feed, so the tab badges follow whichever inbox is open
// rather than showing website counts above a WhatsApp list.
const tabUnread = computed(() => {
    const sid = selectedShopId.value
    const inShop = (arr: any[]) => (sid ? arr.filter((s) => s?.shop?.id === sid) : arr)
    const isWhatsapp = selectedChannel.value === "whatsapp"

    return {
        waiting: inShop(isWhatsapp ? notifWaWaiting.value : notifWaiting.value).length,
        active: inShop(isWhatsapp ? notifWaActive.value : notifActive.value).length,
        closed: inShop(isWhatsapp ? notifWaReopen.value : notifReopen.value).length,
    }
})

const countByShop = (sessions: any[]) => {
    const map: Record<number, number> = {}
    for (const session of sessions) {
        const sid = session?.shop?.id
        if (!sid) continue
        map[sid] = (map[sid] ?? 0) + 1
    }
    return map
}

const shopUnread = computed<Record<number, number>>(() =>
    countByShop([...notifWaiting.value, ...notifActive.value, ...notifReopen.value])
)

const whatsappUnread = computed<Record<number, number>>(() =>
    countByShop([...notifWaWaiting.value, ...notifWaActive.value, ...notifWaReopen.value])
)

const channelUnread = (inbox: { id: number }, channel: { key: string; unread: number }) =>
    channel.key === "whatsapp"
        ? (whatsappUnread.value[inbox.id] ?? 0)
        : (shopUnread.value[inbox.id] ?? 0)

const inboxUnread = computed<Record<number, number>>(() => {
    const map: Record<number, number> = {}
    for (const inbox of props.inboxes ?? []) {
        let total = 0
        for (const channel of inbox.channels ?? []) {
            total += channelUnread(inbox, channel)
        }
        map[inbox.id] = total
    }
    return map
})

const openChat = (c: Contact) => {
    selectedSession.value = {
        channel: c.channel,
        ulid: String(c.ulid),
        guest_identifier: c.name,
        status: c.status,
        priority: c.priority,
        web_user: c.webUser,
        guest_profile: c.guest_profile,
        metadata: c.metadata,
        phone_number: c.phone_number,
        assigned_agent: c.agent,
        shop: c.shop,
        organisation: c.organisation,
        ai_summary: c.ai_summary ?? null,
        is_trashed: trashView.value,
    } as SessionAPI
    messages.value = c.messages ?? []
    updateUrl(String(c.ulid))
}

const onRestoreFromThread = () => {
    const ulid = selectedSession.value?.ulid
    if (ulid) {
        contacts.value = contacts.value.filter((x) => x.ulid !== ulid)
    }
    selectedSession.value = null
    messages.value = []
    fetchInboxNotifications()
}

const onWhatsappChatCreated = (session: any) => {
    selectedSession.value = {
        channel: "whatsapp",
        ulid: String(session.ulid),
        contact_name: session.contact_name,
        guest_identifier: session.guest_identifier ?? session.contact_name,
        status: session.status,
        priority: session.priority,
        shop: session.shop,
        organisation: props.organisation,
        phone_number: session.phone_number,
        customer: session.customer_id ? { id: session.customer_id } : null,
        assigned_agent: session.assigned_agent ?? null,
    } as SessionAPI
    messages.value = []
    updateUrl(String(session.ulid))
    reloadContacts()
}

const handleClickContact = (c: Contact) => {
    errorPerContact.value[c.ulid] = ""
    // Waiting chats open into an "Assign to me" step (no composer) until assigned.
    openChat(c)
}

// In merged views the open conversation may belong to another channel than the one
// selected in the sidebar, so the thread pane follows the conversation itself.
const activeChannel = computed(
    () => (selectedSession.value as any)?.channel ?? (selectedChannel.value === "whatsapp" ? "whatsapp" : "website")
)

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
    const url = selectedChannel.value === "whatsapp"
        ? route("grp.org.chat.inbox", [props.organisation.slug]) + `?channel=whatsapp&session=${ulid}`
        : route("grp.org.chat.inbox.conversation", [props.organisation.slug, ulid])

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
        channel: "website",
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
        selectedSession.value = { channel: "website", ulid: String(props.selectedSessionUlid) } as SessionAPI
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

const onMetaChatListEvent = (e: any) => {
    // Always refresh notification badges so the sidebar unread counts stay current
    // regardless of which channel is selected.
    fetchInboxNotifications()

    // Contact list only needs a full reload when viewing the WhatsApp channel.
    if (selectedChannel.value !== "whatsapp") return

    reloadContacts()

    const s = e?.session
    const open = selectedSession.value
    if (!s || !open || String(s.ulid) !== String(open.ulid)) return

    selectedSession.value = {
        ...open,
        status: s.status ?? open.status,
        can_send_non_template_message: s.can_send_non_template_message,
        ...(s.assigned_user_id
            ? {
                assigned_agent: {
                    id: (open as any)?.assigned_agent?.id,
                    user_id: s.assigned_user_id,
                    name: s.assigned_agent_name ?? "Agent",
                },
            }
            : {}),
    } as SessionAPI
}

onMounted(async () => {
    applyChannelFromUrl()
    fetchInboxNotifications()

    const urlChannel = selectedChannel.value

    // Preselect the shop when opened from the shop-level nav entry.
    if (props.preselectShopId && props.inboxes?.some((i) => i.id === props.preselectShopId)) {
        revealInbox(props.preselectShopId, urlChannel)
    }

    const init = props.initialSession

    // Jump to the shop (inbox) the opened chat belongs to.
    if (init?.shop?.id) {
        revealInbox(init.shop.id, urlChannel)
    }

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
            window.Echo.join(channel)
                .listen(".chatlist", onChatListEvent)
                .listen(".meta-chatlist", onMetaChatListEvent)
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
        window.Echo?.join(channel)
            .stopListening(".chatlist", onChatListEvent)
            .stopListening(".meta-chatlist", onMetaChatListEvent)
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
        <SettingChat :initial-tab="settingInitialTab" :session-ulid="selectedSession?.ulid" @close="chatSettingVisible = false" />
    </Dialog>

    <NewWhatsappChatDialog v-model:visible="newChatVisible" :shop-id="selectedShopId"
        @created="onWhatsappChatCreated" />

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
                <div v-for="inbox in inboxes" :key="inbox.id">
                    <button type="button" @click="toggleInbox(inbox.id)"
                        v-tooltip="inboxRailCollapsed ? inbox.name : undefined"
                        class="w-full flex items-center transition-colors relative"
                        :class="[
                            inboxRailCollapsed ? 'justify-center py-2' : 'gap-2.5 px-3 py-2.5',
                            selectedShopId === inbox.id ? 'font-medium text-gray-800' : 'text-gray-700 hover:bg-gray-100',
                        ]">
                        <div class="relative shrink-0">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center text-[11px] font-bold"
                                :style="shopAvatarStyle(inbox)">
                                {{ shopInitials(inbox.name) }}
                            </div>
                            <span v-if="inboxUnread[inbox.id]"
                                class="absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-red-500 ring-2 ring-gray-50">
                                {{ inboxUnread[inbox.id] }}
                            </span>
                        </div>
                        <span v-if="!inboxRailCollapsed" class="truncate text-sm flex-1 text-left">{{ inbox.name }}</span>
                        <FontAwesomeIcon v-if="!inboxRailCollapsed"
                            :icon="expandedInboxIds.includes(inbox.id) ? faAngleDown : faAngleRight"
                            class="text-[10px] text-gray-400 shrink-0" />
                    </button>

                    <div v-if="!inboxRailCollapsed && expandedInboxIds.includes(inbox.id)" class="pb-1">
                        <button v-for="channel in inbox.channels" :key="channel.key" type="button"
                            @click="selectChannel(inbox.id, channel.key)"
                            class="w-full flex items-center gap-2 py-1.5 pr-3 pl-[38px] text-sm transition-colors"
                            :class="selectedShopId === inbox.id && selectedChannel === channel.key
                                ? 'font-medium text-gray-800'
                                : 'text-gray-600 hover:bg-gray-100'"
                            :style="selectedShopId === inbox.id && selectedChannel === channel.key ? selectedItemStyle : {}">
                            <FontAwesomeIcon :icon="channel.key === 'whatsapp' ? faWhatsapp : faGlobe"
                                class="text-xs shrink-0"
                                :class="channel.key === 'whatsapp' ? 'text-green-600' : 'text-gray-400'" />
                            <span class="truncate flex-1 text-left">{{ channel.name }}</span>
                            <span v-if="channelUnread(inbox, channel)"
                                class="min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-red-500">
                                {{ channelUnread(inbox, channel) }}
                            </span>
                        </button>
                    </div>
                </div>
                <div v-if="!inboxes.length && !inboxRailCollapsed" class="px-3 py-6 text-xs text-gray-400 text-center">
                    {{ trans("No inboxes assigned") }}
                </div>
            </div>

            <!-- Spam -->
            <div class="border-t border-gray-200 py-1">
                <button type="button" @click="selectSpam"
                    v-tooltip="inboxRailCollapsed ? trans('Spam') : undefined"
                    class="w-full flex items-center text-sm transition-colors"
                    :class="[
                        inboxRailCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2',
                        spamView ? 'font-medium text-gray-800' : 'text-gray-600 hover:bg-gray-100',
                    ]"
                    :style="spamView ? selectedItemStyle : {}">
                    <FontAwesomeIcon :icon="faBan" class="text-sm shrink-0" :class="spamView ? 'text-red-500' : ''" />
                    <span v-if="!inboxRailCollapsed">{{ trans("Spam") }}</span>
                </button>
                <button type="button" @click="selectTrash"
                    v-tooltip="inboxRailCollapsed ? trans('Trash') : undefined"
                    class="w-full flex items-center text-sm transition-colors"
                    :class="[
                        inboxRailCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2',
                        trashView ? 'font-medium text-gray-800' : 'text-gray-600 hover:bg-gray-100',
                    ]"
                    :style="trashView ? selectedItemStyle : {}">
                    <FontAwesomeIcon :icon="faTrash" class="text-sm shrink-0" :class="trashView ? 'text-red-500' : ''" />
                    <span v-if="!inboxRailCollapsed">{{ trans("Trash") }}</span>
                </button>
            </div>

            <!-- Highlighted -->
            <div class="border-t border-gray-200 py-1">
                <button type="button" @click="selectHighlight"
                    v-tooltip="inboxRailCollapsed ? trans('Highlighted') : undefined"
                    class="w-full flex items-center text-sm transition-colors"
                    :class="[
                        inboxRailCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2',
                        highlightView ? 'font-medium text-gray-800' : 'text-gray-600 hover:bg-gray-100',
                    ]"
                    :style="highlightView ? selectedItemStyle : {}">
                    <FontAwesomeIcon :icon="faStar" class="text-sm shrink-0" :class="highlightView ? 'text-amber-400' : ''" />
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
                        {{ trashView ? trans("Trash") : spamView ? trans("Spam") : highlightView ? trans("Highlighted") : (selectedInbox?.name ?? trans("Inbox")) }}
                    </div>
                    <div v-if="!spamView && !trashView" class="inline-flex items-center bg-gray-100 rounded-lg p-0.5 text-[11px]">
                        <button type="button" class="px-2.5 py-1 rounded-md transition-all whitespace-nowrap shrink-0"
                            :class="viewMode === 'my' ? 'bg-white shadow-sm text-gray-800 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                            @click="viewMode = 'my'">
                            {{ trans("My Chats") }}
                        </button>
                        <button type="button" class="px-2.5 py-1 rounded-md transition-all whitespace-nowrap shrink-0 inline-flex items-center gap-1"
                            :class="viewMode === 'team' ? 'bg-white shadow-sm text-gray-800 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                            @click="viewMode = 'team'">
                            {{ trans("Team Chats") }}
                            <span v-if="teamUnreadForShop"
                                v-tooltip="trans('Unread team chats in this inbox — take over to reply')"
                                class="min-w-[15px] px-1 text-[9px] leading-[15px] text-white rounded-full text-center bg-amber-500">
                                {{ teamUnreadForShop }}
                            </span>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-0.5 shrink-0 self-start">
                    <button v-if="selectedChannel === 'whatsapp'" type="button"
                        v-tooltip="trans('New WhatsApp chat')"
                        class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-gray-100 text-green-600 text-[11px] font-medium"
                        @click="newChatVisible = true">
                        <FontAwesomeIcon :icon="faPlus" class="text-xs" />
                        {{ trans("New chat") }}
                    </button>

                    <!-- Filter by agent -->
                    <div class="relative">
                        <button type="button" v-tooltip="trans('Filter by agent')"
                            class="relative p-1.5 rounded-lg hover:bg-gray-100 text-gray-500"
                            @click="showAgentFilter = !showAgentFilter">
                            <FontAwesomeIcon :icon="faFilter" class="text-xs" />
                            <span v-if="selectedAgentIds.length"
                                class="absolute -top-0.5 -right-0.5 min-w-[14px] h-3.5 px-0.5 text-[8px] font-semibold leading-[14px] text-white rounded-full text-center"
                                :style="{ backgroundColor: 'var(--theme-color-4)' }">
                                {{ selectedAgentIds.length }}
                            </span>
                        </button>

                        <div v-if="showAgentFilter" class="fixed inset-0 z-[40]" @click="showAgentFilter = false" />

                        <div v-if="showAgentFilter"
                            class="absolute right-0 top-full mt-1 z-[50] w-56 bg-white border border-gray-200 rounded-lg shadow-lg py-1">
                            <div class="px-3 py-1.5 flex items-center justify-between">
                                <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">
                                    {{ trans("Filter by agent") }}
                                </span>
                                <button v-if="selectedAgentIds.length" type="button"
                                    class="text-[10px] text-gray-500 underline hover:text-gray-700"
                                    @click="clearAgentFilter">
                                    {{ trans("Clear") }}
                                </button>
                            </div>
                            <label v-for="a in availableAgents" :key="a.id"
                                class="flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" :checked="selectedAgentIds.includes(a.id)"
                                    class="rounded border-gray-300"
                                    :style="{ accentColor: 'var(--theme-color-4)' }"
                                    @change="toggleAgentFilter(a.id)" />
                                <span class="truncate">{{ a.name }}</span>
                            </label>
                            <div v-if="!availableAgents.length" class="px-3 py-3 text-xs text-gray-400 text-center">
                                {{ trans("No agents in this list") }}
                            </div>
                        </div>
                    </div>

                    <button class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500" @click="toggleSearch">
                        <FontAwesomeIcon :icon="showSearch ? faTimes : faSearch" class="text-xs" />
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div v-if="showSearch" class="px-3 py-2 border-b">
                <input v-model="searchQuery" type="text" :placeholder="trans('Search…')"
                    class="w-full text-sm border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1" />
            </div>

            <!-- Status segmented tabs -->
            <div v-if="!spamView && !trashView" class="px-3 py-2 border-b">
                <div class="flex items-center bg-gray-100 rounded-lg p-1 text-xs">
                    <button v-if="viewMode === 'my'" type="button"
                        class="flex-1 py-1.5 rounded-md transition-all inline-flex items-center justify-center gap-1"
                        :class="activeTab === 'waiting' ? 'bg-white shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab === 'waiting' ? { color: 'var(--theme-color-4)' } : {}"
                        @click="activeTab = 'waiting'">
                        {{ trans("Waiting") }}
                        <span v-if="viewMode === 'my' && !highlightView && tabUnread.waiting"
                            class="min-w-[15px] px-1 text-[9px] leading-[15px] text-white rounded-full text-center"
                            :style="{ backgroundColor: 'var(--theme-color-4)' }">{{ tabUnread.waiting }}</span>
                    </button>
                    <button type="button"
                        class="flex-1 py-1.5 rounded-md transition-all inline-flex items-center justify-center gap-1"
                        :class="activeTab === 'active' ? 'bg-white shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab === 'active' ? { color: 'var(--theme-color-4)' } : {}"
                        @click="activeTab = 'active'">
                        {{ trans("Active") }}
                        <span v-if="viewMode === 'my' && !highlightView && tabUnread.active"
                            class="min-w-[15px] px-1 text-[9px] leading-[15px] text-white rounded-full text-center"
                            :style="{ backgroundColor: 'var(--theme-color-4)' }">{{ tabUnread.active }}</span>
                    </button>
                    <button type="button"
                        class="flex-1 py-1.5 rounded-md transition-all inline-flex items-center justify-center gap-1"
                        :class="activeTab === 'closed' ? 'bg-white shadow-sm font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        :style="activeTab === 'closed' ? { color: 'var(--theme-color-4)' } : {}"
                        @click="activeTab = 'closed'">
                        {{ trans("Closed") }}
                        <span v-if="viewMode === 'my' && !highlightView && tabUnread.closed"
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
                        <div class="group relative flex items-center gap-3 px-3 py-2 border-b cursor-pointer transition-colors"
                            :class="selectedSession?.ulid === c.ulid ? '' : 'hover:bg-gray-50'"
                            :style="selectedSession?.ulid === c.ulid ? selectedItemStyle : {}"
                            @click="handleClickContact(c)"
                            @contextmenu.prevent="toggleRowMenu(c.ulid, $event)">
                            <div v-if="isAssigning[c.ulid] || isSpamming[c.ulid]"
                                class="absolute inset-0 bg-black/30 flex items-center justify-center z-10">
                                <LoadingIcon class="w-8 h-8 text-white" />
                            </div>

                            <button type="button"
                                class="absolute top-1/2 right-2 -translate-y-1/2 z-30 w-7 h-7 flex items-center justify-center rounded-full bg-white text-gray-500 shadow-md ring-1 ring-gray-200 hover:bg-gray-100 hover:text-gray-800 opacity-0 group-hover:opacity-100 transition-opacity"
                                :class="{ '!opacity-100': openMenuUlid === c.ulid }"
                                @click.stop="toggleRowMenu(c.ulid, $event)">
                                <FontAwesomeIcon :icon="faEllipsisVertical" class="text-sm" />
                            </button>

                            <div class="relative shrink-0">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center bg-gray-100 text-gray-500">
                                    <Image v-if="c.avatar" :src="c.avatar" class="w-full h-full rounded-full object-cover" />
                                    <FontAwesomeIcon v-else :icon="faUser" class="text-sm" />
                                </div>
                                <span v-if="isMergedView"
                                    v-tooltip="c.channel === 'whatsapp' ? 'WhatsApp' : trans('Website chat')"
                                    class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full bg-white ring-1 ring-gray-200 flex items-center justify-center">
                                    <FontAwesomeIcon :icon="c.channel === 'whatsapp' ? faWhatsapp : faGlobe"
                                        class="text-[9px]"
                                        :class="c.channel === 'whatsapp' ? 'text-green-600' : 'text-gray-400'" />
                                </span>
                            </div>

                            <div class="flex-1 min-w-0 flex flex-col gap-0.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-medium text-gray-800 truncate">{{ capitalize(c.name) }}</span>
                                    <span class="text-[10px] text-gray-400 shrink-0">{{ c.lastMessageTime }}</span>
                                </div>
                                <div v-if="(spamView || trashView || highlightView) && c.shop?.name" class="flex items-center gap-1 text-[10px] text-gray-400 truncate">
                                    <FontAwesomeIcon :icon="faStoreAlt" class="text-[9px] shrink-0" />
                                    <span class="truncate">{{ c.shop.name }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="c.agent?.name" class="text-[10px] text-gray-400 truncate">
                                        {{ c.agent.name.split(' ')[0] }}
                                    </span>
                                    <span v-if="c.unread && activeTab !== 'closed'"
                                        class="ml-auto min-w-[16px] px-1.5 text-[10px] leading-4 text-white rounded-full text-center shrink-0"
                                        :style="{ backgroundColor: 'var(--theme-color-4)' }">
                                        {{ c.unread }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-gray-500 truncate flex-1 leading-snug">{{ c.lastMessage }}</span>
                                    <button v-if="!trashView" type="button"
                                        v-tooltip="c.is_highlighted ? trans('Remove highlight') : trans('Highlight')"
                                        class="shrink-0 flex items-center justify-center transition-opacity"
                                        :class="c.is_highlighted ? 'text-amber-400 opacity-100' : 'text-gray-300 opacity-0 group-hover:opacity-100 hover:text-amber-400'"
                                        @click.stop="toggleHighlight(c)">
                                        <FontAwesomeIcon :icon="faStarSolid" class="text-[11px]" />
                                    </button>
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
                <WhatsappMessageAreaAgent v-if="activeChannel === 'whatsapp'"
                    :messages="messages" :session="selectedSession"
                    :organisation-slug="organisation.slug"
                    @back="selectedSession = null" @messages-read="onMessagesRead"
                    @assign-self-success="onAssignSelfSuccess"
                    @close-session="closeSession"
                    @view-profile="showProfilePanel" />
                <MessageAreaAgent v-else :messages="messages" :session="selectedSession"
                    @back="selectedSession = null" @send-message="handleSendMessage"
                    @close-session="closeSession" @view-history="showHistoryPanel"
                    @view-user-profile="showProfilePanel" @view-message-details="showMessageDetailsPanel"
                    @transfer-agent-success="onTransferAgentSuccess"
                    @assign-self-success="onAssignSelfSuccess" @messages-read="onMessagesRead"
                    @open-jira-settings="onOpenJiraSettings"
                    @open-slack-settings="onOpenSlackSettings"
                    @spam-success="onSpamFromThread"
                    @restore-success="onRestoreFromThread" />
            </div>
        </div>

        <!-- RIGHT: conversation profile panel (Conversation-style) -->
        <ChatConversationSidePanel v-if="panelSession && sidePanelVisible"
            :session="panelSession" @close="closeSidePanel" @priority-updated="onPriorityUpdated"
            @synced="onSessionSynced" @customer-synced="onCustomerSynced" />

        <!-- Row action menu (teleported so it is never clipped by the list's overflow) -->
        <Teleport to="body">
            <template v-if="menuContact">
                <div class="fixed inset-0 z-[998]" @click="closeRowMenu" @contextmenu.prevent="closeRowMenu"></div>
                <div class="fixed z-[999] w-48 bg-white border border-gray-200 rounded-md shadow-lg py-1"
                    :style="{ top: menuPos.top + 'px', left: menuPos.left + 'px' }" @click.stop>
                    <template v-if="trashView">
                        <button type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="restoreChat(menuContact)">
                            <FontAwesomeIcon :icon="faTrashArrowUp" class="text-[10px]" /> {{ trans("Restore") }}
                        </button>
                        <button v-if="confirmDeleteUlid !== menuContact.ulid" type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-red-600 hover:bg-red-50"
                            @click.stop="confirmDeleteUlid = menuContact.ulid">
                            <FontAwesomeIcon :icon="faTrash" class="text-[10px]" /> {{ trans("Delete permanently") }}
                        </button>
                        <button v-else type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-white bg-red-600 hover:bg-red-700"
                            @click="forceDeleteChat(menuContact)">
                            <FontAwesomeIcon :icon="faTrash" class="text-[10px]" /> {{ trans("Click again to confirm") }}
                        </button>
                    </template>

                    <template v-else>
                        <div class="relative group/prio">
                            <button type="button"
                                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100">
                                <FontAwesomeIcon :icon="priorityMeta(menuContact.priority).icon" class="text-[11px] w-3.5" :style="{ color: priorityMeta(menuContact.priority).color }" />
                                <span>{{ trans("Priority") }}</span>
                                <span class="ml-auto flex items-center gap-1 text-[10px] text-gray-500">
                                    {{ trans(priorityMeta(menuContact.priority).label) }}
                                    <FontAwesomeIcon :icon="faChevronRight" class="text-[8px] text-gray-400" />
                                </span>
                            </button>
                            <div class="absolute left-full top-0 hidden group-hover/prio:block w-40 bg-white border border-gray-200 rounded-md shadow-lg py-1">
                                <button v-for="p in PRIORITIES" :key="p.value" type="button"
                                    class="w-full flex items-center gap-2 px-3 py-1.5 text-xs hover:bg-gray-100"
                                    :class="menuContact.priority === p.value ? 'font-semibold text-gray-900' : 'text-gray-700'"
                                    @click="setPriority(menuContact, p.value)">
                                    <FontAwesomeIcon :icon="p.icon" class="text-[11px] w-3.5" :style="{ color: p.color }" />
                                    <span>{{ trans(p.label) }}</span>
                                    <span v-if="menuContact.priority === p.value" class="ml-auto text-[11px]" :style="{ color: p.color }">✓</span>
                                </button>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 my-1"></div>

                        <button type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="toggleHighlight(menuContact)">
                            <FontAwesomeIcon :icon="faStar" class="text-[10px]"
                                :class="menuContact.is_highlighted ? 'text-amber-400' : ''" />
                            {{ menuContact.is_highlighted ? trans("Remove highlight") : trans("Highlight") }}
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>

                        <button v-if="!menuContact.is_spam" type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="markSpam(menuContact, true)">
                            <FontAwesomeIcon :icon="faBan" class="text-[10px]" /> {{ trans("Report spam") }}
                        </button>
                        <button v-else type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="markSpam(menuContact, false)">
                            <FontAwesomeIcon :icon="faRotateLeft" class="text-[10px]" /> {{ trans("Not spam") }}
                        </button>
                        <button type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-red-600 hover:bg-red-50"
                            @click="trashChat(menuContact)">
                            <FontAwesomeIcon :icon="faTrash" class="text-[10px]" /> {{ trans("Move to trash") }}
                        </button>
                    </template>
                </div>
            </template>
        </Teleport>
    </div>
</template>
