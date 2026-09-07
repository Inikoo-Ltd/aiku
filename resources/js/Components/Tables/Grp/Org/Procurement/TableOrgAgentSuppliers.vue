<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"

interface OrgAgentSupplier {
	org_supplier_slug: string
	org_agent_slug: string
	code: string
	name: string
	location: object
	agent_code: string
	agent_name: string
}

defineProps<{
	data: object
	tab?: string
}>()

function orgSupplierRoute(orgSupplier: OrgAgentSupplier) {
	return route("grp.org.procurement.org_agents.show.suppliers.show", [
		route().params["organisation"],
		orgSupplier.org_agent_slug,
		orgSupplier.org_supplier_slug,
	])
}

function orgAgentRoute(orgSupplier: OrgAgentSupplier) {
	return route("grp.org.procurement.org_agents.show", [
		route().params["organisation"],
		orgSupplier.org_agent_slug,
	])
}
</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(code)="{ item: orgSupplier }">
			<Link :href="orgSupplierRoute(orgSupplier)" class="primaryLink">
				{{ orgSupplier.code }}
			</Link>
		</template>
		<template #cell(agent_code)="{ item: orgSupplier }">
			<Link
				:href="orgAgentRoute(orgSupplier)"
				class="primaryLink"
				:title="orgSupplier.agent_name">
				{{ orgSupplier.agent_code }}
			</Link>
		</template>
		<template #cell(location)="{ item: orgSupplier }">
			<AddressLocation :data="orgSupplier.location" />
		</template>
	</Table>
</template>
