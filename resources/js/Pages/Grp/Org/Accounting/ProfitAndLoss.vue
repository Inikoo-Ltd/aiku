<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 16 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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
import { faBalanceScale, faChartBar, faHardHat, faListUl } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faBalanceScale, faChartBar, faHardHat, faListUl)

type Line = "sales" | "cost_of_goods" | "gross_margin" | "bank_fees" | "provider_fees" | "marketplace_fees" | "staff" | "services" | "other" | "unclassified" | "result" | "tax_paid"
type Lines = Record<Line, number | null> & { provider_fees_unknown: number | null }
type Month = Lines & { month: string; last_year: Lines }

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	organisation: string | null
	organisations: { value: string; label: string }[]
	isGroup: boolean
	notConnected: string[]
	profitAndLoss: {
		currency: string
		months: Month[]
		total: Lines & { last_year: Lines }
	}
}>()

const locale = useLocaleStore()
const currency = computed(() => props.profitAndLoss.currency)
const money = (amount: number | null) => (amount === null ? "—" : locale.currencyFormat(currency.value, amount))
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: currency.value, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const monthLabel = (month: string) => useFormatTime(`${month}-01`, { formatTime: "MMM yy" })

const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const organisation = computed({
	get: () => props.organisation ?? "all",
	set: (value: string) => router.get(route("grp.financials.profit_and_loss.index", value === "all" ? {} : { org: value })),
})

const rows: { key: Line; label: string; strong?: boolean; cost?: boolean; memo?: boolean }[] = [
	{ key: "sales", label: ctrans("Sales") },
	{ key: "cost_of_goods", label: ctrans("Cost of goods"), cost: true },
	{ key: "gross_margin", label: ctrans("Gross margin"), strong: true },
	{ key: "bank_fees", label: ctrans("Bank fees"), cost: true },
	{ key: "provider_fees", label: ctrans("Card processor fees"), cost: true },
	{ key: "marketplace_fees", label: ctrans("Marketplace commissions"), cost: true },
	{ key: "staff", label: ctrans("Staff and contractors"), cost: true },
	{ key: "services", label: ctrans("Services and subscriptions"), cost: true },
	{ key: "other", label: ctrans("Other costs"), cost: true },
	{ key: "unclassified", label: ctrans("Bank payments not classified yet"), cost: true },
	{ key: "result", label: ctrans("Result"), strong: true },
	{ key: "tax_paid", label: ctrans("Tax and duty paid (not in the result)"), memo: true },
]

const total = computed(() => props.profitAndLoss.total)
const marginPercent = (lines: Lines) => (lines.sales ? Math.round(((lines.gross_margin ?? 0) / lines.sales) * 1000) / 10 : null)
const change = (now: number | null, before: number | null) => {
	if (now === null || before === null || before === 0) {
		return null
	}
	const percent = Math.round(((now - before) / Math.abs(before)) * 100)
	return { up: percent >= 0, text: `${percent >= 0 ? "▲" : "▼"} ${locale.number(Math.abs(percent))}%` }
}

const cards = computed(() => [
	{ key: "sales", label: ctrans("Sales"), amount: total.value.sales, before: total.value.last_year.sales },
	{ key: "gross_margin", label: ctrans("Gross margin"), amount: total.value.gross_margin, before: total.value.last_year.gross_margin, note: marginPercent(total.value) === null ? null : ctrans(":percent% of sales", { percent: locale.number(marginPercent(total.value)!) }) },
	{ key: "cost_of_goods", label: ctrans("Cost of goods"), amount: total.value.cost_of_goods, before: total.value.last_year.cost_of_goods },
	{
		key: "result",
		label: ctrans("Result"),
		amount: total.value.result,
		before: total.value.last_year.result,
		note: total.value.result === null ? ctrans("Not known: some months have no bank lines") : null,
	},
])

const barChart = computed(() => ({
	labels: props.profitAndLoss.months.map((month) => monthLabel(month.month)),
	datasets: [
		{ type: "bar", label: ctrans("Sales"), data: props.profitAndLoss.months.map((month) => month.sales), backgroundColor: "#93c5fd", borderRadius: 3, order: 2 },
		{ type: "bar", label: ctrans("Gross margin"), data: props.profitAndLoss.months.map((month) => month.gross_margin), backgroundColor: "#1f845a", borderRadius: 3, order: 2 },
		{ type: "line", label: ctrans("Sales a year before"), data: props.profitAndLoss.months.map((month) => month.last_year.sales), borderColor: "#9ca3af", borderDash: [4, 4], borderWidth: 1.5, pointRadius: 2, tension: 0, order: 1 },
	],
}))
const barOptions = {
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 12 } },
		tooltip: { callbacks: { label: (item: { dataset: { label: string }; raw: unknown }) => ` ${item.dataset.label}: ${money(Number(item.raw))}` } },
	},
	scales: {
		x: { grid: { display: false } },
		y: { beginAtZero: true, ticks: { callback: (value: number | string) => shortMoney(Number(value)) } },
	},
}

const cellClass = (row: (typeof rows)[number], amount: number | null) => [
	row.strong ? "font-semibold" : "",
	row.memo ? "text-gray-400" : "",
	row.strong && amount !== null && amount < 0 ? "text-red-600" : "",
	amount === null ? "text-gray-300" : "",
	amount === 0 ? "text-gray-300" : "",
]
const cell = (row: (typeof rows)[number], amount: number | null) => (amount === null ? "—" : (row.cost && amount !== 0 ? "−" : "") + money(Math.abs(amount) < 0.005 ? 0 : amount))
const unknownFees = computed(() => props.profitAndLoss.total.provider_fees_unknown ?? 0)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
			<FontAwesomeIcon icon="fal fa-hard-hat" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
			<div>
				<p class="font-medium">{{ ctrans("Under construction: this will not be complete until all banks are set up in Aiku") }}</p>
				<p class="mt-0.5 text-xs">
					{{ ctrans("Fees and costs are read from the banks connected to Aiku only.") }}
					<template v-if="notConnected.length">{{ ctrans("Not connected yet: :banks.", { banks: notConnected.join(", ") }) }}</template>
				</p>
			</div>
		</div>

		<div class="flex flex-wrap items-center justify-between gap-3">
			<p class="text-xs text-gray-500">
				{{ ctrans("Management view, not a statutory profit and loss. Last 12 months in :currency, against the same months a year before.", { currency }) }}
			</p>
			<SegmentedToggle v-if="organisations.length > 1" v-model="organisation" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
		</div>

		<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
			<div v-for="card in cards" :key="card.key" class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
				<div class="text-xs text-gray-500">{{ card.label }}</div>
				<div class="mt-1 text-xl font-semibold tabular-nums" :class="card.amount !== null && card.amount < 0 ? 'text-red-600' : ''">{{ money(card.amount) }}</div>
				<div class="mt-0.5 flex flex-wrap gap-x-2 text-xs tabular-nums text-gray-500">
					<span v-if="change(card.amount, card.before)" :class="change(card.amount, card.before)!.up === (card.key !== 'cost_of_goods') ? 'text-green-700' : 'text-red-600'">
						{{ change(card.amount, card.before)!.text }}
					</span>
					<span v-if="card.before !== null">{{ ctrans("a year before :amount", { amount: money(card.before) }) }}</span>
					<span v-if="card.note">{{ card.note }}</span>
				</div>
			</div>
		</div>

		<DashboardWidgetBox storageKey="profit_and_loss_chart_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-chart-bar" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("Sales and gross margin by month") }}
				</span>
			</template>
			<div class="h-72">
				<Chart type="bar" :data="barChart" :options="barOptions" class="h-full" />
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox storageKey="profit_and_loss_statement_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-list-ul" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("Profit and loss") }}
				</span>
				<span class="text-xs text-gray-400">{{ ctrans("— means no bank lines that month") }}</span>
			</template>
			<div class="overflow-x-auto">
				<table class="w-full text-sm tabular-nums">
					<thead class="whitespace-nowrap text-xs uppercase text-gray-500">
						<tr>
							<th class="sticky left-0 bg-white py-1.5 pr-4 text-left font-medium" />
							<th v-for="month in profitAndLoss.months" :key="month.month" class="px-2 py-1.5 text-right font-medium">{{ monthLabel(month.month) }}</th>
							<th class="border-l border-gray-200 px-2 py-1.5 text-right font-medium">{{ ctrans("12 months") }}</th>
							<th class="px-2 py-1.5 text-right font-medium">{{ ctrans("A year before") }}</th>
							<th class="py-1.5 pl-2 text-right font-medium">{{ ctrans("Change") }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="row in rows" :key="row.key" :class="row.strong ? 'border-y border-gray-300 bg-gray-50' : 'border-t border-gray-100'">
							<td class="sticky left-0 whitespace-nowrap py-1.5 pr-4" :class="[row.strong ? 'bg-gray-50 font-semibold' : 'bg-white', row.cost ? 'pl-3 text-gray-700' : '', row.memo ? 'text-xs text-gray-400' : '']">
								{{ row.label }}
							</td>
							<td v-for="month in profitAndLoss.months" :key="month.month" class="whitespace-nowrap px-2 py-1.5 text-right" :class="cellClass(row, month[row.key])">
								{{ cell(row, month[row.key]) }}
							</td>
							<td class="whitespace-nowrap border-l border-gray-200 px-2 py-1.5 text-right" :class="cellClass(row, total[row.key])">
								{{ cell(row, total[row.key]) }}
							</td>
							<td class="whitespace-nowrap px-2 py-1.5 text-right text-gray-500">
								{{ cell(row, total.last_year[row.key]) }}
							</td>
							<td class="whitespace-nowrap py-1.5 pl-2 text-right text-xs">
								<span v-if="!row.memo && change(total[row.key], total.last_year[row.key])" :class="change(total[row.key], total.last_year[row.key])!.up === !row.cost ? 'text-green-700' : 'text-red-600'">
									{{ change(total[row.key], total.last_year[row.key])!.text }}
								</span>
							</td>
						</tr>
						<tr class="border-t border-gray-100 text-xs text-gray-500">
							<td class="sticky left-0 whitespace-nowrap bg-white py-1.5 pr-4">{{ ctrans("Gross margin %") }}</td>
							<td v-for="month in profitAndLoss.months" :key="month.month" class="whitespace-nowrap px-2 py-1.5 text-right">
								{{ marginPercent(month) === null ? "" : `${locale.number(marginPercent(month)!)}%` }}
							</td>
							<td class="whitespace-nowrap border-l border-gray-200 px-2 py-1.5 text-right">{{ marginPercent(total) === null ? "" : `${locale.number(marginPercent(total)!)}%` }}</td>
							<td class="whitespace-nowrap px-2 py-1.5 text-right">{{ marginPercent(total.last_year) === null ? "" : `${locale.number(marginPercent(total.last_year)!)}%` }}</td>
							<td />
						</tr>
					</tbody>
				</table>
			</div>
			<ul class="mt-3 space-y-0.5 text-xs text-gray-500">
				<li>{{ ctrans("Sales are invoices less refunds. Cost of goods is the official (FIFO) value of the stock picked, less cancelled and returned picks.") }}</li>
				<li>{{ ctrans("Payments to suppliers are stock, already in cost of goods; refunds to customers are already refund invoices; payments between our companies are not costs.") }}</li>
				<li v-if="isGroup">{{ ctrans("Sales between our companies and the stock picked for them are left out of the group.") }}</li>
				<li v-if="unknownFees">{{ ctrans(":count card payouts are not reconciled yet: their fees are not counted.", { count: unknownFees }) }}</li>
			</ul>
		</DashboardWidgetBox>
	</div>
</template>
