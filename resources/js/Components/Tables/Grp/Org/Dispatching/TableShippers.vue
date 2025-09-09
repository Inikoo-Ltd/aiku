<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params"
import { Shipper } from "@/types/shipper"

defineProps<{
    data: TableTS,
    tab?: string
}>()


function shipperRoute(shipper: Shipper) {
    switch (route().current()) {
        case "grp.org.warehouses.show.dispatching.shippers.inactive.index":
        case "grp.org.warehouses.show.dispatching.shippers.current.index":
            return route(
                "grp.org.warehouses.show.dispatching.shippers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).warehouse,
                    shipper.slug
                ])
        default:
            return ""
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(api_shipper)="{ item: shipper }">
                {{ shipper.type }}
        </template>
        <template #cell(code)="{ item: shipper }">
            <Link :href="shipperRoute(shipper)" class="primaryLink">
                {{ shipper.code }}
            </Link>
        </template>
    </Table>
</template>
