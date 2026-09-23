<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 23 Sep 2026 23:40:00 Central European Summer Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDesktop, faMobile, faGlobe, faImage, faHandPointer, faArrowsAlt, faPaintBrush, faServer, faSmile, faMeh, faFrown } from "@fal"

type FormFactor = "desktop" | "phone" | "all"
type Metric = "lcp" | "inp" | "cls" | "fcp" | "ttfb"
type Rating = "good" | "needs_improvement" | "poor" | null
type Period = { period_start: string; period_end: string; histograms: Record<string, number[]> } & Record<Metric, number | null>

const props = defineProps<{
	report?: {
		scope: "page" | "website" | null
		url: string | null
		history: Partial<Record<FormFactor, Period[]>>
	} | null
	embedded?: boolean
}>()

const ratingColor = { good: "#0CCE6B", needs_improvement: "#FFA400", poor: "#FF4E42" }
const ratingTextColor = { good: "#0A7B41", needs_improvement: "#8F5700", poor: "#B3261E" }

const metrics: Array<{ key: Metric; label: string; short: string; good: number; poor: number; pointStyle: string; icon: typeof faImage }> = [
	{ key: "lcp", label: ctrans("Largest Contentful Paint"), short: "LCP", good: 2500, poor: 4000, pointStyle: "circle", icon: faImage },
	{ key: "inp", label: ctrans("Interaction to Next Paint"), short: "INP", good: 200, poor: 500, pointStyle: "rect", icon: faHandPointer },
	{ key: "cls", label: ctrans("Cumulative Layout Shift"), short: "CLS", good: 0.1, poor: 0.25, pointStyle: "triangle", icon: faArrowsAlt },
	{ key: "fcp", label: ctrans("First Contentful Paint"), short: "FCP", good: 1800, poor: 3000, pointStyle: "rectRot", icon: faPaintBrush },
	{ key: "ttfb", label: ctrans("Time to First Byte"), short: "TTFB", good: 800, poor: 1800, pointStyle: "star", icon: faServer },
]

const formFactors: Array<{ key: FormFactor; label: string; icon: typeof faDesktop }> = [
	{ key: "desktop", label: ctrans("Desktop"), icon: faDesktop },
	{ key: "phone", label: ctrans("Mobile"), icon: faMobile },
	{ key: "all", label: ctrans("All devices"), icon: faGlobe },
]

const availableFormFactors = computed(() => formFactors.filter((option) => (props.report?.history?.[option.key] ?? []).length > 0))
const formFactor = ref<FormFactor>("desktop")

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
const periods = computed(() => props.report?.history?.[formFactor.value] ?? [])
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

const chartData = computed(() => ({
	labels: periods.value.map((period) => period.period_end),
	datasets: metrics.map((metric) => {
		const values = periods.value.map((period) => period[metric.key])
		const markerColors = values.map((value) => ratingColor[ratingOf(metric.key, value) ?? "needs_improvement"])

		return {
			label: metric.short,
			metric: metric.key,
			values,
			data: values.map((value) => bandPosition(metric.key, value)),
			borderColor: "#374151",
			backgroundColor: "#fff",
			borderWidth: 1.5,
			pointStyle: metric.pointStyle,
			pointRadius: 4,
			pointBorderWidth: 1.5,
			pointBackgroundColor: "#fff",
			pointBorderColor: markerColors,
			spanGaps: true,
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
	layout: { padding: { left: 30 } },
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: {
			position: "bottom",
			labels: { usePointStyle: true, boxWidth: 8, boxHeight: 8, color: "#4b5563", generateLabels: (chart: any) =>
				chart.data.datasets.map((dataset: any, index: number) => ({
					text: dataset.label,
					pointStyle: dataset.pointStyle,
					strokeStyle: "#374151",
					fillStyle: "#fff",
					fontColor: "#4b5563",
					hidden: !chart.isDatasetVisible(index),
					datasetIndex: index,
				})),
			},
		},
		tooltip: {
			backgroundColor: "#fff",
			titleColor: "#111827",
			bodyColor: "#374151",
			borderColor: "#d1d5db",
			borderWidth: 1,
			padding: 10,
			usePointStyle: true,
			callbacks: {
				title: (items: any[]) => {
					const period = periods.value[items[0].dataIndex]

					return `${useFormatTime(period.period_start, { formatTime: "PP" })} – ${useFormatTime(period.period_end, { formatTime: "PP" })}`
				},
				label: (item: any) => `${item.dataset.label}: ${display(item.dataset.metric, item.dataset.values[item.dataIndex])}`,
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 10, color: "#4b5563" } },
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
	<div :class="embedded ? '' : 'rounded-lg bg-white shadow'" data-crux-history>
		<div class="flex flex-wrap items-center gap-3 border-b px-6 py-3">
			<span class="text-sm font-semibold">{{ ctrans("Real user speed") }}</span>

			<div v-if="availableFormFactors.length > 1" class="flex rounded-md bg-gray-100 p-0.5">
				<button
					v-for="option in availableFormFactors"
					:key="option.key"
					type="button"
					:aria-pressed="formFactor === option.key"
					class="flex items-center gap-1.5 rounded px-2.5 py-1 text-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600"
					:class="formFactor === option.key ? 'bg-white font-semibold text-gray-800 shadow-sm' : 'text-gray-600 hover:text-gray-800'"
					@click="formFactor = option.key">
					<FontAwesomeIcon :icon="option.icon" fixed-width aria-hidden="true" />
					{{ option.label }}
				</button>
			</div>

			<span v-if="latest" class="text-xs text-gray-600">
				{{ ctrans("Chrome visits :from – :to", { from: useFormatTime(latest.period_start, { formatTime: "PP" }), to: useFormatTime(latest.period_end, { formatTime: "PP" }) }) }}
			</span>
		</div>

		<div v-if="isLoading" class="space-y-3 px-6 py-6">
			<div class="h-4 w-56 animate-pulse rounded bg-gray-200" />
			<div class="h-64 w-full animate-pulse rounded bg-gray-100" />
		</div>

		<div v-else-if="!report?.scope" class="px-6 py-6 text-sm text-gray-600">
			{{ ctrans("Google has no real user data for this website yet. It needs enough visits from Chrome users over 28 days.") }}
		</div>

		<div v-else class="space-y-4 px-6 py-6">
			<div class="flex flex-wrap gap-2">
				<span
					v-for="metric in metrics"
					:key="metric.key"
					v-tooltip="`${metric.short}: ${metric.label}`"
					class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm tabular-nums shadow-sm">
					<FontAwesomeIcon :icon="metric.icon" :style="{ color: ratingTextColor[ratingOf(metric.key, latest?.[metric.key] ?? null) ?? 'needs_improvement'] }" fixed-width aria-hidden="true" />
					<span class="font-semibold text-gray-700">{{ display(metric.key, latest?.[metric.key] ?? null) }}</span>
					<span class="h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: ratingColor[ratingOf(metric.key, latest?.[metric.key] ?? null) ?? 'needs_improvement'] }" aria-hidden="true" />
					<span class="sr-only">{{ metric.label }}</span>
				</span>
			</div>

			<div class="h-80 w-full">
				<Chart type="line" class="h-full" :data="chartData" :options="chartOptions" :plugins="[ratingBands]" />
			</div>

			<div class="space-y-1 text-xs text-gray-600">
				<div v-if="report.scope === 'website'" data-crux-website-scope>
					{{ ctrans("Whole website (:url): this page does not have enough visits for Google to report it on its own.", { url: report.url ?? "" }) }}
				</div>
				<div>{{ ctrans("75th percentile of real Chrome visits over 28 days, from the Chrome UX Report. Google adds a new point every week.") }}</div>
			</div>
		</div>
	</div>
</template>
