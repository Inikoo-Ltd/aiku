<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Jun 2025 18:32:51 British Summer Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {
	faArrowUp,
	faArrowDown,
	faHandSparkles,
	faEnvelope,
	faUser,
	faHdd,
	faCloudDownload,
} from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Chart from "primevue/chart"
import { onMounted, Ref, ref, watch } from "vue"
import { useFormatTime } from "../../../../Composables/useFormatTime"
import { Head, router } from "@inertiajs/vue3";
import SelectButton from "primevue/selectbutton"
import MetricCard from "../../../../Components/DataDisplay/MetricCard.vue"
import OverviewCard from "../../../../Components/DataDisplay/OverviewCard.vue"
import HorizontalCard from "../../../../Components/DataDisplay/HorizontalCard.vue"
import { computed } from "vue"
import { capitalize } from "@/Composables/capitalize";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

library.add(faArrowUp, faArrowDown, faHandSparkles, faEnvelope, faUser, faHdd, faCloudDownload)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string


	data: {
		rumAnalyticsTimeseries: {
			data: {
				viewer: {
					accounts: Array<{
						series: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
							dimensions: {
								ts: string
							}
							sum: {
								visits: number
							}
						}>
					}>
				}
			}
		}
		rumAnalyticsTopNs: {
			data: {
				viewer: {
					accounts: Array<{
						countries: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
							dimensions: {
								metric: string // e.g., "GB" (country code)
							}
						}>
						topBrowsers: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
							dimensions: {
								metric: string // e.g., "Chrome" (browser name)
							}
						}>
						topDeviceTypes: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
							dimensions: {
								metric: string // e.g., "desktop", "mobile"
							}
						}>
						topOS: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
							dimensions: {
								metric: string // e.g., "Windows", "MacOS"
							}
						}>
						topHosts: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
							dimensions: {
								metric: string // Host information
							}
						}>
						topPaths: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
							dimensions: {
								metric: string // Path details
							}
						}>
						topReferrers: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
							dimensions: {
								metric: string // Referrer details
							}
						}>
						total: {
							count: number // Total count of visits
							sum: {
								visits: number // Total sum of visits
							}
						}
					}>
				}
			}
		}
		rumSparkline: {
			data: {
				viewer: {
					accounts: Array<{
						cls: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
						}>
						fid: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
						}>
						inp: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
						}>
						lcp: Array<{
							avg: {
								sampleInterval: number
							}
							count: number
						}>
						pageviews: Array<{
							sampleInterval: number
							count: number
							dimensions: {
								ts: string // ISO timestamp
							}
						}>
						pageviewsDelta: {
							count: number
						}
						performance: {
							aggregation: {
								pageLoadTime: number // Total page load time
							}
							avg: {
								sampleInterval: number
							}
							count: number
						}
						performanceDelta: {
							aggregation: {
								pageLoadTime: number
							}
						}
						totalPerformance: {
							aggregation: {
								pageLoadTime: number
							}
						}
						visits: Array<{
							sum: {
								visits: number
							}
						}>
						visitsDelta: {
							sum: {
								visits: number
							}
						}
					}>
				}
			}
		}
		zone: {
			data: {
				viewer: {
					zones: Array<{
						zones: Array<{
							dimensions: { timeslot: string }
							sum: {
								requests: number
								cachedRequests?: number
								bytes: number
								cachedBytes?: number
								encryptedRequests?: number
								encryptedBytes?: number
							}
							uniq: { uniques: number }
						}>
					}>
				}
			}
		}
	}
}>()

interface SummaryMetric {
	id: number
	label: string
	value: string
	icon: string
	change: string
	changeType: "increase" | "decrease"
}
console.log(props.data.rumAnalyticsTimeseries)

const reloadData = (until: string, since: string) => {
	router.reload({
		data: { until, since },
		only: ["analytics"],
		onSuccess: () => {
			setChartDataAndOptions()
		},
	})
}

const summaryMetrics: Ref<SummaryMetric[]> = ref([])
const value = ref("24 Hours") // Force to 24 hours by default
const options = ref(["24 Hours", "7 Days", "30 Days"])
const metrics = [
	{ label: "Unique Visitors", dataKey: "uniques", color: "--p-cyan-500" },
	{ label: "Total Requests", dataKey: "requests", color: "--p-blue-500" },
]
const chartsData = ref({})
const chartsOptions = ref({})

const selectedMetric = ref("Unique Visitors")

// Function to format times in HH:mm format
const formatTime = (timestamp: string): string => {
	const date = new Date(timestamp)
	const hours = date.getHours().toString().padStart(2, "0")
	const minutes = date.getMinutes().toString().padStart(2, "0")
	return `${hours}:${minutes}`
}

const handleSelectChange = () => {
	const today = new Date()
	const since = new Date(today)
	since.setHours(today.getHours() - 24) // Get the time 24 hours ago
	const until = today.toISOString().split("T")[0] // Use today's date as "until"

	console.log("Since:", since)
	console.log("Until:", until)

	reloadData(until, since.toISOString().split("T")[0])
}

const setChartDataAndOptions = () => {
	// Retrieve your timeseries data
	const timeseriesData =
		props.data?.rumAnalyticsTimeseries?.data?.viewer?.accounts[0]?.series || []

	// Determine the current time rounded down to the nearest 15 minutes.
	const now = new Date()
	const roundedNow = new Date(now)
	const currentMinutes = roundedNow.getMinutes()
	roundedNow.setMinutes(currentMinutes - (currentMinutes % 15), 0, 0)
	const endTime = roundedNow // end of the timeline
	// Set startTime to 24 hours before the endTime.
	const startTime = new Date(endTime.getTime() - 24 * 60 * 60 * 1000)

	// We'll create 96 buckets (15-minute intervals)
	const labels: string[] = []
	const dataPoints: number[] = new Array(96).fill(0)
	const intervalMs = 15 * 60 * 1000 // 15 minutes in milliseconds

	// Helper: Format a Date as "HH:mm"
	const formatTime = (date: Date): string =>
		`${date.getHours().toString().padStart(2, "0")}:${date
			.getMinutes()
			.toString()
			.padStart(2, "0")}`

	// Generate labels for every 15-minute bucket.
	// Every 12th bucket (every 3 hours) gets a visible label.
	for (let i = 0; i < 96; i++) {
		const bucketTime = new Date(startTime.getTime() + i * intervalMs)
		if (i % 12 === 0) {
			if (i === 0) {
				// For the very first bucket, show the time.
				labels.push(formatTime(bucketTime))
			} else {
				// Compare with the previous labeled bucket (12 intervals earlier)
				const prevBucketTime = new Date(startTime.getTime() + (i - 12) * intervalMs)
				if (bucketTime.toDateString() !== prevBucketTime.toDateString()) {
					// New day: display as "Tue 11"
					labels.push(
						bucketTime.toLocaleDateString("en-US", {
							weekday: "short",
							day: "numeric",
						})
					)
				} else {
					// Same day: display as "HH:mm"
					labels.push(formatTime(bucketTime))
				}
			}
		} else {
			labels.push("") // no visible label for this bucket
		}
	}

	// Bucket the data points into their respective 15-minute intervals.
	timeseriesData.forEach((item) => {
		const timestamp = new Date(item.dimensions.ts)
		// Only include data points within the 24-hour window.
		if (timestamp < startTime || timestamp > endTime) {
			return
		}
		const diffMs = timestamp.getTime() - startTime.getTime()
		const bucketIndex = Math.floor(diffMs / intervalMs)
		if (bucketIndex >= 0 && bucketIndex < dataPoints.length) {
			dataPoints[bucketIndex] += item.sum.visits
		}
	})

	chartsData.value = {
		"Analytics Timeseries": {
			labels: labels,
			datasets: [
				{
					label: "Total visits",
					data: dataPoints,
					borderColor: "#007bff",
					backgroundColor: "transparent",
					fill: false,
					tension: 0,
					borderWidth: 2,
					pointRadius: 0,
				},
			],
		},
	}

	// Update the chart options, adding tooltip callbacks.
	chartsOptions.value = {
		"Analytics Timeseries": {
			maintainAspectRatio: false,
			responsive: true,
			interaction: {
				mode: "index",
				intersect: false,
			},
			plugins: {
				legend: { display: true },
				tooltip: {
					backgroundColor: "#333",
					titleColor: "#fff",
					bodyColor: "#fff",
					borderColor: "#fff",
					borderWidth: 1,
					callbacks: {
						// The title callback computes the full date/time for the hovered bucket.
						title: (tooltipItems) => {
							// Get the dataIndex from the first hovered item.
							const index = tooltipItems[0].dataIndex
							const bucketTime = new Date(startTime.getTime() + index * intervalMs)
							// Format the bucketTime as "YYYY-MM-DD HH:mm"
							const year = bucketTime.getFullYear()
							const month = (bucketTime.getMonth() + 1).toString().padStart(2, "0")
							const day = bucketTime.getDate().toString().padStart(2, "0")
							const hours = bucketTime.getHours().toString().padStart(2, "0")
							const minutes = bucketTime.getMinutes().toString().padStart(2, "0")
							return `${year}-${month}-${day} ${hours}:${minutes}`
						},
						// The label callback shows the count.
						label: (tooltipItem) => {
							return `Count: ${tooltipItem.parsed.y}`
						},
					},
				},
			},
			scales: {
				x: {
					ticks: { color: "#333", font: { size: 12 } },
					grid: { color: "#ddd", borderDash: [4, 2] },
				},
				y: {
					ticks: { color: "#333", font: { size: 12 } },
					grid: { color: "#ddd", borderDash: [4, 2] },
				},
			},
		},
	}

	// Update summary metrics (example: total visits).
	const totalVisits = dataPoints.reduce((sum, visits) => sum + visits, 0)
	summaryMetrics.value = [
		{
			id: 1,
			label: "Total Visits",
			value: totalVisits.toLocaleString(),
			icon: "fal fa-eye",
			change: "",
			changeType: "",
		},
	]
}

const handleCardClick = (metricLabel: string) => {
	selectedMetric.value = metricLabel
}

const visitsTotal = computed(() => {
	const accounts = props.data?.rumAnalyticsTimeseries?.data?.viewer?.accounts

	if (!accounts || !Array.isArray(accounts) || accounts.length === 0) {
		return 0
	}

	return accounts.reduce((accountSum, account) => {
		if (!account.series || !Array.isArray(account.series)) {
			return accountSum
		}

		const seriesSum = account.series.reduce((seriesTotal, seriesItem) => {
			return seriesTotal + (seriesItem.sum?.visits || 0)
		}, 0)

		return accountSum + seriesSum
	}, 0)
})

const pageViewsTotal = computed(() => {
	const accounts = props.data?.rumAnalyticsTimeseries?.data?.viewer?.accounts
	if (!accounts || !Array.isArray(accounts) || accounts.length === 0) {
		return 0
	}

	return accounts.reduce((totalSum, account) => {
		if (!account.series || !Array.isArray(account.series)) {
			return totalSum
		}

		const accountSeriesSum = account.series.reduce((sum, seriesItem) => {
			return sum + (seriesItem.count || 0)
		}, 0)

		return totalSum + accountSeriesSum
	}, 0)
})

const pageViewsChartData = computed(() => {
	const timeseriesData =
		props.data?.rumAnalyticsTimeseries?.data?.viewer?.accounts[0]?.series || []

	const now = new Date()
	const roundedNow = new Date(now)
	const currentMinutes = roundedNow.getMinutes()
	roundedNow.setMinutes(currentMinutes - (currentMinutes % 15), 0, 0)
	const endTime = roundedNow

	const startTime = new Date(endTime.getTime() - 24 * 60 * 60 * 1000)

	const labels: string[] = []
	const dataPoints: number[] = new Array(96).fill(0)
	const intervalMs = 15 * 60 * 1000 

	const formatTimeInternal = (date: Date): string =>
		`${date.getHours().toString().padStart(2, "0")}:${date
			.getMinutes()
			.toString()
			.padStart(2, "0")}`

	for (let i = 0; i < 96; i++) {
		const bucketTime = new Date(startTime.getTime() + i * intervalMs)
		if (i % 12 === 0) {
			if (i === 0) {
				labels.push(formatTimeInternal(bucketTime))
			} else {
				const prevBucketTime = new Date(startTime.getTime() + (i - 12) * intervalMs)
				if (bucketTime.toDateString() !== prevBucketTime.toDateString()) {
					labels.push(
						bucketTime.toLocaleDateString("en-US", {
							weekday: "short",
							day: "numeric",
						})
					)
				} else {
					labels.push(formatTimeInternal(bucketTime))
				}
			}
		} else {
			labels.push("")
		}
	}

	timeseriesData.forEach((item) => {
		const timestamp = new Date(item.dimensions.ts)
		if (timestamp < startTime || timestamp > endTime) {
			return
		}
		const diffMs = timestamp.getTime() - startTime.getTime()
		const bucketIndex = Math.floor(diffMs / intervalMs)
		if (bucketIndex >= 0 && bucketIndex < dataPoints.length) {
			dataPoints[bucketIndex] += item.count || 0
		}
	})

	return {
		labels: labels,
		datasets: [
			{
				label: "Page Views",
				data: dataPoints,
				borderColor: "#3b82f6",
				backgroundColor: "transparent",
				fill: false,
				tension: 0,
				borderWidth: 2,
				pointRadius: 0,
			},
		],
	}
})

onMounted(() => {
	setChartDataAndOptions()
})
watch(value, handleSelectChange)
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

	<div class="min-h-screen bg-gray-100 p-6">
		<!-- Layout -->
		<div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
			<!-- Sidebar -->
			<div class="space-y-4">
				<!-- Visits Card -->

				<OverviewCard
					label="Visits"
					:value="visitsTotal"
					:percentageChange="0"
					:chartData="chartsData['Analytics Timeseries']" />

				<OverviewCard
					label="Page Views"
					:value="pageViewsTotal"
					:percentageChange="0"
					:chartData="pageViewsChartData" />

				<!-- 				<OverviewCard
					label="Page Load Time"
					value="1781ms"
					:percentageChange="-72.34"
					:chartData="{
						labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
						datasets: [
							{
								label: 'Visits',
								data: [10, 20, 15, 10, 5, 10, 5],
								borderColor: '#3b82f6',
								backgroundColor: 'rgba(59, 130, 246, 0.2)',
								fill: true,
							},
						],
					}" />

				<HorizontalCard :labels="labels" :data="data" :backgroundColors="thresholdColors" /> -->
			</div>

			<!-- Main Content -->
			<div class="lg:col-span-3">
				<div class="bg-white rounded-lg shadow-md p-6">
					<div class="relative">
						<Chart
							type="line"
							:data="chartsData['Analytics Timeseries']"
							:options="chartsOptions['Analytics Timeseries']"
							class="h-96" />
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.min-h-screen {
	font-family: Arial, sans-serif;
}
</style>
