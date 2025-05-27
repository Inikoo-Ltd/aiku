<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Family } from "@/types/family"
import { routeType } from "@/types/route"
import { remove as loRemove } from 'lodash-es'
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"


const props = defineProps<{
    data: {}
    tab?: string
    routes: {
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
}>()

// TODO: FIX TS
function collectionRoute(collection: {}) {
    switch (route().current()) {
        case "grp.org.shops.show.catalogue.collections.show":
            return route(
                "grp.org.shops.show.catalogue.collections.show",
                [route().params["organisation"], route().params['shop'], collection.slug])
        case "grp.org.shops.show.catalogue.collections.index":
            return route(
                "grp.org.shops.show.catalogue.collections.show",
                [route().params["organisation"], collection.shop, collection.slug])
        case "grp.org.shops.show.catalogue.dashboard":
            return route(
                "grp.org.shops.show.catalogue.collections.show",
                [route().params["organisation"], collection.shop, collection.slug])
        case 'grp.overview.catalogue.collections.index':
            return route(
                'grp.org.shops.show.catalogue.collections.show',
                [collection.organisation_slug, collection.shop_slug, collection.slug])

        case "grp.org.shops.show.catalogue.departments.show.collection.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.collection.show",
                [route().params["organisation"], route().params['shop'], route().params['department'], collection.slug])
        case "grp.org.shops.show.catalogue.departments.show.families.show.collection.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.families.show.collection.show",
                [route().params["organisation"], route().params['shop'], route().params['department'], route().params['family'], collection.slug])
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.show",
                [route().params["organisation"], route().params['shop'], route().params['department'], route().params['subDepartment'], collection.slug])
        case "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.index":
            return route(
                "grp.org.shops.show.catalogue.departments.show.sub_departments.show.family.show.collection.show",
                [route().params["organisation"], route().params['shop'], route().params['department'], route().params['subDepartment'], route().params['family'],  collection.slug])
        case "grp.org.shops.show.catalogue.families.show.collection.index":
            return route(
                "grp.org.shops.show.catalogue.families.show.collection.show",
                [route().params["organisation"], route().params['shop'], route().params['family'],  collection.slug])
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
    }
}

const isLoadingDetach = ref<string[]>([])
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: collection }">
            <Link :href="collectionRoute(collection)" class="primaryLink">
                {{ collection["code"] }}
            </Link>
        </template>
        <template #cell(shop_code)="{ item: collection }">
            <Link :href="shopRoute(collection)" class="secondaryLink">
                {{ collection["shop_code"] }}
            </Link>
        </template>
        <template #cell(department_code)="{ item: collection }">
            <Link :href="departmentRoute(collection)" class="secondaryLink">
                {{ collection["department_code"] }}
            </Link>
        </template>

        <template #cell(actions)="{ item }">
            <Link
                v-if="routes?.detach?.name"
                as="button"
                :href="route(routes.detach.name, routes.detach.parameters)"
                :method="routes.detach.method"
                :data="{
                    collection: item.id
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
