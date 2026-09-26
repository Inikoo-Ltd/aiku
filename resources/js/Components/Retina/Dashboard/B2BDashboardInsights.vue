<script setup lang="ts">
import { computed, inject, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { Bar } from "vue-chartjs"
import { Chart as ChartJS, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from "chart.js"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowUp, faArrowDown, faRedoAlt, faShoppingBasket, faTruck, faExclamationTriangle, faClock, faStar, faLightbulb, faBoxOpen, faCalendarCheck, faPlus, faCheck } from "@fal"
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

library.add(faArrowUp, faArrowDown, faRedoAlt, faShoppingBasket, faTruck, faExclamationTriangle, faClock, faStar, faLightbulb, faBoxOpen, faCalendarCheck, faPlus, faCheck)
ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

interface Regular {
    id: number
    code: string
    name: string
    image: Record<string, string> | null
    url: string | null
    price: number
    unit: string | null
    available_quantity: number
    stock_status: "in_stock" | "low" | "out_of_stock" | "unavailable"
    eta: string | null
    is_purchasable: boolean
    orders: number
    quantity: number
    spend: number
    average_quantity: number
    last_ordered_at: string
    reorder_every_days: number | null
    due_at: string | null
    days_until_due: number | null
    quantity_in_basket: number
}

interface RecentOrder {
    id: number
    slug: string
    reference: string
    date: string
    state: string
    state_label: string
    total: number
    items: number
}

interface Insights {
    currency_code: string
    kpis: {
        spend: number
        previous_spend: number
        orders: number
        previous_orders: number
        average_order: number | null
        previous_average: number | null
        order_every_days: number | null
        last_order_at: string | null
        days_since_last: number | null
        next_order_due_at: string | null
        is_lapsed: boolean
    }
    monthly: { month: string, spend: number, orders: number, previous_spend: number, previous_orders: number }[]
    regulars: Regular[]
    recent_orders: RecentOrder[]
    recommendations: Record<string, any>[]
    recommendations_source: "bought_together" | "shop_best_sellers"
}

const props = defineProps<{
    insights: Insights
    readOnly?: boolean
}>()

const layout = inject("layout", {})
const locale = useLocaleStore()

const money = (amount: number | null | undefined) => locale.currencyFormat(props.insights.currency_code, amount ?? 0)

const languageCode = computed(() => locale.language?.code || undefined)

const shortDate = (iso: string | null) => {
    if (!iso) return ""
    return new Intl.DateTimeFormat(languageCode.value, { day: "numeric", month: "short" }).format(new Date(iso))
}

const monthLabel = (month: string) => new Intl.DateTimeFormat(languageCode.value, { month: "short" }).format(new Date(`${month}-01T00:00:00`))

const change = (current: number | null, previous: number | null) => {
    if (current === null || !previous) return null
    return Math.round(((current - previous) / previous) * 100)
}

const kpiTiles = computed(() => {
    const k = props.insights.kpis
    return [
        { label: ctrans("Spent in the last 12 months"), value: money(k.spend), change: change(k.spend, k.previous_spend), hint: ctrans("excl. VAT") },
        { label: ctrans("Orders"), value: k.orders.toLocaleString(languageCode.value), change: change(k.orders, k.previous_orders), hint: ctrans("last 12 months") },
        { label: ctrans("Average order"), value: k.average_order ? money(k.average_order) : "—", change: change(k.average_order, k.previous_average), hint: ctrans("excl. VAT") },
    ]
})

const rhythm = computed(() => {
    const k = props.insights.kpis
    if (!k.last_order_at) return null
    return {
        every: k.order_every_days,
        daysSince: k.days_since_last ?? 0,
        isOverdue: !!k.order_every_days && (k.days_since_last ?? 0) > k.order_every_days,
        nextDue: k.next_order_due_at,
    }
})

const chartData = computed(() => ({
    labels: props.insights.monthly.map((m) => monthLabel(m.month)),
    datasets: [
        {
            label: ctrans("Previous year"),
            data: props.insights.monthly.map((m) => m.previous_spend),
            backgroundColor: "#cbd5e1",
            borderRadius: 4,
            borderSkipped: "start" as const,
            maxBarThickness: 18,
        },
        {
            label: ctrans("Last 12 months"),
            data: props.insights.monthly.map((m) => m.spend),
            backgroundColor: "#4f46e5",
            borderRadius: 4,
            borderSkipped: "start" as const,
            maxBarThickness: 18,
        },
    ],
}))

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index" as const, intersect: false },
    plugins: {
        legend: { position: "top" as const, align: "end" as const, labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, color: "#4b5563" } },
        tooltip: {
            callbacks: {
                label: (context: any) => {
                    const month = props.insights.monthly[context.dataIndex]
                    const orders = context.datasetIndex === 0 ? month.previous_orders : month.orders
                    return ` ${context.dataset.label}: ${money(context.parsed.y)} · ${ctrans(":count orders", { count: String(orders) })}`
                },
            },
        },
    },
    scales: {
        x: { grid: { display: false }, ticks: { color: "#6b7280" } },
        y: {
            beginAtZero: true,
            grid: { color: "#f1f5f9" },
            border: { display: false },
            ticks: {
                color: "#9ca3af",
                maxTicksLimit: 5,
                callback: (value: number) => new Intl.NumberFormat(languageCode.value, { notation: "compact", maximumFractionDigits: 1 }).format(value),
            },
        },
    },
}))

const hasMonthlySales = computed(() => props.insights.monthly.some((m) => m.spend > 0 || m.previous_spend > 0))

const dueSoon = computed(() =>
    props.insights.regulars
        .filter((r) => r.is_purchasable && r.stock_status !== "out_of_stock" && r.days_until_due !== null && r.days_until_due <= 7 && r.days_until_due >= -(r.reorder_every_days ?? 0) && !r.quantity_in_basket)
        .sort((a, b) => (a.days_until_due ?? 0) - (b.days_until_due ?? 0))
)
const runningLow = computed(() => props.insights.regulars.filter((r) => r.stock_status === "low" && r.is_purchasable))
const outOfStock = computed(() => props.insights.regulars.filter((r) => r.stock_status === "out_of_stock"))

const attentionCount = computed(() => dueSoon.value.length + runningLow.value.length + outOfStock.value.length)

const dueLabel = (days: number | null) => {
    if (days === null) return ""
    if (days < 0) return ctrans(":count days overdue", { count: String(Math.abs(days)) })
    if (days === 0) return ctrans("Due today")
    return ctrans("Due in :count days", { count: String(days) })
}

const stockBadge = (regular: Regular) => {
    switch (regular.stock_status) {
        case "low":
            return { label: ctrans("Only :count left", { count: String(regular.available_quantity) }), class: "bg-amber-50 text-amber-700 ring-amber-600/20" }
        case "out_of_stock":
            return regular.eta
                ? { label: ctrans("Back :date", { date: shortDate(regular.eta) }), class: "bg-sky-50 text-sky-700 ring-sky-600/20" }
                : { label: ctrans("Out of stock"), class: "bg-red-50 text-red-700 ring-red-600/20" }
        case "unavailable":
            return { label: ctrans("No longer available"), class: "bg-gray-100 text-gray-600 ring-gray-500/20" }
        default:
            return { label: ctrans("In stock"), class: "bg-emerald-50 text-emerald-700 ring-emerald-600/20" }
    }
}

const addingProductIds = ref<number[]>([])

const addToBasket = async (productId: number, quantityInBasket: number, quantity: number) => {
    if (props.readOnly) return
    addingProductIds.value.push(productId)
    try {
        await axios.post(route("retina.models.product.add-to-basket", { product: productId }), {
            quantity: quantityInBasket + quantity,
        })
        notify({ title: ctrans("Added to basket"), type: "success" })
        layout?.reload_handle?.()
        router.reload({ only: ["insights"] })
    } catch (error: any) {
        notify({
            title: ctrans("Could not add to basket"),
            text: error?.response?.data?.message || ctrans("Please try again"),
            type: "error",
        })
    } finally {
        addingProductIds.value = addingProductIds.value.filter((id) => id !== productId)
    }
}

const repeatingOrderId = ref<number | null>(null)

const repeatOrder = async (order: RecentOrder) => {
    if (props.readOnly) return
    repeatingOrderId.value = order.id
    try {
        const { data } = await axios.post(route("retina.models.order.repeat", { order: order.id }))
        notify({
            title: ctrans(":count products added to your basket", { count: String(data.added) }),
            text: data.skipped.length
                ? ctrans("Not available right now: :products", { products: data.skipped.map((p: { code: string }) => p.code).join(", ") })
                : undefined,
            type: data.added ? "success" : "warning",
        })
        if (data.added) {
            router.visit(route("retina.ecom.basket.show"))
        }
    } catch (error: any) {
        notify({
            title: ctrans("Could not repeat this order"),
            text: error?.response?.data?.message || ctrans("Please try again"),
            type: "error",
        })
    } finally {
        repeatingOrderId.value = null
    }
}

const hasHistory = computed(() => props.insights.kpis.orders > 0 || props.insights.recent_orders.length > 0)

const lastOrder = computed(() => props.insights.recent_orders[0] ?? null)

const bestSellersTitle = computed(() => props.insights.regulars.length < 3 ? ctrans("Products you have ordered") : ctrans("Your best sellers"))

const recommendationsTitle = computed(() => props.insights.recommendations_source === "shop_best_sellers" ? ctrans("Best sellers right now") : (props.insights.kpis.is_lapsed ? ctrans("New for you since your last order") : ctrans("Grow your range")))

const recommendationsSubtitle = computed(() => props.insights.recommendations_source === "shop_best_sellers"
    ? ctrans("What other shops are ordering most at the moment, a good place to start.")
    : ctrans("Sells well alongside your best sellers, and new to you."))

const moreCount = (list: unknown[], shown: number) => Math.max(0, list.length - shown)
</script>

<template>
    <div class="space-y-6">
        <div v-if="!hasHistory" class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center">
            <FontAwesomeIcon :icon="faBoxOpen" class="text-3xl text-gray-400" fixed-width aria-hidden="true" />
            <h3 class="mt-3 text-base font-semibold text-gray-900">{{ ctrans("Your business at a glance") }}</h3>
            <p class="mt-1 text-sm text-gray-500">
                {{ ctrans("Once you place your first order, you will see your spending, your best sellers and when to restock them here.") }}
            </p>
        </div>

        <template v-else>
            <div v-if="insights.kpis.is_lapsed" class="flex flex-col gap-4 rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50 to-white p-5 sm:flex-row sm:items-center">
                <div class="flex-1">
                    <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Welcome back, we have missed you") }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ ctrans("Your last order was :count days ago. Pick up where you left off, or see what is new for you below.", { count: String(insights.kpis.days_since_last) }) }}
                    </p>
                </div>
                <button
                    v-if="lastOrder"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                    :disabled="readOnly || repeatingOrderId === lastOrder.id"
                    @click="repeatOrder(lastOrder)"
                >
                    <FontAwesomeIcon :icon="faRedoAlt" fixed-width aria-hidden="true" :spin="repeatingOrderId === lastOrder.id" />
                    {{ ctrans("Order :reference again", { reference: lastOrder.reference }) }}
                </button>
            </div>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="tile in kpiTiles" :key="tile.label" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ tile.label }}</p>
                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900 tabular-nums">{{ tile.value }}</p>
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <span
                            v-if="tile.change !== null"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium"
                            :class="tile.change >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                        >
                            <FontAwesomeIcon :icon="tile.change >= 0 ? faArrowUp : faArrowDown" fixed-width aria-hidden="true" />
                            {{ Math.abs(tile.change) }}%
                        </span>
                        <span class="text-gray-400">{{ tile.change !== null ? ctrans("vs previous 12 months") : tile.hint }}</span>
                    </div>
                </div>

                <div v-if="rhythm" class="rounded-xl border p-5 shadow-sm" :class="rhythm.isOverdue ? 'border-amber-300 bg-amber-50/50' : 'border-gray-200 bg-white'">
                    <p class="flex items-center gap-2 text-sm text-gray-500">
                        <FontAwesomeIcon :icon="faCalendarCheck" fixed-width aria-hidden="true" />
                        {{ ctrans("Your ordering rhythm") }}
                    </p>
                    <template v-if="rhythm.every">
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900">{{ ctrans("Every :count days", { count: String(rhythm.every) }) }}</p>
                        <p class="mt-2 text-xs" :class="rhythm.isOverdue ? 'font-medium text-amber-700' : 'text-gray-500'">
                            <template v-if="rhythm.daysSince === 0">{{ ctrans("Last order today") }}</template>
                            <template v-else>{{ ctrans("Last order :count days ago", { count: String(rhythm.daysSince) }) }}</template>
                            <template v-if="rhythm.isOverdue"> · {{ ctrans("time to restock") }}</template>
                        </p>
                    </template>
                    <template v-else>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900">
                            <template v-if="rhythm.daysSince === 0">{{ ctrans("Today") }}</template>
                            <template v-else>{{ ctrans(":count days ago", { count: String(rhythm.daysSince) }) }}</template>
                        </p>
                        <p class="mt-2 text-xs text-gray-500">{{ ctrans("Your last order. Your rhythm shows here after a few more orders.") }}</p>
                    </template>
                </div>
            </section>

            <section v-if="hasMonthlySales" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex items-baseline justify-between">
                    <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Monthly spend") }}</h3>
                    <span class="text-xs text-gray-400">{{ ctrans("excl. VAT") }}</span>
                </div>
                <div class="mt-4 h-64">
                    <Bar :data="chartData" :options="chartOptions" :aria-label="ctrans('Monthly spend, last 12 months compared with the year before')" role="img" />
                </div>
            </section>

            <section v-if="attentionCount" class="rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Restock planner") }}</h3>
                    <p class="text-sm text-gray-500">{{ ctrans("Based on how often you order your best sellers and what we have in stock.") }}</p>
                </div>
                <div class="grid grid-cols-1 divide-y divide-gray-100 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                    <div class="p-5">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <FontAwesomeIcon :icon="faClock" class="text-indigo-500" fixed-width aria-hidden="true" />
                            {{ ctrans("Due for restock") }}
                            <span class="rounded-full bg-gray-100 px-2 text-xs font-medium text-gray-600">{{ dueSoon.length }}</span>
                        </h4>
                        <ul v-if="dueSoon.length" class="mt-3 space-y-3">
                            <li v-for="regular in dueSoon.slice(0, 6)" :key="regular.id" class="flex items-center gap-3">
                                <div class="h-10 w-10 flex-none overflow-hidden rounded-md bg-gray-50">
                                    <Image v-if="regular.image" :src="regular.image" :alt="regular.name" class="h-full w-full object-contain" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ regular.name }}</p>
                                    <p class="text-xs" :class="(regular.days_until_due ?? 0) < 0 ? 'text-amber-700' : 'text-gray-500'">
                                        {{ dueLabel(regular.days_until_due) }} · {{ ctrans("usually :count", { count: String(regular.average_quantity) }) }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="flex-none rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                    :disabled="readOnly || addingProductIds.includes(regular.id)"
                                    @click="addToBasket(regular.id, regular.quantity_in_basket, regular.average_quantity)"
                                >
                                    <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" /> {{ regular.average_quantity }}
                                </button>
                            </li>
                        </ul>
                        <p v-if="moreCount(dueSoon, 6)" class="mt-3 text-xs text-gray-500">{{ ctrans("and :count more", { count: String(moreCount(dueSoon, 6)) }) }}</p>
                        <p v-else class="mt-3 text-sm text-gray-400">{{ ctrans("Nothing due this week.") }}</p>
                    </div>

                    <div class="p-5">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" />
                            {{ ctrans("Order before it runs out") }}
                            <span class="rounded-full bg-gray-100 px-2 text-xs font-medium text-gray-600">{{ runningLow.length }}</span>
                        </h4>
                        <ul v-if="runningLow.length" class="mt-3 space-y-3">
                            <li v-for="regular in runningLow.slice(0, 6)" :key="regular.id" class="flex items-center gap-3">
                                <div class="h-10 w-10 flex-none overflow-hidden rounded-md bg-gray-50">
                                    <Image v-if="regular.image" :src="regular.image" :alt="regular.name" class="h-full w-full object-contain" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ regular.name }}</p>
                                    <p class="text-xs font-medium text-amber-700">{{ ctrans("Only :count left in stock", { count: String(regular.available_quantity) }) }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="flex-none rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                    :disabled="readOnly || addingProductIds.includes(regular.id)"
                                    @click="addToBasket(regular.id, regular.quantity_in_basket, Math.min(regular.average_quantity, regular.available_quantity))"
                                >
                                    <FontAwesomeIcon :icon="faPlus" fixed-width aria-hidden="true" /> {{ Math.min(regular.average_quantity, regular.available_quantity) }}
                                </button>
                            </li>
                        </ul>
                        <p v-if="moreCount(runningLow, 6)" class="mt-3 text-xs text-gray-500">{{ ctrans("and :count more", { count: String(moreCount(runningLow, 6)) }) }}</p>
                        <p v-else class="mt-3 text-sm text-gray-400">{{ ctrans("Your best sellers are well stocked.") }}</p>
                    </div>

                    <div class="p-5">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <FontAwesomeIcon :icon="faTruck" class="text-sky-500" fixed-width aria-hidden="true" />
                            {{ ctrans("Out of stock, on its way") }}
                            <span class="rounded-full bg-gray-100 px-2 text-xs font-medium text-gray-600">{{ outOfStock.length }}</span>
                        </h4>
                        <ul v-if="outOfStock.length" class="mt-3 space-y-3">
                            <li v-for="regular in outOfStock.slice(0, 6)" :key="regular.id" class="flex items-center gap-3">
                                <div class="h-10 w-10 flex-none overflow-hidden rounded-md bg-gray-50 opacity-60">
                                    <Image v-if="regular.image" :src="regular.image" :alt="regular.name" class="h-full w-full object-contain" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ regular.name }}</p>
                                    <p class="text-xs text-gray-500">
                                        <template v-if="regular.eta">{{ ctrans("Expected :date", { date: shortDate(regular.eta) }) }}</template>
                                        <template v-else>{{ ctrans("Arrival date to be confirmed") }}</template>
                                    </p>
                                </div>
                            </li>
                        </ul>
                        <p v-if="moreCount(outOfStock, 6)" class="mt-3 text-xs text-gray-500">{{ ctrans("and :count more", { count: String(moreCount(outOfStock, 6)) }) }}</p>
                        <p v-else class="mt-3 text-sm text-gray-400">{{ ctrans("All your best sellers are in stock.") }}</p>
                    </div>
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <section v-if="insights.regulars.length" class="rounded-xl border border-gray-200 bg-white shadow-sm xl:col-span-2">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900">
                            <FontAwesomeIcon :icon="faStar" class="text-yellow-500" fixed-width aria-hidden="true" />
                            {{ bestSellersTitle }}
                        </h3>
                        <Link v-if="!readOnly" :href="route('retina.ecom.interest.previously_ordered.index')" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            {{ ctrans("All previously ordered") }}
                        </Link>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-5 py-2 font-medium">{{ ctrans("Product") }}</th>
                                    <th class="px-3 py-2 text-right font-medium">{{ ctrans("Spent") }}</th>
                                    <th class="hidden px-3 py-2 text-right font-medium md:table-cell">{{ ctrans("Ordered") }}</th>
                                    <th class="hidden px-3 py-2 font-medium lg:table-cell">{{ ctrans("Reorder") }}</th>
                                    <th class="px-3 py-2 font-medium">{{ ctrans("Stock") }}</th>
                                    <th class="px-5 py-2"><span class="sr-only">{{ ctrans("Add to basket") }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="regular in insights.regulars.slice(0, 10)" :key="regular.id" class="hover:bg-gray-50/60">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="h-10 w-10 flex-none overflow-hidden rounded-md bg-gray-50">
                                                <Image v-if="regular.image" :src="regular.image" :alt="regular.name" class="h-full w-full object-contain" />
                                            </div>
                                            <div class="min-w-0">
                                                <a v-if="regular.url" :href="regular.url" class="block max-w-[16rem] truncate font-medium text-gray-900 hover:underline">{{ regular.name }}</a>
                                                <span v-else class="block max-w-[16rem] truncate font-medium text-gray-900">{{ regular.name }}</span>
                                                <span class="text-xs text-gray-500">{{ regular.code }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right tabular-nums text-gray-900">{{ money(regular.spend) }}</td>
                                    <td class="hidden px-3 py-3 text-right tabular-nums text-gray-600 md:table-cell">
                                        {{ ctrans(":count orders", { count: String(regular.orders) }) }}
                                    </td>
                                    <td class="hidden px-3 py-3 text-gray-600 lg:table-cell">
                                        <template v-if="regular.reorder_every_days">
                                            {{ ctrans("every :count days", { count: String(regular.reorder_every_days) }) }}
                                        </template>
                                        <template v-else>{{ ctrans("last :date", { date: shortDate(regular.last_ordered_at) }) }}</template>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="stockBadge(regular).class">
                                            {{ stockBadge(regular).label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <span v-if="regular.quantity_in_basket" class="inline-flex items-center gap-1 whitespace-nowrap text-xs font-medium text-emerald-700">
                                            <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                                            {{ ctrans(":count in basket", { count: String(regular.quantity_in_basket) }) }}
                                        </span>
                                        <button
                                            v-else-if="regular.is_purchasable && regular.stock_status !== 'out_of_stock'"
                                            type="button"
                                            class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                                            :disabled="readOnly || addingProductIds.includes(regular.id)"
                                            @click="addToBasket(regular.id, 0, regular.stock_status === 'low' ? Math.min(regular.average_quantity, regular.available_quantity) : regular.average_quantity)"
                                        >
                                            <FontAwesomeIcon :icon="faShoppingBasket" fixed-width aria-hidden="true" />
                                            {{ ctrans("Add :count", { count: String(regular.stock_status === 'low' ? Math.min(regular.average_quantity, regular.available_quantity) : regular.average_quantity) }) }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="insights.recent_orders.length" class="rounded-xl border border-gray-200 bg-white shadow-sm" :class="{ 'xl:col-span-3': !insights.regulars.length }">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">{{ ctrans("Recent orders") }}</h3>
                        <Link v-if="!readOnly" :href="route('retina.ecom.orders.index')" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            {{ ctrans("View all") }}
                        </Link>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="order in insights.recent_orders" :key="order.id" class="flex items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <span v-if="readOnly" class="text-sm font-medium text-gray-900">{{ order.reference }}</span>
                                <Link v-else :href="route('retina.ecom.orders.show', { order: order.slug })" class="text-sm font-medium text-gray-900 hover:underline">
                                    {{ order.reference }}
                                </Link>
                                <p class="text-xs text-gray-500">
                                    {{ shortDate(order.date) }} · {{ ctrans(":count items", { count: String(order.items) }) }} · {{ order.state_label }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm tabular-nums text-gray-900">{{ money(order.total) }}</p>
                                <button
                                    type="button"
                                    class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50"
                                    :disabled="readOnly || repeatingOrderId === order.id"
                                    @click="repeatOrder(order)"
                                >
                                    <FontAwesomeIcon :icon="faRedoAlt" fixed-width aria-hidden="true" :spin="repeatingOrderId === order.id" />
                                    {{ ctrans("Order again") }}
                                </button>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </template>

        <section v-if="insights.recommendations.length" id="recommendations" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="flex items-center gap-2 text-base font-semibold text-gray-900">
                <FontAwesomeIcon :icon="faLightbulb" class="text-amber-500" fixed-width aria-hidden="true" />
                {{ recommendationsTitle }}
            </h3>
            <p class="text-sm text-gray-500">{{ recommendationsSubtitle }}</p>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <div v-for="product in insights.recommendations" :key="product.id" class="group flex flex-col rounded-lg border border-gray-100 p-3 hover:border-gray-200 hover:shadow-sm">
                    <a :href="product.url" class="block aspect-square overflow-hidden rounded-md bg-gray-50">
                        <Image
                            v-if="product.web_images?.main"
                            :src="product.web_images.main.gallery ?? product.web_images.main.thumbnail ?? product.web_images.main.original"
                            :alt="product.name"
                            class="h-full w-full object-contain transition group-hover:scale-105"
                        />
                    </a>
                    <a :href="product.url" class="mt-2 line-clamp-2 text-sm font-medium text-gray-900 hover:underline">{{ product.name }}</a>
                    <div class="mt-auto flex items-center justify-between pt-2">
                        <div>
                            <p class="text-sm font-semibold tabular-nums text-gray-900">{{ money(product.discounted_price ?? product.price) }}</p>
                            <p v-if="product.margin" class="text-xs text-emerald-700">{{ ctrans("Margin :margin", { margin: String(product.margin) }) }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 p-1.5 text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                            :disabled="readOnly || addingProductIds.includes(product.id)"
                            :aria-label="ctrans('Add to basket')"
                            v-tooltip="ctrans('Add to basket')"
                            @click="addToBasket(product.id, 0, 1)"
                        >
                            <FontAwesomeIcon :icon="faShoppingBasket" fixed-width aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
