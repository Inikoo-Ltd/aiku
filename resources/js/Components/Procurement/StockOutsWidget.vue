<script setup>
import { Link, router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { computed, inject, ref } from "vue"
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
	organisationStockLevels: { type: Array, default: null },
	storageKey: { type: String, required: true },
})

const organisationColors = ["#2563eb", "#db2777", "#059669", "#7c3aed", "#ea580c", "#0891b2", "#65a30d", "#9333ea"]

const organisations = computed(() =>
	(props.stockOuts.organisations ?? []).map((organisation, index) => ({ ...organisation, color: organisationColors[index % organisationColors.length] }))
)

const isComparing = computed(() => organisations.value.length > 1)

const selectedSlugs = ref(new Set((props.stockOuts.organisations ?? []).map((organisation) => organisation.slug)))

const selectedOrganisations = computed(() => organisations.value.filter((organisation) => selectedSlugs.value.has(organisation.slug)))

const rankedOrganisations = computed(() => [...organisations.value].sort((a, b) => (b.now?.percentage ?? -1) - (a.now?.percentage ?? -1)))

const toggleOrganisation = (slug) => {
	const selection = new Set(selectedSlugs.value)
	if (selection.has(slug)) {
		if (selection.size > 1) selection.delete(slug)
	} else {
		selection.add(slug)
	}
	selectedSlugs.value = selection
}

const selectOnly = (slug) => {
	selectedSlugs.value = new Set([slug])
}

const selectAll = () => {
	selectedSlugs.value = new Set(organisations.value.map((organisation) => organisation.slug))
}

const metrics = [
	{ key: "percentage", label: "% out of stock" },
	{ key: "out_of_stock", label: "SKOs out of stock" },
	{ key: "lost_per_day", label: "Lost revenue per day" },
]

const metric = ref("percentage")

const headline = computed(() => {
	if (!isComparing.value) {
		return { now: props.stockOuts.now, lostTotal: props.stockOuts.lost_total }
	}
	const withNow = selectedOrganisations.value.filter((organisation) => organisation.now)
	if (!withNow.length) {
		return { now: null, lostTotal: null }
	}
	const outOfStock = withNow.reduce((sum, organisation) => sum + organisation.now.out_of_stock, 0)
	const skos = withNow.reduce((sum, organisation) => sum + organisation.now.skos, 0)
	const lostRows = withNow.filter((organisation) => organisation.now.lost_per_day !== null)
	return {
		now: {
			date: withNow.map((organisation) => organisation.now.date).sort().at(-1),
			out_of_stock: outOfStock,
			percentage: skos ? Math.round((outOfStock / skos) * 1000) / 10 : 0,
			lost_per_day: lostRows.length ? lostRows.reduce((sum, organisation) => sum + organisation.now.lost_per_day, 0) : null,
		},
		lostTotal: selectedOrganisations.value.reduce((sum, organisation) => sum + organisation.lost_total, 0),
	}
})

const coverLevels = computed(() => {
	if (!props.organisationStockLevels) {
		return props.stockLevels
	}
	const selected = props.organisationStockLevels.filter((organisation) => selectedSlugs.value.has(organisation.slug))
	if (!selected.length) {
		return []
	}
	return selected[0].levels.map((level, index) => ({
		...level,
		count: selected.reduce((sum, organisation) => sum + (organisation.levels[index]?.count ?? 0), 0),
		route: selected.length === 1 ? level.route : null,
	}))
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

const outOfStockRoute = computed(() => coverLevels.value.find((level) => level.bucket === "out")?.route)

const bucketLabel = (date) => {
	const parsed = new Date(`${date}T00:00:00`)
	if (props.stockOuts.unit === "month") {
		return parsed.toLocaleDateString(undefined, { month: "short", year: "2-digit" })
	}
	return parsed.toLocaleDateString(undefined, { day: "numeric", month: "short", ...(props.stockOuts.unit === "week" ? { year: "2-digit" } : {}) })
}

const formatMetric = (value) => {
	if (value === null || value === undefined) return "-"
	if (metric.value === "percentage") return `${value}%`
	if (metric.value === "lost_per_day") return money(value)
	return locale.number(value)
}

const stockOutChart = computed(() => {
	const series = props.stockOuts.series ?? []
	const pointRadius = series.length > 40 ? 0 : 2
	if (isComparing.value) {
		return {
			labels: series.map((row) => bucketLabel(row.date)),
			datasets: selectedOrganisations.value.map((organisation) => {
				const byDate = Object.fromEntries(organisation.series.map((row) => [row.date, row[metric.value]]))
				return {
					label: organisation.code,
					data: series.map((row) => byDate[row.date] ?? null),
					borderColor: organisation.color,
					backgroundColor: organisation.color,
					tension: 0,
					borderWidth: 1.5,
					pointRadius,
					spanGaps: true,
				}
			}),
		}
	}
	return {
		labels: series.map((row) => bucketLabel(row.date)),
		datasets: [
			{ label: ctrans("Out of stock"), data: series.map((row) => row.out_of_stock), borderColor: "#dc2626", backgroundColor: "#dc2626", tension: 0, borderWidth: 1.5, pointRadius, yAxisID: "y" },
			{ label: ctrans("Estimated lost revenue per day"), data: series.map((row) => row.lost_per_day), borderColor: "#f59e0b", backgroundColor: "#f59e0b", tension: 0, borderWidth: 1.5, pointRadius, yAxisID: "money" },
			{ label: ctrans("% out of stock"), data: series.map((row) => row.percentage), borderColor: "#7c3aed", backgroundColor: "#7c3aed", tension: 0, borderWidth: 1.5, borderDash: [4, 3], pointRadius, yAxisID: "percent" },
		],
	}
})

const tooltipTitle = (items) => (props.stockOuts.unit === "day" ? items[0].label : `${ctrans(props.stockOuts.unit === "week" ? "Week of" : "Month")} ${items[0].label}`)

const stockOutOptions = computed(() => {
	const x = { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } }
	if (isComparing.value) {
		return {
			responsive: true,
			maintainAspectRatio: false,
			interaction: { mode: "index", intersect: false },
			plugins: {
				legend: { display: false },
				tooltip: {
					itemSort: (a, b) => (b.raw ?? -Infinity) - (a.raw ?? -Infinity),
					callbacks: { title: tooltipTitle, label: (item) => `${item.dataset.label}: ${formatMetric(item.raw)}` },
				},
			},
			scales: {
				x,
				y: {
					beginAtZero: true,
					ticks: { precision: 0, callback: (value) => formatMetric(value) },
					title: { display: true, text: ctrans(metrics.find((option) => option.key === metric.value).label) },
				},
			},
		}
	}
	return {
		responsive: true,
		maintainAspectRatio: false,
		interaction: { mode: "index", intersect: false },
		plugins: {
			legend: { position: "bottom", labels: { boxWidth: 12 } },
			tooltip: {
				callbacks: {
					title: tooltipTitle,
					label: (item) =>
						item.dataset.yAxisID === "money"
							? `${item.dataset.label}: ${money(item.raw)}`
							: `${item.dataset.label}: ${locale.number(item.raw)} (${props.stockOuts.series[item.dataIndex].percentage}%)`,
				},
			},
		},
		scales: {
			x,
			y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: ctrans("SKOs out of stock") } },
			money: { beginAtZero: true, position: "right", grid: { display: false }, ticks: { callback: (value) => money(value) } },
			percent: { beginAtZero: true, position: "right", grid: { display: false }, ticks: { callback: (value) => `${value}%` } },
		},
	}
})

const visibleLevels = computed(() => coverLevels.value.filter((level) => level.count))

const coverTotal = computed(() => coverLevels.value.reduce((sum, level) => sum + level.count, 0))

const coverChart = computed(() => ({
	labels: coverLevels.value.map((level) => level.label),
	datasets: [{ data: coverLevels.value.map((level) => level.count), backgroundColor: coverLevels.value.map((level) => toneColor[level.tone] ?? "#9ca3af") }],
}))

const coverOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	cutout: "70%",
	hoverOffset: 6,
	onHover: (_event, activeElements, chart) => {
		chart.canvas.style.cursor = activeElements.length && coverLevels.value[activeElements[0].index]?.route ? "pointer" : "default"
	},
	onClick: (_event, activeElements) => {
		const level = coverLevels.value[activeElements[0]?.index]
		if (level?.route) router.visit(route(level.route.name, level.route.parameters))
	},
	plugins: { legend: { display: false } },
}))
</script>

<template>
	<div class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<TicketsCreatedInterval :options="stockOuts.periods" :selected="stockOuts.period" label="Stock outs" param="period" :storageKey="`${storageKey}-period`" />
			<div class="flex flex-wrap gap-3 text-sm tabular-nums">
				<component
					:is="outOfStockRoute ? Link : 'span'"
					v-tooltip="ctrans('SKOs out of stock') + (headline.now ? ' · ' + headline.now.date : '')"
					:href="outOfStockRoute ? route(outOfStockRoute.name, outOfStockRoute.parameters) : undefined"
					class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700 shadow-sm"
					:class="{ 'hover:bg-gray-50': outOfStockRoute }">
					<FontAwesomeIcon icon="fal fa-box-open" class="text-red-600" fixed-width aria-hidden="true" />
					{{ headline.now ? locale.number(headline.now.out_of_stock) : "-" }}
					<span class="font-normal text-gray-400">{{ ctrans("out of stock") }}</span>
				</component>
				<span v-tooltip="ctrans('Share of SKOs out of stock')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700 shadow-sm">
					<FontAwesomeIcon icon="fal fa-percentage" class="text-red-600" fixed-width aria-hidden="true" />
					{{ headline.now ? headline.now.percentage + "%" : "-" }}
				</span>
				<span
					v-tooltip="ctrans('Estimated lost revenue per day: what the SKOs out of stock sold per day on average over the 6 months before')"
					class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700 shadow-sm">
					<FontAwesomeIcon icon="fal fa-coins" class="text-amber-600" fixed-width aria-hidden="true" />
					{{ money(headline.now?.lost_per_day) }}
					<span class="font-normal text-gray-400">/ {{ ctrans("day") }}</span>
				</span>
				<span v-tooltip="ctrans('Estimated lost revenue in this period')" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 font-semibold text-gray-700 shadow-sm">
					<FontAwesomeIcon icon="fal fa-coins" class="text-amber-600" fixed-width aria-hidden="true" />
					{{ money(headline.lostTotal) }}
					<span class="font-normal text-gray-400">{{ ctrans("lost in period") }}</span>
				</span>
			</div>
		</div>
		<div v-if="isComparing" class="flex flex-wrap items-center gap-2 text-sm">
			<button
				v-for="organisation in rankedOrganisations"
				:key="organisation.slug"
				type="button"
				:aria-pressed="selectedSlugs.has(organisation.slug)"
				v-tooltip="organisation.name + ' · ' + ctrans('double click to show only this one')"
				class="inline-flex items-center gap-2 rounded-full border px-3 py-1 tabular-nums transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-[--app-accent]"
				:class="selectedSlugs.has(organisation.slug) ? 'border-gray-300 bg-white text-gray-800 shadow-sm' : 'border-dashed border-gray-300 bg-transparent text-gray-400'"
				@click="toggleOrganisation(organisation.slug)"
				@dblclick="selectOnly(organisation.slug)">
				<span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: selectedSlugs.has(organisation.slug) ? organisation.color : '#d1d5db' }" />
				<span class="font-semibold">{{ organisation.code }}</span>
				<span :class="selectedSlugs.has(organisation.slug) ? 'text-gray-500' : ''">{{ organisation.now ? organisation.now.percentage + "%" : "-" }}</span>
			</button>
			<button
				v-if="selectedSlugs.size < organisations.length"
				type="button"
				class="rounded-full px-2 py-1 text-xs text-[--app-accent-strong] hover:underline"
				@click="selectAll">
				{{ ctrans("Show all") }}
			</button>
		</div>
		<DashboardWidgetBox :storageKey="`${storageKey}-collapsed`">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-chart-line" class="text-red-600" fixed-width aria-hidden="true" />
					{{ ctrans("Out of stock history") }}
				</span>
			</template>
			<div class="grid gap-6 lg:grid-cols-5">
				<div class="flex flex-col gap-2 lg:col-span-3">
					<div v-if="isComparing" class="flex flex-wrap gap-1 text-xs">
						<button
							v-for="option in metrics"
							:key="option.key"
							type="button"
							:aria-pressed="metric === option.key"
							class="rounded-full px-3 py-1 transition"
							:class="metric === option.key ? 'bg-[--app-accent] text-[--app-accent-text] shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
							@click="metric = option.key">
							{{ ctrans(option.label) }}
						</button>
					</div>
					<div class="h-72">
					<Chart type="line" :data="stockOutChart" :options="stockOutOptions" class="h-full" />
					</div>
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
