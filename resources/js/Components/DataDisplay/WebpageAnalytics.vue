<script setup lang="ts">
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import { router } from "@inertiajs/vue3"
import { debounce } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faRocketLaunch, faTag } from "@fal"

const props = defineProps<{
	data: {
		start_date: string
		end_date: string
		currency: string
		search: Array<{ clicks: number; impressions: number; keys: string[] }>
		sales: Array<{ date: string; sales: number; orders: number }>
		events: Array<{ date: string; datetime: string; type: "publish" | "price"; label: string; user: string | null }>
	}
}>()

const locale = useLocaleStore()

const series = {
	clicks: { label: trans("Clicks"), color: "#4285F4", axis: "y2" },
	impressions: { label: trans("Impressions"), color: "#5E35B1", axis: "y1" },
	sales: { label: trans("Net sales"), color: "#0F9D58", axis: "y3" },
}
const eventStyle = {
	publish: { label: trans("Page published"), color: "#F4B400", icon: faRocketLaunch },
	price: { label: trans("Price change"), color: "#DB4437", icon: faTag },
}


const rangeDays = computed(() => Math.round((new Date(props.data.end_date).getTime() - new Date(props.data.start_date).getTime()) / 86400000) + 1)
const granularity = ref<"day" | "week">(rangeDays.value > 60 ? "week" : "day")

const bucketOf = (date: string) => {
	if (granularity.value === "day") return date
	const day = new Date(date)
	day.setDate(day.getDate() - ((day.getDay() + 6) % 7))
	return day.toISOString().slice(0, 10)
}

const labels = computed(() => {
	const buckets: string[] = []
	for (let day = new Date(props.data.start_date); day <= new Date(props.data.end_date); day.setDate(day.getDate() + 1)) {
		const bucket = bucketOf(day.toISOString().slice(0, 10))
		if (buckets.at(-1) !== bucket) buckets.push(bucket)
	}
	return buckets
})

const sumBy = (rows: Array<Record<string, any>>, dateOf: (row: any) => string, field: string) => {
	const totals: Record<string, number> = {}
	for (const row of rows) {
		const bucket = bucketOf(dateOf(row))
		totals[bucket] = (totals[bucket] ?? 0) + (row[field] ?? 0)
	}
	return totals
}

const clicksByBucket = computed(() => sumBy(props.data.search ?? [], (row) => row.keys[0], "clicks"))
const impressionsByBucket = computed(() => sumBy(props.data.search ?? [], (row) => row.keys[0], "impressions"))
const salesByBucket = computed(() => sumBy(props.data.sales ?? [], (row) => row.date, "sales"))

const totals = computed(() => ({
	clicks: (props.data.search ?? []).reduce((sum, row) => sum + row.clicks, 0),
	impressions: (props.data.search ?? []).reduce((sum, row) => sum + row.impressions, 0),
	sales: (props.data.sales ?? []).reduce((sum, row) => sum + row.sales, 0),
}))

const visible = ref({ clicks: totals.value.clicks > 0, impressions: totals.value.impressions > 0, sales: true })

const eventsByDate = computed(() => {
	const grouped: Record<string, typeof props.data.events> = {}
	for (const event of props.data.events ?? []) {
		;(grouped[bucketOf(event.date)] ??= []).push(event)
	}
	return grouped
})

const chartData = computed(() => ({
	labels: labels.value,
	datasets: [
		visible.value.clicks && {
			type: "bar",
			label: series.clicks.label,
			data: labels.value.map((bucket) => clicksByBucket.value[bucket] ?? 0),
			backgroundColor: series.clicks.color + "99",
			borderRadius: 2,
			barPercentage: 0.25,
			yAxisID: "y2",
			order: 3,
		},
		visible.value.impressions && {
			label: series.impressions.label,
			data: labels.value.map((bucket) => impressionsByBucket.value[bucket] ?? 0),
			borderColor: series.impressions.color,
			borderWidth: 2,
			pointRadius: 0,
			cubicInterpolationMode: "monotone",
			yAxisID: "y1",
			order: 2,
		},
		visible.value.sales && {
			label: series.sales.label,
			data: labels.value.map((bucket) => Math.round((salesByBucket.value[bucket] ?? 0) * 100) / 100),
			borderColor: series.sales.color,
			backgroundColor: "#0F9D5822",
			borderWidth: 2,
			pointRadius: 0,
			fill: true,
			cubicInterpolationMode: "monotone",
			yAxisID: "y3",
			order: 1,
		},
	].filter(Boolean),
}))

const eventMarkers = {
	id: "eventMarkers",
	afterDatasetsDraw(chart: any) {
		const { ctx, chartArea, scales } = chart
		chart.data.datasets.forEach((dataset: any, datasetIndex: number) => {
			if (dataset.type !== "bar") return
			ctx.save()
			ctx.fillStyle = series.clicks.color
			ctx.font = "600 11px sans-serif"
			ctx.textAlign = "center"
			chart.getDatasetMeta(datasetIndex).data.forEach((bar: any, index: number) => {
				const value = dataset.data[index]
				if (value > 0) ctx.fillText(value.toLocaleString(), bar.x, bar.y - 4)
			})
			ctx.restore()
		})
		for (const [date, events] of Object.entries(eventsByDate.value)) {
			const index = labels.value.indexOf(date)
			if (index < 0) continue
			const x = scales.x.getPixelForValue(index)
			const types = [...new Set(events.map((event) => event.type))]
			types.forEach((type, position) => {
				ctx.save()
				ctx.strokeStyle = eventStyle[type].color
				ctx.lineWidth = 1.5
				ctx.setLineDash(type === "price" ? [4, 3] : [])
				ctx.beginPath()
				ctx.moveTo(x, chartArea.top)
				ctx.lineTo(x, chartArea.bottom)
				ctx.stroke()
				ctx.fillStyle = eventStyle[type].color
				ctx.beginPath()
				ctx.arc(x, chartArea.top + 6 + position * 12, 4, 0, Math.PI * 2)
				ctx.fill()
				ctx.restore()
			})
		}
	},
}

const chartOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
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
				title: (items: any[]) => (granularity.value === "week" ? trans("Week of") + " " : "") + useFormatTime(items[0].label, { formatTime: "PPP" }),
				label: (item: any) =>
					item.dataset.yAxisID === "y3"
						? `${item.dataset.label}: ${locale.currencyFormat(props.data.currency, item.raw)}`
						: `${item.dataset.label}: ${item.raw.toLocaleString()}`,
				afterBody: (items: any[]) => (eventsByDate.value[items[0].label] ?? []).map((event) => `• ${eventStyle[event.type].label}: ${event.label}`),
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, color: "#6b7280" } },
		y1: { type: "linear", position: "left", display: visible.value.impressions, grid: { color: "#EDE7F6" }, ticks: { color: series.impressions.color }, beginAtZero: true, title: { display: true, text: series.impressions.label, color: series.impressions.color } },
		y2: { type: "linear", position: "right", display: visible.value.clicks, grid: { drawOnChartArea: false }, ticks: { color: series.clicks.color, precision: 0 }, beginAtZero: true, grace: "15%", title: { display: true, text: series.clicks.label, color: series.clicks.color } },
		y3: { type: "linear", position: "right", display: visible.value.sales, grid: { drawOnChartArea: false }, ticks: { color: series.sales.color }, min: 0, title: { display: true, text: `${series.sales.label} (${props.data.currency})`, color: series.sales.color } },
	},
}))

const range = ref({ startDate: props.data.start_date, endDate: props.data.end_date })

const reload = debounce(() => {
	router.reload({ data: { startDate: range.value.startDate, endDate: range.value.endDate }, only: ["analytics"] })
}, 400)

const formatTotal = (key: keyof typeof series) =>
	key === "sales" ? locale.currencyFormat(props.data.currency, totals.value.sales) : totals.value[key].toLocaleString()
</script>

<template>
	<div class="p-6 space-y-6">
		<div class="flex flex-wrap items-center gap-3 text-sm">
			<label class="flex items-center gap-2">
				<span class="text-gray-500">{{ trans("From") }}</span>
				<input type="date" v-model="range.startDate" :max="range.endDate" class="rounded border-gray-300 text-sm" @change="reload" />
			</label>
			<label class="flex items-center gap-2">
				<span class="text-gray-500">{{ trans("To") }}</span>
				<input type="date" v-model="range.endDate" :min="range.startDate" class="rounded border-gray-300 text-sm" @change="reload" />
			</label>
			<div class="flex rounded border border-gray-300 text-xs">
				<button
					v-for="option in ['day', 'week']"
					:key="option"
					type="button"
					class="px-3 py-1.5"
					:class="granularity === option ? 'bg-gray-800 text-white' : 'text-gray-600'"
					@click="granularity = option">
					{{ option === "day" ? trans("Daily") : trans("Weekly") }}
				</button>
			</div>
			<div class="ml-auto flex items-center gap-4 text-xs text-gray-500">
				<span v-for="(style, type) in eventStyle" :key="type" class="flex items-center gap-1">
					<span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: style.color }" />
					{{ style.label }}
				</span>
			</div>
		</div>

		<div class="rounded-lg bg-white p-6 shadow space-y-6">
			<div class="grid grid-cols-3 gap-4">
				<button
					v-for="(meta, key) in series"
					:key="key"
					type="button"
					class="rounded-lg border p-4 text-left transition-colors"
					:class="visible[key] ? 'text-white' : 'bg-white'"
					:style="visible[key] ? { backgroundColor: meta.color, borderColor: meta.color } : { color: meta.color, borderColor: meta.color }"
					@click="visible[key] = !visible[key]">
					<div class="text-xs">{{ meta.label }}</div>
					<div class="text-lg font-semibold">{{ formatTotal(key) }}</div>
				</button>
			</div>

			<div class="relative h-96 w-full">
				<Chart type="line" class="h-full" :data="chartData" :options="chartOptions" :plugins="[eventMarkers]" />
			</div>
		</div>

		<div class="rounded-lg bg-white shadow">
			<div class="border-b px-6 py-3 text-sm font-semibold">{{ trans("Changes in this period") }}</div>
			<div v-if="!data.events?.length" class="px-6 py-6 text-sm text-gray-500">{{ trans("No changes to this page in the selected period") }}</div>
			<ul v-else class="divide-y">
				<li v-for="event in data.events" :key="event.datetime" class="flex items-center gap-3 px-6 py-2 text-sm">
					<FontAwesomeIcon :icon="eventStyle[event.type].icon" :style="{ color: eventStyle[event.type].color }" fixed-width />
					<span class="w-44 shrink-0 text-gray-500">{{ useFormatTime(event.datetime, { formatTime: "PPp" }) }}</span>
					<span class="flex-1">{{ event.label }}</span>
					<span class="text-gray-500">{{ event.user ?? trans("System") }}</span>
				</li>
			</ul>
		</div>
	</div>
</template>
