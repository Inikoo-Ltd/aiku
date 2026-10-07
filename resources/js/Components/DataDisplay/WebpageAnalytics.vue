<script setup lang="ts">
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import DatePicker from "primevue/datepicker"
import { router } from "@inertiajs/vue3"
import { debounce } from "lodash-es"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faRocketLaunch, faTag, faInfoCircle } from "@fal"
import RealUserSpeed from "@/Components/DataDisplay/RealUserSpeed.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"

type EventType = "publish" | "price"

const props = defineProps<{
	real_user_speed?: any
	data: {
		start_date: string
		end_date: string
		currency: string
		search: Array<{ clicks: number; impressions: number; keys: string[] }>
		sales: Array<{ date: string; sales: number; orders: number }>
		events: Array<{ date: string; datetime: string; type: EventType; label: string; user: string | null }>
	}
}>()

const locale = useLocaleStore()

const series = {
	clicks: {
		label: ctrans("Clicks"),
		color: "#4285F4",
		axis: "y2",
		source: ctrans("Google Search Console"),
		sourceDetail: ctrans("Clicks from Google Search results to this page, reported by Google Search Console. The last 2 to 3 days can still change."),
	},
	impressions: {
		label: ctrans("Impressions"),
		color: "#5E35B1",
		axis: "y1",
		source: ctrans("Google Search Console"),
		sourceDetail: ctrans("Times this page appeared in Google Search results, reported by Google Search Console. The last 2 to 3 days can still change."),
	},
	sales: {
		label: ctrans("Net sales"),
		color: "#0F9D58",
		axis: "y3",
		source: ctrans("Invoices"),
		sourceDetail: ctrans("Net invoiced amount of the product, category or collection shown on this page, from every sales channel, not only visits to this page."),
	},
}
const eventStyle = {
	publish: { label: ctrans("Page published"), color: "#F4B400", icon: faRocketLaunch },
	price: { label: ctrans("Price change"), color: "#DB4437", icon: faTag },
}
const chartEventTypes: EventType[] = ["price"]


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
	for (const event of (props.data.events ?? []).filter((event) => chartEventTypes.includes(event.type))) {
		;(grouped[bucketOf(event.date)] ??= []).push(event)
	}
	return grouped
})

const pointRadius = computed(() => (labels.value.length > 40 ? 0 : 2))

const line = (key: keyof typeof series, data: number[]) => ({
	label: key === "sales" ? `${series.sales.label} (${props.data.currency})` : series[key].label,
	data,
	borderColor: series[key].color,
	backgroundColor: series[key].color,
	tension: 0,
	borderWidth: 1.5,
	pointRadius: pointRadius.value,
	yAxisID: series[key].axis,
})

const chartData = computed(() => ({
	labels: labels.value,
	datasets: [
		visible.value.sales && line("sales", labels.value.map((bucket) => Math.round((salesByBucket.value[bucket] ?? 0) * 100) / 100)),
		visible.value.impressions && line("impressions", labels.value.map((bucket) => impressionsByBucket.value[bucket] ?? 0)),
		visible.value.clicks && line("clicks", labels.value.map((bucket) => clicksByBucket.value[bucket] ?? 0)),
	].filter(Boolean),
}))

const eventMarkers = {
	id: "eventMarkers",
	afterDatasetsDraw(chart: any) {
		const { ctx, chartArea, scales } = chart
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
		legend: { position: "bottom", labels: { boxWidth: 12, boxHeight: 12 } },
		tooltip: {
			backgroundColor: "#fff",
			titleColor: "#111827",
			bodyColor: "#374151",
			borderColor: "#d1d5db",
			borderWidth: 1,
			padding: 10,
			callbacks: {
				title: (items: any[]) => (granularity.value === "week" ? ctrans("Week of") + " " : "") + useFormatTime(items[0].label, { formatTime: "PPP" }),
				label: (item: any) =>
					item.dataset.yAxisID === series.sales.axis
						? `${item.dataset.label}: ${locale.currencyFormat(props.data.currency, item.raw)}`
						: `${item.dataset.label}: ${item.raw.toLocaleString()}`,
				afterBody: (items: any[]) => (eventsByDate.value[items[0].label] ?? []).map((event) => `• ${eventStyle[event.type].label}: ${event.label}`),
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0, color: "#6b7280", callback: (value: number) => useFormatTime(labels.value[value], { formatTime: "d MMM" }) } },
		y1: { type: "linear", position: "left", display: visible.value.impressions, beginAtZero: true, ticks: { color: series.impressions.color, precision: 0 }, title: { display: true, text: series.impressions.label, color: series.impressions.color } },
		y2: { type: "linear", position: "right", display: visible.value.clicks, beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { color: series.clicks.color, precision: 0 }, title: { display: true, text: series.clicks.label, color: series.clicks.color } },
		y3: { type: "linear", position: "right", display: visible.value.sales, min: 0, grid: { drawOnChartArea: false }, ticks: { color: series.sales.color, callback: (value: number) => locale.currencyFormat(props.data.currency, value) }, title: { display: true, text: `${series.sales.label} (${props.data.currency})`, color: series.sales.color } },
	},
}))

const range = ref({ startDate: props.data.start_date, endDate: props.data.end_date })

const reload = debounce(() => {
	router.reload({ data: { startDate: range.value.startDate, endDate: range.value.endDate }, only: ["analytics"] })
}, 400)

const fromIsoDate = (iso: string): Date => {
	const [year, month, day] = iso.split("-").map(Number)
	return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const dateModel = (key: "startDate" | "endDate") =>
	computed<Date | null>({
		get: () => (range.value[key] ? fromIsoDate(range.value[key]) : null),
		set: (value) => {
			if (!value) return
			range.value = { ...range.value, [key]: toLocalIsoDate(value) }
			reload()
		},
	})
const rangeFrom = dateModel("startDate")
const rangeTo = dateModel("endDate")

const granularityOptions = [
	{ label: ctrans("Daily"), value: "day" },
	{ label: ctrans("Weekly"), value: "week" },
]

const datePickerPt = { pcInputText: { root: { class: "!w-40 !py-1.5 !text-sm" } } }
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent] [&_input:hover]:!border-gray-400"

const formatTotal = (key: keyof typeof series) =>
	key === "sales" ? locale.currencyFormat(props.data.currency, totals.value.sales) : totals.value[key].toLocaleString()
</script>

<template>
	<div class="px-4 py-6 space-y-6 sm:px-6">
		<div class="flex flex-wrap items-center gap-3 text-sm">
			<label class="flex items-center gap-2">
				<span class="text-gray-500">{{ ctrans("From") }}</span>
				<DatePicker v-model="rangeFrom" :maxDate="rangeTo ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('From')" />
			</label>
			<label class="flex items-center gap-2">
				<span class="text-gray-500">{{ ctrans("To") }}</span>
				<DatePicker v-model="rangeTo" :minDate="rangeFrom ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('To')" />
			</label>
			<SegmentedToggle v-model="granularity" :options="granularityOptions" :aria-label="ctrans('Granularity')" />
			<div class="ml-auto flex items-center gap-4 text-xs text-gray-500" data-chart-event-legend>
				<span v-for="type in chartEventTypes" :key="type" class="flex items-center gap-1">
					<span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: eventStyle[type].color }" />
					{{ eventStyle[type].label }}
				</span>
			</div>
		</div>

		<div class="rounded-lg bg-white p-6 shadow space-y-6">
			<div class="grid grid-cols-3 gap-4">
				<button
					v-for="(meta, key) in series"
					:key="key"
					type="button"
					class="series-toggle rounded-lg border p-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
					:class="visible[key] ? 'is-on text-white' : 'bg-white'"
					:style="{ '--series-color': meta.color, color: visible[key] ? undefined : meta.color }"
					:aria-pressed="visible[key]"
					@click="visible[key] = !visible[key]">
					<div class="text-xs">
						{{ meta.label }}
						<FontAwesomeIcon v-tooltip="meta.sourceDetail" :icon="faInfoCircle" class="ml-0.5 opacity-70" fixed-width aria-hidden="true" />
					</div>
					<div class="text-lg font-semibold">{{ formatTotal(key) }}</div>
					<div class="mt-1 text-[11px] opacity-80" data-card-source>{{ ctrans("Source") }}: {{ meta.source }}</div>
					<span class="sr-only">{{ meta.sourceDetail }}</span>
				</button>
			</div>

			<div class="relative h-96 w-full">
				<Chart type="line" class="h-full" :data="chartData" :options="chartOptions" :plugins="[eventMarkers]" />
			</div>
		</div>

		<RealUserSpeed v-if="real_user_speed !== null" :report="real_user_speed" />

		<div class="rounded-lg bg-white shadow">
			<div class="border-b px-6 py-3 text-sm font-semibold">{{ ctrans("Changes in this period") }}</div>
			<div v-if="!data.events?.length" class="px-6 py-6 text-sm text-gray-500">{{ ctrans("No changes to this page in the selected period") }}</div>
			<ul v-else class="divide-y">
				<li v-for="event in data.events" :key="event.datetime" class="flex items-center gap-3 px-6 py-2 text-sm">
					<FontAwesomeIcon :icon="eventStyle[event.type].icon" :style="{ color: eventStyle[event.type].color }" fixed-width />
					<span class="w-44 shrink-0 text-gray-500">{{ useFormatTime(event.datetime, { formatTime: "PPp" }) }}</span>
					<span class="flex-1">{{ event.label }}</span>
					<span class="text-gray-500">{{ event.user ?? ctrans("System") }}</span>
				</li>
			</ul>
		</div>
	</div>
</template>

<style scoped>
.series-toggle {
	border-color: var(--series-color);
	--tw-ring-color: var(--series-color);
}
.series-toggle:hover {
	background-color: color-mix(in srgb, var(--series-color) 8%, white);
	box-shadow: 0 2px 6px color-mix(in srgb, var(--series-color) 25%, transparent);
}
.series-toggle:active {
	background-color: color-mix(in srgb, var(--series-color) 16%, white);
	transform: scale(0.99);
}
.series-toggle.is-on {
	background-color: var(--series-color);
}
.series-toggle.is-on:hover {
	background-color: color-mix(in srgb, var(--series-color) 88%, black);
}
.series-toggle.is-on:active {
	background-color: color-mix(in srgb, var(--series-color) 76%, black);
}
</style>
