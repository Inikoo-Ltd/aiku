<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useFormatTime } from '@/Composables/useFormatTime'
import Icon from "@/Components/Icon.vue"
import { useLocaleStore } from "@/Stores/locale";

const props = defineProps<{
    data: TableTS,
}>()

function orderRoute(order: {}) {
    switch (route().current()) {
         case "grp.org.fulfilments.show.crm.customers.show.platforms.show.orders.index":
            return route(
               "grp.org.fulfilments.show.crm.customers.show.platforms.show.orders.show",
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
