<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { Link } from "@inertiajs/vue3"
import type { Links, Meta } from "@/types/Table"


defineProps<{
    data: TableTS,
}>()

function orderRoute(order: Order) {
    console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.crm.customers.show.platforms.show.orders.index":
            return route(
                "grp.org.shops.show.crm.customers.show.platforms.show.orders.show",
                [route().params["organisation"], route().params["shop"], route().params["customer"], route().params["customerHasPlatform"], order.slug])
        default:
            return null
    }
}

</script>
<template>
     <Table :resource="data" >
        <!-- Column: Reference -->
        <template #cell(reference)="{ item: order }">
            <Link :href="orderRoute(order)" class="primaryLink">
                {{ order["reference"] }}
            </Link>
        </template>
    </Table>
</template>
