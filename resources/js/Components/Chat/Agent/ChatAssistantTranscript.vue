<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronDown, faChevronUp, faThumbsDown, faUndo } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { formatChatTime } from "@/Composables/chatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

/**
 * What the AI shopping assistant said before it passed this chat to a person, at the top of the
 * conversation. Collapsed it shows the question that was handed over; staff can mark an answer
 * of the assistant as wrong.
 */
const props = defineProps<{
    ulid: string
    baseUrl: string
    handOver: { reason_label: string; route_label: string; name: string }
}>()

interface TranscriptMessage {
    id: number
    role: "customer" | "assistant"
    body: string | null
    created_at: string
    answer_kind: string | null
    answer_kind_label: string | null
    sources: { title: string; url: string | null }[]
    products: { code: string; name: string }[]
    hand_over_offer: string | null
    handed_over: boolean
    staff_rating: "wrong" | null
}

interface Transcript {
    assistant_name: string
    conversation: string
    hand_overs: { question: string | null }[]
    can_rate: boolean
    messages: TranscriptMessage[]
}

const transcript = ref<Transcript | null>(null)
const isLoading = ref(false)
const loadError = ref("")
const expanded = ref(false)
const ratingBusyId = ref<number | null>(null)
const ratingError = ref("")

const load = async () => {
    isLoading.value = true
    loadError.value = ""
    transcript.value = null
    const ulid = props.ulid

    try {
        const { data } = await axios.get(`${props.baseUrl}/app/api/chats/sessions/${ulid}/assistant-transcript`)

        if (ulid === props.ulid) {
            transcript.value = data
        }
    } catch {
        if (ulid === props.ulid) {
            loadError.value = ctrans("The assistant transcript could not be loaded")
        }
    } finally {
        if (ulid === props.ulid) {
            isLoading.value = false
        }
    }
}

watch(() => props.ulid, () => {
    expanded.value = false
    ratingError.value = ""
    load()
}, { immediate: true })

const rate = async (message: TranscriptMessage, rating: "wrong" | null) => {
    ratingBusyId.value = message.id
    ratingError.value = ""

    try {
        const { data } = await axios.post(
            `${props.baseUrl}/app/api/chats/sessions/${props.ulid}/assistant-transcript/${message.id}/rating`,
            { rating }
        )
        message.staff_rating = data.staff_rating
    } catch {
        ratingError.value = ctrans("The rating could not be saved")
    } finally {
        ratingBusyId.value = null
    }
}

const timeLabel = (iso: string) => formatChatTime(new Date(iso).getTime())
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white text-sm">
        <div class="flex items-center gap-2 px-3 py-2">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 text-gray-800">
                    <span class="font-semibold">{{ ctrans(":name handed this chat over", { name: handOver.name }) }}</span>
                    <span class="text-xs text-gray-500">{{ handOver.reason_label }}</span>
                    <span class="text-xs text-gray-400">{{ handOver.route_label }}</span>
                </div>
                <p v-if="!expanded && transcript?.hand_overs?.[0]?.question" class="mt-0.5 truncate text-xs text-gray-500">
                    {{ ctrans("Question") }}: {{ transcript.hand_overs[0].question }}
                </p>
            </div>
            <Button type="tertiary" size="xs" :disabled="isLoading || !transcript" :aria-expanded="expanded" @click="expanded = !expanded"
                :label="expanded ? ctrans('Hide transcript') : ctrans('Show transcript')">
                <template #icon>
                    <LoadingIcon v-if="isLoading" />
                    <FontAwesomeIcon v-else :icon="expanded ? faChevronUp : faChevronDown" class="text-gray-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </div>

        <div v-if="isLoading" class="space-y-2 border-t border-gray-100 px-3 py-2" aria-busy="true" :aria-label="ctrans('Loading the assistant transcript')">
            <span class="skeleton block h-3 w-1/2 rounded" />
            <span class="skeleton block h-3 w-3/4 rounded" />
        </div>

        <p v-if="loadError" class="border-t border-gray-100 px-3 py-2 text-xs text-red-600">{{ loadError }}</p>

        <div v-if="expanded && transcript" class="space-y-3 border-t border-gray-100 px-3 py-3">
            <template v-for="message in transcript.messages" :key="message.id">
                <div class="rounded-md px-3 py-2" :class="message.role === 'customer' ? 'bg-gray-50' : 'border border-gray-200'">
                    <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                        <span class="font-semibold text-gray-700">{{ message.role === 'customer' ? ctrans("Customer") : transcript.assistant_name }}</span>
                        <span>{{ timeLabel(message.created_at) }}</span>
                        <span v-if="message.answer_kind_label" class="rounded bg-gray-100 px-1.5 text-[11px] text-gray-600">{{ message.answer_kind_label }}</span>
                    </div>

                    <p class="whitespace-pre-line break-words text-gray-800">{{ message.body }}</p>

                    <ul v-if="message.products.length" class="mt-1.5 space-y-0.5 text-xs text-gray-600">
                        <li v-for="product in message.products" :key="product.code">
                            <span class="whitespace-nowrap font-medium">{{ product.code }}</span> {{ product.name }}
                        </li>
                    </ul>

                    <div v-if="message.sources.length" class="mt-1.5 flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                        <span>{{ ctrans("Sources") }}:</span>
                        <template v-for="(source, index) in message.sources" :key="index">
                            <a v-if="source.url" :href="source.url" target="_blank" rel="noopener" class="text-gray-600 hover:underline">{{ source.title }}</a>
                            <span v-else>{{ source.title }}</span>
                        </template>
                    </div>

                    <div v-if="message.role === 'assistant' && message.answer_kind && transcript.can_rate" class="mt-2 flex items-center gap-2">
                        <template v-if="message.staff_rating === 'wrong'">
                            <span class="text-xs text-gray-600">{{ ctrans("Marked as wrong") }}</span>
                            <Button type="tertiary" size="xs" :disabled="ratingBusyId === message.id" :label="ctrans('Undo')" @click="rate(message, null)">
                                <template #icon>
                                    <LoadingIcon v-if="ratingBusyId === message.id" />
                                    <FontAwesomeIcon v-else :icon="faUndo" class="text-gray-500" fixed-width aria-hidden="true" />
                                </template>
                            </Button>
                        </template>
                        <Button v-else type="tertiary" size="xs" :disabled="ratingBusyId === message.id" :label="ctrans('Wrong answer')" @click="rate(message, 'wrong')">
                            <template #icon>
                                <LoadingIcon v-if="ratingBusyId === message.id" />
                                <FontAwesomeIcon v-else :icon="faThumbsDown" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                    <p v-else-if="message.staff_rating === 'wrong'" class="mt-2 text-xs text-gray-600">{{ ctrans("Marked as wrong") }}</p>
                </div>

                <div v-if="message.handed_over" class="flex items-center gap-2 text-[11px] uppercase tracking-wide text-gray-400">
                    <span class="h-px flex-1 bg-gray-200" />
                    <span>{{ ctrans("Handed over") }}</span>
                    <span class="h-px flex-1 bg-gray-200" />
                </div>
            </template>
            <p v-if="ratingError" class="text-xs text-red-600">{{ ratingError }}</p>
        </div>
    </div>
</template>
