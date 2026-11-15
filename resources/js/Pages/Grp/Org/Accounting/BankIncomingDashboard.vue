<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed } from "vue"
import Chart from "primevue/chart"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUniversity, faClipboardListCheck, faCheckCircle, faChartLine, faChartPie, faUserClock, faRobot, faListUl } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faUniversity, faClipboardListCheck, faCheckCircle, faChartLine, faChartPie, faUserClock, faRobot, faListUl)

type SectionKey = "customers" | "marketplaces" | "payment_accounts" | "other" | "needs_person"
interface Figure {
	lines: number
	amount: number
}
interface Ranked extends Figure {
	key: string
	label: string
	native?: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	query: Record<string, string>
	orgCurrency: string
	dashboard: {
		period: "90d" | "1y" | "all"
		total: Figure & { previous_amount: number | null }
		sections: (Figure & { key: SectionKey; previous_amount: number | null; waiting: number })[]
		backlog: Figure & { oldest: string | null; reasons: Ranked[] }
		own: Figure
		placement: Record<"auto" | "hand" | "waiting" | "unread", Figure>
		trend: { starts: string[]; bin_days: number; series: Record<SectionKey, number[]>; previous: number[] | null }
		providers: Ranked[]
		currencies: Ranked[]
		banks: Ranked[]
	}
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.orgCurrency, amount)
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.orgCurrency, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const sentenceCase = (text: string) => text.charAt(0).toUpperCase() + text.slice(1)

const sectionMeta: Record<SectionKey, { label: string; color: string; hint: string }> = {
	customers: { label: ctrans("Customers paying orders"), color: "#1f845a", hint: ctrans("Placed on customer orders") },
	marketplaces: { label: ctrans("Marketplaces"), color: "#c0399f", hint: ctrans("Payouts from marketplaces") },
	payment_accounts: { label: ctrans("Provider payouts"), color: "#3b82f6", hint: ctrans("Checkout, PastPay and cash on delivery carriers paying us out") },
	needs_person: { label: ctrans("Needs a person"), color: "#f59e0b", hint: ctrans("Waiting to be placed") },
	other: { label: ctrans("Other"), color: "#9ca3af", hint: ctrans("Not for an order") },
}
const sectionOrder: SectionKey[] = ["customers", "marketplaces", "payment_accounts", "needs_person", "other"]
const sections = computed(() => sectionOrder.map((key) => ({ ...props.dashboard.sections.find((section) => section.key === key)!, ...sectionMeta[key] })))

const sectionRoute = (key: SectionKey) => route(`grp.org.accounting.bank_incoming.${key}`, { organisation: route().params["organisation"], ...props.query })

const periodOptions = [
	{ label: ctrans("Last 90 days"), value: "90d" },
	{ label: ctrans("Last year"), value: "1y" },
	{ label: ctrans("All"), value: "all" },
]
const period = computed({
	get: () => props.dashboard.period,
	set: (value: string) => router.get(route("grp.org.accounting.bank_incoming.index", [route().params["organisation"]]), value === "90d" ? {} : { period: value }),
})
const previousLabel = computed(() => (props.dashboard.period === "1y" ? ctrans("the year before") : ctrans("the 90 days before")))

const delta = (amount: number, previous: number | null) => {
	if (!previous) {
		return null
	}
	const change = ((amount - previous) / previous) * 100
	return { up: change >= 0, text: `${change >= 0 ? "▲" : "▼"} ${locale.number(Math.abs(Math.round(change)))}%` }
}
const totalDelta = computed(() => delta(props.dashboard.total.amount, props.dashboard.total.previous_amount))
const share = (amount: number) => (props.dashboard.total.amount > 0 ? Math.round((amount / props.dashboard.total.amount) * 100) : 0)

const oldestDays = computed(() => (props.dashboard.backlog.oldest ? Math.floor((Date.now() - Date.parse(props.dashboard.backlog.oldest)) / 86400000) : null))

const placementMeta = [
	{ key: "auto" as const, label: ctrans("Placed automatically"), color: "#1f845a" },
	{ key: "hand" as const, label: ctrans("Placed by hand"), color: "#0ea5e9" },
	{ key: "waiting" as const, label: ctrans("Waiting for a person"), color: "#f59e0b" },
	{ key: "unread" as const, label: ctrans("Not matched yet"), color: "#e5e7eb" },
]
const placedLines = computed(() => placementMeta.reduce((carry, item) => carry + props.dashboard.placement[item.key].lines, 0))
const placement = computed(() =>
	placementMeta
		.map((item) => ({ ...item, ...props.dashboard.placement[item.key], percent: placedLines.value ? (props.dashboard.placement[item.key].lines / placedLines.value) * 100 : 0 }))
		.filter((item) => item.lines > 0)
)
const autoPercent = computed(() => Math.round(placement.value.find((item) => item.key === "auto")?.percent ?? 0))

const trendLabels = computed(() => props.dashboard.trend.starts.map((start) => useFormatTime(start, { formatTime: props.dashboard.trend.bin_days === 7 ? "d MMM" : "MMM yy" })))
const hasTrend = computed(() => props.dashboard.total.amount > 0)
const lineChart = computed(() => {
	const pointRadius = trendLabels.value.length > 40 ? 0 : 2
	return {
		labels: trendLabels.value,
		datasets: [
			...sections.value
				.filter((section) => section.lines > 0)
				.map((section) => ({ label: section.label, data: props.dashboard.trend.series[section.key], borderColor: section.color, backgroundColor: section.color, tension: 0, borderWidth: 1.5, pointRadius })),
			...(props.dashboard.trend.previous
				? [{ label: ctrans("All received, :period", { period: previousLabel.value }), data: props.dashboard.trend.previous, borderColor: "#9ca3af", backgroundColor: "#9ca3af", borderDash: [4, 4], tension: 0, borderWidth: 1.5, pointRadius: 0, hidden: true }]
				: []),
		],
	}
})
const lineOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: {
			position: "bottom",
			labels: { boxWidth: 12 },
			onClick: (_event: unknown, legendItem: { datasetIndex: number }, legend: { chart: any }) => {
				const chart = legend.chart
				const isVisible = chart.isDatasetVisible(legendItem.datasetIndex)
				const visibleCount = chart.data.datasets.filter((_dataset: unknown, index: number) => chart.isDatasetVisible(index)).length
				if (isVisible && visibleCount <= 1) return
				chart.setDatasetVisibility(legendItem.datasetIndex, !isVisible)
				chart.update()
			},
		},
		tooltip: {
			callbacks: {
				title: (items: any[]) => ctrans(props.dashboard.trend.bin_days === 7 ? "Week from :date" : "30 days from :date", { date: items[0].label }),
				label: (item: { dataset: { label?: string }; raw: unknown }) => ` ${item.dataset.label}: ${money(Number(item.raw))}`,
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 13, maxRotation: 0 } },
		y: { beginAtZero: true, ticks: { callback: (value: number | string) => shortMoney(Number(value)) } },
	},
}))

const donutSections = computed(() => sections.value.filter((section) => section.lines > 0))
const donutChart = computed(() => ({
	labels: donutSections.value.map((section) => section.label),
	datasets: [{ data: donutSections.value.map((section) => section.amount), backgroundColor: donutSections.value.map((section) => section.color) }],
}))
const donutOptions = {
	responsive: true,
	maintainAspectRatio: false,
	cutout: "70%",
	hoverOffset: 8,
	onHover: (_event: unknown, activeElements: { index: number }[], chart: { canvas: HTMLCanvasElement }) => {
		chart.canvas.style.cursor = activeElements.length ? "pointer" : "default"
	},
	onClick: (_event: unknown, activeElements: { index: number }[]) => {
		const section = donutSections.value[activeElements[0]?.index]
		if (section) router.visit(sectionRoute(section.key))
	},
	plugins: {
		legend: { display: false },
		tooltip: { padding: 10, boxPadding: 6, callbacks: { label: (item: { raw: number }) => money(item.raw) } },
	},
}
const placementChart = computed(() => ({
	labels: placement.value.map((item) => item.label),
	datasets: [{ data: placement.value.map((item) => item.lines), backgroundColor: placement.value.map((item) => item.color) }],
}))
const placementOptions = {
	responsive: true,
	maintainAspectRatio: false,
	cutout: "70%",
	plugins: {
		legend: { display: false },
		tooltip: { padding: 10, boxPadding: 6, callbacks: { label: (item: { raw: number }) => ctrans(item.raw === 1 ? ":count payment" : ":count payments", { count: locale.number(item.raw) }) } },
	},
}

const breakdowns = computed(() => [
	{ key: "providers", title: ctrans("By payment provider"), rows: props.dashboard.providers, color: "#3b82f6", empty: ctrans("No payment provider paid out in this period.") },
	{ key: "currencies", title: ctrans("By currency"), rows: props.dashboard.currencies, color: "#1f845a", empty: "" },
	{ key: "banks", title: ctrans("By bank connection"), rows: props.dashboard.banks, color: "#c0399f", empty: "" },
])
const barWidth = (row: Ranked, rows: Ranked[]) => `${Math.max(2, (row.amount / Math.max(...rows.map((item) => item.amount), 1)) * 100)}%`
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex flex-wrap items-end justify-between gap-3">
			<div>
				<div class="text-xs text-gray-500">{{ ctrans("Received by the banks") }}</div>
				<div class="flex flex-wrap items-baseline gap-x-3">
					<span class="text-3xl font-semibold tabular-nums">{{ money(dashboard.total.amount) }}</span>
					<span class="text-sm tabular-nums text-gray-500">{{ ctrans(dashboard.total.lines === 1 ? ":count payment" : ":count payments", { count: locale.number(dashboard.total.lines) }) }}</span>
					<span v-if="totalDelta" class="whitespace-nowrap text-sm tabular-nums" :class="totalDelta.up ? 'text-green-700' : 'text-red-600'">
						{{ totalDelta.text }} <span class="text-gray-500">{{ ctrans("vs :period", { period: previousLabel }) }}</span>
					</span>
				</div>
			</div>
			<SegmentedToggle v-model="period" :options="periodOptions" soft :aria-label="ctrans('Received in')" />
		</div>

		<div v-if="!dashboard.total.lines" class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center">
			<div class="text-lg font-light text-gray-600">{{ ctrans("The banks received nothing in this period") }}</div>
			<p class="mt-1 text-sm text-gray-500">{{ ctrans("Choose a longer period to see what came in before.") }}</p>
		</div>

		<template v-else>
			<div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
				<Link
					v-for="section in sections"
					:key="section.key"
					:href="sectionRoute(section.key)"
					class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm transition hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--app-accent)]"
				>
					<div class="flex items-center gap-2 text-xs text-gray-600">
						<span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: section.color }" aria-hidden="true" />
						<span class="truncate font-medium">{{ section.label }}</span>
					</div>
					<template v-if="section.lines">
						<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(section.amount) }}</div>
						<div class="flex flex-wrap items-baseline gap-x-2 text-xs tabular-nums text-gray-500">
							<span>{{ ctrans(section.lines === 1 ? ":count payment" : ":count payments", { count: locale.number(section.lines) }) }}</span>
							<span>· {{ share(section.amount) }}%</span>
							<span v-if="delta(section.amount, section.previous_amount)" :class="section.key === 'needs_person' ? 'text-gray-500' : delta(section.amount, section.previous_amount)!.up ? 'text-green-700' : 'text-red-600'">
								{{ delta(section.amount, section.previous_amount)!.text }}
							</span>
						</div>
						<div v-if="section.waiting" class="mt-1 text-xs text-amber-700">{{ ctrans(":count not linked to an order yet", { count: locale.number(section.waiting) }) }}</div>
					</template>
					<div v-else class="mt-2 text-sm text-gray-400">{{ section.key === "needs_person" ? ctrans("Nothing waiting") : ctrans("None in this period") }}</div>
				</Link>
			</div>
			<p v-if="dashboard.own.lines" class="-mt-2 text-xs text-gray-500">
				{{ ctrans("Not counted as received: :count movements of the bank itself (cashback, transfers between our balances) worth :amount.", { count: locale.number(dashboard.own.lines), amount: money(dashboard.own.amount) }) }}
				<Link :href="sectionRoute('other')" class="underline decoration-gray-300 hover:text-gray-700">{{ ctrans("See them in Other") }}</Link>
			</p>

			<DashboardWidgetBox storageKey="bank_incoming_received_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-chart-line" class="text-pink-600" fixed-width aria-hidden="true" />
						{{ dashboard.trend.bin_days === 7 ? ctrans("Received each week") : ctrans("Received every 30 days") }}
					</span>
					<span class="text-xs text-gray-400">{{ money(dashboard.total.amount) }} {{ ctrans("received") }} · {{ ctrans(dashboard.total.lines === 1 ? ":count payment" : ":count payments", { count: locale.number(dashboard.total.lines) }) }}</span>
				</template>
				<div class="grid gap-6 lg:grid-cols-5">
					<div class="h-72 lg:col-span-3">
						<Chart v-if="hasTrend" type="line" :data="lineChart" :options="lineOptions" class="h-full" />
						<p v-else class="py-10 text-center text-sm text-gray-500">{{ ctrans("No exchange rate is known for what came in, so there is no trend to draw.") }}</p>
					</div>
					<div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
						<p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
							<FontAwesomeIcon icon="fal fa-chart-pie" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
							{{ ctrans("Where the money went") }}
							<span class="text-xs font-normal text-gray-400">{{ orgCurrency }}</span>
						</p>
						<div class="flex flex-wrap items-center gap-4 xl:flex-nowrap">
							<div class="relative h-44 w-44 shrink-0">
								<Chart type="doughnut" :data="donutChart" :options="donutOptions" class="relative z-10 h-full" />
								<div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
									<span class="text-2xl font-bold tabular-nums">{{ shortMoney(dashboard.total.amount) }}</span>
									<span class="text-xs text-gray-500">{{ ctrans("Total") }}</span>
								</div>
							</div>
							<table class="text-sm tabular-nums">
								<tbody>
									<tr
										v-for="section in donutSections"
										:key="section.key"
										tabindex="0"
										role="link"
										class="group cursor-pointer outline-none"
										@click="router.visit(sectionRoute(section.key))"
										@keydown.enter="router.visit(sectionRoute(section.key))">
										<td class="rounded-l-md py-1 pl-2 pr-3 transition duration-200 group-hover:bg-[var(--app-accent-soft)] group-focus-visible:bg-[var(--app-accent-soft)]">
											<span class="flex items-center gap-2">
												<span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: section.color }" />
												{{ section.label }}
											</span>
										</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right text-xs text-gray-500 transition duration-200 group-hover:bg-[var(--app-accent-soft)] group-focus-visible:bg-[var(--app-accent-soft)]">{{ locale.number(section.lines) }}</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right font-medium transition duration-200 group-hover:bg-[var(--app-accent-soft)] group-focus-visible:bg-[var(--app-accent-soft)]">{{ shortMoney(section.amount) }}</td>
										<td class="rounded-r-md py-1 pr-2 text-right text-gray-500 transition duration-200 group-hover:bg-[var(--app-accent-soft)] group-focus-visible:bg-[var(--app-accent-soft)]">{{ share(section.amount) }}%</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</DashboardWidgetBox>

			<DashboardWidgetBox storageKey="bank_incoming_waiting_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-user-clock" class="text-amber-500" fixed-width aria-hidden="true" />
						{{ ctrans("Waiting for a person") }}
					</span>
					<span class="text-xs text-gray-400">{{ ctrans(dashboard.backlog.lines === 1 ? ":count payment" : ":count payments", { count: locale.number(dashboard.backlog.lines) }) }} · {{ money(dashboard.backlog.amount) }}</span>
				</template>
				<div class="grid gap-6 lg:grid-cols-5">
					<div class="lg:col-span-3">
						<template v-if="dashboard.backlog.lines">
							<div class="flex flex-wrap items-baseline gap-x-3">
								<span class="text-3xl font-bold tabular-nums">{{ locale.number(dashboard.backlog.lines) }}</span>
								<span class="text-sm tabular-nums text-gray-500">{{ money(dashboard.backlog.amount) }}</span>
								<span v-if="oldestDays !== null" class="whitespace-nowrap text-sm text-amber-700">{{ ctrans("oldest waiting :days days", { days: locale.number(oldestDays) }) }}</span>
							</div>
							<table class="mt-3 w-full text-sm tabular-nums">
								<tbody>
									<tr v-for="reason in dashboard.backlog.reasons" :key="reason.key" class="border-t border-gray-100">
										<td class="py-1 pr-3 text-gray-700">{{ sentenceCase(ctrans(reason.key)) }}</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right font-medium">{{ locale.number(reason.lines) }}</td>
										<td class="whitespace-nowrap py-1 text-right text-gray-500">{{ money(reason.amount) }}</td>
									</tr>
								</tbody>
							</table>
							<Link :href="sectionRoute('needs_person')" class="mt-3 inline-block">
								<Button type="tertiary" size="xs" :label="ctrans('Open the queue')">
									<template #icon>
										<FontAwesomeIcon icon="fal fa-clipboard-list-check" fixed-width aria-hidden="true" />
									</template>
								</Button>
							</Link>
						</template>
						<p v-else class="flex items-center gap-2 text-sm text-gray-600">
							<FontAwesomeIcon icon="fal fa-check-circle" class="text-green-600" fixed-width aria-hidden="true" />
							{{ ctrans("Everything received in this period is placed.") }}
						</p>
					</div>
					<div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
						<p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
							<FontAwesomeIcon icon="fal fa-robot" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
							{{ ctrans("How payments were placed") }}
						</p>
						<div class="flex flex-wrap items-center gap-4 sm:flex-nowrap">
							<div class="relative h-32 w-32 shrink-0">
								<Chart type="doughnut" :data="placementChart" :options="placementOptions" class="relative z-10 h-full" />
								<div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
									<span class="text-2xl font-bold">{{ autoPercent }}%</span>
									<span class="text-xs text-gray-500">{{ ctrans("automatic") }}</span>
								</div>
							</div>
							<table class="text-sm tabular-nums">
								<tbody>
									<tr v-for="item in placement" :key="item.key">
										<td class="py-1 pr-3">
											<span class="flex items-center gap-2">
												<span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: item.color }" />
												{{ item.label }}
											</span>
										</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right text-xs text-gray-500">{{ ctrans(item.lines === 1 ? ":count payment" : ":count payments", { count: locale.number(item.lines) }) }}</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right font-medium">{{ shortMoney(item.amount) }}</td>
										<td class="py-1 text-right text-gray-500">{{ Math.round(item.percent) }}%</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</DashboardWidgetBox>

			<DashboardWidgetBox storageKey="bank_incoming_breakdowns_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-list-ul" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
						{{ ctrans("Where it came from") }}
					</span>
					<span class="text-xs text-gray-400">{{ ctrans("by payment provider, currency and bank connection") }}</span>
				</template>
				<div class="grid gap-6 lg:grid-cols-3">
					<div v-for="(breakdown, index) in breakdowns" :key="breakdown.key" :class="index ? 'lg:border-l lg:border-gray-100 lg:pl-6' : ''">
						<p class="mb-2 text-sm font-semibold text-gray-600">{{ breakdown.title }}</p>
						<p v-if="!breakdown.rows.length" class="text-sm text-gray-400">{{ breakdown.empty }}</p>
						<ul v-else class="space-y-2 text-sm">
							<li v-for="row in breakdown.rows" :key="row.key">
								<div class="flex items-baseline gap-2 tabular-nums">
									<span class="min-w-0 flex-1 truncate">{{ row.label }}</span>
									<span class="whitespace-nowrap text-xs text-gray-500">{{ ctrans(row.lines === 1 ? ":count payment" : ":count payments", { count: locale.number(row.lines) }) }}</span>
									<span class="whitespace-nowrap font-medium">{{ money(row.amount) }}</span>
								</div>
								<div class="mt-1 h-1.5 w-full rounded-full bg-gray-100">
									<div class="h-1.5 rounded-full" :style="{ width: barWidth(row, breakdown.rows), backgroundColor: breakdown.color }" />
								</div>
								<div v-if="breakdown.key === 'currencies' && row.key !== orgCurrency && row.native" class="mt-0.5 text-xs tabular-nums text-gray-400">{{ locale.currencyFormat(row.key, row.native) }}</div>
							</li>
						</ul>
					</div>
				</div>
			</DashboardWidgetBox>
		</template>
	</div>
</template>
