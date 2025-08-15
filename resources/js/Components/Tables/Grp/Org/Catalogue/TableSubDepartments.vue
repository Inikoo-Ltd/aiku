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
import Tag from "@/Components/Tag.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
    data: object
    tab?: string,
}>()


function subDepartmentRoute(SubDepartment: SubDepartmentx) {
    switch (route().current()) {
        case "grp.shops.show":
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.sub_departments.show",
                [route().params["organisation"], route().params["shop"], route().params["department"], SubDepartment.slug])
    }
}

function familiesRoute(SubDepartment: SubDepartmentx) {
    switch (route().current()) {
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index",
                [route().params["organisation"], route().params["shop"], route().params["department"], SubDepartment.slug])
    }
}

function productsRoute(SubDepartment: SubDepartmentx) {
    switch (route().current()) {
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.sub_departments.show.product.index",
                [route().params["organisation"], route().params["shop"], route().params["department"], SubDepartment.slug])
    }
}

function shopRoute(family: Family) {
    switch (route().current()) {
        case 'grp.org.shops.index':
            return route(
                "grp.org.shops.show.catalogue.dashboard",
                [route().params["organisation"], family.shop_slug])
    }
}

function departmentRoute(family: Family) {
    switch (route().current()) {
        case 'grp.org.shops.index':
            return route(
                "grp.org.shops.show.catalogue.departments.index",
                [route().params["organisation"], family.shop_slug, family.department_slug])
        case 'grp.org.shops.show.catalogue.dashboard':
        case 'grp.org.shops.show.catalogue.families.index':
            return route(
                "grp.org.shops.show.catalogue.departments.show",
                [route().params["organisation"], route().params["shop"], family.department_slug])

    }
}

const familyRoute = (item, family) => {
    switch (route().current()) {
        case 'grp.org.shops.show.catalogue.departments.show.sub_departments.index':
            return route('grp.org.shops.show.catalogue.departments.show.families.show', {
                organisation: route().params['organisation'],
                shop: route().params['shop'],
                department: item.department_slug,
                family: family.slug
            })
        default:
            return null
    }
}


function masterSubDepartmentRoute(SubDepartment: SubDepartmentx) {
    if(!SubDepartment.master_product_category_id){
        return '';
    }

    return route(
        "grp.helpers.redirect_master_product_category",
        [SubDepartment.master_product_category_id]);
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: SubDepartment }">
            <Icon :data="SubDepartment.state">
            </Icon>
        </template>
        <template #cell(code)="{ item: SubDepartment }">
            <div class="whitespace-nowrap">
                <Link  :href="(masterSubDepartmentRoute(SubDepartment) as string)"  v-tooltip="trans('Go to Master')" class="mr-1"  :class="[ SubDepartment.master_product_category_id ? 'opacity-70 hover:opacity-100' : 'opacity-0']">
                    <FontAwesomeIcon
                        icon="fab fa-octopus-deploy"
                        color="#4B0082"
                    />
                </Link>
                <Link :href="subDepartmentRoute(SubDepartment)" class="primaryLink">
                {{ SubDepartment["code"] }}
                </Link>
            </div>
        </template>
        <template #cell(shop_code)="{ item: family }">
            <Link :href="shopRoute(family)" class="secondaryLink">
            {{ family["shop_code"] }}
            </Link>
        </template>
        <template #cell(department_code)="{ item: family }">
            <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink">
            {{ family["department_code"] }}
            </Link>
        </template>
        <template #cell(number_families)="{ item: SubDepartment }">
            <Link :href="familiesRoute(SubDepartment)" class="secondaryLink">
            {{ SubDepartment["number_families"] }}
            </Link>
        </template>
        <template #cell(number_products)="{ item: SubDepartment }">
            <Link :href="productsRoute(SubDepartment)" class="secondaryLink">
            {{ SubDepartment["number_products"] }}
            </Link>
        </template>
    </Table>
</template>
