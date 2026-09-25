<script setup lang="ts">
import { ref, computed, inject, onMounted, watch } from 'vue'
import axios from 'axios'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faTag, faRobot, faChartLine, faCopy, faCheck, faTimes, faExternalLinkAlt, faLifeRing } from '@fal'
import { faAnglesUp, faAngleUp, faEquals, faAngleDown, faChevronDown } from '@fortawesome/free-solid-svg-icons'
import CustomerTimeline from '@/Components/Showcases/Grp/CustomerTimeline.vue'
import ChatActivityTimeline from '@/Components/Chat/ChatActivityTimeline.vue'
import HistoryChatList from '@/Components/Chat/HistoryChatList.vue'
import MessageHistory from '@/Components/Chat/MessageHistory.vue'
import TicketQuickLook from '@/Components/Tickets/TicketQuickLook.vue'
import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import Icon from '@/Components/Icon.vue'
import Modal from '@/Components/Utils/Modal.vue'
import ProductsSelector from '@/Components/Dropshipping/ProductsSelector.vue'
import SelectQuery from '@/Components/SelectQuery.vue'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import { faArrowLeft, faLink, faUnlink, faEnvelope, faGlobe, faLock, faPhone } from '@fal'
import { faWhatsapp } from '@fortawesome/free-brands-svg-icons'
import { useWhatsappCall } from '@/Composables/useWhatsappCall'

library.add(faTag, faRobot, faChartLine, faCopy, faCheck, faTimes, faExternalLinkAlt, faArrowLeft, faLink, faUnlink, faLifeRing, faLock, faPhone)

type SidePanelTab = 'profile' | 'statistics' | 'tickets' | 'timeline' | 'log' | 'history'

interface PanelSession {
    ulid: string
    contact_name: string
    is_guest: boolean
    channel?: 'website' | 'whatsapp' | string
    web_user_id?: number | null
    customer_id?: number | null
    guest_email?: string | null
    guest_phone?: string | null
    customer_suggestion?: {
        label: string
        basis: string | null
        customer: { name: string | null; email: string | null; reference: string | null } | null
        hint: string | null
    } | null
    phone_number?: string | null
    shop_name?: string | null
    status: string
    priority?: string | null
    assigned_agent?: string | null
    assigned_agent_id?: number | string | null
    started?: string | null
    ai_summary?: {
        summary?: string
        key_points?: string[]
        sentiment?: string
    } | null
}

interface CustomerTag {
    id: number
    name: string
    slug: string
}

interface CustomerStats {
    currency_symbol: string
    number_orders: number
    sales_all: number
    average_order_value: number | null
    last_invoiced_at: string | null
    first_order_date: string | null
    number_invoices: number
    number_returns: number
    number_orders_state_creating: number
}

const props = defineProps<{
    session: PanelSession
    initialTab?: SidePanelTab
    stacked?: boolean
}>()

const emit = defineEmits<{
    (e: 'close'): void
    (e: 'priority-updated', value: string): void
    (e: 'agent-assigned', agent: { id: number; name: string }): void
    (e: 'synced', webUser: { id: number; name: string; email: string | null }): void
    (e: 'customer-synced', customer: { id: number; name: string; email: string | null; phone: string | null }): void
    (e: 'unlinked'): void
}>()

const PRIORITIES: Array<{ value: string; label: string; color: string; icon: any }> = [
    { value: 'urgent', label: 'Urgent', color: '#ef4444', icon: faAnglesUp },
    { value: 'high', label: 'High', color: '#f59e0b', icon: faAngleUp },
    { value: 'normal', label: 'Normal', color: '#3b82f6', icon: faEquals },
    { value: 'low', label: 'Low', color: '#6b7280', icon: faAngleDown },
]

// Optimistic override; falls back to the prop so external changes (e.g. from the
// chat-list row menu) stay reactive here.
const pendingPriority = ref<string | null>(null)
const effectivePriority = computed(() => pendingPriority.value ?? props.session.priority ?? null)
const currentPriority = computed(() => PRIORITIES.find(p => p.value === effectivePriority.value) ?? null)
const isPriorityOpen = ref(false)
const isSavingPriority = ref(false)

watch(() => props.session.ulid, () => {
    pendingPriority.value = null
    pendingAgent.value = null
    isEditingAgent.value = false
})

// WhatsApp has no route for handing a conversation to a named agent yet, so there it stays
// what it was: the name of whoever holds it.
const canAssignAgent = computed(() => props.session.channel !== 'whatsapp')
const pendingAgent = ref<{ id: number; name: string } | null>(null)
const currentAgentName = computed(() => pendingAgent.value?.name ?? props.session.assigned_agent ?? null)
const currentAgentId = computed(() => pendingAgent.value?.id ?? props.session.assigned_agent_id ?? null)
const isEditingAgent = ref(false)
const isAssigningAgent = ref(false)

const assignAgent = async (option: any) => {
    const agentId = Number(option?.agent_id)
    if (!agentId || isAssigningAgent.value) return
    if (String(currentAgentId.value ?? '') === String(agentId)) {
        isEditingAgent.value = false
        return
    }

    isAssigningAgent.value = true
    try {
        const organisation = String((route().params as Record<string, any>)?.organisation ?? '')
        const response = await axios.patch(
            route('grp.org.chat.agents.assign', [organisation, props.session.ulid]),
            { agent_id: agentId },
            { withCredentials: true }
        )
        const name = response.data?.data?.assigned_agent_name || option?.label || option?.name || ''
        pendingAgent.value = { id: agentId, name }
        isEditingAgent.value = false
        emit('agent-assigned', { id: agentId, name })
    } catch (e: any) {
        notify({
            title: ctrans('Something went wrong'),
            text: e.response?.data?.message || e.message,
            type: 'error',
        })
    } finally {
        isAssigningAgent.value = false
    }
}

const updatePriority = async (value: string) => {
    isPriorityOpen.value = false
    if (value === effectivePriority.value) return
    pendingPriority.value = value
    isSavingPriority.value = true
    try {
        const organisation = String((route().params as Record<string, any>)?.organisation ?? '')
        await axios.patch(
            route('grp.org.chat.agents.sessions.priority', [organisation, props.session.ulid]),
            { priority: value },
            { withCredentials: true }
        )
        emit('priority-updated', value)
    } catch (e) {
        // keep pending null so it reverts to the prop value
    } finally {
        pendingPriority.value = null
        isSavingPriority.value = false
    }
}

const layout: any = inject('layout', {})
const baseUrl = layout?.appUrl ?? ''
const themePrimary = computed<string>(() => layout?.app?.theme?.[0] ?? '#16a34a')

const activeTab = ref<SidePanelTab>(props.initialTab ?? 'profile')
const isCopied = ref(false)

interface LastOrder {
    reference: string
    date: string | null
    state: string
    total: string
    url: string | null
    add_items: { products: routeType, save: routeType } | null
    follow_up: routeType | null
    payment_link: routeType | null
}

interface PreviousChat {
    ulid: string
    channel: string
    date: string | null
    topic: string | null
    summary: string | null
    status: string | null
}

interface CustomerProfile {
    tags: CustomerTag[]
    stats: CustomerStats | null
    email: string | null
    profile_url: string | null
    company_name?: string | null
    phone?: string | null
    location?: [string | null, string, string] | null
    address?: string | null
    last_orders?: LastOrder[]
    previous_chats?: PreviousChat[]
    chat_topics?: { topic: string, label: string, count: number }[]
    subscriptions?: {
        channels: Record<string, { subscribed: boolean, unsubscribed_at: string | null }>
        unsubscribed_in_an_email_at: string | null
        marketing_emails_last_30_days: { sent_at: string, outbox: string }[]
        unsubscribe: { name: string, parameters: Record<string, any> } | null
    }
    claim?: {
        is_claim: boolean
        order: { id: number, reference: string, state: string, named: boolean }
        photos: number
        lines: { id: number, transaction_id: number | null, code: string | null, name: string | null, ordered: number, dispatched: number, mentioned: boolean }[]
        reason: string
        reasons: { value: string, label: string }[]
        replacement: { name: string, parameters: Record<string, any> }
        replacements: string[]
        invoiced: Record<number, number>
        tax_ratio: number
        currency: string | null
        refunds: string[]
        refund: { name: string, parameters: Record<string, any> } | null
    } | null
    erasure?: {
        orders: number
        invoices: number
        confirmation: string
        route: { name: string, parameters: Record<string, any> } | null
    }
}

const emptyCustomerProfile = (): CustomerProfile => ({ tags: [], stats: null, email: null, profile_url: null })
const customerProfile = ref<CustomerProfile>(emptyCustomerProfile())
const isLoadingProfile = ref(false)
const profileLoaded = ref(false)

const timelineData = ref<any>({ events: [] })
const isLoadingTimeline = ref(false)
const timelineLoaded = ref(false)
const timelineError = ref<string | null>(null)

const historySessions = ref<any[]>([])
const isLoadingHistory = ref(false)
const isLoadingMoreHistory = ref(false)
const historyLoaded = ref(false)
const historyHasMore = ref(false)
const historyPage = ref(1)
const selectedHistory = ref<any | null>(null)

const tickets = ref<any[]>([])
const isLoadingTickets = ref(false)
const ticketsLoaded = ref(false)
const quickLookTicket = ref<any | null>(null)

const statusColors: Record<string, string> = {
    active:      'bg-green-100 text-green-700',
    waiting:     'bg-yellow-100 text-yellow-700',
    resolved:    'bg-blue-100 text-blue-700',
    transferred: 'bg-purple-100 text-purple-700',
    closed:      'bg-gray-100 text-gray-600',
}

const tabs: { key: SidePanelTab; label: string; onlyRegistered?: boolean }[] = [
    { key: 'profile',    label: ctrans('Overview') },
    { key: 'history',    label: ctrans('Chats'), onlyRegistered: true },
    { key: 'tickets',    label: ctrans('Tickets') },
    { key: 'statistics', label: ctrans('Stats'), onlyRegistered: true },
    { key: 'timeline',   label: ctrans('Timeline'), onlyRegistered: true },
    { key: 'log',        label: ctrans('Log') },
]

const sessionApiBase = computed(() =>
    props.session.channel === 'whatsapp'
        ? `${baseUrl}/app/api/chats/meta/sessions`
        : `${baseUrl}/app/api/chats/sessions`
)

const loadTickets = async () => {
    if (!props.session.ulid) return
    try {
        isLoadingTickets.value = true
        const res = await axios.get(`${sessionApiBase.value}/${props.session.ulid}/tickets`)
        tickets.value = res.data?.data ?? []
        ticketsLoaded.value = true
    } catch (e) {
        tickets.value = []
    } finally {
        isLoadingTickets.value = false
    }
}

const orderTakingItems = ref<LastOrder | null>(null)
const isAddingItems = ref(false)

const addItemsToOrder = async (products: { id: number, quantity_selected?: number }[]) => {
    const order = orderTakingItems.value
    if (!order?.add_items || isAddingItems.value) return
    isAddingItems.value = true
    try {
        await axios.patch(route(order.add_items.save.name, order.add_items.save.parameters), {
            products: Object.fromEntries(products.map((product) => [product.id, { quantity_ordered: product.quantity_selected ?? 1 }]))
        })
        notify({
            title: ctrans("Success"),
            text: ctrans("Items added to order :reference, the warehouse has been notified", { reference: order.reference }),
            type: "success"
        })
        orderTakingItems.value = null
        profileLoaded.value = false
        loadCustomerProfile()
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("The items could not be added"),
            type: "error"
        })
    } finally {
        isAddingItems.value = false
    }
}

const orderBeingFollowedUp = ref<string | null>(null)

const createFollowUpOrder = async (order: LastOrder) => {
    if (!order.follow_up || orderBeingFollowedUp.value) return
    orderBeingFollowedUp.value = order.reference
    try {
        const res = await axios.post(route(order.follow_up.name, order.follow_up.parameters))
        window.open(res.data.url, '_blank', 'noopener')
        notify({
            title: ctrans("Success"),
            text: ctrans("Order :followUp created, the warehouse is told to send it together with :reference", { followUp: res.data.reference, reference: order.reference }),
            type: "success"
        })
        profileLoaded.value = false
        loadCustomerProfile()
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("The follow-up order could not be created"),
            type: "error"
        })
    } finally {
        orderBeingFollowedUp.value = null
    }
}

const orderGettingPaymentLink = ref<string | null>(null)

const CHANNEL_LABELS: Record<string, string> = {
    newsletter: ctrans("Newsletter"),
    marketing: ctrans("Marketing"),
    abandoned_cart: ctrans("Abandoned basket"),
    reorder_reminder: ctrans("Reorder reminder"),
    basket_low_stock: ctrans("Low stock in basket"),
    basket_reminder: ctrans("Basket reminder"),
    price_change_notification: ctrans("Price changes"),
    gold_reward_reminder: ctrans("Gold reward reminder"),
    whatsapp_newsletter: ctrans("WhatsApp newsletter"),
}

const subscribedChannels = computed(() => Object.entries(customerProfile.value.subscriptions?.channels ?? {})
    .filter(([, channel]) => channel.subscribed)
    .map(([key]) => CHANNEL_LABELS[key] ?? key))

const lastUnsubscribedAt = computed(() => {
    const subscriptions = customerProfile.value.subscriptions
    const dates = [
        ...Object.values(subscriptions?.channels ?? {}).map((channel) => channel.unsubscribed_at),
        subscriptions?.unsubscribed_in_an_email_at,
    ].filter(Boolean) as string[]

    return dates.sort().pop() ?? null
})

const claimOpen = ref(false)
const claimReason = ref("")
const claimPicked = ref<Record<number, number>>({})
const isReplacing = ref(false)

watch(() => customerProfile.value.claim, (claim) => {
    claimOpen.value = !!claim?.is_claim
    claimReason.value = claim?.reason ?? "missing_from_parcel"
    claimPicked.value = Object.fromEntries((claim?.lines ?? [])
        .filter((line) => line.mentioned || line.dispatched < line.ordered)
        .map((line) => [line.id, line.dispatched < line.ordered ? line.ordered - line.dispatched : line.ordered]))
})

const toggleClaimLine = (line: { id: number, ordered: number }) => {
    const picked = { ...claimPicked.value }
    if (line.id in picked) {
        delete picked[line.id]
    } else {
        picked[line.id] = line.ordered
    }
    claimPicked.value = picked
}

const isRefunding = ref(false)

const claimRefundAmount = computed(() => {
    const claim = customerProfile.value.claim
    if (!claim) return 0
    const shares: Record<number, number> = {}
    for (const line of claim.lines) {
        const quantity = Number(claimPicked.value[line.id] ?? 0)
        if (!line.transaction_id || !quantity || !line.ordered) continue
        shares[line.transaction_id] = Math.max(shares[line.transaction_id] ?? 0, Math.min(1, quantity / line.ordered))
    }
    const net = Object.entries(shares).reduce((sum, [transactionId, share]) => sum + (claim.invoiced[Number(transactionId)] ?? 0) * share, 0)

    return Math.round(net * claim.tax_ratio * 100) / 100
})

const refundClaimToBalance = async () => {
    const claim = customerProfile.value.claim
    const items = Object.entries(claimPicked.value).filter(([, quantity]) => Number(quantity) > 0)
    if (!claim?.refund || !items.length || isRefunding.value || !claimRefundAmount.value) return
    if (!window.confirm(ctrans("Refund about :amount :currency to the customer's balance for :count lines of :order? A refund invoice is made and paid out as credit.", { amount: claimRefundAmount.value.toFixed(2), currency: claim.currency ?? "", count: String(items.length), order: claim.order.reference }))) return
    isRefunding.value = true
    try {
        const res = await axios.post(route(claim.refund.name, claim.refund.parameters), {
            delivery_note_items: items.map(([id, quantity]) => ({ id: Number(id), quantity: Number(quantity) })),
        })
        notify({ title: ctrans("Refunded to balance"), text: `${res.data.reference}: ${Number(res.data.amount).toFixed(2)} ${res.data.currency ?? ""}`, type: "success" })
        profileLoaded.value = false
        await loadCustomerProfile()
    } catch (error: any) {
        notify({ title: ctrans("Something went wrong"), text: error?.response?.data?.message ?? ctrans("The refund could not be made"), type: "error" })
    } finally {
        isRefunding.value = false
    }
}

const createReplacement = async () => {
    const claim = customerProfile.value.claim
    const items = Object.entries(claimPicked.value).filter(([, quantity]) => Number(quantity) > 0)
    if (!claim || !items.length || isReplacing.value) return
    if (!window.confirm(ctrans("Send :count lines again to the customer as a replacement of :order?", { count: String(items.length), order: claim.order.reference }))) return
    isReplacing.value = true
    try {
        const res = await axios.post(route(claim.replacement.name, claim.replacement.parameters), {
            delivery_note_items: items.map(([id, quantity]) => ({ id: Number(id), quantity: Number(quantity), reason: claimReason.value })),
            private_warehouse_note: ctrans("Claim in chat conversation :ulid", { ulid: props.session.ulid }),
        })
        notify({ title: ctrans("Replacement created"), text: res.data?.reference ?? claim.order.reference, type: "success" })
        profileLoaded.value = false
        await loadCustomerProfile()
    } catch (error: any) {
        notify({ title: ctrans("Something went wrong"), text: error?.response?.data?.message ?? ctrans("The replacement could not be created"), type: "error" })
    } finally {
        isReplacing.value = false
    }
}

const erasureOpen = ref(false)
const erasureReason = ref("")
const erasureConfirmation = ref("")
const isErasing = ref(false)

const openErasure = () => {
    erasureOpen.value = !erasureOpen.value
    erasureReason.value ||= ctrans("Asked to have their data deleted, in chat conversation :ulid", { ulid: props.session?.ulid ?? "" })
}

const eraseCustomer = async () => {
    const erasure = customerProfile.value.erasure
    if (!erasure?.route || isErasing.value || erasureConfirmation.value !== erasure.confirmation) return
    isErasing.value = true
    try {
        await axios.post(route(erasure.route.name, erasure.route.parameters), {
            reason: erasureReason.value,
            reference: erasureConfirmation.value,
        })
        notify({ title: ctrans("Personal data erased"), text: ctrans("Their name, contact details, addresses and conversations are gone; orders and invoices stay, without them"), type: "success" })
        erasureOpen.value = false
        customerProfile.value = emptyCustomerProfile()
    } catch (error: any) {
        notify({ title: ctrans("Something went wrong"), text: error?.response?.data?.message ?? ctrans("Their data could not be erased"), type: "error" })
    } finally {
        isErasing.value = false
    }
}

const isUnsubscribing = ref(false)

const unsubscribeFromMarketing = async () => {
    const unsubscribe = customerProfile.value.subscriptions?.unsubscribe
    if (!unsubscribe || isUnsubscribing.value) return
    if (!window.confirm(ctrans("Unsubscribe this customer from every newsletter, marketing email and reminder? Emails about their orders keep coming."))) return
    isUnsubscribing.value = true
    try {
        const res = await axios.post(route(unsubscribe.name, unsubscribe.parameters))
        customerProfile.value.subscriptions = { ...res.data.subscriptions, unsubscribe }
        notify({ title: ctrans("Unsubscribed"), text: ctrans("They will get no more newsletters, marketing or reminders"), type: "success" })
    } catch (error: any) {
        notify({ title: ctrans("Something went wrong"), text: error?.response?.data?.message ?? ctrans("Could not unsubscribe"), type: "error" })
    } finally {
        isUnsubscribing.value = false
    }
}

const createPaymentLink = async (order: LastOrder) => {
    if (!order.payment_link || orderGettingPaymentLink.value) return
    orderGettingPaymentLink.value = order.reference
    try {
        const res = await axios.post(route(order.payment_link.name, order.payment_link.parameters))
        await navigator.clipboard.writeText(res.data.url)
        notify({
            title: ctrans("Payment link copied"),
            text: ctrans("Paste it in the chat: :amount :currency for order :reference, the payment lands on the order by itself", { amount: res.data.amount, currency: res.data.currency, reference: order.reference }),
            type: "success"
        })
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message ?? ctrans("The payment link could not be created"),
            type: "error"
        })
    } finally {
        orderGettingPaymentLink.value = null
    }
}

const loadCustomerProfile = async () => {
    if (props.session.is_guest || profileLoaded.value || !props.session.ulid) return
    try {
        isLoadingProfile.value = true
        const res = await axios.get(`${sessionApiBase.value}/${props.session.ulid}/customer-profile`)
        customerProfile.value = res.data
        profileLoaded.value = true
    } finally {
        isLoadingProfile.value = false
    }
}

const loadTimeline = async () => {
    if (props.session.is_guest || timelineLoaded.value || !props.session.ulid) return
    try {
        isLoadingTimeline.value = true
        timelineError.value = null
        const res = await axios.get(`${sessionApiBase.value}/${props.session.ulid}/customer-timeline`)
        timelineData.value = res.data
        timelineLoaded.value = true
    } catch (e: any) {
        timelineError.value = e.response?.data?.message ?? e.message ?? 'Failed to load timeline'
    } finally {
        isLoadingTimeline.value = false
    }
}

const loadHistory = async (loadMore = false) => {
    if (props.session.is_guest) return
    if (!loadMore && historyLoaded.value) return
    if (!props.session.customer_id && !props.session.web_user_id) return

    const params: Record<string, any> = { limit: 20 }
    if (props.session.customer_id) params.customer_id = props.session.customer_id
    if (props.session.web_user_id) params.web_user_id = props.session.web_user_id

    if (loadMore) {
        params.page = historyPage.value + 1
        isLoadingMoreHistory.value = true
    } else {
        isLoadingHistory.value = true
    }

    try {
        const res = await axios.get(`${baseUrl}/app/api/chats/customer-chat-history`, {
            params,
            withCredentials: true,
        })
        const sessions = res.data?.data?.sessions ?? []
        const pagination = res.data?.data?.pagination ?? {}

        if (loadMore) {
            historySessions.value = [...historySessions.value, ...sessions]
        } else {
            historySessions.value = sessions
        }

        historyHasMore.value = !!pagination.has_more
        historyPage.value = pagination.current_page ?? historyPage.value
        historyLoaded.value = true
    } finally {
        isLoadingHistory.value = false
        isLoadingMoreHistory.value = false
    }
}

const openPreviousChat = (chat: PreviousChat) => {
    selectedHistory.value = { ulid: chat.ulid, channel: chat.channel }
    activeTab.value = 'history'
}

const isTicketSettled = (ticket: any) => ['resolved', 'cancelled'].includes(String(ticket?.status ?? ''))

const resetAndLoad = () => {
    profileLoaded.value = false
    timelineLoaded.value = false
    historyLoaded.value = false
    historySessions.value = []
    historyHasMore.value = false
    historyPage.value = 1
    selectedHistory.value = null
    tickets.value = []
    ticketsLoaded.value = false
    quickLookTicket.value = null
    customerProfile.value = emptyCustomerProfile()
    activeTab.value = 'profile'
    loadCustomerProfile()
}

watch(() => props.session.ulid, () => resetAndLoad())

watch(activeTab, async (tab) => {
    if ((tab === 'profile' || tab === 'statistics') && !profileLoaded.value) await loadCustomerProfile()
    if (tab === 'tickets' && !ticketsLoaded.value) await loadTickets()
    if (tab === 'timeline' && !timelineLoaded.value) await loadTimeline()
    if (tab === 'history' && !historyLoaded.value) await loadHistory()
})

// Opened straight onto a tab from the thread (the outstanding tickets button), and again when
// that button is pressed while the panel is already open on something else.
watch(() => props.initialTab, (tab) => {
    if (tab) activeTab.value = tab
})

// The watcher below only fires once a tab changes, so a panel opened straight onto one has
// to fetch for itself: history opened this way listed nothing and said there were no chats.
onMounted(() => {
    loadCustomerProfile()
    if (props.initialTab === 'tickets') loadTickets()
    if (props.initialTab === 'history') loadHistory()
    if (props.initialTab === 'timeline') loadTimeline()
})

// When a guest gets matched to a registered Aiku customer, refresh the customer data.
watch(() => props.session.is_guest, (isGuest) => {
    if (!isGuest) resetAndLoad()
})

const isSyncing = ref(false)
const syncError = ref<string | null>(null)
const showCustomerPicker = ref(false)
const customerQuery = ref('')
const customerCandidates = ref<Array<{ id: number, name: string, reference: string, email: string }>>([])
const isSearchingCustomers = ref(false)
let customerSearchTimeout: ReturnType<typeof setTimeout> | null = null

const isWhatsapp = computed(() => props.session.channel === 'whatsapp')

const { call: whatsappCall, busy: isWhatsappCallBusy, dial: dialWhatsapp } = useWhatsappCall()
const startWhatsappCall = () => {
    dialWhatsapp(String((route().params as Record<string, any>)?.organisation ?? ''), props.session.ulid)
}

const canMatchCustomer = computed(() => {
    if (!props.session.is_guest) return false
    if (isWhatsapp.value) return !!(props.session.phone_number || props.session.guest_phone)
    return !!props.session.guest_email
})

const syncGuest = async () => {
    if (!canMatchCustomer.value || isSyncing.value) return
    isSyncing.value = true
    syncError.value = null

    try {
        if (isWhatsapp.value) {
            const phone = props.session.phone_number || props.session.guest_phone
            const res = await axios.put(
                `${baseUrl}/app/api/chats/meta/sessions/${props.session.ulid}/sync-by-phone`,
                { phone },
                { withCredentials: true }
            )
            if (res.data?.success && res.data?.data?.customer) {
                emit('customer-synced', res.data.data.customer)
            } else {
                syncError.value = res.data?.message ?? 'No matching Aiku customer for this phone number'
            }
        } else {
            const res = await axios.put(
                `${baseUrl}/app/api/chats/sessions/${props.session.ulid}/sync-by-email`,
                { email: props.session.guest_email },
                { withCredentials: true }
            )
            if (res.data?.success && res.data?.data?.web_user) {
                emit('synced', res.data.data.web_user)
            } else {
                syncError.value = res.data?.message ?? 'No matching Aiku customer for this email'
                showCustomerPicker.value = true
            }
        }
    } catch (e: any) {
        syncError.value = e?.response?.data?.message ?? (isWhatsapp.value
            ? 'No matching Aiku customer for this phone number'
            : 'No matching Aiku customer for this email')
        if (!isWhatsapp.value) showCustomerPicker.value = true
    } finally {
        isSyncing.value = false
    }
}

const suggestionDismissed = ref(false)

watch(() => props.session.ulid, () => {
    suggestionDismissed.value = false
    syncError.value = null
    showCustomerPicker.value = false
    customerQuery.value = ''
    customerCandidates.value = []
})

const searchCustomerCandidates = (query: string) => {
    customerQuery.value = query
    if (customerSearchTimeout) clearTimeout(customerSearchTimeout)
    if (query.trim().length < 2) {
        customerCandidates.value = []
        isSearchingCustomers.value = false
        return
    }
    isSearchingCustomers.value = true
    customerSearchTimeout = setTimeout(async () => {
        try {
            const res = await axios.get(
                `${baseUrl}/app/api/chats/sessions/${props.session.ulid}/customer-candidates`,
                { params: { q: query }, withCredentials: true }
            )
            customerCandidates.value = res.data ?? []
        } catch {
            customerCandidates.value = []
        } finally {
            isSearchingCustomers.value = false
        }
    }, 300)
}

const unlinkCustomer = async () => {
    if (isSyncing.value) return
    if (!window.confirm(ctrans('Unlink this customer from the conversation?'))) return
    isSyncing.value = true
    syncError.value = null
    try {
        await axios.delete(`${baseUrl}/app/api/chats/sessions/${props.session.ulid}/customer`, { withCredentials: true })
        emit('unlinked')
    } catch (e: any) {
        syncError.value = e?.response?.data?.message ?? ctrans('Could not unlink this customer')
    } finally {
        isSyncing.value = false
    }
}

const pickCustomer = async (customerId: number) => {
    if (isSyncing.value) return
    isSyncing.value = true
    syncError.value = null
    try {
        const res = await axios.put(
            `${baseUrl}/app/api/chats/sessions/${props.session.ulid}/customer`,
            { customer_id: customerId },
            { withCredentials: true }
        )
        showCustomerPicker.value = false
        emit('synced', res.data.data.web_user)
    } catch (e: any) {
        syncError.value = e?.response?.data?.message ?? ctrans('Could not match this customer')
    } finally {
        isSyncing.value = false
    }
}

const answerSuggestion = async (confirmed: boolean) => {
    if (isSyncing.value) return
    isSyncing.value = true
    syncError.value = null

    try {
        const res = await axios.request({
            method: confirmed ? 'put' : 'delete',
            url: `${sessionApiBase.value}/${props.session.ulid}/suggested-customer`,
            withCredentials: true,
        })
        suggestionDismissed.value = true
        if (confirmed && isWhatsapp.value) emit('customer-synced', res.data.data.customer)
        if (confirmed && !isWhatsapp.value) emit('synced', res.data.data.web_user)
    } catch (e: any) {
        syncError.value = e?.response?.data?.message ?? ctrans('Could not update the suggested customer')
    } finally {
        isSyncing.value = false
    }
}

const formatStatDate = (date: string | null): string => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatCurrency = (amount: number | null | undefined, symbol: string): string => {
    if (amount == null) return '-'
    return `${symbol} ${Number(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`
}

const copyChatId = async () => {
    await navigator.clipboard.writeText(props.session.ulid)
    isCopied.value = true
    setTimeout(() => { isCopied.value = false }, 2000)
}
</script>

<template>
    <!-- It floats over the conversation at every width. Taking a column of its own moved the
         thread and re-wrapped every message the moment somebody looked at a profile. -->
    <div class="flex flex-col overflow-hidden border-gray-200 bg-white"
        :class="stacked ? 'shrink-0' : 'absolute inset-y-0 right-0 z-30 w-96 max-w-[85vw] border-l shadow-2xl'">
        <!-- Tabs -->
        <div class="flex border-b border-gray-100 shrink-0 text-xs pl-2">
            <template v-for="tab in tabs" :key="tab.key">
                <button v-if="!tab.onlyRegistered || !session.is_guest"
                    class="flex-1 py-2.5 font-medium transition-colors whitespace-nowrap"
                    :class="activeTab === tab.key ? 'border-b-2' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
                    :style="activeTab === tab.key ? { color: themePrimary, borderBottomColor: themePrimary } : {}"
                    @click="activeTab = tab.key">
                    {{ tab.label }}
                </button>
            </template>
            <button class="px-3 text-gray-400 hover:text-gray-600" @click="emit('close')" aria-label="Close">
                <FontAwesomeIcon :icon="['fal', 'fa-times']" class="text-sm" fixed-width />
            </button>
        </div>

        <!-- Tab Content -->
        <div class="flex-1 overflow-y-auto">
            <!-- Profile -->
            <div v-if="activeTab === 'profile'" class="divide-y divide-gray-100">
                <div class="px-4 py-3 space-y-2.5">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{{ ctrans("Contact") }}</p>
                    <div class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">{{ ctrans("Name") }}</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800">
                            <a v-if="!session.is_guest && customerProfile.profile_url"
                                :href="customerProfile.profile_url" target="_blank" rel="noopener"
                                class="inline-flex items-center gap-1 hover:underline"
                                :style="{ color: themePrimary }">
                                {{ session.contact_name || '-' }}
                                <FontAwesomeIcon :icon="['fal', 'fa-external-link-alt']" class="text-[10px]" fixed-width />
                            </a>
                            <span v-else>{{ session.contact_name || '-' }}</span>
                        </div>
                    </div>
                    <div v-if="!isWhatsapp && !session.is_guest && session.web_user_id" class="grid grid-cols-3 gap-2 items-start">
                        <div></div>
                        <div class="col-span-2">
                            <button type="button" :disabled="isSyncing"
                                class="inline-flex items-center gap-1 text-[11px] text-gray-400 hover:text-gray-600 hover:underline disabled:opacity-60"
                                @click="unlinkCustomer">
                                <FontAwesomeIcon :icon="['fal', 'fa-unlink']" class="text-[9px]" fixed-width />
                                {{ ctrans("Unlink customer") }}
                            </button>
                        </div>
                    </div>
                    <div v-if="!session.is_guest && customerProfile.email" class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">{{ ctrans("Email") }}</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800 break-all">
                            <a :href="`mailto:${customerProfile.email}`" class="hover:underline" :style="{ color: themePrimary }">{{ customerProfile.email }}</a>
                        </div>
                    </div>
                    <div v-if="session.is_guest && session.guest_email" class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">{{ ctrans("Email") }}</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800 break-all">
                            <a :href="`mailto:${session.guest_email}`" class="hover:underline" :style="{ color: themePrimary }">{{ session.guest_email }}</a>
                        </div>
                    </div>
                    <div v-if="customerProfile.company_name" class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">{{ ctrans("Company") }}</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800">{{ customerProfile.company_name }}</div>
                    </div>
                    <div v-if="session.phone_number || session.guest_phone || customerProfile.phone" class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">{{ ctrans("Phone") }}</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800 break-all">{{ session.phone_number || session.guest_phone || customerProfile.phone }}</div>
                    </div>
                    <div v-if="isWhatsapp && session.phone_number" class="grid grid-cols-3 gap-2 items-start">
                        <div></div>
                        <div class="col-span-2">
                            <button type="button" :disabled="!!whatsappCall || isWhatsappCallBusy"
                                class="inline-flex items-center gap-1 text-[11px] font-medium rounded border px-1.5 py-0.5 transition-colors disabled:opacity-60 hover:bg-gray-50"
                                :style="{ color: themePrimary, borderColor: themePrimary }"
                                @click="startWhatsappCall">
                                <FontAwesomeIcon :icon="['fal', 'fa-phone']" class="text-[9px]" fixed-width />
                                {{ ctrans("WhatsApp call") }}
                            </button>
                        </div>
                    </div>
                    <div v-if="customerProfile.location || customerProfile.address" class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">{{ ctrans("Address") }}</div>
                        <div class="col-span-2 text-xs space-y-0.5">
                            <AddressLocation v-if="customerProfile.location" :data="customerProfile.location" class="font-medium text-gray-800" />
                            <div v-else-if="customerProfile.address" class="text-[11px] text-gray-500" v-html="customerProfile.address"></div>
                        </div>
                    </div>
                    <div v-if="session.is_guest && session.customer_suggestion && !suggestionDismissed"
                        class="rounded border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs space-y-1">
                        <template v-if="session.customer_suggestion.customer">
                            <div class="font-medium text-gray-800">
                                {{ ctrans("Probably") }} {{ session.customer_suggestion.customer.name }}
                                <span v-if="session.customer_suggestion.customer.reference" class="font-normal text-gray-500">({{ session.customer_suggestion.customer.reference }})</span>
                            </div>
                            <div class="text-gray-600">{{ session.customer_suggestion.label }} · {{ ctrans("not verified") }}</div>
                            <div class="flex items-center gap-2 pt-0.5">
                                <button type="button" :disabled="isSyncing"
                                    class="font-medium rounded border px-1.5 py-0.5 bg-white hover:bg-gray-50 disabled:opacity-60"
                                    :style="{ color: themePrimary, borderColor: themePrimary }"
                                    @click="answerSuggestion(true)">
                                    {{ ctrans("Confirm") }}
                                </button>
                                <button type="button" :disabled="isSyncing"
                                    class="text-gray-500 hover:text-gray-700 hover:underline disabled:opacity-60"
                                    @click="answerSuggestion(false)">
                                    {{ ctrans("Not them") }}
                                </button>
                            </div>
                        </template>
                        <div v-else class="text-gray-700">{{ session.customer_suggestion.hint }}</div>
                    </div>
                    <div v-if="canMatchCustomer" class="grid grid-cols-3 gap-2 items-start">
                        <div></div>
                        <div class="col-span-2">
                            <button type="button" :disabled="isSyncing"
                                class="inline-flex items-center gap-1 text-[11px] font-medium rounded border px-1.5 py-0.5 transition-colors disabled:opacity-60 hover:bg-gray-50"
                                :style="{ color: themePrimary, borderColor: themePrimary }"
                                @click="syncGuest">
                                <FontAwesomeIcon :icon="['fal', 'fa-link']" class="text-[9px]" fixed-width />
                                {{ isSyncing ? 'Matching…' : 'Match to Aiku customer' }}
                            </button>
                            <p v-if="syncError" class="text-[10px] text-amber-600 mt-1">{{ syncError }}</p>
                            <div v-if="showCustomerPicker && !isWhatsapp" class="mt-1.5 rounded border border-gray-200 p-1.5 space-y-1.5">
                                <input type="text"
                                    class="w-full text-xs rounded border border-gray-200 px-1.5 py-1 focus:outline-none"
                                    :style="{ borderColor: themePrimary }"
                                    :placeholder="ctrans('Customer name, reference or email')"
                                    :value="customerQuery"
                                    @input="searchCustomerCandidates(($event.target as HTMLInputElement).value)" />
                                <div v-if="isSearchingCustomers" class="text-[11px] text-gray-400">{{ ctrans('Searching…') }}</div>
                                <template v-else-if="customerQuery.trim().length >= 2">
                                    <button v-for="candidate in customerCandidates" :key="candidate.id" type="button"
                                        :disabled="isSyncing"
                                        class="block w-full text-left text-xs rounded px-1.5 py-1 hover:bg-gray-50 disabled:opacity-60"
                                        @click="pickCustomer(candidate.id)">
                                        <span class="font-medium text-gray-800">{{ candidate.name }} ({{ candidate.reference }})</span>
                                        <span class="block text-[11px] text-gray-400">{{ candidate.email }}</span>
                                    </button>
                                    <p v-if="!customerCandidates.length" class="text-[11px] text-gray-400">{{ ctrans('No customers found') }}</p>
                                </template>
                                <button type="button" class="text-[11px] text-gray-500 hover:underline" @click="showCustomerPicker = false">
                                    {{ ctrans('Cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="session.shop_name" class="grid grid-cols-3 gap-2 items-start">
                        <div class="text-gray-500 text-xs">Shop</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800">{{ session.shop_name }}</div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Chat ID</div>
                        <div class="col-span-2 flex items-center gap-1">
                            <code class="text-[11px] font-mono text-gray-700 bg-gray-100 rounded px-1.5 py-0.5 truncate">{{ session.ulid }}</code>
                            <button class="shrink-0 text-gray-400 hover:text-gray-600" @click="copyChatId" aria-label="Copy chat ID">
                                <FontAwesomeIcon :icon="isCopied ? ['fal', 'fa-check'] : ['fal', 'fa-copy']" class="text-xs" fixed-width />
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!session.is_guest && customerProfile.claim" class="px-4 py-3 space-y-2 text-xs"
                    :class="customerProfile.claim.is_claim ? 'bg-amber-50/60' : ''">
                    <button type="button" class="flex w-full items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider"
                        :class="customerProfile.claim.is_claim ? 'text-amber-700' : 'text-gray-400 hover:text-gray-600'" @click="claimOpen = !claimOpen">
                        {{ customerProfile.claim.is_claim ? ctrans("Claim") : ctrans("Replacement") }}
                        <span class="normal-case font-medium">{{ customerProfile.claim.order.reference }}</span>
                        <span v-if="!customerProfile.claim.order.named" class="normal-case font-normal text-gray-400">{{ ctrans("(their last dispatched order)") }}</span>
                    </button>
                    <template v-if="claimOpen">
                        <p class="text-gray-500">
                            <span v-if="customerProfile.claim.photos">{{ ctrans(":count photos sent", { count: String(customerProfile.claim.photos) }) }} · </span>
                            <span v-if="customerProfile.claim.replacements.length" class="text-amber-700">{{ ctrans("Already replaced") }}: {{ customerProfile.claim.replacements.join(", ") }}</span>
                            <span v-if="customerProfile.claim.refunds.length" class="text-amber-700"> {{ ctrans("Already refunded") }}: {{ customerProfile.claim.refunds.join(", ") }}</span>
                            <span v-else>{{ ctrans("Tick what to send again") }}</span>
                        </p>
                        <div class="max-h-56 space-y-1 overflow-y-auto">
                            <label v-for="line in customerProfile.claim.lines" :key="line.id"
                                class="flex items-center gap-1.5 rounded px-1 py-0.5 hover:bg-white"
                                :class="line.mentioned ? 'font-medium' : ''">
                                <input type="checkbox" :checked="line.id in claimPicked" @change="toggleClaimLine(line)" />
                                <span class="shrink-0 text-gray-800">{{ line.code }}</span>
                                <span class="truncate text-gray-500" :title="line.name ?? ''">{{ line.name }}</span>
                                <span class="ml-auto shrink-0 tabular-nums" :class="line.dispatched < line.ordered ? 'text-red-600' : 'text-gray-400'"
                                    v-tooltip="ctrans('Ordered, sent')">{{ line.ordered }}/{{ line.dispatched }}</span>
                                <input v-if="line.id in claimPicked" v-model.number="claimPicked[line.id]" type="number" min="0" step="1"
                                    class="w-12 shrink-0 rounded border-gray-300 px-1 py-0 text-xs" />
                            </label>
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model="claimReason" class="flex-1 rounded border-gray-300 py-0.5 text-xs">
                                <option v-for="reason in customerProfile.claim.reasons" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
                            </select>
                            <button type="button" class="shrink-0 rounded px-2 py-1 font-medium text-white disabled:opacity-40"
                                :style="{ backgroundColor: themePrimary }"
                                :disabled="isReplacing || !Object.keys(claimPicked).length" @click="createReplacement">
                                {{ isReplacing ? ctrans("Creating…") : ctrans("Create replacement") }}
                            </button>
                        </div>
                        <div v-if="customerProfile.claim.refund" class="flex items-center justify-end gap-2">
                            <span class="text-gray-500">{{ ctrans("or refund") }} <span class="font-medium tabular-nums text-gray-800">{{ claimRefundAmount.toFixed(2) }} {{ customerProfile.claim.currency }}</span></span>
                            <button type="button" class="shrink-0 rounded border border-gray-300 px-2 py-1 font-medium text-gray-700 hover:bg-white disabled:opacity-40"
                                :disabled="isRefunding || !claimRefundAmount" @click="refundClaimToBalance">
                                {{ isRefunding ? ctrans("Refunding…") : ctrans("Refund to balance") }}
                            </button>
                        </div>
                    </template>
                </div>

                <div v-if="!session.is_guest" class="px-4 py-3 space-y-2">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{{ ctrans("Last orders") }}</p>
                    <div v-if="isLoadingProfile" class="space-y-2">
                        <div class="h-4 bg-gray-100 rounded animate-pulse w-3/4" />
                        <div class="h-4 bg-gray-100 rounded animate-pulse w-1/2" />
                    </div>
                    <p v-else-if="!customerProfile.last_orders?.length" class="text-xs text-gray-400">{{ ctrans("No orders yet") }}</p>
                    <div v-for="order in customerProfile.last_orders" v-else :key="order.reference"
                        class="flex items-center gap-2 text-xs">
                        <a v-if="order.url" :href="order.url" target="_blank" rel="noopener"
                            class="font-medium hover:underline" :style="{ color: themePrimary }">{{ order.reference }}</a>
                        <span v-else class="font-medium text-gray-800">{{ order.reference }}</span>
                        <span class="text-gray-500">{{ order.state }}</span>
                        <button v-if="order.add_items" type="button" class="font-medium hover:underline"
                            :style="{ color: themePrimary }" @click="orderTakingItems = order">
                            + {{ ctrans("Add items") }}
                        </button>
                        <button v-else-if="order.follow_up" type="button" class="font-medium hover:underline disabled:opacity-50"
                            :style="{ color: themePrimary }" :disabled="orderBeingFollowedUp === order.reference"
                            v-tooltip="ctrans('Already picked: the extra items go on a new order the warehouse sends in the same parcel')"
                            @click="createFollowUpOrder(order)">
                            + {{ ctrans("Follow-up order") }}
                        </button>
                        <button v-if="order.payment_link" type="button" class="font-medium hover:underline disabled:opacity-50"
                            :style="{ color: themePrimary }" :disabled="orderGettingPaymentLink === order.reference"
                            v-tooltip="ctrans('Create a card payment link for what this order still owes and copy it')"
                            @click="createPaymentLink(order)">
                            {{ ctrans("Payment link") }}
                        </button>
                        <span class="ml-auto text-gray-500">{{ formatStatDate(order.date) }}</span>
                        <span class="w-16 text-right font-medium text-gray-800">{{ customerProfile.stats?.currency_symbol ?? '' }}{{ order.total }}</span>
                    </div>
                    <Modal :isOpen="!!orderTakingItems" @onClose="orderTakingItems = null" width="w-full max-w-6xl">
                        <ProductsSelector v-if="orderTakingItems?.add_items"
                            :headLabel="ctrans('Add products to Order') + ' #' + orderTakingItems.reference"
                            :routeFetch="orderTakingItems.add_items.products" :isLoadingSubmit="isAddingItems"
                            withQuantity @submit="addItemsToOrder" />
                    </Modal>
                </div>

                <div v-if="!session.is_guest && customerProfile.subscriptions" class="px-4 py-3 space-y-1.5 text-xs">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">{{ ctrans("Emails we send them") }}</p>
                    <p v-if="!subscribedChannels.length" class="font-medium text-gray-800">{{ ctrans("Unsubscribed from everything") }}</p>
                    <p v-else class="text-gray-700">{{ ctrans("Subscribed to") }}: {{ subscribedChannels.join(", ") }}</p>
                    <p v-if="lastUnsubscribedAt" class="text-gray-500">{{ ctrans("Last unsubscribed") }}: {{ formatStatDate(lastUnsubscribedAt) }}</p>
                    <p class="text-gray-500"
                        :class="!subscribedChannels.length && customerProfile.subscriptions.marketing_emails_last_30_days.length ? 'text-red-600 font-medium' : ''">
                        {{ ctrans("Marketing emails in the last 30 days") }}: {{ customerProfile.subscriptions.marketing_emails_last_30_days.length }}<template v-if="customerProfile.subscriptions.marketing_emails_last_30_days.length">, {{ ctrans("last on") }} {{ formatStatDate(customerProfile.subscriptions.marketing_emails_last_30_days[0].sent_at) }}</template>
                    </p>
                    <button v-if="subscribedChannels.length && customerProfile.subscriptions.unsubscribe" type="button"
                        class="font-medium hover:underline disabled:opacity-50" :style="{ color: themePrimary }"
                        :disabled="isUnsubscribing" @click="unsubscribeFromMarketing">
                        {{ isUnsubscribing ? ctrans("Unsubscribing…") : ctrans("Unsubscribe from everything") }}
                    </button>
                </div>

                <div v-if="!session.is_guest && customerProfile.erasure" class="px-4 py-3 space-y-2 text-xs">
                    <button type="button" class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider hover:text-red-600" @click="openErasure">
                        {{ ctrans("Erase their data (GDPR)") }}
                    </button>
                    <template v-if="erasureOpen">
                        <p v-if="customerProfile.erasure.orders || customerProfile.erasure.invoices" class="rounded bg-amber-50 p-2 text-amber-800">
                            {{ ctrans(":orders orders and :invoices invoices. They stay, as the law requires, but no longer say who the customer was.", { orders: String(customerProfile.erasure.orders), invoices: String(customerProfile.erasure.invoices) }) }}
                        </p>
                        <p class="text-gray-600">{{ ctrans("Erases their name, contact details, addresses, web logins and conversations, and unsubscribes them from everything. It cannot be undone.") }}</p>
                        <p v-if="!customerProfile.erasure.route" class="text-gray-500">
                            {{ customerProfile.erasure.orders || customerProfile.erasure.invoices
                                ? ctrans("A customer with orders can only be erased by a CRM supervisor: ask one to open this conversation.")
                                : ctrans("Erasing needs permission to edit customers: ask a CRM supervisor.") }}
                        </p>
                        <template v-else>
                            <textarea v-model="erasureReason" rows="2" class="w-full rounded border-gray-300 text-xs" :placeholder="ctrans('Why')" />
                            <label class="block text-gray-600">
                                {{ ctrans("Type :text to confirm", { text: customerProfile.erasure.confirmation }) }}
                                <input v-model="erasureConfirmation" type="text" class="mt-1 w-full rounded border-gray-300 text-xs" />
                            </label>
                            <button type="button"
                                class="rounded bg-red-600 px-2 py-1 font-medium text-white disabled:opacity-40"
                                :disabled="isErasing || !erasureReason.trim() || erasureConfirmation !== customerProfile.erasure.confirmation"
                                @click="eraseCustomer">
                                {{ isErasing ? ctrans("Erasing…") : ctrans("Erase their data") }}
                            </button>
                        </template>
                    </template>
                </div>

                <div v-if="customerProfile.previous_chats?.length" class="px-4 py-3 space-y-2">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider flex items-center gap-1.5">
                        {{ ctrans("Previous contact") }}
                        <FontAwesomeIcon :icon="['fal', 'fa-robot']" class="text-gray-300" :title="ctrans('Written by AI from the conversation. Open it to check.')" fixed-width />
                    </p>
                    <p v-if="customerProfile.chat_topics?.length" class="text-xs text-gray-500">
                        {{ ctrans("Last 12 months:") }}
                        <span v-for="(chatTopic, index) in customerProfile.chat_topics" :key="chatTopic.topic">
                            <span class="font-medium text-gray-800">{{ chatTopic.count }}</span> {{ chatTopic.label }}<span v-if="index < customerProfile.chat_topics.length - 1"> · </span>
                        </span>
                    </p>
                    <button v-for="chat in customerProfile.previous_chats" :key="chat.ulid" type="button"
                        class="block w-full text-left text-xs rounded hover:bg-gray-50 -mx-1 px-1 py-0.5"
                        @click="openPreviousChat(chat)">
                        <span class="flex items-center gap-1.5">
                            <FontAwesomeIcon
                                :icon="chat.channel === 'whatsapp' ? faWhatsapp : chat.channel === 'email' ? faEnvelope : faGlobe"
                                class="shrink-0" :class="chat.channel === 'whatsapp' ? 'text-green-500' : 'text-blue-500'" fixed-width />
                            <span class="font-medium text-gray-800 truncate">{{ chat.topic }}</span>
                            <span v-if="chat.status === 'pending'" class="shrink-0 text-amber-600">{{ ctrans("Unresolved") }}</span>
                            <span class="ml-auto shrink-0 text-gray-500">{{ formatStatDate(chat.date) }}</span>
                        </span>
                        <span class="block text-gray-500 line-clamp-2">{{ chat.summary }}</span>
                    </button>
                </div>

                <div class="px-4 py-3 space-y-2.5">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Session</p>
                    <div class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Status</div>
                        <div class="col-span-2">
                            <span class="text-xs font-medium capitalize rounded-full px-2 py-0.5"
                                :class="statusColors[session.status] ?? 'bg-gray-100 text-gray-600'">
                                {{ session.status }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Priority</div>
                        <div class="col-span-2 relative">
                            <div v-if="isPriorityOpen" class="fixed inset-0 z-20" @click="isPriorityOpen = false"></div>
                            <button type="button"
                                class="w-full flex items-center gap-2 rounded-md border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50 disabled:opacity-60"
                                :disabled="isSavingPriority"
                                @click="isPriorityOpen = !isPriorityOpen">
                                <FontAwesomeIcon v-if="currentPriority" :icon="currentPriority.icon" class="text-[11px]" :style="{ color: currentPriority.color }" fixed-width />
                                <span class="font-medium text-gray-800">{{ currentPriority?.label ?? 'Set priority' }}</span>
                                <FontAwesomeIcon :icon="faChevronDown" class="ml-auto text-[9px] text-gray-400" fixed-width />
                            </button>
                            <div v-if="isPriorityOpen"
                                class="absolute right-0 z-30 mt-1 w-40 bg-white border border-gray-200 rounded-md shadow-lg py-1">
                                <button v-for="p in PRIORITIES" :key="p.value" type="button"
                                    class="w-full flex items-center gap-2 px-3 py-1.5 text-xs hover:bg-gray-100"
                                    :class="effectivePriority === p.value ? 'font-semibold text-gray-900' : 'text-gray-700'"
                                    @click="updatePriority(p.value)">
                                    <FontAwesomeIcon :icon="p.icon" class="text-[11px] w-3.5" :style="{ color: p.color }" fixed-width />
                                    <span>{{ p.label }}</span>
                                    <span v-if="effectivePriority === p.value" class="ml-auto text-[11px]" :style="{ color: p.color }">✓</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="currentAgentName || canAssignAgent" class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">{{ ctrans("Agent") }}</div>
                        <div v-if="!canAssignAgent" class="col-span-2 text-xs font-medium text-gray-800">{{ currentAgentName }}</div>
                        <div v-else-if="!isEditingAgent" class="col-span-2">
                            <button type="button"
                                class="w-full text-left text-xs font-medium rounded-md border border-gray-200 px-2 py-1 hover:bg-gray-50"
                                :class="currentAgentName ? 'text-gray-800' : 'text-gray-400'"
                                @click="isEditingAgent = true">
                                {{ currentAgentName || ctrans("Assign to an agent") }}
                            </button>
                        </div>
                        <div v-else class="col-span-2">
                            <SelectQuery :urlRoute="`${baseUrl}/app/api/chats/agents`" :label="'label'"
                                :valueProp="'agent_id'" :object="true" :searchable="true" :closeOnSelect="true"
                                :disabled="isAssigningAgent" :onChange="assignAgent" />
                            <button type="button" class="mt-1 text-[10px] text-gray-500 hover:text-gray-700"
                                :disabled="isAssigningAgent" @click="isEditingAgent = false">
                                {{ ctrans("Cancel") }}
                            </button>
                        </div>
                    </div>
                    <div v-if="session.started" class="grid grid-cols-3 gap-2 items-center">
                        <div class="text-gray-500 text-xs">Started</div>
                        <div class="col-span-2 text-xs font-medium text-gray-800">{{ session.started }}</div>
                    </div>
                </div>

                <div v-if="!session.is_guest" class="px-4 py-3">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2">Tags</p>
                    <div v-if="isLoadingProfile" class="text-xs text-gray-400">Loading...</div>
                    <div v-else-if="customerProfile.tags.length" class="flex flex-wrap gap-1.5">
                        <span v-for="tag in customerProfile.tags" :key="tag.id"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border font-medium bg-indigo-50 text-indigo-700 border-indigo-200">
                            <FontAwesomeIcon :icon="['fal', 'fa-tag']" class="text-[9px] opacity-70" fixed-width />
                            {{ tag.name }}
                        </span>
                    </div>
                    <div v-else class="text-xs text-gray-400 italic">No tags</div>
                </div>

                <div v-if="session.ai_summary?.summary" class="px-4 py-3">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <FontAwesomeIcon :icon="['fal', 'fa-robot']" class="text-indigo-400" fixed-width />
                        AI Summary
                        <span v-if="session.ai_summary.sentiment"
                            class="ml-auto text-[10px] font-medium capitalize px-1.5 py-0.5 rounded-full"
                            :class="{
                                'bg-green-100 text-green-700': session.ai_summary.sentiment === 'positive',
                                'bg-red-100 text-red-600': session.ai_summary.sentiment === 'negative',
                                'bg-gray-100 text-gray-500': session.ai_summary.sentiment === 'neutral',
                            }">
                            {{ session.ai_summary.sentiment }}
                        </span>
                    </p>
                    <p class="text-xs text-gray-700 leading-relaxed">{{ session.ai_summary.summary }}</p>
                    <template v-if="session.ai_summary.key_points?.length">
                        <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide mt-3 mb-1.5">Key Points</p>
                        <ul class="space-y-1">
                            <li v-for="(point, i) in session.ai_summary.key_points" :key="i"
                                class="flex items-start gap-1.5 text-xs text-gray-600">
                                <span class="mt-1.5 w-1 h-1 rounded-full bg-indigo-400 shrink-0"></span>
                                {{ point }}
                            </li>
                        </ul>
                    </template>
                </div>
            </div>

            <!-- Statistics -->
            <div v-if="activeTab === 'statistics'" class="p-4">
                <div v-if="isLoadingProfile" class="flex items-center justify-center py-10 text-gray-400 text-xs">Loading...</div>
                <div v-else-if="!customerProfile.stats" class="flex flex-col items-center justify-center py-10 text-gray-400">
                    <FontAwesomeIcon :icon="['fal', 'fa-chart-line']" class="text-2xl mb-2 opacity-30" fixed-width />
                    <p class="text-xs">No statistics available</p>
                </div>
                <div v-else class="space-y-2.5">
                    <div class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Sales Attributes</div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Last Invoice</span>
                        <span class="text-xs font-medium text-gray-800">{{ formatStatDate(customerProfile.stats.last_invoiced_at) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">First Order</span>
                        <span class="text-xs font-medium text-gray-800">{{ formatStatDate(customerProfile.stats.first_order_date) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Total Orders</span>
                        <span class="text-xs font-semibold text-gray-800">{{ customerProfile.stats.number_orders.toLocaleString() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Total Spend</span>
                        <span class="text-xs font-semibold text-gray-800">{{ formatCurrency(customerProfile.stats.sales_all, customerProfile.stats.currency_symbol) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Avg Order Value</span>
                        <span class="text-xs font-medium text-gray-800">{{ formatCurrency(customerProfile.stats.average_order_value, customerProfile.stats.currency_symbol) }}</span>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div v-if="activeTab === 'timeline'">
                <div v-if="isLoadingTimeline" class="p-4 space-y-2">
                    <div class="h-4 bg-gray-100 rounded animate-pulse w-3/4" />
                    <div class="h-4 bg-gray-100 rounded animate-pulse w-1/2" />
                </div>
                <div v-else-if="timelineError" class="flex flex-col items-center justify-center py-10 gap-2 text-center px-4">
                    <p class="text-xs text-red-500">{{ timelineError }}</p>
                </div>
                <CustomerTimeline v-else :data="timelineData" />
            </div>

            <!-- Tickets -->
            <div v-if="activeTab === 'tickets'" class="p-3">
                <div v-if="isLoadingTickets" class="space-y-2">
                    <div class="h-12 bg-gray-100 rounded animate-pulse" />
                    <div class="h-12 bg-gray-100 rounded animate-pulse w-5/6" />
                </div>
                <div v-else-if="!tickets.length" class="flex flex-col items-center justify-center py-10 text-gray-400">
                    <FontAwesomeIcon :icon="['fal', 'fa-life-ring']" class="text-2xl mb-2 opacity-30" fixed-width />
                    <p class="text-xs">No tickets yet</p>
                </div>
                <ul v-else class="space-y-2">
                    <li v-for="ticket in tickets" :key="ticket.id">
                        <button type="button"
                            class="w-full rounded-lg border border-gray-200 bg-white p-2.5 text-left transition hover:border-gray-300 hover:bg-gray-50"
                            @click="quickLookTicket = ticket">
                            <div class="flex items-center gap-2 text-[11px] text-gray-500">
                                <span class="font-semibold text-gray-700">{{ ticket.reference }}</span>
                                <Icon v-if="ticket.status_icon" :data="ticket.status_icon" />
                                <span>{{ ticket.status_label }}</span>
                                <FontAwesomeIcon v-if="ticket.blocks_source"
                                    :icon="['fal', 'fa-lock']"
                                    v-tooltip="isTicketSettled(ticket)
                                        ? ctrans('This was holding the chat open. It is settled, so it no longer does.')
                                        : ctrans('This chat cannot be closed until this ticket is resolved or cancelled.')"
                                    class="text-[11px]"
                                    :class="isTicketSettled(ticket) ? 'text-gray-300' : 'text-amber-600'" fixed-width />
                                <Icon v-if="ticket.priority_icon" :data="ticket.priority_icon" class="ml-auto" />
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs font-medium text-gray-800">{{ ticket.subject }}</p>
                            <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-400">
                                <span v-if="ticket.kind_label">{{ ticket.kind_label }}</span>
                                <span v-if="ticket.assignee">· {{ ticket.assignee }}</span>
                                <span class="ml-auto">{{ formatStatDate(ticket.created_at) }}</span>
                            </div>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Log -->
            <div v-if="activeTab === 'log'" class="px-4">
                <ChatActivityTimeline :sessionUlid="session.ulid" :baseUrl="baseUrl" :channel="session.channel" />
            </div>

            <!-- History -->
            <div v-if="activeTab === 'history'" class="h-full flex flex-col min-h-0">
                <!-- List of past chat sessions -->
                <template v-if="!selectedHistory">
                    <div v-if="!isLoadingHistory && !historySessions.length"
                        class="flex flex-col items-center justify-center py-10 text-gray-400">
                        <FontAwesomeIcon :icon="['fal', 'fa-robot']" class="text-2xl mb-2 opacity-30" fixed-width />
                        <p class="text-xs">No previous chats</p>
                    </div>
                    <HistoryChatList v-else :data="historySessions" :loading="isLoadingHistory"
                        :loading-more="isLoadingMoreHistory" :has-more="historyHasMore"
                        :show-ai-summary="true" @click-session="selectedHistory = $event"
                        @load-more="loadHistory(true)" />
                </template>

                <template v-else>
                    <MessageHistory class="flex-1 min-h-0" :sessionUlid="selectedHistory.ulid"
                        :session="selectedHistory" viewerType="agent"
                        :channel="selectedHistory.channel ?? session.channel" @back="selectedHistory = null" />
                </template>
            </div>
        </div>
        <TicketQuickLook v-model:ticket="quickLookTicket" @closed="quickLookTicket = null" />
</div>
</template>
