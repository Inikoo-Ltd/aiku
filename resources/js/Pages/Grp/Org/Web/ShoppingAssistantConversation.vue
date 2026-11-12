<script setup lang="ts">
import { computed } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fas"
import { faExclamationTriangle } from "@fal"

library.add(faCheck, faExclamationTriangle)

type RouteRef = { name: string; parameters: Record<string, any> }

type Product = {
    id: number
    code: string
    name: string
    group: "must_haves" | "add_ons" | null
    reason: string | null
    clicked: boolean
    added: boolean
    ordered: boolean
    revenue: number
    route: RouteRef | null
}

type Message = {
    id: number
    role: "customer" | "assistant"
    body: string
    created_at: string
    follow_ups?: string[]
    answer_kind_label?: string | null
    sources?: { title: string; url: string | null }[]
    hand_over_offer_label?: string | null
    hand_over?: { route_label: string | null; in_hours: boolean | null; chat_route: RouteRef | null } | null
    wrong?: { by: string | null; at: string | null } | null
    flags?: string[]
    actions?: string[]
    latency_ms?: number | null
    cost?: number | null
    model?: string | null
    products?: Product[]
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    currency: string
    cost_currency: string
    attribution_days: number
    conversation: {
        ulid: string
        created_at: string
        last_message_at: string | null
        entry_point_label: string | null
        status_label: string | null
        language_code: string | null
        website: string | null
        customer: { name: string | null; reference: string | null; shop_type_label: string | null; country: string | null; route: RouteRef | null } | null
        handed_over: boolean
        messages_count: number
        model_calls: number
        prompt_tokens: number
        completion_tokens: number
        cost: number | null
        model: string | null
    }
    messages: Message[]
}>()

const locale = useLocaleStore()

const formatTime = (iso: string | null | undefined) => (iso ? useFormatTime(iso, { formatTime: "hm" }) : "")
const routeHref = (target: RouteRef | null | undefined) => (target ? route(target.name, target.parameters) : null)

const groupLabels: Record<string, string> = {
    must_haves: ctrans("Must-have"),
    add_ons: ctrans("Add-on"),
}

const summary = computed(() => [
    { label: ctrans("Shop type"), value: props.conversation.customer?.shop_type_label },
    { label: ctrans("Country"), value: props.conversation.customer?.country },
    { label: ctrans("Website"), value: props.conversation.website },
    { label: ctrans("Entry point"), value: props.conversation.entry_point_label },
    { label: ctrans("Started"), value: formatTime(props.conversation.created_at) },
    { label: ctrans("Status"), value: props.conversation.status_label },
    { label: ctrans("Messages"), value: String(props.conversation.messages_count ?? 0) },
    { label: ctrans("Passed to a person"), value: props.conversation.handed_over ? ctrans("Yes") : ctrans("No") },
    { label: ctrans("AI cost"), value: locale.currencyFormatUnitCost(props.cost_currency, props.conversation.cost ?? 0) },
    { label: ctrans("Model"), value: props.conversation.model },
])

const formatDate = (iso: string | null | undefined) => (iso ? useFormatTime(iso, { formatTime: "PP" }) : "")

const visibleFlags = (message: Message) => (message.flags ?? []).filter((flag) => flag !== "handed_over")

const detailLine = (message: Message) =>
    [
        message.latency_ms !== null && message.latency_ms !== undefined ? (message.latency_ms / 1000).toFixed(1) + " s" : null,
        message.cost !== null && message.cost !== undefined ? locale.currencyFormatUnitCost(props.cost_currency, message.cost) : null,
        message.model,
    ]
        .filter(Boolean)
        .join(" · ")
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="border-b border-gray-200 bg-white px-4 py-3">
        <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <div>
                <dt class="text-xs text-gray-500">{{ ctrans("Customer") }}</dt>
                <dd>
                    <Link v-if="routeHref(conversation.customer?.route)" :href="routeHref(conversation.customer?.route) ?? ''" class="primaryLink">
                        {{ conversation.customer?.name }}
                    </Link>
                    <span v-else-if="conversation.customer?.name">{{ conversation.customer.name }}</span>
                    <span v-else class="text-gray-400">{{ ctrans("Guest") }}</span>
                    <span v-if="conversation.customer?.reference" class="ml-1.5 whitespace-nowrap text-xs text-gray-500">{{ conversation.customer.reference }}</span>
                </dd>
            </div>
            <div v-for="item in summary" :key="item.label">
                <dt class="text-xs text-gray-500">{{ item.label }}</dt>
                <dd class="whitespace-nowrap text-gray-800" :class="item.value ? '' : 'text-gray-400'">{{ item.value || "—" }}</dd>
            </div>
        </dl>
    </div>

    <div class="mx-auto max-w-4xl space-y-4 p-4">
        <p v-if="!messages?.length" class="py-8 text-center text-sm text-gray-400">{{ ctrans("This conversation has no messages") }}</p>

        <div v-for="message in messages" :key="message.id" class="flex" :class="message.role === 'customer' ? 'justify-end' : 'justify-start'">
            <div v-if="message.role === 'customer'" class="max-w-[80%]">
                <div class="whitespace-pre-line rounded-lg bg-[var(--app-accent-soft)] px-3 py-2 text-sm text-gray-900">{{ message.body }}</div>
                <div class="mt-0.5 text-right text-xs text-gray-400">{{ formatTime(message.created_at) }}</div>
            </div>

            <div v-else class="w-full max-w-[90%]">
                <div class="rounded-lg border border-gray-200 bg-white px-3 py-2">
                    <div v-if="visibleFlags(message).length || message.answer_kind_label || message.hand_over_offer_label || message.hand_over || message.wrong" class="mb-1.5 flex flex-wrap gap-1">
                        <span v-if="message.answer_kind_label" class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ message.answer_kind_label }}</span>
                        <span v-if="message.hand_over_offer_label" class="inline-flex items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-800">
                            <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                            {{ ctrans("Offered a person: :reason", { reason: message.hand_over_offer_label }) }}
                        </span>
                        <span v-if="message.hand_over" class="inline-flex items-center gap-1 rounded bg-green-50 px-1.5 py-0.5 text-xs text-green-800">
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            {{ ctrans("Passed to a person: :route", { route: message.hand_over.route_label ?? "" }) }}<template v-if="message.hand_over.in_hours !== null"> · {{ message.hand_over.in_hours ? ctrans("in chat hours") : ctrans("outside chat hours") }}</template>
                        </span>
                        <span v-if="message.wrong" class="inline-flex items-center gap-1 rounded bg-red-50 px-1.5 py-0.5 text-xs text-red-800">
                            <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                            {{ ctrans("Marked as wrong by the chat team") }}<template v-if="message.wrong.by"> · {{ message.wrong.by }}</template><template v-if="message.wrong.at"> · {{ formatDate(message.wrong.at) }}</template>
                        </span>
                        <span v-for="flag in visibleFlags(message)" :key="flag" class="inline-flex items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-800">
                            <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                            {{ flag.replace(/_/g, " ") }}
                        </span>
                    </div>

                    <div v-if="message.actions?.length" class="mb-1.5 flex flex-wrap gap-1">
                        <span v-for="action in message.actions" :key="action" class="inline-flex items-center gap-1 rounded bg-green-50 px-1.5 py-0.5 text-xs text-green-800">
                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                            {{ action }}
                        </span>
                    </div>

                    <div class="whitespace-pre-line text-sm text-gray-800">{{ message.body }}</div>

                    <div v-if="message.sources?.length" class="mt-1.5 text-xs text-gray-500">
                        {{ ctrans("Answered from:") }}
                        <template v-for="(source, index) in message.sources" :key="index">
                            <template v-if="index > 0">, </template>
                            <a v-if="source.url" :href="source.url" target="_blank" rel="noopener" class="primaryLink">{{ source.title }}</a>
                            <span v-else class="text-gray-700">{{ source.title }}</span>
                        </template>
                    </div>

                    <div v-if="message.hand_over?.chat_route" class="mt-1.5 text-xs">
                        <Link :href="routeHref(message.hand_over.chat_route) ?? ''" class="primaryLink">{{ ctrans("Open the chat") }}</Link>
                    </div>

                    <div v-if="message.products?.length" class="mt-3 overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="whitespace-nowrap border-b border-gray-200 text-gray-400">
                                <tr>
                                    <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Code") }}</th>
                                    <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Product") }}</th>
                                    <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Group") }}</th>
                                    <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Reason") }}</th>
                                    <th class="w-14 pb-1 text-center font-medium">{{ ctrans("Clicked") }}</th>
                                    <th class="w-14 pb-1 text-center font-medium">{{ ctrans("Added") }}</th>
                                    <th class="w-24 pb-1 text-right font-medium">{{ ctrans("Ordered") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="product in message.products" :key="product.id" class="align-top">
                                    <td class="whitespace-nowrap py-1 pr-3">
                                        <Link v-if="routeHref(product.route)" :href="routeHref(product.route) ?? ''" class="primaryLink">{{ product.code }}</Link>
                                        <span v-else class="font-medium text-gray-900">{{ product.code }}</span>
                                    </td>
                                    <td class="py-1 pr-3 text-gray-700">{{ product.name }}</td>
                                    <td class="whitespace-nowrap py-1 pr-3 text-gray-500">{{ product.group ? groupLabels[product.group] ?? product.group : "" }}</td>
                                    <td class="py-1 pr-3 text-gray-500">{{ product.reason ?? "" }}</td>
                                    <td class="py-1 text-center">
                                        <FontAwesomeIcon v-if="product.clicked" :icon="faCheck" class="text-green-600" fixed-width :aria-label="ctrans('Clicked')" />
                                    </td>
                                    <td class="py-1 text-center">
                                        <FontAwesomeIcon v-if="product.added" :icon="faCheck" class="text-green-600" fixed-width :aria-label="ctrans('Added')" />
                                    </td>
                                    <td class="whitespace-nowrap py-1 text-right tabular-nums">
                                        <template v-if="product.ordered">
                                            <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width :aria-label="ctrans('Ordered')" />
                                            <span class="text-green-700">{{ locale.currencyFormat(currency, product.revenue ?? 0) }}</span>
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="message.follow_ups?.length" class="mt-2 flex flex-wrap gap-1">
                        <span v-for="followUp in message.follow_ups" :key="followUp" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ followUp }}</span>
                    </div>
                </div>
                <div class="mt-0.5 text-xs text-gray-400">
                    {{ formatTime(message.created_at) }}<template v-if="detailLine(message)"> · {{ detailLine(message) }}</template>
                </div>
            </div>
        </div>
    </div>
</template>
