<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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
import { faChartLine, faChartPie, faFileInvoice, faShip, faHandHoldingUsd, faTruckContainer, faUsers, faCalendarAlt, faListUl, faExclamationTriangle, faWallet, faHourglassHalf } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faChartLine, faChartPie, faFileInvoice, faShip, faHandHoldingUsd, faTruckContainer, faUsers, faCalendarAlt, faListUl, faExclamationTriangle, faWallet, faHourglassHalf)

interface Slice {
	key: string
	count: number
	amount: number
}
interface Ranked extends Slice {
	label: string
	native?: number
}
interface Creditor extends Ranked {
	type: "supplier" | "agent" | "issuer"
	slug: string | null
	organisation_slug: string
	organisation_code: string
	oldest: string
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	organisation: string | null
	organisations: { value: string; label: string }[]
	isGroup: boolean
	currency: string
	kindLabels: Record<string, string>
	payables: {
		total: { amount: number; count: number }
		overdue: { amount: number; count: number }
		due_7: { amount: number; count: number }
		due_30: { amount: number; count: number }
		kinds: Slice[]
		due: Slice[]
		age: Slice[]
		apart: Record<"not_invoiced" | "awaiting_approval" | "in_wallet", { amount: number; count: number }>
		creditors: Creditor[]
		currencies: Ranked[]
		organisations: Ranked[]
		unconverted: { currency: string; amount: number }[]
	}
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currency, amount)
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.currency, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const documents = (count: number) => ctrans(count === 1 ? ":count document" : ":count documents", { count: locale.number(count) })

const base = computed(() => (props.isGroup ? "grp.financials.payables." : "grp.org.accounting.payables."))
const sectionRoute = (section: string) => route(base.value + section, props.isGroup ? (props.organisation ? { org: props.organisation } : {}) : { organisation: route().params.organisation })

const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const organisation = computed({
	get: () => props.organisation ?? "all",
	set: (value: string) => router.get(route("grp.financials.payables.index", value === "all" ? {} : { org: value })),
})

const kindMeta: Record<string, { icon: string; color: string }> = {
	supplier_invoices: { icon: "fal fa-file-invoice", color: "#1f845a" },
	agent_wallets: { icon: "fal fa-wallet", color: "#8b5cf6" },
	agent_invoices: { icon: "fal fa-ship", color: "#3b82f6" },
	money_requests: { icon: "fal fa-hand-holding-usd", color: "#c0399f" },
	service_invoices: { icon: "fal fa-truck-container", color: "#f59e0b" },
}

const dueLabels: Record<string, string> = {
	overdue_90: ctrans("Overdue 90+ days"),
	overdue_61_90: ctrans("Overdue 61-90"),
	overdue_31_60: ctrans("Overdue 31-60"),
	overdue_1_30: ctrans("Overdue 1-30"),
	due_7: ctrans("Due in 7 days"),
	due_30: ctrans("Due in 8-30 days"),
	later: ctrans("Later"),
	none: ctrans("No due date"),
}
const dueColors: Record<string, string> = {
	overdue_90: "#991b1b",
	overdue_61_90: "#dc2626",
	overdue_31_60: "#ef4444",
	overdue_1_30: "#f87171",
	due_7: "#f59e0b",
	due_30: "#fbbf24",
	later: "#1f845a",
	none: "#9ca3af",
}
const ageLabels: Record<string, string> = {
	age_0_30: ctrans("0-30 days old"),
	age_31_60: ctrans("31-60 days old"),
	age_61_90: ctrans("61-90 days old"),
	age_90: ctrans("Over 90 days old"),
}
const ageColors: Record<string, string> = { age_0_30: "#1f845a", age_31_60: "#fbbf24", age_61_90: "#f59e0b", age_90: "#ef4444" }

const apartFigures = computed(() => [
	{ key: "not_invoiced", section: "not_invoiced", icon: "fal fa-hourglass-half", label: ctrans("Not invoiced yet"), note: ctrans("goods received, invoice not entered: an estimate") },
	{ key: "awaiting_approval", section: "money_requests", icon: "fal fa-hand-holding-usd", label: ctrans("Money requests awaiting approval"), note: ctrans("cannot be paid until approved") },
	{ key: "in_wallet", section: "agent_invoices", icon: "fal fa-ship", label: ctrans("Invoices of agents owed through their wallet"), note: ctrans("already in the wallet balance") },
].map((figure) => ({ ...figure, ...props.payables.apart[figure.key as "not_invoiced"] })))

const hasDueDates = computed(() => props.payables.due.some((slice) => slice.key !== "none" && slice.count > 0))

const barChart = (slices: Slice[], labels: Record<string, string>, colors: Record<string, string>) => ({
	labels: slices.map((slice) => labels[slice.key]),
	datasets: [{ data: slices.map((slice) => slice.amount), backgroundColor: slices.map((slice) => colors[slice.key]), borderRadius: 3, maxBarThickness: 48 }],
})
const dueChart = computed(() => barChart(props.payables.due, dueLabels, dueColors))
const ageChart = computed(() => barChart(props.payables.age, ageLabels, ageColors))
const barOptions = {
	responsive: true,
	maintainAspectRatio: false,
	plugins: {
		legend: { display: false },
		tooltip: { callbacks: { label: (item: { raw: unknown }) => ` ${money(Number(item.raw))}` } },
	},
	scales: {
		x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: false, font: { size: 11 } } },
		y: { beginAtZero: true, ticks: { callback: (value: number | string) => shortMoney(Number(value)) } },
	},
}

const palette = ["#1f845a", "#3b82f6", "#c0399f", "#f59e0b", "#0ea5e9", "#8b5cf6", "#ef4444", "#14b8a6", "#9ca3af"]
const donutCurrencies = computed(() => props.payables.currencies.map((row, index) => ({ ...row, color: palette[Math.min(index, palette.length - 1)] })))
const share = (amount: number) => (props.payables.total.amount > 0 ? Math.round((amount / props.payables.total.amount) * 100) : 0)
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

const barWidth = (row: Slice, rows: Slice[]) => `${Math.max(2, (row.amount / Math.max(...rows.map((item) => item.amount), 1)) * 100)}%`

const creditorTypes: Record<string, string> = { supplier: ctrans("Supplier"), agent: ctrans("Agent"), issuer: ctrans("Service invoices") }
const creditorRoute = (creditor: Creditor) => {
	if (creditor.type === "issuer" || !creditor.slug) {
		return creditor.type === "issuer" ? route("grp.org.procurement.service_invoices.index", [creditor.organisation_slug]) : null
	}
	return route(creditor.type === "agent" ? "grp.org.procurement.org_agents.show" : "grp.org.procurement.org_suppliers.show", [creditor.organisation_slug, creditor.slug])
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex flex-wrap items-end justify-between gap-3">
			<div>
				<div class="text-xs text-gray-500">{{ ctrans("We owe suppliers and agents") }}</div>
				<div class="flex flex-wrap items-baseline gap-x-3">
					<span class="text-3xl font-semibold tabular-nums">{{ money(payables.total.amount) }}</span>
					<span class="text-sm text-gray-500">{{ documents(payables.total.count) }}</span>
				</div>
				<p v-if="payables.unconverted.length" class="mt-0.5 text-xs text-amber-700">
					{{ ctrans("Not in the total, no exchange rate is known: :amounts", { amounts: payables.unconverted.map((row) => locale.currencyFormat(row.currency, row.amount)).join(", ") }) }}
				</p>
			</div>
			<SegmentedToggle v-if="organisations.length > 1" v-model="organisation" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
		</div>

		<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
			<div class="rounded-lg border bg-white px-4 py-3 shadow-sm" :class="payables.overdue.count ? 'border-red-300' : 'border-gray-200'">
				<div class="text-xs text-gray-600">{{ ctrans("Overdue") }}</div>
				<div class="mt-1 text-xl font-semibold tabular-nums" :class="payables.overdue.count ? 'text-red-600' : ''">{{ money(payables.overdue.amount) }}</div>
				<div class="mt-0.5 text-xs text-gray-500">{{ documents(payables.overdue.count) }}</div>
			</div>
			<div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
				<div class="text-xs text-gray-600">{{ ctrans("Due in the next 7 days") }}</div>
				<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(payables.due_7.amount) }}</div>
				<div class="mt-0.5 text-xs text-gray-500">{{ documents(payables.due_7.count) }}</div>
			</div>
			<div class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
				<div class="text-xs text-gray-600">{{ ctrans("Due in the next 30 days") }}</div>
				<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(payables.due_30.amount) }}</div>
				<div class="mt-0.5 text-xs text-gray-500">{{ documents(payables.due_30.count) }}</div>
			</div>
			<div class="rounded-lg border bg-white px-4 py-3 shadow-sm" :class="payables.due[payables.due.length - 1].count ? 'border-amber-300' : 'border-gray-200'">
				<div class="text-xs text-gray-600">{{ ctrans("No due date") }}</div>
				<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(payables.due[payables.due.length - 1].amount) }}</div>
				<div class="mt-0.5 text-xs text-gray-500">{{ documents(payables.due[payables.due.length - 1].count) }}</div>
			</div>
		</div>

		<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
			<Link
				v-for="kind in payables.kinds"
				:key="kind.key"
				:href="sectionRoute(kind.key)"
				class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm hover:border-gray-300 hover:bg-gray-50">
				<div class="flex items-center gap-2 text-xs text-gray-600">
					<FontAwesomeIcon :icon="kindMeta[kind.key].icon" fixed-width aria-hidden="true" :style="{ color: kindMeta[kind.key].color }" />
					<span class="truncate font-medium">{{ kindLabels[kind.key] }}</span>
				</div>
				<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(kind.amount) }}</div>
				<div class="mt-0.5 text-xs text-gray-500">{{ documents(kind.count) }}</div>
			</Link>
		</div>

		<div>
			<p class="mb-2 text-xs text-gray-500">{{ ctrans("Shown apart, not in the total") }}</p>
			<div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
				<Link
					v-for="figure in apartFigures"
					:key="figure.key"
					:href="sectionRoute(figure.section)"
					class="rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100">
					<div class="flex items-center gap-2 text-xs text-gray-600">
						<FontAwesomeIcon :icon="figure.icon" class="text-gray-400" fixed-width aria-hidden="true" />
						<span class="truncate font-medium">{{ figure.label }}</span>
					</div>
					<div class="mt-1 text-lg font-semibold tabular-nums text-gray-600">{{ money(figure.amount) }}</div>
					<div class="mt-0.5 text-xs text-gray-500">{{ documents(figure.count) }} · {{ figure.note }}</div>
				</Link>
			</div>
		</div>

		<DashboardWidgetBox storageKey="payables_due_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-pink-600" fixed-width aria-hidden="true" />
					{{ ctrans("When it falls due") }}
				</span>
				<span class="text-xs text-gray-400">{{ currency }}</span>
			</template>
			<div class="grid gap-6 lg:grid-cols-2">
				<div>
					<p class="mb-2 text-sm font-semibold text-gray-600">{{ ctrans("By due date") }}</p>
					<div v-if="hasDueDates" class="h-64">
						<Chart type="bar" :data="dueChart" :options="barOptions" class="h-full" />
					</div>
					<p v-else class="flex items-start gap-2 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
						<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5" fixed-width aria-hidden="true" />
						<span>{{ ctrans("None of these documents carries a due date, and the payment terms agreed with suppliers are written as free text, so nothing can be placed as overdue or due soon. The age of each document is shown instead.") }}</span>
					</p>
				</div>
				<div class="lg:border-l lg:border-gray-100 lg:pl-6">
					<p class="mb-2 text-sm font-semibold text-gray-600">
						{{ ctrans("By age of the document") }}
						<span class="text-xs font-normal text-gray-400">{{ ctrans("from the date written on it, not a due date") }}</span>
					</p>
					<div class="h-64">
						<Chart type="bar" :data="ageChart" :options="barOptions" class="h-full" />
					</div>
				</div>
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox storageKey="payables_creditors_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-users" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("Who we owe") }}
				</span>
				<Link :href="sectionRoute('creditors')" class="text-xs text-gray-500 hover:underline">{{ ctrans("All creditors") }}</Link>
			</template>
			<div class="grid gap-6 lg:grid-cols-5">
				<div class="lg:col-span-3">
					<p class="mb-2 text-sm font-semibold text-gray-600">{{ ctrans("Top creditors") }}</p>
					<p v-if="!payables.creditors.length" class="text-sm text-gray-400">{{ ctrans("Nothing owed") }}</p>
					<ul v-else class="space-y-2 text-sm">
						<li v-for="creditor in payables.creditors" :key="creditor.key">
							<div class="flex items-baseline gap-2 tabular-nums">
								<span class="min-w-0 flex-1 truncate">
									<Link v-if="creditorRoute(creditor)" :href="creditorRoute(creditor)!" class="font-medium hover:underline">{{ creditor.label }}</Link>
									<template v-else>{{ creditor.label }}</template>
									<span class="ml-1 text-xs text-gray-400">{{ creditorTypes[creditor.type] }}<template v-if="isGroup"> · {{ creditor.organisation_code }}</template></span>
								</span>
								<span class="whitespace-nowrap text-xs text-gray-500">{{ documents(creditor.count) }} · {{ ctrans("oldest :date", { date: useFormatTime(creditor.oldest) }) }}</span>
								<span class="whitespace-nowrap font-medium">{{ money(creditor.amount) }}</span>
							</div>
							<div class="mt-1 h-1.5 w-full rounded-full bg-gray-100">
								<div class="h-1.5 rounded-full" :style="{ width: barWidth(creditor, payables.creditors), backgroundColor: '#1f845a' }" />
							</div>
						</li>
					</ul>
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
								<span class="text-2xl font-bold tabular-nums">{{ shortMoney(payables.total.amount) }}</span>
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
									<td class="whitespace-nowrap py-1 pr-3 text-right text-xs text-gray-500">{{ row.key === currency ? "" : locale.currencyFormat(row.key, row.native ?? 0) }}</td>
									<td class="whitespace-nowrap py-1 pr-3 text-right font-medium">{{ shortMoney(row.amount) }}</td>
									<td class="py-1 text-right text-gray-500">{{ share(row.amount) }}%</td>
								</tr>
							</tbody>
						</table>
					</div>
					<template v-if="isGroup && payables.organisations.length">
						<p class="mb-2 mt-5 text-sm font-semibold text-gray-600">{{ ctrans("By organisation") }}</p>
						<ul class="space-y-2 text-sm">
							<li v-for="row in payables.organisations" :key="row.key">
								<div class="flex items-baseline gap-2 tabular-nums">
									<span class="min-w-0 flex-1 truncate">{{ row.label }}</span>
									<span class="whitespace-nowrap font-medium">{{ money(row.amount) }}</span>
								</div>
								<div class="mt-1 h-1.5 w-full rounded-full bg-gray-100">
									<div class="h-1.5 rounded-full" :style="{ width: barWidth(row, payables.organisations), backgroundColor: '#f59e0b' }" />
								</div>
							</li>
						</ul>
					</template>
				</div>
			</div>
		</DashboardWidgetBox>

		<DashboardWidgetBox storageKey="payables_rules_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-list-ul" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("What is counted") }}
				</span>
				<span class="text-xs text-gray-400">{{ ctrans("only what is recorded in Aiku") }}</span>
			</template>
			<ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
				<li>{{ ctrans("Supplier invoices: the invoice received for a delivery, less the payments recorded on that delivery and the deposits paid on its purchase orders.") }}</li>
				<li>{{ ctrans("Agent wallets: an agent that pays our suppliers from its money ledger is owed its wallet deficit, what it spent less what it received. Its invoices are listed as detail and not added again.") }}</li>
				<li>{{ ctrans("Agent invoices: for an agent that does not pay suppliers from a money ledger (fees and charges alone do not count), its invoice for a container, less the deposits applied to it and the transfers sent for it.") }}</li>
				<li>{{ ctrans("Agent money requests: approved requests still open, less the transfers recorded against them. A request awaiting approval is listed and not counted.") }}</li>
				<li>{{ ctrans("Service invoices: freight, customs and other bills of a delivery not marked as paid.") }}</li>
				<li>{{ ctrans("Not invoiced yet: deliveries received whose invoice has not been entered, at their estimated invoice. Shown apart, never in the total. Cancelled or not received deliveries are left out.") }}</li>
				<li>{{ ctrans("Amounts in other currencies are converted at today's rate. A payment made outside Aiku and never recorded here still shows as owed.") }}</li>
			</ul>
		</DashboardWidgetBox>
	</div>
</template>
