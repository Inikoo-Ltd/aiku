<script setup lang="ts">
/*
* Author: Vika Aqordi
* Created on: 18-02-2025-09h-16m
* Github: https://github.com/aqordeon
* Copyright: 2025
*/
import Table from '@/Components/Table/Table.vue'
import { RouteParams } from "@/types/route-params";
import { Link, router } from "@inertiajs/vue3";
import { faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faPowerOff, faExclamationTriangle, faTrashAlt, faFolders, faFolderTree, faGameConsoleHandheld } from "@fal";
import { faPlay } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";

library.add(faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faPlay, faFolders, faFolderTree);

defineProps<{
    data: {

    }
    tab?: string
}>()

function collectionRoute(collection: {}) {
    const currentRoute = route().current();

    if (currentRoute === "grp.masters.master_shops.show.master_collections.index") {
        return route(
            "grp.masters.master_shops.show.master_collections.show",
            [
                (route().params as RouteParams).masterShop,
                collection.slug
            ]);
    } 
    // The empty case for "grp.org.shops.show.catalogue.families.show.collection.index" is omitted as it had no implementation
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(code)="{ item: collection }">
             <div class="flex items-center gap-2">
                <Link :href="(collectionRoute(collection) as string)" class="primaryLink">
                    {{ collection["code"] }}
                </Link>

                <template v-if="collection.state === 'active'">
                    <FontAwesomeIcon
                        v-if="collection.products_status === 'discontinuing'"
                        :icon="faExclamationTriangle"
                        class="text-orange-500"
                        v-tooltip="'Products are being discontinued'"
                    />
                    <FontAwesomeIcon
                        v-else-if="collection.products_status === 'discontinued'"
                        :icon="faExclamationTriangle"
                        class="text-red-600"
                        v-tooltip="'Products are discontinued'"
                    />
                </template>
            </div>
        </template>
    </Table>
</template>
