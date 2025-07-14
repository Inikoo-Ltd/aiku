<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params"
import { CustomerSalesChannel } from "@/types/customer-sales-channel"

defineProps<{
    data: TableTS,
}>()


function platformRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
        "grp.org.shops.show.crm.customers.show.customer_sales_channels.show",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            (route().params as RouteParams).customer,
            customerSalesChannel.slug])
}

function portfoliosRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
        "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios.index",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            (route().params as RouteParams).customer,
            customerSalesChannel.slug])
}

function clientsRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
        "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            (route().params as RouteParams).customer,
            customerSalesChannel.slug])
}

function ordersRoute(customerSalesChannel: CustomerSalesChannel) {
    return route(
        "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            (route().params as RouteParams).customer,
            customerSalesChannel.slug])
}

</script>
<template>
    <Table :resource="data">
        <template #cell(name)="{ item: customerSalesChannel }">
            <div class="flex items-center gap-2">
                <img v-tooltip="customerSalesChannel.platform_name" :src="customerSalesChannel.platform_image" :alt="customerSalesChannel.platform_name"
                     class="w-6 h-6" />
                <Link :href="platformRoute(customerSalesChannel) as string" class="primaryLink">
                    {{ customerSalesChannel["name"] }}<span v-if="!customerSalesChannel['name']" class="italic">{{ customerSalesChannel["reference"] }}</span>
                </Link>
            </div>
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
