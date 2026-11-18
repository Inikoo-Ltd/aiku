<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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
import { faHandHoldingUsd, faChartLine, faChartPie, faFileInvoiceDollar, faUserClock, faPiggyBank, faStore, faShareAlt, faListUl, faBuilding, faHistory } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faHandHoldingUsd, faChartLine, faChartPie, faFileInvoiceDollar, faUserClock, faPiggyBank, faStore, faShareAlt, faListUl, faBuilding, faHistory)

interface Ranked {
	key: string
	label: string
	amount: number
	native: number
	invoices: number
}
interface Debtor {
	id: number
	name: string
	reference: string
	shop_code: string
	organisation_code: string
	company: boolean
	amount: number
	invoices: number
	oldest: string
	days_overdue: number
	route: RouteTarget
}
interface RouteTarget {
	name: string
	parameters: Record<string, string> | string[]
}
interface BalanceSide {
	amount: number
	customers: number
	currencies: { currency: string; native: number; converted: number | null; customers: number }[]
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	organisation: string | null
	organisations: { value: string; label: string }[]
	isGroup: boolean
	currency: string
	routes: Record<"index" | "invoices" | "debtors" | "balances" | "cash_on_delivery", RouteTarget>
	receivables: {
		total: { amount: number; invoices: number; debtors: number; overdue: number; over_year: number; cod: number; pastpay: number }
		ageing: { key: string; amount: number; invoices: number }[]
		shops: Ranked[]
		currencies: Ranked[]
		organisations: Ranked[]
		debtors: Debtor[]
		companies: { amount: number; invoices: number }
		legacy: { amount: number; invoices: number; organisations: Ranked[] }
		marketplaces: { amount: number; invoices: number; shops: Ranked[] }
		balances: { credit: BalanceSide; debt: BalanceSide }
		trend: { dates: string[]; totals: number[] }
	}
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currency, amount)
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.currency, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)

const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const organisation = computed({
	get: () => props.organisation ?? "all",
	set: (value: string) => router.get(route("grp.financials.receivables.index", value === "all" ? {} : { org: value })),
})

const listUrl = (section: "invoices" | "debtors" | "balances" | "cash_on_delivery", query: Record<string, string> = {}) => route(props.routes[section].name, { ...props.routes[section].parameters, ...query })

const total = computed(() => props.receivables.total)
const share = (amount: number) => (total.value.amount > 0 ? Math.round((amount / total.value.amount) * 100) : 0)

const ages: Record<string, { label: string; color: string }> = {
	not_due: { label: ctrans("Not yet due"), color: "#1f845a" },
	"1_30": { label: ctrans("1 to 30 days overdue"), color: "#f59e0b" },
	"31_60": { label: ctrans("31 to 60 days overdue"), color: "#ea580c" },
	"61_90": { label: ctrans("61 to 90 days overdue"), color: "#dc2626" },
	over_90: { label: ctrans("Over 90 days overdue"), color: "#991b1b" },
}

const palette = ["#1f845a", "#3b82f6", "#c0399f", "#f59e0b", "#0ea5e9", "#8b5cf6", "#ef4444", "#14b8a6", "#9ca3af"]
const donutCurrencies = computed(() => props.receivables.currencies.map((row, index) => ({ ...row, color: palette[Math.min(index, palette.length - 1)] })))
const donutChart = computed(() => ({
	labels: donutCurrencies.value.map((row) => row.label),
	datasets: [{ data: donutCurrencies.value.map((row) => row.amount), backgroundColor: donutCurrencies.value.map((row) => row.color) }],
}))
const donutOptions = {
	responsive: true,
	maintainAspectRatio: false,
	cutout: "70%",
	hoverOffset: 8,
	plugins: {
		legend: { display: false },
		tooltip: { padding: 10, boxPadding: 6, callbacks: { label: (item: { raw: number }) => money(item.raw) } },
	},
}

const lineChart = computed(() => ({
	labels: props.receivables.trend.dates.map((date) => useFormatTime(date, { formatTime: "d MMM" })),
	datasets: [{ label: ctrans("Owed by customers"), data: props.receivables.trend.totals, borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0, borderWidth: 1.5, pointRadius: 0 }],
}))
const lineOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { display: false },
		tooltip: { callbacks: { label: (item: { raw: unknown }) => ` ${money(Number(item.raw))}` } },
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 13, maxRotation: 0 } },
		y: { beginAtZero: true, ticks: { callback: (value: number | string) => shortMoney(Number(value)) } },
	},
}))

const barWidth = (row: Ranked, rows: Ranked[]) => `${Math.max(2, (row.amount / Math.max(...rows.map((item) => item.amount), 1)) * 100)}%`
const breakdowns = computed(() =>
	[
		{ key: "organisations", title: ctrans("By organisation"), rows: props.receivables.organisations, color: "#f59e0b" },
		{ key: "shops", title: ctrans("By shop"), rows: props.receivables.shops, color: "#3b82f6" },
	].filter((breakdown) => breakdown.rows.length),
)
const balanceSides = computed(() => [
	{ key: "credit", title: ctrans("Credit we hold for customers"), hint: ctrans("money we owe them: positive balances"), side: props.receivables.balances.credit, tone: "text-gray-900" },
	{ key: "debt", title: ctrans("Customers below zero"), hint: ctrans("money they owe us on account: negative balances"), side: props.receivables.balances.debt, tone: "text-red-700" },
])
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex flex-wrap items-end justify-between gap-3">
			<div>
				<div class="text-xs text-gray-500">{{ ctrans("Customers owe us on unpaid invoices") }}</div>
				<div class="flex flex-wrap items-baseline gap-x-3">
					<span class="text-3xl font-semibold tabular-nums">{{ money(total.amount) }}</span>
					<span v-if="total.overdue" class="whitespace-nowrap text-sm tabular-nums text-red-600">{{ ctrans(":amount overdue", { amount: money(total.overdue) }) }}</span>
				</div>
				<div class="mt-0.5 flex flex-wrap gap-x-3 text-xs tabular-nums text-gray-500">
					<Link :href="listUrl('invoices')" class="secondaryLink">{{ ctrans(":count invoices", { count: locale.number(total.invoices) }) }}</Link>
					<Link :href="listUrl('debtors')" class="secondaryLink">{{ ctrans(":count customers", { count: locale.number(total.debtors) }) }}</Link>
					<span v-if="total.over_year">· {{ ctrans(":amount invoiced more than a year ago", { amount: money(total.over_year) }) }}</span>
				</div>
			</div>
			<SegmentedToggle v-if="organisations.length > 1" v-model="organisation" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
		</div>

		<p class="-mt-2 text-xs text-gray-500">
			{{ ctrans("An invoice is due on its date plus the payment terms of the customer; without terms it is due on its date, so its age is counted from the invoice date.") }}
			{{ ctrans("Amounts in other currencies are converted at the rate of the invoice.") }}
		</p>

		<div v-if="!total.invoices" class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center">
			<div class="text-lg font-light text-gray-600">{{ ctrans("No customer owes us") }}</div>
			<p class="mt-1 text-sm text-gray-500">{{ ctrans("Every invoice of a customer is paid.") }}</p>
		</div>

		<template v-else>
			<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
				<Link
					v-for="age in receivables.ageing"
					:key="age.key"
					:href="listUrl('invoices', { age: age.key })"
					class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm hover:border-gray-400">
					<div class="flex items-center gap-2 text-xs text-gray-600">
						<span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: ages[age.key].color }" aria-hidden="true" />
						<span class="truncate font-medium">{{ ages[age.key].label }}</span>
					</div>
					<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(age.amount) }}</div>
					<div class="mt-0.5 flex justify-between text-xs tabular-nums text-gray-500">
						<span>{{ ctrans(":count invoices", { count: locale.number(age.invoices) }) }}</span>
						<span>{{ share(age.amount) }}%</span>
					</div>
					<div class="mt-1.5 h-1.5 w-full rounded-full bg-gray-100">
						<div class="h-1.5 rounded-full" :style="{ width: `${share(age.amount)}%`, backgroundColor: ages[age.key].color }" />
					</div>
				</Link>
			</div>

			<DashboardWidgetBox storageKey="receivables_trend_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-chart-line" class="text-pink-600" fixed-width aria-hidden="true" />
						{{ ctrans("Owed over time") }}
					</span>
					<span class="text-xs text-gray-400">{{ ctrans("weekly, rebuilt from the dates invoices were paid on: an estimate") }}</span>
				</template>
				<div class="grid gap-6 lg:grid-cols-5">
					<div class="lg:col-span-3">
						<div class="h-72">
							<Chart type="line" :data="lineChart" :options="lineOptions" class="h-full" />
						</div>
						<p class="mt-2 text-xs text-gray-500">{{ ctrans("Invoices migrated from Aurora are not counted, so the early weeks read low: the line rising does not mean debt grew.") }}</p>
					</div>
					<div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
						<p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
							<FontAwesomeIcon icon="fal fa-chart-pie" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
							{{ ctrans("By currency") }}
							<span class="text-xs font-normal text-gray-400">{{ currency }}</span>
						</p>
						<div class="flex flex-wrap items-center gap-4 xl:flex-nowrap">
							<div class="relative h-44 w-44 shrink-0">
								<Chart type="doughnut" :data="donutChart" :options="donutOptions" class="relative z-10 h-full" />
								<div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
									<span class="text-2xl font-bold tabular-nums">{{ shortMoney(total.amount) }}</span>
									<span class="text-xs text-gray-500">{{ ctrans("Total") }}</span>
								</div>
							</div>
							<table class="text-sm tabular-nums">
								<tbody>
									<tr v-for="row in donutCurrencies" :key="row.key">
										<td class="py-1 pr-3">
											<span class="flex items-center gap-2">
												<span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: row.color }" />
												{{ row.label }}
											</span>
										</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right text-xs text-gray-500">{{ row.key === currency ? "" : locale.currencyFormat(row.key, row.native) }}</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right font-medium">{{ shortMoney(row.amount) }}</td>
										<td class="py-1 text-right text-gray-500">{{ share(row.amount) }}%</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</DashboardWidgetBox>

			<DashboardWidgetBox storageKey="receivables_debtors_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-user-clock" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
						{{ ctrans("Top debtors") }}
					</span>
					<Link :href="listUrl('debtors')" class="secondaryLink text-xs">{{ ctrans("all :count customers", { count: locale.number(total.debtors) }) }}</Link>
				</template>
				<div class="grid gap-6 lg:grid-cols-5">
					<div class="overflow-x-auto lg:col-span-3">
						<table class="w-full text-sm tabular-nums">
							<thead class="whitespace-nowrap text-left text-xs uppercase text-gray-500">
								<tr>
									<th class="py-1.5 pr-4 font-medium">{{ ctrans("Customer") }}</th>
									<th v-if="isGroup" class="py-1.5 pr-4 font-medium">{{ ctrans("Organisation") }}</th>
									<th class="py-1.5 pr-4 font-medium">{{ ctrans("Shop") }}</th>
									<th class="py-1.5 pr-4 text-right font-medium">{{ ctrans("Invoices") }}</th>
									<th class="py-1.5 pr-4 font-medium">{{ ctrans("Oldest unpaid") }}</th>
									<th class="py-1.5 text-right font-medium">{{ ctrans("Owed") }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="debtor in receivables.debtors" :key="debtor.id" class="border-t border-gray-100">
									<td class="py-1.5 pr-4">
										<Link :href="route(debtor.route.name, debtor.route.parameters)" class="primaryLink">{{ debtor.name || debtor.reference }}</Link>
										<span v-if="debtor.company" class="ml-2 whitespace-nowrap rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-800">{{ ctrans("Our company") }}</span>
									</td>
									<td v-if="isGroup" class="whitespace-nowrap py-1.5 pr-4 text-gray-700">{{ debtor.organisation_code }}</td>
									<td class="whitespace-nowrap py-1.5 pr-4 text-gray-700">{{ debtor.shop_code }}</td>
									<td class="py-1.5 pr-4 text-right">{{ locale.number(debtor.invoices) }}</td>
									<td class="whitespace-nowrap py-1.5 pr-4 text-gray-700">{{ useFormatTime(debtor.oldest, { formatTime: "d MMM yyyy" }) }}</td>
									<td class="whitespace-nowrap py-1.5 text-right font-medium">{{ money(debtor.amount) }}</td>
								</tr>
							</tbody>
						</table>
					</div>
					<div class="space-y-5 lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
						<div v-for="breakdown in breakdowns" :key="breakdown.key">
							<p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
								<FontAwesomeIcon icon="fal fa-store" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
								{{ breakdown.title }}
							</p>
							<ul class="space-y-2 text-sm">
								<li v-for="row in breakdown.rows" :key="row.key">
									<div class="flex items-baseline gap-2 tabular-nums">
										<span class="min-w-0 flex-1 truncate">{{ row.label }}</span>
										<span class="whitespace-nowrap text-xs text-gray-500">{{ locale.number(row.invoices) }}</span>
										<span class="w-28 whitespace-nowrap text-right font-medium">{{ money(row.amount) }}</span>
									</div>
									<div class="mt-1 h-1.5 w-full rounded-full bg-gray-100">
										<div class="h-1.5 rounded-full" :style="{ width: barWidth(row, breakdown.rows), backgroundColor: breakdown.color }" />
									</div>
								</li>
							</ul>
						</div>
					</div>
				</div>
			</DashboardWidgetBox>
		</template>

		<DashboardWidgetBox storageKey="receivables_apart_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-list-ul" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("Counted apart") }}
				</span>
				<span class="text-xs text-gray-400">{{ ctrans("not in the total above, and nothing is netted") }}</span>
			</template>
			<div class="grid gap-6" :class="isGroup ? 'lg:grid-cols-4' : 'lg:grid-cols-3'">
				<div v-for="balance in balanceSides" :key="balance.key" class="lg:border-r lg:border-gray-100 lg:pr-6">
					<p class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-piggy-bank" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
						{{ balance.title }}
					</p>
					<p class="text-xs text-gray-400">{{ balance.hint }}</p>
					<div class="mt-1 text-xl font-semibold tabular-nums" :class="balance.side.amount ? balance.tone : 'text-gray-400'">{{ money(balance.side.amount) }}</div>
					<Link :href="listUrl('balances', { side: balance.key })" class="secondaryLink text-xs tabular-nums">{{ ctrans(":count customers", { count: locale.number(balance.side.customers) }) }}</Link>
					<table v-if="balance.side.currencies.length > 1 || balance.side.currencies.some((row) => row.converted === null)" class="mt-2 text-xs tabular-nums text-gray-600">
						<tbody>
							<tr v-for="row in balance.side.currencies" :key="row.currency">
								<td class="py-0.5 pr-3">{{ row.currency }}</td>
								<td class="whitespace-nowrap py-0.5 pr-3 text-right">{{ locale.currencyFormat(row.currency, row.native) }}</td>
								<td class="py-0.5 text-right text-gray-400">{{ locale.number(row.customers) }}</td>
								<td v-if="row.converted === null" class="py-0.5 pl-3 text-amber-700">{{ ctrans("no exchange rate") }}</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div>
					<p class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-share-alt" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
						{{ ctrans("Marketplaces still to pay out") }}
					</p>
					<p class="text-xs text-gray-400">{{ ctrans("invoices of marketplace shops whose payout is not found in the bank yet") }}</p>
					<div class="mt-1 text-xl font-semibold tabular-nums" :class="receivables.marketplaces.amount ? '' : 'text-gray-400'">{{ money(receivables.marketplaces.amount) }}</div>
					<Link :href="listUrl('invoices', { owed_by: 'marketplaces' })" class="secondaryLink text-xs tabular-nums">{{ ctrans(":count invoices", { count: locale.number(receivables.marketplaces.invoices) }) }}</Link>
					<table v-if="receivables.marketplaces.shops.length > 1" class="mt-2 text-xs tabular-nums text-gray-600">
						<tbody>
							<tr v-for="row in receivables.marketplaces.shops" :key="row.key">
								<td class="py-0.5 pr-3">{{ row.label }}</td>
								<td class="whitespace-nowrap py-0.5 pr-3 text-right">{{ money(row.amount) }}</td>
								<td class="py-0.5 text-right text-gray-400">{{ locale.number(row.invoices) }}</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div v-if="isGroup" class="lg:border-l lg:border-gray-100 lg:pl-6">
					<p class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-building" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
						{{ ctrans("Our companies owe each other") }}
					</p>
					<p class="text-xs text-gray-400">{{ ctrans("unpaid invoices from one company of the group to another") }}</p>
					<div class="mt-1 text-xl font-semibold tabular-nums" :class="receivables.companies.amount ? '' : 'text-gray-400'">{{ money(receivables.companies.amount) }}</div>
					<Link :href="listUrl('invoices', { owed_by: 'companies' })" class="secondaryLink text-xs tabular-nums">{{ ctrans(":count invoices", { count: locale.number(receivables.companies.invoices) }) }}</Link>
				</div>
			</div>
			<div class="mt-4 border-t border-gray-100 pt-3">
				<p class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-history" class="text-amber-500" fixed-width aria-hidden="true" />
					{{ ctrans("Legacy from Aurora, unverified") }}
				</p>
				<p class="text-xs text-gray-400">{{ ctrans("unpaid invoices migrated from Aurora: nobody has checked that they are really owed") }}</p>
				<div class="mt-1 flex flex-wrap items-baseline gap-x-3">
					<span class="text-xl font-semibold tabular-nums" :class="receivables.legacy.amount ? 'text-amber-700' : 'text-gray-400'">{{ money(receivables.legacy.amount) }}</span>
					<Link :href="listUrl('invoices', { owed_by: 'legacy' })" class="secondaryLink text-xs tabular-nums">{{ ctrans(":count invoices", { count: locale.number(receivables.legacy.invoices) }) }}</Link>
				</div>
				<table v-if="receivables.legacy.organisations.length" class="mt-2 text-xs tabular-nums text-gray-600">
					<tbody>
						<tr v-for="row in receivables.legacy.organisations" :key="row.key">
							<td class="py-0.5 pr-3">{{ row.label }}</td>
							<td class="whitespace-nowrap py-0.5 pr-3 text-right">{{ money(row.amount) }}</td>
							<td class="py-0.5 text-right text-gray-400">{{ locale.number(row.invoices) }}</td>
						</tr>
					</tbody>
				</table>
			</div>
			<ul class="mt-4 list-disc space-y-0.5 border-t border-gray-100 pl-5 pt-3 text-xs text-gray-500">
				<li>{{ ctrans("Owed is what is left of an unpaid invoice raised in Aiku after its payments and the unpaid part of its refunds. Deleted invoices, invoices still in process and refunds are left out.") }}</li>
				<li>{{ ctrans("Old invoices without any payment, marked as of unknown payment status, are left out.") }}</li>
				<li v-if="!isGroup && receivables.companies.amount">
					{{ ctrans(":amount of the total is owed by other companies of the group.", { amount: money(receivables.companies.amount) }) }}
					<Link :href="listUrl('invoices', { owed_by: 'companies' })" class="secondaryLink">{{ ctrans(":count invoices", { count: locale.number(receivables.companies.invoices) }) }}</Link>
				</li>
				<li v-if="total.cod">
					{{ ctrans(":amount of the total is cash on delivery, to be paid out by the carrier.", { amount: money(total.cod) }) }}
					<Link :href="listUrl('cash_on_delivery')" class="secondaryLink">{{ ctrans("Cash on delivery not paid") }}</Link>
				</li>
				<li v-if="total.pastpay">{{ ctrans(":amount of the total is to be paid by PastPay.", { amount: money(total.pastpay) }) }}</li>
			</ul>
		</DashboardWidgetBox>
	</div>
</template>
