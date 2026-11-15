<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026 19:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import { computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHandHoldingUsd, faChartLine, faFileInvoiceDollar, faUserClock, faPiggyBank } from "@fal"

library.add(faHandHoldingUsd, faChartLine, faFileInvoiceDollar, faUserClock, faPiggyBank)

interface RouteTarget {
	name: string
	parameters: string[]
}
interface Row {
	id: number
	reference: string | null
	invoice_route: RouteTarget | null
	customer_name: string | null
	customer_reference: string
	customer_route: RouteTarget
	organisation_code: string
	shop_code: string
	currency_code: string
	date: string | null
	oldest: string | null
	days_overdue: number | null
	invoices: number | null
	total_amount: number | null
	native: number | null
	amount: number | null
	balance: number | null
	is_cash_on_delivery: boolean
	is_pastpay: boolean
	company: boolean
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	data: object
	section: "invoices" | "debtors" | "balances"
	organisation: string | null
	organisations: { value: string; label: string }[]
	isGroup: boolean
	currency: string
	age: string | null
	owedBy: "customers" | "companies" | "marketplaces" | "legacy"
	side: "credit" | "debt" | null
	summary: { count: number; total: number }
}>()

const locale = useLocaleStore()
const money = (amount: number) => locale.currencyFormat(props.currency, amount)

const reload = (changes: Record<string, string | null>) => {
	const query: Record<string, string | null> = { org: props.organisation, age: props.age, owed_by: props.owedBy === "customers" ? null : props.owedBy, side: props.side, ...changes }
	const parameters = Object.fromEntries(Object.entries({ ...route().params, ...query, page: null }).filter(([, value]) => value !== null))
	router.get(route(route().current() as string, parameters))
}
const pick = (key: string) => (value: string) => reload({ [key]: value === "all" || value === "customers" ? null : value })

const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const ageOptions = [
	{ label: ctrans("All"), value: "all" },
	{ label: ctrans("Not yet due"), value: "not_due" },
	{ label: ctrans("1 to 30 days"), value: "1_30" },
	{ label: ctrans("31 to 60 days"), value: "31_60" },
	{ label: ctrans("61 to 90 days"), value: "61_90" },
	{ label: ctrans("Over 90 days"), value: "over_90" },
]
const owedByOptions = [
	{ label: ctrans("Customers"), value: "customers" },
	{ label: ctrans("Our companies"), value: "companies" },
	{ label: ctrans("Marketplaces"), value: "marketplaces" },
	{ label: ctrans("Legacy from Aurora"), value: "legacy" },
]
const sideOptions = [
	{ label: ctrans("All"), value: "all" },
	{ label: ctrans("Credit we hold"), value: "credit" },
	{ label: ctrans("Below zero"), value: "debt" },
]

const summaryLabel = computed(() => {
	if (props.section === "balances") {
		return props.side ? ctrans("Balances") : ctrans("Balances, credit less what is below zero")
	}
	if (props.section === "invoices" && props.owedBy === "legacy") {
		return ctrans("Legacy from Aurora, unverified")
	}
	if (props.section === "invoices" && props.owedBy === "marketplaces") {
		return ctrans("Marketplaces still to pay out")
	}
	return props.section === "invoices" && props.owedBy === "companies" ? ctrans("Owed by companies of the group") : ctrans("Owed by customers")
})
const overdue = (days: number) => (days > 0 ? ctrans(":days days", { days: locale.number(days) }) : ctrans("Not yet due"))
const overdueClass = (days: number) => (days > 90 ? "text-red-700" : days > 30 ? "text-amber-700" : "text-gray-700")
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="flex flex-wrap items-end justify-between gap-3 px-4 pt-4">
		<div>
			<div class="text-xs text-gray-500">{{ summaryLabel }}</div>
			<div class="flex flex-wrap items-baseline gap-x-3">
				<span class="text-2xl font-semibold tabular-nums">{{ money(summary.total) }}</span>
				<span class="text-sm tabular-nums text-gray-500">
					{{ section === "invoices" ? ctrans(":count invoices", { count: locale.number(summary.count) }) : ctrans(":count customers", { count: locale.number(summary.count) }) }}
				</span>
			</div>
			<p v-if="section === 'invoices'" class="mt-0.5 text-xs text-gray-500">
				{{ ctrans("An invoice is due on its date plus the payment terms of the customer; without terms it is due on its date.") }}
			</p>
			<p v-else-if="section === 'balances'" class="mt-0.5 text-xs text-gray-500">
				{{ ctrans("A positive balance is credit we hold for the customer; a negative one is money the customer owes us on account. Unpaid invoices are not in it.") }}
			</p>
		</div>
		<div class="flex flex-wrap items-center gap-2">
			<SegmentedToggle v-if="section === 'invoices'" :model-value="owedBy" :options="owedByOptions" :aria-label="ctrans('Owed by')" @update:model-value="pick('owed_by')" />
			<SegmentedToggle v-if="section === 'invoices'" :model-value="age ?? 'all'" :options="ageOptions" :aria-label="ctrans('Days overdue')" @update:model-value="pick('age')" />
			<SegmentedToggle v-if="section === 'balances'" :model-value="side ?? 'all'" :options="sideOptions" :aria-label="ctrans('Balance')" @update:model-value="pick('side')" />
			<SegmentedToggle v-if="organisations.length > 1" :model-value="organisation ?? 'all'" :options="organisationOptions" :aria-label="ctrans('Organisation')" @update:model-value="pick('org')" />
		</div>
	</div>

	<Table :resource="data" class="mt-5">
		<template #cell(reference)="{ item }: { item: Row }">
			<Link v-if="item.invoice_route" :href="route(item.invoice_route.name, item.invoice_route.parameters)" class="primaryLink whitespace-nowrap">{{ item.reference }}</Link>
			<span v-if="item.is_cash_on_delivery" class="ml-2 whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ ctrans("Cash on delivery") }}</span>
			<span v-if="item.is_pastpay" class="ml-2 whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">PastPay</span>
		</template>
		<template #cell(date)="{ item }: { item: Row }">
			<span class="whitespace-nowrap tabular-nums">{{ useFormatTime(item.date!, { formatTime: "d MMM yyyy" }) }}</span>
		</template>
		<template #cell(oldest)="{ item }: { item: Row }">
			<span class="whitespace-nowrap tabular-nums">{{ useFormatTime(item.oldest!, { formatTime: "d MMM yyyy" }) }}</span>
			<span class="ml-2 whitespace-nowrap text-xs tabular-nums" :class="overdueClass(item.days_overdue ?? 0)">{{ overdue(item.days_overdue ?? 0) }}</span>
		</template>
		<template #cell(customer_name)="{ item }: { item: Row }">
			<Link :href="route(item.customer_route.name, item.customer_route.parameters)" :class="section === 'invoices' ? 'secondaryLink' : 'primaryLink'">{{ item.customer_name || item.customer_reference }}</Link>
			<span v-if="item.customer_name" class="ml-2 whitespace-nowrap text-xs text-gray-400">{{ item.customer_reference }}</span>
			<span v-if="section === 'debtors' && item.company" class="ml-2 whitespace-nowrap rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-800">{{ ctrans("Our company") }}</span>
		</template>
		<template #cell(days_overdue)="{ item }: { item: Row }">
			<span class="whitespace-nowrap tabular-nums" :class="overdueClass(item.days_overdue ?? 0)">{{ overdue(item.days_overdue ?? 0) }}</span>
		</template>
		<template #cell(total_amount)="{ item }: { item: Row }">
			<span class="whitespace-nowrap tabular-nums text-gray-500">{{ locale.currencyFormat(item.currency_code, item.total_amount ?? 0) }}</span>
		</template>
		<template #cell(amount)="{ item }: { item: Row }">
			<div class="whitespace-nowrap font-medium tabular-nums">{{ locale.currencyFormat(item.currency_code, item.native ?? 0) }}</div>
			<div v-if="item.currency_code !== currency && item.amount !== null" class="whitespace-nowrap text-xs tabular-nums text-gray-500">{{ money(item.amount) }}</div>
		</template>
		<template #cell(balance)="{ item }: { item: Row }">
			<div class="whitespace-nowrap font-medium tabular-nums" :class="(item.balance ?? 0) < 0 ? 'text-red-700' : ''">{{ locale.currencyFormat(item.currency_code, item.balance ?? 0) }}</div>
			<div v-if="item.currency_code !== currency && item.amount !== null" class="whitespace-nowrap text-xs tabular-nums text-gray-500">{{ money(item.amount) }}</div>
		</template>
	</Table>
</template>
