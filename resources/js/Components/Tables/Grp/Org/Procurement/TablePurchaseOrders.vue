<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:49:03 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { PurchaseOrder } from "@/types/purchase-order"
import { useFormatTime } from "@/Composables/useFormatTime"
import Icon from "@/Components/Icon.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"

defineProps<{
	data: {}
	tab?: string
}>()

function PurchaseOrderRoute(purchaseOrder: PurchaseOrder) {
	if (!purchaseOrder?.slug) {
		return null
	}

	switch (route().current()) {
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
			return route("grp.org.procurement.org_suppliers.show.purchase-orders.show", [
				route().params["organisation"],
				route().params["orgSupplier"],
				purchaseOrder.slug,
			])
		default:
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
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(reference)="{ item: purchaseOrder }">
			<Link
				v-if="PurchaseOrderRoute(purchaseOrder)"
				:href="PurchaseOrderRoute(purchaseOrder)"
				class="primaryLink">
				{{ purchaseOrder.reference }}
			</Link>
			<span v-else>{{ purchaseOrder.reference }}</span>
		</template>

		<template #cell(parent_name)="{ item: purchaseOrder }">
			<Link
				v-if="purchaseOrder.parent_type === 'OrgSupplier' && SupplierRoute(purchaseOrder)"
				:href="SupplierRoute(purchaseOrder)"
				class="secondaryLink">
				{{ purchaseOrder.parent_name }}
			</Link>
			<span v-else>{{ purchaseOrder.parent_name }}</span>
			<div v-if="purchaseOrder.agent_name" class="text-xs text-gray-500">
				{{ ctrans("via agent") }}
				<Link v-if="AgentRoute(purchaseOrder)" :href="AgentRoute(purchaseOrder)" class="secondaryLink">
					{{ purchaseOrder.agent_name }}
				</Link>
				<span v-else>{{ purchaseOrder.agent_name }}</span>
			</div>
		</template>

		<template #cell(state)="{ item: purchaseOrder }">
			<div class="flex items-center gap-1">
				<Icon :data="purchaseOrder.state_icon" />
				<span>{{ purchaseOrder.state_label }}</span>
			</div>
		</template>

		<template #cell(org_total_cost)="{ item: purchaseOrder }">
			{{
				useLocaleStore().currencyFormat(
					purchaseOrder.org_currency_code,
					purchaseOrder.org_total_cost
				)
			}}
		</template>

		<template #cell(org_net_amount)="{ item: purchaseOrder }">
			{{
				useLocaleStore().currencyFormat(
					purchaseOrder.org_currency_code,
					purchaseOrder.org_net_amount
				)
			}}
		</template>

		<template #cell(date)="{ item: purchaseOrder }">
			<div class="text-right">
				{{ useFormatTime(purchaseOrder.date, { formatTime: "EEE, do MMM yy" }) }}
			</div>
		</template>

		<template #cell(parent)="{ item: purchaseOrder }">
			{{ purchaseOrder["parent_name"] }}
		</template>
	</Table>
</template>
