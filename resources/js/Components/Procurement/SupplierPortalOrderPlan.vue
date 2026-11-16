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
import { faCalendarAlt, faInfoCircle, faSearch, faBoxes, faCoins, faCube } from "@fal"

library.add(faCalendarAlt, faInfoCircle, faSearch, faBoxes, faCoins, faCube)

export interface OrderPlanProduct {
	id: number
	code: string | null
	name: string | null
	image: ImageProxy | null
	order_on: string | null
	order_month: string | null
	units: number
	cartons: number | null
	units_per_carton: number | null
	value: number
	monthly_units: number
	organisations: string[]
}

export interface OrderPlan {
	as_of: string | null
	currency: string | null
	horizon_days: number
	months: { key: string; products: number; cartons: number; units: number; value: number }[]
	products: OrderPlanProduct[]
}

const props = defineProps<{
	plan?: OrderPlan
	accent: { colour: string; tint: string }
}>()

const locale = useLocaleStore()

const LATER = "later"
const ALL = "all"

const filter = ref<string>(ALL)
const search = ref("")

const today = new Date().toISOString().slice(0, 10)

const monthLabel = (key: string, index: number) => (index === 0 ? ctrans("This month") : useFormatTime(`${key}-01`, { formatTime: "LLLL yyyy" }))

const money = (amount: number) => (props.plan?.currency ? String(locale.currencyFormat(props.plan.currency, amount)) : locale.number(amount))

const expected = computed(() => props.plan?.products.filter((product) => product.order_month !== null) ?? [])

const totals = computed(() => ({
	products: expected.value.length,
	cartons: expected.value.reduce((sum, product) => sum + (product.cartons ?? 0), 0),
	units: expected.value.reduce((sum, product) => sum + product.units, 0),
	value: expected.value.reduce((sum, product) => sum + product.value, 0),
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

const isDueNow = (product: OrderPlanProduct) => product.order_on !== null && product.order_on <= today
</script>

<template>
	<div class="flex flex-col gap-4 lg:gap-6">
		<div class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 shadow-sm">
			<FontAwesomeIcon icon="fal fa-info-circle" class="mt-0.5 shrink-0" :style="{ color: accent.colour }" fixed-width aria-hidden="true" />
			<div class="space-y-1">
				<p>{{ ctrans("What we expect to order from you in the next :days days, from our sales forecast, so you can buy raw materials and plan production in time.", { days: plan?.horizon_days ?? 90 }) }}</p>
				<p class="text-xs text-gray-400">
					{{ ctrans("This is a forecast, not an order: dates and quantities can change. Only the purchase order we send you is binding.") }}
					<template v-if="plan?.as_of">{{ ctrans("Updated :date.", { date: useFormatTime(plan.as_of) }) }}</template>
				</p>
			</div>
		</div>

		<div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
			<SupplierPortalStatCard :label="ctrans('Products to order')" icon="fal fa-cube" :accent="accent" :loading="!plan">
				<div class="text-2xl font-semibold tabular-nums text-gray-900">{{ locale.number(totals.products) }}</div>
			</SupplierPortalStatCard>
			<SupplierPortalStatCard :label="ctrans('Cartons')" icon="fal fa-boxes" :accent="accent" :loading="!plan">
				<div class="text-2xl font-semibold tabular-nums text-gray-900">{{ locale.number(totals.cartons) }}</div>
				<div class="text-xs text-gray-500">{{ ctrans(":units units", { units: locale.number(totals.units) }) }}</div>
			</SupplierPortalStatCard>
			<SupplierPortalStatCard :label="ctrans('Value at your prices')" icon="fal fa-coins" :accent="accent" :loading="!plan">
				<div class="truncate text-2xl font-semibold tabular-nums text-gray-900">{{ money(totals.value) }}</div>
			</SupplierPortalStatCard>
			<SupplierPortalStatCard :label="ctrans('Busiest month')" icon="fal fa-calendar-alt" :accent="accent" :loading="!plan">
				<div v-if="busiestMonth" class="truncate text-2xl font-semibold text-gray-900">{{ busiestMonth }}</div>
				<div v-else class="text-2xl font-semibold text-gray-300">—</div>
			</SupplierPortalStatCard>
		</div>

		<div v-if="plan" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
			<div v-for="(month, index) in plan.months" :key="month.key" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ monthLabel(month.key, index) }}</div>
				<div class="mt-2 text-lg font-semibold tabular-nums text-gray-900">{{ money(month.value) }}</div>
				<div class="mt-0.5 text-xs text-gray-500">
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
					<SegmentedToggle v-model="filter" :options="filterOptions" :aria-label="ctrans('Expected order')" />
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
				{{ plan.products.length ? ctrans("No products match.") : ctrans("We do not expect to order from you in the next :days days.", { days: plan.horizon_days }) }}
			</p>

			<div v-else class="overflow-x-auto">
				<table class="min-w-full divide-y divide-gray-100 text-sm">
					<thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
						<tr>
							<th class="px-4 py-2.5">{{ ctrans("Product") }}</th>
							<th class="px-4 py-2.5">{{ ctrans("Expected order") }}</th>
							<th class="px-4 py-2.5 text-right">{{ ctrans("Quantity") }}</th>
							<th class="px-4 py-2.5 text-right">{{ ctrans("Value at your price") }}</th>
							<th class="px-4 py-2.5 text-right">{{ ctrans("Sells per month") }}</th>
							<th class="px-4 py-2.5">{{ ctrans("For") }}</th>
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
									</div>
								</div>
							</td>
							<td class="whitespace-nowrap px-4 py-3">
								<span v-if="isDueNow(product)" class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-medium text-amber-800">{{ ctrans("Now") }}</span>
								<span v-else-if="product.order_on" class="text-gray-700">{{ useFormatTime(product.order_on, { formatTime: "dd MMM yyyy" }) }}</span>
								<span v-else class="text-xs text-gray-400">{{ ctrans("Not in the next :days days", { days: plan.horizon_days }) }}</span>
							</td>
							<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
								<template v-if="product.units">
									<div class="font-medium text-gray-900">{{ ctrans(":units units", { units: locale.number(product.units) }) }}</div>
									<div v-if="product.cartons" class="text-xs text-gray-500">{{ ctrans(":cartons cartons of :per", { cartons: locale.number(product.cartons), per: locale.number(product.units_per_carton ?? 0) }) }}</div>
								</template>
								<span v-else class="text-gray-300">—</span>
							</td>
							<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">{{ product.value ? money(product.value) : "—" }}</td>
							<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">{{ ctrans(":units units", { units: locale.number(product.monthly_units) }) }}</td>
							<td class="px-4 py-3 text-xs text-gray-500">
								<div v-for="organisation in product.organisations" :key="organisation" class="whitespace-nowrap">{{ organisation }}</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</template>
