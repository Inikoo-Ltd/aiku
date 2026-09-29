<script setup>
import { Link, router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { computed, inject } from "vue"
import Chart from "primevue/chart"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxOpen, faPercentage, faCoins, faChartLine, faChartPie } from "@fal"

library.add(faBoxOpen, faPercentage, faCoins, faChartLine, faChartPie)

const props = defineProps({
	stockOuts: { type: Object, required: true },
	stockLevels: { type: Array, default: () => [] },
	storageKey: { type: String, required: true },
})

const locale = inject("locale", aikuLocaleStructure)

const toneColor = {
	"red-deep": "#b91c1c",
	red: "#ef4444",
	orange: "#f97316",
	amber: "#fbbf24",
	yellow: "#fde047",
	green: "#16a34a",
	blue: "#3b82f6",
	gray: "#9ca3af",
}

const money = (value) =>
	value === null || value === undefined
		? "-"
		: new Intl.NumberFormat(undefined, { style: "currency", currency: props.stockOuts.currency, maximumFractionDigits: 0 }).format(value)

const outOfStockRoute = computed(() => props.stockLevels.find((level) => level.bucket === "out")?.route)

const bucketLabel = (date) => {
	const parsed = new Date(`${date}T00:00:00`)
	if (props.stockOuts.unit === "month") {
		return parsed.toLocaleDateString(undefined, { month: "short", year: "2-digit" })
	}
	return parsed.toLocaleDateString(undefined, { day: "numeric", month: "short", ...(props.stockOuts.unit === "week" ? { year: "2-digit" } : {}) })
}

const stockOutChart = computed(() => {
	const series = props.stockOuts.series ?? []
	const pointRadius = series.length > 40 ? 0 : 2
	return {
		labels: series.map((row) => bucketLabel(row.date)),
		datasets: [
			{ label: ctrans("Out of stock"), data: series.map((row) => row.out_of_stock), borderColor: "#dc2626", backgroundColor: "#dc2626", tension: 0, borderWidth: 1.5, pointRadius, yAxisID: "y" },
			{ label: ctrans("Estimated lost revenue per day"), data: series.map((row) => row.lost_per_day), borderColor: "#f59e0b", backgroundColor: "#f59e0b", tension: 0, borderWidth: 1.5, pointRadius, yAxisID: "money" },
		],
	}
})

const stockOutOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 12 } },
		tooltip: {
			callbacks: {
				title: (items) => (props.stockOuts.unit === "day" ? items[0].label : `${ctrans(props.stockOuts.unit === "week" ? "Week of" : "Month")} ${items[0].label}`),
				label: (item) =>
					item.dataset.yAxisID === "money"
						? `${item.dataset.label}: ${money(item.raw)}`
						: `${item.dataset.label}: ${locale.number(item.raw)} (${props.stockOuts.series[item.dataIndex].percentage}%)`,
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
		y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: ctrans("SKOs out of stock") } },
		money: { beginAtZero: true, position: "right", grid: { display: false }, ticks: { callback: (value) => money(value) } },
	},
}))

const visibleLevels = computed(() => props.stockLevels.filter((level) => level.count))

const coverTotal = computed(() => props.stockLevels.reduce((sum, level) => sum + level.count, 0))

const coverChart = computed(() => ({
	labels: props.stockLevels.map((level) => level.label),
	datasets: [{ data: props.stockLevels.map((level) => level.count), backgroundColor: props.stockLevels.map((level) => toneColor[level.tone] ?? "#9ca3af") }],
}))

const coverOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	cutout: "70%",
	hoverOffset: 6,
	onHover: (_event, activeElements, chart) => {
		chart.canvas.style.cursor = activeElements.length && props.stockLevels[activeElements[0].index]?.route ? "pointer" : "default"
	},
	onClick: (_event, activeElements) => {
		const level = props.stockLevels[activeElements[0]?.index]
		if (level?.route) router.visit(route(level.route.name, level.route.parameters))
	},
	plugins: { legend: { display: false } },
}))
</script>

<template>
	<div class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
		<TicketsCreatedInterval :options="stockOuts.periods" :selected="stockOuts.period" label="Stock outs" param="period" :storageKey="`${storageKey}-period`" />
		<div class="flex flex-wrap gap-3 text-sm tabular-nums">
			<component
				:is="outOfStockRoute ? Link : 'span'"
				v-tooltip="ctrans('SKOs out of stock') + (stockOuts.now ? ' · ' + stockOuts.now.date : '')"
				:href="outOfStockRoute ? route(outOfStockRoute.name, outOfStockRoute.parameters) : undefined"
				class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700 shadow-sm"
				:class="{ 'hover:bg-gray-50': outOfStockRoute }">
				<FontAwesomeIcon icon="fal fa-box-open" class="text-red-600" fixed-width aria-hidden="true" />
				{{ stockOuts.now ? locale.number(stockOuts.now.out_of_stock) : "-" }}
				<span class="font-normal text-gray-400">{{ ctrans("out of stock") }}</span>
			</component>
			<span v-tooltip="ctrans('Share of SKOs out of stock')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700 shadow-sm">
				<FontAwesomeIcon icon="fal fa-percentage" class="text-red-600" fixed-width aria-hidden="true" />
				{{ stockOuts.now ? stockOuts.now.percentage + "%" : "-" }}
			</span>
			<span
				v-tooltip="ctrans('Estimated lost revenue per day: what the SKOs out of stock sold per day on average over the 6 months before')"
				class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700 shadow-sm">
				<FontAwesomeIcon icon="fal fa-coins" class="text-amber-600" fixed-width aria-hidden="true" />
				{{ money(stockOuts.now?.lost_per_day) }}
				<span class="font-normal text-gray-400">/ {{ ctrans("day") }}</span>
			</span>
			<span v-tooltip="ctrans('Estimated lost revenue in this period')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700 shadow-sm">
				<FontAwesomeIcon icon="fal fa-coins" class="text-amber-600" fixed-width aria-hidden="true" />
				{{ money(stockOuts.lost_total) }}
				<span class="font-normal text-gray-400">{{ ctrans("lost in period") }}</span>
			</span>
		</div>
		<DashboardWidgetBox :storageKey="`${storageKey}-collapsed`">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-chart-line" class="text-red-600" fixed-width aria-hidden="true" />
					{{ ctrans("Out of stock history") }}
				</span>
			</template>
			<div class="grid gap-6 lg:grid-cols-5">
				<div class="h-72 lg:col-span-3">
					<Chart type="line" :data="stockOutChart" :options="stockOutOptions" class="h-full" />
				</div>
				<div v-if="visibleLevels.length" class="min-w-0 lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-5">
					<p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-chart-pie" class="text-blue-600" fixed-width aria-hidden="true" />
						{{ ctrans("Stock cover now") }}
						<span class="text-xs font-normal text-gray-400">{{ ctrans("Active SKOs") }}</span>
					</p>
					<div class="flex flex-wrap items-center gap-4 sm:flex-nowrap">
						<div class="relative h-40 w-40 shrink-0">
							<Chart type="doughnut" :data="coverChart" :options="coverOptions" class="relative z-10 h-full" />
							<div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
								<span class="text-2xl font-bold tabular-nums">{{ locale.number(coverTotal) }}</span>
								<span class="text-xs text-gray-500">{{ ctrans("SKOs") }}</span>
							</div>
						</div>
						<table class="w-full min-w-0 text-[13px] tabular-nums">
							<tbody>
								<tr v-for="level in visibleLevels" :key="level.bucket" class="group">
									<td class="rounded-l-md py-1 pl-1.5 pr-2 leading-snug" :class="{ 'group-hover:bg-[--app-accent-soft]': level.route }">
										<component
											:is="level.route ? Link : 'span'"
											:href="level.route ? route(level.route.name, level.route.parameters) : undefined"
											class="flex items-center gap-1.5"
											:class="{ 'group-hover:text-[--app-accent-strong]': level.route }">
											<span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: toneColor[level.tone] ?? '#9ca3af' }" />
											{{ level.label }}
										</component>
									</td>
									<td class="whitespace-nowrap py-1 pr-2 text-right font-medium" :class="{ 'group-hover:bg-[--app-accent-soft]': level.route }">{{ locale.number(level.count) }}</td>
									<td class="whitespace-nowrap rounded-r-md py-1 pr-1.5 text-right text-gray-500" :class="{ 'group-hover:bg-[--app-accent-soft]': level.route }">
										{{ coverTotal ? ((level.count / coverTotal) * 100).toFixed(1) : 0 }}%
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</DashboardWidgetBox>
	</div>
</template>
