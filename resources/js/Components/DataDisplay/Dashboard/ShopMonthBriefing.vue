<script setup lang="ts">
import { ref, computed, onMounted } from "vue"
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faThumbsUp, faExclamationTriangle, faHandPointRight, faPhone, faAlarmClock, faTruckLoading, faCommentExclamation, faArrowRight } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"

library.add(faThumbsUp, faExclamationTriangle, faHandPointRight, faPhone, faAlarmClock, faTruckLoading, faCommentExclamation, faArrowRight)

interface Mover { slug: string, name: string, change: number, sales: number, sales_last_year: number }
interface CustomerRow { slug: string, name: string, sales: number, invoices: number, last_invoiced_at?: string | null, expected_next_order?: string | null }

const props = defineProps<{
    fetchRoute: { name: string, parameters: Record<string, string> }
    currencyCode: string
    gap: number | null
    remainingDays: number
}>()

const data = ref<any>(null)
const isLoading = ref(true)

onMounted(async () => {
    try {
        const response = await axios.get(route(props.fetchRoute.name, props.fetchRoute.parameters), {
            params: { only: "department_movers,family_movers,out_of_stock_month,problems_month,customer_actions" },
        })
        data.value = response.data
    } finally {
        isLoading.value = false
    }
})

const format = (amount: number, currency: string) =>
    new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: 0 }).format(Math.round(amount))
const money = (amount: number) => format(amount, data.value?.currency_code ?? props.currencyCode)
const targetMoney = (amount: number) => format(amount, props.currencyCode)

const link = (key: string, param: string, value: string) => {
    const target = data.value?.routes?.[key]
    return target ? route(target.name, { ...target.parameters, [param]: value }) : null
}
const linkTo = (key: string) => {
    const target = data.value?.routes?.[key]
    return target ? route(target.name, target.parameters) : null
}

const usualOrder = (row: CustomerRow) => (row.invoices ? row.sales / row.invoices : 0)
const shareOfGap = (amount: number) => (props.gap && data.value?.currency_code === props.currencyCode ? Math.round((amount / props.gap) * 100) : null)

const growing = computed(() => [
    ...(data.value?.department_movers?.growing ?? []).slice(0, 3).map((row: Mover) => ({ ...row, kind: "department" })),
    ...(data.value?.family_movers?.growing ?? []).slice(0, 3).map((row: Mover) => ({ ...row, kind: "family" })),
])

const falling = computed(() => [
    ...(data.value?.department_movers?.falling ?? []).slice(0, 3).map((row: Mover) => ({ ...row, kind: "department" })),
    ...(data.value?.family_movers?.falling ?? []).slice(0, 3).map((row: Mover) => ({ ...row, kind: "family" })),
])

const outOfStock = computed(() => data.value?.out_of_stock_month)
const notOnOrder = computed(() => (outOfStock.value?.rows ?? []).filter((row: any) => !row.on_order))
const problems = computed(() => data.value?.problems_month)

const atRisk = computed<CustomerRow[]>(() => data.value?.customer_actions?.at_risk ?? [])
const overdue = computed<CustomerRow[]>(() => data.value?.customer_actions?.overdue ?? [])
const overdueWorth = computed(() => overdue.value.reduce((sum, row) => sum + usualOrder(row), 0))
const atRiskWorth = computed(() => atRisk.value.reduce((sum, row) => sum + usualOrder(row), 0))
</script>

<template>
    <DashboardWidgetBox storageKey="shop_dashboard_month_briefing_collapsed" class="mx-4 mb-4">
        <template #header>
            <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                <FontAwesomeIcon icon="fal fa-hand-point-right" class="text-indigo-600" fixed-width aria-hidden="true" />
                {{ ctrans("This month at a glance") }}
            </span>
            <span v-if="gap" class="text-xs text-gray-400">
                {{ ctrans(":gap still needed after the pipeline, :days days left", { gap: targetMoney(gap), days: String(remainingDays) }) }}
            </span>
        </template>

        <div v-if="isLoading" class="grid gap-4 lg:grid-cols-3">
            <div v-for="index in 3" :key="index" class="h-56 animate-pulse rounded-lg bg-gray-100" />
        </div>

        <div v-else-if="data" class="grid gap-6 lg:grid-cols-3">
            <div>
                <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-green-700">
                    <FontAwesomeIcon icon="fal fa-thumbs-up" fixed-width aria-hidden="true" />
                    {{ ctrans("Going well") }}
                </p>
                <p class="mb-2 text-xs text-gray-500">{{ ctrans("Selling more than the same days last year. Keep them in stock and on the home page.") }}</p>
                <p v-if="!growing.length" class="text-sm italic text-gray-400">{{ ctrans("Nothing growing yet this month") }}</p>
                <ul v-else class="space-y-1 text-sm">
                    <li v-for="row in growing" :key="row.kind + row.slug" class="flex items-center justify-between gap-2">
                        <Link :href="link(row.kind, row.kind, row.slug) ?? '#'" class="min-w-0 truncate hover:underline">
                            {{ row.name }} <span class="text-xs text-gray-400">{{ row.kind === "department" ? ctrans("department") : ctrans("family") }}</span>
                        </Link>
                        <span class="shrink-0 font-semibold tabular-nums text-green-600">+{{ money(row.change) }}</span>
                    </li>
                </ul>
            </div>

            <div class="lg:border-l lg:border-gray-100 lg:pl-6">
                <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-red-700">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
                    {{ ctrans("Holding us back") }}
                </p>
                <ul class="space-y-1 text-sm">
                    <li v-for="row in falling" :key="row.kind + row.slug" class="flex items-center justify-between gap-2">
                        <Link :href="link(row.kind, row.kind, row.slug) ?? '#'" class="min-w-0 truncate hover:underline">
                            {{ row.name }} <span class="text-xs text-gray-400">{{ row.kind === "department" ? ctrans("department") : ctrans("family") }}</span>
                        </Link>
                        <span class="shrink-0 font-semibold tabular-nums text-red-600">−{{ money(Math.abs(row.change)) }}</span>
                    </li>
                </ul>
                <p v-if="outOfStock?.estimated_lost" class="mt-3 text-sm">
                    <span class="font-semibold text-red-600">{{ ctrans("About :amount lost this month", { amount: money(outOfStock.estimated_lost) }) }}</span>
                    {{ ctrans("to :count products out of stock", { count: String(outOfStock.products) }) }}
                </p>
                <p v-if="problems?.problems" class="mt-2 text-sm">
                    <span class="font-semibold text-red-600">{{ ctrans(":count problems reported", { count: String(problems.problems) }) }}</span>
                    {{ ctrans("by customers this month") }}:
                    <span class="text-gray-500">{{ problems.by_topic.map((row: any) => `${row.label} ${row.chat + row.email}`).join(", ") }}</span>
                </p>
            </div>

            <div class="lg:border-l lg:border-gray-100 lg:pl-6">
                <p class="mb-2 flex items-center gap-2 text-sm font-semibold text-indigo-700">
                    <FontAwesomeIcon icon="fal fa-hand-point-right" fixed-width aria-hidden="true" />
                    {{ ctrans("What you can do today") }}
                </p>

                <div v-if="overdue.length" class="mb-3">
                    <p class="text-sm font-medium">
                        <FontAwesomeIcon icon="fal fa-alarm-clock" fixed-width class="text-gray-400" aria-hidden="true" />
                        {{ ctrans("Chase regulars late to reorder") }}
                    </p>
                    <p class="text-xs text-gray-500">
                        {{ ctrans("Their usual orders add up to :amount", { amount: money(overdueWorth) }) }}<template v-if="shareOfGap(overdueWorth) !== null">, {{ ctrans(":percent% of what is still needed", { percent: String(shareOfGap(overdueWorth)) }) }}</template>.
                    </p>
                    <ul class="mt-1 space-y-0.5 text-sm">
                        <li v-for="row in overdue" :key="row.slug" class="flex justify-between gap-2">
                            <Link :href="link('customer', 'customer', row.slug) ?? '#'" class="min-w-0 truncate hover:underline">{{ row.name }}</Link>
                            <span class="shrink-0 text-xs text-gray-500">{{ ctrans("usually :amount", { amount: money(usualOrder(row)) }) }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="atRisk.length" class="mb-3">
                    <p class="text-sm font-medium">
                        <FontAwesomeIcon icon="fal fa-phone" fixed-width class="text-gray-400" aria-hidden="true" />
                        {{ ctrans("Call big customers slipping away") }}
                    </p>
                    <p class="text-xs text-gray-500">{{ ctrans("One order from each is worth about :amount", { amount: money(atRiskWorth) }) }}.</p>
                    <ul class="mt-1 space-y-0.5 text-sm">
                        <li v-for="row in atRisk" :key="row.slug" class="flex justify-between gap-2">
                            <Link :href="link('customer', 'customer', row.slug) ?? '#'" class="min-w-0 truncate hover:underline">{{ row.name }}</Link>
                            <span class="shrink-0 text-xs text-gray-500">{{ ctrans("usually :amount", { amount: money(usualOrder(row)) }) }}</span>
                        </li>
                    </ul>
                </div>

                <div v-if="notOnOrder.length" class="mb-3">
                    <p class="text-sm font-medium">
                        <FontAwesomeIcon icon="fal fa-truck-loading" fixed-width class="text-gray-400" aria-hidden="true" />
                        {{ ctrans("Ask for these best sellers to be reordered") }}
                    </p>
                    <p class="text-xs text-gray-500">{{ ctrans("Out of stock and not on any purchase order") }}</p>
                    <ul class="mt-1 space-y-0.5 text-sm">
                        <li v-for="row in notOnOrder.slice(0, 5)" :key="row.slug" class="flex justify-between gap-2">
                            <Link :href="link('product', 'product', row.slug) ?? '#'" class="min-w-0 truncate hover:underline"><span class="mr-1 font-mono text-xs text-gray-500">{{ row.code }}</span>{{ row.name }}</Link>
                            <span class="shrink-0 text-xs text-red-600">{{ ctrans("−:amount this month", { amount: money(row.estimated_lost) }) }}</span>
                        </li>
                    </ul>
                </div>

                <p v-if="problems?.problems && linkTo('chat_sessions')" class="text-sm">
                    <FontAwesomeIcon icon="fal fa-comment-exclamation" fixed-width class="text-gray-400" aria-hidden="true" />
                    <Link :href="linkTo('chat_sessions')!" class="font-medium hover:underline">
                        {{ ctrans("Follow up the :count problem conversations", { count: String(problems.problems) }) }}
                        <FontAwesomeIcon icon="fal fa-arrow-right" class="text-xs" fixed-width aria-hidden="true" />
                    </Link>
                </p>
            </div>
        </div>
    </DashboardWidgetBox>
</template>
