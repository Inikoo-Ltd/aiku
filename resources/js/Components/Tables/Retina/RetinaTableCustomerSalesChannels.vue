
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 May 2025 09:42:22 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from '@/Components/Table/Table.vue'
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params";
import { Platform } from "@/types/platform";

defineProps<{
    data: TableTS,
}>()



function platformRoute(platform: Platform) {
  return route(
    "grp.org.shops.show.crm.customers.show.customer_sales_channels.show",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).shop,
      (route().params as RouteParams).customer,
      platform.slug])
}

function portfoliosRoute(platform: Platform) {
  return route(
    "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios.index",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).shop,
      (route().params as RouteParams).customer,
      platform.slug])
}
function clientsRoute(platform: Platform) {
  return route(
    "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).shop,
      (route().params as RouteParams).customer,
      platform.slug])
}
function ordersRoute(platform: Platform) {
  return route(
    "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).shop,
      (route().params as RouteParams).customer,
      platform.slug])
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(reference)="{ item: platform }">
            <Link :href="platformRoute(platform) as string" class="primaryLink">
                {{ platform["reference"] }}
            </Link>
        </template>
        <template #cell(number_portfolios)="{ item: platform }">
            <Link :href="portfoliosRoute(platform) as string" class="secondaryLink">
                {{ platform["number_portfolios"] }}
            </Link>
        </template>
        <template #cell(number_clients)="{ item: platform }">
            <Link :href="clientsRoute(platform) as string" class="secondaryLink">
                {{ platform["number_clients"] }}
            </Link>
        </template>
        <template #cell(number_orders)="{ item: platform }">
            <Link :href="ordersRoute(platform) as string" class="secondaryLink">
                {{ platform["number_orders"] }}
            </Link>
        </template>
    </Table>
</template>
