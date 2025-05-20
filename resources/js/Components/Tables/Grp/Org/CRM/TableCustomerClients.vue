<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import { inject } from "vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { RouteParams } from "@/types/route-params";
import { CustomerSalesChannel } from "@/types/customer-client";

const props = defineProps<{
    data: {}
    tab?: string
}>();

const locale = inject("locale", aikuLocaleStructure);


function customerRoute(customer: CustomerSalesChannel) {
    switch (route().current()) {
        case "grp.org.shops.show.crm.customers.show.customer_clients.index":
            return route(
                "grp.org.shops.show.crm.customers.show.customer_clients.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).customer,
                    customer.ulid
                ]);
        case "grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.customer_sales_channels.show.customer_clients.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    (route().params as RouteParams).fulfilmentCustomer,
                    (route().params as RouteParams).customerSalesChannel,
                    customer.ulid]);
        case "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.index":
            return route(
                "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.customer_clients.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    (route().params as RouteParams).customer,
                    (route().params as RouteParams).customerSalesChannel,
                    customer.ulid]);
        case "grp.org.fulfilments.show.crm.customers.show.customer_clients.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.customer_clients.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).fulfilment,
                    (route().params as RouteParams).fulfilmentCustomer,
                    customer.ulid]);
        case "retina.dropshipping.customer_clients.index":
        case "retina.dropshipping.platforms.client.index":
            return route(
                "retina.dropshipping.customer_clients.show",
                [customer.ulid]
            );
        case "retina.fulfilment.dropshipping.customer_sales_channels.client.index":
            return route(
                "retina.fulfilment.dropshipping.customer_sales_channels.client.show",
                [route().params["customerSalesChannel"], customer.ulid]
            )
        default:
            return "";
    }
}
</script>

<template>
    <div class="overflow-x-auto">
        <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(name)="{ item: customer }">
                <Link :href="customerRoute(customer)" class="primaryLink">
                    {{ customer["name"] }}
                </Link>
            </template>

            <template #cell(location)="{ item: customer }">
                <AddressLocation :data="customer['location']" />
            </template>

            <template #cell(created_at)="{ item: customer }">
                <div class="text-gray-500">{{ useFormatTime(customer["created_at"]) }}</div>
            </template>
        </Table>
    </div>
</template>
