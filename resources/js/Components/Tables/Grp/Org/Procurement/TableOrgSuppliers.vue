<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 24 Jul 2024 00:46:50 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import Icon from "@/Components/Icon.vue"
import SupplierAgreementStatusBadge from "@/Components/Procurement/SupplierAgreementStatusBadge.vue"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPersonDolly, faPeopleArrows, faArchive } from "@fal"

library.add(faPersonDolly, faPeopleArrows, faArchive)

interface OrgSupplier {
	org_supplier_slug: string
	code: string
	name: string
	name_source: "company" | "contact" | null
	nickname: string | null
	location: object
	status_icon: object
	org_agent_slug: string | null
	agent_code: string | null
	agent_name: string | null
	number_org_supplier_products: number
	number_purchase_orders: number
	number_open_purchase_orders: number
	number_stock_deliveries: number
	open_incidents: number
	agreement: { status: string, label: string, colour: string, icon: string } | null
}

defineProps<{
	data: {}
	tab?: string
}>()

const locale = useLocaleStore()

function orgSupplierRoute(orgSupplier: OrgSupplier) {
	return route("grp.org.procurement.org_suppliers.show", [
		route().params["organisation"],
		orgSupplier.org_supplier_slug,
	])
}

function supplierProductsRoute(orgSupplier: OrgSupplier) {
	return route("grp.org.procurement.org_suppliers.show.supplier_products.index", [
		route().params["organisation"],
		orgSupplier.org_supplier_slug,
	])
}

const livePurchaseOrderStates = ["in_process", "submitted", "confirmed", "in_production", "in_warehouse", "ready", "handed_over", "arrived"]

function purchaseOrdersRoute(orgSupplier: OrgSupplier, query: Record<string, string> = {}) {
	return route("grp.org.procurement.org_suppliers.show.purchase_orders.index", {
		organisation: route().params["organisation"],
		orgSupplier: orgSupplier.org_supplier_slug,
		...query,
	})
}

function openPurchaseOrdersRoute(orgSupplier: OrgSupplier) {
	return purchaseOrdersRoute(orgSupplier, { "elements[state]": livePurchaseOrderStates.join(",") })
}

function nameSourceTooltip(orgSupplier: OrgSupplier): string | null {
	if (orgSupplier.name_source === "company") {
		return ctrans("Company name")
	}

	return orgSupplier.name_source === "contact" ? ctrans("Contact name") : null
}

function orgAgentRoute(orgSupplier: OrgSupplier) {
	return route("grp.org.procurement.org_agents.show", [
		route().params["organisation"],
		orgSupplier.org_agent_slug,
	])
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(status)="{ item: orgSupplier }">
			<Icon :data="orgSupplier.status_icon" />
		</template>
		<template #cell(code)="{ item: orgSupplier }">
			<Link :href="orgSupplierRoute(orgSupplier)" class="primaryLink">
				{{ orgSupplier["code"] }}
			</Link>
			<span v-if="orgSupplier.open_incidents" class="ml-1 rounded-full bg-gray-800 px-1.5 py-0.5 text-xs font-medium text-white tabular-nums" :title="ctrans('Open incidents')">{{ orgSupplier.open_incidents }}</span>
		</template>
		<template #cell(name)="{ item: orgSupplier }">
			<span v-tooltip="nameSourceTooltip(orgSupplier)">{{ orgSupplier.name }}</span>
		</template>
		<template #cell(agent_code)="{ item: orgSupplier }">
			<Link v-if="orgSupplier.org_agent_slug" :href="orgAgentRoute(orgSupplier)" class="secondaryLink" :title="orgSupplier.agent_name">
				{{ orgSupplier.agent_code }}
			</Link>
		</template>
		<template #cell(location)="{ item: orgSupplier }">
			<AddressLocation :data="orgSupplier['location']" />
		</template>
		<template #cell(number_org_supplier_products)="{ item: orgSupplier }">
			<Link :href="supplierProductsRoute(orgSupplier)" class="secondaryLink">
				{{ locale.number(orgSupplier.number_org_supplier_products ?? 0) }}
			</Link>
		</template>
		<template #cell(number_purchase_orders)="{ item: orgSupplier }">
			<Link :href="purchaseOrdersRoute(orgSupplier)" class="secondaryLink">
				{{ locale.number(orgSupplier.number_purchase_orders ?? 0) }}
			</Link>
			<Link v-if="orgSupplier.number_open_purchase_orders" :href="openPurchaseOrdersRoute(orgSupplier)" class="secondaryLink ml-1 text-xs tabular-nums" :title="ctrans('Open purchase orders')">
				({{ ctrans(":count open", { count: locale.number(orgSupplier.number_open_purchase_orders) }) }})
			</Link>
		</template>
		<template #cell(number_stock_deliveries)="{ item: orgSupplier }">
			{{ locale.number(orgSupplier.number_stock_deliveries ?? 0) }}
		</template>
		<template #cell(agreement)="{ item: orgSupplier }">
			<SupplierAgreementStatusBadge v-if="orgSupplier.agreement" :status="orgSupplier.agreement" />
		</template>
	</Table>
</template>
