<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
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
import { faChartLine, faFileInvoice, faShip, faHandHoldingUsd, faTruckContainer, faUsers, faWallet, faHourglassHalf } from "@fal"

library.add(faChartLine, faFileInvoice, faShip, faHandHoldingUsd, faTruckContainer, faUsers, faWallet, faHourglassHalf)

interface PayableRow {
	id: number | string
	kind?: string
	creditor_type: "supplier" | "agent" | "issuer"
	creditor_name: string | null
	creditor_slug: string | null
	organisation_slug: string
	organisation_code: string
	reference?: string | null
	date?: string
	due_date?: string | null
	currency_code: string
	total?: string
	paid?: string
	owed?: string
	amount: string | null
	stock_delivery_slug?: string | null
	stock_delivery_reference?: string | null
	approved_at?: string | null
	counted?: boolean
	documents?: number
	oldest_date?: string
	oldest_due_date?: string | null
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	organisation: string | null
	organisations: { value: string; label: string }[]
	section: string
	currency: string
	data: { data: PayableRow[] }
}>()

const locale = useLocaleStore()
const date = (value: string) => useFormatTime(value, { formatTime: "d MMM yyyy" })
const daysOld = (value: string) => Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 86400000))

const organisationOptions = computed(() => [{ label: ctrans("All"), value: "all" }, ...props.organisations])
const organisation = computed({
	get: () => props.organisation ?? "all",
	set: (value: string) => router.get(route(`grp.financials.payables.${props.section}`, value === "all" ? {} : { org: value })),
})

const notes: Record<string, string> = {
	agent_wallets: ctrans("An agent that pays our suppliers from its money ledger is owed its wallet deficit: what it spent less what it received."),
	agent_invoices: ctrans("Invoices of an agent owed through its wallet are detail only: they are greyed and not added to the total."),
	money_requests: ctrans("A request awaiting approval is greyed and not added to the total."),
	not_invoiced: ctrans("Deliveries received whose invoice has not been entered, at their estimated invoice. Not added to the total."),
}

const creditorTypes: Record<string, string> = { supplier: ctrans("Supplier"), agent: ctrans("Agent"), issuer: ctrans("Service invoices") }
const creditorRoute = (row: PayableRow) => {
	if (row.creditor_type === "issuer") {
		return route("grp.org.procurement.service_invoices.index", [row.organisation_slug])
	}
	if (!row.creditor_slug) {
		return null
	}
	return row.creditor_type === "agent"
		? route("grp.org.procurement.org_agents.show", { organisation: row.organisation_slug, orgAgent: row.creditor_slug, ...(row.kind === "money_requests" ? { tab: "money" } : {}) })
		: route("grp.org.procurement.org_suppliers.show", [row.organisation_slug, row.creditor_slug])
}
const documentRoute = (row: PayableRow) => {
	if (row.kind === "service_invoices") {
		return route("grp.org.procurement.service_invoices.index", [row.organisation_slug])
	}
	if (row.kind === "money_requests") {
		return creditorRoute(row)
	}
	return row.stock_delivery_slug ? route("grp.org.procurement.stock_deliveries.show", [row.organisation_slug, row.stock_delivery_slug]) : null
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<p v-if="notes[section]" class="px-4 pt-3 text-xs text-gray-500">{{ notes[section] }}</p>

	<div v-if="organisations.length > 1" class="flex justify-end px-4 pt-4">
		<SegmentedToggle v-model="organisation" :options="organisationOptions" :aria-label="ctrans('Organisation')" />
	</div>

	<Table :resource="data" class="mt-5">
		<template #cell(reference)="{ item }">
			<Link v-if="documentRoute(item)" :href="documentRoute(item)!" class="font-medium hover:underline" :class="section === 'money_requests' ? 'text-xs' : 'whitespace-nowrap'">{{ item.reference ?? "—" }}</Link>
			<span v-else class="whitespace-nowrap">{{ item.reference ?? "—" }}</span>
		</template>

		<template #cell(creditor_name)="{ item }">
			<Link v-if="creditorRoute(item)" :href="creditorRoute(item)!" class="font-medium hover:underline">{{ item.creditor_name ?? "—" }}</Link>
			<span v-else>{{ item.creditor_name ?? "—" }}</span>
		</template>

		<template #cell(creditor_type)="{ item }">
			<span class="whitespace-nowrap text-xs text-gray-600">{{ creditorTypes[item.creditor_type] }}</span>
		</template>

		<template #cell(organisation_code)="{ item }">
			<Link :href="route(`grp.org.accounting.payables.${section}`, [item.organisation_slug])" class="whitespace-nowrap font-medium hover:underline">{{ item.organisation_code }}</Link>
		</template>

		<template #cell(stock_delivery_reference)="{ item }">
			<Link v-if="item.stock_delivery_slug" :href="route('grp.org.procurement.stock_deliveries.show', [item.organisation_slug, item.stock_delivery_slug])" class="whitespace-nowrap font-medium hover:underline">
				{{ item.stock_delivery_reference }}
			</Link>
		</template>

		<template #cell(approved_at)="{ item }">
			<span v-if="item.approved_at" class="whitespace-nowrap text-green-700">{{ date(item.approved_at) }}</span>
			<span v-else class="whitespace-nowrap text-amber-700">{{ ctrans("Awaiting approval") }}</span>
		</template>

		<template #cell(counted)="{ item }">
			<span v-if="item.counted" class="whitespace-nowrap text-xs text-gray-600">{{ ctrans("Owed") }}</span>
			<span v-else class="whitespace-nowrap text-xs text-gray-400">{{ ctrans("In the wallet balance") }}</span>
		</template>

		<template #cell(date)="{ item }">
			<span class="whitespace-nowrap">{{ date(item.date) }}</span>
			<div class="whitespace-nowrap text-[11px] text-gray-400">{{ ctrans(":days days old", { days: locale.number(daysOld(item.date)) }) }}</div>
		</template>

		<template #cell(due_date)="{ item }">
			<span v-if="item.due_date" class="whitespace-nowrap">{{ date(item.due_date) }}</span>
			<span v-else class="whitespace-nowrap text-xs text-gray-400">{{ ctrans("No due date") }}</span>
		</template>

		<template #cell(oldest_date)="{ item }">
			<span class="whitespace-nowrap">{{ date(item.oldest_date) }}</span>
			<div class="whitespace-nowrap text-[11px] text-gray-400">{{ ctrans(":days days old", { days: locale.number(daysOld(item.oldest_date)) }) }}</div>
		</template>

		<template #cell(oldest_due_date)="{ item }">
			<span v-if="item.oldest_due_date" class="whitespace-nowrap">{{ date(item.oldest_due_date) }}</span>
			<span v-else class="whitespace-nowrap text-xs text-gray-400">{{ ctrans("No due date") }}</span>
		</template>

		<template #cell(documents)="{ item }">
			<span class="tabular-nums">{{ locale.number(item.documents) }}</span>
		</template>

		<template #cell(currency_code)="{ item }">
			<span class="whitespace-nowrap text-xs text-gray-600">{{ item.currency_code }}</span>
		</template>

		<template #cell(total)="{ item }">
			<span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.currency_code, Number(item.total)) }}</span>
		</template>

		<template #cell(paid)="{ item }">
			<span class="whitespace-nowrap tabular-nums" :class="Number(item.paid) ? '' : 'text-gray-400'">{{ locale.currencyFormat(item.currency_code, Number(item.paid)) }}</span>
		</template>

		<template #cell(owed)="{ item }">
			<span class="whitespace-nowrap tabular-nums" :class="item.counted ? 'font-medium' : 'text-gray-400'">{{ locale.currencyFormat(item.currency_code, Number(item.owed)) }}</span>
		</template>

		<template #cell(amount)="{ item }">
			<span v-if="item.amount !== null" class="whitespace-nowrap tabular-nums" :class="section === 'creditors' ? 'font-medium' : item.counted === false ? 'text-gray-400' : ''">{{ locale.currencyFormat(currency, Number(item.amount)) }}</span>
			<span v-else class="whitespace-nowrap text-xs text-amber-700">{{ ctrans("no exchange rate") }}</span>
		</template>
	</Table>
</template>
