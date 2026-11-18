<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 17 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
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
import { faExchangeAlt, faChartBar, faListUl, faHardHat } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faExchangeAlt, faChartBar, faListUl, faHardHat)

type Period = {
	start: string
	end: string
	opening: number
	in: Record<string, number>
	total_in: number
	out: Record<string, number>
	total_out: number
	conversions: number
	closing: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	organisation: string | null
	organisations: { value: string; label: string }[]
	isGroup: boolean
	inSections: Record<string, string>
	outSections: Record<string, string>
	cashFlow: {
		currency: string
		interval: "week" | "month"
		periods: Period[]
		banks: { name: string; first_line: string | null; partial: boolean }[]
		unconverted: string[]
		not_connected: string[]
		checked_from: string | null
		timezone: string
		organisations?: { code: string; name: string; closing: number; net: number }[]
	}
}>()

const locale = useLocaleStore()
const currency = computed(() => props.cashFlow.currency)
const money = (amount: number) => locale.currencyFormat(currency.value, Math.abs(amount) < 0.005 ? 0 : amount)
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: currency.value, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const periodLabel = (period: Period) =>
	props.cashFlow.interval === "month" ? useFormatTime(period.start, { formatTime: "MMM yy" }) : useFormatTime(period.start, { formatTime: "dd MMM" })

const query = (interval: string, organisation: string | null) => ({
	...(props.isGroup ? (organisation ? { org: organisation } : {}) : { organisation }),
	...(interval === "week" ? {} : { interval }),
})
const routeName = (page: "cash" | "cash_flow") => (props.isGroup ? `grp.financials.${page}.index` : `grp.org.accounting.${page}.index`)
const interval = computed({
	get: () => props.cashFlow.interval,
	set: (value: string) => router.get(route(routeName("cash_flow"), query(value, props.organisation))),
})
const view = computed({
	get: () => "cash_flow",
	set: () => router.get(route(routeName("cash"), query("week", props.organisation))),
})
const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const organisation = computed({
	get: () => props.organisation ?? "all",
	set: (value: string) => router.get(route(routeName("cash_flow"), query(props.cashFlow.interval, value === "all" ? null : value))),
})
const viewOptions = [
	{ label: ctrans("Balances"), value: "cash" },
	{ label: ctrans("Cash flow"), value: "cash_flow" },
]
const intervalOptions = [
	{ label: ctrans("Weekly"), value: "week" },
	{ label: ctrans("Monthly"), value: "month" },
]

const periods = computed(() => props.cashFlow.periods)
const sum = (pick: (period: Period) => number) => periods.value.reduce((total, period) => total + pick(period), 0)
const used = (sections: Record<string, string>, side: "in" | "out") => Object.entries(sections).filter(([key]) => periods.value.some((period) => Math.abs(period[side][key] ?? 0) >= 0.005))

type Row = { key: string; label: string; value: (period: Period) => number; kind: "balance" | "header" | "line" | "total" | "memo"; sign?: "+" | "−"; noTotal?: boolean }
const rows = computed<Row[]>(() => [
	{ key: "opening", label: ctrans("Cash at the start"), value: (period) => period.opening, kind: "balance", noTotal: true },
	{ key: "in-header", label: ctrans("Money in"), value: () => 0, kind: "header" },
	...used(props.inSections, "in").map(([key, label]) => ({ key: `in-${key}`, label, value: (period: Period) => period.in[key] ?? 0, kind: "line" as const, sign: "+" as const })),
	{ key: "total_in", label: ctrans("Total money in"), value: (period) => period.total_in, kind: "total", sign: "+" },
	{ key: "out-header", label: ctrans("Money out"), value: () => 0, kind: "header" },
	...used(props.outSections, "out").map(([key, label]) => ({ key: `out-${key}`, label, value: (period: Period) => period.out[key] ?? 0, kind: "line" as const, sign: "−" as const })),
	{ key: "total_out", label: ctrans("Total money out"), value: (period) => period.total_out, kind: "total", sign: "−" },
	{ key: "net", label: ctrans("Net cash flow"), value: (period) => period.total_in - period.total_out, kind: "total" },
	{ key: "conversions", label: ctrans("Currency conversions and exchange rates"), value: (period) => period.conversions, kind: "memo" },
	{ key: "closing", label: ctrans("Cash at the end"), value: (period) => period.closing, kind: "balance", noTotal: true },
])

const cell = (row: Row, amount: number) => (row.sign === "−" && Math.abs(amount) >= 0.005 ? "−" : "") + money(amount)
const cellClass = (row: Row, amount: number) => [
	Math.abs(amount) < 0.005 ? "text-gray-300" : "",
	row.kind === "total" || row.kind === "balance" ? "font-semibold" : "",
	row.key === "net" && amount < 0 ? "text-red-600" : "",
	row.kind === "memo" ? "text-gray-500" : "",
]
const rowClass = (row: Row) => (row.kind === "balance" ? "border-y border-gray-300 bg-gray-50" : row.kind === "total" ? "border-t border-gray-200" : "border-t border-gray-100")

const last = computed(() => periods.value[periods.value.length - 1])
const cards = computed(() => [
	{ key: "closing", label: ctrans("Cash now"), amount: last.value.closing, note: ctrans("last bank balance + lines since") },
	{ key: "in", label: ctrans("Money in, :count periods", { count: periods.value.length }), amount: sum((period) => period.total_in) },
	{ key: "out", label: ctrans("Money out, :count periods", { count: periods.value.length }), amount: sum((period) => period.total_out) },
	{ key: "net", label: ctrans("Net cash flow, :count periods", { count: periods.value.length }), amount: sum((period) => period.total_in - period.total_out) },
])

const chart = computed(() => ({
	labels: periods.value.map(periodLabel),
	datasets: [
		{ type: "bar", label: ctrans("Money in"), data: periods.value.map((period) => period.total_in), backgroundColor: "rgba(31, 132, 90, 0.75)", ...bar },
		{ type: "bar", label: ctrans("Money out"), data: periods.value.map((period) => period.total_out), backgroundColor: "rgba(220, 76, 76, 0.7)", ...bar },
		{
			type: "line",
			label: ctrans("Cash at the end"),
			data: periods.value.map((period) => period.closing),
			borderColor: "#4b5563",
			borderWidth: 1.5,
			pointRadius: 0,
			pointHoverRadius: 3,
			tension: 0.3,
			yAxisID: "cash",
			order: 1,
		},
	],
}))
const bar = { maxBarThickness: 12, barPercentage: 0.9, categoryPercentage: 0.5, borderRadius: 2, order: 2 }
const axisTicks = { color: "#9ca3af", font: { size: 11 }, callback: (value: number | string) => shortMoney(Number(value)) }
const chartOptions = {
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true, pointStyle: "circle", color: "#6b7280", font: { size: 11 } } },
		tooltip: { callbacks: { label: (item: { dataset: { label: string }; raw: unknown }) => ` ${item.dataset.label}: ${money(Number(item.raw))}` } },
	},
	scales: {
		x: { grid: { display: false }, border: { display: false }, ticks: { color: "#9ca3af", font: { size: 11 } } },
		y: { beginAtZero: true, border: { display: false }, grid: { color: "#f3f4f6" }, ticks: axisTicks },
		cash: { position: "right", border: { display: false }, grid: { display: false }, ticks: axisTicks },
	},
}

const checkedFrom = computed(() => props.cashFlow.checked_from?.slice(0, 10) ?? null)
const unchecked = (period: Period) => checkedFrom.value === null || period.start < checkedFrom.value
const hasUnchecked = computed(() => periods.value.some(unchecked))
const partialBanks = computed(() => props.cashFlow.banks.filter((bank) => bank.partial))
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
			<FontAwesomeIcon icon="fal fa-hard-hat" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
			<div>
				<p class="font-medium">{{ ctrans("Only the banks connected to Aiku are counted") }}</p>
				<p class="mt-0.5 text-xs">
					{{ ctrans("Connected: :banks.", { banks: cashFlow.banks.map((bank) => bank.name).join(", ") || "—" }) }}
					<template v-if="cashFlow.not_connected.length">{{ ctrans("Not connected yet: :banks.", { banks: cashFlow.not_connected.join(", ") }) }}</template>
				</p>
				<p v-for="bank in partialBanks" :key="bank.name" class="mt-0.5 text-xs">
					{{ bank.first_line ? ctrans(":bank has lines only from :date: before that its money in and out is missing.", { bank: bank.name, date: useFormatTime(bank.first_line) }) : ctrans(":bank has no lines yet.", { bank: bank.name }) }}
				</p>
				<p v-if="cashFlow.unconverted.length" class="mt-0.5 text-xs">
					{{ ctrans("Left out, no exchange rate known: :currencies.", { currencies: cashFlow.unconverted.join(", ") }) }}
				</p>
			</div>
		</div>

		<div class="flex flex-wrap items-center justify-between gap-3">
			<p class="text-xs text-gray-500">
				{{ cashFlow.interval === "month" ? ctrans("Last 12 months in :currency.", { currency }) : ctrans("Last 12 weeks, Monday to Sunday, in :currency.", { currency }) }}
			</p>
			<div class="flex flex-wrap items-center gap-3">
				<SegmentedToggle v-if="organisations.length > 1" v-model="organisation" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
				<SegmentedToggle v-model="interval" :options="intervalOptions" :aria-label="ctrans('Interval')" />
				<SegmentedToggle v-model="view" :options="viewOptions" :aria-label="ctrans('View')" />
			</div>
		</div>

		<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
			<div v-for="card in cards" :key="card.key" class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
				<div class="text-xs text-gray-500">{{ card.label }}</div>
				<div class="mt-1 text-xl font-semibold tabular-nums" :class="card.key === 'net' && card.amount < 0 ? 'text-red-600' : ''">{{ money(card.amount) }}</div>
				<div v-if="card.note" class="mt-0.5 text-xs text-gray-400">{{ card.note }}</div>
			</div>
		</div>

		<div v-if="isGroup && (cashFlow.organisations?.length ?? 0) > 1" class="overflow-x-auto rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
			<table class="w-full text-sm tabular-nums">
				<thead class="whitespace-nowrap text-xs uppercase text-gray-500">
					<tr>
						<th class="py-1.5 pr-4 text-left font-medium">{{ ctrans("Organisation") }}</th>
						<th class="px-2 py-1.5 text-right font-medium">{{ ctrans("Net cash flow, :count periods", { count: periods.length }) }}</th>
						<th class="py-1.5 pl-2 text-right font-medium">{{ ctrans("Cash now") }}</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in cashFlow.organisations" :key="row.code" class="border-t border-gray-100">
						<td class="whitespace-nowrap py-1.5 pr-4 text-gray-700">{{ row.name }}</td>
						<td class="whitespace-nowrap px-2 py-1.5 text-right" :class="row.net < 0 ? 'text-red-600' : ''">{{ money(row.net) }}</td>
						<td class="whitespace-nowrap py-1.5 pl-2 text-right font-semibold">{{ money(row.closing) }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<DashboardWidgetBox storageKey="cash_flow_chart_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-chart-bar" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("Money in and out") }}
				</span>
			</template>
			<div class="h-72">
				<Chart type="bar" :data="chart" :options="chartOptions" class="h-full" />
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox storageKey="cash_flow_statement_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-list-ul" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("Cash flow") }}
				</span>
			</template>
			<div class="overflow-x-auto">
				<table class="w-full text-sm tabular-nums">
					<thead class="whitespace-nowrap text-xs uppercase text-gray-500">
						<tr>
							<th class="sticky left-0 bg-white py-1.5 pr-4 text-left font-medium" />
							<th v-for="period in periods" :key="period.start" class="px-2 py-1.5 text-right font-medium">{{ periodLabel(period) }}<sup v-if="unchecked(period)" class="ml-0.5 text-amber-600">†</sup></th>
							<th class="border-l border-gray-200 py-1.5 pl-2 text-right font-medium">{{ ctrans("Total") }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in rows" :key="row.key" :class="rowClass(row)">
							<template v-if="row.kind === 'header'">
								<td class="sticky left-0 whitespace-nowrap bg-white pb-1 pr-4 pt-3 text-xs font-semibold uppercase text-gray-500">{{ row.label }}</td>
								<td :colspan="periods.length + 1" />
							</template>
							<template v-else>
								<td class="sticky left-0 whitespace-nowrap py-1.5 pr-4" :class="[row.kind === 'balance' ? 'bg-gray-50 font-semibold' : 'bg-white', row.kind === 'line' ? 'pl-3 text-gray-700' : '', row.kind === 'total' ? 'font-semibold' : '', row.kind === 'memo' ? 'text-xs text-gray-500' : '']">
									{{ row.label }}
								</td>
								<td v-for="period in periods" :key="period.start" class="whitespace-nowrap px-2 py-1.5 text-right" :class="cellClass(row, row.value(period))">
									{{ cell(row, row.value(period)) }}
								</td>
								<td class="whitespace-nowrap border-l border-gray-200 py-1.5 pl-2 text-right" :class="row.noTotal ? '' : cellClass(row, sum(row.value))">
									{{ row.noTotal ? "" : cell(row, sum(row.value)) }}
								</td>
							</template>
						</tr>
					</tbody>
				</table>
			</div>
			<ul class="mt-3 space-y-0.5 text-xs text-gray-500">
				<li>{{ ctrans("Money in and out follow the sections of Bank incoming and Bank outgoing, at the exchange rate of the day each line was booked.") }}</li>
				<li>{{ ctrans("Moves between our own currency balances are not money in or out: they and the change of exchange rates make the difference between the cash at the start and at the end.") }}</li>
				<li>{{ ctrans("Cash is worked back from the last balance each bank gave, less the lines booked after each date.") }}</li>
				<li>{{ ctrans("Cash now is that last balance plus the lines booked since, at today's exchange rate, so it can differ slightly from the Cash page, which shows the balance as the bank gave it, at the rate of that day.") }}</li>
				<li v-if="isGroup">{{ ctrans("The group adds up each organisation's cash flow in :currency, each at the same exchange rates.", { currency }) }}</li>
				<li>{{ cashFlow.interval === "month" ? ctrans("Months follow the organisation's time zone (:timezone).", { timezone: cashFlow.timezone }) : ctrans("Weeks run Monday to Sunday in the organisation's time zone (:timezone).", { timezone: cashFlow.timezone }) }}</li>
				<li v-if="hasUnchecked" class="text-amber-800">
					<span class="font-semibold">†</span>
					{{ checkedFrom
						? ctrans("Aiku reads the balances from the banks since :date. Cash before that date is worked back from the bank lines alone and is not yet checked against a balance the bank gave: if a line is missing from the bank feed, those openings and closings are off by it, and the difference shows in Currency conversions and exchange rates. Treat them as indicative until every period is checked.", { date: useFormatTime(checkedFrom) })
						: ctrans("No balance has been read from the banks yet: every figure is worked back from the bank lines alone and is not checked against the bank.") }}
				</li>
			</ul>
		</DashboardWidgetBox>
	</div>
</template>
