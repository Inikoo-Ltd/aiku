<script setup lang="ts">
import { ref, watch } from "vue"
import Chart from "primevue/chart"

const props = defineProps<{
	labels: string[]
	data: number[]
	backgroundColors: string[]
}>()

const chartData = ref({
	labels: props.labels,
	datasets: [
		{
			label: "Core Web Vitals",
			data: props.data,
			backgroundColor: props.backgroundColors,
			barPercentage: 0.8,
			categoryPercentage: 0.8,
		},
	],
})

watch(
	() => [props.labels, props.data, props.backgroundColors],
	() => {
		chartData.value = {
			labels: props.labels,
			datasets: [
				{
					label: "Core Web Vitals",
					data: props.data,
					backgroundColor: props.backgroundColors,
					barPercentage: 0.8,
					categoryPercentage: 0.8,
				},
			],
		}
	}
)

const chartOptions = ref({
	responsive: true,
	maintainAspectRatio: false,
	indexAxis: "y",
	plugins: {
		legend: {
			display: false,
		},
		tooltip: {
			callbacks: {
				label: (context: any) => `${context.raw}%`,
			},
		},
	},
	scales: {
		x: {
			beginAtZero: true,
			max: 100,
			grid: {
				color: "#ddd",
			},
			ticks: {
				color: "#333",
			},
		},
		y: {
			grid: {
				display: false,
			},
			ticks: {
				color: "#333",
			},
		},
	},
})
</script>

<template>
	<div class="p-4 bg-white border rounded shadow-xs">
		<h3 class="text-sm font-medium mb-4">Core Web Vitals</h3>
		<Chart type="bar" :data="chartData" :options="chartOptions" class="h-60" />
	</div>
</template>
