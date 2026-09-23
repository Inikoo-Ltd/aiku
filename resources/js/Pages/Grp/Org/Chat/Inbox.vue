<script setup lang="ts">
import { ref, computed, inject, onMounted, onUnmounted, watch, nextTick } from "vue"
import { Head, router } from "@inertiajs/vue3"
import { useDebounceFn, useLocalStorage, watchDebounced } from "@vueuse/core"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { chatSendErrorText } from "@/Composables/chatSendError"
import { capitalize } from "@/Composables/capitalize"
import { cleanEmailText } from "@/Composables/cleanEmailText"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import MessageAreaAgent from "@/Components/Chat/Agent/MessageAreaAgent.vue"
import WhatsappMessageAreaAgent from "@/Components/Chat/Agent/WhatsappMessageAreaAgent.vue"
import ChatConversationSidePanel from "@/Components/Chat/ChatConversationSidePanel.vue"
import SettingChat from "@/Components/Chat/SettingChat.vue"
import NewWhatsappChatDialog from "@/Components/Chat/NewWhatsappChatDialog.vue"
import NewEmailChatDialog from "@/Components/Chat/NewEmailChatDialog.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch, faTimes } from "@far"
import { faCog, faStar, faAngleLeft, faAngleRight, faAngleDown, faFilter, faStoreAlt, faGlobe, faPlus, faEnvelope, faArchive, faPhone, faBell, faUser } from "@fal"
import { faEllipsisVertical, faBan, faRotateLeft, faTrash, faTrashArrowUp, faAnglesUp, faAngleUp, faEquals, faChevronRight, faStar as faStarSolid, faCircleCheck } from "@fortawesome/free-solid-svg-icons"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { formatChatTime, formatChatAge } from "@/Composables/chatTime"
import { useChatPhoneCall } from "@/Composables/useChatPhoneCall"
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
        is_read_only?: boolean
        can_start_email?: boolean
        channels: Array<{
            key: string
            name: string
            available?: boolean
            customer: { waiting: number; active: number; closed: number; mine: number; colleagues: number; closed_mine: number; closed_colleagues: number }
            guest: { waiting: number; active: number; closed: number; mine: number; colleagues: number; closed_mine: number; closed_colleagues: number }
        }>
        phone?: { in_progress: number; customer: number; guest: number }
    }>
    selectedSessionUlid?: string | null
    initialSession?: any | null
    preselectShopId?: number | null
    is_read_only?: boolean
    supervisor?: boolean
    agents?: Array<{ id: number; name: string | null; presence: "online" | "away" | "offline"; open: number; max: number; on_call?: boolean; on_call_since?: string | null }>
    ignoreReasons?: Array<{ value: string; label: string }>
}>()

const layout: any = inject("layout", {})
const baseUrl = layout?.appUrl ?? ""
const myAgentId = layout.user?.id

// Read only follows the shop being looked at, not the page: the same person answers chats on
// one shop and only oversees another, and the inbox shows them side by side.
const isReadOnly = computed(() => {
    const inbox = props.inboxes?.find((i) => i.id === selectedShopId.value)

    return inbox ? inbox.is_read_only === true : props.is_read_only === true
})

// An agent's list is theirs or their team's. Whoever oversees has no list of their own: they
// are shown every conversation on the shop, whoever holds it.
const listIsMine = computed(() => !isReadOnly.value && !props.supervisor)

const PLUS_8_HOURS = layout.app?.environment === "local" ? 8 * 60 * 60 * 1000 : 0

const contacts = ref<Contact[]>([])
type ChatStatus = "waiting" | "active" | "closed"

// The states are filters, not tabs: watching a quiet shop means wanting waiting and active
// side by side. An empty selection would list nothing, so one of them always stays on.
const selectedStatuses = ref<ChatStatus[]>(["waiting"])

const isStatusOn = (status: ChatStatus) => selectedStatuses.value.includes(status)

const toggleStatus = (status: ChatStatus) => {
    if (!isStatusOn(status)) {
        selectedStatuses.value = [...selectedStatuses.value, status]

        return
    }

    if (selectedStatuses.value.length > 1) {
        selectedStatuses.value = selectedStatuses.value.filter((s) => s !== status)
    }
}

// The counts come from the channel being looked at, so the capsules say how much work is in
// each state here rather than across every shop at once.
// The status capsules count exactly the squares that are on.
const ZERO_TALLY = { waiting: 0, active: 0, closed: 0, mine: 0, colleagues: 0, closed_mine: 0, closed_colleagues: 0 }

// Which shop's squares each capsule counts. One shop is the squares that are on; several shops
// is every channel on each of them, which is what the list is showing.
const countedTallies = computed(() => {
    if (selectedShopIds.value.length > 1) {
        return selectedShopIds.value.flatMap((shopId) => {
            const inbox = props.inboxes?.find((i) => i.id === shopId)

            return liveChannels(inbox).flatMap((channel) => [
                inbox?.channels?.find((c) => c.key === channel.key)?.customer,
                inbox?.channels?.find((c) => c.key === channel.key)?.guest,
            ])
        })
    }

    return selectedCells.value.map((cell) => {
        const [channelKey, kind] = cell.split(":") as [string, ChatKind]

        return selectedInbox.value?.channels?.find((c) => c.key === channelKey)?.[kind]
    })
})

const selectedChannelCounts = computed(() =>
    countedTallies.value.reduce((total, tally) => {
        return {
            waiting: total.waiting + (tally?.waiting ?? 0),
            active: total.active + (tally?.active ?? 0),
            closed: total.closed + (tally?.closed ?? 0),
            mine: total.mine + (tally?.mine ?? 0),
            colleagues: total.colleagues + (tally?.colleagues ?? 0),
            closed_mine: total.closed_mine + (tally?.closed_mine ?? 0),
            closed_colleagues: total.closed_colleagues + (tally?.closed_colleagues ?? 0),
        }
    }, { ...ZERO_TALLY })
)

const myChatsCount = computed(() => selectedChannelCounts.value.waiting + selectedChannelCounts.value.mine)
const colleaguesChatsCount = computed(() => selectedChannelCounts.value.colleagues)

const capsuleCount = (status: ChatStatus) => {
    if (status === "waiting" || !listIsMine.value) {
        return selectedChannelCounts.value[status]
    }

    const holder = viewMode.value === "team" ? "colleagues" : "mine"

    return selectedChannelCounts.value[status === "closed" ? `closed_${holder}` : holder]
}

// A colleague holds nothing that is waiting, and what they closed is not counted anywhere yet,
// so their capsules are Active with the number the rail already shows, and Closed without one.
const statusCapsules = computed(() =>
    ([
        { key: "waiting" as ChatStatus, label: ctrans("Waiting") },
        { key: "active" as ChatStatus, label: ctrans("Active") },
        { key: "closed" as ChatStatus, label: ctrans("Closed") },
    ])
        .filter((capsule) => capsule.key !== "waiting" || (viewMode.value === "my" && !agentView.value))
        .map((capsule) => ({
            ...capsule,
            count: agentView.value
                ? (capsule.key === "active" ? pickedAgentOpen.value : null)
                : capsuleCount(capsule.key),
        }))
)

const onlyClosed = computed(() => selectedStatuses.value.length === 1 && selectedStatuses.value[0] === "closed")

// Closed conversations are kept forever, so the list has to say how far back it reaches. Today
// is what the capsule counts and what the queue means by closed; the longer periods are
// somebody going back through the history, which is the same pair the tickets board uses.
const closedPeriods: { key: string; label: string }[] = [
    { key: "today", label: ctrans("Today") },
    { key: "24h", label: ctrans("24h") },
    { key: "1w", label: ctrans("1 week") },
    { key: "1m", label: ctrans("1 month") },
    { key: "1y", label: ctrans("1 year") },
    { key: "all", label: ctrans("All") },
]

const closedPeriod = ref("today")

const setClosedPeriod = (period: string) => {
    closedPeriod.value = period
    afterSelectionChanged()
}

// Nobody is waiting on a colleague's chat, so the team view drops that state and keeps
// whatever else was picked, falling back to active rather than to nothing.
const dropWaitingInTeamView = (): boolean => {
    if (viewMode.value !== "team" || !isStatusOn("waiting")) {
        return false
    }

    const rest = selectedStatuses.value.filter((s) => s !== "waiting")
    selectedStatuses.value = rest.length ? rest : ["active"]

    return true
}
const spamView = ref(false)
const rubbishView = ref(false)
const trashView = ref(false)
const highlightView = ref(false)

// Nobody has taken these, past the time agreed for their channel. The group's queue, not this
// agent's: which shops somebody works is exactly what let last week's chats go unanswered, so
// it spans every shop and every channel and carries no my/team of its own.
const unclaimedView = ref(false)
const unclaimedCount = ref(0)
const spamCount = ref(0)

// Folded, the rail is one icon wide and has nowhere to put the number, so the tooltip says it.
const spamRailTooltip = computed(() =>
    spamCount.value
        ? ctrans("Spam (:count)", { count: String(spamCount.value) })
        : ctrans("Spam")
)

// The list header already names the shop; only the views that span shops need it repeated
// on the conversation.
const crossShopView = computed(() => trashView.value || rubbishView.value || spamView.value || highlightView.value || unclaimedView.value)

const openMenuUlid = ref<string | null>(null)
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
        customer_suggestion: (s as any).customer_suggestion ?? null,
        phone_number: (s as any).phone_number ?? null,
        shop_name: s.shop?.name ?? null,
        status: s.status,
        priority: s.priority ?? null,
        assigned_agent: s.assigned_agent?.name ?? null,
        assigned_agent_id: s.assigned_agent?.id ?? null,
        started: s.created_at ?? null,
        ai_summary: s.ai_summary ?? null,
    }
})

const selectedCellFill = "color-mix(in srgb, var(--theme-color-4) 18%, white)"

const selectedItemStyle = {
    backgroundColor: selectedCellFill,
    boxShadow: "inset 3px 0 0 var(--theme-color-4)",
}

const sidePanelVisible = ref(false)
const sidePanelPreferred = useLocalStorage(`chat-inbox-side-panel:${layout.user?.id ?? "anonymous"}`, true)

watch(() => [panelSession.value?.ulid, panelSession.value?.is_guest, !!panelSession.value?.customer_suggestion?.customer], ([ulid, isGuest, hasSuggestion]) => {
    if (ulid && (!isGuest || hasSuggestion)) sidePanelVisible.value = sidePanelPreferred.value
}, { immediate: true })

const chatSettingVisible = ref(false)
const settingInitialTab = ref<"general" | "slack">("general")

const newChatVisible = ref(false)
const newEmailVisible = ref(false)
const openChatSettings = () => {
    settingInitialTab.value = "general"
    chatSettingVisible.value = true
}
const onOpenSlackSettings = () => {
    settingInitialTab.value = "slack"
    chatSettingVisible.value = true
}

const mapSession = (s: SessionAPI): Contact => ({
    id: s.id,
    ulid: s.ulid,
    // Merged views tag every row; single-channel views inherit the selected channel.
    channel: (s as any).channel ?? (selectedChannel.value === "whatsapp" ? "whatsapp" : "website"),
    name: s.contact_name || s.guest_identifier || "",
    avatar: s.image ?? "",
    lastMessage: cleanEmailText(s.last_message?.message),
    lastMessageTime: s.last_message?.created_at
        ? formatChatTime(new Date(s.last_message.created_at).getTime() + PLUS_8_HOURS)
        : undefined,
    lastMessageAge: s.last_message?.created_at
        ? formatChatAge(new Date(s.last_message.created_at).getTime() + PLUS_8_HOURS)
        : undefined,
    unread: s.unread_count,
    status: s.status,
    is_spam: (s as any).is_spam ?? false,
    is_rubbish: (s as any).is_rubbish ?? false,
    can_dispose: (s as any).can_dispose,
    open_tickets_count: Number((s as any).open_tickets_count ?? 0),
    blocking_tickets_count: Number((s as any).blocking_tickets_count ?? 0),
    noise: (s as any).noise ?? null,
    customer_suggestion: (s as any).customer_suggestion ?? null,
    is_highlighted: (s as any).is_highlighted ?? false,
    webUser: s.web_user ?? (s as any).customer,
    country_code: (s as any).country_code ?? null,
    priority: s.priority,
    guest_profile: s.guest_profile,
    metadata: (s as any).metadata ?? null,
    phone_number: (s as any).phone_number ?? null,
    agent: s.assigned_agent,
    shop: s.shop,
    organisation: s.organisation,
    ai_summary: s.ai_summary ?? null,
})

// Whoever oversees can hold several shops at once; an agent's rail is still one shop, which is
// what a list of one reads as. Everything that needs a single shop — the squares, a new
// WhatsApp chat, the team badge — asks for `selectedShopId` and stands down when several are on.
const selectedShopIds = ref<number[]>(props.inboxes?.[0]?.id ? [props.inboxes[0].id] : [])
const selectedShopId = computed<number | null>(() =>
    selectedShopIds.value.length === 1 ? selectedShopIds.value[0] : null
)
const setShop = (shopId: number | null) => {
    selectedShopIds.value = shopId === null ? [] : [shopId]
}

// A call is filed against the shop being worked when it starts, which is nearly always right and
// can be put straight in the modal when it is not.
const {
    state: phoneCallState,
    openModal: openPhoneCallModal,
    setPreferredShop,
} = useChatPhoneCall()

const openPhoneCall = () => {
    setPreferredShop(selectedShopId.value ?? props.preselectShopId ?? null)
    openPhoneCallModal()
}

// The telephone column leaves the inbox rather than filtering it: a call is not a conversation,
// and the list beside it only knows how to show conversations.
const openPhoneCalls = (inbox: { slug: string }) => {
    router.visit(
        route("grp.org.shops.show.chat.phone_calls.index", [props.organisation.slug, inbox.slug])
    )
}

watch(
    selectedShopId,
    (shopId) => setPreferredShop(shopId ?? props.preselectShopId ?? null),
    { immediate: true }
)
// Every shop shows all three columns; only the ones that can hold something can be picked.
const liveChannels = (inbox?: { channels?: Array<{ key: string; available?: boolean }> }) =>
    (inbox?.channels ?? []).filter((c) => c.available !== false)
// The squares that are on, as "channel:kind". Everything else about the selection is read
// from these, so there is one source of truth for what the list is showing.
const firstChannelKey = liveChannels(props.inboxes?.[0])[0]?.key
const selectedCells = ref<string[]>(
    firstChannelKey ? [`${firstChannelKey}:customer`, `${firstChannelKey}:guest`] : []
)

const selectedChannels = computed(() => [...new Set(selectedCells.value.map((c) => c.split(":")[0]))])

// An agent works the same squares every day, so the choice outlives the page. It is kept per
// browser rather than on the account: it is how somebody is working right now, not a setting,
// and a blocked or cleared store must never stop the inbox loading.
const SELECTION_KEY = `chat-inbox-selection:${layout.user?.id ?? "anonymous"}`

const readStoredSelection = () => {
    try {
        const raw = window.localStorage.getItem(SELECTION_KEY)

        return raw ? JSON.parse(raw) : null
    } catch {
        return null
    }
}

const storeSelection = () => {
    try {
        window.localStorage.setItem(SELECTION_KEY, JSON.stringify({
            shopIds: selectedShopIds.value,
            cells: selectedCells.value,
            statuses: selectedStatuses.value,
            closedPeriod: closedPeriod.value,
        }))
    } catch {
        // A private window, or storage turned off. Losing the choice is not worth an error.
    }
}

/**
 * Only what still exists is restored: shops come and go with the positions somebody holds, and
 * a channel disappears from a shop that stops receiving on it.
 */
const restoreSelection = (): boolean => {
    const stored = readStoredSelection()
    const inbox = props.inboxes?.find((i) => i.id === (stored?.shopIds?.[0] ?? stored?.shopId))

    if (!inbox) {
        return false
    }

    const available = new Set(
        liveChannels(inbox).flatMap((c) => [`${c.key}:customer`, `${c.key}:guest`])
    )
    const cells = (stored.cells ?? []).filter((c: string) => available.has(c))

    if (!cells.length) {
        return false
    }

    setShop(inbox.id)
    selectedCells.value = cells

    const statuses = (stored.statuses ?? []).filter((x: string) => ["waiting", "active", "closed"].includes(x))

    if (statuses.length) {
        selectedStatuses.value = statuses as ChatStatus[]
    }

    if (closedPeriods.some((p) => p.key === stored.closedPeriod)) {
        closedPeriod.value = stored.closedPeriod
    }

    return true
}

// One channel selected still means "this is a WhatsApp list" to everything downstream. With
// several on there is no single answer, so it goes null and each row is read from its own
// channel instead.
const selectedChannel = computed(() => (selectedChannels.value.length === 1 ? selectedChannels.value[0] : null))

const isChannelOn = (key: string) => selectedChannels.value.includes(key)

type ChatKind = "customer" | "guest"

const KINDS: Array<{ key: ChatKind; initial: string; label: string }> = [
    { key: "customer", initial: "C", label: ctrans("Customers") },
    { key: "guest", initial: "G", label: ctrans("Guests") },
]

const cellKey = (channelKey: string, kind: ChatKind) => `${channelKey}:${kind}`

// A colleague's load is not a shop's list, so while one is picked no shop is the open one:
// every row folds back to its line and nothing reads as selected.
//
// Every shop that is on shows its own squares, because somebody covering three shops works one
// channel across all of them: the squares that are lit are the same everywhere, they are the
// filter, and only the numbers beside them belong to the shop they are under.
const shopIsOpen = (shopId: number) => !agentView.value && selectedShopIds.value.includes(shopId)

const shopIsOn = (shopId: number) => !agentView.value && selectedShopIds.value.includes(shopId)

// Overseeing several shops at once: the rail's rows are a filter, and with more than one on
// there is no shop to square off, so the channel table folds away and every channel is read.
const toggleShop = (shopId: number) => {
    if (!props.supervisor) {
        return selectInbox(shopId)
    }

    const on = selectedShopIds.value.includes(shopId)

    if (on && selectedShopIds.value.length === 1) {
        return selectInbox(shopId)
    }

    agentView.value = false
    selectedShopIds.value = on
        ? selectedShopIds.value.filter((id) => id !== shopId)
        : [...selectedShopIds.value, shopId]

    if (selectedShopIds.value.length === 1) {
        return selectInbox(selectedShopIds.value[0])
    }

    // The squares stay as they were. Adding a second shop is asking for the same work in one
    // more place, not for everything that shop holds.
    afterSelectionChanged()
}

const isCellOn = (shopId: number, channelKey: string, kind: ChatKind) =>
    shopIsOpen(shopId) && selectedCells.value.includes(cellKey(channelKey, kind))

// Any set of squares can be on at once, and they travel as pairs rather than as a list of
// channels and a list of kinds: wanting email from strangers and website from customers is
// not the same as wanting both channels from both.
const selectCell = (shopId: number, channelKey: string, kind: ChatKind) => {
    const key = cellKey(channelKey, kind)
    const alreadyShowing = selectedShopIds.value.includes(shopId) && !agentView.value && !spamView.value && !trashView.value && !highlightView.value && !unclaimedView.value
    const sameShop = alreadyShowing && (selectedShopId.value === shopId || selectedShopIds.value.length > 1)

    if (!sameShop) {
        spamView.value = false
        rubbishView.value = false
        trashView.value = false
        highlightView.value = false
        unclaimedView.value = false
        setShop(shopId)
        selectedCells.value = [key]
        afterSelectionChanged()

        return
    }

    const on = selectedCells.value.includes(key)

    if (on && selectedCells.value.length === 1) {
        return
    }

    selectedCells.value = on
        ? selectedCells.value.filter((c) => c !== key)
        : [...selectedCells.value, key]

    afterSelectionChanged()
}

const isMultiChannel = computed(() => selectedChannels.value.length > 1)

const buildParams = (page: number) => ({
    ...(trashView.value
        ? { trashed: 1 }
        : rubbishView.value
            ? { is_rubbish: 1 }
            : spamView.value
                ? { is_spam: 1 }
                : unclaimedView.value
                    ? { unclaimed: 1 }
                    : highlightView.value
                        ? { highlighted: 1, statuses: selectedStatuses.value }
                        : { statuses: selectedStatuses.value }),
    ...(isStatusOn("closed") ? { closed_period: closedPeriod.value } : {}),
    ...(listIsMine.value && !unclaimedView.value ? { assigned_to_me: myAgentId } : {}),
    ...(selectedAgentIds.value.length ? { agent_ids: selectedAgentIds.value } : {}),
    page,
    ...(selectedShopIds.value.length && !highlightView.value && !unclaimedView.value && !agentView.value
        ? { shop_ids: selectedShopIds.value }
        : {}),
    ...(selectedCells.value.length && !agentView.value && !unclaimedView.value ? { pairs: selectedCells.value } : {}),
    ...(viewMode.value === "team" && listIsMine.value && !unclaimedView.value ? { view_team: 1 } : {}),
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
})

// Spam, trash and highlight are cross-channel clean-up views, so they read from the
// merged endpoint instead of whichever channel happens to be selected.
const isMergedView = computed(() =>
    spamView.value || rubbishView.value || trashView.value || highlightView.value || unclaimedView.value || agentView.value
    || selectedShopIds.value.length > 1
)


const sessionsUrl = computed(() => {
    if (isMergedView.value || isMultiChannel.value) {
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

// A burst of chat-list broadcasts (a message, its read receipt, an assignment) reloads the list once
const reloadContactsSoon = useDebounceFn(reloadContacts, 500)

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
}

const toggleRowMenu = (ulid: string, ev?: MouseEvent) => {
    if (isReadOnly.value) return
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

// Same shape as marking spam, without reporting anybody: the sender is never blocked, and the
// conversation keeps its status so taking the mark off puts it back where it was.
const markRubbish = async (c: Contact, rubbish: boolean, reason?: string) => {
    if (isSpamming.value[c.ulid]) return
    openMenuUlid.value = null
    isSpamming.value = { ...isSpamming.value, [c.ulid]: true }
    try {
        const routeName = sessionRoute(rubbish ? "rubbish" : "not_rubbish", c)
        await axios.patch(
            route(routeName, [props.organisation.slug, c.ulid]),
            reason ? { reason } : {},
            { withCredentials: true }
        )
        contacts.value = contacts.value.filter((x) => x.ulid !== c.ulid)
        if (selectedSession.value?.ulid === c.ulid) {
            selectedSession.value = null
            messages.value = []
        }
        fetchInboxNotifications()
    } catch (e: any) {
        errorPerContact.value[c.ulid] = e?.response?.data?.message ?? "Failed to mark as rubbish"
    } finally {
        isSpamming.value = { ...isSpamming.value, [c.ulid]: false }
    }
}

const showAgentFilter = ref(false)
const selectedAgentIds = ref<Array<number | string>>([])

// Picking a colleague asks what they are holding, which is never one shop's question: their
// load is counted across every shop they work, so the list drops the shop and the channels.
const agentView = ref(false)

const pickedAgent = computed(() =>
    props.agents?.find((a) => a.id === selectedAgentIds.value[0]) ?? null
)

const pickedAgentOpen = computed(() => pickedAgent.value?.open ?? null)

const pickedAgentName = computed(() =>
    pickedAgent.value?.name ?? null
)

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
    reloadContacts()
}

const clearAgentFilter = () => {
    selectedAgentIds.value = []
    agentView.value = false
    reloadContacts()
}

// One colleague at a time from the rail: what is this person holding. Somebody holds a
// conversation only once it is active, so the list turns to those.
const showAgentLoad = (id: number) => {
    const alreadyOn = selectedAgentIds.value.length === 1 && selectedAgentIds.value[0] === id

    selectedAgentIds.value = alreadyOn ? [] : [id]
    agentView.value = !alreadyOn
    if (!alreadyOn) {
        selectedStatuses.value = ["active"]
    }
    reloadContacts()
}

const PRESENCE_DOT: Record<string, string> = {
    online: "bg-green-500",
    away: "bg-amber-400",
    offline: "bg-gray-300",
}


const linkedContact = ref<Contact | null>(null)
const viewModeForAgent = (assignedUserId: unknown): "my" | "team" =>
    assignedUserId && String(assignedUserId) !== String(myAgentId) ? "team" : "my"
const revealViewFor = (contact: Contact): void => {
    const mode = viewModeForAgent((contact.agent as any)?.user_id)
    // Team has no waiting bucket; those threads are nobody's yet.
    const tab = mode === "team" && contact.status === "waiting" ? "active" : contact.status

    if (viewMode.value !== mode) {
        viewMode.value = mode
    }

    if (tab && ["waiting", "active", "closed"].includes(tab) && !isStatusOn(tab as ChatStatus)) {
        selectedStatuses.value = [tab as ChatStatus]
    }
}

const matchesCurrentView = (c: Contact) =>
    (spamView.value || trashView.value || unclaimedView.value ? true : selectedStatuses.value.includes(c.status as ChatStatus)) &&
    (highlightView.value || unclaimedView.value || agentView.value || !selectedShopIds.value.length
        || (c.shop?.id && selectedShopIds.value.includes(c.shop.id))) &&
    (!selectedAgentIds.value.length ||
        (c.agent?.id && selectedAgentIds.value.includes(c.agent.id)))

const filteredContacts = computed(() => {
    const list = contacts.value.filter(matchesCurrentView)
    const linked = linkedContact.value

    if (linked && matchesCurrentView(linked) && !list.some((c) => c.ulid === linked.ulid)) {
        return [linked, ...list]
    }

    return list
})

const selectedInbox = computed(() =>
    props.inboxes?.find((i) => i.id === selectedShopId.value) ?? props.inboxes?.[0] ?? null
)

// Writing a fresh email needs one shop, its mailbox, and the right to answer on it.
const canStartEmail = computed(
    () => selectedChannel.value === "email" && !isReadOnly.value && !!selectedShopId.value
        && selectedInbox.value?.can_start_email === true
)

const inboxRailCollapsed = useLocalStorage(`chat-inbox-rail-collapsed:${layout.user?.id ?? "anonymous"}`, false)

/* Two ways the rail can be folded, kept apart on purpose. The one above is what the user chose,
   and it is remembered; the one below is this page folding it for them on a narrow screen, and
   it lives only as long as the page - otherwise a phone would decide how their desktop opens. */
const railAutoCollapsed = ref(false)

const railCollapsed = computed(() => inboxRailCollapsed.value || railAutoCollapsed.value)

const expandRail = () => {
    railAutoCollapsed.value = false
    inboxRailCollapsed.value = false
}

const toggleRail = () => {
    if (railCollapsed.value) {
        expandRail()

        return
    }

    inboxRailCollapsed.value = true
}

/* The rail holds three lists, and which of them matters depends on the day: a supervisor lives
   in Agents, somebody clearing the queue lives in Folders. Each one folds away, and the two
   lower ones are dragged to the height their owner wants; Shops takes whatever is left. */
const railSectionOpen = useLocalStorage(`chat-rail-sections:${layout.user?.id ?? "anonymous"}`, {
    shops: true,
    agents: true,
    folders: true,
})

const railSectionHeight = useLocalStorage(`chat-rail-heights:${layout.user?.id ?? "anonymous"}`, {
    agents: 180,
    folders: 176,
})

const MIN_SECTION_HEIGHT = 72

/* The chat is the page, so it runs from under the heading down to the footer. Two things make
   that awkward to write as a calc: what sits above it varies (banner, breadcrumbs, sub nav), and
   the layout holds back `pb-24` under every page for a footer that is fixed and a third of that
   tall. So the height is measured against the window, and the negative margin below gives back
   the padding the chat now covers - without it the page keeps that 96px and scrolls. */
const chatArea = ref<HTMLElement | null>(null)
const chatAreaHeight = ref<string>("calc(100vh - 10rem)")

const fitChatArea = () => {
    const element = chatArea.value

    if (!element) {
        return
    }

    const footer = document.querySelector("footer")
    const footerHeight = footer ? footer.getBoundingClientRect().height : 0
    const next = Math.max(320, window.innerHeight - element.getBoundingClientRect().top - footerHeight)

    // Nothing moves unless it has to, or the observer below and this become a loop.
    if (Math.abs(next - parseFloat(chatAreaHeight.value)) < 1) {
        return
    }

    chatAreaHeight.value = `${next}px`
}

let chatAreaObserver: ResizeObserver | null = null

onMounted(async () => {
    await nextTick()
    fitChatArea()
    await nextTick()
    fitChatArea()
    window.addEventListener("resize", fitChatArea)

    const main = chatArea.value?.closest("main")

    if (main && typeof ResizeObserver !== "undefined") {
        chatAreaObserver = new ResizeObserver(fitChatArea)
        chatAreaObserver.observe(main)
    }
})

onUnmounted(() => {
    window.removeEventListener("resize", fitChatArea)
    chatAreaObserver?.disconnect()
})

const railElement = ref<HTMLElement | null>(null)

/* On a tablet or a phone the rail is a third of the screen, so it is opened to pick a shop and
   then wants to be out of the way: four seconds after the last touch it folds itself back.
   Anything the pointer or keyboard does inside it starts the count again, so it never closes
   under somebody mid-scroll. On a desktop there is room for it, and folding a rail the user
   left open would just be the page arguing with them. */
const RAIL_IDLE_MS = 4000

const narrowScreen = typeof window !== "undefined" && typeof window.matchMedia === "function"
    ? window.matchMedia("(max-width: 1023px)")
    : null

let railIdleTimer: ReturnType<typeof setTimeout> | null = null

const clearRailIdle = () => {
    if (railIdleTimer) {
        clearTimeout(railIdleTimer)
        railIdleTimer = null
    }
}

const startRailIdle = () => {
    clearRailIdle()

    if (railCollapsed.value || !narrowScreen?.matches) {
        return
    }

    railIdleTimer = setTimeout(() => {
        railAutoCollapsed.value = true
    }, RAIL_IDLE_MS)
}

watch(railCollapsed, startRailIdle)

onMounted(() => {
    startRailIdle()
    narrowScreen?.addEventListener("change", startRailIdle)
})

onUnmounted(() => {
    clearRailIdle()
    narrowScreen?.removeEventListener("change", startRailIdle)
})

const startResize = (section: "agents" | "folders", event: PointerEvent) => {
    event.preventDefault()

    const startY = event.clientY
    const startHeight = railSectionHeight.value[section]
    const limit = Math.max(MIN_SECTION_HEIGHT, (railElement.value?.clientHeight ?? 600) * 0.6)

    const onMove = (move: PointerEvent) => {
        // The handle sits above the section, so dragging up makes it taller.
        const next = startHeight + (startY - move.clientY)
        railSectionHeight.value = { ...railSectionHeight.value, [section]: Math.min(limit, Math.max(MIN_SECTION_HEIGHT, next)) }
    }

    const onUp = () => {
        window.removeEventListener("pointermove", onMove)
        window.removeEventListener("pointerup", onUp)
        document.body.style.userSelect = ""
    }

    document.body.style.userSelect = "none"
    window.addEventListener("pointermove", onMove)
    window.addEventListener("pointerup", onUp)
}

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

// Picking a shop means picking its first channel: there is nothing left to unfold, since the
// channels sit on the row itself.
const selectInbox = (shopId: number) => {
    if (railCollapsed.value) {
        expandRail()
    }

    const inbox = props.inboxes?.find((i) => i.id === shopId)

    selectChannel(shopId, liveChannels(inbox)[0]?.key ?? "website")
}

// Select + expand an inbox without reloading — callers on mount reload once afterwards.
// When `channelKey` is provided (e.g. from URL params) it takes precedence over the
// default first channel so that deep-links like `?channel=whatsapp` are honoured.
const revealInbox = (shopId: number, channelKey?: string | null) => {
    setShop(shopId)
    const inbox = props.inboxes?.find((i) => i.id === shopId)
    const resolved = channelKey && liveChannels(inbox).some((ch) => ch.key === channelKey)
        ? channelKey
        : liveChannels(inbox)[0]?.key ?? null
    selectedCells.value = resolved ? [cellKey(resolved, "customer"), cellKey(resolved, "guest")] : []
}

// A whole channel, both kinds of sender: what a deep link or a shop click asks for.
const selectChannel = (shopId: number, channelKey: string) => {
    spamView.value = false
    rubbishView.value = false
    trashView.value = false
    highlightView.value = false
    unclaimedView.value = false
    setShop(shopId)
    selectedCells.value = [cellKey(channelKey, "customer"), cellKey(channelKey, "guest")]
    afterSelectionChanged()
}

// Everything a change of selection has to clear, whether a whole channel or one square.
function afterSelectionChanged() {
    selectedSession.value = null
    linkedContact.value = null
    messages.value = []
    newChatVisible.value = false
    newEmailVisible.value = false
    selectedAgentIds.value = []
    agentView.value = false

    const baseUrl = route(props.supervisor ? "grp.org.chat.supervision" : "grp.org.chat.inbox", [props.organisation.slug])
    window.history.replaceState(window.history.state, "", baseUrl)

    reloadContacts()
}

// Rubbish is the takeover backlog: an out of office, a circular, a newsletter. Not spam, so
// nobody is blocked, and the conversation keeps its status so unmarking restores it exactly.
const selectRubbish = () => {
    if (rubbishView.value) return
    rubbishView.value = true
    spamView.value = false
    trashView.value = false
    highlightView.value = false
    unclaimedView.value = false
    setShop(null)
    selectedCells.value = []
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectSpam = () => {
    if (spamView.value) return
    spamView.value = true
    rubbishView.value = false
    trashView.value = false
    highlightView.value = false
    unclaimedView.value = false
    setShop(null)
    selectedCells.value = []
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectTrash = () => {
    if (trashView.value) return
    trashView.value = true
    rubbishView.value = false
    spamView.value = false
    highlightView.value = false
    unclaimedView.value = false
    setShop(null)
    selectedCells.value = []
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

// Taking one out of here is answering it: the row leaves the queue as soon as somebody holds
// it, so the list is reloaded rather than trusted after any action.
const selectUnclaimed = () => {
    if (unclaimedView.value) return
    unclaimedView.value = true
    highlightView.value = false
    rubbishView.value = false
    spamView.value = false
    trashView.value = false
    setShop(null)
    selectedCells.value = []
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectHighlight = () => {
    if (highlightView.value) return
    highlightView.value = true
    rubbishView.value = false
    spamView.value = false
    trashView.value = false
    setShop(null)
    selectedCells.value = []
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    dropWaitingInTeamView()
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
    if (linkedContact.value?.ulid === ulid) {
        linkedContact.value = null
    }
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

const onAgentAssigned = (agent: { id: number; name: string }) => {
    const ulid = selectedSession.value?.ulid
    if (selectedSession.value) {
        selectedSession.value.assigned_agent = { id: String(agent.id), name: agent.name }
    }
    const found = contacts.value.find((x) => x.ulid === ulid)
    if (found) found.agent = { id: String(agent.id), name: agent.name }
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

const onSessionUnlinked = () => {
    if (!selectedSession.value) return
    const ulid = selectedSession.value.ulid
    const guestName = (selectedSession.value as any).metadata?.name || (selectedSession.value as any).guest_profile?.name || selectedSession.value.guest_identifier || "Guest"
    selectedSession.value = {
        ...selectedSession.value,
        web_user: null,
        web_user_id: null,
        is_guest: true,
        contact_name: guestName,
    } as SessionAPI
    const found = contacts.value.find((x) => x.ulid === ulid)
    if (found) {
        found.webUser = null
        found.name = guestName
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

const teamUnreadByShop = ref<Record<number, number>>({})

const pendingSessionUlid = ref<string | null>(null)

const applyChannelFromUrl = () => {
    const params = new URLSearchParams(window.location.search)
    const channel = params.get("channel")

    if (channel === "whatsapp" || channel === "website") {
        selectedCells.value = [cellKey(channel, "customer"), cellKey(channel, "guest")]
    }

    pendingSessionUlid.value = params.get("session")
}


// reloadContacts() calls this on every load, and the lookup below reloads in turn, so
// the pending ulid is cleared before that happens and a lookup already in flight is not
// started a second time. Without both, the two functions call each other forever.
const isResolvingPendingSession = ref(false)

const openPendingSession = async () => {
    if (!pendingSessionUlid.value || isResolvingPendingSession.value) return

    const contact = contacts.value.find((c) => String(c.ulid) === pendingSessionUlid.value)

    if (contact) {
        pendingSessionUlid.value = null
        openChat(contact)
        return
    }

    // Session not in the current tab — look it up by ulid so it is found wherever it
    // sits in the list, then switch to the matching tab before opening.
    const ulid = pendingSessionUlid.value
    isResolvingPendingSession.value = true

    try {
        const url = selectedChannel.value === "whatsapp"
            ? `${baseUrl}/app/api/chats/meta/sessions`
            : `${baseUrl}/app/api/chats/sessions`

        // Deliberately unscoped by the selected shop: a link carries no shop, so the
        // sidebar's default inbox would hide a chat belonging to any other one. The
        // backend still limits this to shops the agent handles.
        const { data } = await axios.get(url, {
            params: {
                ulid,
                ...(listIsMine.value ? { assigned_to_me: myAgentId } : {}),
                page: 1,
                limit: 1,
            },
            withCredentials: true,
        })

        const sessions = data?.data?.sessions ?? []
        const found = sessions.find((s: any) => String(s.ulid) === ulid)

        if (!found) {
            pendingSessionUlid.value = null
            return
        }

        const mapped = mapSession(found)

        // Cleared before the reload below, because that reload calls back into here.
        pendingSessionUlid.value = null

        if (mapped.shop?.id && mapped.shop.id !== selectedShopId.value) {
            revealInbox(mapped.shop.id, mapped.channel)
        }

        revealViewFor(mapped)

        await nextTick()
        await reloadContacts()
        linkedContact.value = mapped
        openChat(mapped)
    } catch (e) {
        console.error("Failed to fetch pending session:", e)
    } finally {
        isResolvingPendingSession.value = false
    }
}

// The rail's table and its red badge are the same counts, so they are refreshed together
// and from one place: counted apart, the badge said 40 over a row that added up to 26.
const reloadInboxCountsSoon = useDebounceFn(() => router.reload({ only: ["inboxes"] }), 500)

const fetchInboxNotifications = async () => {
    reloadInboxCountsSoon()

    if (!myAgentId || isReadOnly.value) return
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/users/${myAgentId}/agent-notifications`)
        teamUnreadByShop.value = data?.data?.team_unread ?? {}
        unclaimedCount.value = data?.data?.unclaimed ?? 0
        spamCount.value = data?.data?.spam ?? 0
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

// Who is waiting, across the shop's channels: the bold numbers on the customer row in red
// and on the guest row in amber, so each badge can be checked against the table under it.
// Kept apart because most guests are nobody: an abandoned widget, a bounce, a circular.
// Active is the grey numbers of both rows, in blue: a conversation somebody took is work
// still open, and one left open for ever is how a customer ends up answered by nobody.
const countByInbox = (tally: (channel: any) => number) =>
    Object.fromEntries(
        (props.inboxes ?? []).map((inbox) => [
            inbox.id,
            liveChannels(inbox).reduce((sum, c) => sum + tally(c), 0),
        ])
    ) as Record<number, number>

const inboxUnread = computed(() => countByInbox((c) => c.customer?.waiting ?? 0))
const inboxGuestsWaiting = computed(() => countByInbox((c) => c.guest?.waiting ?? 0))
const inboxActive = computed(() => countByInbox((c) => (c.customer?.active ?? 0) + (c.guest?.active ?? 0)))

const openChat = (c: Contact) => {
    // Moving to another chat retires the linked one, so it does not sit pinned above
    // the list once the agent has moved on.
    if (linkedContact.value && linkedContact.value.ulid !== c.ulid) {
        linkedContact.value = null
    }

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
        is_spam: c.is_spam,
        is_rubbish: c.is_rubbish,
        can_dispose: (c as any).can_dispose,
        open_tickets_count: (c as any).open_tickets_count ?? 0,
        blocking_tickets_count: (c as any).blocking_tickets_count ?? 0,
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
    selectedStatuses.value = ["active"]
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
    // The address follows the conversation that is open, not the sidebar: in a mixed list the
    // two disagree, and a WhatsApp chat deep-linked as a website one does not reopen.
    const page = props.supervisor ? "grp.org.chat.supervision" : "grp.org.chat.inbox"
    const url = activeChannel.value === "whatsapp"
        ? route(page, [props.organisation.slug]) + `?channel=whatsapp&session=${ulid}`
        : route(`${page}.conversation`, [props.organisation.slug, ulid])

    window.history.replaceState(window.history.state, "", url)
}

const handleSendMessage = async ({ text, files, message_type, is_email_notif, onFailed }: {
    text: string
    files?: File[]
    message_type: "text" | "image" | "file"
    tempId: number
    is_email_notif: boolean
    onFailed?: (message: string) => void
}) => {
    if (!selectedSession.value?.ulid) return
    try {
        const formData = new FormData()
        formData.append("message_text", text ?? "")
        formData.append("message_type", message_type)
        formData.append("sender_type", "agent")
        formData.append("is_email_notif", String(is_email_notif ?? false))

        if (files?.length === 1) {
            formData.append(message_type === "image" ? "image" : "file", files[0])
        } else {
            files?.forEach((file) => formData.append("attachments[]", file))
        }

        await axios.post(
            route("grp.org.chat.agents.messages.send", [props.organisation.slug, selectedSession.value.ulid]),
            formData,
            { headers: { "Content-Type": "multipart/form-data" }, withCredentials: true }
        )
    } catch (error) {
        console.error("Error sending message:", error)

        onFailed?.(chatSendErrorText(error, ctrans("The message was not sent. Try again.")))
    }
}

const toggleSidePanel = () => {
    sidePanelVisible.value = !sidePanelVisible.value
    sidePanelPreferred.value = sidePanelVisible.value
}
const showHistoryPanel = () => toggleSidePanel()
const showProfilePanel = () => toggleSidePanel()
const sidePanelTab = ref<'profile' | 'tickets'>('profile')
const showTicketsPanel = () => {
    sidePanelTab.value = 'tickets'
    sidePanelVisible.value = true
    sidePanelPreferred.value = true
}
const showMessageDetailsPanel = () => toggleSidePanel()
const closeSidePanel = () => {
    sidePanelVisible.value = false
    sidePanelPreferred.value = false
}

const closeSession = async () => {
    selectedSession.value = null
    await reloadContacts()
}

const onTransferAgentSuccess = async () => {
    sidePanelVisible.value = false
    selectedSession.value = null
    await reloadContacts()
}


watch([selectedStatuses, viewMode], async () => {
    selectedSession.value = null
    linkedContact.value = null
    messages.value = []
    if (dropWaitingInTeamView()) {
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
    reloadContactsSoon()
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

    if (!isMergedView.value && !isChannelOn("whatsapp")) return

    reloadContactsSoon()

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
    // What the address asks for wins over what was last looked at.
    if (!props.preselectShopId && !props.selectedSessionUlid) {
        restoreSelection()
    }

    applyChannelFromUrl()
    fetchInboxNotifications()

    const urlChannel = selectedChannel.value

    // Preselect the shop when opened from the shop-level nav entry.
    if (props.preselectShopId && props.inboxes?.some((i) => i.id === props.preselectShopId)) {
        revealInbox(props.preselectShopId, urlChannel)
    }

    // Registered after the restore so putting the stored choice back does not write it again.
    watch([selectedShopIds, selectedCells, selectedStatuses], storeSelection, { deep: true })

    const init = props.initialSession

    // Jump to the shop (inbox) the opened chat belongs to.
    if (init?.shop?.id) {
        revealInbox(init.shop.id, urlChannel)
    }

    if (init) {
        const mapped = mapSession(init)

        revealViewFor(mapped)

        await nextTick()
        await reloadContacts()

        linkedContact.value = mapped
        openChat(mapped)
    } else {
        await reloadContacts()
        await nextTick()
        openSelectedFromProp()
    }

    if (window.Echo) {
        // The shops in the rail are the ones this person may work, so they are also the ones
        // worth listening to. Reading them from the assignment table left an agent whose shops
        // come from their position with no live updates at all.
        const shopIds: number[] = (props.inboxes ?? []).map((i) => i.id)
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
            <button v-if="!isReadOnly" type="button" @click="openPhoneCall"
                v-tooltip="phoneCallState.call ? ctrans('You are on a phone call') : ctrans('Log a phone call')"
                class="p-2 rounded-lg transition-colors"
                :class="phoneCallState.call
                    ? 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100'
                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'">
                <FontAwesomeIcon :icon="faPhone" class="text-base" fixed-width />
            </button>
            <button v-if="!isReadOnly" type="button" v-tooltip="ctrans('Chat settings')" @click="openChatSettings"
                class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors">
                <FontAwesomeIcon :icon="faCog" class="text-base" fixed-width />
            </button>
        </template>
    </PageHeading>

    <Dialog v-model:visible="chatSettingVisible" modal :header="ctrans('Chat Settings')"
        :style="{ width: '90vw', maxWidth: '560px' }" :breakpoints="{ '640px': '95vw' }">
        <SettingChat :initial-tab="settingInitialTab" :session-ulid="selectedSession?.ulid" @close="chatSettingVisible = false" />
    </Dialog>

    <NewWhatsappChatDialog v-model:visible="newChatVisible" :shop-id="selectedShopId"
        @created="onWhatsappChatCreated" />

    <NewEmailChatDialog v-model:visible="newEmailVisible" :shop-id="selectedShopId" />

    <div ref="chatArea" :style="{ height: chatAreaHeight }"
        class="relative flex overflow-hidden border-t border-gray-200 bg-white -mb-6 md:-mb-24">
        <!-- PANEL 1: Inboxes (shops the agent handles) -->
        <div ref="railElement" class="shrink-0 border-r border-gray-200 flex flex-col bg-gray-50 transition-all duration-200"
            :class="railCollapsed ? 'w-16' : 'w-64'"
            @pointerdown="startRailIdle" @pointermove="startRailIdle" @wheel="startRailIdle"
            @focusin="startRailIdle" @keydown="startRailIdle">
            <!-- Header + collapse toggle -->
            <div class="border-b border-gray-200 flex items-center h-[41px]"
                :class="railCollapsed ? 'justify-center' : 'justify-between px-3'">
                <button v-if="!railCollapsed" type="button"
                    class="flex items-center gap-1.5 text-[11px] font-semibold text-gray-500 uppercase tracking-wide hover:text-gray-700"
                    @click="railSectionOpen = { ...railSectionOpen, shops: !railSectionOpen.shops }">
                    <FontAwesomeIcon :icon="railSectionOpen.shops ? faAngleDown : faAngleRight" class="text-[10px]" />
                    {{ supervisor ? ctrans("Shops") : ctrans("Inboxes") }}
                </button>
                <button type="button" @click="toggleRail"
                    v-tooltip="railCollapsed ? ctrans('Expand') : ctrans('Collapse')"
                    class="py-1 px-2 rounded hover:bg-gray-200 text-gray-600">
                    <FontAwesomeIcon :icon="railCollapsed ? faAngleRight : faAngleLeft" class="text-xs" fixed-width />
                </button>
            </div>

            <!-- Shop list -->
            <div v-if="!railCollapsed && !railSectionOpen.shops" class="flex-1" />

            <div v-show="railCollapsed || railSectionOpen.shops" class="flex-1 overflow-y-auto [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300">
                <div v-for="inbox in inboxes" :key="inbox.id"
                    class="transition-colors border-b border-gray-200"
                    :class="shopIsOn(inbox.id) ? 'bg-white' : 'hover:bg-gray-100'">
                    <button type="button" @click="toggleShop(inbox.id)"
                        v-tooltip="railCollapsed ? inbox.name : undefined"
                        class="w-full flex items-center gap-2 min-w-0"
                        :class="[
                            railCollapsed ? 'justify-center py-1' : 'px-2 py-1',
                            shopIsOn(inbox.id) ? 'font-medium text-gray-800' : 'text-gray-700',
                        ]">
                        <!-- The initials only stand in for the name when the rail is folded and
                             there is no room for it; beside the name they said it twice. -->
                        <div v-if="railCollapsed" class="relative shrink-0">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-[11px] font-bold"
                                :style="shopAvatarStyle(inbox)">
                                {{ shopInitials(inbox.name) }}
                            </div>
                            <span v-if="inboxUnread[inbox.id]"
                                v-tooltip="ctrans('Customers waiting')"
                                class="absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-red-500 ring-2 ring-gray-50">
                                {{ inboxUnread[inbox.id] }}
                            </span>
                        </div>
                        <FontAwesomeIcon v-if="!railCollapsed && supervisor" :icon="faCircleCheck"
                            class="shrink-0 text-sm transition-colors"
                            :class="shopIsOn(inbox.id) ? 'text-green-500' : 'text-gray-300'" fixed-width />
                        <span v-if="!railCollapsed" class="flex-1 truncate text-sm text-left">{{ inbox.name }}</span>
                        <span v-if="!railCollapsed && inboxUnread[inbox.id]"
                            v-tooltip="ctrans('Customers waiting')"
                            class="shrink-0 min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-red-500">
                            {{ inboxUnread[inbox.id] }}
                        </span>
                        <span v-if="!railCollapsed && inboxGuestsWaiting[inbox.id]"
                            v-tooltip="ctrans('Guests waiting')"
                            class="shrink-0 min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-amber-500">
                            {{ inboxGuestsWaiting[inbox.id] }}
                        </span>
                        <span v-if="!railCollapsed && inboxActive[inbox.id]"
                            v-tooltip="ctrans('Active')"
                            class="shrink-0 min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-blue-500">
                            {{ inboxActive[inbox.id] }}
                        </span>
                    </button>

                    <!-- A little table instead of a row of capsules: channels across, who is
                         on the other end down. Cells line up by construction, and a count of
                         zero holds its place so the eye can run down a column. -->
                    <table v-if="!railCollapsed && shopIsOpen(inbox.id)" class="w-full text-[11px] tabular-nums mb-1">
                        <thead>
                            <tr>
                                <th class="w-4"></th>
                                <th v-for="channel in inbox.channels" :key="channel.key"
                                    class="font-normal pb-0.5 border-b border-slate-100">
                                    <FontAwesomeIcon :icon="channel.key === 'whatsapp' ? faWhatsapp : channel.key === 'email' ? faEnvelope : faGlobe"
                                        class="text-[12px]"
                                        :class="channel.available === false ? 'text-slate-300' : channel.key === 'whatsapp' ? 'text-green-600' : channel.key === 'email' ? 'text-blue-500' : 'text-gray-500'" fixed-width />
                                </th>
                                <!-- A call being taken right now belongs to neither row until it
                                     is filed, so it is said once here rather than guessed into
                                     the customer or the guest line. -->
                                <th class="font-normal pb-0.5 border-b border-slate-100 border-l"
                                    v-tooltip="inbox.phone?.in_progress
                                        ? ctrans(':count on the phone right now', { count: String(inbox.phone.in_progress) })
                                        : ctrans('Phone calls finished today')">
                                    <span class="relative inline-flex items-center justify-center">
                                        <FontAwesomeIcon :icon="faPhone" class="text-[12px]"
                                            :class="inbox.phone?.in_progress ? 'text-emerald-600' : 'text-gray-500'" fixed-width />
                                        <span v-if="inbox.phone?.in_progress" class="absolute -top-0.5 -right-1 flex h-1.5 w-1.5">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                        </span>
                                    </span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="kind in KINDS" :key="kind.key">
                                <td v-tooltip="kind.label" class="text-[9px] font-bold text-center border-r border-slate-100"
                                    :class="kind.key === 'customer' ? 'text-green-500' : 'text-blue-400'">
                                    <FontAwesomeIcon v-if="kind.key === 'customer'" :icon="faUser" class="text-[10px]" fixed-width aria-hidden="true" />
                                    <template v-else>{{ kind.initial }}</template>
                                </td>
                                <td v-for="channel in inbox.channels" :key="channel.key"
                                    class="border border-slate-100">
                                    <button type="button" :disabled="channel.available === false"
                                        class="w-full flex items-center justify-center gap-1 px-1 py-0.5 leading-5 transition-colors"
                                        :class="channel.available === false ? 'bg-slate-50/60 cursor-default' : isCellOn(inbox.id, channel.key, kind.key) ? '' : 'hover:bg-slate-100'"
                                        :style="channel.available !== false && isCellOn(inbox.id, channel.key, kind.key) ? { backgroundColor: selectedCellFill } : {}"
                                        @click="selectCell(inbox.id, channel.key, kind.key)">
                                        <span v-if="channel.available === false" class="text-slate-300">&mdash;</span>
                                        <!-- The selected cell is filled, the same way the rest of
                                             the page marks what is selected. A box around it drew
                                             a blob across the rows that were on together. -->
                                        <span v-if="channel.available !== false" class="font-semibold"
                                            :class="isCellOn(inbox.id, channel.key, kind.key) ? '' : 'text-slate-700'"
                                            :style="isCellOn(inbox.id, channel.key, kind.key) ? { color: 'var(--theme-color-4)' } : {}">
                                            {{ channel[kind.key].waiting }}
                                        </span>
                                        <span v-if="channel.available !== false"
                                            :class="isCellOn(inbox.id, channel.key, kind.key) ? 'opacity-50' : 'text-slate-400'"
                                            :style="isCellOn(inbox.id, channel.key, kind.key) ? { color: 'var(--theme-color-4)' } : {}">
                                            {{ channel[kind.key].active }}
                                        </span>
                                    </button>
                                </td>
                                <td class="border border-slate-100 border-l-slate-200">
                                    <button type="button" @click="openPhoneCalls(inbox)"
                                        v-tooltip="ctrans('Phone calls on this shop')"
                                        class="w-full flex items-center justify-center px-1 py-0.5 leading-5 transition-colors hover:bg-slate-100">
                                        <span class="font-semibold"
                                            :class="inbox.phone?.[kind.key] ? 'text-slate-700' : 'text-slate-400'">
                                            {{ inbox.phone?.[kind.key] ?? 0 }}
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="!inboxes.length && !railCollapsed" class="px-3 py-6 text-xs text-gray-400 text-center">
                    {{ ctrans("No inboxes assigned") }}
                </div>
            </div>

            <!-- The people on these shops: who is there and what they are holding -->
            <template v-if="supervisor && !railCollapsed">
                <div v-if="railSectionOpen.agents" class="h-1 shrink-0 cursor-row-resize bg-gray-200 hover:bg-[--app-accent]"
                    v-tooltip="ctrans('Drag to resize')" @pointerdown="startResize('agents', $event)" />

                <div class="shrink-0 border-t border-gray-200">
                    <button type="button"
                        class="flex w-full items-center gap-1.5 px-3 pt-2 pb-1 text-[11px] font-semibold text-gray-500 uppercase tracking-wide hover:text-gray-700"
                        @click="railSectionOpen = { ...railSectionOpen, agents: !railSectionOpen.agents }">
                        <FontAwesomeIcon :icon="railSectionOpen.agents ? faAngleDown : faAngleRight" class="text-[10px]" />
                        {{ ctrans("Agents") }}
                        <span class="ml-auto font-normal normal-case tabular-nums text-gray-400">{{ agents?.length ?? 0 }}</span>
                    </button>
                </div>

                <div v-show="railSectionOpen.agents" class="shrink-0 overflow-y-auto [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300"
                    :style="{ height: railSectionHeight.agents + 'px' }">
                <button v-for="agent in agents" :key="agent.id" type="button"
                    v-tooltip="ctrans('Show what they are holding')"
                    class="w-full flex items-center gap-2 px-3 py-1 text-sm text-left"
                    :class="selectedAgentIds.includes(agent.id) ? 'font-medium text-gray-800' : 'text-gray-700 hover:bg-gray-100'"
                    :style="selectedAgentIds.includes(agent.id) ? { backgroundColor: selectedCellFill } : {}"
                    @click="showAgentLoad(agent.id)">
                    <span class="w-2 h-2 rounded-full shrink-0" :class="PRESENCE_DOT[agent.presence]" />
                    <span class="flex-1 truncate" :class="agent.presence === 'offline' ? 'text-gray-400' : ''">{{ agent.name }}</span>
                    <FontAwesomeIcon v-if="agent.on_call" :icon="faPhone" class="text-[11px] text-emerald-600 shrink-0"
                        v-tooltip="ctrans('On a phone call, taking no new conversations')" fixed-width />
                    <span class="text-[11px] tabular-nums" :class="agent.open >= agent.max ? 'text-red-500 font-semibold' : 'text-gray-400'">
                        {{ agent.open }}/{{ agent.max }}
                    </span>
                </button>
                    <div v-if="!agents?.length" class="px-3 py-3 text-xs text-gray-400">
                        {{ ctrans("Nobody is on these shops") }}
                    </div>
                </div>
            </template>

            <!-- Everything that is not a shop: the group's queue and the places things are put -->
            <div v-if="!railCollapsed && railSectionOpen.folders" class="h-1 shrink-0 cursor-row-resize bg-gray-200 hover:bg-[--app-accent]"
                v-tooltip="ctrans('Drag to resize')" @pointerdown="startResize('folders', $event)" />

            <div v-if="!railCollapsed" class="shrink-0 border-t border-gray-200">
                <button type="button"
                    class="flex w-full items-center gap-1.5 px-3 pt-2 pb-1 text-[11px] font-semibold text-gray-500 uppercase tracking-wide hover:text-gray-700"
                    @click="railSectionOpen = { ...railSectionOpen, folders: !railSectionOpen.folders }">
                    <FontAwesomeIcon :icon="railSectionOpen.folders ? faAngleDown : faAngleRight" class="text-[10px]" />
                    {{ ctrans("Folders") }}
                    <span v-if="unclaimedCount" class="ml-auto rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-semibold normal-case text-white">
                        {{ unclaimedCount }}
                    </span>
                </button>
            </div>

            <div v-show="railCollapsed || railSectionOpen.folders"
                class="shrink-0 overflow-y-auto [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300"
                :style="railCollapsed ? {} : { height: railSectionHeight.folders + 'px' }">
            <div class="py-1">
                <button type="button" @click="selectUnclaimed"
                    v-tooltip="ctrans('Nobody has taken these yet, on any shop')"
                    class="w-full flex items-center text-sm transition-colors"
                    :class="[
                        railCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2',
                        unclaimedView ? 'font-medium text-gray-800' : 'text-gray-600 hover:bg-gray-100',
                    ]"
                    :style="unclaimedView ? selectedItemStyle : {}">
                    <!-- Folded, the count rides on the bell the way the shops' does on their
                         initials; beside it there is no width for both. -->
                    <span class="relative shrink-0">
                        <FontAwesomeIcon :icon="faBell" class="text-sm"
                            :class="unclaimedCount ? 'text-red-500' : unclaimedView ? 'text-gray-600' : ''" fixed-width />
                        <span v-if="railCollapsed && unclaimedCount"
                            class="absolute -top-2 -right-2.5 min-w-[16px] h-4 px-1 rounded-full bg-red-500 text-center text-[9px] font-semibold leading-4 text-white ring-2 ring-gray-50">
                            {{ unclaimedCount }}
                        </span>
                    </span>
                    <span v-if="!railCollapsed" class="flex-1 text-left">{{ ctrans("Unclaimed") }}</span>
                    <span v-if="!railCollapsed && unclaimedCount"
                        class="text-[10px] font-semibold rounded-full px-1.5 py-0.5 bg-red-500 text-white shrink-0">
                        {{ unclaimedCount }}
                    </span>
                </button>
            </div>

            <!-- Spam -->
            <div v-if="!isReadOnly" class="border-t border-gray-200 py-1">
                <button type="button" @click="selectSpam"
                    v-tooltip="railCollapsed ? spamRailTooltip : undefined"
                    class="w-full flex items-center text-sm transition-colors"
                    :class="[
                        railCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2',
                        spamView ? 'font-medium text-gray-800' : 'text-gray-600 hover:bg-gray-100',
                    ]"
                    :style="spamView ? selectedItemStyle : {}">
                    <FontAwesomeIcon :icon="faBan" class="text-sm shrink-0" :class="spamView ? 'text-red-500' : ''" fixed-width />
                    <span v-if="!railCollapsed" class="flex-1 text-left">{{ ctrans("Spam") }}</span>
                    <!-- Grey, unlike the unclaimed badge: what is in the bin is how much there is
                         to clear out, never anything anybody has to hurry to. -->
                    <span v-if="!railCollapsed && spamCount" class="text-[11px] tabular-nums text-gray-400 shrink-0">
                        {{ spamCount }}
                    </span>
                </button>
                <button type="button" @click="selectRubbish"
                    v-tooltip="railCollapsed ? ctrans('Ignored') : undefined"
                    class="w-full flex items-center text-sm transition-colors"
                    :class="[
                        railCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2',
                        rubbishView ? 'font-medium text-gray-800' : 'text-gray-600 hover:bg-gray-100',
                    ]"
                    :style="rubbishView ? selectedItemStyle : {}">
                    <FontAwesomeIcon :icon="faArchive" class="text-sm shrink-0" :class="rubbishView ? 'text-gray-600' : ''" fixed-width />
                    <span v-if="!railCollapsed">{{ ctrans("Ignored") }}</span>
                </button>
                <button type="button" @click="selectTrash"
                    v-tooltip="railCollapsed ? ctrans('Trash') : undefined"
                    class="w-full flex items-center text-sm transition-colors"
                    :class="[
                        railCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2',
                        trashView ? 'font-medium text-gray-800' : 'text-gray-600 hover:bg-gray-100',
                    ]"
                    :style="trashView ? selectedItemStyle : {}">
                    <FontAwesomeIcon :icon="faTrash" class="text-sm shrink-0" :class="trashView ? 'text-red-500' : ''" fixed-width />
                    <span v-if="!railCollapsed">{{ ctrans("Trash") }}</span>
                </button>
            </div>

            <!-- Highlighted -->
            <div v-if="!isReadOnly" class="border-t border-gray-200 py-1">
                <button type="button" @click="selectHighlight"
                    v-tooltip="railCollapsed ? ctrans('Highlighted') : undefined"
                    class="w-full flex items-center text-sm transition-colors"
                    :class="[
                        railCollapsed ? 'justify-center py-2.5' : 'gap-2.5 px-3 py-2',
                        highlightView ? 'font-medium text-gray-800' : 'text-gray-600 hover:bg-gray-100',
                    ]"
                    :style="highlightView ? selectedItemStyle : {}">
                    <FontAwesomeIcon :icon="faStar" class="text-sm shrink-0" :class="highlightView ? 'text-amber-400' : ''" fixed-width />
                    <span v-if="!railCollapsed">{{ ctrans("Highlighted") }}</span>
                </button>
            </div>
            </div>
        </div>

        <!-- PANEL 2: conversation list for the selected inbox.
             Narrow screens have room for one column, not three, so the list and the thread take
             turns: the list until a conversation is picked, the thread after, with its own back
             button to return. From lg up both stand side by side as before. -->
        <div class="border-r border-gray-200 flex-col lg:w-80 lg:shrink-0 lg:flex-none"
            :class="selectedSession ? 'hidden lg:flex' : 'flex flex-1 min-w-0'">
            <!-- Selected inbox + My/Team segmented toggle -->
            <div class="px-3 py-2.5 border-b flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-800 truncate mb-1.5">
                        {{ trashView ? ctrans("Trash") : rubbishView ? ctrans("Ignored") : spamView ? ctrans("Spam") : unclaimedView ? ctrans("Unclaimed") : highlightView ? ctrans("Highlighted") : agentView ? (pickedAgentName ?? ctrans("Inbox")) : selectedShopIds.length > 1 ? ctrans("Selected shops") : (selectedInbox?.name ?? ctrans("Inbox")) }}
                    </div>
                    <div v-if="agentView" class="text-[11px] text-gray-500">
                        {{ ctrans("Across every shop") }}
                    </div>
                    <div v-else-if="unclaimedView" class="text-[11px] text-gray-500">
                        {{ ctrans("Every shop, waiting longer than agreed") }}
                    </div>
                    <div v-else-if="selectedShopIds.length > 1" class="text-[11px] text-gray-500">
                        {{ selectedShopIds.length }} {{ ctrans("shops, every channel") }}
                    </div>
                    <div v-else-if="supervisor && !spamView && !trashView && !unclaimedView" class="text-[11px] text-gray-500">
                        {{ ctrans("Everybody's conversations") }}
                    </div>
                    <div v-else-if="!spamView && !trashView && !unclaimedView && !isReadOnly" class="inline-flex items-center bg-gray-100 rounded-lg p-0.5 text-[11px]">
                        <button type="button" class="px-2.5 py-1 rounded-md transition-all whitespace-nowrap shrink-0"
                            :class="viewMode === 'my' ? 'bg-white shadow-sm text-gray-800 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                            @click="viewMode = 'my'">
                            {{ ctrans("My Chats") }}
                            <span v-if="myChatsCount" class="ml-1 tabular-nums text-gray-500">{{ myChatsCount }}</span>
                        </button>
                        <button type="button" class="px-2.5 py-1 rounded-md transition-all whitespace-nowrap shrink-0 inline-flex items-center gap-1"
                            :class="viewMode === 'team' ? 'bg-white shadow-sm text-gray-800 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                            @click="viewMode = 'team'">
                            {{ ctrans("Colleagues' Chats") }}
                            <span class="tabular-nums text-gray-500">{{ colleaguesChatsCount }}</span>
                            <span v-if="teamUnreadForShop"
                                v-tooltip="ctrans('Unread chats held by colleagues in this inbox — take over to reply')"
                                class="min-w-[15px] px-1 text-[9px] leading-[15px] text-white rounded-full text-center bg-amber-500">
                                {{ teamUnreadForShop }}
                            </span>
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-0.5 shrink-0 self-start">
                    <button v-if="canStartEmail" type="button"
                        v-tooltip="ctrans('New email')"
                        class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-gray-100 text-indigo-600 text-[11px] font-medium"
                        @click="newEmailVisible = true">
                        <FontAwesomeIcon :icon="faPlus" class="text-xs" fixed-width />
                        {{ ctrans("New email") }}
                    </button>

                    <button v-if="selectedChannel === 'whatsapp' && !isReadOnly" type="button"
                        v-tooltip="ctrans('New WhatsApp chat')"
                        class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-gray-100 text-green-600 text-[11px] font-medium"
                        @click="newChatVisible = true">
                        <FontAwesomeIcon :icon="faPlus" class="text-xs" fixed-width />
                        {{ ctrans("New chat") }}
                    </button>

                    <!-- Filter by agent -->
                    <div class="relative">
                        <button type="button" v-tooltip="ctrans('Filter by agent')"
                            class="relative p-1.5 rounded-lg hover:bg-gray-100 text-gray-500"
                            @click="showAgentFilter = !showAgentFilter">
                            <FontAwesomeIcon :icon="faFilter" class="text-xs" fixed-width />
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
                                    {{ ctrans("Filter by agent") }}
                                </span>
                                <button v-if="selectedAgentIds.length" type="button"
                                    class="text-[10px] text-gray-500 underline hover:text-gray-700"
                                    @click="clearAgentFilter">
                                    {{ ctrans("Clear") }}
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
                                {{ ctrans("No agents in this list") }}
                            </div>
                        </div>
                    </div>

                    <button class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500" @click="toggleSearch">
                        <FontAwesomeIcon :icon="showSearch ? faTimes : faSearch" class="text-xs" fixed-width />
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div v-if="showSearch" class="px-3 py-2 border-b">
                <label for="chat-inbox-search" class="sr-only">{{ ctrans("Search conversations") }}</label>
                <input id="chat-inbox-search" v-model="searchQuery" type="text" :placeholder="ctrans('Search…')"
                    class="w-full text-sm border rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1" />
            </div>

            <!-- Status capsules: any combination, never none -->
            <div v-if="!spamView && !trashView && !unclaimedView" class="px-3 py-2 border-b">
                <div class="flex items-center gap-1.5 text-xs">
                    <button v-for="capsule in statusCapsules" :key="capsule.key" type="button"
                        v-tooltip="ctrans('Show or hide these, at least one stays on')"
                        class="flex-1 py-1.5 px-2 rounded-full border transition-all inline-flex items-center justify-center gap-1"
                        :class="isStatusOn(capsule.key)
                            ? 'bg-white shadow-sm font-semibold border-transparent'
                            : 'border-gray-200 text-gray-500 hover:text-gray-700'"
                        :style="isStatusOn(capsule.key) ? { color: 'var(--theme-color-4)', borderColor: 'var(--theme-color-4)' } : {}"
                        @click="toggleStatus(capsule.key)">
                        {{ capsule.label }}
                        <span v-if="capsule.count"
                            class="min-w-[15px] px-1 text-[9px] leading-[15px] rounded-full text-center"
                            :class="isStatusOn(capsule.key) ? 'text-white' : 'text-gray-600 bg-gray-200'"
                            :style="isStatusOn(capsule.key) ? { backgroundColor: 'var(--theme-color-4)' } : {}">{{ capsule.count }}</span>
                    </button>
                </div>

                <!-- How far back the closed list reaches. Only on when closed is being looked
                     at: waiting and active are open work and have no period. -->
                <div v-if="isStatusOn('closed')" class="mt-1.5 flex items-center gap-1 text-[10px] text-gray-500">
                    <span class="uppercase tracking-wide text-gray-400">{{ ctrans("Closed") }}</span>
                    <button v-for="period in closedPeriods" :key="period.key" type="button"
                        class="px-1.5 py-0.5 rounded transition-colors"
                        :class="closedPeriod === period.key ? 'font-semibold text-white' : 'hover:bg-gray-100'"
                        :style="closedPeriod === period.key ? { backgroundColor: 'var(--theme-color-4)' } : {}"
                        @click="setClosedPeriod(period.key)">
                        {{ period.label }}
                    </button>
                </div>
            </div>

            <!-- List (flat, for the selected inbox) -->
            <div class="flex-1 overflow-y-auto [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300">
                <div v-if="filteredContacts.length === 0"
                    class="h-full flex flex-col items-center justify-center gap-2 text-center px-4">
                    <div class="text-2xl">💬</div>
                    <div class="text-sm font-medium text-gray-700">{{ ctrans("No conversations") }}</div>
                </div>

                <div v-else>
                    <div v-for="c in filteredContacts" :key="c.ulid">
                        <div class="group relative flex items-center gap-2 px-3 py-2 border-b cursor-pointer transition-colors"
                            :class="selectedSession?.ulid === c.ulid ? '' : 'hover:bg-gray-50'"
                            :style="selectedSession?.ulid === c.ulid ? selectedItemStyle : {}"
                            @click="handleClickContact(c)"
                            @contextmenu.prevent="toggleRowMenu(c.ulid, $event)">
                            <div v-if="isAssigning[c.ulid] || isSpamming[c.ulid]"
                                class="absolute inset-0 bg-black/30 flex items-center justify-center z-10">
                                <LoadingIcon class="w-8 h-8 text-white" />
                            </div>

                            <button v-if="!isReadOnly" type="button"
                                class="absolute top-1/2 right-2 -translate-y-1/2 z-30 w-7 h-7 flex items-center justify-center rounded-full bg-white text-gray-500 shadow-md ring-1 ring-gray-200 hover:bg-gray-100 hover:text-gray-800 opacity-0 group-hover:opacity-100 transition-opacity"
                                :class="{ '!opacity-100': openMenuUlid === c.ulid }"
                                @click.stop="toggleRowMenu(c.ulid, $event)">
                                <FontAwesomeIcon :icon="faEllipsisVertical" class="text-sm" fixed-width />
                            </button>

                            <div class="flex-1 min-w-0 flex flex-col gap-0.5">
                                <div class="flex items-center justify-between gap-2">
                                    <!-- Only the guests are marked. A customer is already shown
                                         by their name being a link through to their record, and
                                         a badge on every other row says nothing. -->
                                    <span v-if="!c.webUser?.customer_id"
                                        class="shrink-0 text-[9px] px-1 py-0.5 border border-blue-300 text-blue-400 leading-none">
                                        G
                                    </span>
                                    <img v-if="(c as any).country_code" :src="`/flags/${(c as any).country_code.toLowerCase()}.png`"
                                        :alt="(c as any).country_code" v-tooltip="(c as any).country_code" class="shrink-0 h-3 w-auto" />
                                    <span class="flex-1 min-w-0 text-sm text-gray-800 truncate" :class="c.unread && !onlyClosed ? 'font-bold' : 'font-medium'">{{ capitalize(c.name) }}</span>
                                    <span class="text-[10px] text-gray-500 shrink-0">
                                        {{ c.lastMessageTime }}
                                        <span v-if="c.lastMessageAge" class="text-gray-400">({{ c.lastMessageAge }})</span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-[10px] text-gray-400 min-w-0">
                                    <span v-if="(spamView || trashView || highlightView || unclaimedView || agentView || selectedShopIds.length > 1) && c.shop?.name"
                                        class="flex items-center gap-1 min-w-0 truncate">
                                        <FontAwesomeIcon :icon="faStoreAlt" class="text-[9px] shrink-0" fixed-width />
                                        <span class="truncate">{{ c.shop.name }}</span>
                                    </span>
                                    <span v-if="c.agent?.name" class="truncate">
                                        {{ c.agent.name.split(' ')[0] }}
                                    </span>
                                    <span v-if="c.customer_suggestion?.customer && !c.webUser?.customer_id" v-tooltip="c.customer_suggestion.label"
                                        class="shrink-0 truncate rounded bg-amber-50 px-1 font-medium text-amber-700">
                                        {{ ctrans("Probably") }} {{ c.customer_suggestion.customer.name }}
                                    </span>
                                    <span v-if="c.noise" v-tooltip="c.noise.note"
                                        class="shrink-0 rounded px-1 font-medium"
                                        :class="c.noise.automatic ? 'bg-gray-100 text-gray-600' : 'bg-amber-50 text-amber-700'">
                                        {{ c.noise.automatic ? ctrans("Put aside automatically") : ctrans("Possible noise") }}: {{ c.noise.label }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs truncate flex-1 leading-snug" :class="c.unread && !onlyClosed ? 'font-semibold text-gray-800' : 'text-gray-500'">{{ c.lastMessage }}</span>
                                    <button v-if="!c.webUser?.customer_id && !c.is_spam && !trashView && !onlyClosed && !isReadOnly" type="button"
                                        :disabled="isSpamming[c.ulid]"
                                        v-tooltip="ctrans('Report spam')"
                                        class="shrink-0 flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors disabled:opacity-50"
                                        @click.stop="markSpam(c, true)">
                                        <FontAwesomeIcon :icon="faBan" class="text-[11px]" fixed-width />
                                    </button>
                                    <button v-if="!trashView && !isReadOnly" type="button"
                                        v-tooltip="c.is_highlighted ? ctrans('Remove highlight') : ctrans('Highlight')"
                                        class="shrink-0 flex items-center justify-center transition-opacity"
                                        :class="c.is_highlighted ? 'text-amber-400 opacity-100' : 'text-gray-300 opacity-0 group-hover:opacity-100 hover:text-amber-400'"
                                        @click.stop="toggleHighlight(c)">
                                        <FontAwesomeIcon :icon="faStarSolid" class="text-[11px]" fixed-width />
                                    </button>
                                    <span v-if="c.unread && !onlyClosed"
                                        v-tooltip="ctrans('Unread messages')"
                                        class="shrink-0 min-w-[16px] h-4 px-1 text-[9px] font-semibold leading-4 text-white rounded-full text-center bg-red-500">
                                        {{ c.unread }}
                                    </span>
                                    <!-- Always shown, not only in a mixed list: an agent reading
                                         one row has to know whether to answer a chat or an email,
                                         and the sidebar is not where they are looking. -->
                                    <FontAwesomeIcon
                                        :icon="c.channel === 'whatsapp' ? faWhatsapp : c.channel === 'email' ? faEnvelope : faGlobe"
                                        class="shrink-0 text-xs"
                                        :class="c.channel === 'whatsapp' ? 'text-green-600' : c.channel === 'email' ? 'text-blue-500' : 'text-gray-400'" fixed-width />
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
        <div class="flex-1 min-w-0 relative" :class="selectedSession ? '' : 'hidden lg:block'">
            <div v-if="!selectedSession"
                class="h-full flex flex-col items-center justify-center gap-2 text-gray-400">
                <div class="text-4xl">💬</div>
                <div class="text-sm">{{ ctrans("Select a conversation") }}</div>
            </div>

            <div v-else class="h-full">
                <WhatsappMessageAreaAgent v-if="activeChannel === 'whatsapp'"
                    :messages="messages" :session="selectedSession"
                    :organisation-slug="organisation.slug"
                    :read-only="isReadOnly" :show-shop="crossShopView"
                    @back="selectedSession = null" @messages-read="onMessagesRead"
                    @assign-self-success="onAssignSelfSuccess"
                    @close-session="closeSession"
                    @spam-success="onSpamFromThread"
                    @view-profile="showProfilePanel" />
                <MessageAreaAgent v-else :messages="messages" :session="selectedSession"
                    :read-only="isReadOnly" :ignore-reasons="ignoreReasons" :show-shop="crossShopView"
                    @back="selectedSession = null" @send-message="handleSendMessage"
                    @close-session="closeSession" @view-history="showHistoryPanel"
                    @view-user-profile="showProfilePanel" @view-message-details="showMessageDetailsPanel"
                    @transfer-agent-success="onTransferAgentSuccess"
                    @assign-self-success="onAssignSelfSuccess" @messages-read="onMessagesRead"
                    @open-slack-settings="onOpenSlackSettings"
                    @spam-success="onSpamFromThread"
                    @view-tickets="showTicketsPanel"
                    @restore-success="onRestoreFromThread" />
            </div>
        </div>

        <!-- RIGHT: conversation profile panel (Conversation-style) -->
        <div v-if="panelSession && sidePanelVisible" class="absolute inset-0 z-20 bg-black/20 xl:hidden"
            @click="closeSidePanel" />

        <ChatConversationSidePanel v-if="panelSession && sidePanelVisible"
            :session="panelSession" :initial-tab="sidePanelTab" @close="closeSidePanel" @priority-updated="onPriorityUpdated" @agent-assigned="onAgentAssigned"
            @synced="onSessionSynced" @customer-synced="onCustomerSynced" @unlinked="onSessionUnlinked" />

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
                            <FontAwesomeIcon :icon="faTrashArrowUp" class="text-[10px]" fixed-width /> {{ ctrans("Restore") }}
                        </button>
                    </template>

                    <template v-else>
                        <div class="relative group/prio">
                            <button type="button"
                                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100">
                                <FontAwesomeIcon :icon="priorityMeta(menuContact.priority).icon" class="text-[11px] w-3.5" :style="{ color: priorityMeta(menuContact.priority).color }" fixed-width />
                                <span>{{ ctrans("Priority") }}</span>
                                <span class="ml-auto flex items-center gap-1 text-[10px] text-gray-500">
                                    {{ ctrans(priorityMeta(menuContact.priority).label) }}
                                    <FontAwesomeIcon :icon="faChevronRight" class="text-[8px] text-gray-400" fixed-width />
                                </span>
                            </button>
                            <div class="absolute left-full top-0 hidden group-hover/prio:block w-40 bg-white border border-gray-200 rounded-md shadow-lg py-1">
                                <button v-for="p in PRIORITIES" :key="p.value" type="button"
                                    class="w-full flex items-center gap-2 px-3 py-1.5 text-xs hover:bg-gray-100"
                                    :class="menuContact.priority === p.value ? 'font-semibold text-gray-900' : 'text-gray-700'"
                                    @click="setPriority(menuContact, p.value)">
                                    <FontAwesomeIcon :icon="p.icon" class="text-[11px] w-3.5" :style="{ color: p.color }" fixed-width />
                                    <span>{{ ctrans(p.label) }}</span>
                                    <span v-if="menuContact.priority === p.value" class="ml-auto text-[11px]" :style="{ color: p.color }">✓</span>
                                </button>
                            </div>
                        </div>
                        <div class="border-t border-gray-100 my-1"></div>

                        <button type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="toggleHighlight(menuContact)">
                            <FontAwesomeIcon :icon="faStar" class="text-[10px]"
                                :class="menuContact.is_highlighted ? 'text-amber-400' : ''" fixed-width />
                            {{ menuContact.is_highlighted ? ctrans("Remove highlight") : ctrans("Highlight") }}
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>

                        <!-- Email and website only: WhatsApp has no imported backlog. The reason
                             is picked rather than written, so the noise can be counted later. -->
                        <template v-if="menuContact.channel !== 'whatsapp' && !menuContact.is_rubbish">
                            <div class="px-3 pt-1.5 pb-1 text-[10px] uppercase tracking-wide text-gray-400">
                                {{ ctrans("Ignore as") }}
                            </div>
                            <button v-for="reason in (ignoreReasons ?? [])" :key="reason.value" type="button"
                                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                                @click="markRubbish(menuContact, true, reason.value)">
                                <FontAwesomeIcon :icon="faArchive" class="text-[10px] text-gray-400" fixed-width />
                                {{ reason.label }}
                            </button>
                        </template>
                        <button v-else-if="menuContact.channel !== 'whatsapp'" type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="markRubbish(menuContact, false)">
                            <FontAwesomeIcon :icon="faRotateLeft" class="text-[10px]" fixed-width /> {{ ctrans("Not ignored") }}
                        </button>

                        <button v-if="!menuContact.is_spam" type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="markSpam(menuContact, true)">
                            <FontAwesomeIcon :icon="faBan" class="text-[10px]" fixed-width /> {{ ctrans("Report spam") }}
                        </button>
                        <button v-else type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="markSpam(menuContact, false)">
                            <FontAwesomeIcon :icon="faRotateLeft" class="text-[10px]" fixed-width /> {{ ctrans("Not spam") }}
                        </button>
                        <button type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-red-600 hover:bg-red-50"
                            @click="trashChat(menuContact)">
                            <FontAwesomeIcon :icon="faTrash" class="text-[10px]" fixed-width /> {{ ctrans("Move to trash") }}
                        </button>
                    </template>
                </div>
            </template>
        </Teleport>
    </div>
</template>
