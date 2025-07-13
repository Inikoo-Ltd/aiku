<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import customer from "@/Pages/Grp/Org/Shop/CRM/Customer.vue"
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: object,
    tab?: string
}>();


function favouriteRoute(favourite: {}) {
    if (route().current() === "grp.org.shops.show.catalogue.products.current_products.show") {
        return route(
            "grp.org.shops.show.crm.customers.show",
            [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            favourite.slug
            ]);
    } else {
        return route(
            "grp.org.shops.show.crm.customers.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                customer.slug
            ]);
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: favourite }">
            <Link :href="favouriteRoute(favourite)" class="primaryLink">
            {{ favourite["reference"] }}
            </Link>
        </template>
        <template #cell(contact_name)="{ item: favourite }">
                {{ favourite["contact_name"] }}
        </template>
        <template #cell(email)="{ item: favourite }">
                {{ favourite["email"] }}
        </template>
        <template #cell(phone)="{ item: favourite }">
                {{ favourite["phone"] }}
        </template>
    </Table>
</template>
