<script setup lang="ts">
import { computed } from "vue"
import Chart from "primevue/chart"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMobile, faDesktop } from "@fal"

type Rating = "fast" | "average" | "slow" | "good" | "needs_improvement" | "poor" | null

const props = defineProps<{
	pagespeed?: {
		url: string
		strategy: string
		fetched_at: string
		overall_rating: string | null
		error?: string
		scores: Array<{ key: string; label: string; score: number; rating: Rating }>
		lab: Array<{ key: string; label: string; value: number | null; display: string | null; rating: Rating }>
		field: Array<{ key: string; label: string; percentile: number | null; display: string | null; rating: Rating; distributions: number[] }>
	}
}>()

const ratingColor = {
	fast: "#0CCE6B",
	good: "#0CCE6B",
	average: "#FFA400",
	needs_improvement: "#FFA400",
	slow: "#FF4E42",
	poor: "#FF4E42",
}

const colorOf = (rating: Rating) => ratingColor[rating ?? "average"] ?? "#9ca3af"

const isLoading = computed(() => props.pagespeed === undefined)
const isEmpty = computed(() => !isLoading.value && !props.pagespeed?.scores?.length)

const circumference = 2 * Math.PI * 20

const fieldChartData = computed(() => ({
	labels: (props.pagespeed?.field ?? []).map((metric) => metric.label),
	datasets: [
		{ label: trans("Good"), data: (props.pagespeed?.field ?? []).map((metric) => metric.distributions[0] ?? 0), backgroundColor: ratingColor.good },
		{ label: trans("Needs improvement"), data: (props.pagespeed?.field ?? []).map((metric) => metric.distributions[1] ?? 0), backgroundColor: ratingColor.average },
		{ label: trans("Poor"), data: (props.pagespeed?.field ?? []).map((metric) => metric.distributions[2] ?? 0), backgroundColor: ratingColor.poor },
	],
}))

const fieldChartOptions = {
	responsive: true,
	maintainAspectRatio: false,
	indexAxis: "y",
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: "circle", color: "#6b7280" } },
		tooltip: { callbacks: { label: (item: any) => `${item.dataset.label}: ${item.raw}%` } },
	},
	scales: {
		x: { stacked: true, max: 100, grid: { display: false }, ticks: { callback: (value: number) => `${value}%`, color: "#6b7280" } },
		y: { stacked: true, grid: { display: false }, ticks: { color: "#374151" } },
	},
}
</script>

<template>
	<div class="rounded-lg bg-white shadow">
		<div class="flex flex-wrap items-center gap-3 border-b px-6 py-3">
			<span class="text-sm font-semibold">{{ trans("PageSpeed Insights") }}</span>
			<span v-if="pagespeed?.strategy" class="flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
				<FontAwesomeIcon :icon="pagespeed.strategy === 'desktop' ? faDesktop : faMobile" fixed-width />
				{{ pagespeed.strategy === "desktop" ? trans("Desktop") : trans("Mobile") }}
			</span>
			<span v-if="pagespeed?.fetched_at" class="ml-auto text-xs text-gray-500">
				{{ trans("Measured") }} {{ useFormatTime(pagespeed.fetched_at, { formatTime: "PPp" }) }}
			</span>
		</div>

		<div v-if="isLoading" class="grid grid-cols-2 gap-6 p-6 sm:grid-cols-4">
			<div v-for="placeholder in 4" :key="placeholder" class="flex animate-pulse flex-col items-center gap-2">
				<div class="h-24 w-24 rounded-full bg-gray-200" />
				<div class="h-3 w-20 rounded bg-gray-200" />
			</div>
		</div>

		<div v-else-if="isEmpty" class="px-6 py-6 text-sm text-gray-500">
			{{ pagespeed?.error ?? trans("No PageSpeed Insights data available for this page") }}
		</div>

		<div v-else class="space-y-6 p-6">
			<div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
				<div v-for="score in pagespeed.scores" :key="score.key" class="flex flex-col items-center gap-2">
					<div class="relative h-24 w-24">
						<svg viewBox="0 0 48 48" class="h-full w-full -rotate-90">
							<circle cx="24" cy="24" r="20" fill="none" stroke="#e5e7eb" stroke-width="4" />
							<circle
								cx="24"
								cy="24"
								r="20"
								fill="none"
								:stroke="colorOf(score.rating)"
								stroke-width="4"
								stroke-linecap="round"
								:stroke-dasharray="circumference"
								:stroke-dashoffset="circumference * (1 - score.score / 100)" />
						</svg>
						<span class="absolute inset-0 flex items-center justify-center text-xl font-semibold" :style="{ color: colorOf(score.rating) }">
							{{ score.score }}
						</span>
					</div>
					<span class="text-center text-xs text-gray-600">{{ score.label }}</span>
				</div>
			</div>

			<div v-if="pagespeed.field?.length" class="space-y-3">
				<div class="text-sm font-semibold">{{ trans("Core Web Vitals") }} <span class="font-normal text-gray-500">({{ trans("real users, last 28 days") }})</span></div>
				<div class="grid gap-3 sm:grid-cols-5">
					<div v-for="metric in pagespeed.field" :key="metric.key" class="rounded border p-3" :style="{ borderColor: colorOf(metric.rating) }">
						<div class="text-xs text-gray-500">{{ metric.label }}</div>
						<div class="text-lg font-semibold" :style="{ color: colorOf(metric.rating) }">{{ metric.display ?? "—" }}</div>
					</div>
				</div>
				<div class="h-64 w-full">
					<Chart type="bar" class="h-full" :data="fieldChartData" :options="fieldChartOptions" />
				</div>
			</div>

			<div v-if="pagespeed.lab?.length" class="space-y-3">
				<div class="text-sm font-semibold">{{ trans("Lab metrics") }}</div>
				<div class="grid gap-3 sm:grid-cols-5">
					<div v-for="metric in pagespeed.lab" :key="metric.key" class="rounded border border-gray-200 p-3">
						<div class="flex items-center gap-1.5 text-xs text-gray-500">
							<span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: colorOf(metric.rating) }" />
							{{ metric.label }}
						</div>
						<div class="text-lg font-semibold text-gray-800">{{ metric.display ?? "—" }}</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
