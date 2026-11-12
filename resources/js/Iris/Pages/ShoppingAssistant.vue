<script setup lang="ts">
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { get } from "lodash-es"
import Textarea from "primevue/textarea"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import TabPanels from "primevue/tabpanels"
import TabPanel from "primevue/tabpanel"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperPlane, faSignInAlt, faCommentAlt, faRedo, faShoppingBasket, faCheck, faSparkles, faPlus, faHistory, faTimes, faBars, faColumns, faEdit, faHeadset } from "@far"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { useLocaleStore } from "@/Stores/locale"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Image from "@/Common/Components/Image.vue"
import RenderProduct from "@/Iris/Components/IrisBlocks/Products/Ecom/RenderProduct.vue"
import { ProductResource } from "@/types/Iris/Products"
import {
    shoppingAssistantConversationKey,
    shoppingAssistantQuestionKey,
    useShoppingAssistant,
} from "@/Iris/Composables/useShoppingAssistant"
library.add(faPaperPlane, faSignInAlt, faCommentAlt, faRedo, faShoppingBasket, faCheck, faSparkles, faPlus, faHistory, faTimes, faBars, faColumns, faEdit, faHeadset)

type Card = ProductResource & {
    assistant_reason: string
    assistant_message_id: number
}

type Message =
    | { id: number, role: "customer", body: string, created_at: string }
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
        groups: { must_haves: Card[], add_ons: Card[] }
    }

type AssistantMessage = Extract<Message, { role: "assistant" }>

const props = defineProps<{
    web_block_family?: {
        fieldValue?: Record<string, any>
    }
    web_block_family_code?: string
    products_in_range?: number | null
    assistant_name?: string
    example_products?: { name: string, image: Record<string, any> | null }[]
}>()

const fieldValue = computed(() => props.web_block_family?.fieldValue)
const productCardCode = computed(() => props.web_block_family_code || "products-1")

const layout: any = inject("layout", retinaLayoutStructure)
const screenType = inject("screenType", ref<"mobile" | "tablet" | "desktop">("desktop"))

const { assistant } = useShoppingAssistant()

const locale = useLocaleStore()

const assistantName = computed(() => assistant.value?.name || props.assistant_name || ctrans("Range advisor"))

const isMounted = ref(false)
const isLayoutReady = computed(() => isMounted.value && !layout.iris_varnish?.isFetching)
const isLoggedOut = computed(() => isLayoutReady.value && !layout.iris?.is_logged_in)

const conversation = ref<string | null>(null)
const messages = ref<Message[]>([])
const isPending = ref(false)
const isRestoring = ref(false)
const errorMessage = ref("")
const draft = ref("")
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

const canTalkToAPerson = computed(() => !!assistant.value?.hand_over || handOverAvailable.value)

const showLanding = computed(() => !messages.value.length && !isPending.value && !isRestoring.value)
const isLandingVisible = computed(() => showLanding.value && !!assistant.value)
const isChatVisible = computed(() => !isLandingVisible.value && (!!assistant.value || messages.value.length > 0 || isPending.value))
const isMerlin = computed(() => assistantName.value.trim().toLowerCase() === "merlin")

const knowsTheRange = computed(() => props.products_in_range
    ? ctrans(":name knows all :count+ products in our range and what shops like yours buy most.", {
        name: assistantName.value,
        count: locale.number(props.products_in_range),
    })
    : ctrans(":name knows every product in our range and what shops like yours buy most.", { name: assistantName.value }))

const starterQuestions = computed(() => [
    ctrans("I run a crystal shop – what am I missing?"),
    ctrans("Low-priced Christmas gifts that sell well"),
    ctrans("Help me start bath & body"),
    ctrans("What did I use to order that still sells?"),
])

const exampleSuggestions = computed(() => props.example_products?.length === 3
    ? props.example_products.map((product) => ({ colour: "#f3f0ec", name: product.name, image: product.image, reason: ctrans("Sold well last Christmas") }))
    : placeholderSuggestions.value)

const placeholderSuggestions = computed(() => [
    { colour: "#e3ecef", name: ctrans("Aromatherapy Shower Steamers"), image: null, reason: ctrans("Popular with shops like yours") },
    { colour: "#f3e4e8", name: ctrans("Soap Flower Bouquet"), image: null, reason: ctrans("Sold well last Christmas") },
    { colour: "#ece6da", name: ctrans("Mini Singing Bowl"), image: null, reason: ctrans("Matches what you asked for") },
])

const exampleFollowUps = computed(() => [
    ctrans("Show me more incense"),
    ctrans("Anything for the till?"),
])

const steps = computed(() => [
    { title: ctrans("Tell us about your shop"), body: ctrans("What you sell, where you are, your customers and what you’d like to try.") },
    { title: ctrans("Get picks with a reason"), body: ctrans("Must-haves and add-ons to your range, each with a one-line reason – all live and in stock.") },
    { title: ctrans("Add to basket or keep chatting"), body: ctrans("Add them all in one click, ask a follow-up question or start a new conversation.") },
])

const promises = computed(() => [
    ctrans("Only products in stock now"),
    ctrans("Your own trade prices on every card"),
    ctrans("A reason for every suggestion"),
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

const POLL_INTERVAL_MS = 1500
const POLL_LIMIT_MS = 150_000
let pollTimer: ReturnType<typeof setTimeout> | null = null
let generation = 0

const stopPolling = () => {
    if (pollTimer !== null) {
        clearTimeout(pollTimer)
        pollTimer = null
    }
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

const send = async (text: string) => {
    const message = text.trim().slice(0, 1000)
    if (!message || isPending.value) {
        return
    }
    errorMessage.value = ""
    notice.value = ""
    isPending.value = true
    const requestGeneration = generation
    try {
        const { data } = await axios.post(askUrl(), { message, conversation: conversation.value })
        if (requestGeneration !== generation) {
            return
        }
        const isNewConversation = conversation.value !== data.conversation
        conversation.value = data.conversation
        sessionStorage.setItem(shoppingAssistantConversationKey, data.conversation)
        if (isNewConversation) {
            loadHistory()
        }
        if (!messages.value.some((held) => held.id === data.message_id)) {
            messages.value.push({ id: data.message_id, role: "customer", body: message, created_at: "" })
        }
        draft.value = ""
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
    draft.value = question
    if (!isPending.value) {
        send(question)
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

onMounted(() => {
    isMounted.value = true
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
            <section class="landing-hero">
                <div class="landing-container landing-hero-grid">
                    <div>
                        <div class="landing-eyebrow-row">
                            <span class="landing-badge" aria-hidden="true">
                                <FontAwesomeIcon icon="far fa-sparkles" fixed-width />
                            </span>
                            <p class="landing-eyebrow">{{ ctrans("Meet :name · Your range advisor", { name: assistantName }) }}</p>
                        </div>

                        <h1 class="landing-title">
                            {{ ctrans("Tell :name about your shop.", { name: assistantName }) }}<br>
                            {{ ctrans(":name will find what sells.", { name: assistantName }) }}
                        </h1>

                        <p class="landing-intro">
                            {{ knowsTheRange }}
                            {{ ctrans("Describe your shop – what you sell, where you are, who your customers are and what you’d like to try – and :name will suggest must-haves and add-ons from what is in stock now, each with a reason why.", { name: assistantName }) }}
                        </p>

                        <p v-if="isMerlin" class="landing-legend">
                            {{ ctrans("Named after the wise wizard of British legend – a fitting guide for a British company that has travelled the world for good finds since 1995.") }}
                        </p>

                        <div class="landing-ask">
                            <Textarea
                                v-model="draft"
                                rows="2"
                                auto-resize
                                maxlength="1000"
                                class="landing-textarea"
                                :aria-label="ctrans('Describe your shop')"
                                :placeholder="ctrans('e.g. We’re a small crystal and gift shop in York. I’d like Christmas gifts and to try bath & body.')"
                                @keydown.enter="sendOnEnter"
                            />
                            <div class="landing-ask-footer">
                                <p class="landing-hint">{{ ctrans("Write as much as you like – the more :name knows, the better the picks.", { name: assistantName }) }}</p>
                                <button
                                    type="button"
                                    class="landing-ask-button"
                                    :disabled="!!assistant && !draft.trim()"
                                    @click="askFromLanding(draft)"
                                >
                                    <FontAwesomeIcon :icon="assistant ? 'far fa-sparkles' : 'far fa-sign-in-alt'" fixed-width aria-hidden="true" />
                                    {{ assistant ? ctrans("Ask :name", { name: assistantName }) : ctrans("Log in to ask :name", { name: assistantName }) }}
                                </button>
                            </div>
                        </div>
                        <p v-if="errorMessage" class="mt-2 text-xs text-red-500">{{ errorMessage }}</p>
                        <p v-if="notice" class="mt-2 text-sm text-gray-600">{{ notice }}</p>

                        <div class="landing-chips">
                            <button
                                v-for="question in starterQuestions"
                                :key="question"
                                type="button"
                                class="landing-chip"
                                @click="askFromLanding(question)"
                            >
                                {{ question }}
                            </button>
                        </div>

                        <div v-if="canTalkToAPerson" class="landing-talk">
                            <div class="landing-talk-row">
                                <span class="text-sm text-gray-600">{{ ctrans("Prefer a person?") }}</span>
                                <button
                                    type="button"
                                    class="landing-chip landing-talk-button"
                                    :aria-expanded="isTalkBoxOpen"
                                    @click="isTalkBoxOpen = !isTalkBoxOpen"
                                >
                                    <FontAwesomeIcon icon="far fa-headset" fixed-width aria-hidden="true" />
                                    {{ ctrans("Talk to a person") }}
                                </button>
                            </div>

                            <div v-if="isTalkBoxOpen" class="chat-talk-box landing-talk-box">
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
                        </div>
                    </div>

                    <div class="landing-example">
                        <p class="landing-example-title">{{ ctrans("How a chat looks") }}</p>
                        <div class="landing-example-bubble">
                            {{ ctrans("Small yoga studio in Leeds. We sell a few candles and incense. Gifts that sell for Christmas?") }}
                        </div>
                        <div class="landing-example-reply">
                            <span class="assistant-avatar landing-example-avatar" aria-hidden="true"><FontAwesomeIcon icon="far fa-sparkles" fixed-width /></span>
                            <div class="min-w-0 flex-1">
                                <p class="landing-example-answer">{{ ctrans("Here are easy wins for a yoga studio – all in stock.") }}</p>

                                <div class="landing-example-tabs" aria-hidden="true">
                                    <span class="landing-example-tab landing-example-tab-active">{{ ctrans("Must-haves") }}</span>
                                    <span class="landing-example-tab">{{ ctrans("Add-ons to your range") }}</span>
                                </div>

                                <div class="landing-example-grid">
                                    <div v-for="suggestion in exampleSuggestions" :key="suggestion.name" class="landing-example-item">
                                        <p class="landing-example-reason">{{ suggestion.reason }}</p>
                                        <div class="landing-example-card">
                                            <span class="landing-example-thumb" :style="{ backgroundColor: suggestion.colour }" aria-hidden="true">
                                                <Image v-if="suggestion.image" :src="suggestion.image" :alt="suggestion.name" imageCover class="landing-example-image" />
                                            </span>
                                            <span class="landing-example-name">{{ suggestion.name }}</span>
                                            <span class="landing-example-add">{{ ctrans("Add to basket") }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="landing-example-actions">
                                    <span class="landing-example-pill">
                                        <FontAwesomeIcon icon="far fa-shopping-basket" class="text-green-600" fixed-width aria-hidden="true" />
                                        {{ ctrans("Add all to basket") }}
                                    </span>
                                    <span v-for="followUp in exampleFollowUps" :key="followUp" class="landing-example-pill landing-example-pill-round">
                                        <FontAwesomeIcon icon="far fa-comment-alt" class="text-gray-500" fixed-width aria-hidden="true" />
                                        {{ followUp }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="landing-container landing-steps">
                <div v-for="(step, index) in steps" :key="step.title" class="landing-step">
                    <span class="landing-step-number" aria-hidden="true">{{ index + 1 }}</span>
                    <div>
                        <p class="landing-step-title">{{ step.title }}</p>
                        <p class="landing-step-body">{{ step.body }}</p>
                    </div>
                </div>
            </section>

            <div class="landing-promises">
                <div class="landing-container landing-promises-row">
                    <span v-for="promise in promises" :key="promise">✓ {{ promise }}</span>
                    <span>{{ ctrans("Planning a big order? :name passes it to our team.", { name: assistantName }) }}</span>
                </div>
            </div>
        </div>

        <div v-else-if="isChatVisible" class="chat-shell" :class="{ 'chat-shell-collapsed': isSidebarCollapsed }">
            <aside class="chat-sidebar" :class="{ 'chat-sidebar-open': isHistoryOpen }">
                <div class="chat-sidebar-top">
                    <span class="chat-sidebar-brand">
                        <FontAwesomeIcon icon="far fa-sparkles" class="chat-sidebar-brand-icon" fixed-width aria-hidden="true" />
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
                <p v-else-if="!history.length" class="px-2 text-xs text-gray-500">{{ ctrans("Your conversations will show here.") }}</p>
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
                    <span class="assistant-avatar" aria-hidden="true"><FontAwesomeIcon icon="far fa-sparkles" fixed-width /></span>
                    <div class="min-w-0 flex-1">
                        <p class="chat-header-name">{{ assistantName }}</p>
                        <p class="chat-header-role">{{ ctrans("Your range advisor") }}</p>
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
                        <div v-if="message.role === 'customer'" class="flex justify-end">
                            <div class="assistant-bubble chat-text whitespace-pre-line">{{ message.body }}</div>
                        </div>

                        <div v-else class="flex gap-3">
                            <div class="assistant-avatar" aria-hidden="true"><FontAwesomeIcon icon="far fa-sparkles" fixed-width /></div>
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
                                                        <p class="mb-1 text-xs text-gray-600">{{ card.assistant_reason }}</p>
                                                        <div :style="getStyles(fieldValue?.card_product?.properties, screenType)" class="relative flex justify-center rounded">
                                                            <RenderProduct :code="productCardCode" :product="card"
                                                                :buttonStyle="getStyles(fieldValue?.button?.properties, screenType, false) ?? undefined"
                                                                :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType) ?? undefined"
                                                                :buttonStyleHover="getStyles(fieldValue?.buttonHover?.properties, screenType, false)"
                                                                :button="fieldValue?.button"
                                                                :hasInBasketList="get(layout, ['family_page', 'productInBasket', 'list'], [])"
                                                                :bestSeller="fieldValue?.bestseller" :screenType />
                                                        </div>
                                                    </div>
                                                </div>
                                            </TabPanel>
                                        </TabPanels>
                                    </Tabs>
                                    <div v-if="tabs.length" class="mt-3 flex flex-wrap items-center gap-2">
                                        <Button
                                            type="tertiary"
                                            size="xs"
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

                                <div v-if="message.id === latestAssistantId && message.follow_ups?.length" class="mt-4 flex flex-wrap gap-2">
                                    <button
                                        v-for="followUp in message.follow_ups"
                                        :key="followUp"
                                        type="button"
                                        class="landing-chip"
                                        :disabled="isPending"
                                        @click="send(followUp)"
                                    >
                                        {{ followUp }}
                                    </button>
                                </div>

                                <div v-if="message.hand_over_offer && (handOverAvailable || message.handed_over)" class="mt-3">
                                    <p v-if="message.handed_over" class="text-xs text-gray-500">{{ ctrans("Passed to our team") }}</p>
                                    <Button
                                        v-else
                                        type="tertiary"
                                        size="xs"
                                        :label="ctrans('Talk to our team')"
                                        :disabled="handingOver !== null || isPending"
                                        @click="handOver(message.id)"
                                    >
                                        <template #icon>
                                            <LoadingIcon v-if="handingOver === message.id" />
                                            <FontAwesomeIcon v-else icon="far fa-headset" class="text-gray-500" fixed-width aria-hidden="true" />
                                        </template>
                                    </Button>
                                    <p v-if="handOverErrors[message.id]" class="mt-1 text-xs text-red-500">{{ handOverErrors[message.id] }}</p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div v-if="isPending" class="flex gap-3">
                        <div class="assistant-avatar" aria-hidden="true"><FontAwesomeIcon icon="far fa-sparkles" fixed-width /></div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-600">
                                <LoadingIcon />
                                {{ ctrans("Looking through the range for your shop…") }}
                            </p>
                            <div class="skeleton assistant-skeleton mt-3 rounded" />
                        </div>
                    </div>

                    <p v-if="notice" class="text-sm text-gray-600">{{ notice }}</p>
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

                    <div class="chat-composer-box">
                        <Textarea
                            v-model="draft"
                            rows="1"
                            auto-resize
                            maxlength="1000"
                            class="landing-textarea chat-composer-input"
                            :aria-label="ctrans('Describe what your shop needs')"
                            :placeholder="ctrans('Ask :name a follow-up or describe what your shop needs', { name: assistantName })"
                            @keydown.enter="sendChatOnEnter"
                        />
                        <button
                            type="button"
                            class="chat-send"
                            :aria-label="ctrans('Send')"
                            :disabled="isPending || !draft.trim()"
                            @click="send(draft)"
                        >
                            <FontAwesomeIcon icon="far fa-paper-plane" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                    <p v-if="errorMessage" class="mt-1 text-xs text-red-500">{{ errorMessage }}</p>
                    <p v-else class="chat-composer-hint">{{ ctrans("Enter to send, Shift + Enter for a new line.") }}</p>
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
    background-color: var(--theme-color-4);
    color: var(--theme-color-5);
}

.assistant-bubble {
    max-width: 80%;
    padding: 0.625rem 0.875rem;
    border-radius: 0.75rem 0.75rem 0.25rem 0.75rem;
    line-height: 1.5;
    background-color: color-mix(in srgb, var(--theme-color-4) 6%, #f2ece6);
}

.chat-shell {
    --chat-gutter: max(1.5rem, calc((100% - 820px) / 2));
    position: relative;
    display: grid;
    width: 100%;
    height: min(85vh, 920px);
    min-height: 560px;
    border-top: 1px solid #ece7e1;
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
    padding: 0.75rem 0.625rem;
    background-color: color-mix(in srgb, var(--theme-color-4) 4%, #f9f6f2);
    border-right: 1px solid #ece7e1;
}

.chat-sidebar-open {
    display: block;
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 10;
    width: min(85%, 320px);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
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
    padding: 0 0.25rem 0.5rem 0.5rem;
}

.chat-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    min-width: 0;
    overflow: hidden;
    white-space: nowrap;
    font-size: 0.9375rem;
    font-weight: 800;
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
    color: #555;
    transition: background-color 0.15s;
}

.chat-icon-button:hover {
    background-color: rgba(0, 0, 0, 0.06);
    color: #1f1f1f;
}

.chat-sidebar-row {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.5rem;
    border-radius: 0.5rem;
    text-align: left;
    font-size: 0.875rem;
    font-weight: 500;
    color: #1f1f1f;
    transition: background-color 0.15s;
}

.chat-sidebar-row:hover {
    background-color: rgba(0, 0, 0, 0.06);
}

.chat-sidebar-title {
    margin: 1rem 0.5rem 0.25rem;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #555;
}

.chat-history-item {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    width: 100%;
    padding: 0.375rem 0.5rem;
    text-align: left;
    transition: background-color 0.15s;
}

.chat-history-item:hover {
    background-color: rgba(0, 0, 0, 0.04);
}

.chat-history-item-active {
    background-color: white;
    box-shadow: inset 2px 0 0 var(--theme-color-4);
}

.chat-history-title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.8125rem;
    color: #1f1f1f;
}

.chat-history-date {
    flex-shrink: 0;
    font-size: 0.6875rem;
    color: #777;
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
    padding: 1rem var(--chat-gutter);
    border-bottom: 1px solid #ece7e1;
}

.chat-header-name {
    font-size: 1.125rem;
    font-weight: 800;
    line-height: 1.2;
}

.chat-header-role {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--theme-color-4) 85%, black);
}

.chat-history-toggle {
    width: 2.5rem;
    height: 2.5rem;
    flex-shrink: 0;
    border: 1px solid #ddd5cc;
    border-radius: 9999px;
    color: #444;
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
    padding: 0.5rem 0.875rem;
    border: 1px solid #ddd5cc;
    border-radius: 9999px;
    font-size: 0.875rem;
    color: #1f1f1f;
}

.chat-header-talk {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0.875rem;
    border: 1px solid #ddd5cc;
    border-radius: 9999px;
    font-size: 0.875rem;
    color: #1f1f1f;
}

.chat-header-talk:hover {
    border-color: var(--theme-color-4);
}

.landing-talk {
    margin-top: 1rem;
    max-width: 42rem;
}

.landing-talk-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.landing-talk-button {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.landing-talk-box {
    margin-top: 0.75rem;
    background-color: white;
}

.chat-talk-box {
    margin-bottom: 0.75rem;
    padding: 1rem;
    border: 1px solid #e3ddd6;
    border-radius: 0.75rem;
    background-color: color-mix(in srgb, var(--theme-color-4) 4%, #f9f6f2);
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
    padding: 2rem var(--chat-gutter);
    display: flex;
    flex-direction: column;
    gap: 1.75rem;
}

.chat-text {
    font-size: 0.9375rem;
    line-height: 1.6;
}

.chat-composer {
    padding: 0.75rem var(--chat-gutter) 1.25rem;
    background-color: white;
}

.chat-composer-box {
    display: flex;
    align-items: flex-end;
    gap: 0.5rem;
    padding: 0.625rem 0.625rem 0.625rem 1rem;
    border: 1px solid #e3ddd6;
    border-radius: 1rem;
    background-color: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.chat-composer-box:focus-within {
    border-color: var(--theme-color-4);
}

.chat-composer-input {
    flex: 1;
    min-width: 0;
    max-height: 12rem;
    resize: none;
}

.chat-send {
    width: 2.75rem;
    height: 2.75rem;
    flex-shrink: 0;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--theme-color-4);
    color: var(--theme-color-5);
}

.chat-send:hover:not(:disabled) {
    background-color: color-mix(in srgb, var(--theme-color-4) 85%, black);
}

.chat-send:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.chat-composer-hint {
    margin-top: 0.5rem;
    text-align: center;
    font-size: 0.75rem;
    color: #888;
}

.landing-chip:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.landing-page {
    width: 100%;
    color: #1f1f1f;
}

.landing-container {
    max-width: 1280px;
    margin-left: auto;
    margin-right: auto;
    padding-left: 1rem;
    padding-right: 1rem;
}

.landing-hero {
    background-color: color-mix(in srgb, var(--theme-color-4) 4%, #f9f6f2);
    padding-top: 2.5rem;
    padding-bottom: 2.5rem;
}

.landing-hero-grid {
    display: grid;
    gap: 2.5rem;
    align-items: center;
}

@media (min-width: 1024px) {
    .landing-hero-grid {
        grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
        gap: 3rem;
    }
}

.landing-eyebrow-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.landing-badge {
    width: 3.25rem;
    height: 3.25rem;
    flex-shrink: 0;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background-color: #1f1f1f;
    color: var(--theme-color-4);
}

.landing-eyebrow {
    font-size: 0.8125rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--theme-color-4) 85%, black);
}

.landing-title {
    margin-top: 1.75rem;
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: -0.01em;
}

@media (min-width: 768px) {
    .landing-title {
        font-size: 2.5rem;
    }
}

.landing-intro {
    margin-top: 1.25rem;
    max-width: 38rem;
    font-size: 1rem;
    line-height: 1.65;
    color: #333;
}

.landing-legend {
    margin-top: 1rem;
    max-width: 38rem;
    font-size: 0.8125rem;
    font-style: italic;
    line-height: 1.6;
    color: #555;
}

.landing-ask {
    margin-top: 1rem;
    max-width: 42rem;
    padding: 0.75rem;
    border: 1px solid #e3ddd6;
    border-radius: 0.75rem;
    background-color: white;
}

.landing-textarea {
    width: 100%;
    border: 0;
    box-shadow: none;
    padding: 0.25rem;
    font-size: 0.9375rem;
    background: transparent;
}

.landing-textarea:focus {
    outline: none;
    box-shadow: none;
}

.landing-ask-footer {
    margin-top: 1.5rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.landing-hint {
    font-size: 0.6875rem;
    color: #555;
}

.landing-ask-button {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.625rem 1.25rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 700;
    background-color: var(--theme-color-4);
    color: var(--theme-color-5);
    transition: background-color 0.15s;
}

.landing-ask-button:hover:not(:disabled) {
    background-color: color-mix(in srgb, var(--theme-color-4) 85%, black);
}

.landing-ask-button:focus-visible {
    outline: 2px solid var(--theme-color-4);
    outline-offset: 2px;
}

.landing-ask-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.landing-chips {
    margin-top: 1rem;
    max-width: 42rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.landing-chip {
    padding: 0.4375rem 0.75rem;
    border: 1px solid #ddd5cc;
    border-radius: 9999px;
    font-size: 0.8125rem;
    background-color: white;
    color: #1f1f1f;
    transition: border-color 0.15s;
}

.landing-chip:hover {
    border-color: var(--theme-color-4);
}

.landing-chip:focus-visible {
    outline: 2px solid var(--theme-color-4);
    outline-offset: 2px;
}

.landing-example {
    padding: 1rem;
    border: 1px solid #e3ddd6;
    border-radius: 0.75rem;
    background-color: white;
}

.landing-example-title {
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #555;
}

.landing-example-bubble {
    margin-top: 0.75rem;
    margin-left: auto;
    max-width: 92%;
    padding: 0.625rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.8125rem;
    line-height: 1.5;
    background-color: color-mix(in srgb, var(--theme-color-4) 6%, #f2ece6);
}

.landing-example-reply {
    margin-top: 0.875rem;
    display: flex;
    gap: 0.625rem;
}

.landing-example-avatar {
    width: 1.75rem;
    height: 1.75rem;
    font-size: 0.75rem;
}

.landing-example-answer {
    font-size: 0.8125rem;
    line-height: 1.5;
}

.landing-example-tabs {
    margin-top: 0.625rem;
    display: flex;
    gap: 1rem;
    border-bottom: 1px solid #ece7e1;
}

.landing-example-tab {
    padding-bottom: 0.375rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: #777;
}

.landing-example-tab-active {
    color: var(--theme-color-0);
    box-shadow: inset 0 -2px 0 var(--theme-color-0);
}

.landing-example-grid {
    margin-top: 0.75rem;
    display: grid;
    gap: 0.625rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.landing-example-reason {
    min-height: 2rem;
    margin-bottom: 0.25rem;
    font-size: 0.625rem;
    line-height: 1.3;
    color: #555;
}

.landing-example-item {
    display: flex;
    flex-direction: column;
}

.landing-example-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 0.375rem;
    border: 1px solid #ece7e1;
    border-radius: 0.375rem;
}

.landing-example-thumb {
    display: block;
    aspect-ratio: 1 / 1;
    width: 100%;
    border-radius: 0.25rem;
}

.landing-example-thumb {
    overflow: hidden;
}

.landing-example-image,
.landing-example-image :deep(img) {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.landing-example-name {
    font-size: 0.6875rem;
    font-weight: 700;
    line-height: 1.3;
}

.landing-example-add {
    margin-top: auto;
    padding: 0.25rem;
    border-radius: 0.25rem;
    text-align: center;
    font-size: 0.625rem;
    font-weight: 600;
    background-color: var(--theme-color-4);
    color: var(--theme-color-5);
}

.landing-example-actions {
    margin-top: 0.75rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}

.landing-example-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.6875rem;
    color: #374151;
}

.landing-example-pill-round {
    border-radius: 9999px;
}

.landing-steps {
    display: grid;
    gap: 1.5rem;
    padding-top: 2.25rem;
    padding-bottom: 2.25rem;
}

@media (min-width: 768px) {
    .landing-steps {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.landing-step {
    display: flex;
    gap: 0.75rem;
}

.landing-step-number {
    width: 1.75rem;
    height: 1.75rem;
    flex-shrink: 0;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1.5px solid var(--theme-color-4);
    color: color-mix(in srgb, var(--theme-color-4) 85%, black);
}

.landing-step-title {
    font-size: 0.9375rem;
    font-weight: 700;
}

.landing-step-body {
    margin-top: 0.25rem;
    max-width: 18rem;
    font-size: 0.8125rem;
    line-height: 1.55;
    color: #444;
}

.landing-promises {
    border-top: 1px solid #ece7e1;
    border-bottom: 1px solid #ece7e1;
}

.landing-promises-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.5rem 1.5rem;
    padding-top: 0.875rem;
    padding-bottom: 0.875rem;
    font-size: 0.8125rem;
}

.assistant-link {
    color: var(--theme-color-0);
    text-decoration: underline;
}

.assistant-grid {
    display: grid;
    gap: 1.5rem 1rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
</style>
