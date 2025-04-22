<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { FulfilmentCustomer } from "@/types/Customer";
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import { useLocaleStore } from "@/Stores/locale";

const props = defineProps<{
    data: object,
    tab?: string
}>();

const locale = useLocaleStore();


function customerRoute(customer: FulfilmentCustomer) {
    switch (route().current()) {
        case "shops.show.customers.index":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [customer.shop_slug, customer.slug]);
        case "grp.fulfilment.customers.index":
            return route(
                "grp.fulfilment.customers.show",
                [customer.slug]);
        case "grp.overview.crm.customers.index":
            return null;
        case "grp.org.overview.customers.index":
        case "grp.org.overview.crm.customers.index":
            return null;
        default:
            return route(
                "grp.org.shops.show.crm.customers.show",
                [
                    route().params["organisation"],
                    route().params["shop"],
                    customer.slug
                ]);
    }
}

function shopRoute(customer: FulfilmentCustomer) {
    switch (route().current()) {
        case "shops.show.customers.index":
            return route(
                "shops.show",
                [customer.shop_slug]);
        default:
            return route(
                "shops.show",
                [customer.shop_slug]);
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: customer }">
            <Link :href="customerRoute(customer)" class="primaryLink">
                {{ customer["reference"] }}
            </Link>
        </template>
        <template #cell(shop)="{ item: customer }" class="primaryLink">
            <Link :href="shopRoute(customer)">
                {{ customer["shop"] }}
            </Link>
        </template>
        <template #cell(location)="{ item: customer }">
            <AddressLocation :data="customer['location']" />
        </template>

        <template #cell(invoiced_net_amount)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat( customer.currency_code, customer.sales_all)  }}</div>
        </template>
        <template #cell(sales_all)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat( customer.currency_code, customer.sales_all)  }}</div>
        </template>
    </Table>
</template>


