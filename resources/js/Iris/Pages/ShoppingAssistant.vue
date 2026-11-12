<script setup lang="ts">
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { get } from "lodash-es"
import InputText from "primevue/inputtext"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import TabPanels from "primevue/tabpanels"
import TabPanel from "primevue/tabpanel"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperPlane, faSignInAlt, faCommentAlt, faRedo, faShoppingBasket, faCheck } from "@far"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import RenderProduct from "@/Iris/Components/IrisBlocks/Products/Ecom/RenderProduct.vue"
import { ProductResource } from "@/types/Iris/Products"
import {
    shoppingAssistantConversationKey,
    shoppingAssistantQuestionKey,
    useShoppingAssistant,
} from "@/Iris/Composables/useShoppingAssistant"
library.add(faPaperPlane, faSignInAlt, faCommentAlt, faRedo, faShoppingBasket, faCheck)

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
        groups: { must_haves: Card[], add_ons: Card[] }
    }

type AssistantMessage = Extract<Message, { role: "assistant" }>

const props = defineProps<{
    web_block_family?: {
        fieldValue?: Record<string, any>
    }
    web_block_family_code?: string
}>()

const fieldValue = computed(() => props.web_block_family?.fieldValue)
const productCardCode = computed(() => props.web_block_family_code || "products-1")

const layout: any = inject("layout", retinaLayoutStructure)
const screenType = inject("screenType", ref<"mobile" | "tablet" | "desktop">("desktop"))

const { assistant } = useShoppingAssistant()

const assistantName = computed(() => assistant.value?.name || ctrans("Range advisor"))
const assistantInitial = computed(() => assistantName.value.charAt(0).toUpperCase())

const isMounted = ref(false)
const isLayoutReady = computed(() => isMounted.value && !layout.iris_varnish?.isFetching)

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

const latestAssistantId = computed(() => [...messages.value].reverse().find((message) => message.role === "assistant")?.id ?? null)

const groupTabs = (message: AssistantMessage) => [
    { key: "must_haves", label: ctrans("Must-haves"), cards: message.groups?.must_haves ?? [] },
    { key: "add_ons", label: ctrans("Add-ons to your range"), cards: message.groups?.add_ons ?? [] },
].filter((tab) => tab.cards.length)

const jsonUrl = (name: string, params: Record<string, string>, fallback: string): string =>
    route().has(name) ? route(name, params) : fallback

const askUrl = () => jsonUrl("iris.json.shopping_assistant.ask", {}, "/json/shopping-assistant/ask")
const showUrl = (id: string) => jsonUrl("iris.json.shopping_assistant.show", { conversation: id }, `/json/shopping-assistant/${id}`)
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
        conversation.value = data.conversation
        sessionStorage.setItem(shoppingAssistantConversationKey, data.conversation)
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
    restoreThenAsk()
}

watch(assistant, startOnce)

let stopListeningToVisits: (() => void) | null = null

onMounted(() => {
    isMounted.value = true
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
</script>

<template>
    <div class="assistant-page px-4 py-8">
        <template v-if="assistant || messages.length || isPending">
            <div class="flex items-start justify-between gap-4">
                <h1 class="text-2xl font-bold">{{ assistantName }}</h1>
                <Button
                    v-if="conversation || messages.length"
                    type="tertiary"
                    size="xs"
                    class="shrink-0"
                    :label="ctrans('Start a new conversation')"
                    @click="forgetConversation"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="far fa-redo" class="text-gray-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>

            <div v-if="isRestoring && !messages.length" class="skeleton assistant-skeleton mt-6 rounded" />

            <p v-else-if="!messages.length && !isPending" class="mt-2 text-sm text-gray-600">
                {{ ctrans("Tell us about your shop and what you need, and we will suggest products from our range with a reason for each.") }}
            </p>

            <div class="mt-6 flex flex-col gap-6">
                <template v-for="message in messages" :key="message.id">
                    <div v-if="message.role === 'customer'" class="flex justify-end">
                        <div class="assistant-bubble whitespace-pre-line text-sm">{{ message.body }}</div>
                    </div>

                    <div v-else class="flex gap-3">
                        <div class="assistant-avatar" aria-hidden="true">{{ assistantInitial }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="whitespace-pre-line text-sm">{{ message.body }}</p>

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
                                <Button
                                    v-for="followUp in message.follow_ups"
                                    :key="followUp"
                                    type="tertiary"
                                    size="xs"
                                    class="rounded-full"
                                    :label="followUp"
                                    :disabled="isPending"
                                    @click="send(followUp)"
                                >
                                    <template #icon>
                                        <FontAwesomeIcon icon="far fa-comment-alt" class="text-gray-500" fixed-width aria-hidden="true" />
                                    </template>
                                </Button>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="isPending" class="flex gap-3">
                    <div class="assistant-avatar" aria-hidden="true">{{ assistantInitial }}</div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-600">
                            <LoadingIcon />
                            {{ ctrans("Looking through the range for your shop…") }}
                        </p>
                        <div class="skeleton assistant-skeleton mt-3 rounded" />
                    </div>
                </div>
            </div>

            <p v-if="notice" class="mt-6 text-sm text-gray-600">{{ notice }}</p>

            <div class="mt-6">
                <div class="flex gap-2">
                    <InputText
                        v-model="draft"
                        class="min-w-0 flex-1"
                        maxlength="1000"
                        :placeholder="ctrans('Describe what your shop needs')"
                        @keydown.enter.prevent="send(draft)"
                    />
                    <Button
                        :label="ctrans('Send')"
                        icon="far fa-paper-plane"
                        :disabled="isPending || !draft.trim()"
                        @click="send(draft)"
                    />
                </div>
                <p v-if="errorMessage" class="mt-1 text-xs text-red-500">{{ errorMessage }}</p>
            </div>
        </template>

        <div v-else-if="isLayoutReady && !layout.iris?.is_logged_in" class="flex flex-col items-start gap-3">
            <p class="text-sm">{{ ctrans("Log in to use the range advisor") }}</p>
            <Button :label="ctrans('Log in')" icon="far fa-sign-in-alt" @click="goToLogin" />
        </div>

        <p v-else-if="isLayoutReady" class="text-sm text-gray-600">
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
    border-radius: 1rem 1rem 0.25rem 1rem;
    background-color: color-mix(in srgb, var(--theme-color-0) 10%, white);
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
