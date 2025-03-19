<script setup lang="ts">
import { reactive, computed, watch, ref } from "vue"
import Chart from "primevue/chart"
import DateRangePicker from "@/Components/Utils/ModalDatePicker.vue"
import { router } from "@inertiajs/vue3"
import { debounce } from "lodash-es"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"

// Props for incoming data
const props = defineProps<{
	data: Array<{
		clicks: number
		ctr: number
		impressions: number
		keys: string[]
		position: number
	}>
}>()
const optionsTime = { formatTime: "PPP" }

const cardStates = reactive({
	totalClicks: true,
	totalImpressions: true,
})

const totalClicks = computed(() => props.data.reduce((sum, item) => sum + item.clicks, 0))
const totalImpressions = computed(() => props.data.reduce((sum, item) => sum + item.impressions, 0))

const chartData = reactive({
	labels: props.data.map((item) => item.keys[0]),
	datasets: [],
})

// Method to update chart data
const updateChartData = () => {
	const datasets = []
	if (cardStates.totalClicks) {
		datasets.push({
			label: "Clicks",
			data: props.data.map((item) => item.clicks),
			borderColor: "#4285F4",
			borderWidth: 2,
			fill: false,
			tension: 0.4,
			yAxisID: "y2",
		})
	}
	if (cardStates.totalImpressions) {
		datasets.push({
			label: "Impressions",
			data: props.data.map((item) => item.impressions),
			borderColor: "#5E35B1",
			borderWidth: 2,
			fill: false,
			tension: 0.4,
			yAxisID: "y1",
		})
	}
	chartData.labels = props.data.map((item) => item.keys[0])
	chartData.datasets = datasets
}

watch(
	() => [cardStates.totalClicks, cardStates.totalImpressions],
	() => {
		updateChartData()
	},
	{ immediate: true }
)

watch(
	() => props.data,
	() => {
		updateChartData()
	},
	{ deep: true, immediate: true }
)

const getChartOptions = (dateRangeLength ) => ({
	responsive: true,
	maintainAspectRatio: false,
	plugins: {
		legend: { display: false },
		tooltip: {
			enabled: true,
			mode: "index",
			intersect: false,
			callbacks: {
				title: (tooltipItems) => {
					const date = tooltipItems[0].label
					const options = {
						weekday: "long",
						year: "numeric",
						month: "short",
						day: "numeric",
					}
					return new Date(date).toLocaleDateString("en-US", options)
				},
				label: (tooltipItem) => {
					const label = tooltipItem.dataset.label || ""
					const value = tooltipItem.raw.toLocaleString()
					return `${label}: ${value}`
				},
			},
			backgroundColor: "#fff",
			titleColor: "#333",
			bodyColor: "#555",
			borderColor: "#ddd",
			borderWidth: 1,
			padding: 10,
			cornerRadius: 4,
			displayColors: true,
		},
	},
	scales: {
		x: {
			grid: { display: false },
			ticks: {
				color: "#6b7280",
				font: { size: 12 },
				autoSkip: true, 
				maxTicksLimit: dateRangeLength > 30 ? Math.floor(dateRangeLength / 7) : 10,
			},
		},
		y1: {
			type: "linear",
			position: "left",
			grid: { color: "#EDE7F6" },
			ticks: { color: "#5E35B1", font: { size: 12 }, stepSize: 50 },
			title: {
				display: true,
				text: "Impressions",
				color: "#5E35B1",
				font: { size: 14 },
			},
		},
		y2: {
			type: "linear",
			position: "right",
			grid: { drawOnChartArea: false },
			ticks: { color: "#4285F4", font: { size: 12 }, stepSize: 1 },
			title: {
				display: true,
				text: "Clicks",
				color: "#4285F4",
				font: { size: 14 },
			},
		},
	},
})

const showDateRangePicker = ref(false)
const selectedDateRange = ref<{ startDate: Date; endDate: Date } | null>(null)

const debouncedReloadData = debounce((endDate: string, startDate: string) => {
	router.reload({
		data: { endDate, startDate },
		only: ["analytics"],
		onSuccess: () => {
			updateChartData()
		},
	})
}, 500)

const handleDateRangeSelected = (range) => {
	selectedDateRange.value = range

	debouncedReloadData(
		range.endDate.toLocaleDateString("fr-CA"),
		range.startDate.toLocaleDateString("fr-CA")
	)
}
</script>

<template>
	<div class="p-8 space-y-8">


		<div v-if="selectedDateRange" class="mt-4 text-center text-gray-700">
			<p class="text-sm font-medium">
				Selected Date Range:
				<span class="font-semibold text-blue-600">
					{{ useFormatTime(selectedDateRange.startDate, optionsTime) }} -
					{{ useFormatTime(selectedDateRange.endDate, optionsTime) }}
				</span>
			</p>
		</div>

		<div class="p-8 rounded-lg shadow bg-white space-y-6">
			<!-- Cards Section -->
			<div class="grid grid-cols-4 gap-6">
				<!-- Card: Total Clicks -->
				<div
					:class="[
						cardStates.totalClicks
							? 'bg-[#4285F4] text-white'
							: 'bg-white text-[#4285F4] border border-[#4285F4]',
					]"
					class="relative p-5 rounded-lg shadow cursor-pointer flex items-center transition-colors duration-200"
					@click="cardStates.totalClicks = !cardStates.totalClicks">
					<input
						type="checkbox"
						class="absolute top-2 right-2 h-4 w-4 rounded border-gray-300 focus:ring-[#4285F4]"
						v-model="cardStates.totalClicks" />
					<div>
						<div class="text-xs">Total Clicks</div>
						<div class="text-lg font-semibold">{{ totalClicks }}</div>
					</div>
				</div>

				<!-- Card: Total Impressions -->
				<div
					:class="[
						cardStates.totalImpressions
							? 'bg-[#5E35B1] text-white'
							: 'bg-white text-[#5E35B1] border border-[#5E35B1]',
					]"
					class="relative p-5 rounded-lg shadow cursor-pointer flex items-center transition-colors duration-200"
					@click="cardStates.totalImpressions = !cardStates.totalImpressions">
					<input
						type="checkbox"
						class="absolute top-2 right-2 h-4 w-4 rounded border-gray-300 focus:ring-[#5E35B1]"
						v-model="cardStates.totalImpressions" />
					<div>
						<div class="text-xs">Total Impressions</div>
						<div class="text-lg font-semibold">{{ totalImpressions }}</div>
					</div>
				</div>
			</div>

			<!-- Chart -->
			<div>
				<div class="relative h-96 w-full">
					<Chart type="line" :data="chartData" :options="getChartOptions()" />
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped></style>
