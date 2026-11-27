<script setup lang="ts">
import { computed, ref } from "vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { Head, Link } from "@inertiajs/vue3"
import Chart from "primevue/chart"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxFragile, faTachometerAlt, faHourglassHalf, faArchive } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import CustomerClaimSettingsDialog, { type ClaimSettings } from "@/Components/CRM/CustomerClaimSettingsDialog.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faBoxFragile, faTachometerAlt, faHourglassHalf, faArchive)

type TopCustomer = { name: string, reference: string, route: { name: string, parameters: any }, claims: number, value: number, orders: number, share: number | null, invoiced: number, lost: number | null }

type Dashboard = {
    interval: string
    bucket: "day" | "week" | "month"
    from: string
    series: { date: string, made: number, settled: number, made_value: number, settled_value: number }[]
    states: { key: string, label: string, total: number, value: number }[]
    total: number
    total_value: number
    orders: number
    claimed_orders: number
    first_orders: number
    first_orders_claimed: number
    invoiced: number
    lost: number
    top_customers: Record<"value" | "claims" | "share" | "lost", TopCustomer[]>
    claim_rates: { key: string, label: string, customers: number, rows: TopCustomer[] }[]
    currency_code: string | null
    waiting: number
    oldest_waiting: string | null
    made: number
    settled: number
    auto_settled: number
    rejected: number
    settled_value: number
    credited: number
    review_hours: number | null
    from_merlin: number
    by_customer: number
    by_staff: number
    refunds: { claims: number, value: number }
    tax_corrections: { claims: number, value: number }
    by_resolution: { label: string, claims: number }[]
    by_reason: { reason: string, claims: number, quantity: number }[]
    by_product: { code: string, name: string | null, claims: number, quantity: number }[]
    by_carrier: { label: string, claims: number, lines: number }[]
    by_warehouse: { label: string, claims: number, lines: number }[]
    by_packer: { label: string, claims: number, lines: number }[]
    auto_settled_merlin: number
    settings: ClaimSettings
}

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    dashboard: Dashboard
    intervals: Record<string, string>
    can_edit_settings: boolean
    settings_route: { name: string, parameters: any }
    pending_route: { name: string, parameters: any }
}>()

const locale = useLocaleStore()
const money = (amount: number) => props.dashboard.currency_code ? locale.currencyFormat(props.dashboard.currency_code, amount) : amount
const n = (value: number) => locale.number(value)
const items = (quantity: number) => locale.number(Math.round(quantity))
const percent = (part: number, whole: number) => whole ? Math.round(part * 100 / whole) + "%" : "—"

const bucketLabel = (date: string) => new Date(date + "T00:00:00").toLocaleDateString(undefined, props.dashboard.bucket === "month" ? { month: "short", year: "numeric" } : { day: "numeric", month: "short" })

const deliverySections = computed(() => [
    { key: "carrier", title: ctrans("Claims by carrier"), column: ctrans("Carrier"), rows: props.dashboard.by_carrier },
    { key: "warehouse", title: ctrans("Claims by warehouse"), column: ctrans("Warehouse"), rows: props.dashboard.by_warehouse },
    { key: "packer", title: ctrans("Claims by packer"), column: ctrans("Packer"), rows: props.dashboard.by_packer },
])
const topBy = ref<"value" | "claims" | "share" | "lost">("value")
const topOptions = computed(() => [
    { label: currencySymbol.value || ctrans("Value"), value: "value" },
    { label: ctrans("Claims"), value: "claims" },
    { label: ctrans("% of orders"), value: "share" },
    { label: ctrans("% of invoiced"), value: "lost" },
])
const band = ref<string | null>(null)
const bandRow = computed(() => props.dashboard.claim_rates.find((row) => row.key === band.value) ?? null)
const tableRows = computed(() => bandRow.value ? bandRow.value.rows : props.dashboard.top_customers[topBy.value])
const BAND_COLORS = ["#16a34a", "#4ade80", "#a3e635", "#facc15", "#f59e0b", "#f97316", "#ef4444"]
const logScale = ref(true)
const scaleOptions = computed(() => [{ label: ctrans("Log"), value: true }, { label: ctrans("Normal"), value: false }])
const claimingBands = computed(() => props.dashboard.claim_rates)
const noClaimBand = computed(() => props.dashboard.claim_rates.find((row) => row.key === "none") ?? null)
const histogram = computed(() => ({
    labels: claimingBands.value.map((row) => row.label),
    datasets: [{ label: ctrans("Customers"), data: claimingBands.value.map((row) => row.customers), backgroundColor: claimingBands.value.map((row, index) => row.key === band.value ? "#1e40af" : BAND_COLORS[index] ?? "#9ca3af"), borderRadius: 3, maxBarThickness: 36 }],
}))
const histogramOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    onHover: (_event: unknown, active: unknown[], chart: { canvas: HTMLCanvasElement }) => { chart.canvas.style.cursor = active.length ? "pointer" : "default" },
    onClick: (_event: unknown, active: { index: number }[]) => {
        const row = active.length ? claimingBands.value[active[0].index] : null
        band.value = row && row.key !== band.value ? row.key : null
    },
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item: any) => `${n(item.raw)} ${item.raw === 1 ? ctrans("customer") : ctrans("customers")}` } } },
    scales: {
        x: { grid: { display: false }, title: { display: true, text: ctrans("Claimed, as % of what they were invoiced") } },
        y: logScale.value
            ? { type: "logarithmic", min: 0.5, ticks: { callback: (value: number) => [1, 10, 100, 1000, 10000, 100000].includes(value) ? n(value) : "" } }
            : { type: "linear", beginAtZero: true, ticks: { precision: 0, callback: (value: number) => n(value) } },
    },
}))

const preciseShare = (part: number, whole: number) => whole ? (Math.round(part * 1000 / whole) / 10).toLocaleString() + "%" : "—"

type Metric = "claims" | "value" | "average"
const metric = ref<Metric>("claims")
const currencySymbol = computed(() => props.dashboard.currency_code ? String(locale.currencySymbol?.(props.dashboard.currency_code) ?? props.dashboard.currency_code) : "")
const metricOptions = computed(() => [
    { label: ctrans("Claims"), value: "claims" },
    { label: currencySymbol.value || ctrans("Value"), value: "value" },
    { label: `${currencySymbol.value || ctrans("Value")}/${ctrans("claim")}`, value: "average" },
])
const pick = (count: number, value: number): number => metric.value === "claims" ? count : metric.value === "value" ? value : (count ? Math.round(value / count * 100) / 100 : 0)
const show = (amount: number): string => metric.value === "claims" ? n(amount) : String(money(amount))
const showShortMoney = (amount: number): string => {
    try {
        return new Intl.NumberFormat(undefined, { style: "currency", currency: props.dashboard.currency_code ?? "GBP", notation: "compact", maximumFractionDigits: 1 }).format(amount)
    } catch {
        return n(amount)
    }
}
const showShort = (amount: number): string => {
    if (metric.value === "claims" || !props.dashboard.currency_code) {
        return n(amount)
    }
    try {
        return new Intl.NumberFormat(undefined, { style: "currency", currency: props.dashboard.currency_code, notation: "compact", maximumFractionDigits: 1 }).format(amount)
    } catch {
        return n(amount)
    }
}

const lineChart = computed(() => {
    const pointRadius = props.dashboard.series.length > 40 ? 0 : 2
    return {
        labels: props.dashboard.series.map((row) => bucketLabel(row.date)),
        datasets: [
            { label: ctrans("Made"), data: props.dashboard.series.map((row) => pick(row.made, row.made_value)), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0, borderWidth: 1.5, pointRadius },
            { label: ctrans("Settled"), data: props.dashboard.series.map((row) => pick(row.settled, row.settled_value)), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius },
        ],
    }
})

const lineOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 12 } },
        tooltip: { callbacks: {
            title: (items: any[]) => (props.dashboard.bucket === "day" ? items[0].label : `${ctrans(props.dashboard.bucket === "week" ? "Week of" : "Month")} ${items[0].label}`),
            label: (item: any) => `${item.dataset.label}: ${show(item.raw)}`,
        } },
    },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { precision: 0, callback: (value: number) => showShort(value) } },
    },
}))

const STATE_COLORS: Record<string, string> = { in_review: "#f59e0b", auto: "#16a34a", approved: "#86efac", staff: "#3b82f6", rejected: "#ef4444" }

const donutChart = computed(() => ({
    labels: props.dashboard.states.map((row) => row.label),
    datasets: [{ data: props.dashboard.states.map((row) => metric.value === "claims" ? row.total : row.value), backgroundColor: props.dashboard.states.map((row) => STATE_COLORS[row.key] ?? "#9ca3af") }],
}))

const donutOptions = computed(() => ({ responsive: true, maintainAspectRatio: false, cutout: "70%", plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item: any) => metric.value === "claims" ? n(item.raw) : String(money(item.raw)) } } } }))
const stateFigure = (row: { total: number, value: number }) => metric.value === "claims" ? n(row.total) : metric.value === "value" ? String(money(row.value)) : String(money(row.total ? row.value / row.total : 0))
const stateShare = (row: { total: number, value: number }) => metric.value === "claims" ? percent(row.total, props.dashboard.total) : percent(row.value, props.dashboard.total_value)
const centreFigure = computed(() => metric.value === "claims" ? n(props.dashboard.total) : metric.value === "value" ? String(money(props.dashboard.total_value)) : String(money(props.dashboard.total ? props.dashboard.total_value / props.dashboard.total : 0)))

const status = computed(() => {
    const settings = props.dashboard.settings

    return [
        settings.self_service ? ctrans("Merlin claims: on") : ctrans("Merlin claims: off"),
        settings.self_service ? (settings.auto_approve ? ctrans("Automatic up to :amount", { amount: String(money(settings.max_claim_amount)) }) : ctrans("No automatic approval")) : null,
        settings.framework ? ctrans("Claims only") : ctrans("Old buttons, recorded as claims"),
    ].filter(Boolean)
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <CustomerClaimSettingsDialog v-if="can_edit_settings" :settings="dashboard.settings" :settings-route="settings_route" :currency-code="dashboard.currency_code" />
        </template>
    </PageHeading>

    <div class="space-y-6 p-6">
        <div class="space-y-2">
            <TicketsCreatedInterval :options="intervals" :selected="dashboard.interval" storage-key="claims-dashboard-interval" label="Made" compact />
            <p class="text-xs text-gray-500">{{ status.join(" · ") }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 xl:grid-cols-7">
            <Link :href="route(pending_route.name, pending_route.parameters)" class="rounded border p-4 hover:bg-gray-50">
                <p class="text-xs text-gray-500">{{ ctrans("Waiting for review") }}</p>
                <p class="text-2xl font-semibold tabular-nums" :class="dashboard.waiting ? 'text-amber-600' : 'text-gray-800'">{{ n(dashboard.waiting) }}</p>
                <p class="text-xs text-gray-500">
                    <template v-if="dashboard.oldest_waiting">{{ ctrans("Oldest since :date", { date: useFormatTime(dashboard.oldest_waiting) }) }}</template>
                    <template v-else>{{ ctrans("Nothing waiting") }}</template>
                </p>
            </Link>
            <div class="rounded border p-4">
                <p class="text-xs text-gray-500">{{ ctrans("Claims made") }}</p>
                <p class="text-2xl font-semibold tabular-nums text-gray-800">{{ n(dashboard.made) }}</p>
                <p class="text-xs text-gray-500">{{ ctrans(":count in Merlin", { count: n(dashboard.from_merlin) }) }}<span v-if="dashboard.from_merlin"> · {{ ctrans(":share settled there with no person", { share: percent(dashboard.auto_settled_merlin, dashboard.from_merlin) }) }}</span></p>
            </div>
            <div class="rounded border p-4">
                <p class="text-xs text-gray-500">{{ ctrans("Settled") }}</p>
                <p class="text-2xl font-semibold tabular-nums text-green-700">{{ n(dashboard.settled) }}</p>
                <p class="text-xs text-gray-500">{{ ctrans(":share with no person", { share: percent(dashboard.auto_settled, dashboard.settled) }) }} · {{ ctrans(":count rejected", { count: n(dashboard.rejected) }) }}</p>
            </div>
            <div class="rounded border p-4">
                <p class="text-xs text-gray-500">{{ ctrans("Value settled") }}</p>
                <p class="text-2xl font-semibold tabular-nums text-gray-800">{{ money(dashboard.settled_value) }}</p>
                <p class="text-xs text-gray-500">
                    {{ ctrans(":amount as credit", { amount: String(money(dashboard.credited)) }) }}
                    <template v-if="dashboard.review_hours !== null"> · {{ ctrans("reviewed in :hours h on average", { hours: String(dashboard.review_hours) }) }}</template>
                </p>
            </div>
            <div class="rounded border p-4">
                <p class="text-xs text-gray-500">{{ ctrans("Orders with a claim") }}</p>
                <p class="text-2xl font-semibold tabular-nums text-gray-800">{{ preciseShare(dashboard.claimed_orders, dashboard.orders) }}</p>
                <p class="text-xs text-gray-500">{{ ctrans(":claimed of :orders dispatched", { claimed: n(dashboard.claimed_orders), orders: n(dashboard.orders) }) }}</p>
            </div>
            <div class="rounded border p-4">
                <p class="text-xs text-gray-500">{{ ctrans("First orders with a claim") }}</p>
                <p class="text-2xl font-semibold tabular-nums" :class="dashboard.first_orders_claimed ? 'text-amber-600' : 'text-gray-800'">{{ preciseShare(dashboard.first_orders_claimed, dashboard.first_orders) }}</p>
                <p class="text-xs text-gray-500">{{ ctrans(":claimed of :orders new customers", { claimed: n(dashboard.first_orders_claimed), orders: n(dashboard.first_orders) }) }}</p>
            </div>
            <div class="rounded border p-4">
                <p class="text-xs text-gray-500">{{ ctrans("Lost to claims") }}</p>
                <p class="text-2xl font-semibold tabular-nums text-red-600">{{ preciseShare(dashboard.lost, dashboard.invoiced) }}</p>
                <p class="text-xs text-gray-500">{{ ctrans(":lost of :invoiced invoiced", { lost: String(money(dashboard.lost)), invoiced: showShortMoney(dashboard.invoiced) }) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 rounded border p-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="mb-2 flex justify-end"><SegmentedToggle v-model="metric" :options="metricOptions" /></div>
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Made vs settled") }} <span class="font-normal text-gray-400">{{ ctrans(":made made · :settled settled", { made: show(pick(dashboard.series.reduce((sum, row) => sum + row.made, 0), dashboard.series.reduce((sum, row) => sum + row.made_value, 0))), settled: show(pick(dashboard.series.reduce((sum, row) => sum + row.settled, 0), dashboard.series.reduce((sum, row) => sum + row.settled_value, 0))) }) }}</span></p>
                <div class="h-64"><Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" /></div>
            </div>
            <div>
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("What happened to them") }}</p>
                <div class="flex items-center gap-4">
                    <div class="relative h-40 w-40 shrink-0">
                        <Chart type="doughnut" :data="donutChart" :options="donutOptions" class="h-full" />
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-xl font-semibold tabular-nums text-gray-800">{{ centreFigure }}</span>
                            <span class="text-xs text-gray-500">{{ metric === "average" ? ctrans("Per claim") : ctrans("Total") }}</span>
                        </div>
                    </div>
                    <ul class="min-w-0 flex-1 space-y-1 text-sm">
                        <li v-for="row in dashboard.states" :key="row.key" class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-2"><span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: STATE_COLORS[row.key] }" />{{ row.label }}</span>
                            <span class="tabular-nums text-gray-700">{{ stateFigure(row) }} <span v-if="metric !== 'average'" class="text-xs text-gray-400">{{ stateShare(row) }}</span></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-x-6 gap-y-2 rounded border bg-gray-50 px-4 py-3 text-sm text-gray-700">
            <span>{{ ctrans("Made by customers themselves") }}: <span class="font-semibold">{{ percent(dashboard.by_customer, dashboard.made) }}</span> <span class="text-xs text-gray-500">({{ n(dashboard.by_customer) }} · {{ ctrans(":count by staff", { count: n(dashboard.by_staff) }) }})</span></span>
            <span>{{ ctrans("Invoice refunds") }}: <span class="font-semibold">{{ n(dashboard.refunds.claims) }}</span> <span class="text-xs text-gray-500">{{ money(dashboard.refunds.value) }}</span></span>
            <span>{{ ctrans("Tax corrections") }}: <span class="font-semibold">{{ n(dashboard.tax_corrections.claims) }}</span> <span class="text-xs text-gray-500">{{ money(dashboard.tax_corrections.value) }}</span></span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <div class="rounded border p-4">
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Why") }}</p>
                <p v-if="!dashboard.by_reason.length" class="text-xs text-gray-400">{{ ctrans("No claims in this period") }}</p>
                <table v-else class="w-full text-sm">
                    <thead class="whitespace-nowrap text-left text-xs text-gray-500"><tr><th class="pb-1 font-semibold">{{ ctrans("Reason") }}</th><th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Claims") }}</th><th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Items") }}</th><th class="pb-1 pl-4 text-right font-semibold">%</th></tr></thead>
                    <tbody><tr v-for="row in dashboard.by_reason" :key="row.reason" class="border-t"><td class="py-1">{{ row.reason }}</td><td class="py-1 pl-4 text-right tabular-nums">{{ n(row.claims) }}</td><td class="py-1 pl-4 text-right tabular-nums">{{ items(row.quantity) }}</td><td class="py-1 pl-4 text-right text-xs tabular-nums text-gray-500">{{ percent(row.claims, dashboard.made) }}</td></tr></tbody>
                </table>
            </div>
            <div class="rounded border p-4 lg:col-span-2">
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Products claimed most") }}</p>
                <p v-if="!dashboard.by_product.length" class="text-xs text-gray-400">{{ ctrans("No claims in this period") }}</p>
                <table v-else class="w-full text-sm">
                    <thead class="whitespace-nowrap text-left text-xs text-gray-500"><tr><th class="pb-1 font-semibold">{{ ctrans("Product") }}</th><th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Claims") }}</th><th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Items") }}</th><th class="pb-1 pl-4 text-right font-semibold">%</th></tr></thead>
                    <tbody><tr v-for="row in dashboard.by_product" :key="row.code" class="border-t align-top"><td class="py-1"><span class="whitespace-nowrap font-semibold">{{ row.code }}</span> <span class="text-gray-500">{{ row.name }}</span></td><td class="py-1 pl-4 text-right tabular-nums">{{ n(row.claims) }}</td><td class="py-1 pl-4 text-right tabular-nums">{{ items(row.quantity) }}</td><td class="py-1 pl-4 text-right text-xs tabular-nums text-gray-500">{{ percent(row.claims, dashboard.made) }}</td></tr></tbody>
                </table>
            </div>
            <div class="rounded border p-4">
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("What customers asked for") }}</p>
                <p v-if="!dashboard.by_resolution.length" class="text-xs text-gray-400">{{ ctrans("No claims in this period") }}</p>
                <table v-else class="w-full text-sm">
                    <tbody><tr v-for="row in dashboard.by_resolution" :key="row.label" class="border-t first:border-t-0"><td class="py-1">{{ row.label }}</td><td class="py-1 pl-4 text-right tabular-nums">{{ n(row.claims) }}</td><td class="py-1 pl-4 text-right text-xs text-gray-500">{{ percent(row.claims, dashboard.made) }}</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div v-for="section in deliverySections" :key="section.key" class="rounded border p-4">
                <p class="mb-2 text-sm font-semibold text-gray-700">{{ section.title }}</p>
                <p v-if="!section.rows.length" class="text-xs text-gray-400">{{ ctrans("No claims in this period") }}</p>
                <table v-else class="w-full text-sm">
                    <thead class="whitespace-nowrap text-left text-xs text-gray-500"><tr><th class="pb-1 font-semibold">{{ section.column }}</th><th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Claims") }}</th><th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Lines") }}</th><th class="pb-1 pl-4 text-right font-semibold">%</th></tr></thead>
                    <tbody><tr v-for="row in section.rows" :key="row.label" class="border-t"><td class="py-1">{{ row.label }}</td><td class="py-1 pl-4 text-right tabular-nums">{{ n(row.claims) }}</td><td class="py-1 pl-4 text-right tabular-nums">{{ n(row.lines) }}</td><td class="py-1 pl-4 text-right text-xs tabular-nums text-gray-500">{{ percent(row.claims, dashboard.made) }}</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="rounded border p-4">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-gray-700">{{ ctrans("Customers who claim most") }}</p>
                <SegmentedToggle v-model="topBy" :options="topOptions" @update:model-value="band = null" />
            </div>
            <p v-if="topBy === 'share'" class="mb-2 text-xs text-gray-500">{{ ctrans("Share of their orders dispatched in the period that had a claim; customers with at least 5 orders.") }}</p>
            <p v-if="topBy === 'lost'" class="mb-2 text-xs text-gray-500">{{ ctrans("What they claimed as a share of what they were invoiced in the period; customers with at least 5 orders.") }}</p>
            <p v-if="bandRow" class="mb-2 text-xs text-gray-600">
                {{ bandRow.customers === 1 ? ctrans("1 customer claimed :band of what they were invoiced", { band: bandRow.label }) : ctrans(":count customers claimed :band of what they were invoiced", { count: n(bandRow.customers), band: bandRow.label }) }}<template v-if="bandRow.customers > bandRow.rows.length"> · {{ ctrans("the :shown highest shown", { shown: String(bandRow.rows.length) }) }}</template>
                · <button type="button" class="underline hover:text-gray-800" @click="band = null">{{ ctrans("Show the top customers again") }}</button>
            </p>
            <p v-if="!tableRows.length" class="text-xs text-gray-400">{{ ctrans("No claims in this period") }}</p>
            <table v-else class="w-full text-sm">
                <thead class="whitespace-nowrap text-left text-xs text-gray-500">
                    <tr>
                        <th class="pb-1 font-semibold">{{ ctrans("Customer") }}</th>
                        <th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Claims") }}</th>
                        <th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Value") }}</th>
                        <th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Orders") }}</th>
                        <th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("% of orders") }}</th>
                        <th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("Invoiced") }}</th>
                        <th class="pb-1 pl-4 text-right font-semibold">{{ ctrans("% of invoiced") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in tableRows" :key="row.reference" class="border-t">
                        <td class="py-1"><Link :href="route(row.route.name, row.route.parameters)" class="secondaryLink">{{ row.name }}</Link> <span class="whitespace-nowrap text-xs text-gray-400">{{ row.reference }}</span></td>
                        <td class="py-1 pl-4 text-right tabular-nums" :class="{ 'font-semibold': topBy === 'claims' }">{{ n(row.claims) }}</td>
                        <td class="py-1 pl-4 text-right tabular-nums" :class="{ 'font-semibold': topBy === 'value' }">{{ money(row.value) }}</td>
                        <td class="py-1 pl-4 text-right tabular-nums">{{ n(row.orders) }}</td>
                        <td class="py-1 pl-4 text-right tabular-nums" :class="{ 'font-semibold': topBy === 'share' }">{{ row.share === null ? "—" : row.share.toLocaleString() + "%" }}</td>
                        <td class="py-1 pl-4 text-right tabular-nums">{{ money(row.invoiced) }}</td>
                        <td class="py-1 pl-4 text-right tabular-nums" :class="{ 'font-semibold': topBy === 'lost' }">{{ row.lost === null ? "—" : row.lost.toLocaleString() + "%" }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="dashboard.claim_rates.length" class="mt-6 grid grid-cols-1 border-t pt-4 lg:grid-cols-2">
                <div>
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-700">{{ ctrans("How much customers claim") }}</p>
                    <SegmentedToggle v-model="logScale" :options="scaleOptions" />
                </div>
                <p class="mb-2 text-xs text-gray-500">{{ ctrans("Every customer with an order in the period, first-time customers included, by what they claimed as a share of what they were invoiced. Click a bar to list them.") }}</p>
                <p v-if="noClaimBand" class="mb-2 text-xs text-gray-600">
                    {{ ctrans(":none of :all customers made no claim (:share).", { none: n(noClaimBand.customers), all: n(dashboard.claim_rates.reduce((sum, row) => sum + row.customers, 0)), share: preciseShare(noClaimBand.customers, dashboard.claim_rates.reduce((sum, row) => sum + row.customers, 0)) }) }}
                </p>
                <div class="h-56"><Chart type="bar" :data="histogram" :options="histogramOptions" class="h-full" /></div>
                </div>
            </div>
        </div>
    </div>
</template>
