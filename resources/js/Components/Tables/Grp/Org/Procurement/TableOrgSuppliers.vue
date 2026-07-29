<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 24 Jul 2024 00:46:50 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import Icon from "@/Components/Icon.vue"

interface OrgSupplier {
    org_supplier_slug: string
    code: string
    name: string
    location: object
    status_icon: object
}

defineProps<{
    data: {}
    tab?: string
}>()

function orgSupplierRoute(orgSupplier: OrgSupplier) {
    switch (route().current()) {
        case "grp.org.procurement.org_suppliers.index":
            return route(
                "grp.org.procurement.org_suppliers.show",
                [route().params["organisation"], orgSupplier.org_supplier_slug])

        case "grp.org.procurement.org_agents.show.suppliers.index":
            return route(
                "grp.org.procurement.org_agents.show.suppliers.show",
                [route().params["organisation"], route().params["orgAgent"], orgSupplier.org_supplier_slug])

        default:
            return ''
    }
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
        </template>
        <template #cell(location)="{ item: orgSupplier }">
            <AddressLocation :data="orgSupplier['location']" />
        </template>
    </Table>
</template>
