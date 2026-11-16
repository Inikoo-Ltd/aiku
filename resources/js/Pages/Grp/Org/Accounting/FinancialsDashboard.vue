<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 16 Nov 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed } from "vue"
import Chart from "primevue/chart"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFileInvoiceDollar, faUserClock, faChartLine, faArrowDown, faArrowUp, faHandHoldingUsd, faFileInvoice, faHandHoldingBox } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faFileInvoiceDollar, faUserClock, faChartLine, faArrowDown, faArrowUp, faHandHoldingUsd, faFileInvoice, faHandHoldingBox)

type RouteRef = { name: string; parameters: Record<string, string> }
interface Flow {
	lines: number
	amount: number
	previous_amount: number | null
}
interface Horizon {
	days: number
	receivables: number
	providers: number
	pipeline: number
	payables: number
	balance: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	organisation: string | null
	organisations: { value: string; label: string }[]
	currency: string
	routes: Record<string, RouteRef>
	dashboard: {
		cash: { amount: number; as_of: string | null; stale_amount: number; not_connected: string[] }
		flows: { in: Flow; out: Flow }
		needs: { bank_incoming: number; bank_outgoing: number; payouts: number; stale_banks: number }
		receivables: { amount: number; overdue: number; debtors: number; marketplaces: number; legacy: number }
		payables: { amount: number; count: number; unconverted: number }
		held: { amount: number; not_seen: number; by: { key: string; label: string; amount: number }[] }
		forecast: { start: number; overdue: number; pipeline_later: number; pipeline_orders: number; horizons: Horizon[] }
	}
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currency, amount)
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.currency, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const href = (key: string) => route(props.routes[key].name, props.routes[key].parameters)

const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const organisation = computed({
	get: () => props.organisation ?? "all",
	set: (value: string) => router.get(route("grp.financials.dashboard", value === "all" ? {} : { org: value })),
})

const change = (flow: Flow) => {
	if (flow.previous_amount === null) {
		return null
	}
	const difference = flow.amount - flow.previous_amount
	const percent = flow.previous_amount ? ` (${locale.number(Math.abs(Math.round((difference / Math.abs(flow.previous_amount)) * 100)))}%)` : ""
	return { up: difference >= 0, text: `${difference >= 0 ? "▲" : "▼"} ${money(Math.abs(difference))}${percent}` }
}

const tiles = computed(() => [
	{ key: "bank_incoming", icon: "fal fa-arrow-down", label: ctrans("Money in, last 90 days"), amount: props.dashboard.flows.in.amount, change: change(props.dashboard.flows.in), note: ctrans("vs the 90 days before"), good: true },
	{ key: "bank_outgoing", icon: "fal fa-arrow-up", label: ctrans("Money out, last 90 days"), amount: props.dashboard.flows.out.amount, change: change(props.dashboard.flows.out), note: ctrans("vs the 90 days before"), good: false },
	{ key: "receivables", icon: "fal fa-hand-holding-usd", label: ctrans("Customers owe us"), amount: props.dashboard.receivables.amount, note: ctrans(":amount overdue, :debtors debtors", { amount: money(props.dashboard.receivables.overdue), debtors: props.dashboard.receivables.debtors }), warn: props.dashboard.receivables.overdue > 0 },
	{ key: "payables", icon: "fal fa-file-invoice", label: ctrans("We owe suppliers and agents"), amount: props.dashboard.payables.amount, note: ctrans(":count documents, none has a due date yet", { count: props.dashboard.payables.count }) },
	{ key: "payouts", icon: "fal fa-hand-holding-box", label: ctrans("Held by payment providers"), amount: props.dashboard.held.amount, note: props.dashboard.held.by.map((row) => `${row.label} ${shortMoney(row.amount)}`).join(" · ") || ctrans("Nothing held") },
])

const needs = computed(() =>
	[
		{ key: "needs_in", count: props.dashboard.needs.bank_incoming, label: ctrans("bank lines in that need a person") },
		{ key: "needs_out", count: props.dashboard.needs.bank_outgoing, label: ctrans("bank payments out that need a person") },
		{ key: "payouts", count: props.dashboard.needs.payouts, label: ctrans("payouts not reconciled") },
		{ key: "cash", count: props.dashboard.needs.stale_banks, label: ctrans("bank connections without a current balance") },
	].filter((row) => row.count > 0)
)

const forecast = computed(() => props.dashboard.forecast)
const forecastChart = computed(() => ({
	labels: [ctrans("Today"), ...forecast.value.horizons.map((row) => ctrans(":days days", { days: row.days }))],
	datasets: [
		{
			label: ctrans("Expected cash"),
			data: [forecast.value.start, ...forecast.value.horizons.map((row) => row.balance)],
			backgroundColor: [forecast.value.start, ...forecast.value.horizons.map((row) => row.balance)].map((value) => (value >= 0 ? "#1f845a" : "#ef4444")),
		},
	],
}))
const chartOptions = {
	responsive: true,
	maintainAspectRatio: false,
	plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item: { raw: unknown }) => ` ${money(Number(item.raw))}` } } },
	scales: { x: { grid: { display: false } }, y: { ticks: { callback: (value: number | string) => shortMoney(Number(value)) } } },
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex flex-wrap items-end justify-between gap-3">
			<div>
				<Link :href="href('cash')" class="text-xs text-gray-500 hover:underline">{{ ctrans("Cash in connected banks") }}</Link>
				<div class="text-3xl font-semibold tabular-nums">{{ money(dashboard.cash.amount) }}</div>
				<div class="mt-0.5 flex flex-wrap gap-x-3 text-xs tabular-nums text-gray-500">
					<span v-if="dashboard.cash.as_of">{{ ctrans("As of :date", { date: useFormatTime(dashboard.cash.as_of, { formatTime: "d MMM yyyy HH:mm" }) }) }}</span>
					<span v-if="dashboard.cash.stale_amount" class="text-amber-700">{{ ctrans(":amount of it is not current", { amount: money(dashboard.cash.stale_amount) }) }}</span>
					<span v-if="dashboard.cash.not_connected.length">{{ ctrans("Not counted: :banks", { banks: dashboard.cash.not_connected.join(", ") }) }}</span>
				</div>
			</div>
			<SegmentedToggle v-if="organisations.length > 1" v-model="organisation" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
		</div>

		<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
			<Link v-for="tile in tiles" :key="tile.key" :href="href(tile.key)" class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm hover:border-gray-400">
				<div class="flex items-center gap-2 text-xs text-gray-600">
					<FontAwesomeIcon :icon="tile.icon" fixed-width aria-hidden="true" />
					<span class="truncate font-medium">{{ tile.label }}</span>
				</div>
				<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(tile.amount) }}</div>
				<div v-if="tile.change" class="text-xs tabular-nums" :class="tile.change.up === tile.good ? 'text-green-700' : 'text-red-600'">{{ tile.change.text }} <span class="text-gray-500">{{ tile.note }}</span></div>
				<div v-else class="text-xs" :class="tile.warn ? 'text-amber-700' : 'text-gray-500'">{{ tile.note }}</div>
			</Link>
		</div>

		<div v-if="needs.length" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
			<p class="flex items-center gap-2 font-medium">
				<FontAwesomeIcon icon="fal fa-user-clock" fixed-width aria-hidden="true" />
				{{ ctrans("Needs a person") }}
			</p>
			<ul class="mt-1 space-y-0.5 pl-7">
				<li v-for="row in needs" :key="row.key">
					<Link :href="href(row.key)" class="hover:underline"><span class="font-semibold tabular-nums">{{ locale.number(row.count) }}</span> {{ row.label }}</Link>
				</li>
			</ul>
		</div>

		<DashboardWidgetBox storageKey="financials_forecast_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-chart-line" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("Cash forecast") }}
				</span>
				<span class="text-xs text-gray-400">{{ ctrans("from receivables, payables and orders not invoiced yet") }}</span>
			</template>
			<div class="grid gap-6 lg:grid-cols-5">
				<div class="h-64 lg:col-span-2">
					<Chart type="bar" :data="forecastChart" :options="chartOptions" class="h-full" />
				</div>
				<div class="overflow-x-auto lg:col-span-3">
					<table class="w-full text-sm tabular-nums">
						<thead class="whitespace-nowrap text-left text-xs uppercase text-gray-500">
							<tr>
								<th class="py-1.5 pr-4 font-medium"></th>
								<th v-for="row in forecast.horizons" :key="row.days" class="py-1.5 pr-4 text-right font-medium">{{ ctrans(":days days", { days: row.days }) }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr>
								<td class="whitespace-nowrap py-1.5 pr-4">{{ ctrans("Cash today") }}</td>
								<td v-for="row in forecast.horizons" :key="row.days" class="whitespace-nowrap py-1.5 pr-4 text-right">{{ money(forecast.start) }}</td>
							</tr>
							<tr>
								<td class="whitespace-nowrap py-1.5 pr-4">+ {{ ctrans("Receivables falling due") }}</td>
								<td v-for="row in forecast.horizons" :key="row.days" class="whitespace-nowrap py-1.5 pr-4 text-right">{{ money(row.receivables) }}</td>
							</tr>
							<tr>
								<td class="whitespace-nowrap py-1.5 pr-4">+ {{ ctrans("Paid out by Checkout and PastPay") }}</td>
								<td v-for="row in forecast.horizons" :key="row.days" class="whitespace-nowrap py-1.5 pr-4 text-right">{{ money(row.providers) }}</td>
							</tr>
							<tr>
								<td class="whitespace-nowrap py-1.5 pr-4">+ {{ ctrans("Orders not invoiced yet") }}</td>
								<td v-for="row in forecast.horizons" :key="row.days" class="whitespace-nowrap py-1.5 pr-4 text-right">{{ money(row.pipeline) }}</td>
							</tr>
							<tr>
								<td class="whitespace-nowrap py-1.5 pr-4">− {{ ctrans("Payables") }}</td>
								<td v-for="row in forecast.horizons" :key="row.days" class="whitespace-nowrap py-1.5 pr-4 text-right">{{ money(row.payables) }}</td>
							</tr>
							<tr class="font-semibold">
								<td class="whitespace-nowrap py-1.5 pr-4">{{ ctrans("Expected cash") }}</td>
								<td v-for="row in forecast.horizons" :key="row.days" class="whitespace-nowrap py-1.5 pr-4 text-right" :class="row.balance < 0 ? 'text-red-600' : ''">{{ money(row.balance) }}</td>
							</tr>
						</tbody>
					</table>
					<ul class="mt-3 space-y-0.5 text-xs text-gray-500">
						<li>{{ ctrans("Payables are counted in full in the first 30 days: none records a due date yet.") }}</li>
						<li v-if="forecast.overdue">{{ ctrans(":amount of overdue receivables is not counted: nobody can say when it is paid.", { amount: money(forecast.overdue) }) }}</li>
						<li>{{ ctrans("Orders are expected a week after they are submitted plus the customer's payment terms (:orders orders unpaid).", { orders: forecast.pipeline_orders }) }}</li>
						<li>{{ ctrans("Cash on delivery and marketplaces are in receivables; banks not connected are not counted.") }}</li>
					</ul>
				</div>
			</div>
		</DashboardWidgetBox>
	</div>
</template>
