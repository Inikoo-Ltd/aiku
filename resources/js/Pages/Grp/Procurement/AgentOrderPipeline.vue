<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 9 Oct 2026 Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

type LeadTime = { days: number; source: "measured" | "estimate" | "default"; samples: number }
type SupplierRow = LeadTime & {
	supplier_id: number
	code: string
	name: string
	open_orders: number
	late_orders: number
	worst_days_late: number | null
	no_eta_orders: number
	open_deliveries: number
	list_lines: number
}
type AgentPurchaseOrder = {
	id: number
	slug: string
	reference: string
	state: string
	supplier_code: string | null
	supplier_id: number | null
	date: string
	days_old: number
	days_late: number | null
	no_eta: boolean
}
type OpenStockDelivery = {
	id: number
	slug: string
	reference: string
	state: string
	supplier_code: string | null
	items: number
	days_in_transit: number | null
	date: string
	days_old: number
}

const props = defineProps<{
	pageHead: PageHeadingTypes
	title: string
	shoppingList: { open_items_count: number; oldest_item_at: string | null }
	leadTime: LeadTime
	suppliers: SupplierRow[]
	openAgentPurchaseOrders: AgentPurchaseOrder[]
	openStockDeliveries: OpenStockDelivery[]
	shoppingListRoute: routeType
	stockDeliveriesRoute: routeType
	agentPurchaseOrdersRoute: routeType
}>()

const problemThreshold = () => Math.max(props.leadTime.days * 2, 30)

const agingThreshold = () => Math.max(Math.round(props.leadTime.days * 1.2), 21)

const ageClasses = (daysOld: number) =>
	daysOld > problemThreshold()
		? "text-red-600"
		: daysOld > agingThreshold()
			? "text-amber-600"
			: "text-gray-400"

const stockDeliveryColumns = computed(() =>
	[
		{
			key: "being_prepared",
			label: ctrans("Being prepared"),
			states: ["in_process", "confirmed"],
			dot: "bg-[--app-accent-muted]",
			badge: "bg-[--app-accent-soft] text-[--app-accent-strong]",
		},
		{
			key: "ready_to_ship",
			label: ctrans("Ready to ship"),
			states: ["ready_to_ship"],
			dot: "bg-[--app-accent-muted]",
			badge: "bg-[--app-accent-soft] text-[--app-accent-strong]",
		},
		{
			key: "in_transit",
			label: ctrans("In transit"),
			states: ["dispatched"],
			dot: "bg-[--app-accent]",
			badge: "bg-[--app-accent-soft] text-[--app-accent-strong]",
		},
		{
			key: "arrived",
			label: ctrans("Arrived, booking in"),
			states: ["received", "checked", "booking_in"],
			dot: "bg-[--app-accent-strong]",
			badge: "bg-[--app-accent-soft] text-[--app-accent-strong]",
		},
	].map((column) => ({
		...column,
		deliveries: props.openStockDeliveries.filter((sd) => column.states.includes(sd.state)),
	}))
)

const lateSupplierOrders = computed(() =>
	props.openAgentPurchaseOrders.filter((order) => order.days_late !== null)
)

const latestSuppliers = computed(() =>
	props.suppliers.filter(
		(supplier) =>
			supplier.open_orders > 0 || supplier.list_lines > 0 || supplier.open_deliveries > 0
	)
)

const leadSourceLabel = (source: LeadTime["source"], samples: number) =>
	source === "measured"
		? ctrans("measured from :samples deliveries", { samples })
		: source === "estimate"
			? ctrans("estimate from supplier product settings")
			: ctrans("no data — house default")
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="mx-4 mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
		<div class="rounded-lg border border-gray-200 bg-white p-4">
			<div class="flex items-baseline justify-between text-sm text-gray-500">
				<span>{{ ctrans("Sub-suppliers") }}</span>
				<span v-if="lateSupplierOrders.length" class="font-medium text-red-600"
					>{{ lateSupplierOrders.length }} {{ ctrans("late") }}</span
				>
			</div>
			<div class="mt-1 text-2xl font-semibold text-gray-900">
				{{ suppliers.length }}
				<span class="text-sm font-normal text-gray-400">{{
					ctrans("sub-suppliers, each on its own clock")
				}}</span>
			</div>
			<div class="mt-2 text-xs text-gray-500">
				{{
					ctrans("agent roll-up :days days order → booked in", { days: leadTime.days })
				}}
				· {{ leadSourceLabel(leadTime.source, leadTime.samples) }}
			</div>
			<div v-if="lateSupplierOrders.length" class="mt-1 text-xs text-red-600">
				{{
					ctrans("worst delay :days days (:supplier)", {
						days: lateSupplierOrders[0].days_late ?? 0,
						supplier: lateSupplierOrders[0].supplier_code ?? "—",
					})
				}}
			</div>
		</div>
	</div>

	<div class="mx-4 mt-6">
		<h3 class="text-sm font-semibold text-gray-700">
			<Link
				class="hover:underline"
				:href="route(stockDeliveriesRoute.name, stockDeliveriesRoute.parameters)">
				{{ ctrans("Order pipeline") }}
			</Link>
		</h3>
		<div class="mt-2 grid grid-cols-2 gap-3 lg:grid-cols-6">
			<div class="rounded-lg bg-gray-50 p-2">
				<div class="mb-2 flex items-center justify-between px-1">
					<span class="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
						<span
							class="h-2 w-2 rounded-full bg-[--app-accent-soft] ring-1 ring-[--app-accent-muted]" />
						{{ ctrans("On shopping list") }}
					</span>
					<span
						class="rounded-full bg-[--app-accent-soft] px-2 py-0.5 text-xs font-medium tabular-nums text-[--app-accent-strong]"
						>{{ shoppingList.open_items_count }}</span
					>
				</div>
				<Link
					:href="route(shoppingListRoute.name, shoppingListRoute.parameters)"
					class="block rounded-md bg-white p-2 shadow-sm hover:bg-[--app-accent-soft]">
					<div class="text-sm font-bold text-gray-900">
						{{ shoppingList.open_items_count }} {{ ctrans("items") }}
					</div>
					<div v-if="shoppingList.oldest_item_at" class="mt-1 text-xs text-gray-500">
						{{ ctrans("oldest since") }}
						{{ useFormatTime(shoppingList.oldest_item_at, { formatTime: "mdy" }) }}
					</div>
				</Link>
			</div>

			<div class="rounded-lg bg-gray-50 p-2">
				<div class="mb-2 flex items-center justify-between px-1">
					<span class="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
						<span class="h-2 w-2 rounded-full bg-purple-300" />
						{{ ctrans("With the suppliers") }}
					</span>
					<span
						class="rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium tabular-nums text-purple-700"
						>{{ openAgentPurchaseOrders.length }}</span
					>
				</div>
				<div class="flex flex-col gap-2">
					<Link
						v-for="order in openAgentPurchaseOrders.slice(0, 12)"
						:key="order.id"
						:href="
							route('grp.org.procurement.purchase_orders.show', [
								route().params.organisation,
								order.slug,
							])
						"
						class="block rounded-md border-l-2 p-2 shadow-sm hover:ring-1 hover:ring-purple-300"
						:class="
							order.days_late && order.days_late > problemThreshold()
								? 'border-red-400 bg-red-50'
								: [
										'bg-white',
										order.days_late ? 'border-amber-400' : 'border-transparent',
									]
						">
						<div class="flex items-baseline justify-between gap-2">
							<span class="whitespace-nowrap text-sm font-bold text-gray-900">{{
								order.supplier_code ?? order.reference
							}}</span>
							<span
								class="whitespace-nowrap text-xs tabular-nums"
								:class="ageClasses(order.days_old)"
								>{{ useFormatTime(order.date, { formatTime: "mdy" }) }}</span
							>
						</div>
						<div class="text-xs text-gray-500">{{ order.reference }}</div>
						<div
							v-if="order.days_late"
							class="mt-1 text-xs font-semibold tabular-nums text-red-600">
							{{ order.days_late }} {{ ctrans("days late") }}
							<span v-if="order.no_eta" class="font-normal text-gray-400"
								>· {{ ctrans("no ETA") }}</span
							>
						</div>
					</Link>
					<Link
						v-if="openAgentPurchaseOrders.length > 12"
						:href="
							route(
								agentPurchaseOrdersRoute.name,
								agentPurchaseOrdersRoute.parameters
							)
						"
						class="px-1 py-1 text-xs text-gray-500 hover:text-gray-900 hover:underline">
						{{ ctrans("and :n more", { n: openAgentPurchaseOrders.length - 12 }) }}
					</Link>
					<div
						v-if="!openAgentPurchaseOrders.length"
						class="px-1 py-2 text-xs text-gray-400">
						—
					</div>
				</div>
			</div>

			<div
				v-for="column in stockDeliveryColumns"
				:key="column.key"
				class="rounded-lg bg-gray-50 p-2">
				<div class="mb-2 flex items-center justify-between px-1">
					<span class="flex items-center gap-1.5 text-xs font-semibold text-gray-700">
						<span class="h-2 w-2 rounded-full" :class="column.dot" />
						{{ column.label }}
					</span>
					<span class="flex items-center gap-1">
						<span
							class="rounded-full px-2 py-0.5 text-xs font-medium tabular-nums"
							:class="column.badge"
							:title="ctrans('deliveries')"
							>{{ column.deliveries.length }}</span
						>
					</span>
				</div>
				<div class="flex flex-col gap-2">
					<Link
						v-for="sd in column.deliveries"
						:key="sd.id"
						:href="
							route('grp.org.procurement.stock_deliveries.show', [
								route().params.organisation,
								sd.slug,
							])
						"
						class="block rounded-md border-l-2 p-2 shadow-sm hover:ring-1 hover:ring-[--app-accent-muted]"
						:class="
							sd.days_old > problemThreshold()
								? 'border-red-400 bg-red-50'
								: [
										'bg-white',
										sd.days_old > agingThreshold()
											? 'border-amber-400'
											: 'border-transparent',
									]
						">
						<div class="flex items-baseline justify-between gap-2">
							<span class="whitespace-nowrap text-sm font-bold text-gray-900">{{
								sd.reference
							}}</span>
							<span
								class="whitespace-nowrap text-xs tabular-nums"
								:class="ageClasses(sd.days_old)"
								>{{ useFormatTime(sd.date, { formatTime: "mdy" }) }}</span
							>
						</div>
						<div class="text-xs text-gray-500">
							<span v-if="sd.supplier_code" class="font-medium"
								>{{ sd.supplier_code }} · </span
							>{{ sd.items }} {{ ctrans("items") }}
						</div>
						<div
							v-if="column.key === 'in_transit' && sd.days_in_transit !== null"
							class="mt-1 text-xs font-semibold tabular-nums"
							:class="sd.days_in_transit > 45 ? 'text-amber-600' : 'text-gray-600'">
							{{ sd.days_in_transit }} {{ ctrans("days in transit") }}
						</div>
					</Link>
					<div v-if="!column.deliveries.length" class="px-1 py-2 text-xs text-gray-400">
						—
					</div>
				</div>
			</div>
		</div>
	</div>

	<div v-if="latestSuppliers.length" class="mx-4 mb-8 mt-6">
		<h3 class="text-sm font-semibold text-gray-700">
			{{ ctrans("Sub-suppliers behind this agent") }}
		</h3>
		<div class="mt-2 overflow-x-auto rounded-lg border border-gray-200 bg-white">
			<table class="w-full text-sm">
				<thead>
					<tr class="border-b border-gray-200 text-left text-xs text-gray-500">
						<th class="px-4 py-2 font-normal">{{ ctrans("Supplier") }}</th>
						<th class="px-4 py-2 text-right font-normal">{{ ctrans("Lead time") }}</th>
						<th class="px-4 py-2 text-right font-normal">
							{{ ctrans("Open orders") }}
						</th>
						<th class="px-4 py-2 text-right font-normal">{{ ctrans("Late") }}</th>
						<th class="px-4 py-2 text-right font-normal">
							{{ ctrans("In the pipeline") }}
						</th>
						<th class="px-4 py-2 text-right font-normal">{{ ctrans("On list") }}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					<tr v-for="supplier in latestSuppliers" :key="supplier.supplier_id">
						<td class="px-4 py-2">
							<span class="font-medium text-gray-900">{{ supplier.code }}</span>
							<span class="ml-2 text-xs text-gray-500">{{ supplier.name }}</span>
						</td>
						<td class="px-4 py-2 text-right tabular-nums">
							{{ supplier.days
							}}<span class="text-xs text-gray-400">{{ ctrans("d") }}</span>
							<div class="text-[10px] text-gray-400">
								{{ leadSourceLabel(supplier.source, supplier.samples) }}
							</div>
						</td>
						<td class="px-4 py-2 text-right tabular-nums text-gray-700">
							{{ supplier.open_orders || "—" }}
						</td>
						<td
							class="px-4 py-2 text-right tabular-nums"
							:class="
								supplier.late_orders ? 'font-medium text-red-600' : 'text-gray-400'
							">
							<template v-if="supplier.late_orders">
								{{ supplier.late_orders }}
								<div class="text-[10px] font-normal">
									{{
										ctrans("worst :days days", {
											days: supplier.worst_days_late ?? 0,
										})
									}}
								</div>
							</template>
							<template v-else>—</template>
						</td>
						<td class="px-4 py-2 text-right tabular-nums text-gray-700">
							{{ supplier.open_deliveries || "—" }}
						</td>
						<td class="px-4 py-2 text-right tabular-nums text-gray-700">
							{{ supplier.list_lines || "—" }}
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>
