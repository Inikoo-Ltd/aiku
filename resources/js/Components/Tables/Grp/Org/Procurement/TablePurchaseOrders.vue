<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:49:03 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
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
import { faTrashAlt, faComments } from "@fal"

library.add(faTrashAlt, faComments)

defineProps<{
	data: {}
	tab?: string
	removablePurchaseOrderIds?: number[]
}>()

const emit = defineEmits<{
	(e: "removePurchaseOrder", purchaseOrderId: number): void
}>()

const isInAgentOrganisation = isAgentOrganisation(route().params.organisation as string | undefined)

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
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(reference)="{ item: purchaseOrder }">
			<div class="flex items-center gap-1.5 whitespace-nowrap">
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
			</div>
		</template>

		<template #cell(parent_name)="{ item: purchaseOrder }">
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

		<template #cell(organisation_name)="{ item: purchaseOrder }">
			<span v-if="isInAgentOrganisation && purchaseOrder.organisation_code" v-tooltip="purchaseOrder.organisation_name" class="uppercase">{{ purchaseOrder.organisation_code }}</span>
			<template v-else>{{ purchaseOrder.organisation_name }}</template>
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
