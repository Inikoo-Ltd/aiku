<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 01 Mar 2023 14:57:23 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Deferred, Head, Link } from "@inertiajs/vue3"
import axios from "axios"
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import PaymentMethodsWidget from "@/Components/Accounting/PaymentMethodsWidget.vue"
import InvoiceCountriesWidget from "@/Components/Accounting/InvoiceCountriesWidget.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
	faMoneyCheckAlt, faCashRegister, faFileInvoiceDollar, faCoins, faExclamationTriangle, faChartNetwork, faPiggyBank, faUniversity, faHandHoldingUsd, faFileInvoice,
	faHandHoldingBox, faBalanceScale, faExchangeAlt, faChartLine, faClipboardCheck, faCheckCircle, faArrowRight, faHourglassHalf, faPercent, faUsers,
} from "@fal"

library.add(
	faMoneyCheckAlt, faCashRegister, faFileInvoiceDollar, faCoins, faExclamationTriangle, faChartNetwork, faPiggyBank, faUniversity, faHandHoldingUsd, faFileInvoice,
	faHandHoldingBox, faBalanceScale, faExchangeAlt, faChartLine, faClipboardCheck, faCheckCircle, faArrowRight, faHourglassHalf, faPercent, faUsers
)

type RouteRef = { name: string; parameters: Record<string, string> }
type Flow = { lines: number; amount: number; previous_amount: number | null }
type Horizon = { days: number; receivables: number; providers: number; pipeline: number; payables: number; balance: number }
type Financials = {
	cash: { amount: number; as_of: string | null; stale_amount: number; not_connected: string[] }
	flows: { in: Flow; out: Flow }
	needs: { bank_incoming: number; bank_outgoing: number; payouts: number; stale_banks: number }
	receivables: { amount: number; overdue: number; debtors: number; ageing: { key: string; amount: number; invoices: number }[]; marketplaces: number; legacy: number }
	payables: { amount: number; count: number; unconverted: number }
	held: { amount: number; not_seen: number; by: { key: string; label: string; amount: number }[] }
	forecast: { start: number; overdue: number; pipeline_later: number; pipeline_orders: number; horizons: Horizon[] }
}
type CashFlowPeriod = { start: string; opening: number; total_in: number; total_out: number; closing: number }
type PnlLines = { sales: number; cost_of_goods: number; gross_margin: number; result: number | null }
type PnlMonth = PnlLines & { month: string; last_year: PnlLines }

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	flatTreeMaps: { name: string; icon?: string[]; route: RouteRef; index?: { number: number }; rightSubLink?: { tooltip: string; icon: string[]; route: RouteRef } }[][]
	payment_methods: any
	invoice_countries: any
	intervals: { value: string }
	uncostedStockDeliveries: any
	currency: string
	routes: Record<string, RouteRef>
	financials?: Financials
	cashFlow?: { periods: CashFlowPeriod[]; checked_from: string | null }
	profitAndLoss?: { months: PnlMonth[]; total: PnlLines & { last_year: PnlLines } }
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currency, Math.abs(amount) < 0.005 ? 0 : amount)
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.currency, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const href = (key: string) => route(props.routes[key].name, props.routes[key].parameters)
const percent = (part: number, whole: number) => (whole ? Math.round((part / whole) * 1000) / 10 : 0)

const paymentInterval = ref(props.intervals?.value)
const paymentIntervalOptions = [
	{ label: ctrans("1M"), value: "1m" },
	{ label: ctrans("1Q"), value: "1q" },
	{ label: ctrans("1Y"), value: "1y" },
]
const paymentPeriod = computed(() => props.payment_methods?.periods?.[paymentInterval.value])
const invoiceCountriesPeriod = computed(() => props.invoice_countries?.periods?.[paymentInterval.value])
const savePaymentInterval = (interval: string) => axios.patch(route("grp.models.profile.update"), { settings: { selected_interval: interval } })
const records = computed(() => (props.flatTreeMaps ?? []).flat())

const sparkline = (values: number[], width = 120, height = 32) => {
	if (values.length < 2) {
		return ""
	}
	const min = Math.min(...values)
	const max = Math.max(...values)
	const span = max - min || 1
	return values.map((value, index) => `${((index / (values.length - 1)) * width).toFixed(1)},${(height - 2 - ((value - min) / span) * (height - 4)).toFixed(1)}`).join(" ")
}

const weeks = computed(() => props.cashFlow?.periods ?? [])
const netFlow = computed(() => weeks.value.reduce((total, week) => total + week.total_in - week.total_out, 0))
const pnlTotal = computed(() => props.profitAndLoss?.total)
const marginPercent = computed(() => (pnlTotal.value ? percent(pnlTotal.value.gross_margin, pnlTotal.value.sales) : 0))
const salesChange = computed(() => {
	const total = pnlTotal.value
	if (!total || !total.last_year.sales) {
		return null
	}
	const change = Math.round(((total.sales - total.last_year.sales) / Math.abs(total.last_year.sales)) * 100)
	return { up: change >= 0, text: `${change >= 0 ? "▲" : "▼"} ${locale.number(Math.abs(change))}%` }
})

const ageLabels: Record<string, string> = {
	not_due: ctrans("Not due"),
	"1_30": ctrans("1–30 days"),
	"31_60": ctrans("31–60 days"),
	"61_90": ctrans("61–90 days"),
	over_90: ctrans("Over 90 days"),
}
const ageColours: Record<string, string> = { not_due: "#1f845a", "1_30": "#a3c76d", "31_60": "#f2b84b", "61_90": "#ef8a43", over_90: "#dc4c4c" }
const ageing = computed(() => {
	const rows = props.financials?.receivables.ageing ?? []
	const total = rows.reduce((sum, row) => sum + row.amount, 0)
	return rows.map((row) => ({ ...row, label: ageLabels[row.key] ?? row.key, colour: ageColours[row.key] ?? "#9ca3af", share: percent(row.amount, total) }))
})

const desk = computed(() => {
	const needs = props.financials?.needs
	if (!needs) {
		return []
	}
	return [
		{ key: "needs_in", count: needs.bank_incoming, label: ctrans("Bank lines in to place"), icon: "fal fa-university" },
		{ key: "needs_out", count: needs.bank_outgoing, label: ctrans("Bank payments out to classify"), icon: "fal fa-exchange-alt" },
		{ key: "payouts", count: needs.payouts, label: ctrans("Payouts not reconciled"), icon: "fal fa-hand-holding-box" },
		{ key: "cash", count: needs.stale_banks, label: ctrans("Banks without a current balance"), icon: "fal fa-hourglass-half" },
		...(props.uncostedStockDeliveries
			? [{ key: "uncosted", count: Number(props.uncostedStockDeliveries.value ?? 0), label: ctrans("Supplier deliveries to cost"), icon: "fal fa-file-invoice", route: props.uncostedStockDeliveries.route as RouteRef | undefined }]
			: []),
	]
})
const deskOpen = computed(() => desk.value.reduce((total, row) => total + (row.count > 0 ? 1 : 0), 0))
const deskHref = (row: { key: string; route?: RouteRef }) => (row.route ? route(row.route.name, row.route.parameters) : props.routes[row.key] ? href(row.key) : undefined)

const gridColour = "#f3f4f6"
const tickStyle = { color: "#9ca3af", font: { size: 11 } }
const moneyTicks = { ...tickStyle, callback: (value: number | string) => shortMoney(Number(value)) }
const legend = { position: "bottom", labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: "circle", color: "#6b7280", font: { size: 11 } } }
const tooltip = { callbacks: { label: (item: { dataset: { label: string }; raw: unknown }) => ` ${item.dataset.label}: ${money(Number(item.raw))}` } }
const slimBar = { maxBarThickness: 10, barPercentage: 0.9, categoryPercentage: 0.55, borderRadius: 2, order: 2 }

const cashFlowChart = computed(() => ({
	labels: weeks.value.map((week) => useFormatTime(week.start, { formatTime: "dd MMM" })),
	datasets: [
		{ type: "bar", label: ctrans("Money in"), data: weeks.value.map((week) => week.total_in), backgroundColor: "rgba(31, 132, 90, 0.75)", ...slimBar },
		{ type: "bar", label: ctrans("Money out"), data: weeks.value.map((week) => week.total_out), backgroundColor: "rgba(220, 76, 76, 0.7)", ...slimBar },
		{ type: "line", label: ctrans("Cash at the end"), data: weeks.value.map((week) => week.closing), borderColor: "#4b5563", borderWidth: 1.5, pointRadius: 0, pointHoverRadius: 3, tension: 0.3, yAxisID: "cash", order: 1 },
	],
}))
const cashFlowOptions = {
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: { legend, tooltip },
	scales: {
		x: { grid: { display: false }, border: { display: false }, ticks: tickStyle },
		y: { beginAtZero: true, border: { display: false }, grid: { color: gridColour }, ticks: moneyTicks },
		cash: { position: "right", border: { display: false }, grid: { display: false }, ticks: moneyTicks },
	},
}

const months = computed(() => props.profitAndLoss?.months ?? [])
const pnlChart = computed(() => ({
	labels: months.value.map((month) => useFormatTime(`${month.month}-01`, { formatTime: "MMM yy" })),
	datasets: [
		{ type: "bar", label: ctrans("Sales"), data: months.value.map((month) => month.sales), backgroundColor: "rgba(147, 197, 253, 0.85)", ...slimBar },
		{ type: "bar", label: ctrans("Gross margin"), data: months.value.map((month) => month.gross_margin), backgroundColor: "rgba(31, 132, 90, 0.75)", ...slimBar },
		{ type: "line", label: ctrans("Sales a year before"), data: months.value.map((month) => month.last_year.sales), borderColor: "#9ca3af", borderDash: [4, 4], borderWidth: 1.2, pointRadius: 0, tension: 0.3, order: 1 },
	],
}))
const pnlOptions = {
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: { legend, tooltip },
	scales: {
		x: { grid: { display: false }, border: { display: false }, ticks: tickStyle },
		y: { beginAtZero: true, border: { display: false }, grid: { color: gridColour }, ticks: moneyTicks },
	},
}

const forecastRows = computed(() => {
	const forecast = props.financials?.forecast
	if (!forecast) {
		return []
	}
	const points = [{ label: ctrans("Today"), balance: forecast.start }, ...forecast.horizons.map((row) => ({ label: ctrans(":days days", { days: row.days }), balance: row.balance }))]
	const max = Math.max(...points.map((point) => Math.abs(point.balance)), 1)
	return points.map((point) => ({ ...point, width: Math.max(2, (Math.abs(point.balance) / max) * 100) }))
})
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-4 px-4 py-4">
		<nav :aria-label="ctrans('Records')" class="flex flex-wrap gap-2">
			<div v-for="link in records" :key="link.name" class="flex items-stretch rounded-full border border-gray-200 bg-white text-xs">
				<Link :href="route(link.route.name, link.route.parameters)" class="flex items-center gap-1.5 whitespace-nowrap rounded-l-full px-3 py-1.5 text-gray-600 hover:bg-gray-50 hover:text-gray-900" :class="link.rightSubLink ? '' : 'rounded-r-full'">
					<FontAwesomeIcon v-if="link.icon" :icon="link.icon" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ link.name }}
					<span v-if="link.index" class="font-semibold tabular-nums text-gray-800">{{ locale.number(link.index.number) }}</span>
				</Link>
				<Link v-if="link.rightSubLink" :href="route(link.rightSubLink.route.name, link.rightSubLink.route.parameters)" v-tooltip="link.rightSubLink.tooltip" class="flex items-center rounded-r-full border-l border-gray-200 px-2 text-gray-400 hover:bg-gray-50 hover:text-gray-700">
					<FontAwesomeIcon :icon="link.rightSubLink.icon" fixed-width :aria-label="link.rightSubLink.tooltip" />
				</Link>
			</div>
			<Link :href="href('balances')" class="flex items-center gap-1.5 whitespace-nowrap rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50 hover:text-gray-900">
				<FontAwesomeIcon icon="fal fa-piggy-bank" class="text-gray-400" fixed-width aria-hidden="true" />
				{{ ctrans("Customer balances") }}
			</Link>
		</nav>

		<Deferred data="financials">
			<template #fallback>
				<div class="grid grid-cols-2 gap-3 lg:grid-cols-3 2xl:grid-cols-6">
					<div v-for="index in 6" :key="index" class="skeleton h-28 rounded-xl" />
				</div>
			</template>

			<div v-if="financials" class="grid grid-cols-2 gap-3 lg:grid-cols-3 2xl:grid-cols-6">
				<Link :href="href('cash')" class="group col-span-2 flex flex-col justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow lg:col-span-1 2xl:col-span-2">
					<div class="flex items-center justify-between text-xs font-medium text-gray-500">
						<span class="flex items-center gap-1.5"><FontAwesomeIcon icon="fal fa-university" fixed-width aria-hidden="true" />{{ ctrans("Cash in banks") }}</span>
						<FontAwesomeIcon icon="fal fa-arrow-right" class="text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500" aria-hidden="true" />
					</div>
					<div class="mt-2 flex items-end justify-between gap-4">
						<div>
							<div class="text-3xl font-semibold tracking-tight tabular-nums text-gray-900">{{ money(financials.cash.amount) }}</div>
							<div class="mt-1 text-xs tabular-nums text-gray-500">
								<span v-if="financials.cash.as_of">{{ ctrans("As of :date", { date: useFormatTime(financials.cash.as_of, { formatTime: "d MMM HH:mm" }) }) }}</span>
								<span v-if="financials.cash.stale_amount" class="ml-2 text-amber-700">{{ ctrans(":amount not current", { amount: shortMoney(financials.cash.stale_amount) }) }}</span>
							</div>
						</div>
						<Deferred data="cashFlow">
							<template #fallback><div class="skeleton h-8 w-32 rounded" /></template>
							<svg v-if="weeks.length" viewBox="0 0 120 32" class="h-8 w-32 shrink-0" aria-hidden="true">
								<polyline :points="sparkline(weeks.map((week) => week.closing))" fill="none" stroke="var(--app-accent)" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round" />
							</svg>
						</Deferred>
					</div>
					<div v-if="financials.cash.not_connected.length" class="mt-2 text-[11px] text-gray-400">{{ ctrans("Not connected yet: :banks", { banks: financials.cash.not_connected.join(", ") }) }}</div>
				</Link>

				<Link :href="href('receivables')" class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow">
					<div class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><FontAwesomeIcon icon="fal fa-hand-holding-usd" fixed-width aria-hidden="true" />{{ ctrans("Customers owe us") }}</div>
					<div class="mt-2 text-xl font-semibold tabular-nums text-gray-900">{{ money(financials.receivables.amount) }}</div>
					<div class="mt-2 flex h-1.5 overflow-hidden rounded-full bg-gray-100">
						<div v-for="row in ageing" :key="row.key" :style="{ width: `${row.share}%`, backgroundColor: row.colour }" />
					</div>
					<div class="mt-1.5 text-xs" :class="financials.receivables.overdue > 0 ? 'text-amber-700' : 'text-gray-500'">
						{{ ctrans(":amount overdue · :debtors debtors", { amount: shortMoney(financials.receivables.overdue), debtors: locale.number(financials.receivables.debtors) }) }}
					</div>
				</Link>

				<Link :href="href('payables')" class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow">
					<div class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><FontAwesomeIcon icon="fal fa-file-invoice" fixed-width aria-hidden="true" />{{ ctrans("We owe suppliers") }}</div>
					<div class="mt-2 text-xl font-semibold tabular-nums text-gray-900">{{ money(financials.payables.amount) }}</div>
					<div class="mt-1.5 text-xs text-gray-500">{{ ctrans(":count open documents", { count: locale.number(financials.payables.count) }) }}</div>
				</Link>

				<Link :href="href('payouts')" class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow">
					<div class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><FontAwesomeIcon icon="fal fa-hand-holding-box" fixed-width aria-hidden="true" />{{ ctrans("Held by providers") }}</div>
					<div class="mt-2 text-xl font-semibold tabular-nums text-gray-900">{{ money(financials.held.amount) }}</div>
					<div class="mt-1.5 truncate text-xs text-gray-500">{{ financials.held.by.map((row) => `${row.label} ${shortMoney(row.amount)}`).join(" · ") || ctrans("Nothing held") }}</div>
				</Link>

				<Link :href="href('profit_and_loss')" class="group rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow">
					<div class="flex items-center gap-1.5 text-xs font-medium text-gray-500"><FontAwesomeIcon icon="fal fa-balance-scale" fixed-width aria-hidden="true" />{{ ctrans("Gross margin, 12 months") }}</div>
					<Deferred data="profitAndLoss">
						<template #fallback><div class="skeleton mt-2 h-10 rounded" /></template>
						<template v-if="pnlTotal">
							<div class="mt-2 text-xl font-semibold tabular-nums text-gray-900">{{ money(pnlTotal.gross_margin) }}</div>
							<div class="mt-1.5 flex flex-wrap gap-x-2 text-xs tabular-nums text-gray-500">
								<span>{{ ctrans(":percent% of sales", { percent: locale.number(marginPercent) }) }}</span>
								<span v-if="salesChange" :class="salesChange.up ? 'text-green-700' : 'text-red-600'">{{ ctrans("sales :change", { change: salesChange.text }) }}</span>
							</div>
						</template>
					</Deferred>
				</Link>
			</div>
		</Deferred>

		<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
			<section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm xl:col-span-2">
				<header class="mb-3 flex flex-wrap items-center justify-between gap-2">
					<div>
						<h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700"><FontAwesomeIcon icon="fal fa-exchange-alt" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Cash flow, last 12 weeks") }}</h2>
						<Deferred data="cashFlow">
							<template #fallback><span /></template>
							<p class="mt-0.5 text-xs text-gray-500">
								{{ ctrans("Net :amount", { amount: money(netFlow) }) }}
								<span v-if="cashFlow && (!cashFlow.checked_from || weeks[0]?.start < cashFlow.checked_from.slice(0, 10))" class="ml-2 text-amber-700">
									† {{ cashFlow.checked_from ? ctrans("checked against bank balances only since :date", { date: useFormatTime(cashFlow.checked_from.slice(0, 10)) }) : ctrans("not checked against bank balances yet") }}
								</span>
							</p>
						</Deferred>
					</div>
					<Link :href="href('cash_flow')" class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-800">{{ ctrans("Open") }} <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" /></Link>
				</header>
				<Deferred data="cashFlow">
					<template #fallback><div class="skeleton h-64 rounded" /></template>
					<div class="h-64"><Chart type="bar" :data="cashFlowChart" :options="cashFlowOptions" class="h-full" /></div>
				</Deferred>
			</section>

			<section class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
				<header class="mb-3 flex items-center justify-between gap-2">
					<h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700"><FontAwesomeIcon icon="fal fa-clipboard-check" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Desk") }}</h2>
					<Deferred data="financials">
						<template #fallback><span /></template>
						<span v-if="deskOpen" class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800">{{ ctrans(":count to do", { count: deskOpen }) }}</span>
						<span v-else class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">{{ ctrans("All clear") }}</span>
					</Deferred>
				</header>
				<Deferred data="financials">
					<template #fallback><div class="space-y-2"><div v-for="index in 5" :key="index" class="skeleton h-9 rounded" /></div></template>
					<ul class="divide-y divide-gray-100">
						<li v-for="row in desk" :key="row.key">
							<component :is="deskHref(row) ? Link : 'div'" :href="deskHref(row)" class="group flex items-center gap-3 rounded-md px-1 py-2 text-sm hover:bg-gray-50">
								<FontAwesomeIcon :icon="row.count ? row.icon : 'fal fa-check-circle'" fixed-width :class="row.count ? 'text-amber-600' : 'text-green-600'" aria-hidden="true" />
								<span :class="row.count ? 'text-gray-800' : 'text-gray-400'">{{ row.label }}</span>
								<span class="ml-auto font-semibold tabular-nums" :class="row.count ? 'text-gray-900' : 'text-gray-300'">{{ locale.number(row.count) }}</span>
							</component>
						</li>
					</ul>
				</Deferred>
				<Deferred data="financials">
					<template #fallback><span /></template>
					<div v-if="forecastRows.length" class="mt-auto border-t border-gray-100 pt-3">
						<div class="mb-2 flex items-center justify-between text-xs font-medium text-gray-500">
							<span>{{ ctrans("Expected cash") }}</span>
							<span class="text-gray-400">{{ ctrans("receivables, orders and payables") }}</span>
						</div>
						<div v-for="point in forecastRows" :key="point.label" class="flex items-center gap-2 py-0.5 text-xs tabular-nums">
							<span class="w-14 shrink-0 text-gray-500">{{ point.label }}</span>
							<div class="h-1.5 flex-1 rounded-full bg-gray-100">
								<div class="h-1.5 rounded-full" :class="point.balance >= 0 ? 'bg-[var(--app-accent)]' : 'bg-red-500'" :style="{ width: `${point.width}%` }" />
							</div>
							<span class="w-24 shrink-0 text-right" :class="point.balance < 0 ? 'text-red-600' : 'text-gray-800'">{{ shortMoney(point.balance) }}</span>
						</div>
					</div>
				</Deferred>
			</section>
		</div>

		<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
			<section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm xl:col-span-2">
				<header class="mb-3 flex items-center justify-between gap-2">
					<h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700"><FontAwesomeIcon icon="fal fa-chart-line" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Sales and gross margin by month") }}</h2>
					<Link :href="href('profit_and_loss')" class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-800">{{ ctrans("Profit and loss") }} <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" /></Link>
				</header>
				<Deferred data="profitAndLoss">
					<template #fallback><div class="skeleton h-64 rounded" /></template>
					<div class="h-64"><Chart type="bar" :data="pnlChart" :options="pnlOptions" class="h-full" /></div>
				</Deferred>
			</section>

			<section class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
				<header class="mb-3 flex items-center justify-between gap-2">
					<h2 class="flex items-center gap-2 text-sm font-semibold text-gray-700"><FontAwesomeIcon icon="fal fa-users" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />{{ ctrans("Receivables ageing") }}</h2>
					<Link :href="href('receivables')" class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-800">{{ ctrans("Open") }} <FontAwesomeIcon icon="fal fa-arrow-right" aria-hidden="true" /></Link>
				</header>
				<Deferred data="financials">
					<template #fallback><div class="space-y-2"><div v-for="index in 5" :key="index" class="skeleton h-7 rounded" /></div></template>
					<table class="w-full text-sm tabular-nums">
						<tbody>
							<tr v-for="row in ageing" :key="row.key" class="border-t border-gray-100 first:border-t-0">
								<td class="whitespace-nowrap py-1.5 pr-3">
									<span class="flex items-center gap-2 text-gray-700"><span class="h-2 w-2 shrink-0 rounded-full" :style="{ backgroundColor: row.colour }" />{{ row.label }}</span>
								</td>
								<td class="w-full py-1.5 pr-3">
									<div class="h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full" :style="{ width: `${row.share}%`, backgroundColor: row.colour }" /></div>
								</td>
								<td class="whitespace-nowrap py-1.5 text-right font-medium text-gray-900">{{ money(row.amount) }}</td>
							</tr>
						</tbody>
					</table>
					<div v-if="financials" class="mt-3 flex flex-wrap gap-x-4 gap-y-1 border-t border-gray-100 pt-2 text-xs text-gray-500">
						<span v-if="financials.receivables.marketplaces">{{ ctrans("Marketplaces still to pay :amount", { amount: money(financials.receivables.marketplaces) }) }}</span>
						<span v-if="financials.receivables.legacy">{{ ctrans("From Aurora, not verified :amount", { amount: money(financials.receivables.legacy) }) }}</span>
					</div>
				</Deferred>
			</section>
		</div>

		<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
			<PaymentMethodsWidget v-if="paymentPeriod" :summary="{ ...paymentPeriod.summary, period_label: paymentPeriod.period_label }" :tableRoute="payment_methods.route">
				<template #actions>
					<SegmentedToggle v-model="paymentInterval" :options="paymentIntervalOptions" :ariaLabel="ctrans('Period')" @update:modelValue="savePaymentInterval" />
				</template>
			</PaymentMethodsWidget>
			<InvoiceCountriesWidget v-if="invoiceCountriesPeriod" :summary="invoiceCountriesPeriod" :tableRoute="invoice_countries.route">
				<template #actions>
					<SegmentedToggle v-model="paymentInterval" :options="paymentIntervalOptions" :ariaLabel="ctrans('Period')" @update:modelValue="savePaymentInterval" />
				</template>
			</InvoiceCountriesWidget>
		</div>

	</div>
</template>
