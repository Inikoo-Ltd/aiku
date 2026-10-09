<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, inject } from "vue"
import Chart from "primevue/chart"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"

interface Month {
	month: string
	number: number
	value: number
}

interface Confirmation {
	id: number
	name: string
	number: number
	median_days: number
	average_days: number
	slowest_days: number
}

interface OnTime {
	id: number
	name: string
	number: number
	on_time: number
	on_time_percent: number
	average_days_late: number | null
}

interface OrganisationRow {
	name: string
	code: string
	number: number
	value: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: {
		currency: string
		from: string
		months: Month[]
		confirmation: Confirmation[]
		on_time: OnTime[]
		organisations: OrganisationRow[]
	}
}>()

const locale = inject("locale", aikuLocaleStructure)

const money = (amount: number) => locale.currencyFormat(props.data.currency, amount)
const monthLabel = (month: string) => new Date(`${month}-01T00:00:00`).toLocaleDateString(undefined, { month: "short", year: "2-digit" })

const monthlyChart = computed(() => ({
	labels: props.data.months.map((month) => monthLabel(month.month)),
	datasets: [
		{ type: "bar", label: ctrans("Value"), data: props.data.months.map((month) => month.value), backgroundColor: "#9ca3af", yAxisID: "value", order: 2 },
		{ type: "line", label: ctrans("Orders"), data: props.data.months.map((month) => month.number), borderColor: "#c0399f", backgroundColor: "#c0399f", borderWidth: 1.5, pointRadius: 3, tension: 0, yAxisID: "count", order: 1 },
	],
}))

const monthlyOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { position: "bottom", labels: { boxWidth: 12 } },
		tooltip: {
			callbacks: {
				label: (item: { dataset: { label: string; yAxisID: string }; raw: number }) =>
					`${item.dataset.label}: ${item.dataset.yAxisID === "value" ? money(item.raw) : locale.number(item.raw)}`,
			},
		},
	},
	scales: {
		x: { grid: { display: false } },
		value: { position: "left", beginAtZero: true, ticks: { callback: (value: number) => locale.number(value) } },
		count: { position: "right", beginAtZero: true, grid: { display: false }, ticks: { precision: 0 } },
	},
}))

const totals = computed(() => ({
	number: props.data.months.reduce((sum, month) => sum + month.number, 0),
	value: props.data.months.reduce((sum, month) => sum + month.value, 0),
}))

const topValue = computed(() => Math.max(1, ...props.data.organisations.map((row) => row.value)))
const slowest = computed(() => Math.max(1, ...props.data.confirmation.map((row) => row.median_days)))

const onTimeClass = (percent: number) => (percent >= 90 ? "bg-green-500" : percent >= 70 ? "bg-amber-400" : "bg-red-500")

const days = (count: number) => (count === 1 ? ctrans("1 day") : ctrans(":count days", { count: locale.number(count) }))
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />
	<div class="space-y-4 p-4">
		<p class="text-xs text-gray-500">{{ ctrans("Last 12 months, from :date. Only orders that were submitted to a supplier are counted.", { date: useFormatTime(data.from) }) }}</p>

		<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
			<div class="flex flex-wrap items-baseline justify-between gap-2">
				<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Orders submitted per month") }}</h2>
				<span class="text-xs text-gray-500">
					{{ ctrans(":count orders", { count: locale.number(totals.number) }) }} · {{ money(totals.value) }}
				</span>
			</div>
			<div class="mt-3 h-64">
				<Chart type="bar" :data="monthlyChart" :options="monthlyOptions" class="h-full" />
			</div>
		</section>

		<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
			<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Days from submitted to confirmed, per supplier") }}</h2>
				<p class="mt-0.5 text-xs text-gray-500">{{ ctrans("Orders confirmed in the period, slowest median first") }}</p>
				<p v-if="!data.confirmation.length" class="py-6 text-center text-xs text-gray-400">{{ ctrans("No confirmed orders in the period") }}</p>
				<table v-else class="mt-3 w-full text-sm">
					<thead class="text-xs uppercase tracking-wide text-gray-400">
						<tr>
							<th class="pb-1 text-left font-medium">{{ ctrans("Supplier") }}</th>
							<th class="pb-1 text-right font-medium">{{ ctrans("Orders") }}</th>
							<th class="pb-1 text-right font-medium">{{ ctrans("Median") }}</th>
							<th class="pb-1 text-right font-medium">{{ ctrans("Average") }}</th>
							<th class="pb-1 text-right font-medium">{{ ctrans("Slowest") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in [...data.confirmation].sort((a, b) => b.median_days - a.median_days)" :key="row.id">
							<td class="py-1.5 pr-2">
								<div class="truncate" :title="row.name">{{ row.name }}</div>
								<div class="mt-0.5 h-1 rounded bg-gray-100">
									<div class="h-1 rounded bg-gray-400" :style="{ width: `${(100 * row.median_days) / slowest}%` }" />
								</div>
							</td>
							<td class="py-1.5 text-right tabular-nums text-gray-500">{{ locale.number(row.number) }}</td>
							<td class="py-1.5 text-right tabular-nums font-medium" :class="row.median_days >= 14 ? 'text-red-600' : row.median_days >= 7 ? 'text-amber-600' : 'text-gray-800'">{{ days(row.median_days) }}</td>
							<td class="py-1.5 text-right tabular-nums text-gray-600">{{ days(row.average_days) }}</td>
							<td class="py-1.5 text-right tabular-nums text-gray-500">{{ days(row.slowest_days) }}</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
				<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("On-time delivery, per supplier") }}</h2>
				<p class="mt-0.5 text-xs text-gray-500">{{ ctrans("Container received on or before the order's expected date; orders without an expected date are left out") }}</p>
				<p v-if="!data.on_time.length" class="py-6 text-center text-xs text-gray-400">{{ ctrans("No received deliveries with an expected date in the period") }}</p>
				<table v-else class="mt-3 w-full text-sm">
					<thead class="text-xs uppercase tracking-wide text-gray-400">
						<tr>
							<th class="pb-1 text-left font-medium">{{ ctrans("Supplier") }}</th>
							<th class="pb-1 text-right font-medium">{{ ctrans("Received") }}</th>
							<th class="pb-1 text-right font-medium">{{ ctrans("On time") }}</th>
							<th class="pb-1 text-right font-medium">{{ ctrans("Avg late by") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="row in [...data.on_time].sort((a, b) => a.on_time_percent - b.on_time_percent)" :key="row.id">
							<td class="py-1.5 pr-2">
								<div class="truncate" :title="row.name">{{ row.name }}</div>
								<div class="mt-0.5 h-1 rounded bg-gray-100">
									<div class="h-1 rounded" :class="onTimeClass(row.on_time_percent)" :style="{ width: `${row.on_time_percent}%` }" />
								</div>
							</td>
							<td class="py-1.5 text-right tabular-nums text-gray-500">{{ locale.number(row.number) }}</td>
							<td class="py-1.5 text-right tabular-nums font-medium" :class="row.on_time_percent >= 90 ? 'text-green-700' : row.on_time_percent >= 70 ? 'text-amber-600' : 'text-red-600'">
								{{ row.on_time_percent }}% <span class="text-xs font-normal text-gray-400">({{ row.on_time }}/{{ row.number }})</span>
							</td>
							<td class="py-1.5 text-right tabular-nums text-gray-600">{{ row.average_days_late === null ? "—" : days(row.average_days_late) }}</td>
						</tr>
					</tbody>
				</table>
			</section>
		</div>

		<section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
			<h2 class="text-sm font-semibold text-gray-700">{{ ctrans("Value per client organisation") }}</h2>
			<p v-if="!data.organisations.length" class="py-6 text-center text-xs text-gray-400">{{ ctrans("No orders in the period") }}</p>
			<div v-else class="mt-3 space-y-2">
				<div v-for="row in data.organisations" :key="row.code" class="grid grid-cols-[8rem_1fr_auto] items-center gap-3 text-sm">
					<span class="truncate font-medium text-gray-800" :title="row.name">{{ row.name }}</span>
					<div class="h-3 rounded bg-gray-100">
						<div class="h-3 rounded bg-gray-500" :style="{ width: `${(100 * row.value) / topValue}%` }" />
					</div>
					<span class="whitespace-nowrap text-right tabular-nums text-gray-600">
						{{ money(row.value) }}
						<span class="text-xs text-gray-400">· {{ ctrans(":count orders", { count: locale.number(row.number) }) }}</span>
					</span>
				</div>
			</div>
		</section>
	</div>
</template>
