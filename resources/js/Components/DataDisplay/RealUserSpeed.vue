<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 23 Sep 2026 23:40:00 Central European Summer Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Chart from "primevue/chart"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDesktop, faMobile, faGlobe, faImage, faHandPointer, faArrowsAlt, faPaintBrush, faServer, faSmile, faMeh, faFrown, faTachometerAltFast, faPencilRuler } from "@fal"

type FormFactor = "desktop" | "phone" | "all"
type Metric = "lcp" | "inp" | "cls" | "fcp" | "ttfb"
type Rating = "good" | "needs_improvement" | "poor" | null
type Source = "crux" | "visitors"
type Period = { period_start: string; period_end: string; samples?: number; histograms: Record<string, number[]> } & Record<Metric, number | null>
type SourceReport = {
	scope: "page" | "website" | null
	url: string | null
	period?: "day" | "week"
	history: Partial<Record<FormFactor, Period[]>>
}

const props = defineProps<{
	report?: Partial<Record<Source, SourceReport>> | null
	embedded?: boolean
}>()

const ratingColor = { good: "#0CCE6B", needs_improvement: "#FFA400", poor: "#FF4E42" }
const ratingTextColor = { good: "#0A7B41", needs_improvement: "#8F5700", poor: "#B3261E" }

const metrics: Array<{ key: Metric; label: string; short: string; good: number; poor: number; color: string; icon: typeof faImage }> = [
	{ key: "lcp", label: ctrans("Largest Contentful Paint"), short: "LCP", good: 2500, poor: 4000, color: "#2563eb", icon: faImage },
	{ key: "inp", label: ctrans("Interaction to Next Paint"), short: "INP", good: 200, poor: 500, color: "#9333ea", icon: faHandPointer },
	{ key: "cls", label: ctrans("Cumulative Layout Shift"), short: "CLS", good: 0.1, poor: 0.25, color: "#db2777", icon: faArrowsAlt },
	{ key: "fcp", label: ctrans("First Contentful Paint"), short: "FCP", good: 1800, poor: 3000, color: "#0891b2", icon: faPaintBrush },
	{ key: "ttfb", label: ctrans("Time to First Byte"), short: "TTFB", good: 800, poor: 1800, color: "#64748b", icon: faServer },
]

const metricGroups = [
	{ key: "speed", label: ctrans("Speed"), icon: faTachometerAltFast, metrics: ["ttfb", "fcp", "lcp", "inp"].map((key) => metrics.find((metric) => metric.key === key)!) },
	{ key: "design", label: ctrans("Design"), icon: faPencilRuler, metrics: metrics.filter((metric) => metric.key === "cls") },
]

const formFactors: Array<{ key: FormFactor; label: string; icon: typeof faDesktop }> = [
	{ key: "desktop", label: ctrans("Desktop"), icon: faDesktop },
	{ key: "phone", label: ctrans("Mobile"), icon: faMobile },
	{ key: "all", label: ctrans("All devices"), icon: faGlobe },
]

const sources: Array<{ key: Source; label: string }> = [
	{ key: "crux", label: ctrans("Google") },
	{ key: "visitors", label: ctrans("Our visitors") },
]

const source = ref<Source>("crux")

watch(
	() => props.report,
	(report) => {
		if (report && !report[source.value]?.scope) {
			source.value = sources.find((option) => report[option.key]?.scope === "page")?.key
				?? sources.find((option) => report[option.key]?.scope)?.key
				?? source.value
		}
	},
	{ immediate: true }
)

const current = computed(() => props.report?.[source.value] ?? null)
const availableFormFactors = computed(() => formFactors.filter((option) => (current.value?.history?.[option.key] ?? []).length > 0))
const formFactor = ref<FormFactor>("desktop")
const sourceOptions = sources.map((option) => ({ label: option.label, value: option.key }))
const formFactorOptions = computed(() => availableFormFactors.value.map((option) => ({ label: option.label, value: option.key, icon: option.icon })))

watch(
	availableFormFactors,
	(available) => {
		if (available.length && !available.some((option) => option.key === formFactor.value)) {
			formFactor.value = available[0].key
		}
	},
	{ immediate: true }
)

const isLoading = computed(() => props.report === undefined)
const periods = computed(() => current.value?.history?.[formFactor.value] ?? [])
const latest = computed(() => periods.value[periods.value.length - 1] ?? null)
const metricOf = (key: Metric) => metrics.find((metric) => metric.key === key)!

const ratingOf = (key: Metric, value: number | null): Rating => {
	if (value === null || value === undefined) {
		return null
	}

	const metric = metricOf(key)

	return value <= metric.good ? "good" : value <= metric.poor ? "needs_improvement" : "poor"
}

const display = (key: Metric, value: number | null) => {
	if (value === null || value === undefined) {
		return ctrans("n/a")
	}

	if (key === "cls") {
		return value.toFixed(2)
	}

	return value >= 1000 ? `${(value / 1000).toFixed(1)} s` : `${value} ms`
}

/**
 * Every metric has its own unit, so each is placed on a shared scale by its own limits: 0 to 1 is
 * good, 1 to 2 needs improvement, 2 to 3 is poor, and the tooltip shows the real value.
 */
const bandPosition = (key: Metric, value: number | null) => {
	if (value === null || value === undefined) {
		return null
	}

	const metric = metricOf(key)

	if (value <= metric.good) {
		return value / metric.good
	}

	if (value <= metric.poor) {
		return 1 + (value - metric.good) / (metric.poor - metric.good)
	}

	return Math.min(3, 2 + (value - metric.poor) / metric.poor)
}

const hiddenMetrics = ref<Metric[]>([])

const toggleMetric = (key: Metric) => {
	hiddenMetrics.value = hiddenMetrics.value.includes(key) ? hiddenMetrics.value.filter((hidden) => hidden !== key) : [...hiddenMetrics.value, key]
}

const chartData = computed(() => ({
	labels: periods.value.map((period) => period.period_end),
	datasets: metrics.map((metric) => {
		const values = periods.value.map((period) => period[metric.key])

		return {
			label: metric.short,
			metric: metric.key,
			values,
			data: values.map((value) => bandPosition(metric.key, value)),
			borderColor: metric.color,
			backgroundColor: metric.color,
			borderWidth: 1.5,
			tension: 0,
			pointRadius: periods.value.length > 40 ? 0 : 2.5,
			pointHoverRadius: 4,
			hidden: hiddenMetrics.value.includes(metric.key),
			spanGaps: true,
			clip: false,
		}
	}),
}))

const ratingBands = {
	id: "ratingBands",
	beforeDatasetsDraw(chart: any) {
		const { ctx, chartArea, scales } = chart
		const bands: Array<[number, number, string, string, typeof faSmile]> = [
			[0, 1, "rgba(12, 206, 107, 0.08)", ratingTextColor.good, faSmile],
			[1, 2, "rgba(255, 164, 0, 0.08)", ratingTextColor.needs_improvement, faMeh],
			[2, 3, "rgba(255, 78, 66, 0.08)", ratingTextColor.poor, faFrown],
		]
		const faceSize = 18

		ctx.save()
		for (const [from, to, color, faceColor, face] of bands) {
			const top = scales.y.getPixelForValue(to)
			const bottom = scales.y.getPixelForValue(from)
			ctx.fillStyle = color
			ctx.fillRect(chartArea.left, top, chartArea.right - chartArea.left, bottom - top)

			const [width, height, , , path] = face.icon
			const scale = faceSize / Math.max(width, height)
			ctx.save()
			ctx.translate(chartArea.left - faceSize - 8, (top + bottom) / 2 - (height * scale) / 2)
			ctx.scale(scale, scale)
			ctx.fillStyle = faceColor
			ctx.fill(new Path2D(Array.isArray(path) ? path.join(" ") : path))
			ctx.restore()
		}
		ctx.restore()
	},
}

const chartOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	layout: { padding: { left: 30, top: 8, right: 8 } },
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
				title: (items: any[]) => {
					const period = periods.value[items[0].dataIndex]

					const dates = period.period_start === period.period_end
						? useFormatTime(period.period_end, { formatTime: "PP" })
						: `${useFormatTime(period.period_start, { formatTime: "PP" })} – ${useFormatTime(period.period_end, { formatTime: "PP" })}`

					return period.samples ? `${dates} · ${ctrans(":count page loads", { count: period.samples })}` : dates
				},
				label: (item: any) => `${item.dataset.label}: ${display(item.dataset.metric, item.dataset.values[item.dataIndex])}`,
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 10, maxRotation: 0, color: "#4b5563", callback: (value: number) => useFormatTime(periods.value[value]?.period_end, { formatTime: "d MMM" }) } },
		y: {
			min: 0,
			max: 3,
			grid: { display: false },
			ticks: { display: false },
		},
	},
}))
</script>

<template>
	<div :class="embedded ? '' : 'rounded-lg bg-white shadow'" data-real-user-speed>
		<div class="flex flex-wrap items-center gap-3 border-b px-6 py-3">
			<span class="text-sm font-semibold">{{ ctrans("Real user speed") }}</span>

			<SegmentedToggle v-model="source" :options="sourceOptions" :aria-label="ctrans('Data source')" />

			<SegmentedToggle v-if="availableFormFactors.length > 1" v-model="formFactor" :options="formFactorOptions" :aria-label="ctrans('Device')" />

			<span v-if="latest && source === 'crux'" class="text-xs text-gray-600">
				{{ ctrans("Chrome visits :from – :to", { from: useFormatTime(latest.period_start, { formatTime: "PP" }), to: useFormatTime(latest.period_end, { formatTime: "PP" }) }) }}
			</span>
		</div>

		<div v-if="isLoading" class="space-y-3 px-6 py-6">
			<div class="h-4 w-56 animate-pulse rounded bg-gray-200" />
			<div class="h-64 w-full animate-pulse rounded bg-gray-100" />
		</div>

		<div v-else-if="!current?.scope" class="px-6 py-6 text-sm text-gray-600">
			{{
				source === "crux"
					? ctrans("Google does not have enough visits from Chrome users here to report it. It needs enough visits over 28 days. See Our visitors.")
					: ctrans("Not enough measured page loads yet. A week is shown once :count page loads have been measured.", { count: 5 })
			}}
		</div>

		<div v-else class="space-y-4 px-6 py-6">
			<div class="flex flex-wrap gap-3">
				<div v-for="group in metricGroups" :key="group.key" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 p-2" :data-metric-group="group.key">
					<span v-tooltip="group.label" class="px-1 text-gray-400">
						<FontAwesomeIcon :icon="group.icon" fixed-width aria-hidden="true" />
						<span class="sr-only">{{ group.label }}</span>
					</span>
					<div class="flex flex-wrap gap-2">
						<span
							v-for="metric in group.metrics"
							:key="metric.key"
							v-tooltip="`${metric.short}: ${metric.label}`"
							class="inline-flex items-center gap-1.5 rounded-lg border border-b-2 border-gray-200 bg-white px-3 py-2 text-sm tabular-nums shadow-sm"
							:style="{ borderBottomColor: metric.color }">
							<FontAwesomeIcon :icon="metric.icon" :style="{ color: metric.color }" fixed-width aria-hidden="true" />
							<span class="font-semibold text-gray-700">{{ display(metric.key, latest?.[metric.key] ?? null) }}</span>
							<span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: ratingColor[ratingOf(metric.key, latest?.[metric.key] ?? null) ?? 'needs_improvement'] }" aria-hidden="true" />
							<span class="sr-only">{{ metric.label }}</span>
						</span>
					</div>
				</div>
			</div>

			<div class="h-80 w-full">
				<Chart type="line" class="h-full" :data="chartData" :options="chartOptions" :plugins="[ratingBands]" />
			</div>

			<div class="flex flex-wrap justify-center gap-x-4 gap-y-1 text-sm text-gray-600" data-speed-legend>
				<button
					v-for="metric in metrics"
					:key="metric.key"
					type="button"
					class="inline-flex items-center gap-1.5"
					:class="{ 'opacity-40': hiddenMetrics.includes(metric.key) }"
					v-tooltip="metric.label"
					@click="toggleMetric(metric.key)">
					<FontAwesomeIcon :icon="metric.icon" :style="{ color: metric.color }" fixed-width aria-hidden="true" />
					<span :class="{ 'line-through': hiddenMetrics.includes(metric.key) }">{{ metric.short }}</span>
				</button>
			</div>

			<div class="space-y-1 text-xs text-gray-600">
				<template v-if="source === 'crux'">
					<div>{{ ctrans("75th percentile of real Chrome visits over 28 days, from the Chrome UX Report. Google adds a new point every week.") }}</div>
				</template>
				<template v-else>
					<div v-if="current.period === 'week'">{{ ctrans("75th percentile per week of every page load of this page measured in our visitors' browsers, any browser. Weekly because this page has too few loads a day for a daily figure.") }}</div>
					<div v-else>{{ ctrans("75th percentile per day of every page load measured in our visitors' browsers, any browser. Updated as visitors browse.") }}</div>
				</template>
			</div>
		</div>
	</div>
</template>
