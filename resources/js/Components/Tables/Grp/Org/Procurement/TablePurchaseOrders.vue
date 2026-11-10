<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:49:03 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import qs from "qs"
import Table from "@/Components/Table/Table.vue"
import { PurchaseOrder } from "@/types/purchase-order"
import { useFormatTime } from "@/Composables/useFormatTime"
import Icon from "@/Components/Icon.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans, ctransChoice } from "@/Composables/useTrans"
import { asProcurementRouteName, isAgentOrganisation } from "@/Composables/useAgentRoutes"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PingIcon from "@/Components/Utils/PingIcon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTrashAlt, faComments, faChevronRight, faLayerGroup, faHandHoldingUsd } from "@fal"

library.add(faTrashAlt, faComments, faChevronRight, faLayerGroup, faHandHoldingUsd)

const props = defineProps<{
	data: { data?: PurchaseOrder[] }
	tab?: string
	removablePurchaseOrderIds?: number[]
}>()

const emit = defineEmits<{
	(e: "removePurchaseOrder", purchaseOrderId: number): void
}>()

const isInAgentOrganisation = isAgentOrganisation(route().params.organisation as string | undefined)

type AgentOrderState = { label: string; icon: PurchaseOrder["state_icon"]; count: number }

const expandedAgentOrders = ref<string[]>([])

function toggleAgentOrder(agentOrderReference: string) {
	expandedAgentOrders.value = expandedAgentOrders.value.includes(agentOrderReference)
		? expandedAgentOrders.value.filter((reference) => reference !== agentOrderReference)
		: [...expandedAgentOrders.value, agentOrderReference]
}

function sumOf(purchaseOrders: PurchaseOrder[], key: keyof PurchaseOrder): number | null {
	const values = purchaseOrders.map((purchaseOrder) => purchaseOrder[key]).filter((value) => value !== null && value !== undefined)

	return values.length ? values.reduce((total: number, value) => total + Number(value), 0) : null
}

function sharedValue(purchaseOrders: PurchaseOrder[], key: keyof PurchaseOrder) {
	const values = new Set(purchaseOrders.map((purchaseOrder) => purchaseOrder[key]))

	return values.size === 1 ? purchaseOrders[0][key] : null
}

function agentOrderStates(purchaseOrders: PurchaseOrder[]): AgentOrderState[] {
	const states = new Map<string, AgentOrderState>()
	purchaseOrders.forEach((purchaseOrder) => {
		const state = states.get(purchaseOrder.state) ?? { label: purchaseOrder.state_label, icon: purchaseOrder.state_icon, count: 0 }
		state.count++
		states.set(purchaseOrder.state, state)
	})

	return [...states.values()]
}

function agentOrderShipments(purchaseOrders: PurchaseOrder[]): PurchaseOrder["shipments"] {
	const shipments = new Map<string, NonNullable<PurchaseOrder["shipments"]>[number]>()
	purchaseOrders.flatMap((purchaseOrder) => purchaseOrder.shipments ?? []).forEach((shipment) => {
		const known = shipments.get(shipment.reference)
		shipments.set(shipment.reference, known
			? { ...known, cbm: known.cbm === null && shipment.cbm === null ? null : (known.cbm ?? 0) + (shipment.cbm ?? 0) }
			: { ...shipment })
	})

	return [...shipments.values()]
}

function agentOrderGroupRow(agentOrderReference: string, purchaseOrders: PurchaseOrder[]) {
	const sameCurrency = sharedValue(purchaseOrders, "org_currency_code") !== null

	return {
		id: `agent-order-${agentOrderReference}`,
		slug: `agent-order-${agentOrderReference}`,
		is_agent_order_group: true,
		reference: agentOrderReference,
		agent_order_reference: agentOrderReference,
		agent_order_purchase_orders: purchaseOrders.length,
		agent_order_states: agentOrderStates(purchaseOrders),
		number_pending_line_answers: sumOf(purchaseOrders, "number_pending_line_answers") ?? 0,
		number_without_deposit: purchaseOrders.filter((purchaseOrder) => purchaseOrder.is_without_deposit).length,
		number_current_purchase_order_transactions: sumOf(purchaseOrders, "number_current_purchase_order_transactions"),
		quantity_ordered: sumOf(purchaseOrders, "quantity_ordered"),
		org_currency_code: sameCurrency ? purchaseOrders[0].org_currency_code : null,
		org_total_cost: sameCurrency ? sumOf(purchaseOrders, "org_total_cost") : null,
		org_net_amount: sameCurrency ? sumOf(purchaseOrders, "org_net_amount") : null,
		date: purchaseOrders.map((purchaseOrder) => purchaseOrder.date).filter(Boolean).sort()[0] ?? null,
		organisation_name: sharedValue(purchaseOrders, "organisation_name"),
		organisation_code: sharedValue(purchaseOrders, "organisation_code"),
		shipments: agentOrderShipments(purchaseOrders),
	}
}

const tableResource = computed(() => {
	const purchaseOrders = props.data?.data
	if (!Array.isArray(purchaseOrders)) {
		return props.data
	}

	const purchaseOrdersByAgentOrder = new Map<string, PurchaseOrder[]>()
	purchaseOrders.forEach((purchaseOrder) => {
		if (purchaseOrder.agent_order_reference) {
			purchaseOrdersByAgentOrder.set(purchaseOrder.agent_order_reference, [...(purchaseOrdersByAgentOrder.get(purchaseOrder.agent_order_reference) ?? []), purchaseOrder])
		}
	})

	const rows: Record<string, unknown>[] = []
	const groupedAgentOrders = new Set<string>()
	purchaseOrders.forEach((purchaseOrder) => {
		const agentOrderReference = purchaseOrder.agent_order_reference
		const agentOrderPurchaseOrders = agentOrderReference ? purchaseOrdersByAgentOrder.get(agentOrderReference) ?? [] : []
		if (!agentOrderReference || agentOrderPurchaseOrders.length < 2) {
			rows.push(purchaseOrder)
			return
		}

		if (groupedAgentOrders.has(agentOrderReference)) {
			return
		}

		groupedAgentOrders.add(agentOrderReference)
		rows.push(agentOrderGroupRow(agentOrderReference, agentOrderPurchaseOrders))
		if (expandedAgentOrders.value.includes(agentOrderReference)) {
			rows.push(...agentOrderPurchaseOrders.map((agentOrderPurchaseOrder) => ({ ...agentOrderPurchaseOrder, is_agent_order_member: true })))
		}
	})

	return { ...props.data, data: rows }
})

function purchaseOrderRowClass(row: Record<string, unknown>): string {
	if (row.is_agent_order_group) {
		return "bg-gray-50 hover:bg-gray-100"
	}

	if (row.state === "cancelled") {
		return "bg-red-50 hover:bg-red-100"
	}

	return row.is_agent_order_member ? "bg-white hover:bg-gray-50" : ""
}

function PurchaseOrderRoute(purchaseOrder: PurchaseOrder) {
	if (!purchaseOrder?.slug) {
		return null
	}

	switch (asProcurementRouteName(route().current())) {
		case "grp.org.procurement.incoming_purchase_orders.index":
			return route("grp.org.agent.incoming_purchase_orders.show", [
				route().params["organisation"],
				purchaseOrder.slug,
			])
		case "grp.org.procurement.purchase_orders.index":
		case "grp.org.procurement.org_supplier_products.show":
		case "grp.org.procurement.org_agents.show.supplier_products.show":
		case "grp.org.procurement.org_suppliers.show.supplier_products.show":
			return route("grp.org.procurement.purchase_orders.show", [
				route().params["organisation"],
				purchaseOrder.slug,
			])
		case "grp.org.procurement.org_agents.show.purchase-orders.index":
			return route("grp.org.procurement.org_agents.show.purchase-orders.show", [
				route().params["organisation"],
				route().params["orgAgent"],
				purchaseOrder.slug,
			])
		case "grp.org.procurement.org_suppliers.show":
			return route("grp.org.procurement.org_suppliers.show.purchase-orders.show", [
				route().params["organisation"],
				route().params["orgSupplier"],
				purchaseOrder.slug,
			])
		case "grp.org.procurement.org_partners.show.purchase-orders.index":
			return route("grp.org.procurement.org_partners.show.purchase-orders.show", [
				route().params["organisation"],
				route().params["orgPartner"],
				purchaseOrder.slug,
			])
		case "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show":
		case "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show":
		case "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show":
			return route("grp.org.procurement.purchase_orders.show", [
				route().params["organisation"],
				purchaseOrder.slug,
			])
		case "grp.org.procurement.org_suppliers.show.purchase_orders.index":
			if (isInAgentOrganisation) {
				return route("grp.org.procurement.purchase_orders.show", [
					route().params["organisation"],
					purchaseOrder.slug,
				])
			}

			return route("grp.org.procurement.org_suppliers.show.purchase-orders.show", [
				route().params["organisation"],
				route().params["orgSupplier"],
				purchaseOrder.slug,
			])
		default:
			if (isInAgentOrganisation) {
				return route("grp.org.procurement.purchase_orders.show", [
					route().params["organisation"],
					purchaseOrder.slug,
				])
			}

			if (!purchaseOrder.organisation_slug) {
				return null
			}

			return route("grp.org.procurement.purchase_orders.show", [
				purchaseOrder.organisation_slug,
				purchaseOrder.slug,
			])
	}
}

function SupplierRoute(purchaseOrder: PurchaseOrder) {
	switch (route().current()) {
		case "grp.org.procurement.purchase_orders.index":
			if (!purchaseOrder?.parent_slug) {
				return null
			}

			return route("grp.org.procurement.org_suppliers.show", [
				route().params["organisation"],
				purchaseOrder.parent_slug,
			])
		case "grp.org.procurement.org_agents.show.purchase-orders.index":
			if (!purchaseOrder?.parent_slug) {
				return null
			}

			return route("grp.org.procurement.org_agents.show.suppliers.show", [
				route().params["organisation"],
				route().params["orgAgent"],
				purchaseOrder.parent_slug,
			])
		case "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show":
		case "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show":
		case "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show":
			if (!purchaseOrder?.supplier_slug) {
				return null
			}

			return route("grp.supply-chain.suppliers.show", [purchaseOrder.supplier_slug])
		case "grp.org.procurement.org_suppliers.show.purchase_orders.index":
			return route("grp.org.procurement.org_suppliers.show", [
				route().params["organisation"],
				route().params["orgSupplier"],
			])
		default:
			if (!purchaseOrder?.parent_slug || !purchaseOrder.org_agent_slug) {
				return null
			}

			return route("grp.org.procurement.org_suppliers.show", [
				route().params["organisation"] ?? purchaseOrder.organisation_slug,
				purchaseOrder.parent_slug,
			])
	}
}

function AgentRoute(purchaseOrder: PurchaseOrder) {
	if (!purchaseOrder?.org_agent_slug) {
		return null
	}

	return route("grp.org.procurement.org_agents.show", [
		route().params["organisation"] ?? purchaseOrder.organisation_slug,
		purchaseOrder.org_agent_slug,
	])
}

function isFilteredBySupplier(): boolean {
	return Boolean((qs.parse(location.search.substring(1)) as Record<string, any>).filter?.supplier)
}

function SupplierFilterHref(purchaseOrder: PurchaseOrder) {
	const query = qs.parse(location.search.substring(1)) as Record<string, any>
	query.filter = { ...(query.filter ?? {}), supplier: purchaseOrder.supplier_slug }
	delete query.page

	return `${location.pathname}?${qs.stringify(query)}`
}
</script>

<template>
	<Table :resource="tableResource" :name="tab" :rowColorFunction="purchaseOrderRowClass" class="mt-5">
		<template #cell(reference)="{ item: purchaseOrder }">
			<div v-if="purchaseOrder.is_agent_order_group" class="flex items-center gap-1.5 whitespace-nowrap">
				<button
					type="button"
					class="flex items-center gap-1.5 font-semibold text-gray-700 hover:text-[var(--app-accent)]"
					:aria-expanded="expandedAgentOrders.includes(purchaseOrder.agent_order_reference)"
					v-tooltip="ctrans('Agent order split into one purchase order per supplier')"
					@click="toggleAgentOrder(purchaseOrder.agent_order_reference)">
					<FontAwesomeIcon icon="fal fa-chevron-right" class="text-[10px] text-gray-400 transition-transform" :class="expandedAgentOrders.includes(purchaseOrder.agent_order_reference) ? 'rotate-90' : ''" fixed-width aria-hidden="true" />
					<FontAwesomeIcon icon="fal fa-layer-group" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ purchaseOrder.reference }}
				</button>
				<span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-normal text-gray-600">
					{{ ctrans(":count POs", { count: purchaseOrder.agent_order_purchase_orders }) }}
				</span>
				<span
					v-if="purchaseOrder.number_without_deposit"
					v-tooltip="ctransChoice(':count purchase order has no deposit paid yet|:count purchase orders have no deposit paid yet', purchaseOrder.number_without_deposit)"
					class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-1.5 text-xs font-medium tabular-nums text-amber-700">
					<FontAwesomeIcon :icon="faHandHoldingUsd" fixed-width aria-hidden="true" />
					{{ ctrans(":count no deposit", { count: purchaseOrder.number_without_deposit }) }}
				</span>
				<span
					v-if="purchaseOrder.number_pending_line_answers"
					v-tooltip="ctransChoice(':count line with a new price or quantity waiting for an answer|:count lines with a new price or quantity waiting for an answer', purchaseOrder.number_pending_line_answers)"
					class="relative inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-1.5 text-xs font-medium tabular-nums text-amber-700">
					<FontAwesomeIcon :icon="faComments" fixed-width aria-hidden="true" />
					{{ purchaseOrder.number_pending_line_answers }}
				</span>
			</div>
			<div v-else class="flex items-center gap-1.5 whitespace-nowrap" :class="{ 'border-l-2 border-gray-200 pl-4': purchaseOrder.is_agent_order_member }">
				<Link
					v-if="PurchaseOrderRoute(purchaseOrder)"
					:href="PurchaseOrderRoute(purchaseOrder)"
					class="primaryLink">
					{{ purchaseOrder.reference }}
				</Link>
				<span v-else>{{ purchaseOrder.reference }}</span>
				<span
					v-if="purchaseOrder.number_pending_line_answers"
					v-tooltip="ctransChoice(':count line with a new price or quantity waiting for an answer|:count lines with a new price or quantity waiting for an answer', purchaseOrder.number_pending_line_answers)"
					class="relative inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-1.5 text-xs font-medium tabular-nums text-amber-700">
					<PingIcon v-if="!isInAgentOrganisation" class="!absolute -right-1 -top-1 text-[7px] text-amber-500" aria-hidden="true" />
					<FontAwesomeIcon :icon="faComments" fixed-width aria-hidden="true" />
					{{ purchaseOrder.number_pending_line_answers }}
				</span>
				<span
					v-if="purchaseOrder.is_without_deposit"
					v-tooltip="ctrans('No deposit paid yet')"
					class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-1.5 text-xs font-medium text-amber-700">
					<FontAwesomeIcon :icon="faHandHoldingUsd" fixed-width aria-hidden="true" />
					{{ ctrans("No deposit") }}
				</span>
			</div>
		</template>

		<template #cell(parent_name)="{ item: purchaseOrder }">
			<span v-if="purchaseOrder.is_agent_order_group" class="text-gray-500">
				{{ ctransChoice(":count supplier|:count suppliers", purchaseOrder.agent_order_purchase_orders) }}
			</span>
			<template v-else>
			<Link
				v-if="purchaseOrder.parent_type === 'OrgSupplier' && SupplierRoute(purchaseOrder)"
				:href="SupplierRoute(purchaseOrder)"
				class="secondaryLink">
				{{ purchaseOrder.parent_name }}
			</Link>
			<span v-else>{{ purchaseOrder.parent_name }}</span>
			<Link
				v-if="purchaseOrder.supplier_slug && (purchaseOrder.supplier_purchase_orders ?? 0) > 1 && !isFilteredBySupplier()"
				:href="SupplierFilterHref(purchaseOrder)"
				v-tooltip="ctrans('Show only the purchase orders of this supplier')"
				class="ml-2 whitespace-nowrap rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 hover:bg-gray-200">
				{{ ctrans(":count POs", { count: purchaseOrder.supplier_purchase_orders }) }}
			</Link>
			<div v-if="purchaseOrder.agent_name && !isInAgentOrganisation" class="text-xs text-gray-500">
				{{ ctrans("via agent") }}
				<Link v-if="AgentRoute(purchaseOrder)" :href="AgentRoute(purchaseOrder)" class="secondaryLink">
					{{ purchaseOrder.agent_name }}
				</Link>
				<span v-else>{{ purchaseOrder.agent_name }}</span>
			</div>
			</template>
		</template>

		<template #cell(organisation_name)="{ item: purchaseOrder }">
			<span v-if="isInAgentOrganisation && purchaseOrder.organisation_code" v-tooltip="purchaseOrder.organisation_name" class="uppercase">{{ purchaseOrder.organisation_code }}</span>
			<template v-else>{{ purchaseOrder.organisation_name }}</template>
		</template>
		<template #cell(state)="{ item: purchaseOrder }">
			<div v-if="purchaseOrder.is_agent_order_group" class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
				<span v-for="state in purchaseOrder.agent_order_states" :key="state.label" v-tooltip="state.label" class="flex items-center gap-0.5 whitespace-nowrap tabular-nums">
					<Icon :data="state.icon" />
					{{ state.count }}
				</span>
			</div>
			<div v-else class="flex items-center gap-1">
				<Icon :data="purchaseOrder.state_icon" />
				<span>{{ purchaseOrder.state_label }}</span>
			</div>
		</template>

		<template #cell(org_total_cost)="{ item: purchaseOrder }">
			<span v-if="purchaseOrder.org_total_cost === null || !purchaseOrder.org_currency_code" class="text-gray-400">—</span>
			<span v-else :class="{ 'font-semibold': purchaseOrder.is_agent_order_group }">{{
				useLocaleStore().currencyFormat(
					purchaseOrder.org_currency_code,
					purchaseOrder.org_total_cost
				)
			}}</span>
		</template>

		<template #cell(org_net_amount)="{ item: purchaseOrder }">
			<span v-if="purchaseOrder.org_net_amount === null || !purchaseOrder.org_currency_code" class="text-gray-400">—</span>
			<span v-else :class="{ 'font-semibold': purchaseOrder.is_agent_order_group }">{{
				useLocaleStore().currencyFormat(
					purchaseOrder.org_currency_code,
					purchaseOrder.org_net_amount
				)
			}}</span>
		</template>

		<template #cell(date)="{ item: purchaseOrder }">
			<div class="text-right">
				{{ useFormatTime(purchaseOrder.date, { formatTime: "EEE, do MMM yy" }) }}
			</div>
		</template>

		<template #cell(submitted_at)="{ item: purchaseOrder }">
			<div class="text-right">
				{{ useFormatTime(purchaseOrder.submitted_at, { formatTime: "EEE, do MMM yy" }) }}
			</div>
		</template>

		<template #cell(shipments)="{ item: purchaseOrder }">
			<div v-if="purchaseOrder.shipments?.length" class="flex flex-col gap-0.5">
				<div v-for="shipment in purchaseOrder.shipments" :key="shipment.reference" class="flex items-center gap-1 whitespace-nowrap">
					<Icon v-if="shipment.state_icon" :data="shipment.state_icon" />
					<Link
						v-if="shipment.show_route"
						:href="route(shipment.show_route.name, shipment.show_route.parameters)"
						class="secondaryLink">
						{{ shipment.reference }}
					</Link>
					<span v-else>{{ shipment.reference }}</span>
					<span
						v-if="shipment.cbm !== null"
						v-tooltip="ctrans('Space the items of this purchase order take in the container')"
						class="text-xs tabular-nums text-gray-500">
						{{ ctrans(":cbm m³", { cbm: useLocaleStore().number(shipment.cbm) }) }}
					</span>
				</div>
			</div>
			<span v-else class="text-gray-400">—</span>
		</template>

		<template #cell(actions)="{ item: purchaseOrder }">
			<div class="flex justify-end">
				<Button
					v-if="removablePurchaseOrderIds?.includes(purchaseOrder.id)"
					type="tertiary"
					size="xs"
					:label="ctrans('Remove')"
					:tooltip="ctrans('Remove this purchase order from the container')"
					@click="emit('removePurchaseOrder', purchaseOrder.id)"
				>
					<template #icon>
						<FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-500" fixed-width aria-hidden="true" />
					</template>
				</Button>
			</div>
		</template>

		<template #cell(parent)="{ item: purchaseOrder }">
			{{ purchaseOrder["parent_name"] }}
		</template>
	</Table>
</template>
