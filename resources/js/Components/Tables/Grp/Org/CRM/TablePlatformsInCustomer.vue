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
    "grp.org.shops.show.crm.customers.show.platforms.show",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).shop,
      (route().params as RouteParams).customer,
      platform.slug])
}

function portfoliosRoute(platform: Platform) {
  return route(
    "grp.org.shops.show.crm.customers.show.platforms.show.portfolios.index",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).shop,
      (route().params as RouteParams).customer,
      platform.slug])
}
function clientsRoute(platform: Platform) {
  return route(
    "grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.manual.index",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).shop,
      (route().params as RouteParams).customer,
      platform.slug])
}
function ordersRoute(platform: Platform) {
  return route(
    "grp.org.shops.show.crm.customers.show.platforms.show.orders.index",
    [
      (route().params as RouteParams).organisation,
      (route().params as RouteParams).shop,
      (route().params as RouteParams).customer,
      platform.slug])
}

</script>
<template>
     <Table :resource="data" >
        <template #cell(code)="{ item: platform }">
            <Link :href="platformRoute(platform) as string" class="primaryLink">
                {{ platform["code"] }}
            </Link>
        </template>
        <template #cell(number_portfolios)="{ item: platform }">
            <Link :href="portfoliosRoute(platform) as string" class="primaryLink">
                {{ platform["number_portfolios"] }}
            </Link>
        </template>
        <template #cell(number_clients)="{ item: platform }">
            <Link :href="clientsRoute(platform) as string" class="primaryLink">
                {{ platform["number_clients"] }}
            </Link>
        </template>
        <template #cell(number_orders)="{ item: platform }">
            <Link :href="ordersRoute(platform) as string" class="primaryLink">
                {{ platform["number_orders"] }}
            </Link>
        </template>
    </Table>
</template>
