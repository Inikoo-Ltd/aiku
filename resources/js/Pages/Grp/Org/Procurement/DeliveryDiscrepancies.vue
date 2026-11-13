<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClipboardCheck } from "@fal"

library.add(faClipboardCheck)

type Owner = "mine" | "all" | "unassigned"
type Status = "needs_decision" | "awaiting_recount" | "claim_open" | "closed"

interface Row {
	id: number
	stock_delivery_reference: string
	purchase_order_references: string | null
	supplier_code: string | null
	supplier_name: string | null
	agent_code: string | null
	org_stock_code: string | null
	org_stock_name: string | null
	expected_quantity: number
	received_quantity: number
	difference: number
	claim_amount: number | null
	claim_currency_code: string | null
	claim_state_label: string | null
	outcome_label: string | null
	status: Status
	status_label: string
	age_days: number
	buyer_name: string | null
	stock_delivery_route: routeType
}

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	owner: Owner
	owner_routes: Record<Owner, routeType>
	summary: {
		counts: Record<Status, number>
		open_claims: { currency_code: string; amount: number; number: number }[]
	}
	data: { data: Row[] }
}>()

const locale = useLocaleStore()

const owner = ref<Owner>(props.owner)
const owners = computed(() => [
	{ value: "mine" as Owner, label: ctrans("Mine") },
	{ value: "all" as Owner, label: ctrans("All") },
	{ value: "unassigned" as Owner, label: ctrans("Unassigned") },
])

watch(owner, (chosen) => {
	const target = props.owner_routes[chosen]
	router.get(route(target.name, target.parameters), {}, { preserveScroll: true })
})

const statusClass: Record<Status, string> = {
	needs_decision: "bg-red-50 text-red-700",
	awaiting_recount: "bg-amber-50 text-amber-800",
	claim_open: "bg-amber-50 text-amber-800",
	closed: "bg-green-50 text-green-700",
}

const counts = computed(() => [
	{ key: "needs_decision" as Status, label: ctrans("Needs decision") },
	{ key: "awaiting_recount" as Status, label: ctrans("Awaiting recount") },
	{ key: "claim_open" as Status, label: ctrans("Claim open") },
	{ key: "closed" as Status, label: ctrans("Closed, last 90 days") },
])

const isEmpty = computed(() => !props.data.data.length && !Object.values(props.summary.counts).some(Boolean))
const signed = (quantity: number) => (quantity > 0 ? "+" : "") + locale.number(quantity)
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-gray-200 px-4 py-3">
		<SegmentedToggle v-model="owner" :options="owners" :ariaLabel="ctrans('Whose discrepancies')" />
		<dl class="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
			<div v-for="count in counts" :key="count.key" class="flex items-baseline gap-1.5">
				<dd class="font-semibold tabular-nums" :class="summary.counts[count.key] ? 'text-gray-900' : 'text-gray-400'">{{ locale.number(summary.counts[count.key]) }}</dd>
				<dt class="whitespace-nowrap text-xs text-gray-500">{{ count.label }}</dt>
			</div>
			<div v-for="claim in summary.open_claims" :key="claim.currency_code" class="flex items-baseline gap-1.5">
				<dd class="font-semibold tabular-nums text-gray-900">{{ locale.currencyFormat(claim.currency_code, claim.amount) }}</dd>
				<dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("in :count open claims", { count: locale.number(claim.number) }) }}</dt>
			</div>
		</dl>
	</div>

	<div v-if="isEmpty" class="px-4 py-16 text-center text-sm text-gray-500">
		<p class="font-medium text-gray-700">{{ owner === "mine" ? ctrans("Nothing waiting for you") : ctrans("No delivery discrepancies here") }}</p>
		<p>{{ ctrans("Lines that arrive under or over what was ordered show here until they are settled.") }}</p>
	</div>

	<Table v-else :resource="data">
		<template #cell(stock_delivery_reference)="{ item }: { item: Row }">
			<Link :href="route(item.stock_delivery_route.name, item.stock_delivery_route.parameters)" class="primaryLink whitespace-nowrap">{{ item.stock_delivery_reference }}</Link>
		</template>
		<template #cell(purchase_order_references)="{ item }: { item: Row }">
			<span v-for="(reference, index) in (item.purchase_order_references ?? '').split(', ').filter(Boolean)" :key="reference" class="whitespace-nowrap">{{ reference }}<template v-if="index < (item.purchase_order_references ?? '').split(', ').length - 1">, </template></span>
		</template>
		<template #cell(supplier_code)="{ item }: { item: Row }">
			<span v-tooltip="item.supplier_name" class="whitespace-nowrap">{{ item.supplier_code }}</span>
		</template>
		<template #cell(agent_code)="{ item }: { item: Row }">
			<span class="whitespace-nowrap">{{ item.agent_code }}</span>
		</template>
		<template #cell(org_stock_code)="{ item }: { item: Row }">
			<span v-tooltip="item.org_stock_name" class="whitespace-nowrap font-medium">{{ item.org_stock_code }}</span>
		</template>
		<template #cell(expected_quantity)="{ item }: { item: Row }">
			<span class="tabular-nums">{{ locale.number(item.expected_quantity) }}</span>
		</template>
		<template #cell(received_quantity)="{ item }: { item: Row }">
			<span class="tabular-nums">{{ locale.number(item.received_quantity) }}</span>
		</template>
		<template #cell(difference)="{ item }: { item: Row }">
			<span class="font-semibold tabular-nums" :class="item.difference < 0 ? 'text-red-600' : 'text-amber-600'">{{ signed(item.difference) }}</span>
		</template>
		<template #cell(claim_amount)="{ item }: { item: Row }">
			<span v-if="item.claim_amount !== null && item.claim_currency_code" v-tooltip="item.claim_state_label" class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(item.claim_currency_code, item.claim_amount) }}</span>
			<span v-else class="text-gray-300">—</span>
		</template>
		<template #cell(status)="{ item }: { item: Row }">
			<span v-tooltip="item.outcome_label" class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[item.status]">{{ item.status_label }}</span>
		</template>
		<template #cell(age_days)="{ item }: { item: Row }">
			<span class="whitespace-nowrap tabular-nums" :class="item.age_days > 14 && item.status !== 'closed' ? 'font-semibold text-red-600' : ''">{{ ctrans(":days d", { days: locale.number(item.age_days) }) }}</span>
		</template>
		<template #cell(buyer_name)="{ item }: { item: Row }">
			<span class="whitespace-nowrap" :class="item.buyer_name ? '' : 'text-gray-400'">{{ item.buyer_name ?? ctrans("Unassigned") }}</span>
		</template>
	</Table>
</template>
