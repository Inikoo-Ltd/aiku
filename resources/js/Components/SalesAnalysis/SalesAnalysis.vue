<script setup lang="ts">
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import { Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import {
	faTag,
	faPercent,
	faPen,
	faToggleOn,
	faSparkles,
	faRocketLaunch,
	faBoxOpen,
	faMoneyBillWave,
	faShoppingCart,
	faExclamationTriangle,
	faChevronDown,
	faChevronRight,
	faUsers,
	faBuilding,
	faStore,
	faSpinnerThird,
} from "@fal"

type EventType = "price" | "offer" | "content" | "status" | "launch" | "publish"
type Cause = "no_order" | "ordered_after" | "ordered_too_late" | "supplier_late" | "restocked_directly" | "discontinued" | "unknown"

interface StockOut {
	org_stock_id: number
	code: string
	organisation: string
	organisation_id: number
	organisation_slug: string
	started_on: string
	back_in_on: string | null
	days: number
	approximate: boolean
	cause: Cause
	order: { kind: "purchase_order" | "stock_delivery"; reference: string; slug: string; supplier: string | null; ordered_on: string; expected_on: string | null; expected_is_estimate: boolean } | null
	days_to_order: number | null
	lost_sales: number
	websites: number
}

interface ChangeEvent {
	datetime: string
	date: string
	type: EventType
	field: string
	subjects: string[]
	old: string | null
	new: string | null
	changes: number
	shops: string[]
	details: Array<{ subject: string; shop: string | null; old: string | null; new: string | null }>
	user: string | null
}

interface Totals {
	sales: number
	orders: number
	invoices: number
	refunds: number
	stock_outs: number
	stock_out_days: number
	lost_sales: number
	stock_outs_no_order: number
	customers: number
	registrations?: number
	out_of_stock_percentage?: number
}

const props = defineProps<{
	data: {
		include_partners?: boolean
		filters: {
			organisations: Array<{ slug: string; code: string; name: string }>
			shops: Array<{ slug: string; code: string; name: string; state: string; organisation_slug: string }>
			selected_organisations: string[]
			selected_shops: string[]
		}
		period: { from: string; to: string }
		compare_period: { from: string; to: string }
		frequency: "daily" | "weekly" | "monthly"
		currency: string
		sales: Array<{ date: string; sales: number; orders: number }>
		compare_sales: Array<{ date: string; sales: number; orders: number }>
		totals: { current: Totals; previous: Totals }
		shops: Array<{ shop_id: number; shop_code: string; shop_name: string; shop_state: string; organisation_id: number; node_state: string | null; sales: number; previous_sales: number; orders: number; previous_orders: number; stock_out_days: number }>
		breakdown_label: string | null
		breakdown: Array<{ id: number; code: string; name: string; slug: string | null; status: boolean; is_for_sale: boolean; created_at: string | null; discontinued_at: string | null; sales: number; previous_sales: number; websites: number; websites_out_of_stock: number; stock_outs: number; stock_out_days: number; lost_sales: number }>
		stock_outs: StockOut[]
		skos: number
		stock_level?: "organisation"
		stock_series?: Array<{ date: string; out_of_stock: number }>
		traffic: Array<{ date: string; visitors: number; page_views: number; add_to_baskets: number }>
		events: ChangeEvent[]
	}
	breakdownRoute?: (row: { id: number; slug: string | null }) => string | null
}>()

const moneyFormatter = computed(() => new Intl.NumberFormat(undefined, { style: "currency", currency: props.data.currency, maximumFractionDigits: 0 }))
const money = (value: number) => moneyFormatter.value.format(Math.round(value))
const formatDate = (date: string | null) => (date ? useFormatTime(date, { formatTime: "PP" }) : "")
const change = (current: number, previous: number) => (previous ? ((current - previous) / previous) * 100 : null)
const formatChange = (value: number | null) => (value === null ? "—" : `${value > 0 ? "+" : ""}${value.toFixed(0)}%`)
const changeClass = (value: number) => (value > 0 ? "text-green-600" : value < 0 ? "text-red-600" : "text-gray-500")

const eventStyle: Record<EventType, { label: string; color: string; icon: any }> = {
	price: { label: ctrans("Price"), color: "#DB4437", icon: faTag },
	offer: { label: ctrans("Offers"), color: "#F4B400", icon: faPercent },
	content: { label: ctrans("Content"), color: "#4285F4", icon: faPen },
	status: { label: ctrans("Status"), color: "#6B7280", icon: faToggleOn },
	launch: { label: ctrans("New products"), color: "#0F9D58", icon: faSparkles },
	publish: { label: ctrans("Web page"), color: "#8E24AA", icon: faRocketLaunch },
}

const causeStyle: Record<Cause, { label: string; class: string; explanation: string }> = {
	no_order: { label: ctrans("No order placed"), class: "bg-red-100 text-red-700", explanation: ctrans("Nothing was on order when it ran out, and nothing was ordered by the end of the period") },
	ordered_after: { label: ctrans("Ordered after running out"), class: "bg-red-100 text-red-700", explanation: ctrans("No order was placed until after it had run out") },
	ordered_too_late: { label: ctrans("Ordered too late"), class: "bg-orange-100 text-orange-700", explanation: ctrans("It was on order when it ran out, but the order was placed too late to arrive in time") },
	supplier_late: { label: ctrans("Supplier late"), class: "bg-yellow-100 text-yellow-800", explanation: ctrans("It was ordered in time, but the delivery had not arrived by the date expected") },
	restocked_directly: { label: ctrans("Restocked without an order"), class: "bg-gray-100 text-gray-700", explanation: ctrans("Restocked without a purchase order or stock delivery, for example made in house") },
	discontinued: { label: ctrans("Discontinued"), class: "bg-gray-100 text-gray-500", explanation: ctrans("Discontinued and sold out, not counted as a stock out") },
	unknown: { label: ctrans("No purchase records"), class: "bg-gray-100 text-gray-500", explanation: ctrans("Purchase orders before October 2016 are not in aiku") },
}

const range = ref({ ...props.data.period })
const compareRange = ref({ ...props.data.compare_period })
const selectedOrganisations = ref<string[]>([...props.data.filters.selected_organisations])
const selectedShops = ref<string[]>([...props.data.filters.selected_shops])
const isShopMenuOpen = ref(false)
const includePartners = ref(!!props.data.include_partners)
const isLoading = ref(false)

const reload = () => {
	router.reload({
		onStart: () => (isLoading.value = true),
		onFinish: () => (isLoading.value = false),
		data: {
			from: range.value.from,
			to: range.value.to,
			compareFrom: compareRange.value.from,
			compareTo: compareRange.value.to,
			organisations: selectedOrganisations.value.join(",") || undefined,
			shops: selectedShops.value.join(",") || undefined,
			partners: includePartners.value ? 1 : undefined,
		},
		only: ["sales_analysis"],
	})
}

const availableShops = computed(() =>
	props.data.filters.shops.filter((shop) => !selectedOrganisations.value.length || selectedOrganisations.value.includes(shop.organisation_slug))
)
const toggleOrganisation = (slug: string | null) => {
	selectedOrganisations.value = slug === null ? [] : selectedOrganisations.value.includes(slug) ? selectedOrganisations.value.filter((selected) => selected !== slug) : [...selectedOrganisations.value, slug]
	selectedShops.value = selectedShops.value.filter((shopSlug) => availableShops.value.some((shop) => shop.slug === shopSlug))
	reload()
}
const toggleShop = (slug: string | null) => {
	selectedShops.value = slug === null ? [] : selectedShops.value.includes(slug) ? selectedShops.value.filter((selected) => selected !== slug) : [...selectedShops.value, slug]
	reload()
}
const shopCapsuleLabel = computed(() => {
	if (!selectedShops.value.length) return ctrans("All websites")
	if (selectedShops.value.length === 1) return props.data.filters.shops.find((shop) => shop.slug === selectedShops.value[0])?.code ?? selectedShops.value[0]
	return `${selectedShops.value.length} ${ctrans("websites")}`
})

const isoDate = (date: Date) => date.toISOString().slice(0, 10)
const shiftYears = (date: string, years: number) => {
	const shifted = new Date(date)
	shifted.setFullYear(shifted.getFullYear() - years)
	return isoDate(shifted)
}
const yesterday = () => {
	const date = new Date()
	date.setDate(date.getDate() - 1)
	return date
}
const presets = [
	{ label: ctrans("Last 12 months vs year before"), apply: () => ({ to: isoDate(yesterday()), years: 1, months: 12 }) },
	{ label: ctrans("Last 12 months vs 2 years ago"), apply: () => ({ to: isoDate(yesterday()), years: 2, months: 12 }) },
	{ label: ctrans("Last 3 months vs same months last year"), apply: () => ({ to: isoDate(yesterday()), years: 1, months: 3 }) },
	{ label: ctrans("Last 5 years vs 5 years before"), apply: () => ({ to: isoDate(yesterday()), years: 5, months: 60 }) },
]
const applyPreset = (preset: (typeof presets)[number] | undefined) => {
	if (!preset) return
	const { to, years, months } = preset.apply()
	const from = new Date(to)
	from.setMonth(from.getMonth() - months)
	from.setDate(from.getDate() + 1)
	range.value = { from: isoDate(from), to }
	compareRange.value = { from: shiftYears(isoDate(from), years), to: shiftYears(to, years) }
	reload()
}

const current = computed(() => props.data.totals.current)
const previous = computed(() => props.data.totals.previous)

const isOrganisationStock = computed(() => props.data.stock_level === "organisation")
const tiles = computed(() => [
	{ key: "sales", icon: faMoneyBillWave, label: ctrans("Sales"), value: money(current.value.sales), previous: money(previous.value.sales), change: change(current.value.sales, previous.value.sales), good: "up" },
	{ key: "orders", icon: faShoppingCart, label: ctrans("Orders"), value: current.value.orders.toLocaleString(), previous: previous.value.orders.toLocaleString(), change: change(current.value.orders, previous.value.orders), good: "up" },
	{ key: "customers", icon: faUsers, label: ctrans("Customers"), value: current.value.customers.toLocaleString(), previous: previous.value.customers.toLocaleString(), change: change(current.value.customers, previous.value.customers), good: "up" },
	...(isOrganisationStock.value
		? [
				{ key: "out_of_stock", icon: faBoxOpen, label: ctrans("SKOs out of stock"), value: `${current.value.out_of_stock_percentage ?? 0}%`, previous: `${previous.value.out_of_stock_percentage ?? 0}%`, change: change(current.value.out_of_stock_percentage ?? 0, previous.value.out_of_stock_percentage ?? 0), good: "down" },
				{ key: "registrations", icon: faUsers, label: ctrans("New registrations"), value: (current.value.registrations ?? 0).toLocaleString(), previous: (previous.value.registrations ?? 0).toLocaleString(), change: change(current.value.registrations ?? 0, previous.value.registrations ?? 0), good: "up" },
			]
		: stockTiles.value),
])
const stockTiles = computed(() => [
	{ key: "stock_out_days", icon: faBoxOpen, label: ctrans("Days out of stock"), value: `${current.value.stock_out_days.toLocaleString()} (${current.value.stock_outs}×)`, previous: `${previous.value.stock_out_days.toLocaleString()} (${previous.value.stock_outs}×)`, change: change(current.value.stock_out_days, previous.value.stock_out_days), good: "down" },
	{ key: "lost_sales", icon: faExclamationTriangle, label: ctrans("Lost to stock outs"), value: money(current.value.lost_sales), previous: money(previous.value.lost_sales), change: change(current.value.lost_sales, previous.value.lost_sales), good: "down" },
	{ key: "no_order", icon: faExclamationTriangle, label: ctrans("Ran out, nothing ordered"), value: current.value.stock_outs_no_order.toLocaleString(), previous: previous.value.stock_outs_no_order.toLocaleString(), change: change(current.value.stock_outs_no_order, previous.value.stock_outs_no_order), good: "down" },
])

const tileChangeClass = (tile: (typeof tiles.value)[number]) =>
	tile.change === null || tile.change === 0 ? "text-gray-500" : (tile.change > 0) === (tile.good === "up") ? "text-green-600" : "text-red-600"

const labels = computed(() => props.data.sales.map((row) => row.date))

const bucketIndex = (date: string) => {
	let index = -1
	for (let position = 0; position < labels.value.length && labels.value[position] <= date; position++) index = position
	return index
}

const markers = ref<Record<EventType, boolean>>({ price: false, offer: false, content: false, status: false, launch: false, publish: false })

const stockOutsPerBucket = computed(() => {
	if (props.data.stock_series) {
		const byDate = Object.fromEntries(props.data.stock_series.map((row) => [row.date, row.out_of_stock]))
		return labels.value.map((date) => byDate[date] ?? 0)
	}
	const counts = labels.value.map(() => 0)
	const counted = props.data.stock_outs.filter((stockOut) => stockOut.cause !== "discontinued")
	labels.value.forEach((bucketStart, index) => {
		const bucketEnd = labels.value[index + 1] ?? props.data.period.to
		counts[index] = counted.filter((stockOut) => stockOut.started_on < bucketEnd && (stockOut.back_in_on ?? "9999-12-31") > bucketStart).length
	})
	return counts
})

const eventsByBucket = computed(() => {
	const grouped: Record<number, ChangeEvent[]> = {}
	for (const event of props.data.events) {
		const index = bucketIndex(event.date)
		if (index >= 0) (grouped[index] ??= []).push(event)
	}
	return grouped
})

const trafficByDate = computed(() => Object.fromEntries(props.data.traffic.map((row) => [row.date, row])))

const pointRadius = computed(() => (labels.value.length > 40 ? 0 : 2))
const hasVisitors = computed(() => props.data.traffic.some((row) => row.visitors > 0))

const STOCK_OUT_VIEW_KEY = "masterFamilySalesAnalysis.stockOutView"
const readStockOutView = (): "line" | "background" => {
	try {
		return localStorage.getItem(STOCK_OUT_VIEW_KEY) === "background" ? "background" : "line"
	} catch {
		return "line"
	}
}
const stockOutView = ref<"line" | "background">(readStockOutView())
const setStockOutView = (view: "line" | "background") => {
	stockOutView.value = view
	try {
		localStorage.setItem(STOCK_OUT_VIEW_KEY, view)
	} catch {
		/* storage unavailable */
	}
}
const stockOutShare = (count: number) => Math.min(1, count / Math.max(1, props.data.skos, count))
const stockOutText = (count: number) => `${ctrans("Out of stock")}: ${count} ${ctrans("of")} ${Math.max(props.data.skos, count)} SKOs`
const line = (label: string, color: string, data: Array<number | null>, yAxisID: string, extra: object = {}) => ({
	label,
	data,
	borderColor: color,
	backgroundColor: color,
	tension: 0,
	borderWidth: 1.5,
	pointRadius: pointRadius.value,
	yAxisID,
	...extra,
})

const chartData = computed(() => ({
	labels: labels.value,
	datasets: [
		line(ctrans("Sales"), "#1f845a", props.data.sales.map((row) => row.sales), "sales"),
		line(ctrans("Compared period"), "#9ca3af", labels.value.map((_, index) => props.data.compare_sales[index]?.sales ?? null), "sales", { borderDash: [4, 3] }),
		...(stockOutView.value === "line" ? [line(ctrans("Products out of stock"), "#dc2626", stockOutsPerBucket.value, "stock", { stepped: "middle" })] : []),
		...(hasVisitors.value ? [line(ctrans("Visitors"), "#3b82f6", labels.value.map((date) => trafficByDate.value[date]?.visitors ?? null), "visitors")] : []),
	],
}))

const markerPlugin = {
	id: "salesAnalysisMarkers",
	beforeDatasetsDraw(chart: any) {
		if (stockOutView.value !== "background") return
		const { ctx, chartArea, scales } = chart
		const step = labels.value.length > 1 ? scales.x.getPixelForValue(1) - scales.x.getPixelForValue(0) : chartArea.width
		stockOutsPerBucket.value.forEach((count, index) => {
			if (!count) return
			const x = scales.x.getPixelForValue(index)
			ctx.save()
			ctx.fillStyle = `rgba(220, 38, 38, ${0.04 + 0.4 * stockOutShare(count)})`
			ctx.fillRect(Math.max(chartArea.left, x - step / 2), chartArea.top, step, chartArea.bottom - chartArea.top)
			ctx.restore()
		})
	},
	afterDatasetsDraw(chart: any) {
		const { ctx, chartArea, scales } = chart
		for (const [index, events] of Object.entries(eventsByBucket.value)) {
			const x = scales.x.getPixelForValue(Number(index))
			;[...new Set(events.map((event) => event.type))].forEach((type, position) => {
				ctx.save()
				if (markers.value[type]) {
					ctx.strokeStyle = eventStyle[type].color
					ctx.lineWidth = 1.5
					ctx.setLineDash([4, 3])
					ctx.beginPath()
					ctx.moveTo(x, chartArea.top)
					ctx.lineTo(x, chartArea.bottom)
					ctx.stroke()
				}
				ctx.fillStyle = eventStyle[type].color
				ctx.beginPath()
				ctx.arc(x, chartArea.top + 4 + position * 8, 3, 0, Math.PI * 2)
				ctx.fill()
				ctx.restore()
			})
		}
	},
}

const fieldLabels: Record<string, string> = {
	is_for_sale: ctrans("for sale"),
	status: ctrans("active"),
	state: ctrans("state"),
	description_extra: ctrans("extra description"),
	description_title: ctrans("description title"),
	gold_reward_percentage_off: ctrans("Gold reward % off"),
	gold_reward_min_quantity: ctrans("Gold reward min quantity"),
	gold_reward_state: ctrans("Gold reward"),
}
const fieldLabel = (field: string) => fieldLabels[field] ?? field.replace(/_/g, " ")

const eventLabel = (event: ChangeEvent) => {
	const subjects = event.subjects.length > 3 ? `${event.subjects.slice(0, 3).join(", ")} +${event.subjects.length - 3}` : event.subjects.join(", ")
	const values = event.old !== null || event.new !== null ? `${event.old ?? "—"} → ${event.new ?? "—"}` : ""
	switch (event.field) {
		case "launch":
			return `${ctrans("New")}: ${subjects}`
		case "publish":
			return `${ctrans("Web page published")}${subjects ? `: ${subjects}` : ""}`
		case "offer_started":
			return `${ctrans("Offer started")}: ${subjects}`
		case "offer_ended":
			return `${ctrans("Offer ended")}: ${subjects}`
		default:
			return event.subjects.length > 1 ? `${subjects} · ${fieldLabel(event.field)}` : `${subjects} · ${fieldLabel(event.field)}: ${values}`
	}
}
const shopsLabel = (event: ChangeEvent) =>
	event.shops.length === 0 ? ctrans("All websites") : event.shops.length > 3 ? `${event.shops.length} ${ctrans("websites")}` : event.shops.join(", ")

const chartOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		salesAnalysisMarkers: { markers: { ...markers.value }, stockOutView: stockOutView.value },
		legend: { position: "bottom", labels: { boxWidth: 12, boxHeight: 12 } },
		tooltip: {
			callbacks: {
				title: (items: any[]) => (props.data.frequency === "weekly" ? ctrans("Week of") + " " : "") + useFormatTime(items[0].label, { formatTime: props.data.frequency === "monthly" ? "MMM yyyy" : "PP" }),
				label: (item: any) =>
					item.dataset.yAxisID === "sales"
						? `${item.dataset.label}: ${money(item.raw ?? 0)}`
						: item.dataset.yAxisID === "stock"
							? stockOutText(item.raw ?? 0)
							: `${item.dataset.label}: ${(item.raw ?? 0).toLocaleString()}`,
				afterBody: (items: any[]) => {
					const outOfStock = stockOutsPerBucket.value[items[0].dataIndex]
					const events = eventsByBucket.value[items[0].dataIndex] ?? []
					const lines = stockOutView.value === "background" && outOfStock ? [stockOutText(outOfStock)] : []
					lines.push(...events.slice(0, 8).map((event) => `• ${eventLabel(event)} (${shopsLabel(event)})`))
					if (events.length > 8) lines.push(`… +${events.length - 8}`)
					return lines
				},
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
		sales: { type: "linear", position: "left", beginAtZero: true, ticks: { callback: (value: number) => money(value) } },
		stock: { type: "linear", position: "right", display: stockOutView.value === "line", beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { precision: 0, color: "#dc2626" } },
		visitors: { type: "linear", position: "right", display: hasVisitors.value, beginAtZero: true, grid: { drawOnChartArea: false }, ticks: { precision: 0, color: "#3b82f6" } },
	},
}))

const totalChange = computed(() => current.value.sales - previous.value.sales)
const shareOfChange = (delta: number) => (totalChange.value ? `${Math.round((delta / Math.abs(totalChange.value)) * 100)}%` : "—")

const causeFilter = ref<Cause | null>(null)
const visibleStockOuts = computed(() => props.data.stock_outs.filter((stockOut) => !causeFilter.value || stockOut.cause === causeFilter.value))
const causeCounts = computed(() => {
	const counts: Partial<Record<Cause, number>> = {}
	props.data.stock_outs.forEach((stockOut) => (counts[stockOut.cause] = (counts[stockOut.cause] ?? 0) + 1))
	return counts
})

const eventTypeFilter = ref<EventType | null>(null)
const eventShopFilter = ref<string>("")
const eventShops = computed(() => [...new Set(props.data.events.flatMap((event) => event.shops))].sort())
const visibleEvents = computed(() =>
	props.data.events.filter((event) => (!eventTypeFilter.value || event.type === eventTypeFilter.value) && (!eventShopFilter.value || event.shops.includes(eventShopFilter.value)))
)
const expandedEvents = ref<Record<string, boolean>>({})

const orderRoute = (stockOut: StockOut) =>
	stockOut.order?.kind === "purchase_order"
		? route("grp.org.procurement.purchase_orders.show", [stockOut.organisation_slug, stockOut.order.slug])
		: stockOut.order
			? route("grp.org.procurement.stock_deliveries.show", [stockOut.organisation_slug, stockOut.order.slug])
			: null

const breakdownLink = (row: { id: number; slug: string | null }) => (row.id && props.breakdownRoute ? props.breakdownRoute(row) : null)
const hasManyShops = computed(() => props.data.filters.shops.length > 1)
</script>

<template>
	<div class="relative space-y-4 px-4 py-4 text-sm text-gray-700">
		<div class="flex flex-wrap items-center gap-2">
			<div v-if="data.filters.organisations.length > 1" class="flex items-center gap-1" data-organisation-capsules>
				<FontAwesomeIcon :icon="faBuilding" class="text-gray-400" fixed-width aria-hidden="true" />
				<button
					type="button"
					class="rounded-full border px-2.5 py-0.5 text-xs"
					:class="!selectedOrganisations.length ? 'border-gray-700 bg-gray-700 text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
					@click="toggleOrganisation(null)">
					{{ ctrans("All") }}
				</button>
				<button
					v-for="organisation in data.filters.organisations"
					:key="organisation.slug"
					type="button"
					v-tooltip="organisation.name"
					class="rounded-full border px-2.5 py-0.5 text-xs"
					:class="selectedOrganisations.includes(organisation.slug) ? 'border-gray-700 bg-gray-700 text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
					@click="toggleOrganisation(organisation.slug)">
					{{ organisation.code }}
				</button>
			</div>

			<div v-if="hasManyShops" class="relative" data-shop-capsule>
				<button
					type="button"
					class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs"
					:class="selectedShops.length ? 'border-gray-700 bg-gray-700 text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
					@click="isShopMenuOpen = !isShopMenuOpen">
					<FontAwesomeIcon :icon="faStore" fixed-width aria-hidden="true" />
					{{ shopCapsuleLabel }}
					<FontAwesomeIcon :icon="faChevronDown" fixed-width aria-hidden="true" />
				</button>
				<div v-if="isShopMenuOpen" class="fixed inset-0 z-10" @click="isShopMenuOpen = false" />
				<div v-if="isShopMenuOpen" class="absolute left-0 z-20 mt-1 max-h-80 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
					<button type="button" class="w-full px-3 py-1.5 text-left text-xs hover:bg-gray-50" :class="!selectedShops.length ? 'font-medium text-gray-900' : 'text-gray-600'" @click="toggleShop(null)">
						{{ ctrans("All websites") }}
					</button>
					<label v-for="shop in availableShops" :key="shop.slug" class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-xs hover:bg-gray-50">
						<input type="checkbox" class="h-3.5 w-3.5 rounded border-gray-300" :checked="selectedShops.includes(shop.slug)" @change="toggleShop(shop.slug)" />
						<span class="font-medium">{{ shop.code }}</span>
						<span class="truncate text-gray-500">{{ shop.name }}</span>
					</label>
				</div>
			</div>

			<button
				type="button"
				class="rounded-full border px-2.5 py-0.5 text-xs"
				:class="includePartners ? 'border-gray-700 bg-gray-700 text-white' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
				v-tooltip="ctrans('Sales to our own organisations are left out unless this is on')"
				data-partners-toggle
				@click="includePartners = !includePartners; reload()">
				{{ includePartners ? ctrans("Including partners") : ctrans("Without partners") }}
			</button>

			<div class="ml-auto flex flex-wrap items-center gap-1.5 text-xs">
				<input v-model="range.from" type="date" :max="range.to" class="rounded border-gray-300 py-1 text-xs" @change="reload" />
				<span class="text-gray-400">–</span>
				<input v-model="range.to" type="date" :min="range.from" class="rounded border-gray-300 py-1 text-xs" @change="reload" />
				<span class="text-gray-500">{{ ctrans("vs") }}</span>
				<input v-model="compareRange.from" type="date" :max="compareRange.to" class="rounded border-gray-300 py-1 text-xs" @change="reload" />
				<span class="text-gray-400">–</span>
				<input v-model="compareRange.to" type="date" :min="compareRange.from" class="rounded border-gray-300 py-1 text-xs" @change="reload" />
				<FontAwesomeIcon v-if="isLoading" :icon="faSpinnerThird" spin class="text-indigo-500" fixed-width :aria-label="ctrans('Loading')" />
				<select class="rounded border-gray-300 py-1 text-xs text-gray-600" @change="applyPreset(presets[Number(($event.target as HTMLSelectElement).value)]); ($event.target as HTMLSelectElement).value = ''">
					<option value="">{{ ctrans("Quick periods") }}</option>
					<option v-for="(preset, index) in presets" :key="preset.label" :value="index">{{ preset.label }}</option>
				</select>
			</div>
		</div>

		<div v-if="isLoading" class="absolute inset-x-0 top-28 z-10 flex justify-center">
			<div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-600 shadow">
				<FontAwesomeIcon :icon="faSpinnerThird" spin class="text-indigo-500" fixed-width aria-hidden="true" />
				{{ ctrans("Loading") }}…
			</div>
		</div>
		<div class="space-y-4 transition-opacity" :class="isLoading ? 'pointer-events-none opacity-40' : ''" :aria-busy="isLoading">
		<div class="flex flex-wrap gap-2">
			<div
				v-for="tile in tiles"
				:key="tile.key"
				v-tooltip="`${tile.label} · ${ctrans('was')} ${tile.previous}`"
				class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm tabular-nums">
				<FontAwesomeIcon :icon="tile.icon" class="text-gray-400" fixed-width aria-hidden="true" />
				<span class="text-xs text-gray-500">{{ tile.label }}</span>
				<span class="font-semibold text-gray-900">{{ tile.value }}</span>
				<span class="text-xs" :class="tileChangeClass(tile)">{{ formatChange(tile.change) }}</span>
			</div>
		</div>

		<div class="rounded-lg border border-gray-200 bg-white p-4">
			<div class="relative h-72 w-full">
				<Chart type="line" class="h-full" :data="chartData" :options="chartOptions" :plugins="[markerPlugin]" />
			</div>
			<div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
				<span>{{ ctrans("Out of stock") }}:</span>
				<div class="mr-3 flex overflow-hidden rounded border border-gray-300" data-stock-out-view>
					<button
						v-for="view in (['line', 'background'] as const)"
						:key="view"
						type="button"
						class="px-2 py-0.5"
						:class="stockOutView === view ? 'bg-gray-700 text-white' : 'text-gray-600 hover:bg-gray-50'"
						@click="setStockOutView(view)">
						{{ view === "line" ? ctrans("Line") : ctrans("Background") }}
					</button>
				</div>
				<span v-tooltip="ctrans('Dots at the top mark every change; switch a type on to draw its changes as lines through the chart')">{{ ctrans("Lines") }}:</span>
				<button
					v-for="(style, type) in eventStyle"
					:key="type"
					type="button"
					class="flex items-center gap-1 rounded-full border px-2 py-0.5"
					:class="markers[type] ? 'border-gray-500 bg-gray-50 text-gray-800' : 'border-gray-200 text-gray-500'"
					@click="markers[type] = !markers[type]">
					<span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: style.color }" />
					{{ style.label }}
				</button>
				<span v-if="!hasVisitors" class="ml-auto">{{ ctrans("Visitors are recorded from January 2026.") }}</span>
			</div>
		</div>

		<div v-if="hasManyShops || data.breakdown.length" class="grid gap-6" :class="hasManyShops && data.breakdown.length ? 'xl:grid-cols-2' : ''">
			<div v-if="hasManyShops" class="rounded-lg border border-gray-200 bg-white">
				<div class="border-b px-4 py-2 font-semibold">{{ ctrans("Websites") }}</div>
				<table class="w-full text-xs tabular-nums">
					<thead class="text-gray-600">
						<tr class="border-b">
							<th class="px-4 py-2 text-left font-normal">{{ ctrans("Website") }}</th>
							<th class="px-2 py-2 text-right font-normal">{{ ctrans("Before") }}</th>
							<th class="px-2 py-2 text-right font-normal">{{ ctrans("Now") }}</th>
							<th class="px-2 py-2 text-right font-normal">{{ ctrans("Change") }}</th>
							<th class="px-2 py-2 text-right font-normal">{{ ctrans("Share of change") }}</th>
							<th class="px-4 py-2 text-right font-normal">{{ ctrans("Days out of stock") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y">
						<tr v-for="shop in data.shops" :key="shop.shop_id">
							<td class="px-4 py-1.5">
								<span class="font-medium">{{ shop.shop_code }}</span>
								<span class="ml-1 text-gray-500">{{ shop.shop_name }}</span>
								<span v-if="shop.shop_state !== 'open' || (shop.node_state && shop.node_state !== 'active')" class="ml-1 text-gray-400">({{ shop.shop_state !== "open" ? shop.shop_state : shop.node_state }})</span>
							</td>
							<td class="px-2 py-1.5 text-right">{{ money(shop.previous_sales) }}</td>
							<td class="px-2 py-1.5 text-right">{{ money(shop.sales) }}</td>
							<td class="px-2 py-1.5 text-right" :class="changeClass(shop.sales - shop.previous_sales)">
								{{ formatChange(change(shop.sales, shop.previous_sales)) }}
							</td>
							<td class="px-2 py-1.5 text-right" :class="changeClass(shop.sales - shop.previous_sales)">{{ shareOfChange(shop.sales - shop.previous_sales) }}</td>
							<td class="px-4 py-1.5 text-right" :class="shop.stock_out_days ? 'text-red-600' : 'text-gray-400'">{{ shop.stock_out_days || "—" }}</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div v-if="data.breakdown.length" class="rounded-lg border border-gray-200 bg-white">
				<div class="border-b px-4 py-2 font-semibold">{{ data.breakdown_label }}</div>
				<div class="max-h-[32rem] overflow-y-auto">
					<table class="w-full text-xs tabular-nums">
						<thead class="sticky top-0 bg-white text-gray-600">
							<tr class="border-b">
								<th class="px-4 py-2 text-left font-normal">{{ data.breakdown_label }}</th>
								<th class="px-2 py-2 text-right font-normal">{{ ctrans("Before") }}</th>
								<th class="px-2 py-2 text-right font-normal">{{ ctrans("Now") }}</th>
								<th class="px-2 py-2 text-right font-normal">{{ ctrans("Change") }}</th>
								<th class="px-2 py-2 text-right font-normal">{{ ctrans("Websites") }}</th>
								<th class="px-4 py-2 text-right font-normal">{{ ctrans("Out of stock") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y">
							<tr v-for="product in data.breakdown" :key="product.id">
								<td class="px-4 py-1.5">
									<Link v-if="breakdownLink(product)" :href="breakdownLink(product)" class="font-medium hover:underline">{{ product.code }}</Link>
									<span v-else class="font-medium">{{ product.code }}</span>
									<span v-if="product.discontinued_at || !product.status" class="ml-1 rounded bg-gray-100 px-1 text-gray-500">{{ ctrans("discontinued") }}</span>
									<span v-else-if="!product.is_for_sale" class="ml-1 rounded bg-gray-100 px-1 text-gray-500">{{ ctrans("not for sale") }}</span>
									<span v-if="product.created_at && product.created_at >= data.period.from" class="ml-1 rounded bg-green-50 px-1 text-green-700">{{ ctrans("new") }}</span>
									<div class="truncate text-gray-500" :title="product.name">{{ product.name }}</div>
								</td>
								<td class="px-2 py-1.5 text-right">{{ money(product.previous_sales) }}</td>
								<td class="px-2 py-1.5 text-right">{{ money(product.sales) }}</td>
								<td class="px-2 py-1.5 text-right" :class="changeClass(product.sales - product.previous_sales)">
									{{ formatChange(change(product.sales, product.previous_sales)) }}
								</td>
								<td class="px-2 py-1.5 text-right">{{ product.websites }}</td>
								<td class="px-4 py-1.5 text-right">
									<span v-if="product.stock_outs" class="text-red-600" v-tooltip="`${product.stock_outs} ${ctrans('stock outs')}, ~${money(product.lost_sales)} ${ctrans('lost')}`">
										{{ product.stock_out_days }} {{ ctrans("days") }}
									</span>
									<span v-else class="text-gray-400">—</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<p v-if="isOrganisationStock" class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-xs text-gray-500">
			{{ ctrans("For a whole shop, stock is shown as the share of SKOs out of stock in the warehouses that supply it, which includes SKOs that are no longer stocked. Open a department, family or product to see each stock out and why it happened.") }}
		</p>
		<div v-else class="rounded-lg border border-gray-200 bg-white">
			<div class="flex flex-wrap items-center gap-2 border-b px-4 py-2">
				<span class="mr-2 font-semibold">{{ ctrans("Stock outs") }}</span>
				<button
					type="button"
					class="rounded-full border px-2 py-0.5 text-xs"
					:class="causeFilter === null ? 'border-gray-400 text-gray-700' : 'border-gray-200 text-gray-500'"
					@click="causeFilter = null">
					{{ ctrans("All") }} {{ data.stock_outs.length }}
				</button>
				<button
					v-for="(count, cause) in causeCounts"
					:key="cause"
					type="button"
					class="rounded-full px-2 py-0.5 text-xs"
					:class="[causeStyle[cause].class, causeFilter === cause ? 'ring-1 ring-gray-500' : '']"
					v-tooltip="causeStyle[cause].explanation"
					@click="causeFilter = causeFilter === cause ? null : cause">
					{{ causeStyle[cause].label }} {{ count }}
				</button>
			</div>
			<div v-if="!visibleStockOuts.length" class="px-4 py-6 text-gray-500">{{ ctrans("No stock outs in this period") }}</div>
			<div v-else class="max-h-[32rem] overflow-y-auto">
				<table class="w-full text-xs tabular-nums">
					<thead class="sticky top-0 bg-white text-gray-600">
						<tr class="border-b">
							<th class="px-4 py-2 text-left font-normal">{{ ctrans("SKO") }}</th>
							<th class="px-2 py-2 text-left font-normal">{{ ctrans("Stock held by") }}</th>
							<th class="px-2 py-2 text-left font-normal">{{ ctrans("Ran out") }}</th>
							<th class="px-2 py-2 text-left font-normal">{{ ctrans("Back in stock") }}</th>
							<th class="px-2 py-2 text-right font-normal">{{ ctrans("Days") }}</th>
							<th class="px-2 py-2 text-left font-normal">{{ ctrans("Why") }}</th>
							<th class="px-2 py-2 text-left font-normal">{{ ctrans("Order") }}</th>
							<th class="px-2 py-2 text-right font-normal">{{ ctrans("Websites") }}</th>
							<th class="px-4 py-2 text-right font-normal">{{ ctrans("Estimated lost") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y">
						<tr v-for="stockOut in visibleStockOuts" :key="`${stockOut.org_stock_id}-${stockOut.started_on}`">
							<td class="px-4 py-1.5 font-medium">{{ stockOut.code }}</td>
							<td class="px-2 py-1.5">{{ stockOut.organisation }}</td>
							<td class="px-2 py-1.5">{{ formatDate(stockOut.started_on) }}</td>
							<td class="px-2 py-1.5">
								<span v-if="stockOut.back_in_on">{{ formatDate(stockOut.back_in_on) }}</span>
								<span v-else class="text-red-600">{{ ctrans("still out") }}</span>
							</td>
							<td class="px-2 py-1.5 text-right" v-tooltip="stockOut.approximate ? ctrans('Before August 2023 stock was only recorded at the end of each month, so the dates are approximate') : undefined">
								{{ stockOut.approximate ? "≈" : "" }}{{ stockOut.days }}
							</td>
							<td class="px-2 py-1.5">
								<span class="whitespace-nowrap rounded-full px-2 py-0.5" :class="causeStyle[stockOut.cause].class" v-tooltip="causeStyle[stockOut.cause].explanation">
									{{ causeStyle[stockOut.cause].label }}
								</span>
								<span v-if="stockOut.days_to_order !== null" class="ml-1 text-gray-500">{{ ctrans(":days days later", { days: String(stockOut.days_to_order) }) }}</span>
							</td>
							<td class="px-2 py-1.5">
								<template v-if="stockOut.order">
									<Link :href="orderRoute(stockOut)" class="hover:underline">{{ stockOut.order.reference }}</Link>
									<div class="text-gray-500">
										{{ stockOut.order.kind === "purchase_order" ? ctrans("ordered") : ctrans("dispatched") }} {{ formatDate(stockOut.order.ordered_on) }}
										<span v-if="stockOut.order.expected_on" v-tooltip="stockOut.order.expected_is_estimate ? ctrans('No expected date was recorded; assumed from a usual delivery time') : undefined">· {{ ctrans("expected") }} {{ stockOut.order.expected_is_estimate ? "~" : "" }}{{ formatDate(stockOut.order.expected_on) }}</span>
									</div>
								</template>
								<span v-else class="text-gray-400">—</span>
							</td>
							<td class="px-2 py-1.5 text-right">{{ stockOut.websites }}</td>
							<td class="px-4 py-1.5 text-right">{{ stockOut.lost_sales ? `~${money(stockOut.lost_sales)}` : "—" }}</td>
						</tr>
					</tbody>
				</table>
			</div>
			<p class="border-t px-4 py-2 text-xs text-gray-500">
				{{ ctrans("Estimated lost = this family's average daily sales of the product in the 180 days before it ran out, times the days out of stock (at most 90).") }}
			</p>
		</div>

		<div class="rounded-lg border border-gray-200 bg-white">
			<div class="flex flex-wrap items-center gap-2 border-b px-4 py-2">
				<span class="mr-2 font-semibold">{{ ctrans("Changes") }}</span>
				<button
					type="button"
					class="rounded-full border px-2 py-0.5 text-xs"
					:class="eventTypeFilter === null ? 'border-gray-400 text-gray-700' : 'border-gray-200 text-gray-500'"
					@click="eventTypeFilter = null">
					{{ ctrans("All") }}
				</button>
				<button
					v-for="(style, type) in eventStyle"
					:key="type"
					type="button"
					class="flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs"
					:class="eventTypeFilter === type ? 'border-gray-400 text-gray-700' : 'border-gray-200 text-gray-500'"
					@click="eventTypeFilter = eventTypeFilter === type ? null : type">
					<FontAwesomeIcon :icon="style.icon" :style="{ color: style.color }" fixed-width aria-hidden="true" />
					{{ style.label }}
				</button>
				<select v-model="eventShopFilter" class="ml-auto rounded border-gray-300 py-0.5 text-xs">
					<option value="">{{ ctrans("All websites") }}</option>
					<option v-for="shop in eventShops" :key="shop" :value="shop">{{ shop }}</option>
				</select>
			</div>
			<div v-if="!visibleEvents.length" class="px-4 py-6 text-gray-500">{{ ctrans("No changes in this period") }}</div>
			<ul v-else class="max-h-[32rem] divide-y overflow-y-auto">
				<li v-for="event in visibleEvents" :key="`${event.datetime}-${event.type}-${event.field}-${event.subjects.join()}`" class="px-4 py-1.5">
					<button type="button" class="flex w-full items-center gap-3 text-left" @click="expandedEvents[event.datetime + event.field] = !expandedEvents[event.datetime + event.field]">
						<FontAwesomeIcon :icon="eventStyle[event.type].icon" :style="{ color: eventStyle[event.type].color }" fixed-width aria-hidden="true" />
						<span class="w-40 shrink-0 text-xs text-gray-500">{{ useFormatTime(event.datetime, { formatTime: "PPp" }) }}</span>
						<span class="flex-1">{{ eventLabel(event) }}</span>
						<span class="text-xs text-gray-500">{{ shopsLabel(event) }}</span>
						<span class="w-40 truncate text-right text-xs text-gray-500">{{ event.user ?? ctrans("System") }}</span>
						<FontAwesomeIcon v-if="event.changes > 1" :icon="expandedEvents[event.datetime + event.field] ? faChevronDown : faChevronRight" class="text-gray-400" fixed-width aria-hidden="true" />
					</button>
					<ul v-if="event.changes > 1 && expandedEvents[event.datetime + event.field]" class="ml-9 mt-1 space-y-0.5 text-xs text-gray-600">
						<li v-for="(detail, index) in event.details" :key="index" class="tabular-nums">
							<span class="font-medium">{{ detail.subject }}</span>
							<span v-if="detail.shop" class="text-gray-500"> · {{ detail.shop }}</span>
							<span v-if="detail.old !== null || detail.new !== null">: {{ detail.old ?? "—" }} → {{ detail.new ?? "—" }}</span>
						</li>
						<li v-if="event.changes > event.details.length" class="text-gray-400">… +{{ event.changes - event.details.length }}</li>
					</ul>
				</li>
			</ul>
		</div>
		</div>
	</div>
</template>
