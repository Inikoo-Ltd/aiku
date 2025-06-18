<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Jun 2025 21:39:47 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import Icon from "@/Components/Icon.vue";
import { faSeedling } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Shop } from "@/types/shop";

library.add(faSeedling);

defineProps<{
    data: {}
    tab?: string
}>();

function shopRoute(shop: Shop) {
    return route(
        "grp.org.shops.show.catalogue.dashboard",
        [
            shop.organisation_slug,
            shop.slug
        ]
    );
}


function familyRoute(shop: Shop) {
    return route(
        "grp.org.shops.show.catalogue.families.index",
        [
            shop.organisation_slug,
            shop.slug
        ]);
}

function productRoute(shop: Shop) {
    return route(
        "grp.org.shops.show.catalogue.products.index",
        [
            shop.organisation_slug,
            shop.slug]);
}

function organisationRoute(shop: Shop) {
    return route(
        "grp.org.overview.shops.index",
        [shop.organisation_slug]);
}




</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">

        <template #cell(organisation_code)="{ item: shop }">
            <Link v-tooltip='shop["organisation_name"]' :href="organisationRoute(shop)" class="secondaryLink">
                {{ shop["organisation_code"] }}
            </Link>
        </template>



        <template #cell(state)="{ item: shop }">
            <Icon :data="shop.state">
            </Icon>
        </template>
        <template #cell(code)="{ item: shop }">
            <Link :href="shopRoute(shop) as string" class="primaryLink">
                {{ shop["code"] }}
            </Link>
        </template>
        <template #cell(number_current_departments)="{ item: shop }">
            <Link :href="familyRoute(shop)" class="secondaryLink">
                {{ shop["number_current_departments"] }}
            </Link>
        </template>
        <template #cell(number_current_families)="{ item: shop }">
            <Link :href="familyRoute(shop)" class="secondaryLink">
                {{ shop["number_current_families"] }}
            </Link>
        </template>
        <template #cell(number_current_products)="{ item: shop }">
            <Link :href="productRoute(shop) as string" class="secondaryLink">
                {{ shop["number_current_products"] }}
            </Link>
        </template>


    </Table>
</template>
