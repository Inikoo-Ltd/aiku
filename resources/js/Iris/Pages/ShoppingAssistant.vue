<script setup lang="ts">
import AssistantIcon from "@/Iris/Components/AssistantIcon.vue"
import MerlinThinking from "@/Iris/Components/MerlinThinking.vue"
import MerlinImagePicker from "@/Components/Chat/Customer/MerlinImagePicker.vue"
import CustomerClaimForm, { type ClaimSummary } from "@/Components/Chat/Customer/CustomerClaimForm.vue"
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import { router, usePage } from "@inertiajs/vue3"
import { get } from "lodash-es"
import Textarea from "primevue/textarea"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import TabPanels from "primevue/tabpanels"
import TabPanel from "primevue/tabpanel"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperPlane, faSignInAlt, faCommentAlt, faRedo, faShoppingBasket, faCheck, faPlus, faHistory, faTimes, faBars, faColumns, faEdit, faHeadset, faArrowRight } from "@far"
import { faThumbsUp, faThumbsDown } from "@far"
import { faThumbsUp as fasThumbsUp, faThumbsDown as fasThumbsDown } from "@fas"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { useLocaleStore } from "@/Stores/locale"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import RangeAdvisorProductCard from "@/Iris/Components/RangeAdvisorProductCard.vue"
import RenderProductDropshiping from "@/Iris/Components/IrisBlocks/Products/ds/RenderProductDropshiping.vue"
import RangeAdvisorPanel from "@/Iris/Components/RangeAdvisorPanel.vue"
import { ProductResource } from "@/types/Iris/Products"
import {
    shoppingAssistantConversationKey,
    shoppingAssistantQuestionKey,
    shoppingAssistantPageKey,
    shoppingAssistantTrail,
    useShoppingAssistant,
    askPayload,
} from "@/Iris/Composables/useShoppingAssistant"
library.add(faPaperPlane, faSignInAlt, faCommentAlt, faRedo, faShoppingBasket, faCheck, faPlus, faHistory, faTimes, faBars, faColumns, faEdit, faHeadset, faArrowRight, faThumbsUp, faThumbsDown, fasThumbsUp, fasThumbsDown)

type Card = ProductResource & {
    assistant_reason: string
    assistant_message_id: number
    portfolio_channels?: number[]
}

type Message =
    | { id: number, role: "customer", body: string, created_at: string, images?: { name: string, url: string }[] }
    | {
        id: number
        role: "assistant"
        body: string
        created_at: string
        follow_ups: string[]
        basket_added?: number[]
        links?: { label: string, url: string }[]
        hand_over_offer?: string | null
        handed_over?: boolean
        feedback?: "up" | "down" | null
        groups: { must_haves: Card[], add_ons: Card[] }
        claim?: { order_id: number, reference: string, made: ClaimSummary | null } | null
    }

type AssistantMessage = Extract<Message, { role: "assistant" }>

const props = defineProps<{
    web_block_family?: {
        fieldValue?: Record<string, any>
    }
    web_block_family_code?: string
    products_in_range?: number | null
    assistant_name?: string
}>()

const fieldValue = computed(() => props.web_block_family?.fieldValue)
const productCardCode = computed(() => props.web_block_family_code || "products-1")

const layout: any = inject("layout", retinaLayoutStructure)
const isDropshipping = computed(() => layout?.retina?.type === "dropshipping")
const screenType = inject("screenType", ref<"mobile" | "tablet" | "desktop">("desktop"))

const { assistant } = useShoppingAssistant()

const locale = useLocaleStore()

const assistantName = computed(() => assistant.value?.name || props.assistant_name || ctrans("Range advisor"))

const isMounted = ref(false)
const isLayoutReady = computed(() => isMounted.value && !layout.iris_varnish?.isFetching)
const isLoggedOut = computed(() => isLayoutReady.value && !layout.iris?.is_logged_in)
const showsRangePanel = computed(() => isLayoutReady.value && !!layout.iris?.is_logged_in && !isDropshipping.value)

const conversation = ref<string | null>(null)
const messages = ref<Message[]>([])
const isPending = ref(false)
const isRestoring = ref(false)
const errorMessage = ref("")
const draft = ref("")
const images = ref<File[]>([])
const picker = ref<InstanceType<typeof MerlinImagePicker>>()
const notice = ref("")
const cardsVersion = ref(0)
const addingTo = ref<number | null>(null)
const addErrors = ref<Record<number, string>>({})
const handOverAvailable = ref(false)
const isTalkBoxOpen = ref(false)
const talkDraft = ref("")
const talkError = ref("")
const handingOver = ref<number | "link" | null>(null)
const handOverErrors = ref<Record<number, string>>({})

const hasForgottenProducts = ref(false)
const rangePanelHasProducts = ref(true)

const focusLandingTextarea = () => {
    const textarea = document.querySelector<HTMLTextAreaElement>(".landing-textarea")
    textarea?.scrollIntoView({ behavior: "smooth", block: "center" })
    textarea?.focus({ preventScroll: true })
}

const canTalkToAPerson = computed(() => !!assistant.value?.hand_over || handOverAvailable.value)

const showLanding = computed(() => !messages.value.length && !isPending.value && !isRestoring.value)
const isLandingVisible = computed(() => showLanding.value && !!assistant.value)
const isChatVisible = computed(() => !isLandingVisible.value && (!!assistant.value || messages.value.length > 0 || isPending.value))
const isMerlin = computed(() => assistantName.value.trim().toLowerCase() === "merlin")
const isLittleBird = computed(() => assistantName.value.trim().toLowerCase() === "little bird")

const knowsTheRange = computed(() => isDropshipping.value
    ? (props.products_in_range
        ? ctrans(":name knows all :count+ products you can sell from us and what sells best for online sellers like you.", {
            name: assistantName.value,
            count: locale.number(props.products_in_range),
        })
        : ctrans(":name knows every product you can sell from us and what sells best for online sellers like you.", { name: assistantName.value }))
    : (props.products_in_range
        ? ctrans(":name knows all :count+ products in our range and what shops like yours buy most.", {
            name: assistantName.value,
            count: locale.number(props.products_in_range),
        })
        : ctrans(":name knows every product in our range and what shops like yours buy most.", { name: assistantName.value })))



const landingIntro = computed(() => isDropshipping.value
    ? ctrans(":name is your dropshipping coach. Ask in your own words and your own language: how to start, how to connect your Shopify, eBay, WooCommerce, TikTok Shop or Wix store, which products to list, how to price them, and how orders and delivery to your buyers work. When :name is not sure, one of our experts answers.", { name: assistantName.value })
    : ctrans(":name is a new way to work with us. Ask in your own words and your own language: what sells for shops like yours, a product, offers, delivery, samples or product documents. When :name is not sure, one of our experts answers.", { name: assistantName.value }))

const PLATFORM_NAMES: Record<string, string> = { shopify: "Shopify", ebay: "eBay", woocommerce: "WooCommerce", tiktok: "TikTok Shop", wix: "Wix", amazon: "Amazon", magento: "Magento", allegro: "Allegro" }
const NEXT_PLATFORMS = ["shopify", "tiktok", "ebay", "woocommerce"]

const connectedPlatforms = computed<string[]>(() => {
    const channels = layout?.user?.customerSalesChannels ?? (usePage().props as any)?.auth?.customerSalesChannels ?? {}
    return [...new Set(Object.values(channels).map((channel: any) => channel?.platform_slug).filter((slug: string) => slug && PLATFORM_NAMES[slug]))] as string[]
})

const nextPlatform = computed(() => NEXT_PLATFORMS.find((slug) => !connectedPlatforms.value.includes(slug)) ?? null)

type StarterQuestion = { icon: string, title: string, subtitle?: string, question: string }

const asStarter = (question: string, index: number): StarterQuestion => ({ icon: index % 2 ? "✧" : "✦", title: question, question })

const littleBirdStarterQuestions = (): StarterQuestion[] => [
    ctrans("What are the best-selling Agnes+Cat candles?"),
    ctrans("Which products work well for gift bundles?"),
    ctrans("Suggest a calming fragrance range for my shop"),
    ctrans("What should I pair with bath and body products?"),
].map(asStarter)

const starterQuestions = computed<StarterQuestion[]>(() => isLittleBird.value ? littleBirdStarterQuestions() : isDropshipping.value ? (connectedPlatforms.value.length ? [
    ...connectedPlatforms.value.slice(0, 2).map((slug) => ctrans("Best sellers to add to my :platform store", { platform: PLATFORM_NAMES[slug] })),
    ctrans("How do I get more sales on :platform?", { platform: PLATFORM_NAMES[connectedPlatforms.value[0]] }),
    ...(nextPlatform.value ? [ctrans("Should I start selling on :platform too?", { platform: PLATFORM_NAMES[nextPlatform.value] })] : []),
    ctrans("How should I set my selling prices?"),
    ctrans("How fast do you send orders to my buyers?"),
].map(asStarter) : [
    ctrans("I am new to dropshipping – how do I start?"),
    ctrans("How do I connect my Shopify store?"),
    ctrans("Best sellers to list on eBay"),
    ctrans("How should I set my selling prices?"),
    ctrans("What sells well for a crystal store?"),
    ctrans("How fast do you send orders to my buyers?"),
].map(asStarter)) : [
    {
        icon: "✦",
        title: ctrans("I run a crystal shop – what am I missing?"),
        subtitle: ctrans("Find complementary products your customers might love"),
        question: ctrans("I run a crystal shop – what am I missing?"),
    },
    {
        icon: "▣",
        title: ctrans("What goes well with crystals?"),
        subtitle: ctrans("Ideas for cross-selling and building collections"),
        question: ctrans("What products would pair well with crystals and incense?"),
    },
    {
        icon: "✧",
        title: ctrans("How can I refresh my displays?"),
        subtitle: ctrans("Simple merchandising ideas for independent shops"),
        question: ctrans("How can I refresh my shop display for the next season?"),
    },
    {
        icon: "?",
        title: ctrans("I’m opening a gift shop. What should I stock?"),
        subtitle: ctrans("Build a thoughtful first wholesale range"),
        question: ctrans("I’m opening a gift shop – what should I stock first?"),
    },
])



const latestAssistantId = computed(() => [...messages.value].reverse().find((message) => message.role === "assistant")?.id ?? null)

const groupTabs = (message: AssistantMessage) => [
    { key: "must_haves", label: ctrans("Must-haves"), cards: message.groups?.must_haves ?? [] },
    { key: "add_ons", label: ctrans("Add-ons to your range"), cards: message.groups?.add_ons ?? [] },
].filter((tab) => tab.cards.length)

const jsonUrl = (name: string, params: Record<string, string>, fallback: string): string =>
    route().has(name) ? route(name, params) : fallback

const askUrl = () => jsonUrl("iris.json.shopping_assistant.ask", {}, "/json/shopping-assistant/ask")
const historyUrl = () => jsonUrl("iris.json.shopping_assistant.index", {}, "/json/shopping-assistant/conversations")
const showUrl = (id: string) => jsonUrl("iris.json.shopping_assistant.show", { conversation: id }, `/json/shopping-assistant/${id}`)
const handOverUrl = () => jsonUrl("iris.json.shopping_assistant.hand_over", {}, "/json/shopping-assistant/hand-over")
const eventsUrl = (id: string) => jsonUrl("iris.json.shopping_assistant.events.store", { conversation: id }, `/json/shopping-assistant/${id}/events`)
const feedbackUrl = (id: string) => jsonUrl("iris.json.shopping_assistant.feedback.store", { conversation: id }, `/json/shopping-assistant/${id}/feedback`)

const feedbackErrors = ref<Record<number, string>>({})

const setFeedback = async (message: AssistantMessage, value: "up" | "down") => {
    if (!conversation.value) {
        return
    }
    const previous = message.feedback ?? null
    const next = previous === value ? null : value
    const apply = (feedback: "up" | "down" | null) => {
        messages.value = messages.value.map((item) => item.id === message.id && item.role === "assistant" ? { ...item, feedback } : item)
    }
    apply(next)
    feedbackErrors.value = { ...feedbackErrors.value, [message.id]: "" }
    try {
        await axios.post(feedbackUrl(conversation.value), { message_id: message.id, feedback: next })
    } catch {
        apply(previous)
        feedbackErrors.value = { ...feedbackErrors.value, [message.id]: ctrans("Your feedback could not be saved, please try again.") }
    }
}

const POLL_INTERVAL_MS = 1500
const POLL_LIMIT_MS = 150_000
let pollTimer: ReturnType<typeof setTimeout> | null = null
let generation = 0

const TEAM_POLL_INTERVAL_MS = 8000
let teamTimer: ReturnType<typeof setTimeout> | null = null

const stopPolling = () => {
    if (pollTimer !== null) {
        clearTimeout(pollTimer)
        pollTimer = null
    }
    if (teamTimer !== null) {
        clearTimeout(teamTimer)
        teamTimer = null
    }
}

const waitForTeam = () => {
    if (teamTimer !== null) {
        return
    }
    const teamGeneration = generation
    teamTimer = setTimeout(async () => {
        teamTimer = null
        if (teamGeneration !== generation) {
            return
        }
        if (pollTimer === null) {
            await fetchConversation(lastMessageId()).catch(() => null)
        } else {
            waitForTeam()
        }
    }, TEAM_POLL_INTERVAL_MS)
}

const forgetConversation = () => {
    generation++
    stopPolling()
    sessionStorage.removeItem(shoppingAssistantConversationKey)
    conversation.value = null
    messages.value = []
    isPending.value = false
    errorMessage.value = ""
}

const fetchConversation = async (after?: number): Promise<{ pending: boolean, hasNewAnswer: boolean } | null> => {
    const requestGeneration = generation
    const id = conversation.value
    if (!id) {
        return null
    }
    const { data } = await axios.get(showUrl(id), { params: after ? { after } : {} })
    if (requestGeneration !== generation) {
        return null
    }
    if (data.status === "cleared") {
        forgetConversation()
        notice.value = ctrans("This conversation has been cleared and will not be shown to you again.")
        return null
    }
    handOverAvailable.value = !!data.hand_over_available
    const known = new Set(messages.value.map((message) => message.id))
    const incoming: Message[] = (data.messages ?? []).filter((message: Message) => !known.has(message.id))
    messages.value = [...messages.value, ...incoming].sort((first, second) => first.id - second.id)
    if (after && incoming.some((message) => message.role === "assistant" && message.basket_added?.length)) {
        await refreshBasket()
    }

    if (data.awaiting_team) {
        waitForTeam()
    }

    return { pending: !!data.pending, hasNewAnswer: incoming.some((message) => message.role === "assistant") }
}

const refreshBasket = async () => {
    const id = conversation.value
    if (!id) {
        return
    }
    const requestGeneration = generation
    layout?.reload_handle?.()
    const { data } = await axios.get(showUrl(id))
    if (requestGeneration !== generation) {
        return
    }
    messages.value = data.messages ?? []
    cardsVersion.value++
}

const notInBasket = (message: AssistantMessage): Card[] =>
    [...(message.groups?.must_haves ?? []), ...(message.groups?.add_ons ?? [])].filter((card: any) => !card.quantity_ordered)

const BASKET_LINES_PER_REQUEST = 20

const addAllToBasket = async (message: AssistantMessage) => {
    const cards = notInBasket(message)
    if (!cards.length || addingTo.value !== null) {
        return
    }
    addingTo.value = message.id
    addErrors.value = { ...addErrors.value, [message.id]: "" }
    try {
        for (let start = 0; start < cards.length; start += BASKET_LINES_PER_REQUEST) {
            const { data } = await axios.post(route("iris.models.transactions.store"), {
                lines: cards.slice(start, start + BASKET_LINES_PER_REQUEST).map((card) => ({ product_id: card.id, quantity: 1 })),
            })
            if (data.failed) {
                addErrors.value = { ...addErrors.value, [message.id]: ctrans("Some products could not be added to your basket.") }
            }
        }
    } catch {
        addErrors.value = { ...addErrors.value, [message.id]: ctrans("Could not add the products. Please try again.") }
    } finally {
        await refreshBasket().catch(() => {})
        addingTo.value = null
    }
}

const lastMessageId = () => messages.value[messages.value.length - 1]?.id

const startPolling = () => {
    stopPolling()
    isPending.value = true
    const pollGeneration = generation
    const deadline = Date.now() + POLL_LIMIT_MS

    const tick = async () => {
        if (pollGeneration !== generation) {
            return
        }
        if (Date.now() > deadline) {
            isPending.value = false
            errorMessage.value = ctrans("No answer came back in time. Please try again.")
            return
        }
        try {
            const result = await fetchConversation(lastMessageId())
            if (pollGeneration !== generation) {
                return
            }
            if (result && (!result.pending || result.hasNewAnswer)) {
                isPending.value = false
                return
            }
        } catch (error: any) {
            if (pollGeneration !== generation) {
                return
            }
            if (error?.response?.status === 404) {
                forgetConversation()
                errorMessage.value = ctrans("Something went wrong. Please try again.")
                return
            }
        }
        pollTimer = setTimeout(tick, POLL_INTERVAL_MS)
    }

    pollTimer = setTimeout(tick, POLL_INTERVAL_MS)
}

type HistoryItem = { conversation: string, title: string, last_message_at: string | null }

const history = ref<HistoryItem[]>([])
const isHistoryLoading = ref(false)
const isHistoryOpen = ref(false)
const isSidebarCollapsed = ref(false)
const sidebarCollapsedKey = "shopping_assistant_sidebar_collapsed"

const landingHistory = computed(() => history.value.slice(0, 5))

const toggleHistory = () => {
    if (window.matchMedia("(min-width: 1024px)").matches) {
        isSidebarCollapsed.value = !isSidebarCollapsed.value
        try {
            localStorage.setItem(sidebarCollapsedKey, isSidebarCollapsed.value ? "1" : "0")
        } catch {
            return
        }
        return
    }
    isHistoryOpen.value = !isHistoryOpen.value
}
const chatMessages = ref<HTMLElement | null>(null)

const loadHistory = async () => {
    isHistoryLoading.value = true
    try {
        const { data } = await axios.get(historyUrl())
        history.value = data.conversations ?? []
    } catch {
        history.value = []
    } finally {
        isHistoryLoading.value = false
    }
}

const historyDate = (value: string | null): string => {
    if (!value) {
        return ""
    }
    const date = new Date(value)
    const isToday = date.toDateString() === new Date().toDateString()
    return isToday
        ? date.toLocaleTimeString(locale.locale_iso || undefined, { hour: "2-digit", minute: "2-digit" })
        : date.toLocaleDateString(locale.locale_iso || undefined, { day: "numeric", month: "short" })
}

const openConversation = async (id: string) => {
    isHistoryOpen.value = false
    if (id === conversation.value) {
        return
    }
    forgetConversation()
    notice.value = ""
    conversation.value = id
    sessionStorage.setItem(shoppingAssistantConversationKey, id)
    isRestoring.value = true
    try {
        const result = await fetchConversation()
        if (result?.pending) {
            startPolling()
        }
    } catch (error: any) {
        if (error?.response?.status === 404) {
            forgetConversation()
            history.value = history.value.filter((item) => item.conversation !== id)
        }
    } finally {
        isRestoring.value = false
    }
}

const startNewConversation = () => {
    isHistoryOpen.value = false
    notice.value = ""
    forgetConversation()
}

const sendChatOnEnter = (event: KeyboardEvent) => {
    if (event.shiftKey || event.isComposing) {
        return
    }
    event.preventDefault()
    send(draft.value)
}

watch(() => messages.value.length + (isPending.value ? 1 : 0), async (count, previous) => {
    if (count > (previous ?? 0)) {
        await nextTick()
        chatMessages.value?.scrollTo({ top: chatMessages.value.scrollHeight, behavior: "smooth" })
    }
})

const send = async (text: string, page?: string | null) => {
    const message = text.trim().slice(0, 1000)
    const sending = [...images.value]
    if ((!message && !sending.length) || isPending.value) {
        return
    }
    errorMessage.value = ""
    notice.value = ""
    isPending.value = true
    const requestGeneration = generation
    try {
        const { data } = await axios.post(askUrl(), askPayload({ message, conversation: conversation.value, can_hand_over: true, page, trail: shoppingAssistantTrail() }, sending))
        if (requestGeneration !== generation) {
            return
        }
        if (data.accept_offer) {
            isPending.value = false
            draft.value = ""
            await handOver(data.accept_offer)
            return
        }
        const isNewConversation = conversation.value !== data.conversation
        conversation.value = data.conversation
        sessionStorage.setItem(shoppingAssistantConversationKey, data.conversation)
        if (isNewConversation) {
            loadHistory()
        }
        if (!messages.value.some((held) => held.id === data.message_id)) {
            messages.value.push({ id: data.message_id, role: "customer", body: message, created_at: "", images: sending.map((file) => ({ name: file.name, url: URL.createObjectURL(file) })) })
        }
        draft.value = ""
        images.value = []
        startPolling()
    } catch (error: any) {
        if (requestGeneration !== generation) {
            return
        }
        const status = error?.response?.status
        if (status === 409 && conversation.value) {
            startPolling()
            return
        }
        isPending.value = false
        errorMessage.value = status === 429
            ? ctrans("You have asked a lot in a short time. Please try again in a minute.")
            : status === 404
                ? ctrans("The range advisor is not available on this website.")
                : ctrans("Something went wrong. Please try again.")
    }
}

const openChatWindow = (chatSession: string) => {
    if ((window as any).irisChatWidgetMounted === true) {
        window.dispatchEvent(new CustomEvent("iris-open-chat", { detail: { ulid: chatSession } }))
        return
    }
    window.location.assign(window.location.pathname + "?chat_session=" + chatSession)
}

const rememberChatForLater = (chatSession: string) => {
    try {
        localStorage.setItem("chat", JSON.stringify({ ulid: chatSession, saved_at: new Date().toISOString() }))
    } catch {
        return
    }
}

const handOver = async (offerMessageId: number | null, typed?: string) => {
    if (handingOver.value !== null) {
        return
    }
    const target: number | "link" = offerMessageId ?? "link"
    const question = (typed ?? "").trim().slice(0, 1000)
    if (offerMessageId === null && !question) {
        talkError.value = ctrans("Please write your question for our team.")
        return
    }
    talkError.value = ""
    handOverErrors.value = { ...handOverErrors.value, [offerMessageId ?? 0]: "" }
    handingOver.value = target
    const requestGeneration = generation
    try {
        const { data } = await axios.post(handOverUrl(), offerMessageId !== null
            ? { conversation: conversation.value, offer_message_id: offerMessageId }
            : { conversation: conversation.value, message: question })
        if (requestGeneration !== generation) {
            return
        }
        const isNewConversation = conversation.value !== data.conversation
        conversation.value = data.conversation
        sessionStorage.setItem(shoppingAssistantConversationKey, data.conversation)
        if (isNewConversation) {
            loadHistory()
        }
        if (offerMessageId !== null) {
            messages.value = messages.value.map((message) => message.id === offerMessageId && message.role === "assistant" ? { ...message, handed_over: true } : message)
        } else {
            isTalkBoxOpen.value = false
            talkDraft.value = ""
        }
        await fetchConversation(lastMessageId())
        if (data.route === "live_chat" && data.chat_session) {
            openChatWindow(data.chat_session)
        } else if (data.route === "web_chat" && data.chat_session) {
            rememberChatForLater(data.chat_session)
        }
    } catch (error: any) {
        if (requestGeneration !== generation) {
            return
        }
        const message = ctrans("Sorry, that did not work. Please try again.")
        if (offerMessageId !== null) {
            handOverErrors.value = { ...handOverErrors.value, [offerMessageId]: message }
        } else {
            talkError.value = message
        }
    } finally {
        handingOver.value = null
    }
}

const conversationFromLink = (): string | null => {
    const fromLink = new URLSearchParams(window.location.search).get("conversation")
    return fromLink && /^[0-9A-Za-z]{26}$/.test(fromLink) ? fromLink : null
}

const restoreThenAsk = async () => {
    const linked = conversationFromLink()
    if (linked) {
        sessionStorage.setItem(shoppingAssistantConversationKey, linked)
    }
    const savedConversation = sessionStorage.getItem(shoppingAssistantConversationKey)
    if (savedConversation) {
        conversation.value = savedConversation
        isRestoring.value = true
        try {
            const result = await fetchConversation()
            if (result?.pending) {
                startPolling()
            }
        } catch (error: any) {
            if (error?.response?.status === 404) {
                forgetConversation()
            }
        } finally {
            isRestoring.value = false
        }
    }

    askHandedOverQuestion()
}

const askHandedOverQuestion = () => {
    const question = sessionStorage.getItem(shoppingAssistantQuestionKey)
    if (!question) {
        return
    }
    sessionStorage.removeItem(shoppingAssistantQuestionKey)
    const page = sessionStorage.getItem(shoppingAssistantPageKey)
    sessionStorage.removeItem(shoppingAssistantPageKey)
    draft.value = question
    if (!isPending.value) {
        send(question, page)
    }
}

let hasStarted = false
const startOnce = () => {
    if (hasStarted || !assistant.value) {
        return
    }
    hasStarted = true
    loadHistory()
    restoreThenAsk()
}

watch(assistant, startOnce)

let stopListeningToVisits: (() => void) | null = null
let headerObserver: ResizeObserver | null = null

const followHeaderHeight = () => {
    const header = document.querySelector<HTMLElement>("[data-iris-header]")
    if (!header || typeof ResizeObserver === "undefined") {
        return
    }
    headerObserver = new ResizeObserver(() => document.documentElement.style.setProperty("--iris-header-height", `${header.offsetHeight}px`))
    headerObserver.observe(header)
}

onMounted(() => {
    isMounted.value = true
    followHeaderHeight()
    try {
        isSidebarCollapsed.value = localStorage.getItem(sidebarCollapsedKey) === "1"
    } catch {
        isSidebarCollapsed.value = false
    }
    startOnce()
    stopListeningToVisits = router.on("success", () => {
        if (hasStarted) {
            askHandedOverQuestion()
        }
    })
})

onBeforeUnmount(() => {
    generation++
    stopPolling()
    stopListeningToVisits?.()
    headerObserver?.disconnect()
})

const recordClick = (event: MouseEvent, card: Card) => {
    const target = event.target as HTMLElement | null
    if (!conversation.value || !target?.closest("a") || target.closest("button")) {
        return
    }
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? "")
    fetch(eventsUrl(conversation.value), {
        method: "POST",
        keepalive: true,
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-XSRF-TOKEN": token },
        body: JSON.stringify({ type: "clicked", product_id: card.id, message_id: card.assistant_message_id }),
    }).catch(() => {})
}

const goToLogin = () => {
    window.location.href = urlLoginWithRedirect()
}

watch(isLoggedOut, (loggedOut) => {
    if (loggedOut) {
        window.location.replace(urlLoginWithRedirect())
    }
}, { immediate: true })

const askFromLanding = (text: string) => {
    const question = text.trim().slice(0, 1000)
    if (assistant.value) {
        send(question)
        return
    }
    if (question) {
        sessionStorage.setItem(shoppingAssistantQuestionKey, question)
    }
    goToLogin()
}

const sendOnEnter = (event: KeyboardEvent) => {
    if (event.shiftKey || event.isComposing) {
        return
    }
    event.preventDefault()
    askFromLanding(draft.value)
}
</script>

<template>
    <div :class="isLandingVisible || isChatVisible ? 'landing-page' : 'assistant-page px-4 py-8'">
        <div v-if="isLandingVisible">
            <section v-if="isLittleBird" class="bird-landing">
                <div class="bird-wrap">
                    <div v-if="canTalkToAPerson" class="bird-topbar">
                        <button type="button" class="bird-talk" :aria-expanded="isTalkBoxOpen" @click="isTalkBoxOpen = !isTalkBoxOpen">
                            <FontAwesomeIcon icon="far fa-headset" fixed-width aria-hidden="true" />
                            {{ ctrans("Talk to a person") }}
                        </button>
                    </div>

                    <div v-if="isTalkBoxOpen && canTalkToAPerson" class="bird-card bird-talk-box">
                        <label for="assistant-talk-to-a-person-bird" class="mb-1 block text-sm font-medium">
                            {{ ctrans("What would you like to ask our team?") }} <span class="text-red-500">*</span>
                        </label>
                        <Textarea id="assistant-talk-to-a-person-bird" v-model="talkDraft" rows="3" autoResize maxlength="1000" class="w-full" />
                        <p v-if="talkError" class="mt-1 text-xs text-red-500">{{ talkError }}</p>
                        <div class="mt-3 flex w-full gap-2">
                            <Button
                                type="tertiary"
                                class="shrink-0"
                                :label="ctrans('Cancel')"
                                :disabled="handingOver !== null"
                                @click="isTalkBoxOpen = false; talkError = ''"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="far fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button
                                type="primary"
                                full
                                :label="ctrans('Send to our team')"
                                :loading="handingOver === 'link'"
                                :disabled="handingOver !== null"
                                @click="handOver(null, talkDraft)"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="far fa-paper-plane" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </div>
                    </div>

                    <div class="bird-hero">
                        <AssistantIcon littleBird class="bird-mark" />
                        <p class="bird-eyebrow">{{ ctrans("Your AI shopping assistant") }}</p>
                        <h1 class="bird-title">
                            {{ ctrans("Ask :name", { name: assistantName }) }}
                            <span class="bird-beta" :title="ctrans(':name is new and still learning', { name: assistantName })">{{ ctrans("Beta") }}</span>
                        </h1>
                        <p class="bird-lead">{{ knowsTheRange }}</p>
                    </div>

                    <div class="bird-search">
                        <div class="bird-search-row">
                            <Textarea
                                v-model="draft"
                                rows="1"
                                auto-resize
                                maxlength="1000"
                                class="landing-textarea bird-search-input"
                                :aria-label="ctrans('Ask :name', { name: assistantName })"
                                :placeholder="ctrans('Ask :name...', { name: assistantName })"
                                @keydown.enter="sendOnEnter"
                            />
                            <button
                                type="button"
                                class="bird-send"
                                :disabled="!draft.trim()"
                                :title="ctrans('Ask :name', { name: assistantName })"
                                :aria-label="ctrans('Ask :name', { name: assistantName })"
                                @click="askFromLanding(draft)"
                            >
                                <FontAwesomeIcon icon="far fa-arrow-right" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                        <div class="bird-chips">
                            <button
                                v-for="starter in starterQuestions"
                                :key="starter.question"
                                type="button"
                                class="bird-chip"
                                @click="askFromLanding(starter.question)"
                            >
                                {{ starter.title }}
                            </button>
                        </div>
                    </div>
                    <p v-if="errorMessage" class="bird-error">{{ errorMessage }}</p>
                    <p v-if="notice" class="bird-notice">{{ notice }}</p>

                    <div class="bird-grid" :class="{ 'bird-grid-with-panel': showsRangePanel && rangePanelHasProducts }">
                        <section class="bird-card bird-history" :aria-label="ctrans('Recent conversations')">
                            <h2 class="bird-card-title">{{ ctrans("Recent conversations") }}</h2>
                            <div v-if="isHistoryLoading && !history.length" class="flex flex-col gap-3">
                                <div v-for="index in 3" :key="index" class="skeleton h-14 rounded-2xl" />
                            </div>
                            <p v-else-if="!history.length" class="bird-history-empty">{{ ctrans("Your conversations will show here.") }}</p>
                            <nav v-else class="bird-history-list">
                                <button
                                    v-for="item in landingHistory"
                                    :key="item.conversation"
                                    type="button"
                                    class="bird-history-item"
                                    @click="openConversation(item.conversation)"
                                >
                                    <span class="bird-history-text">{{ item.title }}</span>
                                    <span class="bird-history-date">{{ historyDate(item.last_message_at) }}</span>
                                </button>
                            </nav>
                        </section>

                        <RangeAdvisorPanel
                            v-if="showsRangePanel"
                            littleBird
                            :assistantName
                            :fieldValue
                            :screenType
                            @loaded="(hasProducts, hasForgotten) => { rangePanelHasProducts = hasProducts; hasForgottenProducts = hasForgotten }"
                            @tellShop="focusLandingTextarea"
                        />
                    </div>

                    <p class="bird-note">{{ ctrans(":name is an AI assistant and can make mistakes. Our staff may read these conversations to improve our service; please do not share payment details.", { name: assistantName }) }}</p>
                </div>
            </section>

            <template v-else>
            <section class="merlin-landing antialiased" :style="{ fontFamily: layout?.app?.webpage_layout?.container?.properties?.text?.fontFamily }">
                <div class="merlin-wrap">
                    <div class="merlin-columns">
                    <div class="merlin-main">
                    <div class="merlin-form" @dragover.prevent @drop="picker?.onDrop($event)">
                        <Textarea
                            v-model="draft"
                            rows="5"
                            auto-resize
                            maxlength="1000"
                            class="landing-textarea merlin-textarea"
                            :aria-label="ctrans('Ask :name', { name: assistantName })"
                            :placeholder="isDropshipping && connectedPlatforms.length
                                ? ctrans('e.g. What should I add to my :platform store for Christmas, and how do I get more sales?', { platform: PLATFORM_NAMES[connectedPlatforms[0]] })
                                : isDropshipping
                                ? ctrans('e.g. I’m starting a Shopify store for crystals. What should I list first, and how do I connect my store?')
                                : ctrans('Ask :name anything...', { name: assistantName })"
                            @keydown.enter="sendOnEnter"
                            @paste="picker?.onPaste($event)"
                        />
                        <div class="merlin-form-actions">
                            <MerlinImagePicker v-if="assistant" ref="picker" v-model="images" :disabled="isPending" class="min-w-0 pl-1" />
                            <span v-else class="merlin-helper">✦ {{ ctrans("Your next great idea starts here") }}</span>
                            <button
                                type="button"
                                class="merlin-send"
                                :disabled="!draft.trim() && !images.length"
                                :title="ctrans('Ask :name', { name: assistantName })"
                                :aria-label="ctrans('Ask :name', { name: assistantName })"
                                @click="askFromLanding(draft)"
                            >
                                <FontAwesomeIcon icon="far fa-paper-plane" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </div>
                    <p v-if="errorMessage" class="merlin-error">{{ errorMessage }}</p>
                    <p v-if="notice" class="merlin-notice">{{ notice }}</p>
                    <p class="merlin-note">{{ ctrans(":name is an AI assistant and can make mistakes. Our staff may read these conversations to improve our service; please do not share payment details.", { name: assistantName }) }}</p>

                    <div class="merlin-suggest">
                        <p class="merlin-caption">{{ ctrans("Not sure where to start? Try asking me...") }}</p>
                        <div class="merlin-chips">
                            <button
                                v-for="starter in starterQuestions"
                                :key="starter.question"
                                type="button"
                                class="merlin-chip"
                                @click="askFromLanding(starter.question)"
                            >
                                {{ starter.title }}
                            </button>
                        </div>
                    </div>

                    <aside v-if="canTalkToAPerson" class="merlin-human" :aria-label="ctrans('Talk to a person')">
                        <div class="merlin-human-row">
                            <div>
                                <h3 class="merlin-human-title">{{ ctrans("Prefer to talk to a person?") }}</h3>
                                <p class="merlin-human-text">{{ ctrans("Our friendly customer service team is here to help.") }}</p>
                            </div>
                            <button
                                type="button"
                                class="merlin-human-link"
                                :aria-expanded="isTalkBoxOpen"
                                @click="isTalkBoxOpen = !isTalkBoxOpen"
                            >
                                <FontAwesomeIcon icon="far fa-headset" fixed-width aria-hidden="true" />
                                {{ ctrans("Talk to customer service") }}
                            </button>
                        </div>

                        <div v-if="isTalkBoxOpen" class="chat-talk-box merlin-talk-box">
                            <label for="assistant-talk-to-a-person-landing" class="mb-1 block text-sm font-medium">
                                {{ ctrans("What would you like to ask our team?") }} <span class="text-red-500">*</span>
                            </label>
                            <Textarea id="assistant-talk-to-a-person-landing" v-model="talkDraft" rows="3" autoResize maxlength="1000" class="w-full" />
                            <p v-if="talkError" class="mt-1 text-xs text-red-500">{{ talkError }}</p>
                            <div class="mt-3 flex w-full gap-2">
                                <Button
                                    type="tertiary"
                                    class="shrink-0"
                                    :label="ctrans('Cancel')"
                                    :disabled="handingOver !== null"
                                    @click="isTalkBoxOpen = false; talkError = ''"
                                >
                                    <template #icon>
                                        <FontAwesomeIcon icon="far fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                                    </template>
                                </Button>
                                <Button
                                    type="primary"
                                    full
                                    :label="ctrans('Send to our team')"
                                    :loading="handingOver === 'link'"
                                    :disabled="handingOver !== null"
                                    @click="handOver(null, talkDraft)"
                                >
                                    <template #icon>
                                        <FontAwesomeIcon icon="far fa-paper-plane" fixed-width aria-hidden="true" />
                                    </template>
                                </Button>
                            </div>
                        </div>
                    </aside>

                    <div v-if="isHistoryLoading || history.length" class="merlin-history">
                        <p class="merlin-caption">{{ ctrans("Recent conversations") }}</p>
                        <div v-if="isHistoryLoading && !history.length" class="flex flex-col gap-1.5">
                            <div v-for="index in 3" :key="index" class="skeleton h-9 rounded-lg" />
                        </div>
                        <nav v-else class="merlin-history-list" :aria-label="ctrans('Recent conversations')">
                            <button
                                v-for="item in landingHistory"
                                :key="item.conversation"
                                type="button"
                                class="merlin-history-item"
                                @click="openConversation(item.conversation)"
                            >
                                <FontAwesomeIcon icon="far fa-comment-alt" class="merlin-history-icon" fixed-width aria-hidden="true" />
                                <span class="merlin-history-text">{{ item.title }}</span>
                                <span class="merlin-history-date">{{ historyDate(item.last_message_at) }}</span>
                            </button>
                        </nav>
                    </div>
                    </div>

                    <aside class="merlin-hero">
                        <div class="merlin-hero-head">
                        <AssistantIcon :littleBird="isLittleBird" class="merlin-hero-icon" />
                            <div class="min-w-0">
                        <p class="merlin-eyebrow">{{ ctrans("A little magic. A lot of knowledge.") }}</p>
                        <h1 class="merlin-title">
                            {{ ctrans("Hello, I'm") }} <em>{{ assistantName }}.</em><br>
                            <span class="merlin-title-sub">{{ ctrans("How can I help?") }}</span>
                            <span class="beta-tag" :title="ctrans(':name is new and still learning', { name: assistantName })">{{ ctrans("Beta") }}</span>
                        </h1>
                            </div>
                        </div>
                        <p class="merlin-intro">{{ landingIntro }}</p>
                        <p class="merlin-intro merlin-intro-more">{{ knowsTheRange }}</p>
                        <p v-if="isMerlin" class="merlin-legend">
                            {{ ctrans("Named after the wise wizard of British legend – a fitting guide for a British company that has travelled the world for good finds since 1995.") }}
                        </p>
                    </aside>
                    </div>
                </div>
            </section>

            <RangeAdvisorPanel
                v-if="showsRangePanel"
                :assistantName
                :fieldValue
                :screenType
                @loaded="(_hasProducts, hasForgotten) => { hasForgottenProducts = hasForgotten }"
                @tellShop="focusLandingTextarea"
            />
            </template>
        </div>

        <div v-else-if="isChatVisible" class="chat-shell antialiased" :style="{ fontFamily: layout?.app?.webpage_layout?.container?.properties?.text?.fontFamily }" :class="{ 'chat-shell-collapsed': isSidebarCollapsed, 'chat-shell-little-bird': isLittleBird }">
            <aside class="chat-sidebar" :class="{ 'chat-sidebar-open': isHistoryOpen }">
                <div class="chat-sidebar-top">
                    <span class="chat-sidebar-brand">
                        <AssistantIcon :littleBird="isLittleBird" class="chat-sidebar-brand-icon" />
                        {{ assistantName }}
                    </span>
                    <button
                        type="button"
                        class="chat-icon-button"
                        :title="isSidebarCollapsed && !isHistoryOpen ? ctrans('Open sidebar') : ctrans('Close sidebar')"
                        :aria-label="isSidebarCollapsed && !isHistoryOpen ? ctrans('Open sidebar') : ctrans('Close sidebar')"
                        @click="toggleHistory"
                    >
                        <FontAwesomeIcon icon="far fa-columns" fixed-width aria-hidden="true" />
                    </button>
                </div>

                <button type="button" class="chat-sidebar-row" :title="ctrans('New conversation')" @click="startNewConversation">
                    <FontAwesomeIcon icon="far fa-edit" fixed-width aria-hidden="true" />
                    <span class="chat-sidebar-label">{{ ctrans("New conversation") }}</span>
                </button>

                <div class="chat-sidebar-history">
                <p class="chat-sidebar-title">{{ ctrans("Recent conversations") }}</p>
                <div v-if="isHistoryLoading && !history.length" class="flex flex-col gap-2">
                    <div v-for="index in 4" :key="index" class="skeleton h-9 rounded" />
                </div>
                <p v-else-if="!history.length" class="chat-muted px-2 text-xs">{{ ctrans("Your conversations will show here.") }}</p>
                <nav v-else class="flex flex-col gap-0.5">
                    <button
                        v-for="item in history"
                        :key="item.conversation"
                        type="button"
                        class="chat-history-item"
                        :class="{ 'chat-history-item-active': item.conversation === conversation }"
                        :aria-current="item.conversation === conversation ? 'true' : undefined"
                        @click="openConversation(item.conversation)"
                    >
                        <span class="chat-history-title">{{ item.title }}</span>
                        <span class="chat-history-date">{{ historyDate(item.last_message_at) }}</span>
                    </button>
                </nav>
                </div>
            </aside>

            <section class="chat-main">
                <header class="chat-header">
                    <button
                        type="button"
                        class="chat-history-toggle"
                        :aria-label="ctrans('Recent conversations')"
                        :aria-expanded="isHistoryOpen || !isSidebarCollapsed"
                        :title="ctrans('Recent conversations')"
                        @click="toggleHistory"
                    >
                        <FontAwesomeIcon :icon="isHistoryOpen ? 'far fa-times' : 'far fa-bars'" fixed-width aria-hidden="true" />
                    </button>
                    <span class="assistant-avatar" aria-hidden="true"><AssistantIcon :littleBird="isLittleBird" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="chat-header-name">
                            {{ assistantName }}
                            <span class="beta-tag" :title="ctrans(':name is new and still learning', { name: assistantName })">{{ ctrans("Beta") }}</span>
                        </p>
                        <p class="chat-header-role">{{ isDropshipping ? ctrans("Your dropshipping coach") : ctrans("AI shopping assistant") }}</p>
                    </div>
                    <button
                        v-if="canTalkToAPerson"
                        type="button"
                        class="chat-header-talk"
                        :aria-expanded="isTalkBoxOpen"
                        @click="isTalkBoxOpen = !isTalkBoxOpen"
                    >
                        <FontAwesomeIcon icon="far fa-headset" fixed-width aria-hidden="true" />
                        <span class="hidden sm:inline">{{ ctrans("Talk to a person") }}</span>
                    </button>
                    <button type="button" class="chat-header-new" @click="startNewConversation">
                        <FontAwesomeIcon icon="far fa-plus" fixed-width aria-hidden="true" />
                        <span class="hidden sm:inline">{{ ctrans("New conversation") }}</span>
                    </button>
                </header>

                <div ref="chatMessages" class="chat-messages">
                    <div v-if="isRestoring && !messages.length" class="skeleton assistant-skeleton rounded" />

                    <template v-for="message in messages" :key="message.id">
                        <div v-if="message.role === 'customer'" class="flex flex-col items-end gap-1">
                            <div v-if="message.images?.length" class="flex flex-wrap justify-end gap-1">
                                <a v-for="image in message.images" :key="image.url" :href="image.url" target="_blank" rel="noopener" class="block h-24 w-24 overflow-hidden rounded border bg-gray-50">
                                    <img :src="image.url" :alt="image.name" class="h-full w-full object-cover" />
                                </a>
                            </div>
                            <div v-if="message.body" class="assistant-bubble chat-text whitespace-pre-line">{{ message.body }}</div>
                        </div>

                        <div v-else class="flex gap-3">
                            <div class="assistant-avatar" aria-hidden="true"><AssistantIcon :littleBird="isLittleBird" /></div>
                            <div class="min-w-0 flex-1">
                                <p class="chat-text whitespace-pre-line">{{ message.body }}</p>

                                <p v-for="link in message.links ?? []" :key="link.url" class="mt-2 text-sm">
                                    <a :href="link.url" class="assistant-link">{{ ctrans("More about :name", { name: link.label }) }}</a>
                                </p>

                                <template v-for="tabs in [groupTabs(message)]" :key="message.id">
                                    <Tabs v-if="tabs.length" :value="tabs[0].key" class="mt-3">
                                        <TabList v-if="tabs.length > 1">
                                            <Tab v-for="tab in tabs" :key="tab.key" :value="tab.key">{{ tab.label }}</Tab>
                                        </TabList>
                                        <TabPanels>
                                            <TabPanel v-for="tab in tabs" :key="tab.key" :value="tab.key">
                                                <div class="assistant-grid">
                                                    <div v-for="card in tab.cards" :key="`${card.id}-${cardsVersion}`" @click.capture="(event) => recordClick(event, card)">
                                                        <template v-if="isDropshipping">
                                                            <p class="chat-muted mb-1 text-xs">{{ card.assistant_reason }}</p>
                                                            <div :style="getStyles(fieldValue?.card_product?.properties, screenType)" class="relative flex justify-center rounded">
                                                                <RenderProductDropshiping :code="productCardCode" :product="card"
                                                                :productHasPortfolio="card.portfolio_channels ?? []"
                                                                :buttonStyle="getStyles(fieldValue?.button?.properties, screenType, false) ?? undefined"
                                                                :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType) ?? undefined"
                                                                :bestSeller="fieldValue?.bestseller" :screenType />
                                                            </div>
                                                        </template>
                                                        <RangeAdvisorProductCard v-else :product="card"
                                                            :hasInBasket="get(layout, ['family_page', 'productInBasket', 'list', card.id])"
                                                            :buttonStyle="getStyles(fieldValue?.button?.properties, screenType, false) ?? undefined"
                                                            :buttonStyleHover="getStyles(fieldValue?.buttonHover?.properties, screenType, false) ?? undefined" />
                                                    </div>
                                                </div>
                                            </TabPanel>
                                        </TabPanels>
                                    </Tabs>
                                    <div v-if="tabs.length && !isDropshipping" class="mt-3 flex flex-wrap items-center gap-2">
                                        <Button
                                            type="tertiary"
                                            size="xs"
                                            class="chat-dark-button"
                                            :label="notInBasket(message).length ? ctrans('Add all to basket') : ctrans('All in your basket')"
                                            :disabled="!notInBasket(message).length || addingTo !== null"
                                            :loading="addingTo === message.id"
                                            @click="addAllToBasket(message)"
                                        >
                                            <template #icon>
                                                <FontAwesomeIcon :icon="notInBasket(message).length ? 'far fa-shopping-basket' : 'far fa-check'" class="text-green-600" fixed-width aria-hidden="true" />
                                            </template>
                                        </Button>
                                        <p v-if="addErrors[message.id]" class="text-xs text-red-500">{{ addErrors[message.id] }}</p>
                                    </div>
                                </template>

                                <div v-if="message.id === latestAssistantId && message.follow_ups?.length && !message.claim" class="merlin-cards chat-follow-ups">
                                    <button
                                        v-for="(followUp, index) in message.follow_ups"
                                        :key="followUp"
                                        type="button"
                                        class="merlin-card"
                                        :disabled="isPending"
                                        @click="send(followUp)"
                                    >
                                        <span class="merlin-card-icon" aria-hidden="true">{{ index % 2 ? "✧" : "✦" }}</span>
                                        <span class="merlin-card-text">{{ followUp }}</span>
                                        <span class="merlin-card-arrow" aria-hidden="true">↗</span>
                                    </button>
                                </div>

                                <CustomerClaimForm v-if="message.claim" class="max-w-xl" :order-id="message.claim.order_id" source="merlin" :conversation="conversation" :made="message.claim.made" />

                                <div v-if="message.hand_over_offer && (handOverAvailable || message.handed_over)" class="mt-3">
                                    <p v-if="message.handed_over" class="chat-muted text-xs">{{ ctrans("Passed to our team") }}</p>
                                    <Button
                                        v-else
                                        type="tertiary"
                                        size="xs"
                                        class="chat-dark-button"
                                        :label="ctrans('Talk to our team')"
                                        :disabled="handingOver !== null || isPending"
                                        @click="handOver(message.id)"
                                    >
                                        <template #icon>
                                            <LoadingIcon v-if="handingOver === message.id" />
                                            <FontAwesomeIcon v-else icon="far fa-headset" class="chat-muted" fixed-width aria-hidden="true" />
                                        </template>
                                    </Button>
                                    <p v-if="handOverErrors[message.id]" class="mt-1 text-xs text-red-500">{{ handOverErrors[message.id] }}</p>
                                </div>

                                <div class="mt-2 flex items-center gap-1">
                                    <Button
                                        type="tertiary"
                                        size="xs"
                                        class="chat-dark-button"
                                        :tooltip="ctrans('Helpful')"
                                        :aria-pressed="message.feedback === 'up'"
                                        @click="setFeedback(message, 'up')"
                                    >
                                        <template #icon>
                                            <FontAwesomeIcon :icon="message.feedback === 'up' ? 'fas fa-thumbs-up' : 'far fa-thumbs-up'" :class="message.feedback === 'up' ? 'text-green-500' : 'chat-muted'" fixed-width aria-hidden="true" />
                                        </template>
                                    </Button>
                                    <Button
                                        type="tertiary"
                                        size="xs"
                                        class="chat-dark-button"
                                        :tooltip="ctrans('Not helpful')"
                                        :aria-pressed="message.feedback === 'down'"
                                        @click="setFeedback(message, 'down')"
                                    >
                                        <template #icon>
                                            <FontAwesomeIcon :icon="message.feedback === 'down' ? 'fas fa-thumbs-down' : 'far fa-thumbs-down'" :class="message.feedback === 'down' ? 'text-red-500' : 'chat-muted'" fixed-width aria-hidden="true" />
                                        </template>
                                    </Button>
                                    <p v-if="feedbackErrors[message.id]" class="text-xs text-red-500">{{ feedbackErrors[message.id] }}</p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div v-if="isPending" class="flex gap-3">
                        <div class="assistant-avatar assistant-avatar-thinking" aria-hidden="true"><AssistantIcon :littleBird="isLittleBird" /></div>
                        <div class="min-w-0 flex-1">
                            <MerlinThinking :name="assistantName" :expected-seconds="assistant?.expected_seconds" />
                        </div>
                    </div>

                    <p v-if="notice" class="chat-muted text-sm">{{ notice }}</p>
                </div>

                <div class="chat-composer">
                    <div v-if="isTalkBoxOpen && canTalkToAPerson" class="chat-talk-box">
                        <label for="assistant-talk-to-a-person" class="mb-1 block text-sm font-medium">
                            {{ ctrans("What would you like to ask our team?") }} <span class="text-red-500">*</span>
                        </label>
                        <Textarea id="assistant-talk-to-a-person" v-model="talkDraft" rows="3" autoResize maxlength="1000" class="w-full" />
                        <p v-if="talkError" class="mt-1 text-xs text-red-500">{{ talkError }}</p>
                        <div class="mt-3 flex w-full gap-2">
                            <Button
                                type="tertiary"
                                class="chat-dark-button shrink-0"
                                :label="ctrans('Cancel')"
                                :disabled="handingOver !== null"
                                @click="isTalkBoxOpen = false; talkError = ''"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="far fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                            <Button
                                type="primary"
                                full
                                :label="ctrans('Send to our team')"
                                :loading="handingOver === 'link'"
                                :disabled="handingOver !== null"
                                @click="handOver(null, talkDraft)"
                            >
                                <template #icon>
                                    <FontAwesomeIcon icon="far fa-paper-plane" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </div>
                    </div>

                    <div class="chat-composer-box" @dragover.prevent @drop="picker?.onDrop($event)">
                        <Textarea
                            v-model="draft"
                            rows="1"
                            auto-resize
                            maxlength="1000"
                            class="landing-textarea chat-composer-input"
                            :aria-label="ctrans('Describe what your shop needs')"
                            :placeholder="isDropshipping
                                ? ctrans('Ask :name a follow-up or tell it about your store', { name: assistantName })
                                : ctrans('Ask :name a follow-up or describe what your shop needs', { name: assistantName })"
                            @keydown.enter="sendChatOnEnter"
                            @paste="picker?.onPaste($event)"
                        />
                        <button
                            type="button"
                            class="chat-send"
                            :aria-label="ctrans('Send')"
                            :disabled="isPending || (!draft.trim() && !images.length)"
                            @click="send(draft)"
                        >
                            <FontAwesomeIcon :icon="isLittleBird ? 'far fa-arrow-right' : 'far fa-paper-plane'" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                    <MerlinImagePicker ref="picker" v-model="images" :disabled="isPending" class="mt-2" />
                    <p v-if="errorMessage" class="mt-1 text-xs text-red-500">{{ errorMessage }}</p>
                    <p class="chat-composer-hint">
                        <span v-if="!errorMessage">{{ ctrans("Enter to send, Shift + Enter for a new line.") }}</span>
                        {{ ctrans(":name is an AI assistant and can make mistakes. Our staff may read these conversations to improve our service; please do not share payment details.", { name: assistantName }) }}
                    </p>
                </div>
            </section>
        </div>

        <p v-else-if="isLayoutReady && !isLoggedOut" class="text-sm text-gray-600">
            {{ ctrans("The range advisor is not available on this website.") }}
        </p>

        <div v-else class="skeleton assistant-skeleton rounded" />
    </div>
</template>

<style scoped>
.assistant-page {
    max-width: 860px;
    margin-left: auto;
    margin-right: auto;
    width: 100%;
}

.assistant-avatar {
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: color-mix(in srgb, var(--theme-color-4) 80%, #000);
    background: radial-gradient(circle at 30% 25%, #fff 0%, color-mix(in srgb, var(--theme-color-4) 14%, #fff) 100%);
    border: 1px solid color-mix(in srgb, var(--theme-color-4) 28%, #fff);
    box-shadow: 0 1px 3px color-mix(in srgb, var(--theme-color-4) 18%, transparent);
}

.assistant-avatar .merlin-icon {
    width: 1.5rem;
    height: 1.5rem;
    vertical-align: middle;
    --merlin-stroke-weight: 1.5;
}

.assistant-avatar-thinking {
    animation: assistant-avatar-glow 1.8s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .assistant-avatar-thinking {
        animation: none;
    }
}

@keyframes assistant-avatar-glow {
    0%, 100% {
        box-shadow: 0 0 0 0 color-mix(in srgb, var(--theme-color-4) 35%, transparent);
    }

    50% {
        box-shadow: 0 0 0 0.375rem color-mix(in srgb, var(--theme-color-4) 0%, transparent);
    }
}

.assistant-bubble {
    max-width: 80%;
    padding: 0.625rem 0.875rem;
    border-radius: 0.75rem 0.75rem 0.25rem 0.75rem;
    line-height: 1.5;
    background-color: color-mix(in srgb, var(--theme-color-4) 6%, #efefef);
}

.chat-shell {
    --chat-gutter: max(1.5rem, calc((100% - 820px) / 2));
    --chat-bg: #1d252e;
    --chat-sidebar: #18212a;
    --chat-card: #293541;
    --chat-card-hover: #2e3a46;
    --chat-bubble: #354350;
    --chat-edge: #43515d;
    --chat-edge-strong: #64717b;
    --chat-muted: #bfccd6;
    --chat-accent: var(--theme-color-4, #e87928);
    --chat-accent-text: var(--theme-color-5, #ffffff);
    --merlin-card: #293541;
    --merlin-card-hover: #34414e;
    --merlin-edge: #46535f;
    --merlin-accent: var(--chat-accent);
    position: relative;
    display: grid;
    width: 100%;
    height: min(calc(100vh - var(--iris-header-height, 0px) - 1rem), 920px);
    min-height: 560px;
    scroll-margin-top: var(--iris-header-height, 0px);
    border-top: 1px solid var(--chat-edge);
    background-color: var(--chat-bg);
    color: #ffffff;
}

.chat-muted {
    color: var(--chat-muted);
}

.chat-shell .chat-dark-button {
    border-color: var(--chat-edge);
    color: #dfe8ef;
    background-color: transparent;
}

.chat-shell .chat-dark-button:not(:disabled):hover {
    border-color: var(--chat-accent);
    background-color: var(--chat-card-hover);
}

.chat-shell .chat-dark-button:disabled {
    color: var(--chat-muted);
    background-color: transparent;
    opacity: 0.7;
}

.chat-shell .assistant-avatar {
    border-color: var(--chat-edge);
    background: color-mix(in srgb, var(--chat-accent) 17%, var(--chat-card));
    box-shadow: none;
    color: var(--chat-accent);
}

.chat-shell .assistant-bubble {
    border: 1px solid #4a5a65;
    border-radius: 0.8125rem 0.8125rem 0.1875rem 0.8125rem;
    background-color: var(--chat-bubble);
    color: #ffffff;
}

.chat-shell .chat-text {
    color: #f5f7fa;
}

.chat-shell .assistant-link {
    color: color-mix(in srgb, var(--chat-accent) 55%, #ffffff);
}

.chat-follow-ups {
    margin-top: 1rem;
}

.chat-follow-ups .merlin-card:disabled {
    transform: none;
    opacity: 0.5;
    cursor: not-allowed;
}

.chat-shell:not(.chat-shell-little-bird) .chat-follow-ups {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.chat-shell:not(.chat-shell-little-bird) .chat-follow-ups .merlin-card {
    width: auto;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    background-color: transparent;
    color: #c8d2db;
}

.chat-shell:not(.chat-shell-little-bird) .chat-follow-ups .merlin-card:not(:disabled):hover {
    transform: none;
    color: #fff;
}

.chat-shell:not(.chat-shell-little-bird) .chat-follow-ups .merlin-card-text {
    font-size: 0.8125rem;
    font-weight: 400;
}

.chat-shell:not(.chat-shell-little-bird) .chat-follow-ups .merlin-card-icon,
.chat-shell:not(.chat-shell-little-bird) .chat-follow-ups .merlin-card-arrow {
    display: none;
}

.chat-shell .landing-textarea {
    color: #ffffff;
}

.chat-shell .landing-textarea::placeholder {
    color: #cbd4dd;
}

.chat-shell :deep(.p-tabs),
.chat-shell :deep(.p-tablist),
.chat-shell :deep(.p-tablist-content),
.chat-shell :deep(.p-tablist-tab-list),
.chat-shell :deep(.p-tabpanels),
.chat-shell :deep(.p-tabpanel) {
    background: transparent;
}

.chat-shell :deep(.p-tablist-tab-list) {
    gap: 1.5rem;
    border-bottom: 1px solid var(--chat-edge);
}

.chat-shell :deep(.p-tab) {
    padding: 0.6875rem 0.9375rem 0.8125rem;
    border-color: transparent;
    background: transparent;
    color: #b9c6d1;
    font-weight: 700;
}

.chat-shell :deep(.p-tab:not(.p-tab-active):hover) {
    color: #ffffff;
}

.chat-shell :deep(.p-tab-active) {
    color: color-mix(in srgb, var(--chat-accent) 55%, #ffffff);
}

.chat-shell :deep(.p-tablist-active-bar) {
    background: var(--chat-accent);
}

.chat-shell :deep(.p-tablist-nav-button) {
    background: var(--chat-bg);
    color: #ffffff;
    box-shadow: none;
}

.chat-shell :deep(.merlin-thinking-caption) {
    color: var(--chat-muted);
}

.chat-shell :deep(.merlin-thinking-usually) {
    color: #a3b0bc;
}

.chat-shell :deep(.merlin-thinking-bar) {
    background-color: color-mix(in srgb, var(--chat-accent) 20%, var(--chat-card));
}

.chat-shell .skeleton {
    background-color: var(--chat-card);
}

@media (min-width: 1024px) {
    .chat-shell {
        grid-template-columns: 250px minmax(0, 1fr);
    }
}

.chat-sidebar {
    display: none;
    min-height: 0;
    overflow-y: auto;
    padding: 1.0625rem 0.625rem;
    background-color: var(--chat-sidebar);
    border-right: 1px solid var(--chat-edge);
}

.chat-sidebar-open {
    display: block;
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 10;
    width: min(85%, 320px);
    box-shadow: 10px 0 40px rgba(0, 0, 0, 0.5);
}

@media (min-width: 1024px) {
    .chat-sidebar,
    .chat-sidebar-open {
        display: block;
        position: static;
        width: auto;
        box-shadow: none;
    }
}

.chat-sidebar-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0 0.3125rem 0.9375rem;
}

.chat-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    font-size: 1.0625rem;
    font-weight: 800;
    color: #ffffff;
}

.chat-sidebar-brand-icon {
    color: var(--theme-color-4);
}

.chat-icon-button {
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    color: #d9e3eb;
    transition: background-color 0.15s;
}

.chat-icon-button:hover {
    background-color: var(--chat-card-hover);
    color: #ffffff;
}

.chat-sidebar-row {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    margin: 0.5625rem 0 0.5rem;
    padding: 0.75rem 0.8125rem;
    border: 1px solid #53616d;
    border-radius: 0.625rem;
    text-align: left;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #ffffff;
    transition: background-color 0.15s, border-color 0.15s;
}

.chat-sidebar-row:hover {
    border-color: var(--chat-accent);
    background-color: var(--chat-card-hover);
}

.chat-sidebar-title {
    margin: 1rem 0.3125rem 0.5625rem;
    font-size: 0.625rem;
    font-weight: 800;
    letter-spacing: 0.115em;
    text-transform: uppercase;
    color: #9baab6;
}

.chat-history-item {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    width: 100%;
    padding: 0.6875rem 0.4375rem;
    text-align: left;
    transition: background-color 0.15s;
}

.chat-history-item:hover {
    background-color: var(--chat-card-hover);
}

.chat-history-item-active {
    background-color: var(--chat-card);
    box-shadow: inset 2px 0 0 var(--chat-accent);
}

.chat-history-title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.75rem;
    color: #dfe8ef;
}

.chat-history-date {
    flex-shrink: 0;
    font-size: 0.625rem;
    color: #a7b4be;
}

.chat-main {
    display: flex;
    flex-direction: column;
    min-width: 0;
    min-height: 0;
}

.chat-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 4.625rem;
    padding: 0.75rem var(--chat-gutter);
    border-bottom: 1px solid var(--chat-edge);
}

.chat-header-name {
    font-size: 1.1875rem;
    font-weight: 800;
    line-height: 1.1;
    color: #ffffff;
}

.beta-tag {
    display: inline-block;
    margin-left: 0.375rem;
    padding: 0.0625rem 0.375rem;
    border: 1px solid #d1d5db;
    border-radius: 9999px;
    color: #6b7280;
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    line-height: 1.4;
    text-transform: uppercase;
    vertical-align: middle;
    white-space: nowrap;
}

.chat-shell .beta-tag {
    border-color: #60707c;
    color: #ced7df;
    font-weight: 400;
}

.chat-header-role {
    margin-top: 0.3125rem;
    font-size: 0.6875rem;
    font-weight: 800;
    letter-spacing: 0.11em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--chat-accent) 70%, #ffffff);
}

.chat-history-toggle {
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    border: 1px solid #55636d;
    border-radius: 0.5625rem;
    color: #ffffff;
}

.chat-history-toggle:hover {
    border-color: var(--theme-color-4);
}

@media (min-width: 1024px) {
    .chat-history-toggle {
        display: none;
    }

    .chat-shell-collapsed {
        grid-template-columns: 56px minmax(0, 1fr);
    }

    .chat-shell-collapsed .chat-sidebar {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    .chat-shell-collapsed .chat-sidebar-top {
        justify-content: center;
        padding: 0 0 0.5rem;
    }

    .chat-shell-collapsed .chat-sidebar-brand,
    .chat-shell-collapsed .chat-sidebar-label,
    .chat-shell-collapsed .chat-sidebar-history {
        display: none;
    }

    .chat-shell-collapsed .chat-sidebar-row {
        justify-content: center;
        padding: 0.5rem 0;
    }
}

.chat-header-new {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.625rem 1.0625rem;
    border: 1px solid #697681;
    border-radius: 9999px;
    font-size: 0.8125rem;
    color: #ffffff;
}

.chat-header-talk {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.625rem 1.0625rem;
    border: 1px solid #697681;
    border-radius: 9999px;
    font-size: 0.8125rem;
    color: #ffffff;
}

.chat-header-talk:hover {
    border-color: var(--theme-color-4);
}

.chat-talk-box {
    margin-bottom: 0.75rem;
    padding: 1rem;
    border: 1px solid var(--chat-edge);
    border-radius: 0.75rem;
    background-color: var(--chat-card);
    color: #ffffff;
}

.chat-header-new:hover {
    border-color: var(--theme-color-4);
}

@media (min-width: 1024px) {
    .chat-header-new {
        display: none;
    }
}

.chat-messages {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 2.375rem var(--chat-gutter);
    display: flex;
    flex-direction: column;
    gap: 1.625rem;
}

.chat-text {
    font-size: 0.9375rem;
    line-height: 1.6;
}

.chat-composer {
    padding: 0.75rem var(--chat-gutter) 1.375rem;
    background: linear-gradient(0deg, var(--chat-bg) 80%, transparent);
}

.chat-composer-box {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
    padding: 0.5rem 0.5rem 0.5rem 0.8125rem;
    border: 1px solid var(--chat-edge-strong);
    border-radius: 1rem;
    background-color: var(--chat-card);
}

.chat-composer-box:focus-within {
    border-color: var(--chat-accent);
}

.chat-composer-input {
    flex: 1;
    min-width: 0;
    max-height: 12rem;
    resize: none;
}

.chat-composer-box .chat-composer-input {
    min-height: 2.25rem;
    padding: 0.375rem 0.25rem;
    font-size: 0.9375rem;
}

.chat-send {
    width: 2.4375rem;
    height: 2.4375rem;
    flex-shrink: 0;
    border-radius: 0.625rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--chat-accent);
    color: var(--chat-accent-text);
}

.chat-send:hover:not(:disabled) {
    background-color: color-mix(in srgb, var(--chat-accent) 85%, #ffffff);
}

.chat-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.chat-composer-hint {
    margin-top: 0.375rem;
    text-align: center;
    font-size: 0.6875rem;
    line-height: 1.4;
    color: #a3b0bc;
}

.landing-page {
    width: 100%;
    color: #1f1f1f;
}

.landing-textarea {
    width: 100%;
    min-height: 8.5rem;
    border: 0;
    box-shadow: none;
    padding: 0.375rem 0.5rem;
    font-size: 1rem;
    line-height: 1.5;
    background: transparent;
}

.landing-textarea:focus {
    outline: none;
    box-shadow: none;
}

.assistant-link {
    color: var(--theme-color-0);
    text-decoration: underline;
}

.assistant-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-auto-rows: 1fr;
}

@media (min-width: 640px) {
    .assistant-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (min-width: 1024px) {
    .assistant-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

.assistant-skeleton {
    height: 8rem;
}

:deep(.p-tab-active) {
    color: var(--theme-color-0);
    border-color: var(--theme-color-0);
}

:deep(.p-tabpanels) {
    padding-left: 0;
    padding-right: 0;
}

:deep(.p-tablist-active-bar) {
    background: var(--theme-color-0);
}

.merlin-landing {
    --merlin-bg: #1d252e;
    --merlin-card: #293541;
    --merlin-card-hover: #34414e;
    --merlin-edge: #46535f;
    --merlin-muted: #c8d2db;
    --merlin-accent: var(--theme-color-4, #e87928);
    --merlin-accent-text: var(--theme-color-5, #fff);
    background-color: var(--merlin-bg);
    color: #fff;
    padding: 0 1rem 3rem;
    line-height: 1.5;
}

.merlin-wrap {
    max-width: 1280px;
    margin: 0 auto;
}

.merlin-hero {
    --merlin-hero-accent: oklch(from var(--theme-color-4, #e87928) max(l, 0.72) c h);
    text-align: left;
}

.merlin-columns {
    display: grid;
    gap: 2rem;
    align-items: start;
    padding-top: 2rem;
}

.merlin-main {
    min-width: 0;
}

@media (min-width: 1024px) {
    .merlin-columns {
        grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);
        gap: 3rem;
    }
}

.merlin-hero-icon {
    --theme-color-4: var(--merlin-hero-accent);
    display: block;
    width: 5rem;
    height: 5rem;
    flex: none;
    --merlin-stroke-weight: 0.8;
}

.merlin-hero-head {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.merlin-eyebrow {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--merlin-accent) 70%, #fff);
}

.merlin-title {
    margin: 0.375rem 0 0;
    font-size: 2.25rem;
    font-weight: 600;
    line-height: 1.15;
    letter-spacing: -0.02em;
    color: #fff;
}

.merlin-title-sub {
    font-size: 1.375rem;
    font-weight: 500;
    letter-spacing: 0;
    color: #dfe6ec;
}

.merlin-title em {
    font-style: normal;
    color: color-mix(in srgb, var(--merlin-accent) 75%, #fff);
    color: oklch(from var(--merlin-accent) max(l, 0.78) c h);
}

.merlin-intro {
    max-width: 28rem;
    font-size: 1rem;
    line-height: 1.6;
    color: #dfe6ec;
}

.merlin-intro-more {
    margin-top: 0.75rem;
}

.merlin-legend {
    max-width: 28rem;
    margin-top: 1.25rem;
    padding-left: 0.875rem;
    border-left: 2px solid var(--merlin-edge);
    font-size: 0.8125rem;
    line-height: 1.6;
    color: var(--merlin-muted);
}

.merlin-suggest,
.merlin-form,
.merlin-human,
.merlin-history,
.merlin-note,
.merlin-error,
.merlin-notice {
    margin-left: auto;
    margin-right: auto;
}

.merlin-suggest {
    margin-top: 1.5rem;
    margin-bottom: 1.75rem;
}

.merlin-caption {
    margin-bottom: 0.9375rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--merlin-muted);
}

.merlin-cards {
    display: grid;
    gap: 0.8rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.merlin-card {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    width: 100%;
    padding: 1.1875rem;
    border: 1px solid var(--merlin-edge);
    border-radius: 0.9375rem;
    background-color: var(--merlin-card);
    color: #fff;
    text-align: left;
    transition: transform 0.2s, border-color 0.2s, background-color 0.2s;
}

.merlin-card:hover {
    transform: translateY(-2px);
    border-color: var(--merlin-accent);
    background-color: var(--merlin-card-hover);
}

.merlin-card:focus-visible {
    outline: 2px solid var(--merlin-accent);
    outline-offset: 2px;
}

.merlin-card-icon {
    width: 2.75rem;
    height: 2.75rem;
    flex: none;
    display: grid;
    place-items: center;
    border-radius: 0.75rem;
    background-color: color-mix(in srgb, var(--merlin-accent) 17%, transparent);
    font-size: 1.375rem;
    color: color-mix(in srgb, var(--merlin-accent) 50%, #fff);
}

.merlin-card-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.merlin-card-text {
    font-size: 0.9375rem;
    font-weight: 700;
}

.merlin-card-subtitle {
    margin-top: 0.1875rem;
    font-size: 0.75rem;
    color: #d7e1e9;
}

.merlin-card-arrow {
    margin-left: auto;
    font-size: 1.3rem;
    color: color-mix(in srgb, var(--merlin-accent) 75%, #fff);
}

.merlin-chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.5rem;
}

.merlin-chip {
    padding: 0.25rem 0.75rem;
    border: 1px solid var(--merlin-edge);
    border-radius: 9999px;
    color: var(--merlin-muted);
    font-size: 0.8125rem;
    transition: border-color 0.2s, color 0.2s;
}

.merlin-chip:hover {
    border-color: var(--merlin-accent);
    color: #fff;
}

.merlin-chip:focus-visible {
    outline: 2px solid var(--merlin-accent);
    outline-offset: 2px;
}

.merlin-form {
    padding: 0.8rem;
    border: 2px solid var(--merlin-accent);
    border-radius: 1.0625rem;
    background-color: #fff;
}

.merlin-form:focus-within {
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--merlin-accent) 35%, transparent);
}

.merlin-form .merlin-textarea {
    display: block;
    width: 100%;
    min-height: 8.5rem;
    padding: 0.625rem;
    border: 0;
    box-shadow: none;
    background: transparent;
    font-size: 1.0625rem;
    color: #1f2937;
}

.merlin-form .merlin-textarea::placeholder {
    color: #6b7280;
    opacity: 1;
}

.merlin-form-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.625rem;
}

.merlin-helper {
    font-size: 0.75rem;
    color: #6b7280;
}

.merlin-send {
    width: 2.6875rem;
    height: 2.6875rem;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 0.75rem;
    background-color: var(--merlin-accent);
    color: var(--merlin-accent-text);
    font-size: 1.125rem;
    transition: background-color 0.15s;
}

.merlin-send:hover:not(:disabled) {
    background-color: color-mix(in srgb, var(--merlin-accent) 85%, #fff);
}

.merlin-send:focus-visible {
    outline: 2px solid var(--merlin-accent);
    outline-offset: 2px;
}

.merlin-send:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.merlin-error {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: #fca5a5;
}

.merlin-notice {
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--merlin-muted);
}

.merlin-human {
    margin-top: 1.5rem;
    padding: 0.625rem 0.875rem;
    border: 1px solid var(--merlin-edge);
    border-radius: 0.75rem;
}

.merlin-human-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.merlin-human-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
}

.merlin-human-text {
    font-size: 0.75rem;
    color: var(--merlin-muted);
}

.merlin-human-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    white-space: nowrap;
    padding: 0.375rem 0.75rem;
    border: 1px solid var(--merlin-accent);
    border-radius: 9999px;
    color: color-mix(in srgb, var(--merlin-accent) 70%, #fff);
    font-size: 0.8125rem;
    font-weight: 600;
    transition: background-color 0.2s, color 0.2s;
}

.merlin-human-link:hover {
    background-color: var(--merlin-accent);
    color: var(--merlin-accent-text);
}

.merlin-human-link:focus-visible {
    outline: 2px solid var(--merlin-accent);
    outline-offset: 2px;
}

.merlin-talk-box {
    margin: 1rem 0 0;
    background-color: white;
    color: #1f1f1f;
}

.merlin-history {
    margin-top: 1.75rem;
}

.merlin-history-list {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.merlin-history-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.625rem 0.875rem;
    border: 1px solid var(--merlin-edge);
    border-radius: 0.75rem;
    background-color: var(--merlin-card);
    color: #fff;
    text-align: left;
    transition: border-color 0.15s, background-color 0.15s;
}

.merlin-history-item:hover {
    border-color: var(--merlin-accent);
    background-color: var(--merlin-card-hover);
}

.merlin-history-item:focus-visible {
    outline: 2px solid var(--merlin-accent);
    outline-offset: 2px;
}

.merlin-history-icon {
    flex-shrink: 0;
    color: color-mix(in srgb, var(--merlin-accent) 60%, #fff);
}

.merlin-history-text {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.875rem;
}

.merlin-history-date {
    flex-shrink: 0;
    font-size: 0.6875rem;
    color: var(--merlin-muted);
}

.merlin-note {
    margin-top: 0.5rem;
    font-size: 0.6875rem;
    color: var(--merlin-muted);
    opacity: 0.8;
}

@media (max-width: 620px) {
    .merlin-cards {
        grid-template-columns: minmax(0, 1fr);
    }

    .merlin-helper {
        font-size: 0.625rem;
    }

    .merlin-card {
        padding: 0.8rem;
    }

    .merlin-human-row {
        flex-direction: column;
        text-align: center;
    }

    .merlin-human-link {
        width: 100%;
    }
}
.bird-landing {
    --bird-ink: #151515;
    --bird-text: #525252;
    --bird-muted: #777777;
    --bird-line: #dedede;
    --bird-line-strong: #bcbcbc;
    --bird-soft: #fafafa;
    --bird-hover: #f4f4f4;
    min-height: 100vh;
    padding: 1.875rem 1rem 3.75rem;
    background-color: #ffffff;
    color: var(--bird-ink);
}

.bird-wrap {
    max-width: 1120px;
    margin: 0 auto;
}

.bird-topbar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 1.75rem;
}

.bird-talk {
    display: inline-flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.8125rem 1.25rem;
    border: 1px solid #c9c9c9;
    border-radius: 9999px;
    background-color: #ffffff;
    color: var(--bird-ink);
    font-size: 1rem;
    transition: background-color 0.2s;
}

.bird-talk:hover {
    background-color: #f5f5f5;
}

.bird-talk-box {
    max-width: 920px;
    margin: 0 auto 1.5rem;
}

.bird-hero {
    padding: 1.875rem 0.75rem 1.5rem;
    text-align: center;
}

.bird-mark {
    width: 148px;
    height: 148px;
    margin: 0 auto 1rem;
}

.bird-eyebrow {
    margin-bottom: 0.625rem;
    color: #666666;
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.17em;
    text-transform: uppercase;
}

.bird-title {
    margin: 0 0 0.75rem;
    color: #111111;
    font-size: clamp(2.125rem, 6vw, 3.5rem);
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.03em;
}

.bird-beta {
    display: inline-block;
    margin-left: 0.625rem;
    padding: 0.25rem 0.5625rem;
    border: 1px solid #dddddd;
    border-radius: 9999px;
    background-color: #f5f5f5;
    color: #666666;
    font-size: 0.6875rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    vertical-align: middle;
}

.bird-lead {
    max-width: 760px;
    margin: 0 auto;
    color: var(--bird-text);
    font-size: 1.125rem;
    line-height: 1.65;
}

.bird-search {
    max-width: 920px;
    margin: 1.875rem auto 0;
    padding: 1.125rem;
    border: 1px solid #d8d8d8;
    border-radius: 1.75rem;
    background-color: #ffffff;
    box-shadow: 0 12px 38px rgba(0, 0, 0, 0.055);
}

.bird-search-row {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.625rem 0.625rem 0.625rem 1rem;
    border: 1px solid var(--bird-line);
    border-radius: 1.125rem;
    background-color: var(--bird-soft);
}

.bird-search-row:focus-within {
    border-color: var(--bird-line-strong);
}

.bird-search .bird-search-input {
    flex: 1;
    min-height: 1.5rem;
    padding: 0;
    border: 0;
    background: transparent;
    box-shadow: none;
    color: var(--bird-ink);
    font-size: 1rem;
}

.bird-search .bird-search-input::placeholder {
    color: #828282;
}

.bird-send {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.875rem;
    height: 2.875rem;
    flex-shrink: 0;
    border-radius: 0.875rem;
    background-color: var(--bird-ink);
    color: #ffffff;
    transition: opacity 0.2s;
}

.bird-send:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.bird-send:focus-visible,
.bird-chip:focus-visible,
.bird-talk:focus-visible,
.bird-history-item:focus-visible {
    outline: 2px solid var(--bird-ink);
    outline-offset: 2px;
}

.bird-chips {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1.125rem;
}

.bird-chip {
    padding: 0.75rem 1rem;
    border: 1px solid #d5d5d5;
    border-radius: 9999px;
    background-color: #ffffff;
    color: #333333;
    font-size: 0.875rem;
    text-align: center;
    transition: background-color 0.2s, border-color 0.2s;
}

.bird-chip:hover {
    border-color: #999999;
    background-color: var(--bird-hover);
}

.bird-error,
.bird-notice {
    max-width: 920px;
    margin: 1rem auto 0;
    font-size: 0.875rem;
    text-align: center;
}

.bird-error {
    color: #dc2626;
}

.bird-notice {
    color: var(--bird-muted);
}

.bird-grid {
    display: grid;
    gap: 1.375rem;
    margin-top: 2.125rem;
}

@media (min-width: 900px) {
    .bird-grid-with-panel {
        grid-template-columns: minmax(0, 1.05fr) minmax(0, 1.4fr);
        align-items: start;
    }
}

.bird-card {
    padding: 1.5rem;
    border: 1px solid #dfdfdf;
    border-radius: 1.5rem;
    background-color: #ffffff;
    box-shadow: 0 7px 30px rgba(0, 0, 0, 0.04);
}

.bird-card-title {
    margin: 0 0 1rem;
    color: var(--bird-ink);
    font-size: 1.125rem;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.bird-history .skeleton {
    background-color: var(--bird-hover);
}

.bird-history-empty {
    color: var(--bird-muted);
    font-size: 0.9375rem;
}

.bird-history-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.bird-history-item {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    width: 100%;
    padding: 0.875rem 1rem;
    border: 1px solid #e7e7e7;
    border-radius: 1rem;
    background-color: var(--bird-soft);
    text-align: left;
    transition: border-color 0.2s, background-color 0.2s;
}

.bird-history-item:hover {
    border-color: var(--bird-line-strong);
    background-color: var(--bird-hover);
}

.bird-history-text {
    min-width: 0;
    overflow: hidden;
    color: var(--bird-ink);
    font-size: 0.9375rem;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.bird-history-date {
    flex-shrink: 0;
    color: var(--bird-muted);
    font-size: 0.8125rem;
    white-space: nowrap;
}

.bird-note {
    margin-top: 1.5rem;
    color: var(--bird-muted);
    font-size: 0.75rem;
    text-align: center;
}

@media (max-width: 640px) {
    .bird-landing {
        padding: 1.125rem 0.75rem 2.625rem;
    }

    .bird-topbar {
        justify-content: stretch;
    }

    .bird-talk {
        width: 100%;
        justify-content: center;
    }

    .bird-lead {
        font-size: 1rem;
    }

    .bird-search {
        padding: 0.875rem;
        border-radius: 1.375rem;
    }

    .bird-search-row {
        padding: 0.5rem 0.5rem 0.5rem 0.875rem;
    }

    .bird-send {
        width: 2.625rem;
        height: 2.625rem;
        border-radius: 0.75rem;
    }
}

.chat-shell.chat-shell-little-bird {
    --chat-bg: #ffffff;
    --chat-sidebar: #fafafa;
    --chat-card: #fafafa;
    --chat-card-hover: #f4f4f4;
    --chat-bubble: #f5f5f5;
    --chat-edge: #dedede;
    --chat-edge-strong: #bcbcbc;
    --chat-muted: #666666;
    --chat-accent: #151515;
    --chat-accent-text: #ffffff;
    --merlin-card: #ffffff;
    --merlin-card-hover: #f4f4f4;
    --merlin-edge: #d5d5d5;
    --merlin-accent: #151515;
    color: #151515;
}

.chat-shell-little-bird .chat-text,
.chat-shell-little-bird .landing-textarea,
.chat-shell-little-bird .chat-sidebar-brand,
.chat-shell-little-bird .chat-sidebar-row,
.chat-shell-little-bird .chat-header-name,
.chat-shell-little-bird .chat-history-toggle,
.chat-shell-little-bird .chat-header-new,
.chat-shell-little-bird .chat-header-talk,
.chat-shell-little-bird .chat-talk-box,
.chat-shell-little-bird .chat-icon-button,
.chat-shell-little-bird .chat-icon-button:hover {
    color: #151515;
}

.chat-shell-little-bird .chat-history-title {
    color: #333333;
}

.chat-shell-little-bird .chat-sidebar-title,
.chat-shell-little-bird .chat-history-date,
.chat-shell-little-bird .chat-composer-hint,
.chat-shell-little-bird .chat-header-role {
    color: var(--chat-muted);
}

.chat-shell-little-bird .landing-textarea::placeholder {
    color: #828282;
}

.chat-shell-little-bird .chat-sidebar-row,
.chat-shell-little-bird .chat-history-toggle,
.chat-shell-little-bird .chat-header-new,
.chat-shell-little-bird .chat-header-talk {
    border-color: #c9c9c9;
    background-color: #ffffff;
}

.chat-shell-little-bird .chat-sidebar-row:hover,
.chat-shell-little-bird .chat-history-toggle:hover,
.chat-shell-little-bird .chat-header-new:hover,
.chat-shell-little-bird .chat-header-talk:hover {
    border-color: #999999;
    background-color: var(--chat-card-hover);
}

.chat-shell-little-bird .chat-sidebar-open {
    box-shadow: 10px 0 40px rgba(0, 0, 0, 0.12);
}

.chat-shell-little-bird .beta-tag {
    border-color: #dddddd;
    background-color: #f5f5f5;
    color: #666666;
}

.chat-shell-little-bird .assistant-avatar {
    border-color: var(--chat-edge);
    background: #ffffff;
}

.chat-shell-little-bird .assistant-avatar .little-bird-icon {
    width: 2.125rem;
    height: 2.125rem;
}

.chat-shell-little-bird .chat-sidebar-brand-icon {
    width: 2.25rem;
    height: 2.25rem;
}

.chat-shell-little-bird .assistant-bubble {
    border-color: #e5e5e5;
    background-color: var(--chat-bubble);
    color: #151515;
}

.chat-shell-little-bird .assistant-link {
    color: #151515;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.chat-shell-little-bird .chat-dark-button {
    border-color: #d5d5d5;
    color: #333333;
}

.chat-shell-little-bird .chat-dark-button:not(:disabled):hover {
    border-color: #999999;
}

.chat-shell-little-bird .merlin-card {
    color: #333333;
}

.chat-shell-little-bird .chat-follow-ups {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.chat-shell-little-bird .chat-follow-ups .merlin-card {
    width: auto;
    padding: 0.75rem 1rem;
    border-color: #d5d5d5;
    border-radius: 9999px;
    font-size: 0.875rem;
}

.chat-shell-little-bird .chat-follow-ups .merlin-card:not(:disabled):hover {
    transform: none;
    border-color: #999999;
}

.chat-shell-little-bird .chat-follow-ups .merlin-card-text {
    font-size: 0.875rem;
    font-weight: 400;
}

.chat-shell-little-bird .chat-follow-ups .merlin-card-icon,
.chat-shell-little-bird .chat-follow-ups .merlin-card-arrow {
    display: none;
}

.chat-shell-little-bird :deep(.p-tab) {
    color: #777777;
}

.chat-shell-little-bird :deep(.p-tab:not(.p-tab-active):hover),
.chat-shell-little-bird :deep(.p-tab-active) {
    color: #151515;
}

.chat-shell-little-bird :deep(.p-tablist-nav-button) {
    color: #151515;
}

.chat-shell-little-bird :deep(.merlin-thinking-usually) {
    color: var(--chat-muted);
}

.chat-shell-little-bird .chat-send:hover:not(:disabled) {
    background-color: #333333;
}
</style>
