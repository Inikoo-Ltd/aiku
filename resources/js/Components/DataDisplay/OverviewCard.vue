<script setup lang="ts">
import Chart from "primevue/chart"

const props = defineProps<{
	label: string
	value: number | string
	percentageChange: number // Positive or negative
	chartData: any // PrimeVue Chart Data
}>()

const chartOptions = {
	maintainAspectRatio: false,
	responsive: true,
	plugins: {
		legend: { display: false },
	},
	scales: {
		x: { display: false },
		y: { display: false },
	},
	elements: {
		point: { radius: 0 },
		line: { borderWidth: 2, tension: 0.4 },
	},
}
</script>

<template>
	<div class="bg-white border border-gray-300 rounded-lg shadow-xs p-4 w-full">
		<!-- Label and Value -->
		<div class="flex justify-between items-center">
			<div>
				<p class="text-sm font-medium text-gray-500">{{ label }}</p>
				<p class="text-2xl font-bold text-gray-900">{{ value }}</p>
			</div>
			<!-- <div
				:class="percentageChange < 0 ? 'text-red-500' : 'text-green-500'"
				class="flex items-center text-sm">
				<svg
					v-if="percentageChange < 0"
					xmlns="http://www.w3.org/2000/svg"
					class="h-4 w-4 mr-1"
					fill="none"
					viewBox="0 0 24 24"
					stroke="currentColor">
					<path
						stroke-linecap="round"
						stroke-linejoin="round"
						stroke-width="2"
						d="M5 10l7-7m0 0l7 7m-7-7v18" />
				</svg>
				<svg
					v-else
					xmlns="http://www.w3.org/2000/svg"
					class="h-4 w-4 mr-1"
					fill="none"
					viewBox="0 0 24 24"
					stroke="currentColor">
					<path
						stroke-linecap="round"
						stroke-linejoin="round"
						stroke-width="2"
						d="M5 14l7 7m0 0l7-7m-7 7V3" />
				</svg>
				{{ percentageChange }}%
			</div> -->
		</div>

		<!-- Chart -->
		<div class="mt-4">
			<Chart type="line" :data="chartData" :options="chartOptions" class="h-16" />
		</div>
	</div>
</template>
