<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params";
import { CustomerSalesChannel } from "@/types/customer-sales-channel";

defineProps<{
    data: TableTS,
}>()



function customerSalesChannelRoute(customerSalesChannel: CustomerSalesChannel) {
    if (route().current() === "grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.index") {
        return route(
           "grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).fulfilment,
                (route().params as RouteParams).fulfilmentCustomer,
                customerSalesChannel.slug])
    }
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(reference)="{ item: customerSalesChannel }">
            <Link :href="customerSalesChannelRoute(customerSalesChannel) as string" class="primaryLink">
                {{ customerSalesChannel["reference"] }}
            </Link>
        </template>
    </Table>
</template>
