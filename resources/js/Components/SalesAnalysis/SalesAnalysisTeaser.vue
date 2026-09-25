<script setup lang="ts">
import { computed } from "vue"
import Chart from "primevue/chart"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChartLine, faArrowRight } from "@fal"

interface Totals {
	sales: number
	orders: number
	customers: number
	stock_outs: number
	stock_out_days: number
	lost_sales: number
	stock_outs_no_order: number
}

const props = defineProps<{
	teaser?: {
		period: { from: string; to: string }
		compare_period: { from: string; to: string }
		currency: string
		frequency: "daily" | "weekly" | "monthly"
		sales: Array<{ date: string; sales: number }>
		compare_sales: Array<{ date: string; sales: number }>
		totals: { current: Totals; previous: Totals }
		shop_count: number
	}
}>()

const money = (value: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.teaser?.currency ?? "GBP", maximumFractionDigits: 0 }).format(Math.round(value))
const percentChange = (current: number, previous: number) => (previous ? Math.round(((current - previous) / previous) * 100) : null)
const formatChange = (value: number | null) => (value === null ? "—" : `${value > 0 ? "+" : ""}${value}%`)

const analysisUrl = computed(() => {
	const url = new URL(window.location.href)
	url.search = "?tab=sales_analysis"
	return url.pathname + url.search
})

const salesChange = computed(() => (props.teaser ? percentChange(props.teaser.totals.current.sales, props.teaser.totals.previous.sales) : null))


const chartData = computed(() => ({
	labels: props.teaser?.sales.map((row) => row.date) ?? [],
	datasets: [
		{ label: ctrans("Sales"), data: props.teaser?.sales.map((row) => row.sales) ?? [], borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius: 0 },
		{ label: ctrans("Year before"), data: props.teaser?.compare_sales.map((row) => row.sales) ?? [], borderColor: "#9ca3af", backgroundColor: "#9ca3af", borderDash: [4, 3], tension: 0, borderWidth: 1.5, pointRadius: 0 },
	],
}))

const chartOptions = {
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { display: false },
		tooltip: {
			callbacks: {
				title: (items: any[]) => ctrans("Week of") + " " + useFormatTime(items[0].label, { formatTime: "PP" }),
				label: (item: any) => `${item.dataset.label}: ${money(item.raw ?? 0)}`,
			},
		},
	},
	scales: { x: { display: false }, y: { display: false, beginAtZero: true } },
}
</script>

<template>
	<div class="rounded-lg border border-gray-200 bg-white p-3 text-sm text-gray-700">
		<div class="mb-2 flex items-center gap-2">
			<FontAwesomeIcon :icon="faChartLine" class="text-gray-400" fixed-width aria-hidden="true" />
			<span class="whitespace-nowrap font-semibold">{{ ctrans("Last 12 months") }}</span>
			<span class="text-xs text-gray-500">{{ teaser && teaser.shop_count > 1 ? ctrans("vs year before, all websites") : ctrans("vs year before") }}</span>
			<Link :href="analysisUrl" class="ml-auto flex items-center gap-1 text-xs text-indigo-600 hover:underline">
				{{ ctrans("Sales analysis") }}
				<FontAwesomeIcon :icon="faArrowRight" fixed-width aria-hidden="true" />
			</Link>
		</div>

		<template v-if="teaser">
			<div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 tabular-nums">
				<span>
					<span class="font-semibold text-gray-900">{{ money(teaser.totals.current.sales) }}</span>
					<span class="ml-1 text-xs" :class="(salesChange ?? 0) < 0 ? 'text-red-600' : 'text-green-600'">{{ formatChange(salesChange) }}</span>
				</span>
				<span class="text-xs text-gray-500">{{ teaser.totals.current.orders.toLocaleString() }} {{ ctrans("orders") }} · {{ teaser.totals.current.customers.toLocaleString() }} {{ ctrans("customers") }}</span>
			</div>

			<div class="relative mt-2 h-40">
				<Chart type="line" class="h-full" :data="chartData" :options="chartOptions" />
			</div>

			<div class="mt-2 space-y-0.5 text-xs tabular-nums">
				<div :class="teaser.totals.current.stock_out_days ? 'text-red-600' : 'text-gray-500'">
					{{ teaser.totals.current.stock_out_days.toLocaleString() }} {{ ctrans("days out of stock") }} ({{ teaser.totals.current.stock_outs }}×)
					<span v-if="teaser.totals.current.lost_sales"> · ~{{ money(teaser.totals.current.lost_sales) }} {{ ctrans("lost") }}</span>
					<span v-if="teaser.totals.current.stock_outs_no_order"> · {{ teaser.totals.current.stock_outs_no_order }} {{ ctrans("ran out with nothing ordered") }}</span>
				</div>
			</div>
		</template>

		<div v-else class="space-y-2">
			<div class="h-5 w-40 animate-pulse rounded bg-gray-100" />
			<div class="h-40 animate-pulse rounded bg-gray-100" />
			<div class="h-4 w-56 animate-pulse rounded bg-gray-100" />
		</div>
	</div>
</template>
