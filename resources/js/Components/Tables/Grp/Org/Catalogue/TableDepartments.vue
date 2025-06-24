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
import { remove as loRemove } from "lodash-es";
import { ref } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { faSeedling } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import routes from "../../../../../../../han/src/constants/Routes";
import { RouteParams } from "@/types/route-params";

library.add(faSeedling);

defineProps<{
    data: {}
    tab?: string
}>();

function departmentRoute(department: Department) {
    switch (route().current()) {
        case "grp.org.shops.show.catalogue.departments.index":
        case "grp.org.shops.show.catalogue.collections.show":
        case "grp.org.shops.show.catalogue.dashboard":
            return route(
                "grp.org.shops.show.catalogue.departments.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    department.slug]);
        case "grp.org.shops.index":
        case "grp.overview.catalogue.departments.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show",
                [
                    (route().params as RouteParams).organisation,
                    department.shop_slug,
                    department.slug]);


        default:
            return null;
    }
}

function shopRoute(department: Department) {
    if (route().current() === "grp.org.shops.index") {
        return route(
            "grp.org.shops.show.catalogue.dashboard",
            [
                (route().params as RouteParams).organisation,
                department.shop_slug]);
    }
    return undefined;
}


function subDepartmentsRoute(department: Department) {
    if (route().current() === "grp.org.shops.show.catalogue.departments.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.sub_departments.index",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                department.slug
            ]);
    }
    return undefined;
}

function subCollectionsRoute(department: Department) {
    if (route().current() === "grp.org.shops.show.catalogue.departments.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.collection.index",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                department.slug
            ]);
    }
    return undefined;
}


function familyRoute(department: Department) {
    if (route().current() === "grp.org.shops.show.catalogue.departments.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.families.index",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                department.slug]);
    }
    return undefined;
}

function productRoute(department: Department) {
    if (route().current() === "grp.org.shops.show.catalogue.departments.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.products.index",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                department.slug]);
    }
    return undefined;
}

function organisationRoute(department: Department) {
    return route(
        "grp.org.overview.departments.index",
        [department.organisation_slug]);
}

function departmentsInShopRoute(department: Department) {
    return route(
        "grp.org.shops.show.catalogue.departments.index",
        [department.organisation_slug, department.shop_slug]);
}


const isLoadingDetach = ref<string[]>([]);

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
            <Link :href="familyRoute(department) as string" class="secondaryLink">
                {{ department["number_current_families"] }}
            </Link>
        </template>
        <template #cell(number_current_sub_departments)="{ item: department }">
            <Link :href="subDepartmentsRoute(department) as string" class="secondaryLink">
                {{ department["number_current_sub_departments"] }}
            </Link>
        </template>
        <template #cell(number_current_collections)="{ item: department }">
            <Link :href="subCollectionsRoute(department) as string" class="secondaryLink">
                {{ department["number_current_collections"] }}
            </Link>
        </template>
        <template #cell(number_current_products)="{ item: department }">
            <Link :href="productRoute(department) as string" class="secondaryLink">
                {{ department["number_current_products"] }}
            </Link>
        </template>


        <template #cell(actions)="{ item }">
            <Link
                v-if="routes?.detach?.name"
                as="button"
                :href="route(routes.detach.name, routes.detach.parameters)"
                :method="routes.detach.method"
                :data="{
                    family: item.id
                }"
                preserve-scroll
                @start="() => isLoadingDetach.push('detach' + item.id)"
                @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)"
            >
                <Button
                    icon="fal fa-times"
                    type="negative"
                    size="xs"
                    :loading="isLoadingDetach.includes('detach' + item.id)"
                />
            </Link>
        </template>
    </Table>
</template>
