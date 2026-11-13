<script setup lang="ts">
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue"
import axios from "axios"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPaperPlane, faHatWizard } from "@fortawesome/free-solid-svg-icons"
import Image from "@common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { shoppingAssistantConversationKey } from "@/Iris/Composables/useShoppingAssistant"

type Card = { id: number, name: string, url?: string | null, web_images?: any }
type Message = {
    id: number
    role: "customer" | "assistant"
    body: string
    follow_ups?: string[]
    links?: { label: string, url: string }[]
    hand_over_offer?: string | null
    groups?: { must_haves?: Card[], add_ons?: Card[] }
}

const emit = defineEmits<{ (e: "leave-message"): void }>()

const layout: any = inject("layout", {})
const assistantName = computed(() => layout?.iris?.shopping_assistant?.name || "Merlin")

const CARDS_IN_CHAT = 4
const POLL_INTERVAL_MS = 1500
const POLL_LIMIT_MS = 150_000

const conversation = ref<string | null>(null)
const messages = ref<Message[]>([])
const draft = ref("")
const isPending = ref(false)
const errorMessage = ref("")
const scroller = ref<HTMLElement>()
let pollTimer: ReturnType<typeof setTimeout> | null = null

const jsonUrl = (name: string, params: Record<string, string>, fallback: string): string =>
    route().has(name) ? route(name, params) : fallback
const askUrl = () => jsonUrl("iris.json.shopping_assistant.ask", {}, "/json/shopping-assistant/ask")
const showUrl = (id: string) => jsonUrl("iris.json.shopping_assistant.show", { conversation: id }, `/json/shopping-assistant/${id}`)

const cardsOf = (message: Message): Card[] => [...(message.groups?.must_haves ?? []), ...(message.groups?.add_ons ?? [])]
const latestAssistantId = computed(() => [...messages.value].reverse().find((message) => message.role === "assistant")?.id ?? null)
const fullPageUrl = computed(() => conversation.value ? `/assistant?conversation=${conversation.value}` : "/assistant")

const stopPolling = () => {
    if (pollTimer) {
        clearTimeout(pollTimer)
        pollTimer = null
    }
}

const fetchConversation = async (after?: number) => {
    if (!conversation.value) {
        return null
    }
    const { data } = await axios.get(showUrl(conversation.value), { params: after ? { after } : {} })
    if (data.status === "cleared") {
        conversation.value = null
        messages.value = []
        return null
    }
    const known = new Set(messages.value.map((message) => message.id))
    const incoming: Message[] = (data.messages ?? []).filter((message: Message) => !known.has(message.id))
    messages.value = [...messages.value, ...incoming].sort((first, second) => first.id - second.id)

    return { pending: !!data.pending, hasNewAnswer: incoming.some((message) => message.role === "assistant") }
}

const startPolling = () => {
    stopPolling()
    isPending.value = true
    const deadline = Date.now() + POLL_LIMIT_MS
    const tick = async () => {
        if (Date.now() > deadline) {
            isPending.value = false
            errorMessage.value = ctrans("No answer came back in time. Please try again.")
            return
        }
        try {
            const result = await fetchConversation(messages.value[messages.value.length - 1]?.id)
            if (result && (!result.pending || result.hasNewAnswer)) {
                isPending.value = false
                return
            }
        } catch {
            // ponytail: a failed poll just tries again until the deadline
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
    isPending.value = true
    try {
        const { data } = await axios.post(askUrl(), { message, conversation: conversation.value, entry_point: "chat" })
        conversation.value = data.conversation
        sessionStorage.setItem(shoppingAssistantConversationKey, data.conversation)
        if (!messages.value.some((held) => held.id === data.message_id)) {
            messages.value.push({ id: data.message_id, role: "customer", body: message })
        }
        draft.value = ""
        startPolling()
    } catch (error: any) {
        const status = error?.response?.status
        if (status === 409 && conversation.value) {
            startPolling()
            return
        }
        isPending.value = false
        errorMessage.value = status === 429
            ? ctrans("You have asked a lot in a short time. Please try again in a minute.")
            : ctrans("Something went wrong. Please try again.")
    }
}

const onEnter = (event: KeyboardEvent) => {
    if (!event.shiftKey) {
        event.preventDefault()
        send(draft.value)
    }
}

watch(() => messages.value.length + (isPending.value ? 1 : 0), async () => {
    await nextTick()
    scroller.value?.scrollTo({ top: scroller.value.scrollHeight, behavior: "smooth" })
})

onMounted(async () => {
    const saved = sessionStorage.getItem(shoppingAssistantConversationKey)
    if (saved) {
        conversation.value = saved
        try {
            const result = await fetchConversation()
            if (result?.pending) {
                startPolling()
            }
        } catch {
            conversation.value = null
            messages.value = []
        }
    }
})

onBeforeUnmount(stopPolling)
</script>

<template>
    <div class="flex h-full min-h-0 flex-col bg-white">
        <div class="shrink-0 border-b px-4 py-3">
            <p class="text-sm font-semibold text-gray-800">
                {{ ctrans("We are closed now, but :name never sleeps.", { name: assistantName }) }}
            </p>
            <p class="mt-0.5 text-xs text-gray-500">
                {{ ctrans(":name is an AI assistant and can make mistakes.", { name: assistantName }) }}
            </p>
        </div>

        <div ref="scroller" class="min-h-[260px] flex-1 overflow-y-auto px-4 py-3 space-y-3">
            <div v-if="!messages.length" class="text-sm text-gray-600">
                <p>{{ ctrans("Hi, I am :name. Ask me anything: products, delivery, how things work. I answer right away.", { name: assistantName }) }}</p>
            </div>

            <template v-for="message in messages" :key="message.id">
                <div v-if="message.role === 'customer'" class="flex justify-end">
                    <p class="max-w-[85%] whitespace-pre-line rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-800">{{ message.body }}</p>
                </div>

                <div v-else class="flex gap-2">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[var(--app-accent-soft)] text-[var(--app-accent)]" aria-hidden="true">
                        <FontAwesomeIcon :icon="faHatWizard" class="text-xs" fixed-width />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="whitespace-pre-line text-sm text-gray-800">{{ message.body }}</p>

                        <a v-for="link in message.links ?? []" :key="link.url" :href="link.url" target="_blank" rel="noopener"
                            class="mt-1 block text-xs text-gray-600 underline hover:text-gray-800">
                            {{ ctrans("More about :name", { name: link.label }) }}
                        </a>

                        <div v-if="cardsOf(message).length" class="mt-2 space-y-1.5">
                            <a v-for="card in cardsOf(message).slice(0, CARDS_IN_CHAT)" :key="card.id" :href="card.url ?? fullPageUrl"
                                class="flex items-center gap-2 rounded border px-2 py-1.5 hover:bg-gray-50">
                                <span class="h-10 w-10 shrink-0 overflow-hidden rounded bg-gray-50">
                                    <Image v-if="card.web_images?.main?.gallery" :src="card.web_images.main.gallery" :alt="card.name" class="h-full w-full object-contain" />
                                </span>
                                <span class="line-clamp-2 text-xs text-gray-800">{{ card.name }}</span>
                            </a>
                            <a :href="fullPageUrl" class="block text-xs font-semibold text-gray-700 underline hover:text-gray-900">
                                {{ ctrans("See all suggestions with prices") }}
                            </a>
                        </div>

                        <a v-if="message.hand_over_offer" :href="fullPageUrl" class="mt-2 block text-xs font-semibold text-gray-700 underline hover:text-gray-900">
                            {{ ctrans("Pass this to our team") }}
                        </a>

                        <div v-if="message.id === latestAssistantId && message.follow_ups?.length && !isPending" class="mt-2 flex flex-wrap gap-1.5">
                            <button v-for="followUp in message.follow_ups" :key="followUp" type="button"
                                class="rounded-full border px-2.5 py-1 text-left text-xs text-gray-700 hover:bg-gray-50"
                                @click="send(followUp)">
                                {{ followUp }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="isPending" class="flex items-center gap-2 text-xs text-gray-500">
                <LoadingIcon />
                {{ ctrans(":name is thinking…", { name: assistantName }) }}
            </div>
            <p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>
        </div>

        <div class="shrink-0 border-t p-3">
            <div class="flex items-end gap-2">
                <Textarea v-model="draft" rows="2" autoResize class="flex-1 text-sm" :placeholder="ctrans('Ask :name anything', { name: assistantName })"
                    :disabled="isPending" @keydown.enter="onEnter" />
                <Button type="primary" :icon="faPaperPlane" :disabled="isPending || !draft.trim()" :aria-label="ctrans('Send')"
                    @click="send(draft)" />
            </div>
            <button type="button" class="mt-2 text-xs text-gray-500 underline hover:text-gray-700" @click="emit('leave-message')">
                {{ ctrans("Prefer a person? Leave a message for our team") }}
            </button>
        </div>
    </div>
</template>
