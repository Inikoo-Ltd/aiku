<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Family } from "@/types/family"
import Icon from "@/Components/Icon.vue"
import { ref } from "vue"
import { routeType } from "@/types/route"
import { remove as loRemove } from 'lodash-es'
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core";
import { faCheck } from "@fal";

library.add(faCheck)

const props = defineProps<{
    data: object
    tab?: string,
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
    isCheckBox?: boolean
}>()

const emits = defineEmits<{
    (e: "selectedRow", value: {}): void
}>()

function familyRoute(family: Family) {
    switch (route().current()) {
        case "grp.shops.show":
        case "grp.org.shops.show.catalogue.families.index":
        case "grp.org.shops.show.catalogue.collections.show":
            return route(
                "grp.org.shops.show.catalogue.families.show",
                [route().params["organisation"], route().params["shop"], family.slug])
        case "grp.org.shops.show.catalogue.departments.show":
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show",
                [route().params["organisation"], route().params["shop"], route().params["department"], family.slug])
        case 'grp.org.shops.index':
            return route(
                "grp.org.shops.show.catalogue.families.show",
                [route().params["organisation"], family.shop_slug, family.slug])
        case "grp.org.shops.show.catalogue.dashboard":
            return route(
                "grp.org.shops.show.catalogue.families.show",
                [route().params["organisation"], route().params["shop"], family.slug])
        case "grp.org.shops.show.catalogue.departments.show.families.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show",
                [route().params["organisation"], route().params["shop"], route().params["department"], family.slug])
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show",
                [route().params["organisation"], route().params["shop"], route().params["department"], route().params["subDepartment"], family.slug])
        case 'grp.overview.catalogue.families.index':
            return route(
                'grp.org.shops.show.catalogue.families.show',
                [family.organisation_slug, family.shop_slug, family.slug])
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
function productRoute(family: Family) {
    switch (route().current()) {
        case 'grp.org.shops.show.catalogue.departments.show.families.index':
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show.products.index",
                [route().params["organisation"], route().params["shop"], route().params["department"], family.slug])
        case 'grp.org.shops.show.catalogue.families.index':
            return route(
                "grp.org.shops.show.catalogue.families.show.products.index",
                [route().params["organisation"], route().params["shop"], family.slug])
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

function subDepartmentRoute(family: Family) {
    switch (route().current()) {
        case 'grp.org.shops.show.catalogue.families.index':
            return route(
                'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                 [route().params["organisation"], route().params["shop"], family.department_slug, family.sub_department_slug])
        case 'grp.org.shops.show.catalogue.departments.show.families.index':
            return route(
                'grp.org.shops.show.catalogue.departments.show.sub_departments.show',
                [route().params["organisation"], route().params["shop"], family.department_slug, family.sub_department_slug])
    }
}

const isLoadingDetach = ref<string[]>([])

</script>

<template>
    <Table
        :resource="data"
        :name="tab"
        class="mt-5"
        :isCheckBox="isCheckBox"
        @onSelectRow="(item) => (console.log('qqqq', item), emits('selectedRow', item))"
    >
        <template #cell(state)="{ item: family }">
            <Icon :data="family.state" />
        </template>
        <template #cell(code)="{ item: family }">
            <Link :href="familyRoute(family)" class="primaryLink">
                {{ family["code"] }}
            </Link>
        </template>
        <template #cell(shop_code)="{ item: family }">
            <Link :href="shopRoute(family)" class="secondaryLink">
                {{ family["shop_code"] }}
            </Link>
        </template>
        <template #cell(current_products)="{ item: family }">
            <Link :href="productRoute(family)" class="primaryLink">
                {{ family["current_products"] }}
            </Link>
        </template>
        
        <!-- Column: Department code -->
        <template #cell(department_code)="{ item: family }">
            <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink">
                {{ family["department_code"] }}
            </Link>

            <div v-else>
                {{ family["department_code"] }}
            </div>
        </template>

        <!-- Column: Department name -->
        <template #cell(department_name)="{ item: family }">
            <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink">
                {{ family["department_name"] }}
            </Link>
        </template>

        <template #cell(product_categories)="{ item: family }">
            <!-- <Link v-if="family.department_slug" :href="departmentRoute(family)" class="secondaryLink">
                {{ family["department_code"] }}
            </Link> -->
        </template>
        <template #cell(sub_department_name)="{ item: family }">
            <Link v-if="family.sub_department_slug" :href="subDepartmentRoute(family)" class="secondaryLink">
                {{ family["sub_department_code"] }}
            </Link>
            <span v-else class="text-xs text-gray-400 italic">-</span>
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


