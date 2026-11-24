<script setup lang="ts">
import { ref, reactive, computed, watchEffect, inject, provide, onMounted, onUnmounted, watch, nextTick, defineAsyncComponent } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import { useDebounceFn, useEventListener, useLocalStorage, useNow, watchDebounced } from "@vueuse/core"
import axios, { type AxiosResponse } from "axios"
import { ctrans } from "@/Composables/useTrans"
import { chatAutoTranslate, loadChatTranslation, saveChatTranslation } from "@/Composables/useChatTranslation"
import { myShopIds } from "@/Composables/chatMyShops"
import { chatSendErrorText } from "@/Composables/chatSendError"
import { capitalize } from "@/Composables/capitalize"
import InputText from "primevue/inputtext"
import Button from "@/Components/Elements/Buttons/Button.vue"
import AutoComplete from "primevue/autocomplete"
import { followPointer } from "@/Composables/followPointer"
import { cleanEmailText } from "@/Composables/cleanEmailText"
import { stripPictureTokens } from "@/Composables/useWhatsappMarkup"
import { readComposerDraft } from "@/Composables/useComposerDraft"
import type { MarketingMailshot } from "@/Components/Chat/MarketingFolder.vue"
import NewWhatsappChatDialog from "@/Components/Chat/NewWhatsappChatDialog.vue"
import NewEmailChatDialog from "@/Components/Chat/NewEmailChatDialog.vue"
import ChatPreviewModal from "@/Components/Chat/Agent/ChatPreviewModal.vue"
import ChatFilesDialog from "@/Components/Chat/ChatFilesDialog.vue"
import ChatFilesHoverCard from "@/Components/Chat/ChatFilesHoverCard.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ChatInboxRow from "@/Components/Chat/ChatInboxRow.vue"
import { onChatDraftingWhisper } from "@/Composables/useChatDrafting"
import Dialog from "primevue/dialog"
import Checkbox from "primevue/checkbox"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faSearch, faTimes, faArrowUp } from "@far"
import { faCog, faLanguage, faStar, faAngleRight, faAngleDown, faFilter, faStoreAlt, faGlobe, faPlus, faEnvelope, faArchive, faPhone, faBell, faUser, faTruck, faUsers, faBullhorn, faInbox, faEnvelopeOpen, faReply, faList, faComments, faMoon, faSortAmountDown, faSortAmountDownAlt } from "@fal"
import { faBan, faRotateLeft, faTrash, faCheck, faPen, faTrashArrowUp, faAnglesUp, faAngleUp, faEquals, faChevronRight, faChevronLeft, faStar as faStarSolid } from "@fortawesome/free-solid-svg-icons"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { formatChatTime, formatChatAge } from "@/Composables/chatTime"
import { useChatPhoneCall } from "@/Composables/useChatPhoneCall"
import { useInboxTimings } from "@/Composables/useInboxTimings"
import { useActiveTime } from "@/Composables/useActiveTime"
import { listKey, rememberFirstList, takePrefetchedList } from "@/Composables/inboxListPrefetch"

import {
    Contact,
    SessionAPI,
    ChatMessage,
} from "@/types/Chat/chat"

// Loaded beside the inbox, not before it: the list paints first and the thread, side panel and
// dialogs follow (INI-145). The thread and side panel are fetched as soon as the list is up. A
// download that fails is tried three times, or a blip would leave the pane empty all day.
const lazy = (loader: () => Promise<any>) => defineAsyncComponent({
    loader,
    onError(_error, retry, fail, attempts) {
        if (attempts >= 3) {
            fail()

            return
        }

        setTimeout(retry, attempts * 1000)
    },
})
const loadMessageArea = () => import("@/Components/Chat/Agent/MessageAreaAgent.vue")
const loadWhatsappMessageArea = () => import("@/Components/Chat/Agent/WhatsappMessageAreaAgent.vue")
const loadSidePanel = () => import("@/Components/Chat/ChatConversationSidePanel.vue")
const MessageAreaAgent = lazy(loadMessageArea)
const WhatsappMessageAreaAgent = lazy(loadWhatsappMessageArea)
const ChatConversationSidePanel = lazy(loadSidePanel)
const SettingChat = lazy(() => import("@/Components/Chat/SettingChat.vue"))
const MarketingFolder = lazy(() => import("@/Components/Chat/MarketingFolder.vue"))
const PhoneCallList = lazy(() => import("@/Components/Chat/PhoneCallList.vue"))
const warm = (load: () => Promise<unknown>) => load().catch(() => {})

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
        can_create_template?: boolean
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
const isEmbedded = inject("isEmbedded", false)
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

// The capsules count the squares that are on, on every shop that is on: the same pairs the
// list is filtered by, so a capsule never promises a chat the list cannot show.
const countedTallies = computed(() => {
    const inboxes = selectedShopIds.value.length > 1
        ? selectedShopIds.value.map((shopId) => props.inboxes?.find((i) => i.id === shopId))
        : [selectedInbox.value]

    return inboxes.flatMap((inbox) =>
        selectedCells.value.map((cell) => {
            const [channelKey, kind] = cell.split(":") as [string, ChatKind]

            return inbox?.channels?.find((c) => c.key === channelKey && c.available !== false)?.[kind]
        })
    )
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

const dsKinds: { key: "" | "integration" | "documents"; label: string }[] = [
    { key: "", label: ctrans("All") },
    { key: "integration", label: ctrans("Integration") },
    { key: "documents", label: ctrans("Documents") },
]

const dsKind = ref<"" | "integration" | "documents">("")

const setDsKind = (kind: "" | "integration" | "documents") => {
    dsKind.value = kind
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

// Nobody has taken these, past the time agreed for their channel. Not limited to the shops this
// agent works, and no my/team of its own; like every folder it reads the shops picked above.
const unclaimedView = ref(false)
const unclaimedCount = ref(0)
const carrierView = ref(false)
const carriersCount = ref(0)
const colleagueView = ref(false)
const colleaguesCount = ref(0)
// What the shops picked above sent their customers lately, so whoever answers has read the
// offer the customer is asking about. Read state is per browser: nobody else's business.
const marketingView = ref(false)
const phoneView = ref(false)
const marketingMailshots = ref<MarketingMailshot[]>([])
const isLoadingMarketing = ref(false)
const marketingReadIds = useLocalStorage<number[]>("chat.marketing.read", [])
const marketingUnreadCount = computed(() => marketingMailshots.value.filter((m) => !marketingReadIds.value.includes(m.id)).length)
// Told while we were closed that we would answer when we open: every one still unanswered in
// the shops picked, whoever holds it, so the morning starts with them and the number on the
// capsule is the list.
const promisedOnly = ref(false)
const promisedCount = ref(0)
const spamCount = ref(0)

// The list header already names the shop; only the views that span shops need it repeated
// on the conversation.
const crossShopView = computed(() => (marketingView.value || trashView.value || rubbishView.value || spamView.value || highlightView.value || carrierView.value || colleagueView.value || unclaimedView.value)
    && selectedShopIds.value.length !== 1)

// Folders read the shops picked above: somebody covering many shops clears their own backlog,
// not everybody's.
const folderScope = computed(() =>
    selectedShopIds.value.length === 0
        ? ctrans("Every shop")
        : selectedShopIds.value.length === 1
            ? (selectedInbox.value?.name ?? "")
            : ctrans(":count shops", { count: String(selectedShopIds.value.length) })
)

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

type SearchSuggestion = { type: "customer" | "order"; label: string; detail: string | null; value: string }

const searchSuggestions = ref<SearchSuggestion[]>([])

const loadSearchSuggestions = async (event: { query: string }) => {
    const query = event.query.trim()

    if (query.length < 2 || !selectedShopIds.value.length) {
        searchSuggestions.value = []
        return
    }

    try {
        const { data } = await axios.get(route("grp.org.chat.agents.search_suggestions", [props.organisation.slug]), {
            params: { q: query, shop_ids: selectedShopIds.value },
        })
        searchSuggestions.value = data?.data ?? []
    } catch {
        searchSuggestions.value = []
    }
}

const onSearchInput = (value: unknown) => {
    if (typeof value === "string") {
        searchQuery.value = value
    }
}
const showSearch = ref(false)
const currentPage = ref(1)
const hasMore = ref(false)
const isLoadingMore = ref(false)
const isAssigning = ref<Record<string, boolean>>({})
const errorPerContact = ref<Record<string, string>>({})

const selectedSession = ref<SessionAPI | null>(null)
const messages = ref<ChatMessage[]>([])
loadChatTranslation(layout.user)
const toggleAutoTranslate = () => {
    chatAutoTranslate.value = !chatAutoTranslate.value
    saveChatTranslation()
}
const TRANSLATE_HINT_KEY = "chat-auto-translate-hint-seen"
const showTranslateHint = ref((() => {
    try {
        return localStorage.getItem(TRANSLATE_HINT_KEY) !== "1"
    } catch {
        return false
    }
})())
const closeTranslateHint = () => {
    showTranslateHint.value = false
    try {
        localStorage.setItem(TRANSLATE_HINT_KEY, "1")
    } catch {
        return
    }
}
const openMyLanguages = () => {
    closeTranslateHint()
    openChatSettings()
}

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
        shop_id: s.shop?.id ?? null,
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

const railRowClass = (on: boolean) => railCollapsed.value
    ? ["relative flex h-9 w-11 items-center justify-center rounded-lg text-sm transition-colors", on ? "bg-[var(--app-accent-soft)] text-gray-900 shadow-[inset_3px_0_0_var(--app-accent)]" : "text-gray-600 hover:bg-gray-200"]
    : ["h-8 w-[calc(100%-0.5rem)] gap-3 rounded-r-full pl-5 pr-3", on ? "bg-[var(--app-accent-soft)] font-semibold text-gray-900" : "text-gray-700 hover:bg-gray-100"]

const RAIL_BADGE = "absolute -top-1 -right-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[9px] font-semibold leading-none tabular-nums text-white ring-2 ring-gray-50"

const sidePanelVisible = ref(false)
const sidePanelPreferred = useLocalStorage(`chat-inbox-side-panel:${layout.user?.id ?? "anonymous"}`, true)
const stackedPanelHeight = useLocalStorage(`chat-pane-details-height:${layout.user?.id ?? "anonymous"}`, 50)
const conversationColumn = ref<HTMLElement | null>(null)
const replyActiveTime = useActiveTime(conversationColumn)
watch(() => selectedSession.value?.ulid, () => replyActiveTime.reset())

const startStackedPanelResize = (event: PointerEvent) => {
    const column = conversationColumn.value?.getBoundingClientRect()

    if (!column) {
        return
    }

    followPointer(event, (move) => {
        stackedPanelHeight.value = Math.round(Math.min(80, Math.max(20, ((column.bottom - move.clientY) / column.height) * 100)))
    })
}

watch(() => [panelSession.value?.ulid, panelSession.value?.is_guest, !!panelSession.value?.customer_suggestion?.customer], ([ulid, isGuest, hasSuggestion]) => {
    if (ulid && (isEmbedded || !isGuest || hasSuggestion)) sidePanelVisible.value = sidePanelPreferred.value
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

const formatPromiseTime = (iso: string) => {
    const d = new Date(iso)
    const time = d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })

    return d.toDateString() === new Date().toDateString() ? time : `${d.toLocaleDateString([], { weekday: "short" })} ${time}`
}

const mapSession = (s: SessionAPI): Contact => ({
    id: s.id,
    ulid: s.ulid,
    // Merged views tag every row; single-channel views inherit the selected channel.
    channel: (s as any).channel ?? (selectedChannel.value === "whatsapp" ? "whatsapp" : "website"),
    name: s.contact_name || s.guest_identifier || "",
    avatar: s.image ?? "",
    lastMessage: cleanEmailText(stripPictureTokens(s.last_message?.message)),
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
    can_move_to_couriers: (s as any).can_move_to_couriers,
    can_forward_by_email: (s as any).can_forward_by_email,
    can_move_to_shop: (s as any).can_move_to_shop,
    open_tickets_count: Number((s as any).open_tickets_count ?? 0),
    blocking_tickets_count: Number((s as any).blocking_tickets_count ?? 0),
    open_tasks: (s as any).open_tasks ?? [],
    noise: (s as any).noise ?? null,
    claim: (s as any).claim ?? null,
    promise: (s as any).promise ?? null,
    urgent: (s as any).urgent ?? null,
    assistant_hand_over: (s as any).assistant_hand_over ?? null,
    waiting_until: (s as any).waiting_until ?? null,
    customer_suggestion: (s as any).customer_suggestion ?? null,
    customer_signals: (s as any).customer_signals ?? [],
    is_highlighted: (s as any).is_highlighted ?? false,
    has_attachments: s.has_attachments ?? false,
    labels: (s as any).labels ?? [],
    reply_draft: (s as any).reply_draft ?? null,
    webUser: s.web_user ?? (s as any).customer,
    country_code: (s as any).country_code ?? null,
    priority: s.priority,
    guest_profile: s.guest_profile,
    metadata: (s as any).metadata ?? null,
    phone_number: (s as any).phone_number ?? null,
    agent: s.assigned_agent,
    shop: s.shop,
    language: (s as any).customer_language?.code ?? null,
    is_unanswered: (s as any).is_unanswered ?? false,
    organisation: s.organisation,
    ai_summary: s.ai_summary ?? null,
})

// Whoever oversees can hold several shops at once; an agent's rail is still one shop, which is
// what a list of one reads as. Everything that needs a single shop — the squares, a new
// WhatsApp chat, the team badge — asks for `selectedShopId` and stands down when several are on.
const myShops = computed(() => myShopIds((props.inboxes ?? []).map((inbox) => inbox.id), layout?.user?.chat_alert_shops))
const selectedShopIds = ref<number[]>([...myShops.value])
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

const canLogPhoneCalls = computed(() => (usePage().props as any)?.auth?.user?.is_agent === true)

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
const CHAT_CHANNELS = ["website", "whatsapp"]

const chatChannelsOn = (inboxes: Array<{ channels?: Array<{ key: string; available?: boolean }> }>) =>
    CHAT_CHANNELS.filter((key) => inboxes.some((inbox) => liveChannels(inbox).some((channel) => channel.key === key)))

const startingChannels = () => {
    const chatChannels = chatChannelsOn(props.inboxes ?? [])

    if (chatChannels.length) {
        return chatChannels
    }

    const firstChannelKey = liveChannels(props.inboxes?.[0])[0]?.key

    return firstChannelKey ? [firstChannelKey] : []
}

const selectedCells = ref<string[]>(startingChannels().flatMap((key) => [`${key}:customer`, `${key}:guest`]))

const selectedChannels = computed(() => [...new Set(selectedCells.value.map((c) => c.split(":")[0]))])

const isMailCell = (cell: string) => cell.startsWith("email:")

const withoutMixedChannels = (cells: string[]) =>
    cells.some(isMailCell) && cells.some((cell) => !isMailCell(cell)) ? cells.filter((cell) => !isMailCell(cell)) : cells

const withEveryChatChannel = (cells: string[]) => {
    if (!cells.length || cells.some(isMailCell)) {
        return cells
    }

    const kinds = [...new Set(cells.map((cell) => cell.split(":")[1]))]

    return chatChannelsOn(props.inboxes ?? []).flatMap((key) => kinds.map((kind) => `${key}:${kind}`))
}

// An agent works the same squares every day, so the choice outlives the page. It is kept per
// browser rather than on the account: it is how somebody is working right now, not a setting,
// and a blocked or cleared store must never stop the inbox loading. The v2 key started everybody
// once on My shops (HELP-4362), as the choices kept until then were nearly all a single shop.
const SELECTION_KEY = `chat-inbox-selection-v2:${layout.user?.id ?? "anonymous"}`

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
            mailTab: mailTab.value,
        }))
    } catch {
        // A private window, or storage turned off. Losing the choice is not worth an error.
    }

    if (!isEmbedded && showsOnlySavedSelection()) {
        rememberFirstList(sessionsUrl.value, buildParams(1), route(props.supervisor ? "grp.org.chat.supervision" : "grp.org.chat.inbox", [props.organisation.slug]))
    }
}

const showsOnlySavedSelection = () =>
    !isFolderView.value && inboxView.value === "all" && viewMode.value === "my" && !searchQuery.value.trim()
    && !selectedAgentIds.value.length && !dsKind.value && !promisedOnly.value && !starredOnly.value && !fromAssistantOnly.value
    && sortOrder.value === null && labelFilterId.value === null

/**
 * Only what still exists is restored: shops come and go with the positions somebody holds, and
 * a channel disappears from a shop that stops receiving on it.
 */
const restoreSelection = (): boolean => {
    const stored = readStoredSelection()
    const storedIds: number[] = stored?.shopIds ?? (stored?.shopId ? [stored.shopId] : [])
    const inboxes = (props.inboxes ?? []).filter((i) => storedIds.includes(i.id))

    if (!inboxes.length) {
        return false
    }

    const available = new Set(
        inboxes.flatMap((inbox) => liveChannels(inbox).flatMap((c) => [`${c.key}:customer`, `${c.key}:guest`]))
    )
    const cells = (stored.cells ?? []).filter((c: string) => available.has(c))

    if (!cells.length) {
        return false
    }

    selectedShopIds.value = inboxes.map((inbox) => inbox.id)
    selectedCells.value = withEveryChatChannel(withoutMixedChannels(cells))

    const statuses = (stored.statuses ?? []).filter((x: string) => ["waiting", "active", "closed"].includes(x))

    if (statuses.length) {
        selectedStatuses.value = statuses as ChatStatus[]
    }

    if (closedPeriods.some((p) => p.key === stored.closedPeriod)) {
        closedPeriod.value = stored.closedPeriod
    }

    if (MAIL_TABS.some((tab) => tab.key === stored.mailTab)) {
        mailTab.value = stored.mailTab
    }

    return true
}

// One channel selected still means "this is a WhatsApp list" to everything downstream. With
// several on there is no single answer, so it goes null and each row is read from its own
// channel instead.
const selectedChannel = computed(() => (selectedChannels.value.length === 1 ? selectedChannels.value[0] : null))

const isChannelOn = (key: string) => selectedChannels.value.includes(key)

type ChatKind = "customer" | "guest"

const cellKey = (channelKey: string, kind: ChatKind) => `${channelKey}:${kind}`

const wholeChannel = (channelKey: string) => withEveryChatChannel([cellKey(channelKey, "customer"), cellKey(channelKey, "guest")])

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

const isMultiChannel = computed(() => selectedChannels.value.length > 1)

const promisedCapsuleShown = computed(() =>
    !spamView.value && !trashView.value && !unclaimedView.value && !rubbishView.value && !highlightView.value
    && !carrierView.value && !colleagueView.value && !agentView.value
)

const isPromisedList = computed(() => promisedOnly.value && promisedCapsuleShown.value)

type MailTab = "unanswered" | "waiting_on_customer" | "closed" | "all"

const MAIL_TABS: Array<{ key: MailTab; label: string }> = [
    { key: "unanswered", label: ctrans("Unanswered") },
    { key: "waiting_on_customer", label: ctrans("Waiting on customer") },
    { key: "closed", label: ctrans("Closed") },
    { key: "all", label: ctrans("All mail") },
]

const mailTab = ref<MailTab>("unanswered")

type InboxView = "all" | "unread" | "replied" | "offline" | "label"

const inboxView = ref<InboxView>("all")

type ChatLabel = { id: number; name: string; colour: string }

const chatLabels = ref<ChatLabel[]>([])
const labelFilterId = ref<number | null>(null)
const labelFilter = computed(() => chatLabels.value.find((label) => label.id === labelFilterId.value) ?? null)

const activeInboxView = computed<InboxView>(() =>
    spamView.value || rubbishView.value || trashView.value || carrierView.value || colleagueView.value
    || highlightView.value || unclaimedView.value || marketingView.value || agentView.value || isPromisedList.value
        ? "all"
        : inboxView.value
)

const isMailLayout = computed(() =>
    selectedChannel.value === "email" && !marketingView.value && !agentView.value && !isPromisedList.value
)

const isEmailMailbox = computed(() =>
    activeInboxView.value === "all" && selectedChannel.value === "email" && !isPromisedList.value && !marketingView.value
    && !spamView.value && !rubbishView.value && !trashView.value && !highlightView.value
    && !carrierView.value && !colleagueView.value && !unclaimedView.value && !agentView.value
)

const mailTabStatuses = (tab: MailTab): ChatStatus[] => {
    const statuses: ChatStatus[] = tab === "all" ? ["waiting", "active", "closed"] : tab === "closed" ? ["closed"] : ["waiting", "active"]

    return viewMode.value === "team" && listIsMine.value ? statuses.filter((s) => s !== "waiting") : statuses
}

const searchesEverything = computed(() =>
    !!searchQuery.value.trim() && !isPromisedList.value && !spamView.value && !rubbishView.value && !trashView.value
)

const listStatuses = computed<ChatStatus[]>(() => {
    if (searchesEverything.value) {
        return ["waiting", "active", "closed"]
    }

    if (activeInboxView.value === "label") {
        return ["waiting", "active", "closed"]
    }

    if (activeInboxView.value !== "all") {
        return ["waiting", "active"]
    }

    return isEmailMailbox.value ? mailTabStatuses(mailTab.value) : selectedStatuses.value
})

const COUNTED_MAIL_TABS: MailTab[] = ["unanswered", "waiting_on_customer"]

const mailTabCounts = ref<Partial<Record<MailTab, number>>>({})
const mailTabCountsKey = ref("")
const mailTabCountsStale = computed(() => mailTabCountsKey.value !== currentListKey.value)
let mailTabCountsRequest = 0

// Every badge is the total of the list it opens. The badges asked for in the same tick go to
// the server as one request, each counted by its own list's action.
type SessionCountQuery = Record<string, unknown> & { source: "sessions" | "all" }
let queuedSessionCounts: { queries: Record<string, SessionCountQuery>, totals: Promise<Record<string, number>> } | null = null

const countSessions = (queries: Record<string, SessionCountQuery>): Promise<Record<string, number>> => {
    if (!queuedSessionCounts) {
        const batch: { queries: Record<string, SessionCountQuery>, totals: Promise<Record<string, number>> } = {
            queries: {},
            totals: Promise.resolve().then(() => {
                queuedSessionCounts = null

                return axios.get(`${baseUrl}/app/api/chats/sessions/counts`, { params: { counts: batch.queries } })
                    .then((res) => (res.data?.data ?? {}) as Record<string, number>)
                    .catch(() => ({}))
            }),
        }
        queuedSessionCounts = batch
    }
    Object.assign(queuedSessionCounts.queries, queries)

    return queuedSessionCounts.totals
}

const reloadMailTabCounts = async () => {
    const request = ++mailTabCountsRequest
    const key = currentListKey.value

    if (!isEmailMailbox.value) {
        mailTabCounts.value = {}
        mailTabCountsKey.value = key
        return
    }

    const { order, closed_period, ...params } = buildParams(1) as Record<string, unknown>
    const source = sessionsUrl.value.endsWith("/all/sessions") ? "all" : "sessions"
    const totals = await countSessions(Object.fromEntries(COUNTED_MAIL_TABS.map((tab) =>
        [`mail_${tab}`, { ...params, source, statuses: mailTabStatuses(tab), reply_state: tab }]
    )))

    if (request === mailTabCountsRequest) {
        mailTabCounts.value = Object.fromEntries(COUNTED_MAIL_TABS.map((tab) => [tab, totals[`mail_${tab}`]]))
        mailTabCountsKey.value = key
    }
}

const mailTabMatches = (c: Contact) => {
    if (!isEmailMailbox.value || mailTab.value === "all" || searchesEverything.value) {
        return true
    }

    if (mailTab.value === "closed" || c.status === "closed") {
        return mailTab.value === "closed" && c.status === "closed"
    }

    const unanswered = !!c.is_unanswered && !(c as any).waiting_until

    return mailTab.value === "unanswered" ? unanswered : !unanswered
}

const emailSubject = (c: Contact): string | null => c.metadata?.email_subject || null

const ownerInitials = (name?: string | null) =>
    (name ?? "").split(" ").filter(Boolean).slice(0, 2).map((part) => part[0]?.toUpperCase()).join("")

// The capsule only appears while there is a promise to keep, so the row is the usual three for
// most of the day. Kept on screen while it is switched on, or there would be no way back.
watch(promisedCount, (count) => {
    if (!count && promisedOnly.value && !contacts.value.some((c) => c.promise)) {
        promisedOnly.value = false
    }
})

// null keeps the list's own order: queues oldest waiting first, every Mail tab and the bins newest first.
const sortOrder = ref<"newest" | "oldest" | null>(null)
const listSortsNewest = () => {
    const params = buildListParams(1) as Record<string, unknown>

    return params.order === "newest" || !!(params.trashed || params.is_rubbish || params.is_spam)
}
const isNewestFirst = computed(() => (sortOrder.value ? sortOrder.value === "newest" : listSortsNewest()))
const toggleSortOrder = () => {
    const next = isNewestFirst.value ? "oldest" : "newest"
    sortOrder.value = (next === "newest") === listSortsNewest() ? null : next
    onListFiltersChanged()
}
watch(mailTab, () => {
    sortOrder.value = null
})

// Starred narrows the list in front of you; the rail's Highlighted is every starred chat everywhere.
const starredOnly = ref(false)
const toggleStarredOnly = () => {
    starredOnly.value = !starredOnly.value
    onListFiltersChanged()
}

const fromAssistantOnly = ref(false)
const toggleFromAssistantOnly = () => {
    fromAssistantOnly.value = !fromAssistantOnly.value
    onListFiltersChanged()
}

const buildParams = (page: number) => ({
    ...buildListParams(page),
    ...(sortOrder.value ? { order: sortOrder.value } : {}),
    ...(fromAssistantOnly.value ? { from_assistant: 1 } : {}),
    ...(starredOnly.value && !highlightView.value ? { highlighted: 1 } : {}),
})

const buildListParams = (page: number) => isPromisedList.value ? ({
    promised: 1,
    statuses: ["waiting", "active"],
    page,
    ...(selectedShopIds.value.length ? { shop_ids: selectedShopIds.value } : {}),
    ...(selectedCells.value.length ? { pairs: selectedCells.value } : {}),
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
}) : ({
    ...(trashView.value
        ? { trashed: 1 }
        : rubbishView.value
            ? { is_rubbish: 1 }
            : spamView.value
                ? { is_spam: 1 }
                : unclaimedView.value
                    ? { unclaimed: 1 }
                    : highlightView.value
                        ? { highlighted: 1, statuses: listStatuses.value }
                        : carrierView.value
                            ? { carrier: 1, statuses: listStatuses.value }
                            : colleagueView.value
                                ? { colleague: 1, statuses: listStatuses.value }
                                : { statuses: listStatuses.value }),
    ...(activeInboxView.value === "unread" ? { unread: 1 } : {}),
    ...(activeInboxView.value === "offline" ? { offline: 1 } : {}),
    ...(activeInboxView.value === "label" && labelFilterId.value ? { label_id: labelFilterId.value } : {}),
    ...(searchesEverything.value || activeInboxView.value === "label"
        ? { closed_period: "all", order: "newest" }
        : activeInboxView.value === "replied"
            ? { reply_state: "waiting_on_customer" }
        : isEmailMailbox.value
            ? (mailTab.value === "all" || mailTab.value === "closed" ? { closed_period: "all", order: "newest" } : { reply_state: mailTab.value, order: "newest" })
            : (isStatusOn("closed") ? { closed_period: closedPeriod.value } : {})),
    ...(showDsKinds.value && dsKind.value ? { ds_kind: dsKind.value } : {}),
    ...(listIsMine.value && !unclaimedView.value ? { assigned_to_me: myAgentId } : {}),
    ...(selectedAgentIds.value.length ? { agent_ids: selectedAgentIds.value } : {}),
    page,
    ...(selectedShopIds.value.length && !agentView.value
        ? { shop_ids: selectedShopIds.value }
        : {}),
    ...(selectedCells.value.length && !agentView.value ? { pairs: selectedCells.value } : {}),
    ...(viewMode.value === "team" && listIsMine.value && !unclaimedView.value ? { view_team: 1 } : {}),
    ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
})

// Spam, trash and highlight are cross-channel clean-up views, so they read from the
// merged endpoint instead of whichever channel happens to be selected.
const isMergedView = computed(() =>
    isPromisedList.value || spamView.value || rubbishView.value || trashView.value || highlightView.value || carrierView.value || colleagueView.value || unclaimedView.value || agentView.value
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

const listIsLoading = ref(false)

// Four count requests ride on every list load, and the list reloads on every chat event in
// every open tab: the same filters only recount every 30 seconds (INI-145 fan-out).
const COUNTS_REFRESH_MS = 30_000
let countsKey = ""
let countsAt = 0

const reloadCountsIfDue = (key: string) => {
    if (key === countsKey && Date.now() - countsAt < COUNTS_REFRESH_MS) {
        return
    }

    countsKey = key
    countsAt = Date.now()
    reloadMailTabCounts()
    reloadInboxViewCounts()
}
const currentListKey = computed(() => listKey(sessionsUrl.value, buildParams(1)))
let shownListKey = ""
let reloadRequest = 0
let prefetchedFirstList: Promise<AxiosResponse> | null = null
let firstListSource = "list:fetched"

const reloadContacts = async (): Promise<boolean> => {
    const request = ++reloadRequest
    const url = sessionsUrl.value
    const params = buildParams(1)
    const key = listKey(url, params)
    const prefetched = prefetchedFirstList
    prefetchedFirstList = null

    if (key !== shownListKey) {
        listIsLoading.value = true
    }

    currentPage.value = 1
    hasMore.value = false
    try {
        const res = await (prefetched?.catch(() => {
            firstListSource = "list:failed"

            return axios.get(url, { params })
        }) ?? axios.get(url, { params }))

        if (request !== reloadRequest) {
            return true
        }

        currentPage.value = 1
        contacts.value = res.data.data.sessions.map(mapSession)
        hasMore.value = res.data.data.pagination?.has_more ?? false
        shownListKey = key
        listIsLoading.value = false
        reloadCountsIfDue(key)
        await openPendingSession()

        return true
    } catch (e) {
        console.error("Failed to reload contacts:", e)

        return false
    } finally {
        if (request === reloadRequest) {
            listIsLoading.value = false
        }
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
    if (isSpamming.value[c.ulid]) return false
    openMenuUlid.value = null
    isSpamming.value = { ...isSpamming.value, [c.ulid]: true }
    try {
        const routeName = sessionRoute(spam ? "spam" : "not_spam", c)
        await axios.patch(route(routeName, [props.organisation.slug, c.ulid]), {}, { withCredentials: true })
        // It moved to (or out of) the Spam tab — drop it from the current list.
        removeFromList(c.ulid)
        if (selectedSession.value?.ulid === c.ulid) {
            selectedSession.value = null
            messages.value = []
        }
        fetchInboxNotifications()
        return true
    } catch (e: any) {
        errorPerContact.value[c.ulid] = e?.response?.data?.message ?? "Failed to update spam"
        return false
    } finally {
        isSpamming.value = { ...isSpamming.value, [c.ulid]: false }
    }
}

// Same shape as marking spam, without reporting anybody: the sender is never blocked, and the
// conversation keeps its status so taking the mark off puts it back where it was.
const markRubbish = async (c: Contact, rubbish: boolean, reason?: string) => {
    if (isSpamming.value[c.ulid]) return false
    openMenuUlid.value = null
    isSpamming.value = { ...isSpamming.value, [c.ulid]: true }
    try {
        const routeName = sessionRoute(rubbish ? "rubbish" : "not_rubbish", c)
        await axios.patch(
            route(routeName, [props.organisation.slug, c.ulid]),
            reason ? { reason } : {},
            { withCredentials: true }
        )
        removeFromList(c.ulid)
        if (selectedSession.value?.ulid === c.ulid) {
            selectedSession.value = null
            messages.value = []
        }
        fetchInboxNotifications()
        return true
    } catch (e: any) {
        errorPerContact.value[c.ulid] = e?.response?.data?.message ?? "Failed to mark as rubbish"
        return false
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

const folderLabel = computed<string | null>(() =>
    trashView.value ? ctrans("Trash") : rubbishView.value ? ctrans("Ignored") : spamView.value ? ctrans("Spam")
        : unclaimedView.value ? ctrans("Unclaimed") : highlightView.value ? ctrans("Starred") : carrierView.value ? ctrans("Couriers")
        : colleagueView.value ? ctrans("Colleagues") : marketingView.value ? ctrans("Marketing")
        : activeInboxView.value === "unread" ? ctrans("Unread") : activeInboxView.value === "replied" ? ctrans("Replied")
        : activeInboxView.value === "offline" ? ctrans("Offline messages") : activeInboxView.value === "label" ? (labelFilter.value?.name ?? ctrans("Label"))
        : agentView.value ? (pickedAgentName.value ?? null) : null
)
const shopLabel = computed<string | null>(() =>
    crossShopView.value ? null : selectedShopIds.value.length > 1 ? ctrans("Selected shops") : (selectedInbox.value?.name ?? null)
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

    if (!mailTabMatches(contact)) {
        mailTab.value = contact.status === "closed" ? "closed" : contact.is_unanswered && !(contact as any).waiting_until ? "unanswered" : "waiting_on_customer"
    }
}

const matchesCurrentView = (c: Contact) =>
    (!isPromisedList.value || (!!c.promise && ["waiting", "active"].includes(c.status as string))) &&
    (spamView.value || trashView.value || unclaimedView.value ? true : listStatuses.value.includes(c.status as ChatStatus)) &&
    mailTabMatches(c) &&
    (activeInboxView.value !== "unread" || !!c.unread) &&
    (activeInboxView.value !== "replied" || !c.is_unanswered || !!(c as any).waiting_until) &&
    (activeInboxView.value !== "label" || (c.labels ?? []).some((label) => label.id === labelFilterId.value)) &&
    (agentView.value || !selectedShopIds.value.length
        || (c.shop?.id && selectedShopIds.value.includes(c.shop.id))) &&
    (!selectedAgentIds.value.length ||
        (c.agent?.id && selectedAgentIds.value.includes(c.agent.id)))

const filteredContacts = computed(() => {
    const list = contacts.value.filter(matchesCurrentView)
    const linked = linkedContact.value

    const linkedChannelShown = isMergedView.value || !selectedChannels.value.length || isChannelOn(linked?.channel as string)

    if (linked && linkedChannelShown && matchesCurrentView(linked) && !list.some((c) => c.ulid === linked.ulid)) {
        return [linked, ...list]
    }

    return list
})

const selectedInbox = computed(() =>
    props.inboxes?.find((i) => i.id === selectedShopId.value) ?? props.inboxes?.[0] ?? null
)

const showDsKinds = computed(() =>
    selectedShopId.value !== null && selectedInbox.value?.type === "dropshipping"
    && !agentView.value && !spamView.value && !trashView.value && !rubbishView.value && !carrierView.value && !colleagueView.value && !unclaimedView.value
)

// Writing a fresh email needs one shop, its mailbox, and the right to answer on it. The button
// stays in sight whenever some shop could send one, and says what is missing instead of vanishing.
const showNewEmailButton = computed(
    () => !isReadOnly.value && activeApp.value === "email" && (props.inboxes ?? []).some((inbox) => inbox.can_start_email === true)
)
// The shops picked on the rail that can send: with several, the dialog asks which one it goes from.
const newEmailShops = computed(() => (props.inboxes ?? [])
    .filter((inbox) => inbox.can_start_email === true && (!selectedShopIds.value.length || selectedShopIds.value.includes(inbox.id)))
    .map((inbox) => ({ id: inbox.id, name: inbox.name })))

const newEmailBlockedReason = computed<string | null>(() => {
    if (!newEmailShops.value.length) {
        return selectedShopIds.value.length > 1
            ? ctrans("None of the shops picked has a mailbox connected")
            : ctrans("This shop has no mailbox connected")
    }
    if (!carrierView.value && selectedChannel.value !== "email") {
        return ctrans("Select only the Email squares to write a new email")
    }

    return null
})

const newEmailShopId = computed(() => newEmailShops.value.some((shop) => shop.id === selectedShopId.value) ? selectedShopId.value : null)

const hasNewEmailDraft = computed(() => !newEmailVisible.value && newEmailShops.value.some((shop) =>
    ["to", "subject", "message"].some((field) => readComposerDraft(`new-email:${shop.id}:${field}`).trim())))

const inboxRailCollapsed = useLocalStorage(`chat-inbox-rail-collapsed:${layout.user?.id ?? "anonymous"}`, false)

/* Two ways the rail can be folded, kept apart on purpose. The one above is what the user chose,
   and it is remembered; the one below is this page folding it for them on a narrow screen, and
   it lives only as long as the page - otherwise a phone would decide how their desktop opens. */
const railAutoCollapsed = ref(typeof window !== "undefined" && typeof window.matchMedia === "function" && window.matchMedia("(max-width: 767px)").matches)

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

    const footer = document.getElementById("grp_footer")
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

// The rail's round handle sits at the same height as the main menu's, so the two read as one control.
const railToggleTop = ref<string | null>(null)

const alignRailToggle = () => {
    const mainToggle = document.querySelector("#leftSidebar > div[class*='translate-x-1/2']")

    if (!mainToggle || !railElement.value) {
        railToggleTop.value = null

        return
    }

    railToggleTop.value = `${mainToggle.getBoundingClientRect().top - railElement.value.getBoundingClientRect().top}px`
}

onMounted(() => nextTick(alignRailToggle))
useEventListener("resize", alignRailToggle)

// A column of icons gives no hint that there is more above or below, so the edge
// that actually has more gets a shade. Recomputed on scroll and whenever the list's own size
// changes — a shop added, the rail folded, the window resized.
const shopListElement = ref<HTMLElement | null>(null)
const shopListHasMoreAbove = ref(false)
const shopListHasMoreBelow = ref(false)

const updateShopListOverflow = () => {
    const el = shopListElement.value

    if (!el) {
        shopListHasMoreAbove.value = false
        shopListHasMoreBelow.value = false

        return
    }

    shopListHasMoreAbove.value = el.scrollTop > 1
    shopListHasMoreBelow.value = el.scrollTop + el.clientHeight < el.scrollHeight - 1
}

let shopListObserver: ResizeObserver | null = null

onMounted(() => {
    if (shopListElement.value && typeof ResizeObserver !== "undefined") {
        shopListObserver = new ResizeObserver(updateShopListOverflow)
        shopListObserver.observe(shopListElement.value)
    }
    updateShopListOverflow()
})

onUnmounted(() => shopListObserver?.disconnect())

watch(railCollapsed, () => nextTick(updateShopListOverflow))

/* Below 1440px (a phone, a tablet, a 14" laptop) the rail is a third of the screen, so it is opened to pick a shop and
   then wants to be out of the way: four seconds after the last touch it folds itself back.
   Anything the pointer or keyboard does inside it starts the count again, so it never closes
   under somebody mid-scroll. On a desktop there is room for it, and folding a rail the user
   left open would just be the page arguing with them. */
const RAIL_IDLE_MS = 4000

const narrowScreen = typeof window !== "undefined" && typeof window.matchMedia === "function"
    ? window.matchMedia("(max-width: 1439px)")
    : null

const phoneScreen = typeof window !== "undefined" && typeof window.matchMedia === "function"
    ? window.matchMedia("(max-width: 767px)")
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

const SHOP_COLORS = ["#6366f1", "#0ea5e9", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#ec4899", "#14b8a6"]

const initials = (name: string) => {
    const words = (name || "?").trim().split(/\s+/).filter(Boolean)
    if (words.length >= 2) return (words[0][0] + words[1][0]).toUpperCase()
    return (words[0] ?? "?").slice(0, 2).toUpperCase()
}

const shopChipStyle = (inbox: { id: number }, on: boolean) => {
    const color = SHOP_COLORS[(inbox.id ?? 0) % SHOP_COLORS.length]
    return on ? { backgroundColor: color, color: "white" } : { backgroundColor: color + "1A", color }
}

const shopDotStyle = (inbox: { id: number }) => ({ backgroundColor: SHOP_COLORS[(inbox.id ?? 0) % SHOP_COLORS.length] })

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
    selectedCells.value = resolved ? wholeChannel(resolved) : []
}

// A whole channel, both kinds of sender: what a deep link or a shop click asks for.
const selectChannel = (shopId: number, channelKey: string) => {
    spamView.value = false
    rubbishView.value = false
    trashView.value = false
    carrierView.value = false
    colleagueView.value = false
    highlightView.value = false
    unclaimedView.value = false
    marketingView.value = false
    setShop(shopId)
    selectedCells.value = wholeChannel(channelKey)
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

// What was on before switching into a folder view — every shop a supervisor had selected,
// not just one — so unclicking that folder can put it back exactly, rather than falling
// through to a single-shop reset that silently drops the rest of the selection.
let preFolderViewSelection: { shopIds: number[]; cells: string[] } | null = null

let statusesBeforeMailFolder: ChatStatus[] | null = null

const openStatusesForMailFolder = () => {
    statusesBeforeMailFolder ??= [...selectedStatuses.value]
    selectedStatuses.value = ["waiting", "active"]
}

const restoreStatusesAfterMailFolder = () => {
    if (statusesBeforeMailFolder) {
        selectedStatuses.value = statusesBeforeMailFolder
        statusesBeforeMailFolder = null
    }
}

const captureInboxSnapshot = () => {
    if (crossShopView.value) {
        return
    }

    preFolderViewSelection = { shopIds: [...selectedShopIds.value], cells: [...selectedCells.value] }
}

// Clicking an already-selected folder a second time turns it off rather than doing nothing,
// landing back on the ordinary inbox: exactly the shops and channels that were on before,
// or whatever was last looked at, or the agent's own shops if nothing was stored.
const backToMainInbox = () => {
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()

    if (preFolderViewSelection) {
        selectedShopIds.value = preFolderViewSelection.shopIds
        selectedCells.value = preFolderViewSelection.cells
        preFolderViewSelection = null
        reloadContacts()

        return
    }

    selectedCells.value = []

    if (restoreSelection()) {
        reloadContacts()

        return
    }

    const firstChannel = liveChannels(props.inboxes?.find((inbox) => inbox.id === myShops.value[0]))[0]?.key
    selectedCells.value = firstChannel ? wholeChannel(firstChannel) : []
    openMyShops()
}

// Rubbish is the takeover backlog: an out of office, a circular, a newsletter. Not spam, so
// nobody is blocked, and the conversation keeps its status so unmarking restores it exactly.
const selectRubbish = () => {
    if (rubbishView.value) {
        rubbishView.value = false
        backToMainInbox()

        return
    }
    captureInboxSnapshot()
    restoreStatusesAfterMailFolder()
    rubbishView.value = true
    spamView.value = false
    trashView.value = false
    carrierView.value = false
    colleagueView.value = false
    highlightView.value = false
    unclaimedView.value = false
    selectedCells.value = folderCells()
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectSpam = () => {
    if (spamView.value) {
        spamView.value = false
        backToMainInbox()

        return
    }
    captureInboxSnapshot()
    restoreStatusesAfterMailFolder()
    spamView.value = true
    rubbishView.value = false
    trashView.value = false
    carrierView.value = false
    colleagueView.value = false
    highlightView.value = false
    unclaimedView.value = false
    selectedCells.value = folderCells()
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectTrash = () => {
    if (trashView.value) {
        trashView.value = false
        backToMainInbox()

        return
    }
    captureInboxSnapshot()
    restoreStatusesAfterMailFolder()
    trashView.value = true
    rubbishView.value = false
    spamView.value = false
    carrierView.value = false
    colleagueView.value = false
    highlightView.value = false
    unclaimedView.value = false
    selectedCells.value = folderCells()
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

// Taking one out of here is answering it: the row leaves the queue as soon as somebody holds
// it, so the list is reloaded rather than trusted after any action.
const selectUnclaimed = () => {
    if (unclaimedView.value) {
        unclaimedView.value = false
        backToMainInbox()

        return
    }
    captureInboxSnapshot()
    restoreStatusesAfterMailFolder()
    unclaimedView.value = true
    carrierView.value = false
    colleagueView.value = false
    highlightView.value = false
    rubbishView.value = false
    spamView.value = false
    trashView.value = false
    selectedCells.value = folderCells()
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectCarriers = () => {
    if (carrierView.value) {
        carrierView.value = false
        restoreStatusesAfterMailFolder()
        backToMainInbox()

        return
    }
    captureInboxSnapshot()
    carrierView.value = true
    colleagueView.value = false
    highlightView.value = false
    rubbishView.value = false
    spamView.value = false
    trashView.value = false
    unclaimedView.value = false
    selectedCells.value = folderCells("email")
    openStatusesForMailFolder()
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const selectColleagues = () => {
    if (colleagueView.value) {
        colleagueView.value = false
        restoreStatusesAfterMailFolder()
        backToMainInbox()

        return
    }
    captureInboxSnapshot()
    colleagueView.value = true
    carrierView.value = false
    highlightView.value = false
    rubbishView.value = false
    spamView.value = false
    trashView.value = false
    unclaimedView.value = false
    selectedCells.value = folderCells("email")
    openStatusesForMailFolder()
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    reloadContacts()
}

const loadMarketing = async () => {
    isLoadingMarketing.value = true
    try {
        const response = await axios.get(route("grp.org.chat.marketing.index", [props.organisation.slug]), {
            params: { shop_ids: selectedShopIds.value },
        })
        marketingMailshots.value = response.data ?? []
    } finally {
        isLoadingMarketing.value = false
    }
}


const markMarketingRead = (id: number) => {
    if (!marketingReadIds.value.includes(id)) {
        marketingReadIds.value = [...marketingReadIds.value, id]
    }
}

const selectMarketing = () => {
    if (marketingView.value) {
        marketingView.value = false
        backToMainInbox()

        return
    }
    captureInboxSnapshot()
    restoreStatusesAfterMailFolder()
    carrierView.value = false
    colleagueView.value = false
    highlightView.value = false
    rubbishView.value = false
    spamView.value = false
    trashView.value = false
    unclaimedView.value = false
    selectedCells.value = folderCells("email")
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()
    marketingView.value = true
}

// Every other folder, a shop or an agent picked leaves the marketing folder.
watch([spamView, rubbishView, trashView, highlightView, carrierView, colleagueView, unclaimedView, agentView, selectedSession],
(picked) => {
    if (picked.some(Boolean)) {
        marketingView.value = false
    }
})

const selectHighlight = () => {
    if (highlightView.value) {
        highlightView.value = false
        backToMainInbox()

        return
    }
    captureInboxSnapshot()
    restoreStatusesAfterMailFolder()
    highlightView.value = true
    carrierView.value = false
    colleagueView.value = false
    rubbishView.value = false
    spamView.value = false
    trashView.value = false
    selectedCells.value = folderCells()
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
        customer: null,
        is_guest: true,
        contact_name: guestName,
    } as SessionAPI
    const found = contacts.value.find((x) => x.ulid === ulid)
    if (found) {
        found.webUser = null
        found.name = guestName
    }
}

// Another conversation of the same WhatsApp contact was merged into the open one: open it again
// so the thread shows the messages it took in.
const onSessionMerged = async () => {
    const session = selectedSession.value
    if (!session) return
    selectedSession.value = null
    messages.value = []
    await nextTick()
    selectedSession.value = session
    reloadContactsSoon()
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

// Several at once: the Spam and Ignored bins are cleared (trash or put back), every other list is
// worked through (read, unread, close). Close, not Ignore, is the archive: a customer writing
// back reopens a closed email, while a reply to an ignored one stays ignored.
const isBinView = computed(() => spamView.value || rubbishView.value)
const canBulkSelect = computed(() => !trashView.value && !isReadOnly.value)
const bulkSelected = ref<Record<string, boolean>>({})
const isBulkRunning = ref(false)
const bulkError = ref("")

const isBulkSelectable = (c: Contact) => canBulkSelect.value && (spamView.value ? !!c.is_spam : rubbishView.value ? !!c.is_rubbish : true)
const bulkSelectableContacts = computed(() => filteredContacts.value.filter(isBulkSelectable))
const bulkSelectedContacts = computed(() => bulkSelectableContacts.value.filter((c) => bulkSelected.value[c.ulid]))
const allBulkSelected = computed(() => bulkSelectableContacts.value.length > 0 && bulkSelectedContacts.value.length === bulkSelectableContacts.value.length)

const toggleBulkSelected = (c: Contact) => {
    bulkSelected.value = { ...bulkSelected.value, [c.ulid]: !bulkSelected.value[c.ulid] }
}

const toggleAllBulkSelected = () => {
    bulkSelected.value = allBulkSelected.value ? {} : Object.fromEntries(bulkSelectableContacts.value.map((c) => [c.ulid, true]))
}

watch([spamView, rubbishView, selectedShopIds, canBulkSelect, activeInboxView, searchQuery], () => {
    bulkSelected.value = {}
    bulkError.value = ""
})

const runBulk = async (action: (c: Contact) => Promise<boolean>) => {
    if (isBulkRunning.value) return
    isBulkRunning.value = true
    bulkError.value = ""

    const failed: Record<string, boolean> = {}
    for (const c of [...bulkSelectedContacts.value]) {
        if (!(await action(c))) {
            failed[c.ulid] = true
        }
    }

    const failedCount = Object.keys(failed).length
    bulkSelected.value = failed
    bulkError.value = failedCount ? ctrans(":count could not be updated", { count: String(failedCount) }) : ""
    isBulkRunning.value = false
    fetchInboxNotifications()
    reloadContactsSoon()
}

const updateListedContact = (ulid: string, changes: Partial<Contact>) => {
    const found = contacts.value.find((x) => x.ulid === ulid)
    if (found) Object.assign(found, changes)
}

const trashBulkSelected = () => runBulk(async (c) => {
    if (!(await patchSession(c, sessionRoute("trash", c), "delete"))) return false
    removeFromList(c.ulid)
    return true
})

const restoreBulkSelected = () => {
    const fromSpam = spamView.value

    return runBulk((c) => fromSpam ? markSpam(c, false) : markRubbish(c, false))
}

const readBulkSelected = () => runBulk(async (c) => {
    if (!c.unread) return true
    if (!(await patchSession(c, sessionRoute("read", c), "patch"))) return false
    updateListedContact(c.ulid, { unread: 0 })
    return true
})

const unreadBulkSelected = () => runBulk(async (c) => {
    if (!(await patchSession(c, sessionRoute("unread", c), "patch"))) return false
    updateListedContact(c.ulid, { unread: Math.max(Number(c.unread) || 0, 1) })
    if (selectedSession.value?.ulid === c.ulid) {
        selectedSession.value = null
        messages.value = []
    }
    return true
})

const closeBulkSelected = () => runBulk(async (c) => {
    if (c.status === "closed") return true
    if (!(await patchSession(c, sessionRoute("close", c), "patch"))) return false
    updateListedContact(c.ulid, { status: "closed" })
    return true
})

const LABEL_COLOURS = ["#ef4444", "#f97316", "#eab308", "#22c55e", "#14b8a6", "#3b82f6", "#8b5cf6", "#ec4899", "#6b7280"]

const newLabelName = ref("")
const isSavingLabel = ref(false)

const canManageLabels = ref(false)

const loadChatLabels = async () => {
    try {
        const { data } = await axios.get(route("grp.org.chat.agents.labels.index", [props.organisation.slug]))
        chatLabels.value = data?.data ?? []
        canManageLabels.value = !!data?.can_manage
    } catch {
        chatLabels.value = []
    }
}

const createChatLabel = async () => {
    const name = newLabelName.value.trim()

    if (!name || isSavingLabel.value) {
        return
    }

    isSavingLabel.value = true
    labelError.value = null

    try {
        const { data } = await axios.post(route("grp.org.chat.agents.labels.store", [props.organisation.slug]), {
            name,
            colour: LABEL_COLOURS[chatLabels.value.length % LABEL_COLOURS.length],
        })
        chatLabels.value = [...chatLabels.value, data.data].sort((a, b) => a.name.localeCompare(b.name))
        newLabelName.value = ""
    } catch (e: any) {
        labelError.value = e?.response?.data?.message || ctrans("Label not created")
    } finally {
        isSavingLabel.value = false
    }
}

const confirmingDeleteLabelId = ref<number | null>(null)
const labelError = ref<string | null>(null)

const deleteChatLabel = async (label: ChatLabel) => {
    if (confirmingDeleteLabelId.value !== label.id) {
        confirmingDeleteLabelId.value = label.id
        labelError.value = null
        return
    }

    confirmingDeleteLabelId.value = null
    editingLabelId.value = null

    try {
        await axios.delete(route("grp.org.chat.agents.labels.delete", [props.organisation.slug, label.id]))
        chatLabels.value = chatLabels.value.filter((other) => other.id !== label.id)
        contacts.value.forEach((c) => {
            c.labels = (c.labels ?? []).filter((other) => other.id !== label.id)
        })

        if (labelFilterId.value === label.id) {
            openInboxView("all")
        }
    } catch (e: any) {
        labelError.value = e?.response?.data?.message || ctrans("Label not removed")
    }
}

const editingLabelId = ref<number | null>(null)
const editLabelName = ref("")
const editLabelColour = ref("")

const startEditingLabel = (label: ChatLabel) => {
    editingLabelId.value = label.id
    editLabelName.value = label.name
    editLabelColour.value = label.colour
    confirmingDeleteLabelId.value = null
    labelError.value = null
    nextTick(() => document.getElementById("edit-chat-label")?.focus())
}

const saveChatLabel = async (label: ChatLabel) => {
    const name = editLabelName.value.trim()

    if (!name || isSavingLabel.value) {
        return
    }

    isSavingLabel.value = true
    labelError.value = null

    try {
        const { data } = await axios.patch(route("grp.org.chat.agents.labels.update", [props.organisation.slug, label.id]), {
            name,
            colour: editLabelColour.value,
        })
        const saved: ChatLabel = data.data
        chatLabels.value = chatLabels.value.map((other) => (other.id === saved.id ? saved : other)).sort((a, b) => a.name.localeCompare(b.name))
        contacts.value.forEach((c) => {
            c.labels = (c.labels ?? []).map((other) => (other.id === saved.id ? saved : other))
        })
        editingLabelId.value = null
    } catch (e: any) {
        labelError.value = e?.response?.data?.message || ctrans("Label not saved")
    } finally {
        isSavingLabel.value = false
    }
}

const labelChipStyle = (label: ChatLabel) => {
    const [red, green, blue] = [1, 3, 5].map((at) => parseInt(label.colour.slice(at, at + 2), 16))

    return { backgroundColor: label.colour, color: red * 0.299 + green * 0.587 + blue * 0.114 > 160 ? "#111827" : "#ffffff" }
}

const contactHasLabel = (c: Contact, label: ChatLabel) => (c.labels ?? []).some((other) => other.id === label.id)

const toggleContactLabel = async (c: Contact, label: ChatLabel) => {
    openMenuUlid.value = null

    try {
        const { data } = await axios.patch(route("grp.org.chat.agents.sessions.label", [props.organisation.slug, c.ulid, label.id]))
        const found = contacts.value.find((x) => x.ulid === c.ulid)

        if (found) {
            found.labels = data.data.labels
        }

        if (activeInboxView.value === "label" && labelFilterId.value === label.id && !data.data.is_on) {
            removeFromList(c.ulid)
        }
    } catch (e: any) {
        errorPerContact.value[c.ulid] = e?.response?.data?.message ?? ctrans("Action failed")
    }
}

const openLabelView = (labelId: number) => {
    labelFilterId.value = labelId
    openInboxView("label")
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

const markUnread = async (c: Contact) => {
    openMenuUlid.value = null
    if (!(await patchSession(c, sessionRoute("unread", c), "patch"))) return

    if (selectedSession.value?.ulid === c.ulid) {
        selectedSession.value = null
        messages.value = []
    }
    const found = contacts.value.find((x) => x.ulid === c.ulid)
    if (found) found.unread = Math.max(Number(found.unread) || 0, 1)
    reloadContactsSoon()
    fetchInboxNotifications()
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
const pendingSessionChannel = ref<string | null>(null)

const applyChannelFromUrl = () => {
    const params = new URLSearchParams(window.location.search)
    const channel = params.get("channel")

    if (channel === "whatsapp" || channel === "website") {
        selectedCells.value = wholeChannel(channel)
        pendingSessionChannel.value = channel
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
        const url = (pendingSessionChannel.value ?? selectedChannel.value) === "whatsapp"
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

        if (mapped.shop?.id && (mapped.shop.id !== selectedShopId.value || !isChannelOn(mapped.channel as string))) {
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
    await fetchAgentNotifications()
}

const agentCountsKey = ref("")
const agentCountsFor = computed(() => JSON.stringify([selectedShopIds.value, activeApp.value]))
const agentCountsStale = computed(() => !!myAgentId && !isReadOnly.value && agentCountsKey.value !== agentCountsFor.value)

// The shop watcher and the mount ask in the same tick: calls made together share one request,
// sent with the selection as it stands then. A call after an action always sends its own, so it
// never gets numbers counted before the change. The all-shops totals ride on the same request.
let queuedAgentNotifications: Promise<void> | null = null

const fetchAgentNotifications = (): Promise<void> => {
    if (!myAgentId || (isReadOnly.value && allShopIds.value.length < 2)) return Promise.resolve()

    queuedAgentNotifications ??= new Promise<void>((resolve) => setTimeout(resolve, 0)).then(() => {
        queuedAgentNotifications = null

        return loadAgentNotifications(agentCountsFor.value, allShopIds.value.length >= 2)
    })

    return queuedAgentNotifications
}

const loadAgentNotifications = async (key: string, wantsAllShops: boolean) => {
    try {
        const { data } = await axios.get(`${baseUrl}/app/api/chats/users/${myAgentId}/agent-notifications`, {
            params: {
                shop_ids: selectedShopIds.value,
                ...(activeApp.value ? { channel: activeApp.value } : {}),
                ...(wantsAllShops ? { all_shop_ids: allShopIds.value } : {}),
            },
        })
        if (wantsAllShops) {
            totalUnclaimed.value = data?.data?.all_shops?.unclaimed ?? 0
            totalSpam.value = data?.data?.all_shops?.spam ?? 0
        }
        if (!isReadOnly.value) {
            teamUnreadByShop.value = data?.data?.team_unread ?? {}
            unclaimedCount.value = data?.data?.unclaimed ?? 0
            carriersCount.value = data?.data?.carriers ?? 0
            colleaguesCount.value = data?.data?.colleagues ?? 0
            promisedCount.value = data?.data?.promised ?? 0
            spamCount.value = data?.data?.spam ?? 0
        }
    } catch (e) {
        // silent — badges are non-critical
    } finally {
        if (!isReadOnly.value && key === agentCountsFor.value) {
            agentCountsKey.value = key
        }
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

// People work one shop at a time, so the folders follow it; the strip above keeps every shop
// they may see in sight, and a click opens that folder across all of them.
const allShopIds = computed(() => (props.inboxes ?? []).map((inbox) => inbox.id))
const sumOf = (byInbox: Record<number, number>) => Object.values(byInbox).reduce((sum, n) => sum + n, 0)
const totalCustomersWaiting = computed(() => sumOf(inboxUnread.value))
const totalGuestsWaiting = computed(() => sumOf(inboxGuestsWaiting.value))
const totalActive = computed(() => sumOf(inboxActive.value))
const totalUnclaimed = ref(0)
const totalSpam = ref(0)

const openMyShops = () => {
    spamView.value = false
    rubbishView.value = false
    trashView.value = false
    carrierView.value = false
    colleagueView.value = false
    highlightView.value = false
    unclaimedView.value = false
    selectedShopIds.value = [...myShops.value]
    afterSelectionChanged()
}
const MOVED_HINT_KEY = "inbox-apps-moved-hint-seen"
const showMovedHint = ref((() => {
    try {
        return localStorage.getItem(MOVED_HINT_KEY) !== "1"
    } catch {
        return false
    }
})())
const closeMovedHint = () => {
    if (!showMovedHint.value) return
    showMovedHint.value = false
    try {
        localStorage.setItem(MOVED_HINT_KEY, "1")
    } catch {
        return
    }
}

type RailApp = "chat" | "email"

const RAIL_APPS: Array<{ key: RailApp; label: string; icon: any }> = [
    { key: "chat", label: ctrans("Chat"), icon: faComments },
    { key: "email", label: ctrans("Mail"), icon: faEnvelope },
]

const channelBadge = (channel?: string) =>
    channel === "whatsapp"
        ? { icon: faWhatsapp, label: ctrans("WhatsApp"), class: "bg-green-50 text-green-600" }
        : channel === "email"
            ? { icon: faEnvelope, label: ctrans("Email"), class: "bg-blue-50 text-blue-600" }
            : { icon: faGlobe, label: ctrans("Website chat"), class: "bg-sky-50 text-sky-600" }

const channelsOf = (key: string) => (key === "chat" ? CHAT_CHANNELS : [key])

const appOfChannel = (channel?: string): RailApp | null => (!channel ? null : channel === "email" ? "email" : "chat")

type RailMenuSection = "queue" | "labels" | "sorting" | "cleanup" | "phone" | "shops" | "agents"

const railMenuOpen = useLocalStorage<Record<RailMenuSection, boolean>>(`chat-rail-menu:${layout.user?.id ?? "anonymous"}`, {
    queue: true,
    labels: true,
    sorting: true,
    cleanup: true,
    phone: true,
    shops: true,
    agents: true,
})

const isRailMenuOpen = (section: RailMenuSection) => railMenuOpen.value[section] !== false

const toggleRailMenu = (section: RailMenuSection) => {
    railMenuOpen.value = { ...railMenuOpen.value, [section]: !isRailMenuOpen(section) }
}

const phoneShops = computed(() => {
    const picked = (props.inboxes ?? []).filter((inbox) => selectedShopIds.value.includes(inbox.id))

    return picked.length ? picked : (props.inboxes ?? [])
})

const phoneShopIds = computed(() => phoneShops.value.map((inbox) => inbox.id))

const phoneOnNow = computed(() => phoneShops.value.reduce((sum, inbox) => sum + (inbox.phone?.in_progress ?? 0), 0))

type PhoneCallView = "all" | "in_progress" | "today"

const phoneCallView = ref<PhoneCallView>("all")

const phoneCallsToday = computed(() =>
    phoneShops.value.reduce((sum, inbox) => sum + (inbox.phone?.customer ?? 0) + (inbox.phone?.guest ?? 0) + (inbox.phone?.in_progress ?? 0), 0)
)

const menuApp = computed<RailApp | "phone" | null>(() => phoneView.value ? "phone" : activeApp.value)

const openPhoneCallView = (view: PhoneCallView) => {
    phoneCallView.value = view

    if (!phoneView.value) {
        openPhoneView()
    }
}

const openPhoneView = () => {
    leaveFolderViews()
    phoneView.value = true
    selectedSession.value = null
    messages.value = []
    newChatVisible.value = false
    clearAgentFilter()

    if (!selectedShopIds.value.length) {
        selectedShopIds.value = [...myShops.value]
    }
}

const isFolderView = computed(() =>
    phoneView.value || spamView.value || rubbishView.value || trashView.value || carrierView.value || colleagueView.value
    || highlightView.value || unclaimedView.value || marketingView.value || agentView.value
)

const activeApp = computed<RailApp | null>(() =>
    agentView.value || phoneView.value ? null : appOfChannel(selectedChannels.value[0])
)

const MAIL_ONLY_FOLDERS = computed(() => carrierView.value || colleagueView.value || marketingView.value)

const breadcrumbExtras = computed(() => [
    RAIL_APPS.find((app) => app.key === activeApp.value)?.label,
    shopLabel.value,
    folderLabel.value,
    selectedSession.value ? ((selectedSession.value as any).guest_identifier || (selectedSession.value as any).contact_name) : null,
].filter((label): label is string => !!label).map((label) => ({ type: "simple", simple: { label }, inbox_extra: true })))

watchEffect(() => {
    const page = usePage()
    const base = (page.props.breadcrumbs as any[] ?? []).filter((crumb) => !crumb.inbox_extra)
    const next = [...base, ...breadcrumbExtras.value]
    if (JSON.stringify(next) !== JSON.stringify(page.props.breadcrumbs)) {
        page.props.breadcrumbs = next
    }
})
onUnmounted(() => {
    const page = usePage()
    page.props.breadcrumbs = (page.props.breadcrumbs as any[] ?? []).filter((crumb) => !crumb.inbox_extra)
})

const waitingOn = (inbox: { channels?: Array<any> }, app: RailApp | null, kinds: ChatKind[] = ["customer", "guest"]) =>
    liveChannels(inbox)
        .filter((channel: any) => !app || channelsOf(app).includes(channel.key))
        .reduce((sum, channel: any) => sum + kinds.reduce((n, kind) => n + (channel[kind]?.waiting ?? 0), 0), 0)

const appWaiting = (app: RailApp) => (props.inboxes ?? []).reduce((sum, inbox) => sum + waitingOn(inbox, app), 0)

const appIsAvailable = (app: RailApp) => (props.inboxes ?? []).some((inbox) => liveChannels(inbox).some((channel) => channelsOf(app).includes(channel.key)))

const selectedKinds = computed(() => [...new Set(selectedCells.value.map((cell) => cell.split(":")[1] as ChatKind))])

const kindFilter = computed<"all" | ChatKind>(() => selectedKinds.value.length === 1 ? selectedKinds.value[0] : "all")

const KIND_PILLS = [
    { value: "customer", label: ctrans("Customers") },
    { value: "guest", label: ctrans("Guests") },
]

const showKindPills = computed(() => !marketingView.value && !agentView.value && !isPromisedList.value && selectedCells.value.length > 0)

// "All" in the bar is All mail, so only the two queues are pills, each with what is in it.
const mailPills = computed(() => [...COUNTED_MAIL_TABS, "closed" as MailTab].map((tab) => ({
    value: tab,
    label: MAIL_TABS.find((mail) => mail.key === tab)!.label,
    count: mailTabCounts.value[tab] || undefined,
})))

const cellsFor = (apps: string[], kind: "all" | ChatKind) =>
    [...new Set(apps.flatMap((app) => app === "chat" ? chatChannelsOn(props.inboxes ?? []) : [app]))]
        .flatMap((channel) => (kind === "all" ? ["customer", "guest"] as ChatKind[] : [kind]).map((k) => cellKey(channel, k)))

const folderCells = (app?: RailApp) => cellsFor([app ?? activeApp.value ?? "email"], kindFilter.value)

const leaveFolderViews = () => {
    restoreStatusesAfterMailFolder()
    phoneView.value = false
    spamView.value = false
    rubbishView.value = false
    trashView.value = false
    carrierView.value = false
    colleagueView.value = false
    highlightView.value = false
    unclaimedView.value = false
    marketingView.value = false
}

const inboxViewCounts = ref<Record<"unread" | "replied", number | undefined>>({ unread: undefined, replied: undefined })
const inboxViewCountsKey = ref("")
const inboxViewCountsStale = computed(() => inboxViewCountsKey.value !== currentListKey.value)
let inboxViewCountsRequest = 0

const reloadInboxViewCounts = async () => {
    const request = ++inboxViewCountsRequest
    const app = activeApp.value
    const key = currentListKey.value

    if (!app || !selectedShopIds.value.length) {
        inboxViewCounts.value = { unread: undefined, replied: undefined }
        inboxViewCountsKey.value = key
        return
    }

    const params = {
        shop_ids: selectedShopIds.value,
        pairs: cellsFor([app], kindFilter.value),
        statuses: ["waiting", "active"],
        ...(listIsMine.value ? { assigned_to_me: myAgentId } : {}),
        ...(viewMode.value === "team" && listIsMine.value ? { view_team: 1 } : {}),
    }
    const totals = await countSessions({
        view_unread: { ...params, source: "all", unread: 1 },
        view_replied: { ...params, source: "all", reply_state: "waiting_on_customer" },
    })

    if (request === inboxViewCountsRequest) {
        inboxViewCounts.value = { unread: totals.view_unread, replied: totals.view_replied }
        inboxViewCountsKey.value = key
    }
}

const openInboxView = (view: InboxView) => {
    leaveFolderViews()
    inboxView.value = view

    if (!selectedCells.value.length) {
        selectedCells.value = cellsFor([activeApp.value ?? "email"], kindFilter.value)
    }

    afterSelectionChanged()
}

const openApp = (app: RailApp) => {
    const isSwitchingApp = app !== activeApp.value
    const staysInFolder = isFolderView.value && !agentView.value && !phoneView.value && app !== activeApp.value
        && !(MAIL_ONLY_FOLDERS.value && app !== "email")

    if (!staysInFolder) {
        leaveFolderViews()
    }
    selectedCells.value = cellsFor([app], kindFilter.value)

    if (!selectedShopIds.value.length) {
        selectedShopIds.value = [...myShops.value]
    }

    afterSelectionChanged()

    if (isSwitchingApp) {
        resumeLastOpened(app)
    }
}

const setKindFilter = (kind: "all" | ChatKind) => {
    selectedCells.value = cellsFor(selectedChannels.value.length ? selectedChannels.value : ["email"], kind)
    afterSelectionChanged()
}

const pickShop = (shopId: number, event: MouseEvent) => {
    const isOnlyShop = selectedShopIds.value.length === 1 && selectedShopIds.value[0] === shopId

    if (event.ctrlKey || event.metaKey) {
        const on = selectedShopIds.value.includes(shopId)
        if (on && selectedShopIds.value.length === 1) {
            return
        }
        selectedShopIds.value = on ? selectedShopIds.value.filter((id) => id !== shopId) : [...selectedShopIds.value, shopId]
    } else {
        selectedShopIds.value = isOnlyShop && !isFolderView.value ? [...myShops.value] : [shopId]
    }

    const app = activeApp.value ?? appOfChannel(selectedChannels.value[0]) ?? "chat"
    const staysOnPhone = phoneView.value
    leaveFolderViews()
    phoneView.value = staysOnPhone
    selectedCells.value = cellsFor([app], kindFilter.value)
    afterSelectionChanged()
}

watch(activeApp, () => fetchAgentNotifications())

const pickedView = computed(() => JSON.stringify([
    activeApp.value, activeInboxView.value, labelFilterId.value, phoneView.value, phoneCallView.value, spamView.value, rubbishView.value,
    trashView.value, carrierView.value, colleagueView.value, highlightView.value, unclaimedView.value, marketingView.value, agentView.value,
]))

watch(pickedView, () => {
    if (phoneScreen?.matches && !railCollapsed.value) {
        railAutoCollapsed.value = true
    }
})

const toggleShopChecked = (shopId: number) => pickShop(shopId, { ctrlKey: true } as MouseEvent)

const shopCheckboxPt = {
    box: ({ context }: { context: { checked: boolean } }) => ({
        class: context.checked ? "!border-[--app-accent] !bg-[--app-accent]" : "!border-gray-300",
    }),
    icon: { class: "!text-[--app-accent-text]" },
}

const isMyShopsView = computed(() =>
    !agentView.value && !spamView.value && !trashView.value && !unclaimedView.value && !highlightView.value && !carrierView.value && !colleagueView.value
    && selectedShopIds.value.length === myShops.value.length && myShops.value.every((id) => selectedShopIds.value.includes(id))
)

const openAcrossAllShops = (select: () => void, alreadyOpen: boolean) => {
    selectedShopIds.value = [...allShopIds.value]
    if (alreadyOpen) {
        reloadContacts()

        return
    }
    select()
}

const lastOpenedByApp = ref<Partial<Record<RailApp, Contact>>>({})

const emailToResume = computed(() => (activeApp.value === "chat" ? lastOpenedByApp.value.email ?? null : null))

const resumeLastOpened = (app: RailApp) => {
    const last = lastOpenedByApp.value[app]

    if (!last || isFolderView.value) {
        return
    }

    linkedContact.value = last
    openChat(last)
}

const leaveConversation = () => {
    const app = appOfChannel(selectedSession.value?.channel)

    if (app) {
        lastOpenedByApp.value = Object.fromEntries(Object.entries(lastOpenedByApp.value).filter(([key]) => key !== app))
    }

    selectedSession.value = null
}

const openChat = (c: Contact) => {
    if (selectedSession.value?.ulid !== String(c.ulid)) {
        timings.conversationOpening(String(c.ulid), (c.channel as string) ?? null)
    }

    const app = appOfChannel(c.channel)

    if (app) {
        lastOpenedByApp.value = { ...lastOpenedByApp.value, [app]: c }
    }

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
        customer_signals: (c as any).customer_signals ?? [],
        ai_summary: c.ai_summary ?? null,
        is_trashed: trashView.value,
        is_spam: c.is_spam,
        is_rubbish: c.is_rubbish,
        can_dispose: (c as any).can_dispose,
        can_move_to_couriers: (c as any).can_move_to_couriers,
        can_forward_by_email: (c as any).can_forward_by_email,
        can_move_to_shop: (c as any).can_move_to_shop,
        open_tickets_count: (c as any).open_tickets_count ?? 0,
        blocking_tickets_count: (c as any).blocking_tickets_count ?? 0,
        open_tasks: c.open_tasks ?? [],
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

const LONG_PRESS_MS = 500

const previewContact = ref<Contact | null>(null)

// The shop's own language goes without saying; only a customer writing in another one is worth a chip.
const writesInOtherLanguage = (c: Contact) => !!c.language && c.language !== c.shop?.language

const filesUlid = ref("")
const filesVisible = ref(false)
const filesHoverCard = ref<InstanceType<typeof ChatFilesHoverCard> | null>(null)
const openFiles = (c: Contact) => {
    filesHoverCard.value?.hide()
    filesUlid.value = c.ulid
    filesVisible.value = true
}
let longPressTimer: ReturnType<typeof setTimeout> | null = null
let longPressStart: { x: number, y: number } | null = null
let lastPointerType = "mouse"
let swallowNextRowClick = false

const cancelLongPress = () => {
    if (longPressTimer) {
        clearTimeout(longPressTimer)
        longPressTimer = null
    }
    longPressStart = null
}

const startLongPress = (c: Contact, event: PointerEvent) => {
    lastPointerType = event.pointerType
    swallowNextRowClick = false
    cancelLongPress()

    if (event.button !== 0) {
        return
    }

    longPressStart = { x: event.clientX, y: event.clientY }
    longPressTimer = setTimeout(() => {
        longPressTimer = null
        swallowNextRowClick = true
        previewContact.value = c
    }, LONG_PRESS_MS)
}

const moveLongPress = (event: PointerEvent) => {
    if (longPressStart && Math.hypot(event.clientX - longPressStart.x, event.clientY - longPressStart.y) > 10) {
        cancelLongPress()
    }
}

const onRowClick = (c: Contact) => {
    if (swallowNextRowClick) {
        swallowNextRowClick = false

        return
    }

    handleClickContact(c)
}

const onRowContextMenu = (c: Contact, event: MouseEvent) => {
    if (lastPointerType === "touch") {
        return
    }

    toggleRowMenu(c.ulid, event)
}

provide("chatInboxRow", reactive({
    minuteClock: useNow({ interval: 60_000 }),
    isAssigning, isSpamming, errorPerContact, openMenuUlid, previewContact, filesHoverCard,
    mailTab, trashView, onlyClosed, isEmailMailbox, isMailLayout, isReadOnly, selectedShopId, selectedItemStyle,
    onRowClick, startLongPress, moveLongPress, cancelLongPress, onRowContextMenu, toggleHighlight, toggleRowMenu,
    openFiles, markSpam, channelBadge, emailSubject, formatPromiseTime, labelChipStyle, ownerInitials, writesInOtherLanguage,
    isBulkSelectable, bulkSelected, toggleBulkSelected,
}))

const closePreview = () => {
    previewContact.value = null
    swallowNextRowClick = false
}

const openFromPreview = () => {
    const contact = previewContact.value
    previewContact.value = null
    swallowNextRowClick = false

    if (contact) {
        handleClickContact(contact)
    }
}

const handleClickContact = (c: Contact) => {
    errorPerContact.value[c.ulid] = ""
    // Waiting chats open into an "Assign to me" step (no composer) until assigned.
    openChat(c)
}

const stepConversation = (step: number) => {
    const list = filteredContacts.value
    const next = list[list.findIndex((c) => c.ulid === selectedSession.value?.ulid) + step]

    if (next) {
        handleClickContact(next)
    }
}

const contactName = (contact: any) => contact?.guest_identifier || contact?.contact_name || ""
const baseNavigation = usePage().props.navigation
watchEffect(() => {
    if (isEmbedded) return
    const list = filteredContacts.value
    const at = list.findIndex((c) => c.ulid === selectedSession.value?.ulid)
    const page = usePage()
    page.props.navigation = at < 0 ? baseNavigation : {
        previous: at > 0 ? { label: contactName(list[at - 1]), action: () => stepConversation(-1) } : null,
        next: at < list.length - 1 ? { label: contactName(list[at + 1]), action: () => stepConversation(1) } : null,
        position: `${at + 1} / ${list.length}`,
    } as any
})
onUnmounted(() => {
    usePage().props.navigation = baseNavigation
})

const onChatPaneMessage = (event: MessageEvent) => {
    if (event.source === window.parent && typeof event.data?.chatPaneStep === "number") {
        stepConversation(event.data.chatPaneStep)
    }
}

if (isEmbedded) {
    window.addEventListener("message", onChatPaneMessage)
    onUnmounted(() => window.removeEventListener("message", onChatPaneMessage))

    watch([() => selectedSession.value?.ulid, filteredContacts], ([ulid, list]) => {
        window.parent.postMessage({ chatPanePosition: { at: list.findIndex((c) => c.ulid === ulid) + 1, total: list.length } }, window.location.origin)
    }, { immediate: true })
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

/** Giving a conversation back: it waits for anybody again, still open here. */
const onReleased = async () => {
    const prev = selectedSession.value
    await reloadContacts()
    await nextTick()
    selectedSession.value = { ...(prev as SessionAPI), status: "waiting", assigned_agent: null } as SessionAPI
}

const updateUrl = (ulid: string) => {
    // The address follows the conversation that is open, not the sidebar: in a mixed list the
    // two disagree, and a WhatsApp chat deep-linked as a website one does not reopen.
    const page = props.supervisor ? "grp.org.chat.supervision" : "grp.org.chat.inbox"
    const url = activeChannel.value === "whatsapp"
        ? route(props.supervisor ? "grp.org.chat.supervision" : page, [props.organisation.slug]) + `?channel=whatsapp&session=${ulid}`
        : route(`${page}.conversation`, [props.organisation.slug, ulid])

    window.history.replaceState(window.history.state, "", url)
}

const handleSendMessage = async ({ text, files, message_type, is_email_notif, email_cc_excluded, ai_draft_translation_of, content_ids, onFailed }: {
    text: string
    files?: File[]
    message_type: "text" | "image" | "file"
    tempId: number
    is_email_notif: boolean
    email_cc_excluded?: string[]
    ai_draft_translation_of?: number | null
    content_ids?: string[]
    onFailed?: (message: string) => void
}) => {
    if (!selectedSession.value?.ulid) return
    const sessionUlid = selectedSession.value.ulid
    const claimsChat = selectedSession.value.status === "waiting"
    const replyAccepted = timings.replySending((selectedSession.value.channel as string) ?? null)
    try {
        const formData = new FormData()
        formData.append("message_text", text ?? "")
        formData.append("message_type", message_type)
        formData.append("sender_type", "agent")
        formData.append("is_email_notif", String(is_email_notif ?? false))
        const activeTime = replyActiveTime.read()
        formData.append("writing_seconds", String(activeTime.writing_seconds))
        formData.append("typing_seconds", String(activeTime.typing_seconds))
        email_cc_excluded?.forEach((address) => formData.append("email_cc_excluded[]", address))
        if (ai_draft_translation_of) formData.append("ai_draft_translation_of", String(ai_draft_translation_of))

        if (files?.length === 1 && !content_ids?.some(Boolean)) {
            formData.append(message_type === "image" ? "image" : "file", files[0])
        } else {
            files?.forEach((file) => formData.append("attachments[]", file))
            content_ids?.forEach((contentId) => formData.append("attachment_content_ids[]", contentId))
        }

        await axios.post(
            route("grp.org.chat.agents.messages.send", [props.organisation.slug, sessionUlid]),
            formData,
            { headers: { "Content-Type": "multipart/form-data" }, withCredentials: true }
        )
        replyAccepted()
        replyActiveTime.reset()

        if (claimsChat && selectedSession.value?.ulid === sessionUlid) {
            await onAssignSelfSuccess()
        }
    } catch (error) {
        console.error("Error sending message:", error)

        onFailed?.(chatSendErrorText(error, ctrans("The message was not sent. Try again.")))
    }
}

const sidePanelTab = ref<'profile' | 'tickets' | 'history' | 'log'>('profile')

// Asking for a tab is asking for that tab, never for the panel to go away: these used to
// toggle, so pressing View Profile with the panel already open closed it instead.
const openSidePanelOn = (tab: 'profile' | 'tickets' | 'history' | 'log') => {
    sidePanelTab.value = tab
    sidePanelVisible.value = true
    sidePanelPreferred.value = true
}
const showHistoryPanel = () => openSidePanelOn('history')
const showProfilePanel = () => openSidePanelOn('profile')
const showTicketsPanel = () => openSidePanelOn('tickets')
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


const onListFiltersChanged = async () => {
    selectedSession.value = null
    linkedContact.value = null
    messages.value = []
    if (dropWaitingInTeamView()) {
        return
    }
    await reloadContacts()
}

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

// Every chat event, in every open inbox tab, reloaded the list and the badges (INI-145). A burst
// now refreshes once, at most every 2 seconds while it lasts, and a tab in the background
// refreshes when it is looked at again instead of for every event it was not shown.
let isListOwed = false
let areBadgesOwed = false

const refreshOwed = () => {
    if (document.visibilityState === "hidden" || !(isListOwed || areBadgesOwed)) {
        return
    }

    if (isListOwed) {
        reloadContacts()
    }

    if (areBadgesOwed) {
        fetchInboxNotifications()
    }

    isListOwed = false
    areBadgesOwed = false
}

const refreshOwedSoon = useDebounceFn(refreshOwed, 500, { maxWait: 2000 })

const oweRefresh = (includingList: boolean) => {
    isListOwed ||= includingList
    areBadgesOwed = true
    refreshOwedSoon()
}

const onVisibilityChange = () => {
    if (document.visibilityState === "visible") {
        refreshOwed()
    }
}

const onChatListEvent = (e: any) => {
    oweRefresh(true)

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
    // The badges count every channel, the list only when it shows WhatsApp.
    const showsWhatsapp = isMergedView.value || isChannelOn("whatsapp")
    oweRefresh(showsWhatsapp)

    if (!showsWhatsapp) return

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

const timings = useInboxTimings()

// What the address asks for wins over what was last looked at.
if (!props.preselectShopId && !props.selectedSessionUlid) {
    restoreSelection()
}

applyChannelFromUrl()
warm(loadMessageArea)
warm(loadSidePanel)

const urlChannel = selectedChannel.value

// Preselect the shop when opened from the shop-level nav entry.
if (props.preselectShopId && props.inboxes?.some((i) => i.id === props.preselectShopId)) {
    revealInbox(props.preselectShopId, urlChannel)
}

// Registered after the restore so putting the stored choice back does not write it again.
watch([selectedShopIds, selectedCells, selectedStatuses, mailTab], storeSelection, { deep: true })

// Jump to the shop (inbox) the opened chat belongs to.
if (props.initialSession?.shop?.id) {
    revealInbox(props.initialSession.shop.id, (props.initialSession as any).channel ?? urlChannel)
}

const initContact = props.initialSession ? mapSession(props.initialSession) : null

if (initContact) {
    revealViewFor(initContact)
}

// Registered once the restored filters are in place, which the first list load below
// already asks for: registered earlier, putting them back loaded the list a second time.
dropWaitingInTeamView()
watch([selectedStatuses, viewMode, promisedOnly, mailTab], onListFiltersChanged)

if (!isEmbedded) {
    const firstList = takePrefetchedList(sessionsUrl.value, buildParams(1))
    prefetchedFirstList = firstList.response
    firstListSource = `list:${firstList.outcome}${props.initialSession ? ":conversation" : ""}`

    rememberFirstList(sessionsUrl.value, buildParams(1), usePage().url)
}

const firstListLoad = reloadContacts()

onMounted(async () => {
    const inboxOpening = timings.start()

    // Asked once the shops are settled, so the first answer is not for the wrong ones. The rail's
    // counts came with the page, except when Back put the page back as it was left.
    watch(selectedShopIds, () => fetchAgentNotifications(), { deep: true })
    watch(selectedShopIds, loadMarketing, { deep: true })
    fetchAgentNotifications()

    if (!inboxOpening.isVisit) {
        reloadInboxCountsSoon()
    }

    // The thread opens once the list is on screen, so the list is not held back by it. A tab in
    // the background paints nothing, so there it does not wait for a paint.
    const listPainted = (isLoaded: boolean) => document.visibilityState === "hidden"
        ? nextTick()
        : inboxOpening.listPainted(isLoaded, firstListSource)

    const isListLoaded = await firstListLoad

    if (initContact) {
        linkedContact.value = initContact
        await listPainted(isListLoaded)
        openChat(initContact)
    } else {
        await listPainted(isListLoaded)
        openSelectedFromProp()
    }

    // The rail's extras wait for the list rather than compete with it.
    loadChatLabels()
    loadMarketing()
    warm(loadWhatsappMessageArea)
    document.addEventListener("visibilitychange", onVisibilityChange)

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
                .listenForWhisper("chatDrafting", onChatDraftingWhisper)
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
            .stopListeningForWhisper("chatDrafting", onChatDraftingWhisper)
    )
    scrollObserver?.disconnect()
    document.removeEventListener("visibilitychange", onVisibilityChange)
    timings.stop()
})
</script>

<template>
    <Head :title="title" />

    <template v-if="!isEmbedded">
    <div class="mx-4 my-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs tabular-nums text-gray-500">
        <div id="chat-channels" class="flex w-[17rem] shrink-0 items-center self-stretch border-r border-gray-200 empty:hidden" />
        <template v-if="inboxes.length > 1">
            <span v-tooltip="ctrans('Customers waiting, every shop')" class="inline-flex items-center gap-1">
                <FontAwesomeIcon :icon="faUser" class="text-red-500" fixed-width aria-hidden="true" />
                <span class="font-semibold text-gray-800">{{ totalCustomersWaiting }}</span>
                <span class="hidden xl:inline">{{ ctrans("waiting") }}</span>
            </span>
            <span v-tooltip="ctrans('Guests waiting, every shop')" class="inline-flex items-center gap-1">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" aria-hidden="true" />
                <span class="font-semibold text-gray-800">{{ totalGuestsWaiting }}</span>
                <span class="hidden xl:inline">{{ ctrans("guests") }}</span>
            </span>
            <span v-tooltip="ctrans('Active, every shop')" class="inline-flex items-center gap-1">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-blue-500" aria-hidden="true" />
                <span class="font-semibold text-gray-800">{{ totalActive }}</span>
                <span class="hidden xl:inline">{{ ctrans("active") }}</span>
            </span>
            <span class="h-3.5 w-px bg-gray-200" aria-hidden="true" />
            <button type="button" v-tooltip="ctrans('The shops you picked in Profile › Alerts, all at once')"
                class="-mx-1 inline-flex items-center gap-1 rounded px-1 py-0.5 font-semibold text-gray-800 transition-colors hover:bg-gray-100"
                :class="isMyShopsView && 'bg-gray-100'"
                @click="openMyShops">
                <FontAwesomeIcon :icon="faStoreAlt" class="text-gray-400" fixed-width aria-hidden="true" />
                {{ ctrans("My shops") }}
            </button>
            <button type="button" v-tooltip="ctrans('Unclaimed, every shop')"
                class="-mx-1 inline-flex items-center gap-1 rounded px-1 py-0.5 transition-colors hover:bg-gray-100"
                :class="unclaimedView && 'bg-gray-100'"
                @click="openAcrossAllShops(selectUnclaimed, unclaimedView)">
                <FontAwesomeIcon :icon="faBell" :class="totalUnclaimed ? 'text-red-500' : 'text-gray-400'" fixed-width aria-hidden="true" />
                <span class="font-semibold" :class="totalUnclaimed ? 'text-red-600' : 'text-gray-800'">{{ totalUnclaimed }}</span>
                <span class="hidden xl:inline">{{ ctrans("unclaimed") }}</span>
            </button>
            <button type="button" v-tooltip="ctrans('Spam, every shop')"
                class="-mx-1 inline-flex items-center gap-1 rounded px-1 py-0.5 transition-colors hover:bg-gray-100"
                :class="spamView && 'bg-gray-100'"
                @click="openAcrossAllShops(selectSpam, spamView)">
                <FontAwesomeIcon :icon="faBan" class="text-gray-400" fixed-width aria-hidden="true" />
                <span class="font-semibold text-gray-800">{{ totalSpam }}</span>
                <span class="hidden xl:inline">{{ ctrans("spam") }}</span>
            </button>
        </template>
        <div class="ml-auto flex items-center gap-0.5">
            <template v-if="selectedSession">
                <div class="relative">
                    <button type="button" :aria-pressed="chatAutoTranslate"
                        class="inline-flex items-center gap-1 whitespace-nowrap rounded-md px-1.5 py-1 text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-800"
                        v-tooltip="ctrans('Every chat and email in a language you do not read is shown in yours')"
                        @click="toggleAutoTranslate">
                        <FontAwesomeIcon :icon="faLanguage" fixed-width aria-hidden="true" />
                        <span>{{ ctrans("Auto translation") }}</span>
                        <span class="font-semibold" :class="chatAutoTranslate ? 'text-green-600' : 'text-gray-400'">
                            {{ chatAutoTranslate ? ctrans("ON") : ctrans("OFF") }}
                        </span>
                    </button>
                    <div v-if="showTranslateHint && !showMovedHint" role="note"
                        class="absolute right-0 top-full z-40 mt-3 flex w-80 items-start gap-2 rounded-xl bg-[var(--app-accent)] p-3 text-sm font-semibold text-[var(--app-accent-text)] shadow-2xl ring-4 ring-[var(--app-accent-soft)]">
                        <span class="absolute -top-1.5 right-12 h-3 w-3 rotate-45 bg-[var(--app-accent)]" aria-hidden="true" />
                        <FontAwesomeIcon :icon="faLanguage" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                        <span class="min-w-0 flex-1">
                            {{ ctrans("Chats and emails are now shown in your language. Add the languages you read, and they will not be translated.") }}
                            <button type="button" class="mt-2 block rounded-md bg-white/20 px-2 py-1 text-xs hover:bg-white/30" @click="openMyLanguages">{{ ctrans("My languages") }}</button>
                        </span>
                        <button type="button" class="shrink-0 rounded p-0.5 opacity-80 hover:opacity-100" :aria-label="ctrans('Close')" @click.stop="closeTranslateHint">
                            <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </template>
            <button v-if="!isReadOnly && canLogPhoneCalls" type="button" @click="openPhoneCall"
                v-tooltip="phoneCallState.call ? ctrans('You are on a phone call') : ctrans('Log a phone call')"
                :aria-label="phoneCallState.call ? ctrans('You are on a phone call') : ctrans('Log a phone call')"
                class="rounded-md p-1 transition-colors"
                :class="phoneCallState.call
                    ? 'text-emerald-600 bg-emerald-50 hover:bg-emerald-100'
                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'">
                <FontAwesomeIcon :icon="faPhone" class="text-sm" fixed-width />
            </button>
            <button v-if="!isReadOnly" type="button" v-tooltip="ctrans('Chat settings')" :aria-label="ctrans('Chat settings')" @click="openChatSettings"
                class="rounded-md p-1 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700">
                <FontAwesomeIcon :icon="faCog" class="text-sm" fixed-width />
            </button>
        </div>
    </div>
    </template>

    <Dialog v-model:visible="chatSettingVisible" modal :header="ctrans('Chat Settings')"
        :style="{ width: '90vw', maxWidth: '560px' }" :breakpoints="{ '640px': '95vw' }">
        <SettingChat :initial-tab="settingInitialTab" :session-ulid="selectedSession?.ulid" @close="chatSettingVisible = false" />
    </Dialog>

    <NewWhatsappChatDialog v-model:visible="newChatVisible" :shop-id="selectedShopId"
        @created="onWhatsappChatCreated" />

    <NewEmailChatDialog v-model:visible="newEmailVisible" :shop-id="newEmailShopId" :shops="newEmailShops" :to-address="carrierView" />

    <ChatPreviewModal :contact="previewContact" @close="closePreview" @open="openFromPreview" />
    <ChatFilesDialog v-model:visible="filesVisible" :session-ulid="filesUlid" :is-whatsapp="false" />
    <ChatFilesHoverCard ref="filesHoverCard" />

    <div ref="chatArea" :style="{ height: chatAreaHeight }"
        class="relative isolate flex overflow-hidden border-t border-gray-200 bg-white -mb-6 md:-mb-24">
        <!-- PANEL 1: Inboxes (shops the agent handles) -->
        <!-- Below 1440px the rail lies over the conversation instead of pushing it aside: a phone,
             a tablet or a 14" laptop has no room for both, and squeezed, the thread header's buttons ran over the name. It stays
             lifted while folded too, or closing would animate it back in the flow and shove the
             thread sideways before letting go. -->
        <div v-show="!isEmbedded" ref="railElement" class="relative shrink-0 border-r border-gray-200 flex flex-col bg-gray-50 transition-all duration-200 max-[1439px]:absolute max-[1439px]:inset-y-0 max-[1439px]:left-0 max-[1439px]:z-40"
            :class="[railCollapsed ? 'w-16' : 'w-72 max-[1439px]:shadow-xl', railCollapsed && (selectedSession || phoneView) ? 'max-md:hidden' : '']"
            @pointerdown="startRailIdle" @pointermove="startRailIdle" @wheel="startRailIdle"
            @focusin="startRailIdle" @keydown="startRailIdle">
            <!-- The same round handle the main menu folds with, on the rail's edge halfway down. -->
            <button type="button" @click="toggleRail"
                v-tooltip="railCollapsed ? ctrans('Expand') : ctrans('Collapse')" :aria-label="railCollapsed ? ctrans('Expand') : ctrans('Collapse')"
                class="absolute right-0 z-50 flex w-8 lg:w-5 aspect-square translate-x-1/2 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                :class="railToggleTop ? '' : 'top-2/4 -translate-y-full'" :style="railToggleTop ? { top: railToggleTop } : {}">
                <FontAwesomeIcon :icon="faChevronLeft" class="h-[10px] leading-none transition-transform duration-300" :class="railCollapsed && 'rotate-180'" fixed-width aria-hidden="true" />
            </button>
            <!-- Folded, the rail is the menu again in icons: the same items in the same order, each
                 with its count on it, so nothing needs the rail opened to be reached. -->
            <div v-if="railCollapsed" class="relative min-h-0 flex-1">
            <div ref="shopListElement" @scroll="updateShopListOverflow"
                class="absolute inset-0 overflow-y-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <nav :aria-label="ctrans('Channels')" class="flex flex-col items-center gap-1 border-b border-gray-200 py-2">
                    <button v-for="app in RAIL_APPS" :key="app.key" type="button"
                        :disabled="!appIsAvailable(app.key)" :aria-label="app.label" v-tooltip="app.label"
                        class="relative flex h-9 w-11 items-center justify-center rounded-lg transition-colors disabled:opacity-40"
                        :class="activeApp === app.key ? 'bg-[var(--app-accent-soft)] text-gray-900 shadow-[inset_0_-2px_0_var(--app-accent)]' : 'text-gray-600 hover:bg-gray-200'"
                        @click="openApp(app.key)">
                        <FontAwesomeIcon :icon="app.icon" class="text-base" fixed-width aria-hidden="true" />
                        <span v-if="appWaiting(app.key)" :class="RAIL_BADGE" class="bg-red-500">
                            {{ appWaiting(app.key) > 99 ? "99+" : appWaiting(app.key) }}
                        </span>
                    </button>
                </nav>

                <div v-if="menuApp === 'phone'" class="flex flex-col items-center gap-0.5 border-b border-gray-200 py-2">
                    <button type="button" @click="openPhoneCallView('all')" v-tooltip="ctrans('All calls')" :aria-label="ctrans('All calls')"
                        :class="railRowClass(phoneCallView === 'all')">
                        <FontAwesomeIcon :icon="faList" fixed-width aria-hidden="true" />
                    </button>
                    <button type="button" @click="openPhoneCallView('in_progress')" v-tooltip="ctrans('On the phone now')" :aria-label="ctrans('On the phone now')"
                        :class="railRowClass(phoneCallView === 'in_progress')">
                        <span class="relative flex h-3.5 w-3.5 items-center justify-center">
                            <span v-if="phoneOnNow" class="absolute inline-flex h-2 w-2 animate-ping rounded-full bg-emerald-400 opacity-75" />
                            <span class="relative inline-flex h-2 w-2 rounded-full" :class="phoneOnNow ? 'bg-emerald-500' : 'bg-gray-300'" />
                        </span>
                        <span v-if="phoneOnNow" :class="RAIL_BADGE" class="bg-emerald-600">{{ phoneOnNow }}</span>
                    </button>
                    <button type="button" @click="openPhoneCallView('today')" v-tooltip="ctrans('Today')" :aria-label="ctrans('Today')"
                        :class="railRowClass(phoneCallView === 'today')">
                        <FontAwesomeIcon :icon="faPhone" fixed-width aria-hidden="true" />
                        <span v-if="phoneCallsToday" :class="RAIL_BADGE" class="bg-gray-500">{{ phoneCallsToday }}</span>
                    </button>
                </div>

                <template v-else>
                <div class="flex flex-col items-center gap-0.5 border-b border-gray-200 py-2">
                    <button type="button" @click="openInboxView('all')" v-tooltip="ctrans('All')" :aria-label="ctrans('All')"
                        :class="railRowClass(!isFolderView && activeInboxView === 'all')">
                        <FontAwesomeIcon :icon="faInbox" fixed-width aria-hidden="true" />
                    </button>
                    <button type="button" @click="openInboxView('unread')" v-tooltip="ctrans('Unread')" :aria-label="ctrans('Unread')"
                        :class="railRowClass(activeInboxView === 'unread')">
                        <FontAwesomeIcon :icon="faEnvelope" fixed-width aria-hidden="true" />
                        <span v-if="inboxViewCounts.unread" :class="[RAIL_BADGE, { 'animate-pulse opacity-40': inboxViewCountsStale }]" class="bg-gray-800">{{ inboxViewCounts.unread }}</span>
                    </button>
                    <button type="button" @click="openInboxView('replied')" v-tooltip="ctrans('Replied')" :aria-label="ctrans('Replied')"
                        :class="railRowClass(activeInboxView === 'replied')">
                        <FontAwesomeIcon :icon="faReply" fixed-width aria-hidden="true" />
                        <span v-if="inboxViewCounts.replied" :class="[RAIL_BADGE, { 'animate-pulse opacity-40': inboxViewCountsStale }]" class="bg-gray-500">{{ inboxViewCounts.replied }}</span>
                    </button>
                    <button type="button" @click="selectUnclaimed" v-tooltip="ctrans('Unclaimed')" :aria-label="ctrans('Unclaimed')"
                        :class="railRowClass(unclaimedView)">
                        <FontAwesomeIcon :icon="faBell" :class="unclaimedCount ? 'text-red-500' : ''" fixed-width aria-hidden="true" />
                        <span v-if="unclaimedCount" :class="[RAIL_BADGE, { 'animate-pulse opacity-40': agentCountsStale }]" class="bg-red-500">{{ unclaimedCount }}</span>
                    </button>
                    <button v-if="!isReadOnly" type="button" @click="selectHighlight" v-tooltip="ctrans('Starred')" :aria-label="ctrans('Starred')"
                        :class="railRowClass(highlightView)">
                        <FontAwesomeIcon :icon="faStar" :class="highlightView ? 'text-amber-400' : ''" fixed-width aria-hidden="true" />
                    </button>
                    <button type="button" @click="openInboxView('offline')" v-tooltip="ctrans('Offline messages')" :aria-label="ctrans('Offline messages')"
                        :class="railRowClass(activeInboxView === 'offline')">
                        <FontAwesomeIcon :icon="faMoon" fixed-width aria-hidden="true" />
                    </button>
                </div>

                <div v-if="chatLabels.length" class="flex flex-col items-center gap-0.5 border-b border-gray-200 py-2">
                    <button v-for="label in chatLabels" :key="label.id" type="button" @click="openLabelView(label.id)"
                        v-tooltip="label.name" :aria-label="label.name"
                        :class="railRowClass(activeInboxView === 'label' && labelFilterId === label.id)">
                        <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: label.colour }" aria-hidden="true" />
                    </button>
                </div>

                <div v-if="menuApp === 'email' || menuApp === null" class="flex flex-col items-center gap-0.5 border-b border-gray-200 py-2">
                    <button type="button" @click="selectCarriers" v-tooltip="ctrans('Couriers')" :aria-label="ctrans('Couriers')"
                        :class="railRowClass(carrierView)">
                        <FontAwesomeIcon :icon="faTruck" fixed-width aria-hidden="true" />
                        <span v-if="carriersCount" :class="[RAIL_BADGE, { 'animate-pulse opacity-40': agentCountsStale }]" class="bg-gray-500">{{ carriersCount }}</span>
                    </button>
                    <button type="button" @click="selectColleagues" v-tooltip="ctrans('Colleagues')" :aria-label="ctrans('Colleagues')"
                        :class="railRowClass(colleagueView)">
                        <FontAwesomeIcon :icon="faUsers" fixed-width aria-hidden="true" />
                        <span v-if="colleaguesCount" :class="[RAIL_BADGE, { 'animate-pulse opacity-40': agentCountsStale }]" class="bg-gray-500">{{ colleaguesCount }}</span>
                    </button>
                    <button type="button" @click="selectMarketing" v-tooltip="ctrans('Marketing')" :aria-label="ctrans('Marketing')"
                        :class="railRowClass(marketingView)">
                        <FontAwesomeIcon :icon="faBullhorn" fixed-width aria-hidden="true" />
                        <span v-if="marketingUnreadCount" :class="RAIL_BADGE" class="bg-gray-500">{{ marketingUnreadCount }}</span>
                    </button>
                </div>

                <div v-if="!isReadOnly" class="flex flex-col items-center gap-0.5 border-b border-gray-200 py-2">
                    <button type="button" @click="selectSpam" v-tooltip="ctrans('Spam')" :aria-label="ctrans('Spam')"
                        :class="railRowClass(spamView)">
                        <FontAwesomeIcon :icon="faBan" :class="spamView ? 'text-red-500' : ''" fixed-width aria-hidden="true" />
                        <span v-if="spamCount" :class="[RAIL_BADGE, { 'animate-pulse opacity-40': agentCountsStale }]" class="bg-gray-500">{{ spamCount }}</span>
                    </button>
                    <button type="button" @click="selectRubbish" v-tooltip="ctrans('Ignored')" :aria-label="ctrans('Ignored')"
                        :class="railRowClass(rubbishView)">
                        <FontAwesomeIcon :icon="faArchive" fixed-width aria-hidden="true" />
                    </button>
                    <button type="button" @click="selectTrash" v-tooltip="ctrans('Trash')" :aria-label="ctrans('Trash')"
                        :class="railRowClass(trashView)">
                        <FontAwesomeIcon :icon="faTrash" :class="trashView ? 'text-red-500' : ''" fixed-width aria-hidden="true" />
                    </button>
                </div>
                </template>

                <!-- Every shop's name starts the same way, so initials told them apart by nothing;
                     the shop's code is what tells AT from SK. -->
                <div class="flex flex-col items-center gap-1 border-b border-gray-200 py-2">
                    <button type="button" @click="openMyShops" v-tooltip="ctrans('My shops')" :aria-label="ctrans('My shops')"
                        :class="railRowClass(isMyShopsView)">
                        <FontAwesomeIcon :icon="faStoreAlt" fixed-width aria-hidden="true" />
                    </button>
                    <button v-for="inbox in inboxes" :key="inbox.id" type="button"
                        v-tooltip="ctrans(':shop (Ctrl-click to add or remove it)', { shop: inbox.name })" :aria-label="inbox.name"
                        class="relative flex h-7 w-11 items-center justify-center rounded-md text-[10px] font-bold uppercase tracking-tight transition"
                        :style="shopChipStyle(inbox, shopIsOn(inbox.id))"
                        @click="pickShop(inbox.id, $event)">
                        {{ inbox.slug.slice(0, 4) }}
                        <span v-if="waitingOn(inbox, activeApp)" :class="RAIL_BADGE" class="bg-red-500">{{ waitingOn(inbox, activeApp) }}</span>
                    </button>
                </div>

                <div v-if="supervisor && agents?.length" class="flex flex-col items-center gap-1 py-2">
                    <button v-for="agent in agents" :key="agent.id" type="button" @click="showAgentLoad(agent.id)"
                        v-tooltip="ctrans(':name: :open conversations held, room for :max', { name: agent.name, open: String(agent.open), max: String(agent.max) })"
                        :aria-label="agent.name"
                        :class="railRowClass(selectedAgentIds.includes(agent.id))">
                        <span class="relative flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-[10px] font-semibold text-gray-700"
                            :class="agent.presence === 'offline' ? 'opacity-50' : ''">
                            {{ initials(agent.name) }}
                            <span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-gray-50" :class="PRESENCE_DOT[agent.presence]" />
                        </span>
                        <span :class="[RAIL_BADGE, agent.open >= agent.max ? 'bg-red-500' : 'bg-gray-400']">{{ agent.open }}</span>
                    </button>
                </div>
            </div>
            <div v-show="shopListHasMoreAbove" class="pointer-events-none absolute inset-x-0 top-0 h-5 bg-gradient-to-b from-black/15 to-transparent" />
            <div v-show="shopListHasMoreBelow" class="pointer-events-none absolute inset-x-0 bottom-0 h-5 bg-gradient-to-t from-black/15 to-transparent" />
            </div>

            <div v-else class="flex min-h-0 flex-1">
                <Teleport defer to="#chat-channels">
                <nav :aria-label="ctrans('Channels')" class="relative flex items-stretch divide-x divide-gray-200 rounded-md border border-gray-200 bg-white" :class="showMovedHint ? 'ring-2 ring-[var(--app-accent)] ring-offset-2' : ''" @click="closeMovedHint">
                    <div v-if="showMovedHint" role="note"
                        class="absolute left-0 top-full z-40 mt-3 flex w-72 items-start gap-2 rounded-xl bg-[var(--app-accent)] p-3 text-sm font-semibold text-[var(--app-accent-text)] shadow-2xl ring-4 ring-[var(--app-accent-soft)]">
                        <span class="absolute -top-1.5 left-24 h-3 w-3 rotate-45 bg-[var(--app-accent)]" aria-hidden="true" />
                        <FontAwesomeIcon :icon="faArrowUp" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
                        <span class="min-w-0 flex-1">{{ ctrans("Chat, Mail and Calls moved here, to give the conversation more room.") }}</span>
                        <button type="button" class="shrink-0 rounded p-0.5 opacity-80 hover:opacity-100" :aria-label="ctrans('Close')" @click.stop="closeMovedHint">
                            <FontAwesomeIcon :icon="faTimes" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                    <button v-for="app in RAIL_APPS" :key="app.key" type="button"
                        :disabled="!appIsAvailable(app.key)"
                        class="relative flex items-center gap-1.5 px-2.5 py-0.5 font-semibold transition-colors first:rounded-l-md last:rounded-r-md disabled:opacity-40"
                        :class="activeApp === app.key ? 'bg-[var(--app-accent-soft)] text-gray-900 shadow-[inset_0_-2px_0_var(--app-accent)]' : 'text-gray-600 hover:bg-gray-50'"
                        @click="openApp(app.key)">
                        <FontAwesomeIcon :icon="app.icon" fixed-width aria-hidden="true" />
                        {{ app.label }}
                        <span v-if="appWaiting(app.key)"
                            v-tooltip="ctrans(':count waiting', { count: String(appWaiting(app.key)) })"
                            class="inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-none text-white tabular-nums">
                            {{ appWaiting(app.key) > 99 ? "99+" : appWaiting(app.key) }}
                        </span>
                    </button>
                </nav>
                </Teleport>


                <div class="min-w-0 flex-1 overflow-y-auto pb-4 [scrollbar-width:thin]">
                    <template v-if="menuApp === 'phone'">
                    <div class="flex items-center gap-1 pl-3 pr-3 pt-4 pb-1">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 rounded py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-800"
                            :aria-expanded="isRailMenuOpen('phone')" @click="toggleRailMenu('phone')">
                            <FontAwesomeIcon :icon="isRailMenuOpen('phone') ? faAngleDown : faAngleRight" class="text-[10px]" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ ctrans("Calls") }}</span>
                        </button>
                    </div>
                    <div v-show="isRailMenuOpen('phone')">
                        <button type="button" @click="openPhoneCallView('all')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(phoneCallView === 'all')">
                            <FontAwesomeIcon :icon="faList" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("All calls") }}</span>
                        </button>
                        <button type="button" @click="openPhoneCallView('in_progress')"
                            v-tooltip="ctrans('Calls being taken right now in these shops')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(phoneCallView === 'in_progress')">
                            <span class="relative flex h-3.5 w-[1.25em] shrink-0 items-center justify-center">
                                <span v-if="phoneOnNow" class="absolute inline-flex h-2 w-2 animate-ping rounded-full bg-emerald-400 opacity-75" />
                                <span class="relative inline-flex h-2 w-2 rounded-full" :class="phoneOnNow ? 'bg-emerald-500' : 'bg-gray-300'" />
                            </span>
                            <span class="flex-1 text-left">{{ ctrans("On the phone now") }}</span>
                            <span v-if="phoneOnNow" class="shrink-0 text-xs font-semibold tabular-nums text-emerald-700">{{ phoneOnNow }}</span>
                        </button>
                        <button type="button" @click="openPhoneCallView('today')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(phoneCallView === 'today')">
                            <FontAwesomeIcon :icon="faPhone" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Today") }}</span>
                            <span v-if="phoneCallsToday" class="shrink-0 text-xs tabular-nums text-gray-600">{{ phoneCallsToday }}</span>
                        </button>
                    </div>
                    </template>

                    <template v-if="menuApp !== 'phone'">
                    <div class="flex items-center gap-1 pl-3 pr-3 pt-4 pb-1">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 rounded py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-800"
                            :aria-expanded="railMenuOpen.queue" @click="toggleRailMenu('queue')">
                            <FontAwesomeIcon :icon="railMenuOpen.queue ? faAngleDown : faAngleRight" class="text-[10px]" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ ctrans("Queue") }}</span>
                        </button>
                        <span :class="{ 'animate-pulse opacity-40': agentCountsStale }" v-if="!railMenuOpen.queue && unclaimedCount" class="shrink-0 text-xs tabular-nums font-semibold text-red-600">{{ unclaimedCount }}</span>
                    </div>
                    <div v-show="railMenuOpen.queue">
                        <button type="button" @click="openInboxView('all')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(!isFolderView && activeInboxView === 'all')">
                            <FontAwesomeIcon :icon="faInbox" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("All") }}</span>
                        </button>
                        <button type="button" @click="openInboxView('unread')"
                            v-tooltip="ctrans('Open conversations with a customer message nobody has opened yet')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(activeInboxView === 'unread')">
                            <FontAwesomeIcon :icon="faEnvelope" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Unread") }}</span>
                            <span :class="{ 'animate-pulse opacity-40': inboxViewCountsStale }" v-if="inboxViewCounts.unread" class="shrink-0 text-xs font-semibold tabular-nums text-gray-900">{{ inboxViewCounts.unread }}</span>
                        </button>
                        <button type="button" @click="openInboxView('replied')"
                            v-tooltip="ctrans('Open conversations where we wrote last, waiting for the customer')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(activeInboxView === 'replied')">
                            <FontAwesomeIcon :icon="faReply" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Replied") }}</span>
                            <span :class="{ 'animate-pulse opacity-40': inboxViewCountsStale }" v-if="inboxViewCounts.replied" class="shrink-0 text-xs tabular-nums text-gray-600">{{ inboxViewCounts.replied }}</span>
                        </button>
                        <button type="button" @click="selectUnclaimed"
                            v-tooltip="ctrans('Nobody has taken these yet, on any shop')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(unclaimedView)">
                            <FontAwesomeIcon :icon="faBell" class="shrink-0 text-sm" :class="unclaimedCount ? 'text-red-500' : ''" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Unclaimed") }}</span>
                            <span :class="{ 'animate-pulse opacity-40': agentCountsStale }" v-if="unclaimedCount" class="shrink-0 text-xs font-semibold tabular-nums text-red-600">{{ unclaimedCount }}</span>
                        </button>
                        <button v-if="!isReadOnly" type="button" @click="selectHighlight"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(highlightView)">
                            <FontAwesomeIcon :icon="faStar" class="shrink-0 text-sm" :class="highlightView ? 'text-amber-400' : ''" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Starred") }}</span>
                        </button>
                        <button v-if="menuApp !== 'phone'" type="button" @click="openInboxView('offline')"
                            v-tooltip="ctrans('Left through the website form while nobody was there to chat')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(activeInboxView === 'offline')">
                            <FontAwesomeIcon :icon="faMoon" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Offline messages") }}</span>
                        </button>
                    </div>
                    </template>

                    <template v-if="menuApp !== 'phone'">
                    <div class="flex items-center gap-1 pl-3 pr-3 pt-4 pb-1">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 rounded py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-800"
                            :aria-expanded="isRailMenuOpen('labels')" @click="toggleRailMenu('labels')">
                            <FontAwesomeIcon :icon="isRailMenuOpen('labels') ? faAngleDown : faAngleRight" class="text-[10px]" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ ctrans("Labels") }}</span>
                        </button>
                    </div>
                    <div v-show="isRailMenuOpen('labels')">
                        <div v-for="label in chatLabels" :key="label.id" class="group/label relative">
                            <button type="button" @click="openLabelView(label.id)"
                                class="flex items-center text-sm transition-colors" :class="railRowClass(activeInboxView === 'label' && labelFilterId === label.id)">
                                <span class="mx-[3px] h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: label.colour }" aria-hidden="true" />
                                <span class="flex-1 truncate text-left">{{ label.name }}</span>
                            </button>
                            <button v-if="canManageLabels && editingLabelId !== label.id" type="button"
                                v-tooltip="ctrans('Rename, recolour or remove')"
                                :aria-label="ctrans('Edit this label')"
                                class="absolute right-3 top-1/2 hidden h-6 w-6 -translate-y-1/2 items-center justify-center rounded text-xs text-gray-400 hover:bg-gray-200 hover:text-gray-700 focus:flex group-hover/label:flex"
                                @click="startEditingLabel(label)">
                                <FontAwesomeIcon :icon="faPen" class="text-[10px]" fixed-width aria-hidden="true" />
                            </button>
                            <form v-if="editingLabelId === label.id" class="mx-3 mb-1 space-y-2 rounded border border-gray-200 bg-white p-2"
                                @submit.prevent="saveChatLabel(label)" @keydown.esc="editingLabelId = null">
                                <InputText id="edit-chat-label" v-model="editLabelName" size="small" maxlength="40" class="w-full" :aria-label="ctrans('Label name')"
                                    :disabled="isSavingLabel" @input="labelError = null" />
                                <div class="flex flex-wrap gap-1.5" role="radiogroup" :aria-label="ctrans('Colour')">
                                    <button v-for="colour in LABEL_COLOURS" :key="colour" type="button" role="radio"
                                        :aria-checked="editLabelColour === colour" :aria-label="colour"
                                        class="h-5 w-5 rounded-full ring-offset-1"
                                        :class="editLabelColour === colour ? 'ring-2 ring-gray-600' : 'hover:ring-2 hover:ring-gray-300'"
                                        :style="{ backgroundColor: colour }" @click="editLabelColour = colour" />
                                </div>
                                <div class="flex items-center gap-1">
                                    <Button type="tertiary" size="xs" native-type="submit" :label="ctrans('Save')" :loading="isSavingLabel">
                                        <template #icon>
                                            <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                                        </template>
                                    </Button>
                                    <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="editingLabelId = null">
                                        <template #icon>
                                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                                        </template>
                                    </Button>
                                </div>
                                <div>
                                    <Button type="tertiary" size="xs"
                                        :label="confirmingDeleteLabelId === label.id ? ctrans('Remove?') : ctrans('Remove this label')"
                                        :tooltip="confirmingDeleteLabelId === label.id ? ctrans('Takes it off every conversation') : undefined"
                                        @click="deleteChatLabel(label)" @blur="confirmingDeleteLabelId = null">
                                        <template #icon>
                                            <FontAwesomeIcon :icon="faTrash" class="text-amber-600" fixed-width aria-hidden="true" />
                                        </template>
                                    </Button>
                                </div>
                            </form>
                        </div>
                        <form v-if="!isReadOnly" class="px-3 py-1" @submit.prevent="createChatLabel">
                            <label for="new-chat-label" class="sr-only">{{ ctrans("New label") }}</label>
                            <InputText id="new-chat-label" v-model="newLabelName" size="small" maxlength="40" class="w-full"
                                :disabled="isSavingLabel" :placeholder="ctrans('New label, then Enter')" @input="labelError = null" />
                        </form>
                        <p v-if="labelError" class="px-3 pb-1 text-xs leading-snug text-red-600">{{ labelError }}</p>
                    </div>
                    </template>

                    <template v-if="menuApp === 'email' || menuApp === null">
                    <div class="flex items-center gap-1 pl-3 pr-3 pt-4 pb-1">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 rounded py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-800"
                            :aria-expanded="railMenuOpen.sorting" @click="toggleRailMenu('sorting')">
                            <FontAwesomeIcon :icon="railMenuOpen.sorting ? faAngleDown : faAngleRight" class="text-[10px]" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ ctrans("Mail sorting") }}</span>
                        </button>
                        <span :class="{ 'animate-pulse opacity-40': agentCountsStale }" v-if="!railMenuOpen.sorting && (carriersCount + colleaguesCount)" class="shrink-0 text-xs tabular-nums text-gray-500">{{ carriersCount + colleaguesCount }}</span>
                    </div>
                    <div v-show="railMenuOpen.sorting">
                        <button type="button" @click="selectCarriers"
                            v-tooltip="ctrans('Emails from couriers about deliveries')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(carrierView)">
                            <FontAwesomeIcon :icon="faTruck" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Couriers") }}</span>
                            <span :class="{ 'animate-pulse opacity-40': agentCountsStale }" v-if="carriersCount" class="shrink-0 text-xs tabular-nums text-gray-600">{{ carriersCount }}</span>
                        </button>
                        <button type="button" @click="selectColleagues"
                            v-tooltip="ctrans('Emails colleagues wrote to this mailbox; their circulars, newsletters and notifications stay out')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(colleagueView)">
                            <FontAwesomeIcon :icon="faUsers" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Colleagues") }}</span>
                            <span :class="{ 'animate-pulse opacity-40': agentCountsStale }" v-if="colleaguesCount" class="shrink-0 text-xs tabular-nums text-gray-600">{{ colleaguesCount }}</span>
                        </button>
                        <button type="button" @click="selectMarketing"
                            v-tooltip="ctrans('Newsletters and campaigns the shops sent their customers, to know what customers ask about')"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(marketingView)">
                            <FontAwesomeIcon :icon="faBullhorn" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Marketing") }}</span>
                            <span v-if="marketingUnreadCount" class="shrink-0 text-xs tabular-nums text-gray-600">{{ marketingUnreadCount }}</span>
                        </button>
                    </div>
                    </template>

                    <template v-if="!isReadOnly && menuApp !== 'phone'">
                    <div class="flex items-center gap-1 pl-3 pr-3 pt-4 pb-1">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 rounded py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-800"
                            :aria-expanded="railMenuOpen.cleanup" @click="toggleRailMenu('cleanup')">
                            <FontAwesomeIcon :icon="railMenuOpen.cleanup ? faAngleDown : faAngleRight" class="text-[10px]" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ ctrans("Clean-up") }}</span>
                        </button>
                        <span :class="{ 'animate-pulse opacity-40': agentCountsStale }" v-if="!railMenuOpen.cleanup && spamCount" class="shrink-0 text-xs tabular-nums text-gray-500">{{ spamCount }}</span>
                    </div>
                        <div v-show="railMenuOpen.cleanup">
                        <button type="button" @click="selectSpam"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(spamView)">
                            <FontAwesomeIcon :icon="faBan" class="shrink-0 text-sm" :class="spamView ? 'text-red-500' : ''" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Spam") }}</span>
                            <span :class="{ 'animate-pulse opacity-40': agentCountsStale }" v-if="spamCount" class="shrink-0 text-xs tabular-nums text-gray-500">{{ spamCount }}</span>
                        </button>
                        <button type="button" @click="selectRubbish"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(rubbishView)">
                            <FontAwesomeIcon :icon="faArchive" class="shrink-0 text-sm" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Ignored") }}</span>
                        </button>
                        <button type="button" @click="selectTrash"
                            class="flex items-center text-sm transition-colors" :class="railRowClass(trashView)">
                            <FontAwesomeIcon :icon="faTrash" class="shrink-0 text-sm" :class="trashView ? 'text-red-500' : ''" fixed-width />
                            <span class="flex-1 text-left">{{ ctrans("Trash") }}</span>
                        </button>
                        </div>
                    </template>

                    <div class="flex items-center gap-1 pl-3 pr-3 pt-4 pb-1">
                        <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 rounded py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-800"
                            :aria-expanded="railMenuOpen.shops" @click="toggleRailMenu('shops')">
                            <FontAwesomeIcon :icon="railMenuOpen.shops ? faAngleDown : faAngleRight" class="text-[10px]" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ supervisor ? ctrans("Shops") : ctrans("Inboxes") }}</span>
                        </button>
                        <button type="button" class="shrink-0 rounded px-1.5 py-0.5 text-xs hover:bg-gray-200"
                            :class="isMyShopsView ? 'font-semibold text-gray-900' : 'text-gray-500'"
                            v-tooltip="ctrans('Tick shops to add or remove them; click a name to see only that shop')"
                            @click="openMyShops">
                            {{ ctrans("My shops") }}
                        </button>
                    </div>
                    <div v-show="railMenuOpen.shops">
                        <div v-for="inbox in inboxes" :key="inbox.id"
                            class="flex h-8 w-[calc(100%-0.5rem)] items-center gap-2.5 rounded-r-full pl-5 pr-3 text-sm text-gray-700 transition-colors hover:bg-gray-100">
                            <Checkbox :model-value="shopIsOn(inbox.id)" binary size="small" :input-id="`rail-shop-${inbox.id}`"
                                :disabled="shopIsOn(inbox.id) && selectedShopIds.length === 1"
                                :aria-label="ctrans('Include :shop', { shop: inbox.name })" :pt="shopCheckboxPt"
                                @update:model-value="toggleShopChecked(inbox.id)" />
                            <span class="h-2.5 w-2.5 shrink-0 rounded-[3px]" :style="shopDotStyle(inbox)" aria-hidden="true" />
                            <button type="button" class="min-w-0 flex-1 truncate text-left" :class="shopIsOn(inbox.id) ? 'font-medium text-gray-900' : ''"
                                v-tooltip="ctrans('Show only this shop')" @click="pickShop(inbox.id, $event)">
                                {{ inbox.name }}
                            </button>
                            <span v-if="waitingOn(inbox, activeApp)" class="shrink-0 text-xs tabular-nums"
                                v-tooltip="ctrans(':count waiting', { count: String(waitingOn(inbox, activeApp)) })">
                                {{ waitingOn(inbox, activeApp) }}
                            </span>
                        </div>
                        <div v-if="!inboxes.length" class="pl-5 pr-3 py-2 text-xs text-gray-400">{{ ctrans("No inboxes assigned") }}</div>
                    </div>

                    <template v-if="supervisor">
                        <div class="flex items-center gap-1 pl-3 pr-3 pt-4 pb-1">
                            <button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 rounded py-0.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 hover:text-gray-800"
                                :aria-expanded="railMenuOpen.agents" @click="toggleRailMenu('agents')">
                                <FontAwesomeIcon :icon="railMenuOpen.agents ? faAngleDown : faAngleRight" class="text-[10px]" fixed-width aria-hidden="true" />
                                <span class="truncate">{{ ctrans("Agents") }}</span>
                            </button>
                            <span class="shrink-0 text-xs text-gray-500">{{ ctrans("holding / max") }}</span>
                        </div>
                        <div v-show="railMenuOpen.agents">
                        <button v-for="agent in agents" :key="agent.id" type="button"
                            v-tooltip="ctrans('Show what they are holding')"
                            class="flex items-center text-sm text-left transition-colors"
                            :class="railRowClass(selectedAgentIds.includes(agent.id))"
                            @click="showAgentLoad(agent.id)">
                            <span class="w-2 h-2 rounded-full shrink-0" :class="PRESENCE_DOT[agent.presence]" />
                            <span class="flex-1 truncate" :class="agent.presence === 'offline' ? 'text-gray-400' : ''">{{ agent.name }}</span>
                            <FontAwesomeIcon v-if="agent.on_call" :icon="faPhone" class="text-[11px] text-emerald-600 shrink-0"
                                v-tooltip="ctrans('On a phone call, taking no new conversations')" fixed-width />
                            <span class="text-[11px] tabular-nums" :class="agent.open >= agent.max ? 'text-red-500 font-semibold' : 'text-gray-400'"
                                v-tooltip="ctrans(':open conversations held, room for :max', { open: String(agent.open), max: String(agent.max) })">
                                {{ agent.open }}/{{ agent.max }}
                            </span>
                        </button>
                            <div v-if="!agents?.length" class="pl-5 pr-3 py-2 text-xs text-gray-400">{{ ctrans("Nobody is on these shops") }}</div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Holds the folded rail's place so nothing behind shifts, and a tap on the dimmed thread
             folds the rail away for this page only, as the idle timer does. -->
        <template v-if="!isEmbedded">
            <div class="min-[1440px]:hidden shrink-0 w-16" :class="{ 'max-md:hidden': selectedSession || phoneView }" />
            <div v-if="!railCollapsed" class="min-[1440px]:hidden absolute inset-0 z-[35] bg-black/20" @click="railAutoCollapsed = true" />
        </template>

        <!-- PANEL 2: conversation list for the selected inbox.
             Narrow screens have room for one column, not three, so the list and the thread take
             turns: the list until a conversation is picked, the thread after, with its own back
             button to return. From 1440px up both stand side by side. -->
        <PhoneCallList v-if="phoneView" :shop-ids="phoneShopIds" :can-log-calls="!isReadOnly && canLogPhoneCalls"
            :on-call="!!phoneCallState.call" :refresh-key="phoneCallState.call?.id ?? null" :view="phoneCallView" @log-call="openPhoneCall" />

        <MarketingFolder v-else-if="marketingView" :organisation-slug="organisation.slug"
            :mailshots="marketingMailshots" :read-ids="marketingReadIds" :loading="isLoadingMarketing"
            :show-shop="selectedShopIds.length !== 1" @read="markMarketingRead" />

        <div v-if="!marketingView && !phoneView" class="border-r border-gray-200 flex-col"
            :class="isMailLayout
                ? (selectedSession ? 'hidden' : 'flex flex-1 min-w-0')
                : ['min-[1440px]:w-80 min-[1440px]:shrink-0 min-[1440px]:flex-none', selectedSession ? 'hidden min-[1440px]:flex' : 'flex flex-1 min-w-0']">
            <button v-if="emailToResume" type="button"
                class="flex items-center gap-2 border-b border-[--app-accent-muted] bg-[--app-accent-soft] px-3 py-1.5 text-left text-xs text-[--app-accent-deep] hover:bg-[--app-accent-muted]"
                v-tooltip="ctrans('Your reply is kept as you left it')"
                @click="openApp('email')">
                <FontAwesomeIcon :icon="faEnvelope" class="shrink-0" fixed-width aria-hidden="true" />
                <span class="min-w-0 flex-1 truncate">
                    {{ ctrans("Back to the email from :name", { name: capitalize(emailToResume.name ?? "") }) }}
                </span>
                <FontAwesomeIcon :icon="faAngleRight" class="shrink-0" fixed-width aria-hidden="true" />
            </button>
            <!-- Selected inbox + My/Team segmented toggle -->
            <div class="px-3 py-1.5 border-b flex items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-gray-800 truncate mb-0.5">
                        {{ folderLabel ?? shopLabel ?? ctrans("Inbox") }}
                    </div>
                    <div v-if="agentView" class="text-[11px] text-gray-500">
                        {{ ctrans("Across every shop") }}
                    </div>
                    <div v-else-if="unclaimedView" class="text-[11px] text-gray-500 truncate">
                        {{ ctrans(":shops, waiting longer than agreed", { shops: folderScope }) }}
                    </div>
                    <div v-else-if="spamView || trashView || rubbishView || highlightView || carrierView || colleagueView" class="text-[11px] text-gray-500 truncate">
                        {{ folderScope }}
                    </div>
                    <div v-else-if="selectedShopIds.length > 1" class="text-[11px] text-gray-500">
                        {{ activeApp ? ctrans(":count shops · :channel", { count: String(selectedShopIds.length), channel: RAIL_APPS.find((app) => app.key === activeApp)?.label ?? "" }) : ctrans(":count shops", { count: String(selectedShopIds.length) }) }}
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
                    <button v-if="showNewEmailButton" type="button"
                        v-tooltip="newEmailBlockedReason ?? (hasNewEmailDraft ? ctrans('New email: you have an unsent email, it opens where you left it') : ctrans('New email'))"
                        :aria-disabled="newEmailBlockedReason !== null"
                        class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-[11px] font-medium"
                        :class="newEmailBlockedReason ? 'text-gray-400 cursor-not-allowed' : 'hover:bg-gray-100 text-[--app-accent]'"
                        @click="newEmailBlockedReason === null && (newEmailVisible = true)">
                        <FontAwesomeIcon :icon="faPlus" class="text-xs" fixed-width />
                        {{ ctrans("New email") }}
                        <span v-if="hasNewEmailDraft" class="h-1.5 w-1.5 rounded-full bg-amber-500" aria-hidden="true" />
                    </button>

                    <button v-if="isChannelOn('whatsapp') && !isReadOnly && !isFolderView" type="button"
                        v-tooltip="ctrans('New WhatsApp chat')"
                        class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg hover:bg-gray-100 text-green-600 text-[11px] font-medium"
                        @click="newChatVisible = true">
                        <FontAwesomeIcon :icon="faPlus" class="text-xs" fixed-width />
                        {{ ctrans("New chat") }}
                    </button>

                    <button type="button" v-tooltip="isNewestFirst ? ctrans('Newest first') : ctrans('Oldest first')"
                        :aria-label="isNewestFirst ? ctrans('Newest first') : ctrans('Oldest first')"
                        class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500"
                        @click="toggleSortOrder">
                        <FontAwesomeIcon :icon="isNewestFirst ? faSortAmountDown : faSortAmountDownAlt" class="text-xs" fixed-width />
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

                    <button type="button" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500" :aria-label="showSearch ? ctrans('Close search') : ctrans('Search conversations')"
                        v-tooltip="showSearch ? ctrans('Close search') : ctrans('Search conversations')" @click="toggleSearch">
                        <FontAwesomeIcon :icon="showSearch ? faTimes : faSearch" class="text-xs" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>

            <!-- Search -->
            <div v-if="showSearch" class="px-3 py-2 border-b">
                <label for="chat-inbox-search" class="sr-only">{{ ctrans("Search conversations") }}</label>
                <AutoComplete :model-value="searchQuery" input-id="chat-inbox-search" :suggestions="searchSuggestions" option-label="label"
                    :delay="250" :min-length="2" size="small" fluid :placeholder="ctrans('Name, email, order no., subject or message')"
                    @complete="loadSearchSuggestions" @update:model-value="onSearchInput"
                    @option-select="(event) => searchQuery = event.value.value">
                    <template #option="{ option }">
                        <div class="flex min-w-0 items-center gap-2 text-sm">
                            <FontAwesomeIcon :icon="option.type === 'order' ? faList : faUser" class="shrink-0 text-xs text-gray-400" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ option.label }}</span>
                            <span v-if="option.detail && option.detail !== option.label" class="truncate text-xs text-gray-500">{{ option.detail }}</span>
                        </div>
                    </template>
                </AutoComplete>
                <p v-if="searchesEverything" class="mt-1 text-[11px] text-gray-500">
                    {{ ctrans("Searching every conversation in the selected shops, closed ones included, newest first") }}
                </p>
            </div>

            <div v-if="showKindPills || isEmailMailbox" class="flex flex-wrap items-center gap-2 border-b px-3 py-1.5">
                <PillFilterBar v-if="showKindPills" :label="ctrans('Who')" :options="KIND_PILLS"
                    :selected="kindFilter === 'all' ? null : kindFilter" @select="(kind) => setKindFilter((kind ?? 'all') as 'all' | ChatKind)" />
                <PillFilterBar v-if="isEmailMailbox" :label="ctrans('Mail')" :options="mailPills"
                    :selected="mailTab === 'all' ? null : mailTab" @select="(tab) => (mailTab = (tab ?? 'all') as MailTab)" />
                <button v-if="!highlightView" type="button" :aria-pressed="starredOnly"
                    v-tooltip="ctrans('Only the conversations you starred, in this list')"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1 text-xs transition"
                    :class="starredOnly ? 'border-amber-300 bg-white font-medium text-gray-800 shadow-sm' : 'border-gray-200 bg-gray-50 text-gray-500 hover:bg-white hover:text-gray-800'"
                    @click="toggleStarredOnly">
                    <FontAwesomeIcon :icon="starredOnly ? faStarSolid : faStar" class="text-amber-400" fixed-width />
                    {{ ctrans("Highlighted") }}
                </button>
                <button v-if="selectedChannel !== 'whatsapp'" type="button" :aria-pressed="fromAssistantOnly"
                    v-tooltip="ctrans('Only the conversations the AI shopping assistant handed over, in this list')"
                    class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-1 text-xs transition"
                    :class="fromAssistantOnly ? 'border-gray-400 bg-white font-medium text-gray-800 shadow-sm' : 'border-gray-200 bg-gray-50 text-gray-500 hover:bg-white hover:text-gray-800'"
                    @click="toggleFromAssistantOnly">
                    {{ ctrans("From the AI assistant") }}
                </button>
                <span class="flex-1" />
                <button v-if="isEmailMailbox && promisedCapsuleShown && promisedCount" type="button"
                    v-tooltip="ctrans('Only the chats told while we were closed that we would reply when we open, not answered yet, in every channel and whoever holds them')"
                    class="shrink-0 py-1 px-3 rounded-full border border-gray-200 text-xs text-gray-500 hover:text-gray-700 inline-flex items-center gap-1"
                    @click="promisedOnly = true">
                    {{ ctrans("Promised") }}
                    <span :class="{ 'animate-pulse opacity-40': agentCountsStale }" class="min-w-[15px] px-1 text-[9px] leading-[15px] rounded-full text-center text-amber-700 bg-amber-100">{{ promisedCount }}</span>
                </button>
            </div>

            <!-- Status capsules: any combination, never none -->
            <div v-if="!spamView && !trashView && !unclaimedView && !isEmailMailbox && activeInboxView === 'all'" class="px-3 py-1.5 border-b">
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <button v-for="capsule in statusCapsules" v-show="!isPromisedList" :key="capsule.key" type="button"
                        v-tooltip="ctrans('Show or hide these, at least one stays on')"
                        class="shrink-0 py-1 px-3 rounded-full border transition-all inline-flex items-center justify-center gap-1"
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
                    <button v-if="promisedCapsuleShown && (promisedCount || promisedOnly)" type="button"
                        v-tooltip="ctrans('Only the chats told while we were closed that we would reply when we open, not answered yet, in every channel and whoever holds them')"
                        class="shrink-0 py-1 px-3 rounded-full border transition-all inline-flex items-center justify-center gap-1"
                        :class="promisedOnly
                            ? 'bg-amber-50 shadow-sm font-semibold border-amber-400 text-amber-700'
                            : 'border-gray-200 text-gray-500 hover:text-gray-700'"
                        @click="promisedOnly = !promisedOnly">
                        {{ ctrans("Promised") }}
                        <span v-if="promisedCount"
                            class="min-w-[15px] px-1 text-[9px] leading-[15px] rounded-full text-center"
                            :class="[promisedOnly ? 'text-white bg-amber-500' : 'text-amber-700 bg-amber-100', { 'animate-pulse opacity-40': agentCountsStale }]">{{ promisedCount }}</span>
                    </button>
                </div>

                <!-- How far back the closed list reaches. Only on when closed is being looked
                     at: waiting and active are open work and have no period. -->
                <div v-if="isStatusOn('closed') && !isPromisedList" class="mt-1.5 flex items-center gap-1 text-[10px] text-gray-500">
                    <span class="uppercase tracking-wide text-gray-400">{{ ctrans("Closed") }}</span>
                    <button v-for="period in closedPeriods" :key="period.key" type="button"
                        class="px-1.5 py-0.5 rounded transition-colors"
                        :class="closedPeriod === period.key ? 'font-semibold text-white' : 'hover:bg-gray-100'"
                        :style="closedPeriod === period.key ? { backgroundColor: 'var(--theme-color-4)' } : {}"
                        @click="setClosedPeriod(period.key)">
                        {{ period.label }}
                    </button>
                </div>

                <div v-if="showDsKinds" class="mt-1.5 flex items-center gap-1 text-[10px] text-gray-500">
                    <span class="uppercase tracking-wide text-gray-400">{{ ctrans("Kind") }}</span>
                    <button v-for="kind in dsKinds" :key="kind.key" type="button"
                        class="px-1.5 py-0.5 rounded transition-colors"
                        :class="dsKind === kind.key ? 'font-semibold text-white' : 'hover:bg-gray-100'"
                        :style="dsKind === kind.key ? { backgroundColor: 'var(--theme-color-4)' } : {}"
                        @click="setDsKind(kind.key)">
                        {{ kind.label }}
                    </button>
                </div>
            </div>

            <!-- List (flat, for the selected inbox) -->
            <div class="flex-1 overflow-y-auto overflow-x-hidden [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-300">
                <div v-if="listIsLoading" aria-busy="true" :aria-label="ctrans('Loading conversations')">
                    <template v-if="isMailLayout">
                        <div v-for="row in 10" :key="row" class="flex h-11 items-center gap-3 border-b border-gray-100 pl-3 pr-3">
                            <span class="h-4 w-4 shrink-0 animate-pulse rounded bg-gray-200" />
                            <span class="h-3 w-28 shrink-0 animate-pulse rounded bg-gray-200 sm:w-40" />
                            <span class="h-3 w-10 shrink-0 animate-pulse rounded bg-gray-100" />
                            <span class="h-3 flex-1 animate-pulse rounded bg-gray-100" />
                            <span class="h-6 w-6 shrink-0 animate-pulse rounded-full bg-gray-200" />
                            <span class="h-3 w-12 shrink-0 animate-pulse rounded bg-gray-200" />
                        </div>
                    </template>
                    <template v-else>
                        <div v-for="row in 7" :key="row" class="space-y-2 border-b border-gray-100 px-3 py-3">
                            <div class="flex items-center gap-2">
                                <span class="h-3.5 w-32 animate-pulse rounded bg-gray-200" />
                                <span class="ml-auto h-3 w-16 animate-pulse rounded bg-gray-100" />
                            </div>
                            <div class="flex gap-1.5">
                                <span class="h-3 w-8 animate-pulse rounded bg-gray-100" />
                                <span class="h-3 w-6 animate-pulse rounded bg-gray-100" />
                                <span class="h-3 w-16 animate-pulse rounded bg-gray-100" />
                            </div>
                            <span class="block h-3 w-4/5 animate-pulse rounded bg-gray-100" />
                        </div>
                    </template>
                </div>

                <div v-else-if="filteredContacts.length === 0"
                    class="h-full flex flex-col items-center justify-center gap-2 text-center px-4">
                    <div class="text-2xl">💬</div>
                    <div class="text-sm font-medium text-gray-700">{{ ctrans("No conversations") }}</div>
                </div>

                <div v-else>
                    <div v-if="canBulkSelect" class="sticky top-0 z-40 flex items-center gap-2 border-b bg-white px-3 py-1.5 text-xs text-gray-600">
                        <Checkbox :model-value="allBulkSelected" binary :aria-label="ctrans('Select all')" @update:model-value="toggleAllBulkSelected" />
                        <span class="whitespace-nowrap">{{ bulkSelectedContacts.length ? ctrans(":count selected", { count: String(bulkSelectedContacts.length) }) : ctrans("Select all") }}</span>
                        <span v-if="bulkError" class="truncate text-red-600">{{ bulkError }}</span>
                        <div v-if="bulkSelectedContacts.length" class="ml-auto flex flex-wrap items-center justify-end gap-1">
                            <template v-if="isBinView">
                                <Button type="tertiary" size="xs" :icon="faRotateLeft"
                                    :label="spamView ? ctrans('Not spam') : ctrans('Not ignored')" :disabled="isBulkRunning" @click="restoreBulkSelected" />
                                <Button type="tertiary" size="xs" :icon="faTrash"
                                    :label="ctrans('Move to trash')" :loading="isBulkRunning" @click="trashBulkSelected" />
                            </template>
                            <template v-else>
                                <Button type="tertiary" size="xs" :icon="faEnvelopeOpen"
                                    :label="ctrans('Mark as read')" :disabled="isBulkRunning" @click="readBulkSelected" />
                                <Button type="tertiary" size="xs" :icon="faEnvelope"
                                    :label="ctrans('Mark as unread')" :disabled="isBulkRunning" @click="unreadBulkSelected" />
                                <Button type="tertiary" size="xs" :icon="faCheck"
                                    :label="ctrans('Close')" :loading="isBulkRunning" @click="closeBulkSelected" />
                            </template>
                        </div>
                    </div>
                    <ChatInboxRow v-for="c in filteredContacts" :key="c.ulid" :c="c" :is-selected="selectedSession?.ulid === c.ulid" />

                    <div ref="sentinelEl" class="flex justify-center py-3">
                        <LoadingIcon v-if="isLoadingMore" class="w-5 h-5 text-gray-400" />
                    </div>
                </div>
            </div>
        </div>

        <!-- CENTER: thread + composer -->
        <div v-if="!marketingView && !phoneView" class="flex-1 min-w-0 relative" :class="selectedSession ? '' : (isMailLayout ? 'hidden' : 'hidden min-[1440px]:block')">
            <div v-if="!selectedSession"
                class="h-full flex flex-col items-center justify-center gap-2 text-gray-400">
                <div class="text-4xl">💬</div>
                <div class="text-sm">{{ ctrans("Select a conversation") }}</div>
            </div>

            <div v-else ref="conversationColumn" class="h-full flex flex-col">
                <WhatsappMessageAreaAgent v-if="activeChannel === 'whatsapp'" class="flex-1 min-h-0"
                    :messages="messages" :session="selectedSession"
                    :organisation-slug="organisation.slug"
                    :can-create-template="inboxes.find((inbox) => inbox.id === selectedSession?.shop?.id)?.can_create_template === true"
                    :read-only="isReadOnly" :show-shop="crossShopView"
                    @back="leaveConversation" @messages-read="onMessagesRead"
                    @assign-self-success="onAssignSelfSuccess" @released="onReleased"
                    @close-session="closeSession"
                    @spam-success="onSpamFromThread"
                    @task-created="reloadContactsSoon"
                    @view-profile="showProfilePanel" />
                <MessageAreaAgent v-else class="flex-1 min-h-0" :messages="messages" :session="selectedSession"
                    :read-only="isReadOnly" :ignore-reasons="ignoreReasons" :show-shop="crossShopView"
                    @back="leaveConversation" @send-message="handleSendMessage"
                    @close-session="closeSession" @view-history="showHistoryPanel"
                    @view-user-profile="showProfilePanel" @view-session-info="openSidePanelOn('log')"
                    @transfer-agent-success="onTransferAgentSuccess"
                    @assign-self-success="onAssignSelfSuccess" @released="onReleased" @messages-read="onMessagesRead"
                    @open-slack-settings="onOpenSlackSettings"
                    @spam-success="onSpamFromThread"
                    @view-tickets="showTicketsPanel"
                    @task-created="reloadContactsSoon"
                    @restore-success="onRestoreFromThread" />

                <template v-if="isEmbedded && panelSession && sidePanelVisible">
                    <div class="h-1 shrink-0 cursor-row-resize bg-gray-200 hover:bg-[--app-accent]"
                        v-tooltip="ctrans('Drag to resize')" @pointerdown="startStackedPanelResize" />
                    <ChatConversationSidePanel stacked :style="{ height: `${stackedPanelHeight}%` }"
                        :session="panelSession" :initial-tab="sidePanelTab" @close="closeSidePanel" @priority-updated="onPriorityUpdated" @agent-assigned="onAgentAssigned"
                        @synced="onSessionSynced" @customer-synced="onCustomerSynced" @unlinked="onSessionUnlinked" @merged="onSessionMerged" />
                </template>
            </div>
        </div>

        <!-- RIGHT: conversation profile panel (Conversation-style) -->
        <div v-if="!isEmbedded && panelSession && sidePanelVisible" class="absolute inset-0 z-20 bg-black/20 xl:hidden"
            @click="closeSidePanel" />

        <ChatConversationSidePanel v-if="!isEmbedded && panelSession && sidePanelVisible"
            :session="panelSession" :initial-tab="sidePanelTab" @close="closeSidePanel" @priority-updated="onPriorityUpdated" @agent-assigned="onAgentAssigned"
            @synced="onSessionSynced" @customer-synced="onCustomerSynced" @unlinked="onSessionUnlinked" @merged="onSessionMerged" />

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
                        <button v-if="!menuContact.unread" type="button"
                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                            @click="markUnread(menuContact)">
                            <FontAwesomeIcon :icon="faEnvelope" class="text-[10px]" fixed-width />
                            {{ ctrans("Mark as unread") }}
                        </button>
                        <template v-if="menuContact.channel !== 'whatsapp' && chatLabels.length">
                            <div class="border-t border-gray-100 my-1"></div>
                            <div class="px-3 pt-1.5 pb-1 text-[10px] uppercase tracking-wide text-gray-400">
                                {{ ctrans("Labels") }}
                            </div>
                            <button v-for="label in chatLabels" :key="label.id" type="button"
                                class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100"
                                :aria-pressed="contactHasLabel(menuContact, label)"
                                @click="toggleContactLabel(menuContact, label)">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: label.colour }" aria-hidden="true" />
                                <span class="flex-1 truncate text-left">{{ label.name }}</span>
                                <span v-if="contactHasLabel(menuContact, label)" class="text-[11px] text-[--app-accent]">✓</span>
                            </button>
                        </template>
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
