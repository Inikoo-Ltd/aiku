<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import axios from "axios"
import Chart from "primevue/chart"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMobile, faDesktop, faSyncAlt } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"

type Rating = "fast" | "average" | "slow" | "good" | "needs_improvement" | "poor" | null
type Strategy = "mobile" | "desktop"

type StrategyReport = {
	url: string
	strategy: string
	fetched_at: string
	overall_rating: string | null
	measuring?: boolean
	error?: string
	scores: Array<{ key: string; label: string; score: number; rating: Rating }>
	lab: Array<{ key: string; label: string; value: number | null; display: string | null; rating: Rating }>
	field: Array<{ key: string; label: string; percentile: number | null; display: string | null; rating: Rating; distributions: number[] }>
}

const props = defineProps<{
	pagespeed?: {
		status: "ready" | "measuring" | "unavailable"
		message?: string
		refresh_route?: { name: string; parameters: Record<string, string | number> }
		mobile?: StrategyReport | null
		desktop?: StrategyReport | null
	}
}>()

const POLL_INTERVAL_MS = 15000
const MAX_POLLS = 12

const strategy = ref<Strategy>("mobile")
const polls = ref(0)
const isRefreshing = ref(false)
let pollTimer: ReturnType<typeof setTimeout> | null = null

const markColor = {
	fast: "#0CCE6B",
	good: "#0CCE6B",
	average: "#FFA400",
	needs_improvement: "#FFA400",
	slow: "#FF4E42",
	poor: "#FF4E42",
}

// Google's rating colors are too light for text on white, so readable variants carry the numbers
const textColor = {
	fast: "#0A7B41",
	good: "#0A7B41",
	average: "#8F5700",
	needs_improvement: "#8F5700",
	slow: "#B3261E",
	poor: "#B3261E",
}

const colorOf = (rating: Rating) => markColor[rating ?? "average"] ?? "#9ca3af"
const textColorOf = (rating: Rating) => textColor[rating ?? "average"] ?? "#4b5563"

const strategies: Array<{ key: Strategy; label: string; icon: typeof faMobile }> = [
	{ key: "mobile", label: trans("Mobile"), icon: faMobile },
	{ key: "desktop", label: trans("Desktop"), icon: faDesktop },
]

const isLoading = computed(() => props.pagespeed === undefined)
const isUnavailable = computed(() => props.pagespeed?.status === "unavailable")
const report = computed<StrategyReport | null>(() => props.pagespeed?.[strategy.value] ?? null)
const hasScores = computed(() => !!report.value?.scores?.length)
const isStalled = computed(() => props.pagespeed?.status === "measuring" && polls.value >= MAX_POLLS)

const fieldChartData = computed(() => ({
	labels: (report.value?.field ?? []).map((metric) => metric.label),
	datasets: [
		{ label: trans("Good"), data: (report.value?.field ?? []).map((metric) => metric.distributions[0] ?? 0), backgroundColor: markColor.good },
		{ label: trans("Needs improvement"), data: (report.value?.field ?? []).map((metric) => metric.distributions[1] ?? 0), backgroundColor: markColor.average },
		{ label: trans("Poor"), data: (report.value?.field ?? []).map((metric) => metric.distributions[2] ?? 0), backgroundColor: markColor.poor },
	],
}))

const fieldChartOptions = {
	responsive: true,
	maintainAspectRatio: false,
	indexAxis: "y",
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: "circle", color: "#4b5563" } },
		tooltip: { callbacks: { label: (item: any) => `${item.dataset.label}: ${item.raw}%` } },
	},
	scales: {
		x: { stacked: true, max: 100, grid: { display: false }, ticks: { callback: (value: number) => `${value}%`, color: "#4b5563" } },
		y: { stacked: true, grid: { display: false }, ticks: { color: "#374151" } },
	},
}

const circumference = 2 * Math.PI * 20

const stopPolling = () => {
	if (pollTimer) {
		clearTimeout(pollTimer)
		pollTimer = null
	}
}

const pollUntilMeasured = () => {
	stopPolling()

	if (polls.value >= MAX_POLLS) {
		return
	}

	pollTimer = setTimeout(() => {
		polls.value++
		router.reload({ only: ["pagespeed"] })
	}, POLL_INTERVAL_MS)
}

const reMeasure = async () => {
	const refreshRoute = props.pagespeed?.refresh_route

	if (!refreshRoute || isRefreshing.value) {
		return
	}

	isRefreshing.value = true

	try {
		await axios.post(route(refreshRoute.name, refreshRoute.parameters))
		polls.value = 0
		router.reload({ only: ["pagespeed"] })
	} catch (error) {
		notify({
			title: trans("Something went wrong"),
			text: trans("The PageSpeed Insights run could not be queued"),
			type: "error",
		})
	} finally {
		isRefreshing.value = false
	}
}

watch(
	() => props.pagespeed,
	(pagespeed) => {
		stopPolling()

		if (pagespeed?.status === "measuring") {
			pollUntilMeasured()
		} else {
			polls.value = 0
		}
	},
	{ immediate: true }
)

onBeforeUnmount(stopPolling)
</script>

<template>
	<div class="rounded-lg bg-white shadow">
		<div class="flex flex-wrap items-center gap-3 border-b px-6 py-3">
			<span class="text-sm font-semibold">{{ trans("PageSpeed Insights") }}</span>

			<div v-if="!isLoading && !isUnavailable" class="flex rounded-md bg-gray-100 p-0.5">
				<button
					v-for="option in strategies"
					:key="option.key"
					type="button"
					:aria-pressed="strategy === option.key"
					class="flex items-center gap-1.5 rounded px-2.5 py-1 text-xs focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600"
					:class="strategy === option.key ? 'bg-white font-semibold text-gray-800 shadow-sm' : 'text-gray-600 hover:text-gray-800'"
					@click="strategy = option.key">
					<FontAwesomeIcon :icon="option.icon" fixed-width />
					{{ option.label }}
				</button>
			</div>

			<span v-if="report?.fetched_at" class="text-xs text-gray-600">
				{{ trans("Measured") }} {{ useFormatTime(report.fetched_at, { formatTime: "PPp" }) }}
			</span>

			<span v-if="report?.measuring" class="text-xs text-gray-600">{{ trans("Re-measuring now") }}</span>

			<Button
				v-if="pagespeed?.refresh_route"
				class="ml-auto focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600"
				size="xs"
				type="tertiary"
				:icon="faSyncAlt"
				:label="trans('Re-measure')"
				:loading="isRefreshing"
				:disabled="isRefreshing"
				@click="reMeasure" />
		</div>

		<div v-if="isLoading" class="grid grid-cols-2 gap-6 p-6 sm:grid-cols-4">
			<div v-for="placeholder in 4" :key="placeholder" class="flex animate-pulse flex-col items-center gap-2">
				<div class="h-24 w-24 rounded-full bg-gray-200" />
				<div class="h-3 w-20 rounded bg-gray-200" />
			</div>
		</div>

		<div v-else-if="isUnavailable" class="px-6 py-6 text-sm text-gray-600">
			{{ pagespeed?.message ?? trans("No PageSpeed Insights data available for this page") }}
		</div>

		<div v-else-if="report?.error" class="space-y-1 px-6 py-6 text-sm">
			<div class="text-gray-800">{{ trans("Google could not measure this page") }}</div>
			<div class="text-gray-600">{{ report.error }}</div>
		</div>

		<div v-else-if="!report" class="space-y-4 p-6">
			<div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
				<div v-for="placeholder in 4" :key="placeholder" class="flex animate-pulse flex-col items-center gap-2">
					<div class="h-24 w-24 rounded-full bg-gray-200" />
					<div class="h-3 w-20 rounded bg-gray-200" />
				</div>
			</div>
			<div class="text-sm text-gray-600">
				{{ isStalled
					? trans("Google is still measuring this page. Use Re-measure to check again.")
					: trans("Google is measuring this page, results usually arrive within a minute.") }}
			</div>
		</div>

		<div v-else-if="!hasScores" class="px-6 py-6 text-sm text-gray-600">
			{{ trans("No PageSpeed Insights data available for this page") }}
		</div>

		<div v-else class="space-y-6 p-6">
			<div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
				<div v-for="score in report.scores" :key="score.key" class="flex flex-col items-center gap-2">
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
						<span class="absolute inset-0 flex items-center justify-center text-xl font-semibold" :style="{ color: textColorOf(score.rating) }">
							{{ score.score }}
						</span>
					</div>
					<span class="text-center text-xs text-gray-600">{{ score.label }}</span>
				</div>
			</div>

			<div v-if="report.field?.length" class="space-y-3">
				<div class="text-sm font-semibold">{{ trans("Core Web Vitals") }} <span class="font-normal text-gray-600">({{ trans("real users, last 28 days") }})</span></div>
				<div class="grid gap-3 sm:grid-cols-5">
					<div v-for="metric in report.field" :key="metric.key" class="rounded border p-3" :style="{ borderColor: colorOf(metric.rating) }">
						<div class="text-xs text-gray-600">{{ metric.label }}</div>
						<div class="text-lg font-semibold" :style="{ color: textColorOf(metric.rating) }">{{ metric.display ?? trans("n/a") }}</div>
					</div>
				</div>
				<div class="h-64 w-full">
					<Chart type="bar" class="h-full" :data="fieldChartData" :options="fieldChartOptions" />
				</div>
			</div>

			<div v-if="report.lab?.length" class="space-y-3">
				<div class="text-sm font-semibold">{{ trans("Lab metrics") }}</div>
				<div class="grid gap-3 sm:grid-cols-5">
					<div v-for="metric in report.lab" :key="metric.key" class="rounded border border-gray-200 p-3">
						<div class="flex items-center gap-1.5 text-xs text-gray-600">
							<span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: colorOf(metric.rating) }" />
							{{ metric.label }}
						</div>
						<div class="text-lg font-semibold text-gray-800">{{ metric.display ?? trans("n/a") }}</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
