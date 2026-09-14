<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed } from "vue"
import {
	Chart as ChartJS,
	CategoryScale,
	LinearScale,
	PointElement,
	LineElement,
	Filler,
	Tooltip,
	Legend,
} from "chart.js"
import { Line } from "vue-chartjs"
import { ctrans } from "@/Composables/useTrans"

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

/**
 * The question this answers: is this campaign's spend buying more clicks than it used to, or fewer?
 * That is why the two series share an x axis and get separate y axes rather than being two charts:
 * the shape worth seeing is cost climbing while clicks flatten.
 */
const props = defineProps<{
	daily: { date: string; clicks: number; cost: number }[]
	currency: string
}>()

// The action hands the series back newest first, for the table under it; a chart has to read forwards.
const series = computed(() => [...(props.daily ?? [])].reverse())

const hasData = computed(() => series.value.some((day) => day.clicks > 0 || day.cost > 0))

const chartData = computed(() => ({
	labels: series.value.map((day) =>
		new Date(day.date).toLocaleDateString(undefined, { day: "numeric", month: "short" })
	),
	datasets: [
		{
			label: ctrans("Cost"),
			data: series.value.map((day) => day.cost),
			borderColor: "#4f46e5",
			backgroundColor: "rgba(79,70,229,0.12)",
			fill: true,
			tension: 0.35,
			pointRadius: 0,
			pointHoverRadius: 4,
			borderWidth: 2,
			yAxisID: "cost",
		},
		{
			label: ctrans("Clicks"),
			data: series.value.map((day) => day.clicks),
			borderColor: "#a15c00",
			backgroundColor: "transparent",
			fill: false,
			tension: 0.35,
			pointRadius: 0,
			pointHoverRadius: 4,
			borderWidth: 2,
			borderDash: [4, 3],
			yAxisID: "clicks",
		},
	],
}))

const chartOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index" as const, intersect: false },
	plugins: {
		legend: { display: true, labels: { boxWidth: 12, font: { size: 11 } } },
	},
	scales: {
		x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkipPadding: 16 } },
		cost: {
			type: "linear" as const,
			position: "left" as const,
			beginAtZero: true,
			ticks: { font: { size: 10 } },
			title: { display: true, text: `${ctrans("Cost")} (${props.currency})`, font: { size: 10 } },
		},
		clicks: {
			type: "linear" as const,
			position: "right" as const,
			beginAtZero: true,
			grid: { display: false },
			ticks: { font: { size: 10 } },
			title: { display: true, text: ctrans("Clicks"), font: { size: 10 } },
		},
	},
}))
</script>

<template>
	<div>
		<div v-if="hasData" class="h-56">
			<Line :data="chartData" :options="chartOptions" />
		</div>
		<div v-else class="flex h-56 items-center justify-center text-xs text-gray-500">
			{{ ctrans("This campaign recorded no spend and no clicks in this period.") }}
		</div>
	</div>
</template>
