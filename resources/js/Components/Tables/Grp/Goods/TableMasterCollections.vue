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
import { faTrash, faEdit,faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faFolders, faFolderTree, faFolderDownload, faMinus } from "@fal"
import { faPlay,faTimesCircle, faCheckCircle  as fasCheckCircle, faTriangle, faEquals} from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { RouteParams } from "@/types/route-params"
import { trans } from "laravel-vue-i18n"
import { ref, inject } from 'vue'
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(fasCheckCircle,faTimesCircle,faSeedling, faBroadcastTower, faPauseCircle, faSunset, faSkull, faCheckCircle, faLockAlt, faHammer, faExclamationTriangle, faPlay, faFolders, faFolderTree, faTrash, faEdit, faTriangle, faEquals, faMinus)

const props = defineProps<{
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
const locale = inject("locale", aikuLocaleStructure)

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


function masterDepartmentRoute(master) {
    return route(
        "grp.masters.master_shops.show.master_departments.show",
        {
            masterShop: (route().params as RouteParams).masterShop,
            masterDepartment: master.slug
        });
}



function masterSubDepartmentRoute(master) {
    return route(
        "grp.masters.master_shops.show.master_sub_departments.show",
        {
            masterShop: (route().params as RouteParams).masterShop,
            masterSubDepartment: master.slug
        }
    );
}

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return {
            icon: faTriangle
        }
    } else if (!isPositive) {
        return {
            icon: faTriangle,
            class: 'rotate-180'
        }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    if (isPositive) {
        return 'text-green-500'
    } else if (!isPositive) {
        return 'text-red-500'
    }
}

console.log('ssss',props)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(image_thumbnail)="{ item: collection }">
            <div class="flex justify-center">
                <Image :src="collection['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>
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



       <!--  <template #cell(parents)="{ item: collection }">
            <template v-for="(parent, index) in collection.parents_data" :key="index">
                <FontAwesomeIcon v-if="parent.type === 'department'" :icon="faFolderTree" class="mr-1" v-tooltip="trans('Department')" />
                <FontAwesomeIcon v-else-if="parent.type === 'sub_department'" :icon="faFolderDownload" class="mr-1" v-tooltip="trans('Sub Department')" />
                <Link :href="parentRoute(parent.slug, parent.type) as string" class="secondaryLink">
                    {{ parent.code && parent.code.length > 16 ? parent.code.substring(0, 16) + "..." : parent.code }}
                </Link>&nbsp;
            </template>
        </template> -->

        <template #cell(master_department)="{ item: department }">
            <span class="inline-flex max-w-full overflow-hidden whitespace-nowrap">
                <template v-for="(item, index) in department.departments_data" :key="item.id ?? index">
                    <Link v-if="item.slug" v-tooltip="item.name" :href="masterDepartmentRoute(item) as string"
                        class="secondaryLink truncate max-w-[90px] inline-block">
                        {{ item.code }}
                    </Link>
                    <span v-if="index < department.departments_data.length - 1">, </span>
                </template>
            </span>
        </template>



         <template #cell(master_sub_department)="{ item: subdepartment }">
             <span class="inline-flex max-w-full overflow-hidden whitespace-nowrap">
                <template v-for="(item, index) in subdepartment.sub_departments_data" :key="item.id ?? index">
                    <Link v-if="item.slug" v-tooltip="item.name" :href="masterSubDepartmentRoute(item) as string"
                        class="secondaryLink truncate max-w-[90px] inline-block">
                        {{ item.code }}
                    </Link>
                    <span v-if="index < subdepartment.sub_departments_data.length - 1">, </span>
                </template>
            </span>
        </template>

         <template #cell(sales)="{ item: collection }">
            <span class="tabular-nums">{{ locale.currencyFormat(collection.currency_code, collection.sales) }}</span>
        </template>

        <template #cell(sales_delta)="{ item }">
            <div v-if="item.sales_delta">
                <span>{{ item.sales_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_delta.is_positive).class,
                        getIntervalStateColor(item.sales_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faEquals"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
        </template>

        <template #cell(invoices_delta)="{ item }">
            <div v-if="item.invoices_delta">
                <span>{{ item.invoices_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.invoices_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.invoices_delta.is_positive).class,
                        getIntervalStateColor(item.invoices_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faEquals"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
        </template>

         <template #cell(actions)="{ item }">
            <div class="flex items-center gap-2">
            <ModalConfirmationDelete
                :routeDelete="item.delete_route"
                :title="trans('Are you sure you want to delete this master collection?')"
                :description="trans('Doing so would delete all of the collections and permanently delete their webpages in every single shop under this master collection 😥 .This action cannot be undone.')"
                isFullLoading
                :noLabel="trans('Delete')"
                :noIcon="'fal fa-store-alt-slash'"
            >
                <template #beforeTitle>
                    <div class="text-center font-semibold text-xl mb-4">
                        {{ trans('Deleting master collection :_masterCollection', {_masterCollection: item.name}) }} <br>
                        {{ `(${item.code})` }}
                    </div>
                </template>

                <template #default="{ isOpenModal, changeModel }">
                    <Button
                        v-tooltip="trans('Delete master collection')"
                        @click="changeModel()"
                        :type="'negative'"
                        icon="fal fa-trash"
                        size="s"
                        :key="1"
                    />
                </template>
            </ModalConfirmationDelete>

            </div>
        </template>
    </Table>
</template>
