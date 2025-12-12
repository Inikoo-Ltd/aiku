<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { routeType } from "@/types/route"
import Icon from "@/Components/Icon.vue"
import { faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faFolders, faFolderTree, faFolderDownload } from "@fal"
import { faPlay,faTimesCircle, faCheckCircle  as fasCheckCircle} from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { RouteParams } from "@/types/route-params"
import { trans } from "laravel-vue-i18n"
import { remove as loRemove } from 'lodash-es'
import { ref } from 'vue'
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { faTrash, faEdit } from "@fal"

library.add(fasCheckCircle,faTimesCircle,faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faPlay, faFolders, faFolderTree, faTrash, faEdit)

defineProps<{
    data: {}
    tab?: string
    routes: {
        indexWebpage: routeType
        dataList: routeType
        submitAttach: routeType
        detach: routeType
    }
    website_domain?: string
}>()

const inMasterCollection = route().current() === 'grp.masters.master_shops.show.master_collections.index';

function collectionRoute(collection: { slug: string }) {
  const currentRoute = route().current();

  switch (currentRoute) {
    case "grp.masters.master_shops.show.master_collections.index":
    case 'grp.masters.master_shops.show.master_departments.show.master_collections.index':
      return route(
        "grp.masters.master_shops.show.master_collections.show",
        [
          (route().params as RouteParams).masterShop,
          collection.slug
        ]
      );

    default:
      return null;
  }
}

function parentRoute(slug: string, type: string) {
    if(inMasterCollection && type)
    {
        const routeLink = type === 'department' ? 'grp.masters.master_shops.show.master_departments.show' : 'grp.masters.master_shops.show.master_sub_departments.show';
        return route(
            routeLink,
            [
                (route().params as RouteParams).masterShop,
                slug
            ]);
    }

    return route(
        "grp.helpers.redirect_collections_in_product_category",
        [
            slug
        ]
    )

}

// edit route
function editRoute(collection: {}) {
    return route(
        "grp.masters.master_shops.show.master_collections.edit",
        [
            (route().params as RouteParams).masterShop,
            collection.slug
        ]
    )
}

const isLoadingDetach = ref<string[]>([])
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state_icon)="{ item: collection }">
            <Icon :data="collection.state_icon" />
        </template>
        <template #cell(code)="{ item: collection }">
            <div class="flex items-center gap-2">
                <Link :href="collectionRoute(collection) as string" class="primaryLink">
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

        <template #cell(parents)="{ item: collection }">
            <template v-for="(parent, index) in collection.parents_data" :key="index">
                <FontAwesomeIcon v-if="parent.type === 'department'" :icon="faFolderTree" class="mr-1" v-tooltip="trans('Department')" />
                <FontAwesomeIcon v-else-if="parent.type === 'sub_department'" :icon="faFolderDownload" class="mr-1" v-tooltip="trans('Sub Department')" />
                <Link :href="parentRoute(parent.slug, parent.type) as string" class="secondaryLink">
                    {{ parent.code && parent.code.length > 16 ? parent.code.substring(0, 16) + "..." : parent.code }}
                </Link>&nbsp;
            </template>
        </template>

         <template #cell(actions)="{ item }">
            <div class="flex items-center gap-2">
            <!--  <Link v-if="routes?.detach?.name" as="button"
                :href="route(routes.detach.name, { ...routes.detach.parameters, collection : item.id })"
                :method="routes.detach.method" preserve-scroll @start="() => isLoadingDetach.push('detach' + item.id)"
                @finish="() => loRemove(isLoadingDetach, (xx) => xx == 'detach' + item.id)">
            <Button icon="fal fa-times" type="negative" size="xs"
                :loading="isLoadingDetach.includes('detach' + item.id)" />
            </Link>

            <Link :href="editRoute(item)">
             <Button
                        type="tertiary"
                        icon="fal fa-edit"
                        size="s"
                    />
            </Link> -->

            <ModalConfirmationDelete
                :routeDelete="item.delete_route"
                :title="trans('Are you sure you want to delete this master collection?')"
                isFullLoading
                :noLabel="trans('Delete')"
                :noIcon="'fal fa-store-alt-slash'"
            >
                <template #beforeTitle>
                    <div class="text-center font-semibold text-xl mb-4">
                        {{ `${item.name} (${item.code})` }}
                    </div>
                </template>

                <template #default="{ isOpenModal, changeModel }">
                    <Button
                        v-tooltip="item.has_active_webpage ? trans('Cannot delete: Active webpage exists') : trans('Delete master collection')"
                        @click="!item.has_active_webpage && changeModel()"
                        :type="item.has_active_webpage ? 'disabled' : 'negative'"
                        icon="fal fa-trash"
                        size="s"
                        :key="1"
                        :class="{'opacity-50 cursor-not-allowed': item.has_active_webpage}"
                    />
                </template>
            </ModalConfirmationDelete>

            </div>
        </template>
    </Table>
</template>
