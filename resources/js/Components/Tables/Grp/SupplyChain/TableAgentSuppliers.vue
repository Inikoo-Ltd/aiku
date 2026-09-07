<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useLocaleStore } from "@/Stores/locale"

interface AgentSupplier {
	id: number
	code: string
	name: string
	location: object
	agent_slug: string
	agent_code: string
	agent_name: string
	number_supplier_products: number
	number_purchase_orders: number
	number_stock_deliveries: number
}

defineProps<{
	data: object
	tab?: string
}>()

const locale = useLocaleStore()

function supplierRoute(supplier: AgentSupplier) {
	return route("grp.majordomo.redirect_supplier", [supplier.id])
}

function agentRoute(supplier: AgentSupplier) {
	return route("grp.supply-chain.agents.show", [supplier.agent_slug])
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(code)="{ item: supplier }">
			<Link :href="supplierRoute(supplier)" class="primaryLink">
				{{ supplier.code }}
			</Link>
		</template>
		<template #cell(agent_code)="{ item: supplier }">
			<Link :href="agentRoute(supplier)" class="primaryLink" :title="supplier.agent_name">
				{{ supplier.agent_code }}
			</Link>
		</template>
		<template #cell(location)="{ item: supplier }">
			<AddressLocation :data="supplier.location" />
		</template>
		<template #cell(number_supplier_products)="{ item: supplier }">
			{{ locale.number(supplier.number_supplier_products) }}
		</template>
		<template #cell(number_purchase_orders)="{ item: supplier }">
			{{ locale.number(supplier.number_purchase_orders) }}
		</template>
		<template #cell(number_stock_deliveries)="{ item: supplier }">
			{{ locale.number(supplier.number_stock_deliveries) }}
		</template>
	</Table>
</template>
