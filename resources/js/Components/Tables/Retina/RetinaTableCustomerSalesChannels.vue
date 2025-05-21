<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 May 2025 09:42:22 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params"
import { Platform } from "@/types/platform"
import Image from "@/Components/Image.vue"
import { CustomerSalesChannel } from "@/types/customer-sales-channel";

defineProps<{
    data: TableTS,
}>()



function platformRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
    "retina.dropshipping.customer_sales_channels.show",
    [customerSalesChannel.slug])
}

function portfoliosRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
    "retina.dropshipping.customer_sales_channels.portfolios.index",
    [customerSalesChannel.slug])
}
function clientsRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
    "retina.dropshipping.customer_sales_channels.client.index",
    [customerSalesChannel.slug])
}
function ordersRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
    "retina.dropshipping.customer_sales_channels.orders.index",
    [customerSalesChannel.slug])
}

</script>
<template>
    <Table :resource="data">
        <template #cell(platform_name)="{ item: customerSalesChannel }">
            <div class="flex items-center gap-2">
                <img :src="customerSalesChannel.platform_image" :alt="customerSalesChannel.platform_name" class="w-6 h-6" />
                {{ customerSalesChannel.platform_name }}
            </div>
        </template>

        <template #cell(reference)="{ item: customerSalesChannel }">
            <Link :href="(platformRoute(customerSalesChannel) as string)" class="primaryLink">
            {{ customerSalesChannel["reference"] }}
            </Link>
        </template>
        <template #cell(number_portfolios)="{ item: customerSalesChannel }">
            <Link :href="(portfoliosRoute(customerSalesChannel) as string)" class="secondaryLink">
            {{ customerSalesChannel["number_portfolios"] }}
            </Link>
        </template>
        <template #cell(number_clients)="{ item: customerSalesChannel }">
            <Link :href="(clientsRoute(customerSalesChannel) as string)" class="secondaryLink">
            {{ customerSalesChannel["number_clients"] }}
            </Link>
        </template>
        <template #cell(number_orders)="{ item: customerSalesChannel }">
            <Link :href="(ordersRoute(customerSalesChannel) as string)" class="secondaryLink">
            {{ customerSalesChannel["number_orders"] }}
            </Link>
        </template>
    </Table>
</template>
