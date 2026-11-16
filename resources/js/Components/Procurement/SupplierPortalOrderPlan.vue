<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-16 14:30
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import InputText from "primevue/inputtext"
import IconField from "primevue/iconfield"
import InputIcon from "primevue/inputicon"
import Image from "@common/Components/Image.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import SupplierPortalStatCard from "@/Components/Procurement/SupplierPortalStatCard.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import type { Image as ImageProxy } from "@/types/Image"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendarAlt, faInfoCircle, faSearch, faBoxes, faCoins, faCube, faExclamationTriangle, faClipboard } from "@fal"

library.add(faCalendarAlt, faInfoCircle, faSearch, faBoxes, faCoins, faCube, faExclamationTriangle, faClipboard)

type PlanStatus = "estimate" | "no_order"
type NoOrderReason = "covered" | "out_of_season" | "no_sales" | "no_forecast"
type PlanFlag = "little_history" | "irregular_sales" | "stale_forecast" | "lead_time_estimated" | "no_carton_size"

export interface OrderPlanProduct {
	id: number
	code: string | null
	name: string | null
	image: ImageProxy | null
	status: PlanStatus
	reason: NoOrderReason | null
	flags: PlanFlag[]
	order_on: string | null
	order_from: string | null
	order_to: string | null
	arrive_from: string | null
	arrive_to: string | null
	order_month: string | null
	units_low: number
	units: number
	units_high: number
	cartons_low: number | null
	cartons: number | null
	cartons_high: number | null
	value_low: number
	value: number
	value_high: number
	units_per_carton: number | null
	monthly_units: number
	organisations: { name: string; status: PlanStatus; reason: NoOrderReason | null; order_on: string | null; order_from: string | null; order_to: string | null; units_low: number; units_high: number }[]
	open_orders: { id: number; reference: string; units: number }[]
}

export interface OrderPlan {
	as_of: string | null
	currency: string | null
	horizon_days: number
	months: { key: string; products: number; cartons: number; units: number; value: number; value_low: number; value_high: number }[]
	products: OrderPlanProduct[]
}

const props = defineProps<{
	plan?: OrderPlan
	accent: { colour: string; tint: string }
}>()

const emit = defineEmits<{ open: [purchaseOrderId: number] }>()

const locale = useLocaleStore()

const LATER = "later"
const ALL = "all"

const filter = ref<string>(ALL)
const search = ref("")

const horizon = computed(() => props.plan?.horizon_days ?? 90)

const monthLabel = (key: string, index: number) => (index === 0 ? ctrans("This month") : useFormatTime(`${key}-01`, { formatTime: "LLLL yyyy" }))

const money = (amount: number) => (props.plan?.currency ? String(locale.currencyFormat(props.plan.currency, amount)) : locale.number(amount))

const span = (low: number, high: number, format: (value: number) => string) => (low === high ? format(low) : `${format(low)} – ${format(high)}`)

const day = (date: string) => useFormatTime(date, { formatTime: "dd MMM yyyy" })

const dateSpan = (from: string | null, to: string | null) => {
	if (!from) {
		return "—"
	}
	if (!to || from === to) {
		return day(from)
	}

	return `${useFormatTime(from, { formatTime: from.slice(0, 4) === to.slice(0, 4) ? "dd MMM" : "dd MMM yyyy" })} – ${day(to)}`
}

const flagLabels = computed<Record<PlanFlag, string>>(() => ({
	little_history: ctrans("Little sales history: estimated from similar products"),
	irregular_sales: ctrans("Irregular sales"),
	stale_forecast: ctrans("Forecast not recent"),
	lead_time_estimated: ctrans("Lead time estimated"),
	no_carton_size: ctrans("Carton size missing"),
}))

const reasonLabels = computed<Record<NoOrderReason, string>>(() => ({
	covered: ctrans("Enough stock for the next :days days", { days: horizon.value }),
	out_of_season: ctrans("Out of season"),
	no_sales: ctrans("No recent sales"),
	no_forecast: ctrans("No forecast yet"),
}))

const expected = computed(() => props.plan?.products.filter((product) => product.order_month !== null) ?? [])

const sum = (key: "cartons" | "cartons_low" | "cartons_high" | "units" | "value" | "value_low" | "value_high") =>
	expected.value.reduce((total, product) => total + (product[key] ?? 0), 0)

const totals = computed(() => ({
	products: expected.value.length,
	cartons: sum("cartons"),
	cartons_low: sum("cartons_low"),
	cartons_high: sum("cartons_high"),
	units: sum("units"),
	value: sum("value"),
	value_low: sum("value_low"),
	value_high: sum("value_high"),
}))

const busiestMonth = computed(() => {
	const months = props.plan?.months ?? []
	const index = months.reduce((top, month, position) => (month.value > months[top].value ? position : top), 0)

	return months[index]?.value ? monthLabel(months[index].key, index) : null
})

const filterOptions = computed(() => [
	{ label: ctrans("All"), value: ALL, count: props.plan?.products.length ?? 0 },
	...(props.plan?.months ?? []).map((month, index) => ({ label: monthLabel(month.key, index), value: month.key, count: month.products })),
	{ label: ctrans("Later"), value: LATER, count: props.plan?.products.filter((product) => product.order_month === null).length ?? 0 },
])

const rows = computed(() => {
	const term = search.value.trim().toLowerCase()

	return (props.plan?.products ?? [])
		.filter((product) => filter.value === ALL || (filter.value === LATER ? product.order_month === null : product.order_month === filter.value))
		.filter((product) => !term || `${product.code ?? ""} ${product.name ?? ""}`.toLowerCase().includes(term))
})

const quantity = (product: OrderPlanProduct) => span(product.units_low, product.units_high, (units) => locale.number(units))

const cartons = (product: OrderPlanProduct) =>
	product.cartons_high ? span(product.cartons_low ?? 0, product.cartons_high, (count) => locale.number(count)) : null

const organisationLine = (organisation: OrderPlanProduct["organisations"][number]) =>
	organisation.status === "estimate"
		? `${dateSpan(organisation.order_from, organisation.order_to)} · ${span(organisation.units_low, organisation.units_high, (units) => locale.number(units))}`
		: organisation.reason
			? reasonLabels.value[organisation.reason]
			: ""
</script>

<template>
	<div class="flex flex-col gap-4 lg:gap-6">
		<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
			<div class="flex items-start gap-3">
				<FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0" fixed-width aria-hidden="true" />
				<p class="font-medium">{{ ctrans("For planning only. This is not a purchase order or authority to buy materials or begin production. Quantities and dates may change.") }}</p>
			</div>
		</div>

		<div class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 shadow-sm">
			<FontAwesomeIcon icon="fal fa-info-circle" class="mt-0.5 shrink-0" :style="{ color: accent.colour }" fixed-width aria-hidden="true" />
			<div class="space-y-1">
				<p>{{ ctrans("What we may order from you in the next :days days, so you can plan raw materials and production capacity.", { days: horizon }) }}</p>
				<p class="text-xs text-gray-500">
					{{ ctrans("System estimate from our sales forecast for each of our companies, not yet reviewed by AW. Each range covers about 8 in 10 likely outcomes; the order window is when we may place the order, the delivery window when the goods would reach us.") }}
					<template v-if="plan?.as_of">{{ ctrans("Calculated :date.", { date: useFormatTime(plan.as_of) }) }}</template>
				</p>
			</div>
		</div>

		<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
			<SupplierPortalStatCard :label="ctrans('Products to order')" icon="fal fa-cube" :accent="accent" :loading="!plan">
				<div class="text-2xl font-semibold tabular-nums text-gray-900">{{ locale.number(totals.products) }}</div>
			</SupplierPortalStatCard>
			<SupplierPortalStatCard :label="ctrans('Cartons')" icon="fal fa-boxes" :accent="accent" :loading="!plan">
				<div class="text-2xl font-semibold tabular-nums text-gray-900">{{ locale.number(totals.cartons) }}</div>
				<div class="text-xs tabular-nums text-gray-500">{{ ctrans("Range :range", { range: span(totals.cartons_low, totals.cartons_high, (count) => locale.number(count)) }) }}</div>
			</SupplierPortalStatCard>
			<SupplierPortalStatCard :label="ctrans('Value at your prices')" icon="fal fa-coins" :accent="accent" :loading="!plan">
				<div class="truncate text-2xl font-semibold tabular-nums text-gray-900">{{ money(totals.value) }}</div>
				<div class="truncate text-xs tabular-nums text-gray-500">{{ ctrans("Range :range", { range: span(totals.value_low, totals.value_high, money) }) }}</div>
			</SupplierPortalStatCard>
			<SupplierPortalStatCard :label="ctrans('Busiest month')" icon="fal fa-calendar-alt" :accent="accent" :loading="!plan">
				<div v-if="busiestMonth" class="truncate text-2xl font-semibold text-gray-900">{{ busiestMonth }}</div>
				<div v-else class="text-2xl font-semibold text-gray-300">—</div>
			</SupplierPortalStatCard>
		</div>

		<div v-if="plan" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
			<div v-for="(month, index) in plan.months" :key="month.key" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ monthLabel(month.key, index) }}</div>
				<div class="mt-2 truncate text-lg font-semibold tabular-nums text-gray-900">{{ money(month.value) }}</div>
				<div class="truncate text-xs tabular-nums text-gray-400">{{ span(month.value_low, month.value_high, money) }}</div>
				<div class="mt-1 text-xs text-gray-500">
					{{ ctrans(":products products · :cartons cartons", { products: locale.number(month.products), cartons: locale.number(month.cartons) }) }}
				</div>
				<div class="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100">
					<div class="h-full rounded-full" :style="{ width: `${totals.value ? Math.round((month.value / totals.value) * 100) : 0}%`, backgroundColor: accent.colour }" />
				</div>
			</div>
		</div>
		<div v-else class="skeleton h-28 rounded-lg" />

		<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
			<div class="flex flex-col gap-3 border-b border-gray-100 p-4 lg:flex-row lg:items-center lg:justify-between">
				<div class="overflow-x-auto">
					<SegmentedToggle v-model="filter" :options="filterOptions" :aria-label="ctrans('Order window')" />
				</div>
				<IconField class="w-full lg:w-64">
					<InputIcon><FontAwesomeIcon icon="fal fa-search" fixed-width aria-hidden="true" /></InputIcon>
					<InputText v-model="search" size="small" class="w-full" :placeholder="ctrans('Search code or name')" />
				</IconField>
			</div>

			<div v-if="!plan" class="space-y-2 p-4">
				<div v-for="index in 5" :key="index" class="skeleton h-12 rounded" />
			</div>

			<p v-else-if="!rows.length" class="px-4 py-10 text-center text-sm text-gray-500">
				{{ plan.products.length ? ctrans("No products match.") : ctrans("We do not expect to order from you in the next :days days.", { days: horizon }) }}
			</p>

			<template v-else>
				<div class="hidden overflow-x-auto md:block">
					<table class="min-w-full divide-y divide-gray-100 text-sm">
						<thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
							<tr>
								<th class="px-4 py-2.5">{{ ctrans("Product") }}</th>
								<th class="px-4 py-2.5">{{ ctrans("Status") }}</th>
								<th class="px-4 py-2.5">{{ ctrans("Order window") }}</th>
								<th class="px-4 py-2.5">{{ ctrans("Delivery window") }}</th>
								<th class="px-4 py-2.5 text-right">{{ ctrans("Quantity") }}</th>
								<th class="px-4 py-2.5 text-right">{{ ctrans("Value at your price") }}</th>
								<th class="px-4 py-2.5 text-right">{{ ctrans("Sells per month") }}</th>
								<th class="px-4 py-2.5">{{ ctrans("Our companies") }}</th>
								<th class="px-4 py-2.5">{{ ctrans("Open orders") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="product in rows" :key="product.id" class="align-top">
								<td class="px-4 py-3">
									<div class="flex min-w-[14rem] items-start gap-3">
										<div class="h-10 w-10 shrink-0 overflow-hidden rounded border border-gray-100 bg-gray-50">
											<Image v-if="product.image" :src="product.image" class="h-full w-full object-cover" :alt="product.code ?? ''" />
										</div>
										<div class="min-w-0">
											<div class="whitespace-nowrap font-medium text-gray-900">{{ product.code }}</div>
											<div class="text-xs text-gray-500">{{ product.name }}</div>
											<div v-if="product.flags.length" class="mt-1 flex flex-wrap gap-1">
												<span v-for="flag in product.flags" :key="flag" class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-800">{{ flagLabels[flag] }}</span>
											</div>
										</div>
									</div>
								</td>
								<td class="px-4 py-3">
									<template v-if="product.status === 'estimate'">
										<div class="whitespace-nowrap font-medium text-gray-800">{{ ctrans("System estimate") }}</div>
										<div class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Not yet reviewed by AW") }}</div>
									</template>
									<template v-else>
										<div class="whitespace-nowrap font-medium text-gray-500">{{ ctrans("No order currently indicated") }}</div>
										<div v-if="product.reason" class="text-xs text-gray-400">{{ reasonLabels[product.reason] }}</div>
									</template>
								</td>
								<td class="whitespace-nowrap px-4 py-3 text-gray-700">
									{{ dateSpan(product.order_from, product.order_to) }}
									<div v-if="product.order_on" class="text-xs text-gray-400">{{ ctrans("Most likely :date", { date: day(product.order_on) }) }}</div>
								</td>
								<td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ dateSpan(product.arrive_from, product.arrive_to) }}</td>
								<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
									<template v-if="product.status === 'estimate'">
										<div class="font-medium text-gray-900">{{ ctrans(":units units", { units: quantity(product) }) }}</div>
										<div v-if="cartons(product)" class="text-xs text-gray-500">{{ ctrans(":cartons cartons of :per", { cartons: cartons(product) ?? "", per: locale.number(product.units_per_carton ?? 0) }) }}</div>
									</template>
									<span v-else class="text-gray-300">—</span>
								</td>
								<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">{{ product.status === "estimate" ? span(product.value_low, product.value_high, money) : "—" }}</td>
								<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">{{ ctrans(":units units", { units: locale.number(product.monthly_units) }) }}</td>
								<td class="px-4 py-3 text-xs">
									<div v-for="organisation in product.organisations" :key="organisation.name" class="whitespace-nowrap">
										<span class="text-gray-700">{{ organisation.name }}</span>
										<span class="text-gray-400"> · {{ organisationLine(organisation) }}</span>
									</div>
								</td>
								<td class="px-4 py-3">
									<button
										v-for="order in product.open_orders"
										:key="order.id"
										type="button"
										class="block whitespace-nowrap text-xs text-[var(--app-accent)] hover:underline"
										@click="emit('open', order.id)">
										{{ order.reference }} · {{ locale.number(order.units) }}
									</button>
									<span v-if="!product.open_orders.length" class="text-gray-300">—</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<ul class="divide-y divide-gray-100 md:hidden">
					<li v-for="product in rows" :key="product.id" class="space-y-3 p-4">
						<div class="flex items-start gap-3">
							<div class="h-12 w-12 shrink-0 overflow-hidden rounded border border-gray-100 bg-gray-50">
								<Image v-if="product.image" :src="product.image" class="h-full w-full object-cover" :alt="product.code ?? ''" />
							</div>
							<div class="min-w-0 flex-1">
								<div class="whitespace-nowrap font-medium text-gray-900">{{ product.code }}</div>
								<div class="text-xs text-gray-500">{{ product.name }}</div>
								<div class="mt-1 text-xs" :class="product.status === 'estimate' ? 'text-gray-700' : 'text-gray-400'">
									{{ product.status === "estimate" ? `${ctrans("System estimate")} · ${ctrans("Not yet reviewed by AW")}` : `${ctrans("No order currently indicated")}${product.reason ? ` · ${reasonLabels[product.reason]}` : ""}` }}
								</div>
							</div>
						</div>
						<dl v-if="product.status === 'estimate'" class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
							<div>
								<dt class="text-gray-400">{{ ctrans("Order window") }}</dt>
								<dd class="text-gray-800">{{ dateSpan(product.order_from, product.order_to) }}</dd>
							</div>
							<div>
								<dt class="text-gray-400">{{ ctrans("Delivery window") }}</dt>
								<dd class="text-gray-800">{{ dateSpan(product.arrive_from, product.arrive_to) }}</dd>
							</div>
							<div>
								<dt class="text-gray-400">{{ ctrans("Quantity") }}</dt>
								<dd class="tabular-nums text-gray-800">
									{{ ctrans(":units units", { units: quantity(product) }) }}
									<span v-if="cartons(product)" class="text-gray-500">({{ ctrans(":cartons cartons of :per", { cartons: cartons(product) ?? "", per: locale.number(product.units_per_carton ?? 0) }) }})</span>
								</dd>
							</div>
							<div>
								<dt class="text-gray-400">{{ ctrans("Value at your price") }}</dt>
								<dd class="tabular-nums text-gray-800">{{ span(product.value_low, product.value_high, money) }}</dd>
							</div>
						</dl>
						<div class="space-y-0.5 text-xs">
							<div v-for="organisation in product.organisations" :key="organisation.name">
								<span class="text-gray-700">{{ organisation.name }}</span>
								<span class="text-gray-400"> · {{ organisationLine(organisation) }}</span>
							</div>
						</div>
						<div v-if="product.flags.length || product.open_orders.length" class="flex flex-wrap gap-1">
							<span v-for="flag in product.flags" :key="flag" class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] text-amber-800">{{ flagLabels[flag] }}</span>
							<button
								v-for="order in product.open_orders"
								:key="order.id"
								type="button"
								class="flex items-center gap-1 whitespace-nowrap rounded border border-gray-200 px-1.5 py-0.5 text-[11px] text-[var(--app-accent)]"
								@click="emit('open', order.id)">
								<FontAwesomeIcon icon="fal fa-clipboard" fixed-width aria-hidden="true" />
								{{ order.reference }} · {{ locale.number(order.units) }}
							</button>
						</div>
					</li>
				</ul>
			</template>
		</div>
	</div>
</template>
