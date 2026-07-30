<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 03 May 2024 08:59:19 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Agent } from "@/types/agent"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"

defineProps<{
    data: {}
}>()

function orgAgentRoute(routeName: string, agent: Agent) {
    return route(routeName, [route().params["organisation"], agent.slug])
}
</script>

<template>
    <Table :resource="data" class="mt-5">
        <template #cell(code)="{ item: agent }">
            <Link
                :href="orgAgentRoute('grp.org.procurement.org_agents.show', agent)"
                class="primaryLink">
                {{ agent["code"] }}
            </Link>
        </template>

        <template #cell(location)="{ item: agent }">
            <AddressLocation :data="agent['location']" />
        </template>

        <template #cell(number_org_suppliers)="{ item: agent }">
            <Link
                :href="orgAgentRoute('grp.org.procurement.org_agents.show.suppliers.index', agent)"
                class="secondaryLink">
                {{ agent.number_org_suppliers }}
            </Link>
        </template>

        <template #cell(number_purchase_orders)="{ item: agent }">
            <Link
                :href="orgAgentRoute('grp.org.procurement.org_agents.show.purchase-orders.index', agent)"
                class="secondaryLink">
                {{ agent.number_purchase_orders }}
            </Link>
        </template>

        <template #cell(number_stock_deliveries)="{ item: agent }">
            <Link
                :href="orgAgentRoute('grp.org.procurement.org_agents.show.stock-deliveries.index', agent)"
                class="secondaryLink">
                {{ agent.number_stock_deliveries }}
            </Link>
        </template>
    </Table>
</template>
