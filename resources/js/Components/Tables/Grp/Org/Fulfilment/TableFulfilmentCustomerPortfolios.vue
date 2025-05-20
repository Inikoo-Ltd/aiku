<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"


const props = defineProps<{
    data: TableTS,
}>()

function portfolioRoute(portfolio: {}) {
    switch (route().current()) {
         case "grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.portfolios.index":
            return route(
               "grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.portfolios.show",
                [route().params["organisation"], route().params["fulfilment"], route().params["fulfilmentCustomer"],  route().params["customerSalesChannel"],  portfolio.slug])
    }
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(reference)="{ item: portfolio }">
            <Link :href="portfolioRoute(portfolio) as string" class="primaryLink">
                {{ portfolio["reference"] }}
            </Link>
        </template>
    </Table>
</template>
