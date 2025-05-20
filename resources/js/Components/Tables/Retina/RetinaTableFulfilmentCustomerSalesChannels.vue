
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 May 2025 09:42:22 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { CustomerSalesChannel } from "@/types/customer-sales-channel";

defineProps<{
    data: TableTS,
}>()



function customerSalesChannelRoute(customerSalesChannel: CustomerSalesChannel) {
  return route(
    "retina.fulfilment.dropshipping.customer_sales_channels.dashboard",
    [
     
      customerSalesChannel.customer_sales_channel_slug])
}

function portfoliosRoute(customerSalesChannel: CustomerSalesChannel) {
  return route(
    "retina.fulfilment.dropshipping.customer_sales_channels.portfolios.index",
    [
      customerSalesChannel.customer_sales_channel_slug])
}
function clientsRoute(customerSalesChannel: CustomerSalesChannel) {
  return route(
    "retina.fulfilment.dropshipping.customer_sales_channels.client.index",
    [
      customerSalesChannel.customer_sales_channel_slug])
}
function ordersRoute(customerSalesChannel: CustomerSalesChannel) {
  return route(
    "retina.fulfilment.dropshipping.customer_sales_channels.orders.index",
    [

      customerSalesChannel.customer_sales_channel_slug])
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(reference)="{ item: customerSalesChannel }">
            <Link :href="customerSalesChannelRoute(customerSalesChannel) as string" class="primaryLink">
                {{ customerSalesChannel["reference"] }}
            </Link>
        </template>
        <template #cell(number_portfolios)="{ item: customerSalesChannel }">
            <Link :href="portfoliosRoute(customerSalesChannel) as string" class="secondaryLink">
                {{ customerSalesChannel["number_portfolios"] }}
            </Link>
        </template>
        <template #cell(number_clients)="{ item: customerSalesChannel }">
            <Link :href="clientsRoute(customerSalesChannel) as string" class="secondaryLink">
                {{ customerSalesChannel["number_clients"] }}
            </Link>
        </template>
        <template #cell(number_orders)="{ item: customerSalesChannel }">
            <Link :href="ordersRoute(customerSalesChannel) as string" class="secondaryLink">
                {{ customerSalesChannel["number_orders"] }}
            </Link>
        </template>
    </Table>
</template>
