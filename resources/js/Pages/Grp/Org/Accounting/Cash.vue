<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { computed } from "vue"
import Chart from "primevue/chart"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCoins, faChartLine, faChartPie, faUniversity, faListUl, faExclamationTriangle, faUnlink } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faCoins, faChartLine, faChartPie, faUniversity, faListUl, faExclamationTriangle, faUnlink)

type Status = "ok" | "stale" | "failed" | "never"
interface Balance {
	currency: string
	amount: number
	reserved: number
	converted: number | null
	converted_reserved: number | null
}
interface Connection {
	id: number
	name: string
	organisation: string
	organisation_code: string
	status: Status
	taken_at: string | null
	failed_at: string | null
	amount: number
	reserved: number
	balances: Balance[]
}
interface Ranked {
	key: string
	label: string
	amount: number
	native?: number
	reserved?: number
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	organisation: string | null
	organisations: { value: string; label: string }[]
	isGroup: boolean
	currency: string
	cash: {
		total: { amount: number; reserved: number; as_of: string | null; stale_amount: number; ago_7: number | null; ago_30: number | null }
		connections: Connection[]
		organisations: Ranked[]
		currencies: Ranked[]
		unconverted: { currency: string; amount: number }[]
		trend: { dates: string[]; totals: number[] }
		not_connected: string[]
		stale_hours: number
	}
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currency, amount)
const shortMoney = (amount: number) =>
	new Intl.NumberFormat(undefined, { style: "currency", currency: props.currency, currencyDisplay: "narrowSymbol", notation: "compact", maximumFractionDigits: 1 }).format(amount)
const ago = (date: string) => useRangeFromNow(date, { addSuffix: true })

const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const organisation = computed({
	get: () => props.organisation ?? "all",
	set: (value: string) => router.get(route("grp.financials.cash.index", value === "all" ? {} : { org: value })),
})

const hasBalances = computed(() => props.cash.connections.some((connection) => connection.balances.length > 0))
const flagged = computed(() => props.cash.connections.filter((connection) => connection.status !== "ok"))

const change = (previous: number | null, days: number) => {
	if (previous === null) {
		return null
	}
	const difference = props.cash.total.amount - previous
	const percent = previous ? ` (${locale.number(Math.abs(Math.round((difference / Math.abs(previous)) * 100)))}%)` : ""
	return { up: difference >= 0, text: `${difference >= 0 ? "▲" : "▼"} ${money(Math.abs(difference))}${percent}`, label: ctrans("vs :days days ago", { days }) }
}
const changes = computed(() => [change(props.cash.total.ago_7, 7), change(props.cash.total.ago_30, 30)].filter((item) => item !== null))

const freshness = (connection: Connection) => {
	if (connection.status === "ok") {
		return ctrans("Read :ago", { ago: ago(connection.taken_at!) })
	}
	if (connection.status === "never") {
		return connection.failed_at ? ctrans("Reading failed :ago, no balance read yet", { ago: ago(connection.failed_at) }) : ctrans("No balance read yet")
	}
	const read = ctrans("last read :ago", { ago: ago(connection.taken_at!) })
	if (connection.status === "failed") {
		return connection.failed_at ? ctrans("Reading failed :ago, :read", { ago: ago(connection.failed_at), read }) : ctrans("Reading failed, :read", { read })
	}
	return ctrans("Not current: :read", { read })
}

const palette = ["#1f845a", "#3b82f6", "#c0399f", "#f59e0b", "#0ea5e9", "#8b5cf6", "#ef4444", "#14b8a6", "#9ca3af"]
const donutCurrencies = computed(() => props.cash.currencies.filter((row) => row.amount > 0).map((row, index) => ({ ...row, color: palette[Math.min(index, palette.length - 1)] })))
const share = (amount: number) => (props.cash.total.amount > 0 ? Math.round((amount / props.cash.total.amount) * 100) : 0)
const donutChart = computed(() => ({
	labels: donutCurrencies.value.map((row) => row.label),
	datasets: [{ data: donutCurrencies.value.map((row) => row.amount), backgroundColor: donutCurrencies.value.map((row) => row.color) }],
}))
const donutOptions = {
	responsive: true,
	maintainAspectRatio: false,
	cutout: "70%",
	hoverOffset: 8,
	plugins: {
		legend: { display: false },
		tooltip: { padding: 10, boxPadding: 6, callbacks: { label: (item: { raw: number }) => money(item.raw) } },
	},
}

const lineChart = computed(() => ({
	labels: props.cash.trend.dates.map((date) => useFormatTime(date, { formatTime: "d MMM" })),
	datasets: [{ label: ctrans("Cash in connected banks"), data: props.cash.trend.totals, borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius: props.cash.trend.dates.length > 40 ? 0 : 2 }],
}))
const lineOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	interaction: { mode: "index", intersect: false },
	plugins: {
		legend: { display: false },
		tooltip: { callbacks: { label: (item: { raw: unknown }) => ` ${money(Number(item.raw))}` } },
	},
	scales: {
		x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 13, maxRotation: 0 } },
		y: { beginAtZero: true, ticks: { callback: (value: number | string) => shortMoney(Number(value)) } },
	},
}))

const barWidth = (row: Ranked, rows: Ranked[]) => `${Math.max(2, (Math.max(row.amount, 0) / Math.max(...rows.map((item) => item.amount), 1)) * 100)}%`
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="space-y-5 px-4 py-4">
		<div class="flex flex-wrap items-end justify-between gap-3">
			<div>
				<div class="text-xs text-gray-500">{{ ctrans("Cash in connected banks") }}</div>
				<div class="flex flex-wrap items-baseline gap-x-3">
					<span v-if="hasBalances" class="text-3xl font-semibold tabular-nums">{{ money(cash.total.amount) }}</span>
					<span v-else class="text-2xl font-light text-gray-500">{{ ctrans("No balance read yet") }}</span>
					<template v-if="changes.length">
						<span v-for="item in changes" :key="item!.label" class="whitespace-nowrap text-sm tabular-nums" :class="item!.up ? 'text-green-700' : 'text-red-600'">
							{{ item!.text }} <span class="text-gray-500">{{ item!.label }}</span>
						</span>
					</template>
					<span v-else-if="hasBalances" class="text-sm text-gray-500">{{ ctrans("Not enough history yet to compare with 7 and 30 days ago") }}</span>
				</div>
				<div v-if="cash.total.as_of" class="mt-0.5 flex flex-wrap gap-x-3 text-xs tabular-nums text-gray-500">
					<span>{{ ctrans("As of :date", { date: useFormatTime(cash.total.as_of, { formatTime: "d MMM yyyy HH:mm" }) }) }}</span>
					<span v-if="cash.total.reserved">· {{ ctrans(":amount on hold", { amount: money(cash.total.reserved) }) }}</span>
				</div>
			</div>
			<SegmentedToggle v-if="organisations.length > 1" v-model="organisation" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
		</div>

		<div v-if="flagged.length" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
			<p class="flex items-center gap-2 font-medium">
				<FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />
				<template v-if="cash.total.stale_amount">{{ ctrans(":amount of this total is not current", { amount: money(cash.total.stale_amount) }) }}</template>
				<template v-else>{{ ctrans("Some connections have no current balance") }}</template>
			</p>
			<ul class="mt-1 space-y-0.5 pl-7 text-xs">
				<li v-for="connection in flagged" :key="connection.id">
					<span class="font-medium">{{ connection.name }}<template v-if="isGroup"> ({{ connection.organisation_code }})</template></span>: {{ freshness(connection) }}
				</li>
			</ul>
			<p class="mt-1 pl-7 text-xs">{{ ctrans("A balance counts as current when it was read in the last :hours hours.", { hours: cash.stale_hours }) }}</p>
		</div>

		<p class="-mt-2 text-xs text-gray-500">
			{{ ctrans("Only banks connected to Aiku are counted.") }}
			<template v-if="cash.not_connected.length">{{ ctrans("Not counted: :banks.", { banks: cash.not_connected.join(", ") }) }}</template>
		</p>

		<div v-if="!cash.connections.length" class="rounded-lg border border-gray-200 bg-white px-6 py-12 text-center">
			<div class="text-lg font-light text-gray-600">{{ ctrans("No bank is connected to Aiku here") }}</div>
			<p class="mt-1 text-sm text-gray-500">{{ ctrans("Cash shows once a bank connection is set up and its balances are read.") }}</p>
		</div>

		<template v-else>
			<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
				<div
					v-for="connection in cash.connections"
					:key="connection.id"
					class="rounded-lg border bg-white px-4 py-3 shadow-sm"
					:class="connection.status === 'ok' ? 'border-gray-200' : 'border-amber-300'">
					<div class="flex items-center gap-2 text-xs text-gray-600">
						<FontAwesomeIcon icon="fal fa-university" fixed-width aria-hidden="true" />
						<span class="truncate font-medium">{{ connection.name }}</span>
						<span v-if="isGroup" class="whitespace-nowrap text-gray-400">{{ connection.organisation_code }}</span>
					</div>
					<div v-if="connection.balances.length" class="mt-1 text-xl font-semibold tabular-nums" :class="connection.status === 'ok' ? '' : 'text-amber-700'">{{ money(connection.amount) }}</div>
					<div v-else class="mt-2 text-sm text-gray-400">{{ ctrans("No balance yet") }}</div>
					<div class="mt-0.5 flex items-center gap-1.5 text-xs" :class="connection.status === 'ok' ? 'text-gray-500' : 'text-amber-700'">
						<span class="h-2 w-2 shrink-0 rounded-full" :class="connection.status === 'ok' ? 'bg-green-600' : 'bg-amber-500'" aria-hidden="true" />
						{{ freshness(connection) }}
					</div>
				</div>
			</div>

			<DashboardWidgetBox v-if="hasBalances" storageKey="cash_trend_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-chart-line" class="text-pink-600" fixed-width aria-hidden="true" />
						{{ ctrans("Cash over time") }}
					</span>
					<span class="text-xs text-gray-400">{{ ctrans("one balance a day, the last read of the day") }}</span>
				</template>
				<div class="grid gap-6 lg:grid-cols-5">
					<div class="h-72 lg:col-span-3">
						<Chart v-if="cash.trend.dates.length > 1" type="line" :data="lineChart" :options="lineOptions" class="h-full" />
						<p v-else class="py-10 text-center text-sm text-gray-500">{{ ctrans("Balances are kept from today: the trend shows from tomorrow.") }}</p>
					</div>
					<div class="lg:col-span-2 lg:border-l lg:border-gray-100 lg:pl-6">
						<p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
							<FontAwesomeIcon icon="fal fa-chart-pie" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
							{{ ctrans("By currency") }}
							<span class="text-xs font-normal text-gray-400">{{ currency }}</span>
						</p>
						<div class="flex flex-wrap items-center gap-4 xl:flex-nowrap">
							<div class="relative h-44 w-44 shrink-0">
								<Chart type="doughnut" :data="donutChart" :options="donutOptions" class="relative z-10 h-full" />
								<div class="pointer-events-none absolute inset-0 z-0 flex flex-col items-center justify-center">
									<span class="text-2xl font-bold tabular-nums">{{ shortMoney(cash.total.amount) }}</span>
									<span class="text-xs text-gray-500">{{ ctrans("Total") }}</span>
								</div>
							</div>
							<table class="text-sm tabular-nums">
								<tbody>
									<tr v-for="row in donutCurrencies" :key="row.key">
										<td class="py-1 pr-3">
											<span class="flex items-center gap-2">
												<span class="h-3 w-3 shrink-0 rounded-sm" :style="{ backgroundColor: row.color }" />
												{{ row.label }}
											</span>
										</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right text-xs text-gray-500">{{ row.key === currency ? "" : locale.currencyFormat(row.key, row.native ?? 0) }}</td>
										<td class="whitespace-nowrap py-1 pr-3 text-right font-medium">{{ shortMoney(row.amount) }}</td>
										<td class="py-1 text-right text-gray-500">{{ share(row.amount) }}%</td>
									</tr>
								</tbody>
							</table>
						</div>
						<p v-if="cash.unconverted.length" class="mt-3 text-xs text-amber-700">
							{{ ctrans("Not in the total, no exchange rate is known: :amounts", { amounts: cash.unconverted.map((row) => locale.currencyFormat(row.currency, row.amount)).join(", ") }) }}
						</p>
					</div>
				</div>
			</DashboardWidgetBox>

			<DashboardWidgetBox v-if="hasBalances" storageKey="cash_balances_collapsed">
				<template #header>
					<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-coins" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
						{{ ctrans("Balances") }}
					</span>
					<span class="text-xs text-gray-400">{{ ctrans("by bank connection and currency") }}</span>
				</template>
				<div class="overflow-x-auto">
					<table class="w-full text-sm tabular-nums">
						<thead class="whitespace-nowrap text-left text-xs uppercase text-gray-500">
							<tr>
								<th v-if="isGroup" class="py-1.5 pr-4 font-medium">{{ ctrans("Organisation") }}</th>
								<th class="py-1.5 pr-4 font-medium">{{ ctrans("Bank") }}</th>
								<th class="py-1.5 pr-4 font-medium">{{ ctrans("Currency") }}</th>
								<th class="py-1.5 pr-4 text-right font-medium">{{ ctrans("Balance") }}</th>
								<th class="py-1.5 pr-4 text-right font-medium">{{ ctrans("On hold") }}</th>
								<th class="py-1.5 pr-4 text-right font-medium">{{ ctrans("In :currency", { currency }) }}</th>
								<th class="py-1.5 font-medium">{{ ctrans("Balance read") }}</th>
							</tr>
						</thead>
						<tbody v-for="connection in cash.connections.filter((item) => item.balances.length)" :key="connection.id" class="border-t border-gray-200">
							<tr v-for="(balance, index) in connection.balances" :key="balance.currency" :class="index ? 'border-t border-gray-100' : ''">
								<td v-if="isGroup && !index" :rowspan="connection.balances.length" class="whitespace-nowrap py-1.5 pr-4 align-top text-gray-700">{{ connection.organisation_code }}</td>
								<td v-if="!index" :rowspan="connection.balances.length" class="py-1.5 pr-4 align-top">
									<div class="font-medium">{{ connection.name }}</div>
									<div class="text-xs text-gray-500">{{ money(connection.amount) }}</div>
								</td>
								<td class="whitespace-nowrap py-1.5 pr-4">{{ balance.currency }}</td>
								<td class="whitespace-nowrap py-1.5 pr-4 text-right">{{ locale.currencyFormat(balance.currency, balance.amount) }}</td>
								<td class="whitespace-nowrap py-1.5 pr-4 text-right text-gray-500">{{ balance.reserved ? locale.currencyFormat(balance.currency, balance.reserved) : "" }}</td>
								<td class="whitespace-nowrap py-1.5 pr-4 text-right font-medium">
									<template v-if="balance.converted !== null">{{ money(balance.converted) }}</template>
									<span v-else class="text-xs font-normal text-amber-700">{{ ctrans("no exchange rate") }}</span>
								</td>
								<td v-if="!index" :rowspan="connection.balances.length" class="py-1.5 align-top text-xs" :class="connection.status === 'ok' ? 'text-gray-500' : 'text-amber-700'">
									<span class="flex items-center gap-1.5">
										<span class="h-2 w-2 shrink-0 rounded-full" :class="connection.status === 'ok' ? 'bg-green-600' : 'bg-amber-500'" aria-hidden="true" />
										{{ freshness(connection) }}
									</span>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</DashboardWidgetBox>
		</template>

		<DashboardWidgetBox storageKey="cash_coverage_collapsed">
			<template #header>
				<span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
					<FontAwesomeIcon icon="fal fa-list-ul" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("What is counted") }}
				</span>
				<span class="text-xs text-gray-400">{{ ctrans("this is not all our money") }}</span>
			</template>
			<div class="grid gap-6" :class="isGroup ? 'lg:grid-cols-2' : ''">
				<div v-if="isGroup">
					<p class="mb-2 text-sm font-semibold text-gray-600">{{ ctrans("By organisation") }}</p>
					<p v-if="!cash.organisations.length" class="text-sm text-gray-400">{{ ctrans("No bank is connected to Aiku here") }}</p>
					<ul v-else class="space-y-2 text-sm">
						<li v-for="row in cash.organisations" :key="row.key">
							<div class="flex items-baseline gap-2 tabular-nums">
								<span class="min-w-0 flex-1 truncate">{{ row.label }}</span>
								<span class="whitespace-nowrap font-medium">{{ money(row.amount) }}</span>
							</div>
							<div class="mt-1 h-1.5 w-full rounded-full bg-gray-100">
								<div class="h-1.5 rounded-full" :style="{ width: barWidth(row, cash.organisations), backgroundColor: '#f59e0b' }" />
							</div>
						</li>
					</ul>
				</div>
				<div :class="isGroup ? 'lg:border-l lg:border-gray-100 lg:pl-6' : ''">
					<p class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-600">
						<FontAwesomeIcon icon="fal fa-unlink" class="text-amber-500" fixed-width aria-hidden="true" />
						{{ ctrans("Not connected") }}
					</p>
					<p class="text-sm text-gray-600">{{ ctrans("Money in these banks is not in any figure on this page:") }}</p>
					<ul class="mt-2 flex flex-wrap gap-2 text-sm">
						<li v-for="bank in cash.not_connected" :key="bank" class="rounded-full border border-gray-200 bg-gray-50 px-3 py-0.5 text-gray-700">{{ bank }}</li>
					</ul>
				</div>
			</div>
		</DashboardWidgetBox>
	</div>
</template>
