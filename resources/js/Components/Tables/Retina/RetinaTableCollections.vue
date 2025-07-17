<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { routeType } from "@/types/route";
import Icon from "@/Components/Icon.vue";
import { faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faPowerOff, faExclamationTriangle, faTrashAlt, faFolders, faFolderTree, faGameConsoleHandheld } from "@fal";
import { faPlay } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";
import Tag from "@/Components/Tag.vue";


library.add(faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faPlay, faFolders, faFolderTree);

const props = defineProps<{
    data: {}
    tab?: string
}>();



</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: collection }">
            <Tag :label="collection.state_icon.tooltip" v-tooltip="collection.state_icon.tooltip">
                <template #label>
                    <Icon :data="collection.state_icon" /> <span :class="collection.state_icon.class">{{
                        collection.state_icon.tooltip }}</span>
                </template>
            </Tag>
        </template>
        <template #cell(code)="{ item: collection }">

            <!--  <Link :href="collectionRoute(collection) as string" class="primaryLink"> -->
            {{ collection["code"] }}
            <!--  </Link> -->
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
                <!--   <Link :href="parentRoute(parent.slug) as string" class="secondaryLink">
                    {{ parent.code && parent.code.length > 6 ? parent.code.substring(0, 6) + "..." : parent.code }}
                </Link> -->
            </template>
        </template>

    </Table>
</template>
