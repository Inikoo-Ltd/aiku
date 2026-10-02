<script setup>
import { Link, router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { computed, inject, ref } from "vue"
import Chart from "primevue/chart"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxOpen, faPercentage, faCoins, faClipboardList, faTruckContainer, faExclamationTriangle, faBoxes, faChevronDown } from "@fal"

library.add(faBoxOpen, faPercentage, faCoins, faClipboardList, faTruckContainer, faExclamationTriangle, faBoxes, faChevronDown)

const props = defineProps({
	stockOuts: { type: Object, required: true },
	stockLevels: { type: Array, default: () => [] },
	organisationStockLevels: { type: Array, default: null },
	cards: { type: Array, default: () => [] },
	storageKey: { type: String, required: true },
	reloadProps: { type: Array, default: () => ["stockOuts", "stockLevels", "stockLevelsByOrganisation"] },
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

const isPeriodOpen = ref(false)

const cardTooltip = (card) => [card.description, ...(card.metrics ?? []).map((metric) => `${metric.label}: ${locale.number(metric.value)}`)].join(" · ")

const loadingSource = ref(null)

const sourceOptions = computed(() => {
	const sources = props.stockOuts.sources ?? {}
	return {
		all: { label: ctrans("All sources"), out_of_stock: Object.values(sources).reduce((sum, option) => sum + option.out_of_stock, 0) },
		...sources,
	}
})

const selectSource = (source) => {
	router.reload({
		data: { source: source ?? "" },
		only: props.reloadProps,
		preserveScroll: true,
		onStart: () => (loadingSource.value = source ?? "all"),
		onFinish: () => (loadingSource.value = null),
	})
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
		in_transit: selected.reduce((sum, organisation) => sum + (organisation.levels[index]?.in_transit ?? 0), 0),
		late: selected.reduce((sum, organisation) => sum + (organisation.levels[index]?.late ?? 0), 0),
		purchase_orders: selected.reduce((sum, organisation) => sum + (organisation.levels[index]?.purchase_orders ?? 0), 0),
		stock_deliveries: selected.reduce((sum, organisation) => sum + (organisation.levels[index]?.stock_deliveries ?? 0), 0),
		days_min: Math.min(...selected.map((organisation) => organisation.levels[index]?.days_min ?? Infinity)),
		days_max: Math.max(...selected.map((organisation) => organisation.levels[index]?.days_max ?? -Infinity)),
		arrivals: selected.reduce((arrivals, organisation) => {
			Object.entries(organisation.levels[index]?.arrivals ?? {}).forEach(([month, count]) => (arrivals[month] = (arrivals[month] ?? 0) + count))
			return arrivals
		}, {}),
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
			legend: { position: "bottom", labels: { boxWidth: 8, boxHeight: 8, padding: 8, font: { size: 10 }, color: "#9ca3af" } },
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

const share = (count) => (coverTotal.value ? `${((count / coverTotal.value) * 100).toFixed(1)}%` : "0%")

const isOrderDue = (level) => ["out", "w1", "w2"].includes(level.bucket)

const coverSummary = computed(() => {
	const urgent = coverLevels.value.filter(isOrderDue)
	return {
		urgent: urgent.reduce((sum, level) => sum + level.count, 0),
		inTransit: urgent.reduce((sum, level) => sum + (level.in_transit ?? 0), 0),
		late: urgent.reduce((sum, level) => sum + (level.late ?? 0), 0),
		toOrderNow: urgent.reduce((sum, level) => sum + notOrdered(level), 0),
	}
})

const urgentParts = computed(() => [
	{ key: "order", value: coverSummary.value.toOrderNow, label: ctrans("to order now"), bar: "bg-amber-400", text: "text-amber-700", tooltip: ctrans("Nothing ordered") },
	{ key: "late", value: coverSummary.value.late, label: ctrans("late"), bar: "bg-red-400", text: "text-red-700", tooltip: ctrans("Waiting only on purchase orders or deliveries past their expected arrival") },
	{ key: "way", value: coverSummary.value.inTransit, label: ctrans("on the way"), bar: "bg-blue-400", text: "text-blue-700", tooltip: ctrans("A purchase order or delivery on schedule") },
])

const historyDateLabel = computed(() => {
	const date = headline.value.now?.date
	if (!date) return ""
	const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10)
	return date === yesterday ? ctrans("yesterday") : new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { day: "numeric", month: "short" })
})

const notOrdered = (level) => level.count - (level.in_transit ?? 0) - (level.late ?? 0)

const waitingDays = (level) => {
	if (!Number.isFinite(level.days_min) || !Number.isFinite(level.days_max)) return ""
	return level.days_min === level.days_max ? `${level.days_min}d` : `${level.days_min}–${level.days_max}d`
}

const monthLabel = (month) =>
	month === "unknown" ? ctrans("No date") : new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, { month: "short", year: "2-digit" })

const arrivalsTooltip = (arrivals) =>
	Object.entries(arrivals ?? {})
		.sort(([a], [b]) => a.localeCompare(b))
		.map(([month, count]) => `${monthLabel(month)}: ${count}`)
		.join(" · ")
</script>

<template>
	<div class="space-y-3 rounded-xl border border-gray-200 bg-gray-50 p-3">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<div class="flex flex-wrap items-center gap-2 text-sm">
				<button
					v-for="(sourceOption, source) in sourceOptions"
					:key="source"
					type="button"
					:aria-pressed="(stockOuts.source ?? 'all') === source"
					:disabled="loadingSource !== null"
					class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-[--app-accent]"
					:class="(stockOuts.source ?? 'all') === source ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text] shadow-sm' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
					@click="selectSource(source === 'all' ? null : source)">
					<LoadingIcon v-if="loadingSource === source" />
					{{ sourceOption.label }}
					<span v-tooltip="ctrans('SKOs out of stock') + (historyDateLabel ? ' · ' + historyDateLabel : '')" class="tabular-nums" :class="(stockOuts.source ?? 'all') === source ? 'opacity-80' : 'text-gray-400'">{{ locale.number(sourceOption.out_of_stock) }}</span>
				</button>
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
		<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
			<div class="grid gap-6 lg:grid-cols-5">
				<div class="flex flex-col gap-2 lg:col-span-3">
					<div class="flex flex-wrap items-center gap-2">
						<div class="flex flex-wrap gap-2 text-xs tabular-nums">
							<component
								:is="outOfStockRoute ? Link : 'span'"
								v-tooltip="ctrans('SKOs out of stock') + (headline.now ? ' · ' + headline.now.date : '')"
								:href="outOfStockRoute ? route(outOfStockRoute.name, outOfStockRoute.parameters) : undefined"
								class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 font-semibold text-gray-700"
								:class="{ 'hover:bg-gray-50': outOfStockRoute }">
								<FontAwesomeIcon icon="fal fa-box-open" class="text-red-600" fixed-width aria-hidden="true" />
								{{ headline.now ? locale.number(headline.now.out_of_stock) : "-" }}
								<span class="font-normal text-gray-400">{{ ctrans("out of stock") }}<template v-if="historyDateLabel"> · {{ historyDateLabel }}</template></span>
							</component>
							<span v-tooltip="ctrans('Share of SKOs out of stock')" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 font-semibold text-gray-700">
								<FontAwesomeIcon icon="fal fa-percentage" class="text-red-600" fixed-width aria-hidden="true" />
								{{ headline.now ? headline.now.percentage + "%" : "-" }}
							</span>
							<span
								v-tooltip="ctrans('Estimated lost revenue per day: what the SKOs out of stock sold per day on average over the 6 months before')"
								class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 font-semibold text-gray-700">
								<FontAwesomeIcon icon="fal fa-coins" class="text-amber-600" fixed-width aria-hidden="true" />
								{{ money(headline.now?.lost_per_day) }}
								<span class="font-normal text-gray-400">/ {{ ctrans("day") }}</span>
							</span>
							<span v-tooltip="ctrans('Estimated lost revenue in this period')" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2 py-1 font-semibold text-gray-700">
								<FontAwesomeIcon icon="fal fa-coins" class="text-amber-600" fixed-width aria-hidden="true" />
								{{ money(headline.lostTotal) }}
								<span class="font-normal text-gray-400">{{ ctrans("lost in period") }}</span>
							</span>
						</div>
						<div class="relative ml-auto">
							<button type="button" class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-50" :aria-expanded="isPeriodOpen" @click="isPeriodOpen = !isPeriodOpen">
								{{ stockOuts.periods[stockOuts.period] }}
								<FontAwesomeIcon icon="fal fa-chevron-down" class="text-[10px] text-gray-400" aria-hidden="true" />
							</button>
							<TicketsCreatedInterval
								v-show="isPeriodOpen"
								:options="stockOuts.periods"
								:selected="stockOuts.period"
								label=""
								param="period"
								:storageKey="`${storageKey}-period`"
								:only="reloadProps"
								compact
								class="absolute right-0 top-full z-20 mt-1 w-max shadow-md"
								@click="isPeriodOpen = false" />
						</div>
					</div>
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
					<div class="h-80">
					<Chart type="line" :data="stockOutChart" :options="stockOutOptions" class="h-full" />
					</div>
				</div>
				<div v-if="visibleLevels.length" class="min-w-0 lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-5">
					<div class="mb-3">
						<div v-if="coverSummary.urgent" class="flex items-center gap-x-2.5 whitespace-nowrap text-xs tabular-nums text-gray-500">
							<span v-tooltip="ctrans('Out of stock, Doomed and Critical SKOs, live')"><span class="font-semibold text-gray-700">{{ locale.number(coverSummary.urgent) }}</span> {{ ctrans("urgent") }}</span>
							<span class="flex h-1.5 w-20 shrink-0 overflow-hidden rounded-full bg-gray-100">
								<span v-for="part in urgentParts" :key="part.key" :class="part.bar" :style="{ width: (part.value / coverSummary.urgent) * 100 + '%' }" />
							</span>
							<span v-for="part in urgentParts" :key="part.key" v-tooltip="part.tooltip" class="inline-flex items-center gap-1">
								<span class="h-2 w-2 rounded-full" :class="part.bar" />
								<span class="font-medium" :class="part.text">{{ locale.number(part.value) }}</span>
								{{ part.label }}
							</span>
						</div>
					</div>
					<div>
						<table class="w-full min-w-0 text-[13px] tabular-nums">
							<thead>
								<tr class="border-b border-gray-200 text-[11px] text-gray-400">
									<th />
									<th class="pb-1 pr-2 text-right font-normal">{{ ctrans("SKOs") }}</th>
									<th class="border-l border-gray-200 pb-1 pl-3 pr-2 text-right font-normal">{{ ctrans("On the way") }}</th>
									<th class="border-l border-gray-200 pb-1 pl-3 pr-1.5 text-right font-normal">{{ ctrans("Not ordered") }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="level in visibleLevels" :key="level.bucket" class="group border-b border-gray-100 last:border-b-0">
									<td class="whitespace-nowrap rounded-l-md py-1 pl-1.5 pr-2 leading-snug" :class="{ 'group-hover:bg-[--app-accent-soft]': level.route }">
										<component
											:is="level.route ? Link : 'span'"
											v-tooltip="level.description"
											:href="level.route ? route(level.route.name, level.route.parameters) : undefined"
											class="flex items-center gap-1.5"
											:class="{ 'group-hover:text-[--app-accent-strong]': level.route }">
											<span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: toneColor[level.tone] ?? '#9ca3af' }" />
											{{ level.label }}
										</component>
									</td>
									<td class="whitespace-nowrap py-1 pr-2 text-right" :class="{ 'group-hover:bg-[--app-accent-soft]': level.route }">
										<span class="font-medium">{{ locale.number(level.count) }}</span>
										<span class="ml-1.5 inline-block w-11 text-xs text-gray-400">{{ share(level.count) }}</span>
									</td>
									<td class="whitespace-nowrap border-l border-gray-200 py-1 pl-3 pr-2 text-right text-[12px] tabular-nums" :class="{ 'group-hover:bg-[--app-accent-soft]': level.route }">
										<span v-if="level.in_transit || level.late" class="inline-flex items-center justify-end">
											<span v-tooltip="ctrans('SKOs waiting only on purchase orders or deliveries past their expected arrival')" class="inline-flex w-10 items-center justify-end gap-0.5 text-red-600">
												<template v-if="level.late"><FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-[10px]" aria-hidden="true" />{{ locale.number(level.late) }}</template>
											</span>
											<span v-tooltip="ctrans('Purchase orders')" class="inline-flex w-10 items-center justify-end gap-0.5 text-gray-400">
												<template v-if="level.purchase_orders"><FontAwesomeIcon icon="fal fa-clipboard-list" class="text-[10px]" aria-hidden="true" />{{ level.purchase_orders }}</template>
											</span>
											<span v-tooltip="ctrans('Stock deliveries')" class="inline-flex w-10 items-center justify-end gap-0.5 text-gray-400">
												<template v-if="level.stock_deliveries"><FontAwesomeIcon icon="fal fa-truck-container" class="text-[10px]" aria-hidden="true" />{{ level.stock_deliveries }}</template>
											</span>
											<span v-tooltip="arrivalsTooltip(level.arrivals)" class="inline-block w-16 text-gray-400">{{ waitingDays(level) }}</span>
											<span class="inline-block w-10 text-[13px] font-medium text-blue-700">{{ level.in_transit ? locale.number(level.in_transit) : "" }}</span>
										</span>
									</td>
									<td
										class="whitespace-nowrap rounded-r-md border-l border-gray-200 py-1 pl-3 pr-1.5 text-right"
										:class="[{ 'group-hover:bg-[--app-accent-soft]': level.route }, isOrderDue(level) ? 'text-amber-700' : 'text-gray-400']">
										<span :class="{ 'font-medium': isOrderDue(level) }">{{ locale.number(notOrdered(level)) }}</span>
										<span class="ml-1.5 inline-block w-11 text-xs opacity-70">{{ share(notOrdered(level)) }}</span>
									</td>
								</tr>
							</tbody>
						</table>
						<div v-if="cards.length" class="mt-3 flex flex-wrap gap-x-3 gap-y-1 border-t border-gray-100 pt-2 text-xs text-gray-400">
							<component
								:is="card.route ? Link : 'span'"
								v-for="card in cards"
								:key="card.label"
								v-tooltip="cardTooltip(card)"
								:href="card.route ? route(card.route.name, card.route.parameters) : undefined"
								class="tabular-nums"
								:class="{ 'hover:text-[--app-accent-strong] hover:underline': card.route }">
								{{ card.label }} <span class="font-medium text-gray-600">{{ locale.number(card.value ?? 0) }}</span>
							</component>
						</div>
					</div>
				</div>
			</div>
		</div>
		<p class="px-1 text-xs text-gray-400">{{ ctrans("Only SKOs of products on sale · new SKOs not yet received are left out") }}</p>
	</div>
</template>
