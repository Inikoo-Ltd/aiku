<script setup lang="ts">
import { getActiveLanguage } from "laravel-vue-i18n"
import { format } from "date-fns"
import { Deferred, Link } from "@inertiajs/vue3"
import { computed, inject, ref } from "vue"
import Chart from "primevue/chart"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import type { Valuation } from "@/Components/Procurement/AgentSalesValuation.vue"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { localesCode, useFormatTime } from "@/Composables/useFormatTime"

type Problem = "out" | "low" | "dead"

interface OrganisationRow {
	code: string
	name: string
	products: number
	selling: number
	sold: number
	this_year: number[]
	last_year: number[]
	month_this_year: number[]
	month_last_year: number[]
	problems: Record<Problem, number>
}

interface ProductRow {
	code: string
	name: string
	sold: number
	organisations: { code: string; sold: number; stock: number; problem: Problem | null }[]
}

interface Ranking {
	count: number
	rows: ProductRow[]
}

interface Feedback {
	customers: {
		rating: number | null
		reviews: number
		refund_percent: number | null
		recent: { code: string; rating: number; message: string; date: string }[]
	}
	purchasing: { title: string; body: string | null; severity: "info" | "warning" | "critical"; category: string; date: string }[]
	warehouse: {
		short_percent: number | null
		short_lines: number
		lines: number
		worst: { code: string; name: string; short_lines: number; lines: number }[]
	}
}

interface Sales {
	currency: string
	from: string
	year: number
	month: number
	days_in_month: number
	products: number
	selling: number
	organisations: OrganisationRow[]
	top_sellers: ProductRow[]
	out: Ranking
	low: Ranking
	dead: Ranking
}

const props = defineProps<{
	supplier?: { code: string; name: string } | null
	seller?: "agent" | "supplier"
	staff?: boolean
	links: Record<"all" | Problem, string> | null
	valuation: Valuation
	sales?: Sales
	feedback?: Feedback
	language?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

const COLORS = { thisYear: "#1f845a", lastYear: "#0ea5e9", behind: "#dc2626", forecastBand: "rgba(31, 132, 90, 0.15)" }
const problemClass: Record<Problem, string> = {
	out: "bg-red-100 text-red-700",
	low: "bg-amber-100 text-amber-700",
	dead: "bg-gray-200 text-gray-600",
}

const money = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.sales?.currency ?? "GBP", maximumFractionDigits: 0 }).format(Math.round(amount))
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.sales?.currency ?? "GBP", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const dateLocale = () => localesCode[props.language || getActiveLanguage()] ?? localesCode.en
const monthName = (month: number) => format(new Date(props.sales?.year ?? 2000, month - 1, 1), "LLL", { locale: dateLocale() })
const severityClass: Record<string, string> = {
	info: "bg-gray-100 text-gray-600",
	warning: "bg-amber-100 text-amber-700",
	critical: "bg-red-100 text-red-700",
}
const stars = (rating: number) => "★".repeat(rating) + "☆".repeat(5 - rating)
const percent = (value: number | null) => (value === null ? "—" : `${value.toFixed(1)}%`)
const signed = (percent: number) => `${percent > 0 ? "+" : ""}${percent.toFixed(1)}%`

const atCost = computed(() => props.valuation.measure !== "sales")
const atSupplierPrices = computed(() => props.valuation.measure === "supplier")

const view = ref<"month" | "year">("year")
const inMonth = computed(() => view.value === "month")
const viewOptions = computed(() => [
	{ label: ctrans("This month"), value: "month" },
	{ label: ctrans("Year to date"), value: "year" },
])
const periodName = (year: number) =>
	inMonth.value ? format(new Date(year, (props.sales?.month ?? 1) - 1, 1), "LLLL yyyy", { locale: dateLocale() }) : String(year)
const nowLabel = computed(() => periodName(props.sales?.year ?? 0))
const thenLabel = computed(() => periodName((props.sales?.year ?? 0) - 1))
const pointName = (index: number) =>
	inMonth.value ? format(new Date(props.sales?.year ?? 2000, (props.sales?.month ?? 1) - 1, index), "d LLL", { locale: dateLocale() }) : monthName(index)

const selected = ref("all")
const organisationOptions = computed(() => [
	{ label: ctrans("All"), value: "all" },
	...(props.sales?.organisations ?? []).map((organisation) => ({ label: organisation.name, value: organisation.code })),
])

const summed = (series: "this_year" | "last_year" | "month_this_year" | "month_last_year") => {
	const organisations = (props.sales?.organisations ?? []).filter((organisation) => selected.value === "all" || organisation.code === selected.value)

	return (organisations[0]?.[series] ?? []).map((_, index) => organisations.reduce((sum, organisation) => sum + (organisation[series][index] ?? 0), 0))
}
const thisYear = computed(() => summed(inMonth.value ? "month_this_year" : "this_year"))
const lastYear = computed(() => summed(inMonth.value ? "month_last_year" : "last_year"))
const monthRatios = computed(() => {
	const now = summed("this_year")
	const then = summed("last_year")

	return [...Array(Math.max(0, now.length - 1)).keys()].filter((index) => then[index] > 0).slice(-6).map((index) => now[index] / then[index])
})
const cumulative = (series: number[]) => series.reduce<number[]>((running, amount) => [...running, (running[running.length - 1] ?? 0) + amount], [])

const completeMonths = computed(() => Math.max(0, thisYear.value.length - 1))
const yearSoFar = computed(() => thisYear.value.reduce((sum, amount) => sum + amount, 0))
const completeThisYear = computed(() => thisYear.value.slice(0, completeMonths.value).reduce((sum, amount) => sum + amount, 0))
const completeLastYear = computed(() => lastYear.value.slice(0, completeMonths.value).reduce((sum, amount) => sum + amount, 0))
const versusLastYear = computed(() => (completeLastYear.value ? (completeThisYear.value / completeLastYear.value - 1) * 100 : null))

const forecast = computed(() => {
	const complete = completeMonths.value
	const compared = [...Array(complete).keys()].filter((index) => lastYear.value[index] > 0)
	const recent = inMonth.value ? compared : compared.slice(-3)
	const recentLastYear = recent.reduce((sum, index) => sum + lastYear.value[index], 0)
	if (!recentLastYear || complete >= lastYear.value.length) {
		return null
	}

	const trend = recent.reduce((sum, index) => sum + thisYear.value[index], 0) / recentLastYear
	const ratios = inMonth.value ? monthRatios.value : compared.slice(-6).map((index) => thisYear.value[index] / lastYear.value[index])
	const rates = { expected: trend, low: Math.min(trend, ...ratios), high: Math.max(trend, ...ratios) }
	const soFarThisMonth = thisYear.value[complete] ?? 0

	const monthly = (rate: number) => lastYear.value.map((amount, index) => (index < complete ? null : Math.max(index === complete ? soFarThisMonth : 0, amount * rate)))
	const running = (rate: number) => {
		let total = completeThisYear.value

		return monthly(rate).map((amount, index) => {
			if (amount === null) {
				return index === complete - 1 ? total : null
			}
			total += amount

			return total
		})
	}

	return {
		months: monthly(rates.expected),
		expected: running(rates.expected),
		low: running(rates.low),
		high: running(rates.high),
	}
})
const yearEnd = computed(() => (forecast.value ? { expected: forecast.value.expected.at(-1) ?? 0, low: forecast.value.low.at(-1) ?? 0, high: forecast.value.high.at(-1) ?? 0 } : null))
const lastYearTotal = computed(() => lastYear.value.reduce((sum, amount) => sum + amount, 0))

const monthRows = computed(() =>
	lastYear.value.map((amount, index) => {
		const isPartial = index === thisYear.value.length - 1
		const isForecast = index >= thisYear.value.length && !!forecast.value
		const sales = index < thisYear.value.length ? thisYear.value[index] : (forecast.value?.months[index] ?? null)

		return {
			month: index + 1,
			isPartial,
			isForecast,
			sales,
			lastYear: amount,
			change: sales !== null && !isPartial && amount ? (sales / amount - 1) * 100 : null,
		}
	})
)

const monthLabels = computed(() => lastYear.value.map((_, index) => pointName(index + 1)))
const forecastBands = (line: { expected: (number | null)[]; low: (number | null)[]; high: (number | null)[] }) => [
	{ key: "forecast", label: ctrans("Forecast"), data: line.expected, borderColor: COLORS.thisYear, backgroundColor: COLORS.thisYear, borderDash: [4, 3], tension: 0, borderWidth: 1.5, pointRadius: 0 },
	{ key: "band", label: "", data: line.high, borderColor: "transparent", backgroundColor: COLORS.forecastBand, fill: "+1", tension: 0, borderWidth: 0, pointRadius: 0 },
	{ key: "band_low", label: "", data: line.low, borderColor: "transparent", backgroundColor: COLORS.forecastBand, tension: 0, borderWidth: 0, pointRadius: 0 },
]
const withoutBands = (item: any) => item.parsed.y !== null && !String(item.dataset.key ?? "").startsWith("band")

const chartData = computed(() => ({
	labels: monthLabels.value,
	datasets: [
		{ label: nowLabel.value, data: cumulative(forecast.value ? thisYear.value.slice(0, completeMonths.value) : thisYear.value), borderColor: COLORS.thisYear, backgroundColor: COLORS.thisYear, tension: 0, borderWidth: 1.5, pointRadius: 2 },
		...(forecast.value ? forecastBands(forecast.value) : []),
		{ label: thenLabel.value, data: cumulative(lastYear.value), borderColor: COLORS.lastYear, backgroundColor: COLORS.lastYear, borderDash: [4, 3], tension: 0, borderWidth: 1.5, pointRadius: 0 },
	],
}))

const chartOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { display: false },
		tooltip: {
			filter: withoutBands,
			callbacks: {
				label: (item: any) =>
					item.dataset.key === "forecast" && forecast.value
						? ctrans(":label: :amount (likely :low to :high)", { label: item.dataset.label, amount: money(item.parsed.y), low: money(forecast.value.low[item.dataIndex] ?? 0), high: money(forecast.value.high[item.dataIndex] ?? 0) })
						: `${item.dataset.label}: ${money(item.parsed.y)}`,
			},
		},
	},
	scales: {
		x: { grid: { display: false }, ticks: { maxRotation: 0 } },
		y: { beginAtZero: true, afterFit: (axis: any) => { axis.width = 64 }, ticks: { callback: (value: number) => shortMoney(value) } },
	},
}))

const percentOfLastYear = (series: (number | null)[]) => {
	const lastYearRunning = cumulative(lastYear.value)

	return series.map((amount, index) => (amount !== null && lastYearRunning[index] ? Math.round((amount / lastYearRunning[index] - 1) * 1000) / 10 : null))
}
const aheadBehind = computed(() => percentOfLastYear(monthLabels.value.map((_, index) => (index < completeMonths.value ? cumulative(thisYear.value)[index] : null))))
const aheadBehindForecast = computed(() =>
	forecast.value ? { expected: percentOfLastYear(forecast.value.expected), low: percentOfLastYear(forecast.value.low), high: percentOfLastYear(forecast.value.high) } : null
)

const aheadBehindData = computed(() => ({
	labels: monthLabels.value,
	datasets: [
		...(aheadBehindForecast.value ? forecastBands(aheadBehindForecast.value) : []),
		{
			label: ctrans("vs :last_year", { last_year: thenLabel.value }),
			data: aheadBehind.value,
			borderColor: COLORS.thisYear,
			tension: 0,
			borderWidth: 1.5,
			pointRadius: 2,
			fill: { target: { value: 0 }, above: "rgba(31, 132, 90, 0.15)", below: "rgba(220, 38, 38, 0.15)" },
			segment: { borderColor: (context: any) => (context.p1.parsed.y < 0 ? COLORS.behind : COLORS.thisYear) },
			pointBackgroundColor: (context: any) => (context.parsed?.y < 0 ? COLORS.behind : COLORS.thisYear),
		},
	],
}))

const aheadBehindOptions = computed(() => {
	const values = [aheadBehind.value, ...Object.values(aheadBehindForecast.value ?? {})].flat().map((percent) => Math.abs(percent ?? 0))
	const reach = Math.max(2, 2 * Math.ceil(Math.max(0, ...values) * 0.6))

	return {
		responsive: true,
		maintainAspectRatio: false,
		interaction: { mode: "index", intersect: false },
		plugins: {
			legend: { display: false },
			tooltip: { filter: withoutBands, callbacks: { label: (item: any) => `${item.dataset.label}: ${signed(item.parsed.y)}` } },
		},
		scales: {
			x: { grid: { display: false }, ticks: { maxRotation: 0 } },
			y: { min: -reach, max: reach, afterFit: (axis: any) => { axis.width = 64 }, grid: { color: (context: any) => (context.tick.value === 0 ? "#64748b" : "#f1f5f9") }, ticks: { count: 5, callback: (value: number) => `${value > 0 ? "+" : ""}${Math.round(value * 10) / 10}%` } },
		},
	}
})

const totalSold = computed(() => (props.sales?.organisations ?? []).reduce((sum, organisation) => sum + organisation.sold, 0))
const topSold = computed(() => Math.max(1, ...(props.sales?.organisations ?? []).map((organisation) => organisation.sold)))
const problemTotal = (problem: Problem) => (props.sales?.organisations ?? []).reduce((sum, organisation) => sum + organisation.problems[problem], 0)

const rankings = computed(() =>
	props.sales
		? [
				{
				key: "top",
					title: ctrans("Best sellers"),
					hint: atSupplierPrices.value ? ctrans("Goods sold in the last 12 months at your prices, every organisation together") : atCost.value ? ctrans("Goods sold in the last 12 months at cost, every organisation together") : ctrans("Goods sold in the last 12 months at what customers paid, every organisation together"),
					empty: ctrans("Nothing sold in the period"),
					rows: props.sales.top_sellers,
					count: null,
					tone: "text-gray-700",
					figure: "sold" as const,
				},
				{
					key: "out",
					title: ctrans("Out of stock"),
					hint: ctrans("Nothing left to sell where shown; the best sellers there first"),
					empty: ctrans("No product is out of stock"),
					rows: props.sales.out.rows,
					count: props.sales.out.count,
					tone: "text-red-600",
					figure: "sold" as const,
				},
				{
					key: "low",
					title: ctrans("About to run out"),
					hint: ctrans("At the rate it sells, the stock will not last until a new order can arrive"),
					empty: ctrans("No product is about to run out"),
					rows: props.sales.low.rows,
					count: props.sales.low.count,
					tone: "text-amber-600",
					figure: "sold" as const,
				},
				{
					key: "dead",
					title: ctrans("In stock but not selling"),
					hint: atSupplierPrices.value ? ctrans("Stock sitting in the warehouse with no sales expected, at your prices; the largest first") : ctrans("Stock sitting in the warehouse with no sales expected, at what it cost; the largest first"),
					empty: ctrans("No product is sitting unsold"),
					rows: props.sales.dead.rows,
					count: props.sales.dead.count,
					tone: "text-gray-500",
					figure: "stock" as const,
				},
			]
		: []
)
</script>

<template>
	<div class="space-y-4">
		<Deferred data="sales">
			<template #fallback>
				<div class="space-y-4">
					<div class="h-96 animate-pulse rounded-lg bg-gray-100" />
					<div class="h-40 animate-pulse rounded-lg bg-gray-100" />
					<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
						<div v-for="card in 4" :key="card" class="h-64 animate-pulse rounded-lg bg-gray-100" />
					</div>
				</div>
			</template>

			<div v-if="sales" class="space-y-4">
				<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
					<div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-2">
						<h2 v-if="inMonth" class="text-sm font-semibold text-gray-600">{{ atSupplierPrices ? ctrans(":month goods sold at your prices, month to date", { month: nowLabel }) : atCost ? ctrans(":month goods sold at cost, month to date", { month: nowLabel }) : ctrans(":month goods sold wholesale, month to date", { month: nowLabel }) }}</h2>
						<h2 v-else class="text-sm font-semibold text-gray-600">{{ atSupplierPrices ? ctrans(":year goods sold at your prices, year to date", { year: String(sales.year) }) : atCost ? ctrans(":year goods sold at cost, year to date", { year: String(sales.year) }) : ctrans(":year goods sold wholesale, year to date", { year: String(sales.year) }) }}</h2>
						<span class="flex items-center gap-3 text-xs text-gray-500">
							<span class="flex items-center gap-1.5"><span class="w-4 border-t-2" :style="{ borderColor: COLORS.thisYear }" />{{ nowLabel }}</span>
							<span v-if="forecast" class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm" :style="{ backgroundColor: COLORS.forecastBand }" />{{ ctrans("Forecast") }}</span>
							<span class="flex items-center gap-1.5"><span class="w-4 border-t-2 border-dashed" :style="{ borderColor: COLORS.lastYear }" />{{ thenLabel }}</span>
						</span>
						<SegmentedToggle v-model="view" :options="viewOptions" :aria-label="ctrans('Period')" class="ml-auto" />
					</div>
					<p class="-mt-2 mb-4 text-xs text-gray-400">
						<template v-if="staff">{{ atCost ? ctrans("What each organisation sold of these products to its own customers, at the cost of the goods, in :currency", { currency: sales.currency }) : ctrans("What each organisation sold of these products to its own customers, at what they paid, in :currency", { currency: sales.currency }) }}</template>
						<template v-else>{{ atSupplierPrices ? ctrans("What each organisation sold of your products to its own customers, at your prices, in :currency", { currency: sales.currency }) : atCost ? ctrans("What each organisation sold of your products to its own customers, at the cost of the goods, in :currency", { currency: sales.currency }) : ctrans("What each organisation sold of your products to its own customers, at what they paid, in :currency", { currency: sales.currency }) }}</template>
					</p>

					<SegmentedToggle v-if="sales.organisations.length > 1" v-model="selected" :options="organisationOptions" :aria-label="ctrans('Organisation')" class="mb-4" />

					<div class="grid gap-6 xl:grid-cols-5">
						<div class="flex min-w-0 flex-col xl:col-span-3">
							<div class="h-56">
								<Chart type="line" :data="chartData" :options="chartOptions" class="h-full" />
							</div>
							<div class="mt-4 flex flex-1 flex-col">
								<div class="mb-1 text-xs text-gray-500">{{ ctrans("Ahead or behind :last_year", { last_year: thenLabel }) }}</div>
								<div class="relative min-h-40 flex-1">
									<Chart type="line" :data="aheadBehindData" :options="aheadBehindOptions" class="!absolute inset-0" />
								</div>
							</div>
						</div>

						<div class="min-w-0 xl:col-span-2 xl:border-l xl:border-gray-100 xl:pl-6">
							<div class="flex flex-wrap items-start justify-between gap-2">
								<div>
									<p class="text-2xl font-bold tabular-nums">{{ money(yearSoFar) }}</p>
									<p class="text-xs text-gray-400">{{ atSupplierPrices ? ctrans("Sold at your prices in :year so far", { year: nowLabel }) : atCost ? ctrans("Cost of the goods sold in :year so far", { year: nowLabel }) : ctrans("Sold wholesale in :year so far", { year: nowLabel }) }}</p>
								</div>
								<div v-if="yearEnd" class="text-right">
									<p class="text-xs text-gray-500">{{ inMonth ? ctrans("Expected by month end") : ctrans("Expected by year end") }}</p>
									<p class="text-2xl font-bold tabular-nums" :class="yearEnd.expected < lastYearTotal ? 'text-red-600' : 'text-green-600'">{{ money(yearEnd.expected) }}</p>
									<p class="text-xs text-gray-400">
										{{ ctrans("likely :low to :high", { low: shortMoney(yearEnd.low), high: shortMoney(yearEnd.high) }) }}
										<span v-if="lastYearTotal"> · {{ ctrans(":change on :last_year", { change: signed((yearEnd.expected / lastYearTotal - 1) * 100), last_year: thenLabel }) }}</span>
									</p>
								</div>
							</div>
							<p v-if="versusLastYear !== null" class="mt-2 text-xs text-gray-500">
								<span :class="versusLastYear < 0 ? 'text-red-600' : 'text-green-600'">{{ inMonth ? ctrans(":change vs the same days of :last_year", { change: signed(versusLastYear), last_year: thenLabel }) : ctrans(":change vs the same months of :last_year", { change: signed(versusLastYear), last_year: String(sales.year - 1) }) }}</span>
								· {{ ctrans(":amount against :last_year_amount, up to :month", { amount: money(completeThisYear), last_year_amount: money(completeLastYear), month: pointName(completeMonths) }) }}
							</p>

							<table v-if="!inMonth" class="mt-4 w-full text-xs tabular-nums">
								<thead class="text-gray-400">
									<tr>
										<th class="py-0.5 text-left font-normal">{{ ctrans("Month") }}</th>
										<th class="py-0.5 text-right font-normal">{{ sales.year }}</th>
										<th class="py-0.5 text-right font-normal">{{ sales.year - 1 }}</th>
										<th class="py-0.5 text-right font-normal">{{ ctrans("Difference") }}</th>
									</tr>
								</thead>
								<tbody>
									<tr v-for="row in monthRows" :key="row.month" :class="row.isForecast ? 'italic text-gray-400' : 'text-gray-700'">
										<td class="py-0.5">{{ monthName(row.month) }}<span v-if="row.isPartial" class="text-gray-400"> · {{ ctrans("so far") }}</span><span v-if="row.isForecast"> · {{ ctrans("forecast") }}</span></td>
										<td class="py-0.5 text-right">{{ row.sales === null ? "—" : money(row.sales) }}</td>
										<td class="py-0.5 text-right text-gray-500">{{ money(row.lastYear) }}</td>
										<td class="py-0.5 text-right" :class="row.change === null ? '' : row.change < 0 ? 'text-red-600' : 'text-green-600'">{{ row.change === null ? "" : signed(row.change) }}</td>
									</tr>
								</tbody>
							</table>
							<p v-if="forecast && inMonth" class="mt-3 text-xs text-gray-400">
								{{ ctrans("Forecast: the rest of :last_year, scaled by how this month has run so far against the same days then. The band is the worst and the best of the last six full months.", { last_year: thenLabel }) }}
							</p>
							<p v-else-if="forecast" class="mt-3 text-xs text-gray-400">
								{{ ctrans("Forecast: the rest of :last_year, scaled by how the last three full months ran against the same months then. The band is the worst and the best of the last six.", { last_year: String(sales.year - 1) }) }}
							</p>
						</div>
					</div>
				</section>

				<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
					<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Per organisation") }}</h2>
					<p class="mt-0.5 text-xs text-gray-500">{{ atSupplierPrices ? ctrans("Goods sold in the last 12 months, from :date, at your prices, and the state of the stock today", { date: useFormatTime(sales.from) }) : atCost ? ctrans("Goods sold in the last 12 months, from :date, at cost, and the state of the stock today", { date: useFormatTime(sales.from) }) : ctrans("Goods sold in the last 12 months, from :date, at what customers paid, and the state of the stock today", { date: useFormatTime(sales.from) }) }}</p>
					<p v-if="!sales.organisations.length" class="py-6 text-center text-xs text-gray-400">{{ seller === "supplier" ? ctrans("No organisation buys from this supplier yet") : ctrans("No organisation buys from this agent yet") }}</p>
					<table v-else class="mt-3 w-full text-sm">
						<thead class="text-xs uppercase tracking-wide text-gray-400">
							<tr>
								<th class="pb-1 text-left font-medium">{{ ctrans("Organisation") }}</th>
								<th class="pb-1 text-right font-medium">{{ atSupplierPrices ? ctrans("Sold, at your prices") : atCost ? ctrans("Sold, at cost") : ctrans("Sold, wholesale") }}</th>
								<th class="pb-1 text-right font-medium">{{ ctrans("Products that sold") }}</th>
								<th class="pb-1 text-right font-medium">{{ ctrans("Out of stock") }}</th>
								<th class="pb-1 text-right font-medium">{{ ctrans("About to run out") }}</th>
								<th class="pb-1 text-right font-medium">{{ ctrans("Not selling") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="organisation in sales.organisations" :key="organisation.code">
								<td class="w-1/3 py-1.5 pr-4">
									<div class="truncate font-medium text-gray-800" :title="organisation.name">{{ organisation.name }}</div>
									<div class="mt-0.5 h-1 rounded bg-gray-100">
										<div class="h-1 rounded bg-gray-400" :style="{ width: `${(100 * organisation.sold) / topSold}%` }" />
									</div>
								</td>
								<td class="py-1.5 text-right font-medium tabular-nums text-gray-800">{{ money(organisation.sold) }}</td>
								<td class="py-1.5 text-right tabular-nums text-gray-600">
									{{ locale.number(organisation.selling) }} <span class="text-xs text-gray-400">/ {{ locale.number(organisation.products) }}</span>
								</td>
								<td class="py-1.5 text-right tabular-nums" :class="organisation.problems.out ? 'text-red-600' : 'text-gray-400'">{{ locale.number(organisation.problems.out) }}</td>
								<td class="py-1.5 text-right tabular-nums" :class="organisation.problems.low ? 'text-amber-600' : 'text-gray-400'">{{ locale.number(organisation.problems.low) }}</td>
								<td class="py-1.5 text-right tabular-nums text-gray-500">{{ locale.number(organisation.problems.dead) }}</td>
							</tr>
						</tbody>
						<tfoot v-if="sales.organisations.length > 1" class="border-t border-gray-200 text-gray-500">
							<tr>
								<td class="py-1.5 text-xs uppercase tracking-wide">{{ ctrans("Total") }}</td>
								<td class="py-1.5 text-right font-medium tabular-nums text-gray-800">{{ money(totalSold) }}</td>
								<td />
								<td class="py-1.5 text-right tabular-nums">{{ locale.number(problemTotal("out")) }}</td>
								<td class="py-1.5 text-right tabular-nums">{{ locale.number(problemTotal("low")) }}</td>
								<td class="py-1.5 text-right tabular-nums">{{ locale.number(problemTotal("dead")) }}</td>
							</tr>
						</tfoot>
					</table>
				</section>

				<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
					<section v-for="ranking in rankings" :key="ranking.key" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
						<div class="flex items-baseline justify-between gap-2">
							<h2 class="text-sm font-semibold text-gray-700">
								{{ ranking.title }}
								<span v-if="ranking.count !== null" class="ml-1 tabular-nums" :class="ranking.tone">{{ locale.number(ranking.count) }}</span>
							</h2>
							<span class="flex items-baseline gap-3 text-xs">
								<span v-if="ranking.count !== null && ranking.count > ranking.rows.length" class="text-gray-400">
									{{ ctrans("first :shown shown", { shown: ranking.rows.length }) }}
								</span>
								<Link v-if="links" :href="links[ranking.key === 'top' ? 'all' : ranking.key]" class="text-gray-500 hover:text-gray-700">{{ ctrans("See all") }}</Link>
							</span>
						</div>
						<p class="mt-0.5 text-xs text-gray-500">{{ ranking.hint }}</p>
						<p v-if="!ranking.rows.length" class="py-6 text-center text-xs text-gray-400">{{ ranking.empty }}</p>
						<ul v-else class="mt-3 divide-y divide-gray-100 text-sm">
							<li v-for="product in ranking.rows" :key="product.code" class="flex items-center gap-3 py-1.5">
								<div class="min-w-0 flex-1">
									<div class="truncate text-gray-800" :title="product.name">
										<span class="font-medium">{{ product.code }}</span>
										<span class="ml-1.5 text-gray-500">{{ product.name }}</span>
									</div>
									<div class="mt-0.5 flex flex-wrap gap-1">
										<span
											v-for="organisation in product.organisations"
											:key="organisation.code"
											class="rounded px-1.5 py-0.5 text-xs tabular-nums"
											:class="organisation.problem ? problemClass[organisation.problem] : 'bg-gray-100 text-gray-600'"
										>
											{{ organisation.code }} {{ shortMoney(organisation[ranking.figure]) }}
										</span>
									</div>
								</div>
								<span class="whitespace-nowrap text-right tabular-nums text-gray-600">
									{{ ranking.figure === "stock" ? ctrans(":amount in stock", { amount: money(product.organisations.reduce((sum, organisation) => sum + organisation.stock, 0)) }) : money(product.sold) }}
								</span>
							</li>
						</ul>
					</section>
				</div>

				<Link v-if="supplier && links" :href="links.all" class="inline-block">
					<Button type="tertiary" icon="fal fa-box-usd" :label="ctrans('All products of this supplier')" />
				</Link>
			</div>
		</Deferred>

		<Deferred data="feedback">
			<template #fallback>
		<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
			<div v-for="card in 3" :key="card" class="h-64 animate-pulse rounded-lg bg-gray-100" />
		</div>
			</template>

			<section v-if="feedback" class="space-y-2">
		<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Feedback") }}</h2>
		<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
			<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Customers") }}</h3>
				<dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs tabular-nums">
					<dt class="text-gray-500">{{ ctrans("Rating") }}</dt>
					<dd class="text-right text-gray-800">{{ feedback.customers.rating === null ? "—" : feedback.customers.rating.toFixed(2) }} <span class="text-gray-400">({{ locale.number(feedback.customers.reviews) }})</span></dd>
					<dt class="text-gray-500">{{ ctrans("Refunds %") }}</dt>
					<dd class="text-right text-gray-800">{{ percent(feedback.customers.refund_percent) }}</dd>
				</dl>
				<p v-if="!feedback.customers.recent.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("No public reviews yet") }}</p>
				<ul v-else class="mt-3 divide-y divide-gray-100 text-xs">
					<li v-for="(review, index) in feedback.customers.recent" :key="index" class="py-1.5">
						<div class="flex items-baseline justify-between gap-2 text-gray-500">
							<span><span class="text-amber-500">{{ stars(review.rating) }}</span> <span class="font-medium text-gray-700">{{ review.code }}</span></span>
							<span class="tabular-nums text-gray-400">{{ useFormatTime(review.date) }}</span>
						</div>
						<p class="mt-0.5 line-clamp-3 text-gray-700">{{ review.message }}</p>
					</li>
				</ul>
			</div>

			<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Purchasing") }}</h3>
				<p class="mt-0.5 text-xs text-gray-500">{{ seller === "supplier" ? ctrans("Notes purchasing shared with the supplier") : ctrans("Notes purchasing shared with the agent") }}</p>
				<p v-if="!feedback.purchasing.length" class="py-6 text-center text-xs text-gray-400">{{ staff ? ctrans("No notes shared yet") : ctrans("Purchasing has not shared any notes with you") }}</p>
				<ul v-else class="mt-3 divide-y divide-gray-100 text-xs">
					<li v-for="(note, index) in feedback.purchasing" :key="index" class="py-1.5">
						<div class="flex items-baseline justify-between gap-2">
							<span class="rounded px-1.5 py-0.5" :class="severityClass[note.severity]">{{ note.title }}</span>
							<span class="tabular-nums text-gray-400">{{ useFormatTime(note.date) }}</span>
						</div>
						<p v-if="note.body" class="mt-0.5 line-clamp-3 text-gray-700">{{ note.body }}</p>
					</li>
				</ul>
			</div>

			<div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Warehouse") }}</h3>
				<p class="mt-0.5 text-xs text-gray-500">{{ ctrans("Delivery lines received short in the last 12 months") }}</p>
				<p class="mt-2 text-2xl font-bold tabular-nums">
					{{ percent(feedback.warehouse.short_percent) }}
					<span class="text-xs font-normal text-gray-400">{{ ctrans(":short of :lines lines", { short: locale.number(feedback.warehouse.short_lines), lines: locale.number(feedback.warehouse.lines) }) }}</span>
				</p>
				<p v-if="!feedback.warehouse.worst.length" class="py-4 text-center text-xs text-gray-400">{{ ctrans("No delivery line arrived short") }}</p>
				<ul v-else class="mt-3 divide-y divide-gray-100 text-xs">
					<li v-for="product in feedback.warehouse.worst" :key="product.code" class="flex items-center justify-between gap-2 py-1.5">
						<span class="min-w-0 truncate text-gray-700" :title="product.name"><span class="font-medium">{{ product.code }}</span> <span class="text-gray-500">{{ product.name }}</span></span>
						<span class="whitespace-nowrap tabular-nums text-gray-600">{{ product.short_lines }} / {{ product.lines }}</span>
					</li>
				</ul>
			</div>
		</div>
			</section>
		</Deferred>
	</div>
</template>
