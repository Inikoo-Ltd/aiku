<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Department } from "@/types/department";
import Icon from "@/Components/Icon.vue";
import { faSeedling } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { RouteParams } from "@/types/route-params";

library.add(faSeedling);

defineProps<{
    data: {}
    tab?: string
}>();

function departmentRoute(department: Department) {
    return route(
        "grp.org.shops.show.catalogue.departments.show",
        [
            department.organisation_slug,
            department.shop_slug,
            department.slug
        ]
    );
}


function familyRoute(department: Department) {
    return route(
        "grp.org.shops.show.catalogue.departments.show.families.index",
        [
            department.organisation_slug,
            department.shop_slug,
            department.slug
        ]);
}

function productRoute(department: Department) {
    return route(
        "grp.org.shops.show.catalogue.departments.show.products.index",
        [
            department.organisation_slug,
            department.shop_slug,
            department.slug]);
}

function organisationRoute(department: Department) {
    return route(
        "grp.org.overview.departments.index",
        [department.organisation_slug]);
}

function departmentsInShopRoute(department: Department) {
    return route(
        "grp.org.shops.show.catalogue.departments.index",
        [department.organisation_slug, department.shop_slug]
    );
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">

        <template #cell(organisation_code)="{ item: department }">
            <Link v-tooltip='department["organisation_name"]' :href="organisationRoute(department)" class="secondaryLink">
                {{ department["organisation_code"] }}
            </Link>
        </template>

        <template #cell(shop_code)="{ item: department }">
            <Link v-tooltip='department["shop_name"]' :href="departmentsInShopRoute(department) as string" class="secondaryLink">
                {{ department["shop_code"] }}
            </Link>
        </template>

        <template #cell(state)="{ item: department }">
            <Icon :data="department.state">
            </Icon>
        </template>
        <template #cell(code)="{ item: department }">
            <Link :href="departmentRoute(department) as string" class="primaryLink">
                {{ department["code"] }}
            </Link>
        </template>
        <template #cell(number_current_families)="{ item: department }">
            <Link :href="familyRoute(department)" class="secondaryLink">
                {{ department["number_current_families"] }}
            </Link>
        </template>
        <template #cell(number_current_products)="{ item: department }">
            <Link :href="productRoute(department) as string" class="secondaryLink">
                {{ department["number_current_products"] }}
            </Link>
        </template>


    </Table>
</template>
