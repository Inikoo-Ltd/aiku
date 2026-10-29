<script setup lang="ts">
import qs from "qs"
import { router } from "@inertiajs/vue3"
import { useMediaQuery } from "@vueuse/core"
import Table from "@/Components/Table/Table.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

interface PurchaseOrderRow {
	reference: string
	organisation_name: string
	state: string
	state_label: string
	delivery_label: string | null
	open: boolean
	submitted_at: string | null
	confirmed_at: string | null
	estimated_received_at: string | null
	settled_at: string | null
	number_lines: number
	cost_items: number
	currency_code: string | null
	labels: Record<"carton" | "sko" | "unit", string> | null
}

const props = defineProps<{
	data: {
		data?: PurchaseOrderRow[]
		facets?: { state: { value: string; label: string; count: number }[] }
		active_facets?: { state: string | null }
	}
	tab: string
}>()

const isPhone = useMediaQuery("(max-width: 767px)")

const selectState = (value: string | null) => {
	const query = qs.parse(location.search.substring(1)) as Record<string, any>
	query[`${props.tab}_facets`] = { ...(query[`${props.tab}_facets`] ?? {}), state: value }
	delete query[`${props.tab}Page`]

	router.get(location.pathname + "?" + qs.stringify(query, { skipNulls: true, encodeValuesOnly: true }), {}, { preserveState: true, preserveScroll: true, replace: true })
}

const money = (amount: number, currency: string | null) =>
	currency ? new Intl.NumberFormat(undefined, { style: "currency", currency, maximumFractionDigits: 0 }).format(Math.round(amount)) : Math.round(amount).toString()
const day = (date: string | null) => (date ? useFormatTime(date) : "—")
const stateClass = (order: PurchaseOrderRow) => (order.open ? "text-amber-700" : order.state === "settled" ? "text-green-700" : "text-gray-400")
</script>

<template>
	<div>
		<div v-if="data.facets" class="mx-4 mt-4">
			<PillFilterBar :label="ctrans('Status')" :options="data.facets.state" :selected="data.active_facets?.state ?? null" @select="selectState" />
		</div>
		<Table :resource="data" :name="tab" class="mt-4">
			<template v-if="isPhone" #table>
				<ul class="divide-y divide-gray-100 border-t border-gray-100">
					<li v-for="order in data.data ?? []" :key="order.reference" class="space-y-1.5 px-4 py-3">
						<div class="flex items-baseline justify-between gap-3">
							<span class="text-base font-semibold text-gray-900">{{ order.reference }}</span>
							<span class="text-base tabular-nums text-gray-900">{{ money(order.cost_items, order.currency_code) }}</span>
						</div>
						<div class="flex items-baseline justify-between gap-3 text-sm">
							<span :class="stateClass(order)">
								{{ order.state_label }}<span v-if="order.open && order.delivery_label" class="text-gray-400"> · {{ order.delivery_label }}</span>
							</span>
							<span class="text-gray-500">{{ ctrans(":count lines", { count: String(order.number_lines) }) }}</span>
						</div>
						<div class="text-sm text-gray-500">{{ order.organisation_name }}</div>
						<dl class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-sm">
							<dt class="text-gray-400">{{ ctrans("Sent") }}</dt>
							<dd class="text-right tabular-nums text-gray-700">{{ day(order.submitted_at) }}</dd>
							<template v-if="order.confirmed_at">
								<dt class="text-gray-400">{{ ctrans("Confirmed") }}</dt>
								<dd class="text-right tabular-nums text-gray-700">{{ day(order.confirmed_at) }}</dd>
							</template>
							<template v-if="order.open && order.estimated_received_at">
								<dt class="text-gray-400">{{ ctrans("Expected") }}</dt>
								<dd class="text-right tabular-nums text-gray-700">{{ day(order.estimated_received_at) }}</dd>
							</template>
							<template v-if="order.settled_at">
								<dt class="text-gray-400">{{ ctrans("Received") }}</dt>
								<dd class="text-right tabular-nums text-gray-700">{{ day(order.settled_at) }}</dd>
							</template>
						</dl>
						<div v-if="order.labels" class="flex flex-wrap items-center gap-2 pt-1 text-sm">
							<span class="text-gray-400">{{ ctrans("Labels") }}</span>
							<a :href="order.labels.carton" target="_blank" class="rounded border border-gray-300 px-2.5 py-1 text-gray-700">{{ ctrans("Cartons") }}</a>
							<a :href="order.labels.sko" target="_blank" class="rounded border border-gray-300 px-2.5 py-1 text-gray-700">{{ ctrans("SKOs") }}</a>
							<a :href="order.labels.unit" target="_blank" class="rounded border border-gray-300 px-2.5 py-1 text-gray-700">{{ ctrans("Units") }}</a>
						</div>
					</li>
				</ul>
			</template>

			<template #cell(state)="{ item }: { item: PurchaseOrderRow }">
				<span class="whitespace-nowrap" :class="stateClass(item)">{{ item.state_label }}</span>
				<span v-if="item.open && item.delivery_label" class="whitespace-nowrap text-xs text-gray-400"> · {{ item.delivery_label }}</span>
			</template>
			<template #cell(submitted_at)="{ item }: { item: PurchaseOrderRow }">
				<span class="whitespace-nowrap">{{ day(item.submitted_at) }}</span>
			</template>
			<template #cell(confirmed_at)="{ item }: { item: PurchaseOrderRow }">
				<span class="whitespace-nowrap">{{ day(item.confirmed_at) }}</span>
			</template>
			<template #cell(estimated_received_at)="{ item }: { item: PurchaseOrderRow }">
				<span class="whitespace-nowrap">{{ item.open ? day(item.estimated_received_at) : "—" }}</span>
			</template>
			<template #cell(settled_at)="{ item }: { item: PurchaseOrderRow }">
				<span class="whitespace-nowrap">{{ day(item.settled_at) }}</span>
			</template>
			<template #cell(cost_items)="{ item }: { item: PurchaseOrderRow }">
				<span class="whitespace-nowrap tabular-nums">{{ money(item.cost_items, item.currency_code) }}</span>
			</template>
			<template #cell(labels)="{ item }: { item: PurchaseOrderRow }">
				<span v-if="item.labels" class="whitespace-nowrap text-xs">
					<a :href="item.labels.carton" target="_blank" class="text-gray-600 underline hover:text-gray-900">{{ ctrans("Cartons") }}</a>
					<span class="text-gray-300"> · </span>
					<a :href="item.labels.sko" target="_blank" class="text-gray-600 underline hover:text-gray-900">{{ ctrans("SKOs") }}</a>
					<span class="text-gray-300"> · </span>
					<a :href="item.labels.unit" target="_blank" class="text-gray-600 underline hover:text-gray-900">{{ ctrans("Units") }}</a>
				</span>
			</template>
		</Table>
	</div>
</template>
