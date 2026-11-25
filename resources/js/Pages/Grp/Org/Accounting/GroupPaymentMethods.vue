<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 25 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Chart from "primevue/chart"
import Select from "primevue/select"
import ToggleSwitch from "primevue/toggleswitch"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCreditCard, faChartBar, faListUl, faStore, faSeedling, faSort, faSortUp, faSortDown, faChevronRight } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faCreditCard, faChartBar, faListUl, faStore, faSeedling, faSort, faSortUp, faSortDown, faChevronRight)

type Method = { key: string; label: string; colour: string; online: boolean | null }
type Cell = { amount: number; payments: number; average: number | null }
type MatrixRow = {
	key: string
	label: string
	kind: "online" | "subtotal" | "offline" | "total" | "memo"
	group: string | null
	cells: Record<string, Cell>
	total: Cell & { share: number | null; growth_month: number | null; growth_months: number | null; run_rate: number | null }
}
type ShopRow = { shop: string; shop_name: string; shop_type: string; organisation: string; currency: string; method: string; first_payment: string | null; payments: number; amount: number; amount_group: number; monthly: number }
type OrganisationCard = {
	slug: string
	code: string
	name: string
	currency: string
	total: number
	payments: number
	online: number
	online_share: number | null
	run_rate: number | null
	online_run_rate: number | null
	new_run_rate: number | null
	balance: number
	top: { key: string; amount: number }[]
	launched: string | null
	new_share: { before: { months: string[]; share: number | null } | null; now: { months: string[]; share: number | null } }
	emerging: Record<string, number[]>
	emerging_group: Record<string, number[]>
}
type Filters = { organisation: string | null; shop: string | null; shop_type: string | null; brand: string | null; currency: string | null; months: number; intercompany: boolean }

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	filters: Filters
	organisations: { value: string; label: string }[]
	report: {
		currency: string
		group_currency: string
		months: string[]
		weeks: string[]
		complete_months: { last_three: string[]; three_before: string[]; last: string[]; same_days_cut: string }
		methods: Method[]
		series: { key: string; label: string; colour: string }[]
		organisations: OrganisationCard[]
		chart: Record<string, Record<string, number[]>>
		matrix: { columns: { key: string; label: string; currency: string }[]; rows: MatrixRow[]; online_share: { cells: Record<string, number | null>; total: number | null } }
		shops_table: ShopRow[]
		options: { shops: { value: string; label: string }[]; shop_types: string[]; currencies: string[]; brands: { value: string; label: string }[]; periods: number[] }
	}
}>()

const locale = useLocaleStore()
const money = (currency: string, amount: number | null) => (amount === null ? "—" : locale.currencyFormat(currency, amount))
const shortMoney = (currency: string, amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const percent = (value: number | null) => (value === null ? "—" : `${locale.number(value)}%`)
const monthLabel = (month: string) => useFormatTime(`${month}-01`, { formatTime: "MMM yy" })
const monthRange = (months: string[]) => (months.length ? (months.length === 1 ? monthLabel(months[0]) : `${monthLabel(months[0])} – ${monthLabel(months[months.length - 1])}`) : "")
const methodsByKey = computed(() => Object.fromEntries(props.report.methods.map((method) => [method.key, method])))
const methodLabel = (key: string) => ctrans(methodsByKey.value[key]?.label ?? key)
const methodColour = (key: string) => methodsByKey.value[key]?.colour ?? "#9ca3af"

const reload = (changes: Partial<Filters>) => {
	const next = { ...props.filters, ...changes }
	router.get(
		route("grp.financials.payment_methods.index"),
		Object.fromEntries(
			Object.entries({ org: next.organisation, shop: next.shop, shop_type: next.shop_type, brand: next.brand, currency: next.currency, months: next.months === 12 ? null : next.months, intercompany: next.intercompany ? 1 : null }).filter(([, value]) => value !== null && value !== undefined && value !== ""),
		),
		{ preserveScroll: true },
	)
}

const organisationModel = computed({ get: () => props.filters.organisation ?? "all", set: (value: string) => reload({ organisation: value === "all" ? null : value, shop: null }) })
const periodModel = computed({ get: () => props.filters.months, set: (value: number) => reload({ months: value }) })
const shopModel = computed({ get: () => props.filters.shop, set: (value: string | null) => reload({ shop: value }) })
const shopTypeModel = computed({ get: () => props.filters.shop_type, set: (value: string | null) => reload({ shop_type: value }) })
const brandModel = computed({ get: () => props.filters.brand, set: (value: string | null) => reload({ brand: value }) })
const currencyModel = computed({ get: () => props.filters.currency, set: (value: string | null) => reload({ currency: value }) })
const intercompanyModel = computed({ get: () => props.filters.intercompany, set: (value: boolean) => reload({ intercompany: value }) })

const organisationOptions = computed(() => [{ label: ctrans("All group"), value: "all" }, ...props.organisations])
const periodOptions = computed(() => props.report.options.periods.map((months) => ({ label: ctrans(":count months", { count: months }), value: months })))
const shopTypeOptions = computed(() => props.report.options.shop_types.map((type) => ({ label: capitalize(type.replace("_", " ")), value: type })))
const currencyOptions = computed(() => props.report.options.currencies.map((currency) => ({ label: currency, value: currency })))

const chartScopes = computed(() => [
	...(props.report.chart.all ? [{ label: ctrans("All group"), value: "all" }] : []),
	...props.report.organisations.filter((organisation) => props.report.chart[organisation.slug]).map((organisation) => ({ label: organisation.code, value: organisation.slug })),
])
const chartScope = ref<string>(props.report.chart.all ? "all" : props.report.organisations[0]?.slug ?? "all")
const chartCurrency = computed(() => (chartScope.value === "all" ? props.report.group_currency : props.report.organisations.find((organisation) => organisation.slug === chartScope.value)?.currency ?? props.report.currency))
const ROLLING_WEEKS = 4
const shareChart = computed(() => {
	const amounts = props.report.chart[chartScope.value] ?? {}
	const weekly = props.report.weeks.map((_, index) => Object.values(amounts).reduce((sum, series) => sum + (series[index] ?? 0), 0))
	let last = weekly.length - 1
	while (last > 0 && weekly[last] <= 0) {
		last--
	}
	const rolling = (series: number[], index: number) => series.slice(Math.max(0, index - ROLLING_WEEKS + 1), index + 1).reduce((sum, amount) => sum + (amount ?? 0), 0)
	const indexes = props.report.weeks.map((_, index) => index).slice(0, last + 1)
	const totals = indexes.map((index) => rolling(weekly, index))
	return {
		labels: indexes.map((index) => useFormatTime(props.report.weeks[index], { formatTime: "d MMM yy" })),
		datasets: [...props.report.series]
			.sort((a, b) => (amounts[b.key] ?? []).reduce((sum, amount) => sum + amount, 0) - (amounts[a.key] ?? []).reduce((sum, amount) => sum + amount, 0))
			.map((series) => ({
				label: ctrans(series.label),
				data: indexes.map((index, position) => (totals[position] > 0 ? Math.round((rolling(amounts[series.key] ?? [], index) / totals[position]) * 1000) / 10 : null)),
				amounts: indexes.map((index) => rolling(amounts[series.key] ?? [], index)),
				backgroundColor: `${series.colour}d9`,
				borderColor: series.colour,
				borderWidth: 1,
				fill: true,
				cubicInterpolationMode: "monotone",
				pointRadius: 0,
				pointHoverRadius: 3,
				pointHoverBackgroundColor: series.colour,
			})),
	}
})
type TooltipContext = {
	chart: { canvas: HTMLCanvasElement }
	tooltip: { opacity: number; caretX: number; caretY: number; title: string[]; dataPoints: { dataset: { label: string; borderColor: string; amounts: number[] }; raw: unknown; dataIndex: number }[] }
}
const tableTooltip = ({ chart, tooltip }: TooltipContext) => {
	const parent = chart.canvas.parentNode as HTMLElement
	let element = parent.querySelector<HTMLDivElement>(".share-tooltip")
	if (!element) {
		element = document.createElement("div")
		element.className = "share-tooltip pointer-events-none absolute z-10 rounded-md border border-gray-200 bg-white px-3 py-2 text-xs shadow-lg transition-opacity"
		parent.style.position = "relative"
		parent.appendChild(element)
	}
	if (tooltip.opacity === 0) {
		element.style.opacity = "0"
		return
	}
	const points = [...tooltip.dataPoints].sort((a, b) => Number(b.raw) - Number(a.raw))
	const cell = (text: string, className = "") => Object.assign(document.createElement("td"), { textContent: text, className })
	const table = document.createElement("table")
	table.className = "tabular-nums"
	for (const point of points) {
		const row = table.insertRow()
		const swatch = document.createElement("td")
		swatch.className = "pr-1.5"
		const box = document.createElement("span")
		box.className = "inline-block h-2.5 w-2.5 rounded-sm"
		box.style.backgroundColor = point.dataset.borderColor
		swatch.appendChild(box)
		row.append(swatch, cell(point.dataset.label, "whitespace-nowrap pr-4 text-gray-700"), cell(`${locale.number(Number(point.raw))}%`, "whitespace-nowrap pr-3 text-right font-medium text-gray-900"), cell(money(chartCurrency.value, point.dataset.amounts[point.dataIndex] ?? 0), "whitespace-nowrap text-right text-gray-500"))
	}
	const total = table.insertRow()
	total.className = "border-t border-gray-200"
	total.append(cell(""), cell(ctrans("Total"), "pt-1 pr-4 font-medium text-gray-700"), cell("100%", "pt-1 pr-3 text-right font-medium text-gray-900"), cell(money(chartCurrency.value, points.reduce((sum, point) => sum + (point.dataset.amounts[point.dataIndex] ?? 0), 0)), "pt-1 text-right font-medium text-gray-700"))
	const title = Object.assign(document.createElement("div"), { textContent: ctrans(":weeks weeks to the week of :date", { weeks: ROLLING_WEEKS, date: tooltip.title[0] ?? "" }), className: "mb-1.5 font-semibold text-gray-800" })
	element.replaceChildren(title, table)
	const left = tooltip.caretX + 16 + element.offsetWidth > parent.clientWidth ? tooltip.caretX - element.offsetWidth - 16 : tooltip.caretX + 16
	element.style.left = `${Math.max(0, left)}px`
	element.style.top = `${Math.max(0, Math.min(tooltip.caretY - element.offsetHeight / 2, parent.clientHeight - element.offsetHeight))}px`
	element.style.opacity = "1"
}
const shareOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 12, color: "#4b5563" } },
		tooltip: { enabled: false, external: tableTooltip },
	},
	scales: {
		x: { grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
		y: { stacked: true, min: 0, max: 100, grid: { color: "#f3f4f6" }, ticks: { color: "#6b7280", callback: (value: number | string) => `${value}%` } },
	},
}))

const sameScale = ref(true)
const scaleOptions = computed(() => [
	{ label: ctrans("Own scale"), value: false },
	{ label: ctrans("Same scale (:currency)", { currency: props.report.group_currency }), value: true },
])
const emergingKeys = computed(() => Object.keys(props.report.organisations[0]?.emerging ?? {}))
const hiddenEmerging = ref<Record<string, boolean>>({})
const emergingValues = (organisation: OrganisationCard) => (sameScale.value ? organisation.emerging_group : organisation.emerging)
const visibleTotals = (values: Record<string, number[]>) => props.report.months.map((_, index) => emergingKeys.value.reduce((sum, key) => sum + (hiddenEmerging.value[key] ? 0 : values[key]?.[index] ?? 0), 0))
const firstActive = (values: Record<string, number[]>) => {
	const index = visibleTotals(values).findIndex((amount) => amount > 0)
	return index < 0 ? null : index
}
const emergingOrganisations = computed(() => props.report.organisations.filter((organisation) => emergingKeys.value.some((key) => (organisation.emerging[key] ?? []).some((amount) => amount > 0))))
const sharedStart = computed(() => Math.min(...emergingOrganisations.value.map((organisation) => firstActive(organisation.emerging_group) ?? props.report.months.length)))
const sharedMax = computed(() => Math.max(0, ...emergingOrganisations.value.flatMap((organisation) => visibleTotals(organisation.emerging_group).slice(sharedStart.value))))
const newMethodCharts = computed(() =>
	emergingOrganisations.value.map((organisation) => {
		const values = emergingValues(organisation)
		const start = sameScale.value ? sharedStart.value : firstActive(values) ?? 0
		const currency = sameScale.value ? props.report.group_currency : organisation.currency
		return {
			organisation,
			since: props.report.months[start],
			data: {
				labels: props.report.months.slice(start).map(monthLabel),
				datasets: emergingKeys.value.map((key) => ({
					label: methodLabel(key),
					data: (values[key] ?? []).slice(start),
					hidden: !!hiddenEmerging.value[key],
					backgroundColor: methodColour(key),
					borderColor: "#ffffff",
					borderWidth: { top: 2 },
					borderSkipped: false,
					stack: "emerging",
				})),
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: "index", intersect: false },
				plugins: {
					legend: { display: false },
					tooltip: { callbacks: { label: (item: { dataset: { label: string }; raw: unknown }) => ` ${item.dataset.label}: ${money(currency, Number(item.raw))}` } },
				},
				scales: {
					x: { stacked: true, grid: { display: false }, ticks: { color: "#6b7280", maxRotation: 0, autoSkip: true } },
					y: {
						stacked: true,
						beginAtZero: true,
						...(sameScale.value && sharedMax.value > 0 ? { max: Math.ceil((sharedMax.value * 1.05) / 1000) * 1000 } : {}),
						grid: { color: "#f3f4f6" },
						ticks: { color: "#6b7280", callback: (value: number | string) => shortMoney(currency, Number(value)) },
					},
				},
			},
		}
	}),
)

const growthClass = (value: number | null) => (value === null ? "text-gray-300" : value >= 0 ? "text-green-700" : "text-red-600")
const growthText = (value: number | null) => (value === null ? "—" : `${value >= 0 ? "▲" : "▼"} ${locale.number(Math.abs(value))}%`)

type MatrixSortKey = "amount" | "payments" | "average" | "share" | "growth_month" | "growth_months" | "run_rate"
const matrixSort = ref<{ key: MatrixSortKey; desc: boolean }>({ key: "amount", desc: true })
const sortedMatrix = computed(() => {
	const { key, desc } = matrixSort.value
	const sortSection = (kind: MatrixRow["kind"]) =>
		props.report.matrix.rows
			.filter((row) => row.kind === kind)
			.sort((a, b) => {
				const order = (a.total[key] ?? -Infinity) - (b.total[key] ?? -Infinity)
				return desc ? -order : order
			})
	return [...sortSection("online"), ...props.report.matrix.rows.filter((row) => row.kind === "subtotal"), ...sortSection("offline"), ...props.report.matrix.rows.filter((row) => row.kind === "total" || row.kind === "memo")]
})
const sortMatrix = (key: MatrixSortKey) => (matrixSort.value = { key, desc: matrixSort.value.key === key ? !matrixSort.value.desc : true })
const sortIcon = (state: { key: string; desc: boolean }, key: string) => (state.key !== key ? "fal fa-sort" : state.desc ? "fal fa-sort-down" : "fal fa-sort-up")
const rowClass = (row: MatrixRow) =>
	row.kind === "subtotal" || row.kind === "total" ? "border-y border-gray-300 bg-gray-50 font-semibold" : row.kind === "memo" ? "border-t border-gray-100 text-gray-500" : "border-t border-gray-100"
const wholeMoney = (currency: string, amount: number | null) =>
	amount === null ? "—" : new Intl.NumberFormat(undefined, { style: "currency", currency, currencyDisplay: "narrowSymbol", maximumFractionDigits: 0 }).format(amount)
const cellText = (currency: string, cell: Cell | undefined) => (cell && cell.payments ? wholeMoney(currency, cell.amount) : "—")
const cellNote = (currency: string, cell: Cell | undefined) =>
	cell && cell.payments ? ctrans(":count · avg :average", { count: locale.number(cell.payments), average: wholeMoney(currency, cell.average) }) : ""

const EMERGING_SHOP_METHODS = ["klarna", "ideal", "p24", "bancontact", "blik", "eps", "swish", "applepay", "googlepay"]
const shopOrganisation = ref<string>("all")
const shopType = ref<string>("all")
const shopOrganisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...[...new Set(props.report.shops_table.map((row) => row.organisation))].sort().map((code) => ({ label: code, value: code }))])
const shopTypeCapsules = computed(() => [{ label: ctrans("All"), value: "all" }, ...[...new Set(props.report.shops_table.map((row) => row.shop_type))].sort().map((type) => ({ label: capitalize(type.replace("_", " ")), value: type }))])
const expandedShops = ref<Record<string, boolean>>({})
const methodRanking = computed(() => {
	const totals: Record<string, number> = {}
	for (const row of props.report.matrix.rows.filter((row) => row.kind === "online" || row.kind === "offline")) {
		const key = row.group === "cards" ? "cards" : row.key
		totals[key] = (totals[key] ?? 0) + row.total.amount
	}
	return Object.keys(totals).sort((a, b) => totals[b] - totals[a])
})
const shopBarOrder = ref<"biggest" | "fixed">("fixed")
const shopBarOrderOptions = computed(() => [
	{ label: ctrans("Same order"), value: "fixed" },
	{ label: ctrans("Biggest first"), value: "biggest" },
])
const barMethods = (methods: (ShopRow & { share: number })[]) =>
	shopBarOrder.value === "biggest" ? methods : [...methods].sort((a, b) => (methodRanking.value.indexOf(a.method) + 1000) % 1000 - (methodRanking.value.indexOf(b.method) + 1000) % 1000)
const shopCards = computed(() => {
	const byShop = new Map<string, ShopRow[]>()
	for (const row of props.report.shops_table) {
		if ((shopOrganisation.value !== "all" && row.organisation !== shopOrganisation.value) || (shopType.value !== "all" && row.shop_type !== shopType.value)) {
			continue
		}
		byShop.set(row.shop, [...(byShop.get(row.shop) ?? []), row])
	}
	return [...byShop.entries()]
		.map(([code, rows]) => {
			const amount = rows.reduce((sum, row) => sum + row.amount, 0)
			const methods = [...rows].sort((a, b) => b.amount - a.amount)
			return {
				code,
				name: rows[0].shop_name,
				organisation: rows[0].organisation,
				type: rows[0].shop_type,
				currency: rows[0].currency,
				amount,
				amountGroup: rows.reduce((sum, row) => sum + row.amount_group, 0),
				payments: rows.reduce((sum, row) => sum + row.payments, 0),
				monthly: rows.reduce((sum, row) => sum + row.monthly, 0),
				methods: methods.map((row) => ({ ...row, share: amount > 0 ? (row.amount / amount) * 100 : 0 })),
				emergingShare: amount > 0 ? Math.round((rows.filter((row) => EMERGING_SHOP_METHODS.includes(row.method)).reduce((sum, row) => sum + row.amount, 0) / amount) * 1000) / 10 : null,
			}
		})
		.sort((a, b) => b.amountGroup - a.amountGroup)
})
const shopBarWidth = ref<"full" | "proportional">("full")
const shopBarWidthOptions = computed(() => [
	{ label: ctrans("Full width"), value: "full" },
	{ label: ctrans("Proportional"), value: "proportional" },
])
const biggestShop = computed(() => Math.max(0, ...shopCards.value.map((shop) => shop.amountGroup)))
const barLength = (amountGroup: number) => (shopBarWidth.value === "full" || biggestShop.value <= 0 ? 100 : Math.max(1, (amountGroup / biggestShop.value) * 100))

const totalColumns: { key: MatrixSortKey; label: string }[] = [
	{ key: "amount", label: ctrans("Amount") },
	{ key: "payments", label: ctrans("Payments") },
	{ key: "average", label: ctrans("Average") },
	{ key: "share", label: ctrans("Share") },
	{ key: "growth_month", label: ctrans("This month") },
	{ key: "growth_months", label: ctrans("Last 3 months") },
	{ key: "run_rate", label: ctrans("Run rate a year") },
]
const hasData = computed(() => props.report.matrix.rows.some((row) => row.kind === "total" && row.total.payments > 0))
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex flex-wrap items-center gap-3">
			<SegmentedToggle v-if="organisations.length > 1" v-model="organisationModel" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
			<SegmentedToggle v-model="periodModel" :options="periodOptions" :aria-label="ctrans('Period')" />
			<Select v-model="shopModel" :options="report.options.shops" optionLabel="label" optionValue="value" filter showClear :placeholder="ctrans('All shops')" class="w-52" size="small" />
			<Select v-model="shopTypeModel" :options="shopTypeOptions" optionLabel="label" optionValue="value" showClear :placeholder="ctrans('All shop types')" class="w-40" size="small" />
			<Select v-model="brandModel" :options="report.options.brands" optionLabel="label" optionValue="value" showClear :placeholder="ctrans('Any card brand')" class="w-44" size="small" />
			<Select v-model="currencyModel" :options="currencyOptions" optionLabel="label" optionValue="value" showClear :placeholder="ctrans('Any currency')" class="w-36" size="small" />
			<label class="flex items-center gap-2 text-sm text-gray-600">
				<ToggleSwitch v-model="intercompanyModel" />
				{{ ctrans("Include our own companies") }}
			</label>
		</div>
		<p class="text-xs text-gray-500">
			{{ ctrans("Completed payments by month, Bratislava time; this month is month to date. Each organisation in its own currency, the group in :currency. Customer account balance is credit already held: shown, not counted in the shares.", { currency: report.group_currency }) }}
		</p>

		<div v-if="!hasData" class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500">
			{{ ctrans("No payments match these filters.") }}
		</div>

		<template v-else>
			<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
				<div v-for="organisation in report.organisations.filter((card) => card.total > 0)" :key="organisation.slug" class="rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm">
					<div class="flex items-baseline justify-between gap-2">
						<span class="text-sm font-semibold text-gray-700">{{ organisation.code }}</span>
						<span class="text-xs text-gray-400">{{ organisation.currency }}</span>
					</div>
					<div class="mt-1 text-xl font-semibold tabular-nums">{{ money(organisation.currency, organisation.total) }}</div>
					<div class="text-xs tabular-nums text-gray-500">
						{{ ctrans(":count payments · run rate :amount a year", { count: locale.number(organisation.payments), amount: organisation.run_rate === null ? "—" : shortMoney(organisation.currency, organisation.run_rate) }) }}
					</div>
					<div class="mt-2 rounded bg-gray-50 px-2 py-1.5 text-xs tabular-nums text-gray-600">
						<div class="flex justify-between gap-2">
							<span>{{ ctrans("Online") }}</span>
							<span class="font-semibold text-gray-800">{{ shortMoney(organisation.currency, organisation.online) }} · {{ percent(organisation.online_share) }}</span>
						</div>
						<div class="flex justify-between gap-2">
							<span>{{ ctrans("Online run rate") }}</span>
							<span>{{ organisation.online_run_rate === null ? "—" : ctrans(":amount a year", { amount: shortMoney(organisation.currency, organisation.online_run_rate) }) }}</span>
						</div>
					</div>
					<ul class="mt-2 space-y-1 text-sm">
						<li v-for="method in organisation.top" :key="method.key" class="flex items-center gap-2">
							<span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: methodColour(method.key) }" aria-hidden="true" />
							<span class="truncate text-gray-700">{{ methodLabel(method.key) }}</span>
							<span class="ml-auto whitespace-nowrap tabular-nums text-gray-600">{{ shortMoney(organisation.currency, method.amount) }}</span>
							<span class="w-12 whitespace-nowrap text-right text-xs tabular-nums text-gray-400">{{ organisation.total ? percent(Math.round((method.amount / organisation.total) * 1000) / 10) : "" }}</span>
						</li>
					</ul>
					<div class="mt-3 border-t border-gray-100 pt-2 text-xs text-gray-500">
						<div class="flex items-center gap-1.5 font-medium text-gray-600">
							<FontAwesomeIcon icon="fal fa-seedling" fixed-width aria-hidden="true" />
							{{ ctrans("Klarna and local methods") }}
						</div>
						<div v-if="organisation.new_share.before" class="mt-1 flex flex-wrap items-baseline gap-x-1.5 tabular-nums">
							<span>{{ monthRange(organisation.new_share.before.months) }}</span>
							<span class="font-semibold text-gray-700">{{ percent(organisation.new_share.before.share) }}</span>
							<span>→</span>
							<span>{{ monthRange(organisation.new_share.now.months) }}</span>
							<span class="font-semibold text-gray-700">{{ percent(organisation.new_share.now.share) }}</span>
						</div>
						<div v-if="organisation.new_share.before && organisation.new_run_rate" class="mt-0.5 tabular-nums">
							{{ ctrans(":month level: :amount a year", { month: monthRange(report.complete_months.last), amount: shortMoney(organisation.currency, organisation.new_run_rate) }) }}
						</div>
						<div v-if="!organisation.new_share.before" class="mt-1">{{ ctrans("Not launched yet") }}</div>
					</div>
				</div>
			</div>

			<DashboardWidgetBox storageKey="group_payment_methods_share_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-chart-bar" fixed-width aria-hidden="true" />
						{{ ctrans("Share of takings by method, 4-week rolling") }}
					</span>
					<SegmentedToggle v-if="chartScopes.length > 1" v-model="chartScope" :options="chartScopes" :aria-label="ctrans('Chart organisation')" />
				</template>
				<div class="h-80">
					<Chart type="line" :data="shareChart" :options="shareOptions" class="h-full" />
				</div>
			</DashboardWidgetBox>

			<DashboardWidgetBox v-if="newMethodCharts.length" storageKey="group_payment_methods_new_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-seedling" fixed-width aria-hidden="true" />
						{{ ctrans("Emerging payments") }}
					</span>
					<span class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
						<SegmentedToggle v-model="sameScale" :options="scaleOptions" :aria-label="ctrans('Scale')" />
						<button
							v-for="key in emergingKeys"
							:key="key"
							type="button"
							class="flex items-center gap-1 rounded px-1 py-0.5 hover:bg-gray-100"
							:class="hiddenEmerging[key] ? 'text-gray-400 line-through' : ''"
							:aria-pressed="!hiddenEmerging[key]"
							@click="hiddenEmerging[key] = !hiddenEmerging[key]">
							<span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: methodColour(key), opacity: hiddenEmerging[key] ? 0.3 : 1 }" aria-hidden="true" />
							{{ methodLabel(key) }}
						</button>
					</span>
				</template>
				<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
					<div v-for="chart in newMethodCharts" :key="chart.organisation.slug">
						<div class="mb-1 flex items-baseline justify-between text-xs">
							<span class="font-semibold text-gray-700">{{ chart.organisation.code }}</span>
							<span class="text-gray-400">{{ ctrans("since :month", { month: monthLabel(chart.since) }) }}</span>
						</div>
						<div class="h-40">
							<Chart type="bar" :data="chart.data" :options="chart.options" class="h-full" />
						</div>
					</div>
				</div>
			</DashboardWidgetBox>

			<DashboardWidgetBox storageKey="group_payment_methods_table_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-list-ul" fixed-width aria-hidden="true" />
						{{ ctrans("By payment method") }}
					</span>
					<span class="text-xs text-gray-400">
						{{ ctrans("Total in :currency. This month: to :date against the same days of last month. Last 3 months: :recent against :before. Run rate: :recent a year.", { currency: report.currency, date: useFormatTime(report.complete_months.same_days_cut, { formatTime: "d MMM yyyy" }), recent: monthRange(report.complete_months.last_three), before: monthRange(report.complete_months.three_before) }) }}
					</span>
				</template>
				<div class="overflow-x-auto">
					<table class="w-full text-xs tabular-nums">
						<thead class="whitespace-nowrap text-[11px] uppercase text-gray-500">
							<tr>
								<th class="w-px whitespace-nowrap py-1.5 pr-3 text-left font-medium">{{ ctrans("Method") }}</th>
								<th v-for="column in report.matrix.columns" :key="column.key" class="px-1.5 py-1 text-right font-medium">{{ column.label }} ({{ column.currency }})</th>
								<th v-for="(column, index) in totalColumns" :key="column.key" class="px-1.5 py-1 text-right font-medium" :class="index === 0 && report.matrix.columns.length ? 'border-l border-gray-200' : ''">
									<button type="button" class="ml-auto flex items-center gap-1 hover:text-gray-700" @click="sortMatrix(column.key)">
										{{ index === 0 && report.matrix.columns.length ? ctrans("Total") : column.label }}
										<FontAwesomeIcon :icon="sortIcon(matrixSort, column.key)" fixed-width aria-hidden="true" />
									</button>
								</th>
							</tr>
						</thead>
						<tbody>
							<tr v-for="row in sortedMatrix" :key="row.key" :class="rowClass(row)">
								<td class="whitespace-nowrap py-1 pr-2">
									<span class="flex items-center gap-2">
										<span v-if="row.group" class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: methodColour(row.key) }" aria-hidden="true" />
										<span v-if="row.kind === 'memo'" v-tooltip="ctrans('Paid from customer account balance: credit customers already held with us, so it is not counted in totals or shares')" class="cursor-help border-b border-dotted border-gray-400">{{ ctrans(row.label) }}</span>
										<template v-else>{{ ctrans(row.label) }}</template>
									</span>
								</td>
								<td v-for="column in report.matrix.columns" :key="column.key" class="whitespace-nowrap px-1.5 py-1 text-right">
									<div class="text-[13px]">{{ cellText(column.currency, row.cells[column.key]) }}</div>
									<div class="text-[10px] font-normal text-gray-400">{{ cellNote(column.currency, row.cells[column.key]) }}</div>
								</td>
								<td class="whitespace-nowrap px-1.5 py-1 text-right text-[13px]" :class="report.matrix.columns.length ? 'border-l border-gray-200' : ''">{{ wholeMoney(report.currency, row.total.payments ? row.total.amount : null) }}</td>
								<td class="whitespace-nowrap px-1.5 py-1 text-right text-[13px]">{{ locale.number(row.total.payments) }}</td>
								<td class="whitespace-nowrap px-1.5 py-1 text-right text-[13px]">{{ wholeMoney(report.currency, row.total.average) }}</td>
								<td class="whitespace-nowrap px-1.5 py-1 text-right text-[13px]">{{ row.kind === "memo" ? ctrans("not counted") : percent(row.total.share) }}</td>
								<td class="whitespace-nowrap px-1.5 py-1 text-right text-xs" :class="growthClass(row.total.growth_month)">{{ growthText(row.total.growth_month) }}</td>
								<td class="whitespace-nowrap px-1.5 py-1 text-right text-xs" :class="growthClass(row.total.growth_months)">{{ growthText(row.total.growth_months) }}</td>
								<td class="whitespace-nowrap py-1 pl-1.5 text-right text-[13px]">{{ row.total.run_rate === null ? "—" : shortMoney(report.currency, row.total.run_rate) }}</td>
							</tr>
							<tr class="border-t border-gray-100 text-xs text-gray-500">
								<td class="whitespace-nowrap py-1 pr-2">{{ ctrans("Online share of value") }}</td>
								<td v-for="column in report.matrix.columns" :key="column.key" class="whitespace-nowrap px-1.5 py-1 text-right">{{ percent(report.matrix.online_share.cells[column.key] ?? null) }}</td>
								<td class="whitespace-nowrap px-1.5 py-1 text-right" :class="report.matrix.columns.length ? 'border-l border-gray-200' : ''">{{ percent(report.matrix.online_share.total) }}</td>
								<td :colspan="totalColumns.length - 1" />
							</tr>
						</tbody>
					</table>
				</div>
				<p class="mt-2 text-xs text-gray-500">{{ ctrans("Online: cards, Apple Pay, Google Pay, Klarna and local methods. Card brand is recorded since 10 Feb 2026; earlier card payments are under Card (other).") }}</p>
			</DashboardWidgetBox>

			<DashboardWidgetBox storageKey="group_payment_methods_shops_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-store" fixed-width aria-hidden="true" />
						{{ ctrans("By shop") }}
					</span>
					<span class="text-xs text-gray-400">{{ ctrans("In each organisation's currency. Click a shop for its methods.") }}</span>
				</template>
				<div class="mb-3 flex flex-wrap items-center gap-3">
					<SegmentedToggle v-if="shopOrganisationOptions.length > 2" v-model="shopOrganisation" :options="shopOrganisationOptions" :aria-label="ctrans('Organisation')" />
					<SegmentedToggle v-if="shopTypeCapsules.length > 2" v-model="shopType" :options="shopTypeCapsules" :aria-label="ctrans('Shop type')" />
					<SegmentedToggle v-model="shopBarWidth" :options="shopBarWidthOptions" class="ml-auto" :aria-label="ctrans('Bar length')" />
					<SegmentedToggle v-model="shopBarOrder" :options="shopBarOrderOptions" :aria-label="ctrans('Bar order')" />
				</div>
				<ul class="divide-y divide-gray-100">
					<li v-for="shop in shopCards" :key="shop.code">
						<button type="button" class="grid w-full grid-cols-[minmax(10rem,14rem)_1fr_auto] items-center gap-4 py-2.5 text-left hover:bg-gray-50 sm:grid-cols-[minmax(10rem,14rem)_1fr_8rem_7rem] lg:grid-cols-[minmax(10rem,14rem)_1fr_8rem_7rem_5rem]" :aria-expanded="!!expandedShops[shop.code]" @click="expandedShops[shop.code] = !expandedShops[shop.code]">
							<span class="min-w-0">
								<span class="flex items-center gap-1.5 text-sm font-medium text-gray-800">
									<FontAwesomeIcon icon="fal fa-chevron-right" class="text-[10px] text-gray-400 transition-transform" :class="expandedShops[shop.code] ? 'rotate-90' : ''" fixed-width aria-hidden="true" />
									{{ shop.code }}
								</span>
								<span class="block truncate pl-5 text-xs text-gray-500">{{ shop.name }} · {{ shop.organisation }}</span>
							</span>
							<span class="flex h-3 overflow-hidden rounded-full" :class="shopBarWidth === 'full' ? 'bg-gray-100' : ''" :style="{ width: `${barLength(shop.amountGroup)}%` }" role="img" :aria-label="shop.methods.map((method) => `${methodLabel(method.method)} ${percent(Math.round(method.share * 10) / 10)}`).join(', ')">
								<span
									v-for="method in barMethods(shop.methods).filter((method) => method.share >= 0.5)"
									:key="method.method"
									v-tooltip="`${methodLabel(method.method)}: ${percent(Math.round(method.share * 10) / 10)} · ${wholeMoney(shop.currency, method.amount)}`"
									class="h-full border-r border-white last:border-r-0"
									:style="{ width: `${method.share}%`, backgroundColor: methodColour(method.method) }" />
							</span>
							<span class="text-right tabular-nums">
								<span class="block text-sm text-gray-800">{{ wholeMoney(shop.currency, shop.amount) }}</span>
								<span class="block text-[11px] text-gray-400">{{ ctrans(":count payments", { count: locale.number(shop.payments) }) }}</span>
							</span>
							<span class="hidden text-right tabular-nums sm:block">
								<span class="block text-sm text-gray-700">{{ wholeMoney(shop.currency, shop.monthly) }}</span>
								<span class="block text-[11px] text-gray-400">{{ ctrans("a month, last 3") }}</span>
							</span>
							<span class="hidden text-right tabular-nums lg:block">
								<span class="block text-sm text-gray-700">{{ percent(shop.emergingShare) }}</span>
								<span class="block text-[11px] text-gray-400">{{ ctrans("emerging") }}</span>
							</span>
						</button>
						<table v-if="expandedShops[shop.code]" class="mb-3 ml-5 w-[calc(100%-1.25rem)] max-w-3xl text-xs tabular-nums">
							<thead class="whitespace-nowrap text-[11px] uppercase text-gray-500">
								<tr>
									<th class="py-1 pr-3 text-left font-medium">{{ ctrans("Method") }}</th>
									<th class="px-2 py-1 text-right font-medium">{{ ctrans("Share") }}</th>
									<th class="px-2 py-1 text-right font-medium">{{ ctrans("Amount") }}</th>
									<th class="px-2 py-1 text-right font-medium">{{ ctrans("Payments") }}</th>
									<th class="px-2 py-1 text-right font-medium">{{ ctrans("A month, last 3") }}</th>
									<th class="py-1 pl-2 text-right font-medium">{{ ctrans("First payment") }}</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="method in shop.methods" :key="method.method" class="border-t border-gray-100">
									<td class="whitespace-nowrap py-1 pr-3">
										<span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-sm" :style="{ backgroundColor: methodColour(method.method) }" aria-hidden="true" />{{ methodLabel(method.method) }}</span>
									</td>
									<td class="whitespace-nowrap px-2 py-1 text-right">{{ percent(Math.round(method.share * 10) / 10) }}</td>
									<td class="whitespace-nowrap px-2 py-1 text-right text-[13px]">{{ wholeMoney(shop.currency, method.amount) }}</td>
									<td class="whitespace-nowrap px-2 py-1 text-right">{{ locale.number(method.payments) }}</td>
									<td class="whitespace-nowrap px-2 py-1 text-right">{{ wholeMoney(shop.currency, method.monthly) }}</td>
									<td class="whitespace-nowrap py-1 pl-2 text-right text-gray-600">{{ method.first_payment ? useFormatTime(method.first_payment, { formatTime: "d MMM yyyy" }) : "—" }}</td>
								</tr>
							</tbody>
						</table>
					</li>
				</ul>
			</DashboardWidgetBox>
		</template>
	</div>
</template>
