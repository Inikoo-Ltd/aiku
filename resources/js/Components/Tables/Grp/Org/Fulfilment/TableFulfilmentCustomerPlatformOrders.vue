<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"


const props = defineProps<{
    data: TableTS,
}>()

function orderRoute(order: {}) {
    switch (route().current()) {
         case "grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.orders.index":
            return route(
               "grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.orders.show",
                [route().params["organisation"], route().params["fulfilment"], route().params["fulfilmentCustomer"], route().params["customerHasPlatform"],  order.slug])
    }
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(reference)="{ item: order }">
            <Link :href="orderRoute(order)" class="primaryLink">
                {{ order["reference"] }}
            </Link>
        </template>
    </Table>
</template>
