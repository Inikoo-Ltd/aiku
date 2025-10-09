<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { SubDepartmentx } from "@/types/SubDepartment"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"
import { Family } from "@/types/family"
import { faCheck, faTimesCircle, faCheckCircle } from "@fal";

defineProps<{
    data: object
    tab?: string,
}>()


function subDepartmentRoute(SubDepartment: SubDepartmentx) {
    const currentRoute = route().current();
    if (currentRoute === "grp.shops.show" || currentRoute === "grp.org.shops.show.catalogue.departments.show.sub_departments.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.sub_departments.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                (route().params as RouteParams).department,
                SubDepartment.slug])
    } else if (currentRoute === "grp.org.shops.show.catalogue.sub_departments.index") {
        return route(
            "grp.org.shops.show.catalogue.sub_departments.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                SubDepartment.slug
            ]
        )
    } else {
        return route(
            "grp.org.shops.show.catalogue.sub_departments.show",
            [
                SubDepartment.organisation_slug,
                SubDepartment.shop_slug,
                SubDepartment.slug
            ]
        )
    }
}

function familiesRoute(SubDepartment: SubDepartmentx) {
    const currentRoute = route().current();
    if (currentRoute === "grp.org.shops.show.catalogue.departments.show.sub_departments.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                (route().params as RouteParams).department,
                SubDepartment.slug])
    } else if (currentRoute === "grp.org.shops.show.catalogue.sub_departments.index") {
        return route(
            "grp.org.shops.show.catalogue.sub_departments.show.families.index",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                SubDepartment.slug
            ]
        )
    }
}

function productsRoute(SubDepartment: SubDepartmentx) {
    const currentRoute = route().current();
    if (currentRoute === "grp.org.shops.show.catalogue.departments.show.sub_departments.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show.sub_departments.show.product.index",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                (route().params as RouteParams).department,
                SubDepartment.slug])
    } else if (currentRoute === "grp.org.shops.show.catalogue.sub_departments.index") {
        return route(
            "grp.org.shops.show.catalogue.sub_departments.show.products.index",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                SubDepartment.slug
            ]
        )
    }
}

function shopRoute(family: Family) {
    const currentRoute = route().current();
    if (currentRoute === "grp.org.shops.index") {
        return route(
            "grp.org.shops.show.catalogue.dashboard",
            [
                (route().params as RouteParams).organisation,
                family.shop_slug])
    }

    return route(
        "grp.org.shops.show.catalogue.dashboard",
        [
            family.organisation_slug,
            family.shop_slug])
}

function departmentRoute(family: Family) {
    const currentRoute = route().current();
    if (currentRoute === "grp.org.shops.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.index",
            [
                (route().params as RouteParams).organisation,
                family.shop_slug, family.department_slug])
    } else if (currentRoute === "grp.org.shops.show.catalogue.dashboard" || 
               currentRoute === "grp.org.shops.show.catalogue.families.index") {
        return route(
            "grp.org.shops.show.catalogue.departments.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                family.department_slug])
    }
}

function masterSubDepartmentRoute(SubDepartment: SubDepartmentx) {
    if (!SubDepartment.master_product_category_id) {
        return ""
    }

    return route(
        "grp.helpers.redirect_master_product_category",
        [SubDepartment.master_product_category_id])
}


const dotClass = (filled: boolean) =>
    filled ? "bg-green-100 text-green-600" : "bg-red-100 text-red-600";
const statusIcon = (filled: boolean) => (filled ? faCheckCircle : faTimesCircle);
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: SubDepartment }">
            <Icon :data="SubDepartment.state">
            </Icon>
        </template>
        <template #cell(code)="{ item: SubDepartment }">
            <div class="whitespace-nowrap">
                <Link :href="(masterSubDepartmentRoute(SubDepartment) as string)" v-tooltip="trans('Go to Master')" class="mr-1"
                      :class="[ SubDepartment.master_product_category_id ? 'opacity-70 hover:opacity-100' : 'opacity-0']">
                    <FontAwesomeIcon
                        icon="fab fa-octopus-deploy"
                        color="#4B0082"
                    />
                </Link>
                <Link :href="subDepartmentRoute(SubDepartment) as string" class="primaryLink">
                    {{ SubDepartment["code"] }}
                </Link>
            </div>
        </template>
        <template #cell(shop_code)="{ item: family }">
            <Link :href="shopRoute(family) as string" class="secondaryLink">
                {{ family["shop_code"] }}
            </Link>
        </template>
        <template #cell(department_code)="{ item: family }">
            <Link v-if="family.department_slug" :href="departmentRoute(family) as string" class="secondaryLink">
                {{ family["department_code"] }}
            </Link>
        </template>
        <template #cell(number_families)="{ item: SubDepartment }">
            <Link :href="familiesRoute(SubDepartment) as string" class="secondaryLink">
                {{ SubDepartment["number_families"] }}
            </Link>
        </template>
        <template #cell(number_products)="{ item: SubDepartment }">
            <Link :href="productsRoute(SubDepartment) as string" class="secondaryLink">
                {{ SubDepartment["number_products"] }}
            </Link>
        </template>
                <template #cell(is_name_reviewed)="{ item }">
            <div >
                <FontAwesomeIcon :class="[
                    'flex items-center justify-center w-4 h-4 rounded-full',
                    dotClass(item.is_name_reviewed),
                ]" :icon="statusIcon(item.is_name_reviewed)" v-tooltip="'Review name'" />
            </div>
        </template>
        <template #cell(is_description_reviewed)="{ item }">
            <div>
                <FontAwesomeIcon :class="[
                    'flex items-center justify-center w-4 h-4 rounded-full',
                    dotClass(item.is_description_reviewed),
                ]" :icon="statusIcon(item.is_description_reviewed)" v-tooltip="'Review name'" />
            </div>
        </template>
        <template #cell(is_description_title_reviewed)="{ item }">
            <div>
                <FontAwesomeIcon :class="[
                    'flex items-center justify-center w-4 h-4 rounded-full',
                    dotClass(item.is_description_title_reviewed),
                ]" :icon="statusIcon(item.is_description_title_reviewed)" v-tooltip="'Review name'" />
            </div>
        </template>
        <template #cell(is_description_extra_reviewed)="{ item }">
            <div>
                <FontAwesomeIcon :class="[
                    'flex items-center justify-center w-4 h-4 rounded-full',
                    dotClass(item.is_description_extra_reviewed),
                ]" :icon="statusIcon(item.is_description_extra_reviewed)" v-tooltip="'Review name'" />
            </div>
        </template>
    </Table>
</template>
