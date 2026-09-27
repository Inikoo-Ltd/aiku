<script setup lang="ts">
import { inject, computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowRight, faExclamationTriangle, faAlarmClock, faSeedling, faCrown, faPeopleArrows, faCommentExclamation } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faArrowRight, faExclamationTriangle, faAlarmClock, faSeedling, faCrown, faPeopleArrows, faCommentExclamation)

interface CustomerRow {
    slug: string
    name: string
    invoices: number
    sales: number
    last_invoiced_at?: string | null
    first_order_date?: string | null
    expected_next_order?: string | null
}

interface SisterShops {
    currency_code: string
    buyers: number
    shared_buyers: number
    sales_here: number
    sales_there: number
    shops: { code: string, name: string, customers: number, sales: number }[]
    top: { slug: string, name: string, sales_here: number, sales_there: number, shops: string[] }[]
}

interface Problems {
    days: number
    conversations: number
    classified: number
    problems: number
    by_topic: { topic: string, label: string, chat: number, email: number }[]
}

interface CustomersDashboard {
    pending?: boolean
    problems?: Problems
    hydrated_at?: string
    sister_shops?: SisterShops
    currency_code: string
    month_label: string
    base: { ordered: number, active: number, losing: number, lost: number, never_ordered: number, one_order: number, repeat: number }
    this_month: { registrations: number, registrations_last_year: number, registrations_with_orders: number, registrations_with_orders_last_year: number }
    at_risk: CustomerRow[]
    overdue: CustomerRow[]
    new_customers: CustomerRow[]
    top_customers: CustomerRow[]
    routes: { customers: { name: string, parameters: Record<string, string> }, customer: { name: string, parameters: Record<string, string> } }
}

const props = defineProps<{
    data?: CustomersDashboard
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (amount: number, currency?: string) =>
    new Intl.NumberFormat(undefined, { style: "currency", currency: currency ?? props.data?.currency_code ?? "GBP", maximumFractionDigits: 0 }).format(Math.round(amount))
const sister = computed(() => props.data?.sister_shops)
const sisterShare = computed(() => (sister.value?.buyers ? Math.round((sister.value.shared_buyers / sister.value.buyers) * 100) : 0))
const count = (value: number) => locale.number(value)
const share = (value: number) => (props.data?.base.ordered ? Math.round((value / props.data.base.ordered) * 100) : 0)
const date = (value?: string | null) => (value ? new Date(value).toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" }) : "–")
const customerHref = (slug: string) => route(props.data!.routes.customer.name, { ...props.data!.routes.customer.parameters, customer: slug })

const change = (current: number, lastYear: number) => {
    if (!lastYear) {
        return null
    }
    return Math.round(((current - lastYear) / lastYear) * 100)
}

const baseCards = computed(() => props.data ? [
    { label: ctrans("Buying regularly"), hint: ctrans("Ordering as often as usual"), value: props.data.base.active, colour: "text-green-600", isShare: true },
    { label: ctrans("Slipping away"), hint: ctrans("Ordering less often than they used to"), value: props.data.base.losing, colour: "text-amber-600", isShare: true },
    { label: ctrans("Stopped buying"), hint: ctrans("No order for a long time"), value: props.data.base.lost, colour: "text-red-600", isShare: true },
    { label: ctrans("Never ordered"), hint: ctrans("Registered but no order yet"), value: props.data.base.never_ordered, colour: "text-gray-500", isShare: false },
] : [])

const monthCards = computed(() => props.data ? [
    { label: ctrans("New registrations"), value: props.data.this_month.registrations, lastYear: props.data.this_month.registrations_last_year },
    { label: ctrans("Registered and ordered"), value: props.data.this_month.registrations_with_orders, lastYear: props.data.this_month.registrations_with_orders_last_year },
] : [])

const lists = computed(() => props.data ? [
    {
        key: "at_risk",
        title: ctrans("Big customers slipping away"),
        hint: ctrans("Worth a call: good customers ordering less than they used to"),
        icon: "fal fa-exclamation-triangle",
        rows: props.data.at_risk,
        detail: (row: CustomerRow) => ctrans("Last order :date", { date: date(row.last_invoiced_at) }),
        amount: (row: CustomerRow) => money(row.sales),
        amountHint: ctrans("spent in total"),
    },
    {
        key: "overdue",
        title: ctrans("Should have reordered by now"),
        hint: ctrans("Regular customers whose next order is late"),
        icon: "fal fa-alarm-clock",
        rows: props.data.overdue,
        detail: (row: CustomerRow) => ctrans("Expected around :date", { date: date(row.expected_next_order) }),
        amount: (row: CustomerRow) => money(row.sales),
        amountHint: ctrans("spent in total"),
    },
    {
        key: "new_customers",
        title: ctrans("New customers in :month", { month: props.data.month_label }),
        hint: ctrans("Their first order was this month"),
        icon: "fal fa-seedling",
        rows: props.data.new_customers,
        detail: (row: CustomerRow) => ctrans("First order :date", { date: date(row.first_order_date) }),
        amount: (row: CustomerRow) => money(row.sales),
        amountHint: ctrans("spent so far"),
    },
    {
        key: "top_customers",
        title: ctrans("Top customers"),
        hint: ctrans("Who bought the most in the last 12 months"),
        icon: "fal fa-crown",
        rows: props.data.top_customers,
        detail: (row: CustomerRow) => ctrans(":count invoices", { count: String(row.invoices) }),
        amount: (row: CustomerRow) => money(row.sales),
        amountHint: ctrans("last 12 months"),
    },
] : [])
</script>

<template>
    <div v-if="!data" class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4" aria-busy="true">
        <div v-for="index in 8" :key="index" class="h-28 animate-pulse rounded-lg bg-gray-100" />
    </div>

    <div v-else-if="data.pending" class="p-8 text-center text-sm text-gray-500">
        {{ ctrans("The customer figures for this shop are being prepared. Check back in a few minutes.") }}
    </div>

    <div v-else class="space-y-4 p-4">
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-6">
            <div v-for="card in baseCards" :key="card.label" class="rounded-lg border bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-gray-700">{{ card.label }}</p>
                <p class="text-2xl font-bold" :class="card.colour">{{ count(card.value) }}</p>
                <p class="text-xs text-gray-500">
                    <template v-if="card.isShare">{{ ctrans(":percent% of customers who ordered", { percent: String(share(card.value)) }) }} · </template>{{ card.hint }}
                </p>
            </div>
            <div v-for="card in monthCards" :key="card.label" class="rounded-lg border bg-white p-4 shadow-sm">
                <p class="text-sm font-semibold text-gray-700">{{ card.label }} <span class="font-normal text-gray-400">{{ data.month_label }}</span></p>
                <p class="text-2xl font-bold">{{ count(card.value) }}</p>
                <p class="text-xs text-gray-500">
                    {{ ctrans(":count by this day last year", { count: count(card.lastYear) }) }}
                    <span
                        v-if="change(card.value, card.lastYear) !== null"
                        :class="change(card.value, card.lastYear)! < 0 ? 'text-red-600' : 'text-green-600'"
                    >({{ change(card.value, card.lastYear)! > 0 ? "+" : "" }}{{ change(card.value, card.lastYear) }}%)</span>
                </p>
            </div>
        </div>

        <p class="text-xs text-gray-500">
            {{ ctrans(":one ordered once, :repeat ordered more than once", { one: count(data.base.one_order), repeat: count(data.base.repeat) }) }}
            ·
            <Link :href="route(data.routes.customers.name, data.routes.customers.parameters)" class="text-indigo-600 hover:underline">{{ ctrans("All customers") }}</Link>
        </p>

        <div v-if="data.problems" class="rounded-lg border bg-white p-4 shadow-sm">
            <p class="flex items-center gap-2 text-lg font-bold">
                <FontAwesomeIcon icon="fal fa-comment-exclamation" fixed-width class="text-gray-400" aria-hidden="true" />
                {{ ctrans("Problems customers told us about") }}
                <span class="text-sm font-normal text-gray-400">{{ ctrans("last :days days, chat and email", { days: String(data.problems.days) }) }}</span>
            </p>
            <p class="mb-3 text-xs text-gray-500">
                {{ ctrans(":problems of :classified conversations were about a problem.", { problems: count(data.problems.problems), classified: count(data.problems.classified) }) }}
                <template v-if="data.problems.conversations > data.problems.classified">
                    {{ ctrans(":count more are not sorted by topic yet.", { count: count(data.problems.conversations - data.problems.classified) }) }}
                </template>
            </p>
            <p v-if="!data.problems.by_topic.length" class="text-sm italic text-gray-400">{{ ctrans("No problems reported") }}</p>
            <table v-else class="text-sm tabular-nums">
                <thead>
                    <tr class="text-xs text-gray-400">
                        <th class="py-1 pr-6 text-left font-normal"></th>
                        <th class="py-1 pr-6 text-right font-normal">{{ ctrans("Chat") }}</th>
                        <th class="py-1 pr-6 text-right font-normal">{{ ctrans("Email") }}</th>
                        <th class="py-1 text-right font-normal">{{ ctrans("Total") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in data.problems.by_topic" :key="row.topic">
                        <td class="py-1 pr-6">{{ row.label }}</td>
                        <td class="py-1 pr-6 text-right">{{ count(row.chat) }}</td>
                        <td class="py-1 pr-6 text-right">{{ count(row.email) }}</td>
                        <td class="py-1 text-right font-semibold">{{ count(row.chat + row.email) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div v-for="list in lists" :key="list.key" class="flex flex-col rounded-lg border bg-white p-4 shadow-sm">
                <div class="mb-3">
                    <p class="flex items-center gap-2 text-lg font-bold">
                        <FontAwesomeIcon :icon="list.icon" fixed-width class="text-gray-400" aria-hidden="true" />
                        {{ list.title }}
                    </p>
                    <p class="text-xs text-gray-500">{{ list.hint }}</p>
                </div>

                <p v-if="!list.rows.length" class="flex flex-1 items-center justify-center text-sm italic text-gray-400">
                    {{ ctrans("Nobody right now") }}
                </p>

                <ul v-else class="divide-y text-sm">
                    <li v-for="row in list.rows" :key="row.slug" class="flex items-center justify-between gap-3 py-1.5">
                        <div class="min-w-0">
                            <Link :href="customerHref(row.slug)" class="block truncate font-medium text-gray-800 hover:text-indigo-600 hover:underline">{{ row.name }}</Link>
                            <p class="text-xs text-gray-500">{{ list.detail(row) }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-semibold">{{ list.amount(row) }}</p>
                            <p class="text-xs text-gray-400">{{ list.amountHint }}</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="sister?.shared_buyers" class="rounded-lg border bg-white p-4 shadow-sm">
            <p class="flex items-center gap-2 text-lg font-bold">
                <FontAwesomeIcon icon="fal fa-people-arrows" fixed-width class="text-gray-400" aria-hidden="true" />
                {{ ctrans("Also buying in our other shops") }}
            </p>
            <p class="mb-4 text-sm text-gray-600">
                {{ ctrans(":shared of our :buyers customers (:percent%) also order from sister shops with the same email. All time, they spent :here here and :there there.", {
                    shared: count(sister.shared_buyers),
                    buyers: count(sister.buyers),
                    percent: String(sisterShare),
                    here: money(sister.sales_here, sister.currency_code),
                    there: money(sister.sales_there, sister.currency_code),
                }) }}
            </p>

            <div class="grid gap-6 lg:grid-cols-2">
                <div>
                    <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Which shops") }}</p>
                    <ul class="space-y-1.5 text-sm">
                        <li v-for="shop in sister.shops.slice(0, 10)" :key="shop.code" class="relative">
                            <div class="absolute inset-y-0 left-0 rounded bg-indigo-100/70" :style="{ width: Math.round((shop.customers / sister.shops[0].customers) * 100) + '%' }" />
                            <div class="relative flex items-center justify-between gap-2 px-1.5 py-0.5">
                                <span class="truncate">{{ shop.name }} <span class="text-xs text-gray-400">{{ shop.code }}</span></span>
                                <span class="shrink-0 text-xs text-gray-500">
                                    {{ ctrans(":count customers", { count: count(shop.customers) }) }} · <span class="font-semibold text-gray-700">{{ money(shop.sales, sister.currency_code) }}</span>
                                </span>
                            </div>
                        </li>
                    </ul>
                    <p v-if="sister.shops.length > 10" class="mt-1 text-xs text-gray-400">{{ ctrans("and :count more shops", { count: String(sister.shops.length - 10) }) }}</p>
                </div>
                <div>
                    <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Spending more in other shops") }}</p>
                    <ul class="divide-y text-sm">
                        <li v-for="row in sister.top" :key="row.slug" class="flex items-center justify-between gap-3 py-1.5">
                            <div class="min-w-0">
                                <Link :href="customerHref(row.slug)" class="block truncate font-medium text-gray-800 hover:text-indigo-600 hover:underline">{{ row.name }}</Link>
                                <p class="text-xs text-gray-500">{{ ctrans("Also in :shops", { shops: row.shops.join(", ") }) }}</p>
                            </div>
                            <div class="shrink-0 text-right text-xs text-gray-500">
                                <p>{{ ctrans(":amount here", { amount: money(row.sales_here, sister.currency_code) }) }}</p>
                                <p class="font-semibold text-gray-700">{{ ctrans(":amount there", { amount: money(row.sales_there, sister.currency_code) }) }}</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
