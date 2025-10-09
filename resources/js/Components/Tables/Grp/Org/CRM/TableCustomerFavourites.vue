<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { RouteParams } from "@/types/route-params";
import { CustomerFavourite } from "@/types/customer-favourite";

defineProps<{
    data: object,
    tab?: string
}>();



function favouriteRoute(favourite: CustomerFavourite) {
    return route(
        "grp.org.shops.show.catalogue.products.all_products.show",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            favourite.slug
        ]);
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: favourite }">
            <Link :href="favouriteRoute(favourite)" class="primaryLink">
            {{ favourite.code }}
            </Link>
        </template>
        <template #cell(name)="{ item: favourite }">
                {{ favourite.name }}
        </template>
    </Table>
</template>
