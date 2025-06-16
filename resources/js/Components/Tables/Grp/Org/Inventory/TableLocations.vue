<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:31:26 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Location } from "@/types/location";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBox, faHandHoldingBox, faPallet, faPencil } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Table as TableTS } from "@/types/Table";
import { RouteParams } from "@/types/route-params";

library.add(faBox, faHandHoldingBox, faPallet, faPencil);

defineProps<{
    data: TableTS,
    tab?: string
}>();


function locationRoute(location: Location) {
    switch (route().current()) {
        case "grp.org.warehouses.show.infrastructure.dashboard":
        case "grp.org.warehouses.show.infrastructure.locations.index":
            return route(
                "grp.org.warehouses.show.infrastructure.locations.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).warehouse,
                    location.slug]);
        case "grp.org.warehouse-areas.show":
        case "grp.org.warehouse-areas.locations.index":
        case  "grp.overview.inventory.locations.index":
            return route(
                "grp.org.warehouse-areas.show.locations.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).warehouseArea,
                    location.slug]
            );

        case "grp.org.warehouses.show.infrastructure.warehouse_areas.show":
        case "grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index":
            return route(
                "grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).warehouse,
                    (route().params as RouteParams).warehouseArea,
                    location.slug
                ]);

        default:
            return route(
                "grp.org.locations.show",
                [
                    (route().params as RouteParams).organisation,
                    location.slug
                ]);
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Code -->
        <template #cell(code)="{ item: location }">
            <Link :href="locationRoute(location)" class="primaryLink">
                {{ location.code }}
            </Link>
        </template>

        <!-- Column: Scope -->
        <template #cell(scope)="{ item: location }">
            <div class="flex">
                <div v-tooltip="location.allow_stocks ? 'Allow stock' : 'No stock'" class="px-1 py-0.5">
                    <FontAwesomeIcon icon="fal fa-box" fixed-width aria-hidden="true"
                                     :class="[location.allow_stocks ? location.has_stock_slots ? 'text-green-500' : 'text-gray-400' : location.has_stock_slots ? 'text-red-500' : 'text-gray-400']"
                    />
                </div>
                <div v-tooltip="location.allow_dropshipping ? 'Allow dropshipping' : 'No dropshipping'" class="px-1 py-0.5">
                    <FontAwesomeIcon icon="fal fa-hand-holding-box" class="" fixed-width aria-hidden="true"
                                     :class="[location.allow_dropshipping ? location.has_dropshipping_slots ? 'text-green-500' : 'text-gray-400' : location.has_dropshipping_slots ? 'text-red-500' : 'text-gray-400']"
                    />
                </div>
                <div v-tooltip="location.allow_fulfilment ? 'Allow fulfilment' : 'No fulfilment'" class="px-1 py-0.5">
                    <FontAwesomeIcon icon="fal fa-pallet" class="" fixed-width aria-hidden="true"
                                     :class="[location.allow_fulfilment ? location.has_fulfilment ? 'text-green-500' : 'text-gray-400' : location.has_fulfilment ? 'text-red-500' : 'text-gray-400']"
                    />
                </div>
            </div>


        </template>

    </Table>
</template>
