<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import Icon from "@/Components/Icon.vue";
import { faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faPowerOff, faExclamationTriangle, faTrashAlt, faFolders, faFolderTree, faGameConsoleHandheld } from "@fal";
import { faPlay } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";
import Image from "@common/Components/Image.vue";
import Tag from "@/Components/Tag.vue";
import { GridProducts } from "@/Components/Product"
import { faExternalLink } from "@far";


library.add(faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faPlay, faFolders, faFolderTree);

defineProps<{
    data: {}
    tab?: string
}>();


function collectionRoute(collection): string {
    const current = route().current()
    if (current === 'retina.catalogue.collections.index') {
        return route('retina.catalogue.collections.show', [collection.slug])
    }
    return route('retina.catalogue.collections.show', [collection.slug])
}


</script>

<template>
    <Table :resource="data" :name="tab"  class="mt-5 hidden md:block">
         <template #cell(image)="{ item: item }">
            <div class="flex justify-center">
                <Image :src="item['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>
        <template #cell(state)="{ item: collection }">
            <Tag :label="collection.state_icon.tooltip" v-tooltip="collection.state_icon.tooltip">
                <template #label>
                    <Icon :data="collection.state_icon" /> <span :class="collection.state_icon.class">{{
                        collection.state_icon.tooltip }}</span>
                </template>
            </Tag>
        </template>
        <template #cell(code)="{ item: collection }">

            <Link :href="collectionRoute(collection) as string" class="primaryLink">
            {{ collection["code"] }}
            </Link>
        </template>


        <template #cell(department_code)="{ item: collection }">
            <!--    <Link :href="departmentRoute(collection) as string" class="secondaryLink"> -->
            {{ collection["department_code"] }}
            <!--   </Link> -->
        </template>

        <template #cell(parents)="{ item: collection }">
            <template v-for="(parent, index) in collection.parents_data" :key="index">
                <FontAwesomeIcon v-if="parent.type === 'department'" :icon="faFolderTree" class="mr-1"
                    v-tooltip="trans('Department')" />
                <FontAwesomeIcon v-else-if="parent.type === 'subdepartment'" :icon="faFolders" class="mr-1"
                    v-tooltip="trans('Sub Department')" />
            </template>
        </template>
    </Table>


    <GridProducts :resource="data" :preserve-scroll="true" class="mt-5 md:hidden" :name="tab"
        :gridClass="'grid grid-cols-1'">
        <template #card="{ item }">
            <div
                class="group flex items-center gap-4 rounded-xl border border-gray-200 bg-white p-4 transition-all hover:border-primary-300 hover:shadow-sm">
                <Image
                    :src="item.image_thumbnail ?? item.web_images?.main?.thumbnail ?? item.web_images?.main?.original"
                    class="h-12 w-12 rounded-full object-cover shadow-sm flex-shrink-0" />

                <div class="min-w-0 flex-1">
                       <Link :href="collectionRoute(item) as string" class="primaryLink">
                        {{ item.code }}
                    </Link>

                    <p class="mt-2 p-1 truncate text-sm text-gray-500">
                        {{ item.name }}
                    </p>
                </div>

                <a v-if="item.public_url" :href="item.public_url" target="_blank"
                    class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-primary-600"
                    title="Open public page">
                    <FontAwesomeIcon :icon="faExternalLink" />
                </a>
            </div>
        </template>
  </GridProducts>
</template>
