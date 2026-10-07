<script setup lang="ts">
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBullseye, faDoorOpen, faStopwatch, faEye } from "@fal"

type MetricKey = "page_views" | "conversion_rate" | "bounce_rate" | "avg_time_on_page"

type HistoryRecord = {
	date: string
	page_views: number
	visitors: number
	bounces: number
	add_to_baskets: number
	conversion_rate: number | null
	bounce_rate: number | null
	avg_time_on_page: number | null
}

const props = defineProps<{
	engagement?: {
		days?: number
		page_views?: number
		visitors?: number
		add_to_baskets?: number
		conversion_rate?: number
		bounces?: number
		bounce_rate?: number
		avg_time_on_page?: number
		timed_page_views?: number
		history?: HistoryRecord[]
	} | null
}>()

const isLoading = computed(() => props.engagement === undefined)
const history = computed<HistoryRecord[]>(() => props.engagement?.history ?? [])
const hasViews = computed(() => (props.engagement?.page_views ?? 0) > 0)

const duration = (seconds: number) => (seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`)

const percent = (value?: number | null) => `${(value ?? 0).toFixed(2)}%`

// Each metric keeps its own colour wherever it appears. The four were run through the palette
// checks together, so no two of them are told apart by colour alone under colour blindness.
const metrics = computed<
	Array<{ key: MetricKey; label: string; icon: typeof faEye; color: string; value: string; hint: string; format: (value: number) => string }>
>(() => [
	{
		key: "page_views",
		label: ctrans("Page views"),
		icon: faEye,
		color: "#ca8a04",
		value: String(props.engagement?.page_views ?? 0),
		hint: ctrans(":count visitors", { count: props.engagement?.visitors ?? 0 }),
		format: (value: number) => String(value),
	},
	{
		key: "conversion_rate",
		label: ctrans("Conversion rate"),
		icon: faBullseye,
		color: "#059669",
		value: percent(props.engagement?.conversion_rate),
		hint: ctrans(":count added to basket", { count: props.engagement?.add_to_baskets ?? 0 }),
		format: (value: number) => `${value}%`,
	},
	{
		key: "bounce_rate",
		label: ctrans("Bounce rate"),
		icon: faDoorOpen,
		color: "#e11d48",
		value: percent(props.engagement?.bounce_rate),
		hint: ctrans(":count sessions saw this page and nothing else", { count: props.engagement?.bounces ?? 0 }),
		format: (value: number) => `${value}%`,
	},
	{
		key: "avg_time_on_page",
		label: ctrans("Avg. time on page"),
		icon: faStopwatch,
		color: "#0284c7",
		value: duration(props.engagement?.avg_time_on_page ?? 0),
		hint: ctrans("Measured on :timed of :views views", {
			timed: props.engagement?.timed_page_views ?? 0,
			views: props.engagement?.page_views ?? 0,
		}),
		format: (value: number) => duration(value),
	},
])

const selected = ref<MetricKey>("conversion_rate")

const selectedMetric = computed(() => metrics.value.find((metric) => metric.key === selected.value) ?? metrics.value[0])

// One metric at a time: a rate, a count and a number of seconds do not share a scale, and putting
// them on one grid would mean a second axis, which makes every crossing of the two lines a
// coincidence of the scales rather than anything about the page.
const chartData = computed(() => ({
	labels: history.value.map((record) => record.date),
	datasets: [
		{
			label: selectedMetric.value.label,
			data: history.value.map((record) => record[selected.value]),
			borderColor: selectedMetric.value.color,
			backgroundColor: selectedMetric.value.color,
			borderWidth: 2,
			pointRadius: 4,
			pointHoverRadius: 6,
			tension: 0.25,
			spanGaps: true,
		},
	],
}))

const chartOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { display: false },
		tooltip: {
			backgroundColor: "#fff",
			titleColor: "#111827",
			bodyColor: "#374151",
			borderColor: "#d1d5db",
			borderWidth: 1,
			padding: 10,
			callbacks: {
				title: (items: any[]) => useFormatTime(items[0].label, { formatTime: "PPP" }),
				label: (item: any) => {
					const record = history.value[item.dataIndex]

					if (item.raw === null) {
						return ctrans("No views")
					}

					return `${selectedMetric.value.format(item.raw)} · ${ctrans(":count views", { count: record?.page_views ?? 0 })}`
				},
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 6, color: "#4b5563" } },
		y: { beginAtZero: true, grid: { color: "#f3f4f6" }, ticks: { color: "#4b5563" } },
	},
}))
</script>

<template>
	<div class="rounded-lg border border-gray-200 bg-white shadow-sm">
		<div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 px-6 py-3">
			<span class="text-sm font-semibold">{{ ctrans("Ads performance") }}</span>
			<span class="text-xs text-gray-500">{{ ctrans("Last :days days", { days: engagement?.days ?? 30 }) }}</span>
		</div>

		<div v-if="isLoading" class="space-y-3 px-6 py-6">
			<div class="h-4 w-40 animate-pulse rounded bg-gray-200" />
			<div class="h-40 w-full animate-pulse rounded bg-gray-100" />
		</div>

		<template v-else>
			<div class="grid grid-cols-2 gap-px bg-gray-200">
				<button
					v-for="metric in metrics"
					:key="metric.key"
					type="button"
					:aria-pressed="selected === metric.key"
					class="p-4 text-left transition"
					:class="selected === metric.key ? 'bg-gray-50' : 'bg-white hover:bg-gray-50'"
					@click="selected = metric.key">
					<div class="flex items-center gap-2 text-xs text-gray-500">
						<FontAwesomeIcon :icon="metric.icon" :style="{ color: metric.color }" fixed-width aria-hidden="true" />
						{{ metric.label }}
					</div>
					<div class="mt-1 text-2xl font-semibold text-gray-800">{{ metric.value }}</div>
					<div class="mt-1 text-[11px] leading-tight text-gray-500">{{ metric.hint }}</div>
				</button>
			</div>

			<div v-if="hasViews" class="px-4 py-4">
				<div class="mb-2 px-2 text-xs text-gray-600">
					{{ ctrans(":metric per day", { metric: selectedMetric.label }) }}
				</div>
				<div class="h-44">
					<Chart type="line" :data="chartData" :options="chartOptions" class="h-full w-full" />
				</div>
			</div>

			<div v-else class="border-t border-gray-200 px-6 py-3 text-xs text-gray-500">
				{{ ctrans("No visitors recorded yet, so nothing can be read from these numbers") }}
			</div>
		</template>
	</div>
</template>
