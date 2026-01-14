<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Jun 2025 02:34:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faBullhorn,
    faCameraRetro,
    faCube,
    faFolder, faMoneyBillWave, faProjectDiagram, faTag, faUser, faFolderDownload
} from "@fal"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableMailshots from "@/Components/Tables/TableMailshots.vue"
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import SubDepartmentShowcase from "@/Components/Shop/SubDepartmentShowcase.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"
import FormCreateMasterFamily from "@/Components/Master/FormCreateMasterFamily.vue"
import TableSubDepartments from "@/Components/Tables/Grp/Org/Catalogue/TableSubDepartments.vue"
import ImagesManagement from "@/Components/Goods/ImagesManagement.vue"
import Breadcrumb from "primevue/breadcrumb"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import ProductCategoryTimeSeriesTable from "@/Components/Product/ProductCategoryTimeSeriesTable.vue"

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faDiagramNext,
    faFolderDownload
)


const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string
        navigation: object
    }

    routes: {
        fetch_families: routeType
        attach_families: routeType
        detach_families: routeType
    }

    showcase?: {}
    customers?: {}
    mailshots?: {}
    products?: {}
    history?: object
    sub_departments?: {}
    storeRoute: routeType
    shopsData: {}
    images?: object
    sales?: object
    salesData?: object
    mini_breadcrumbs: any
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component: Component = computed(() => {
    const components = {
        showcase: SubDepartmentShowcase,
        products: TableProducts,
        mailshots: TableMailshots,
        customers: TableCustomers,
        sub_departments: TableSubDepartments,
        details: ModelDetails,
        history: TableHistories,
        images: ImagesManagement,
        sales: ProductCategoryTimeSeriesTable
    }
    return components[currentTab.value]

})

const showDialog = ref(false)
</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-add-master-family>
            <Button :label="trans('Master family')" @click="showDialog = true" :style="'create'" />
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <div v-if="mini_breadcrumbs.length != 0" class="bg-white  px-4 py-2  w-full  border-gray-200 border-b overflow-x-auto">
        <Breadcrumb :model="mini_breadcrumbs">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <!-- Breadcrumb link or text -->
                    <component :is="item.to ? Link : 'span'" :href="route(item.to.name,item.to.parameters)"
                               v-tooltip="item.tooltip" :title="item.title"
                               class="flex items-center gap-2 text-sm transition-colors duration-150" :class="item.to
                            ? 'text-gray-500'
                            : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" />
                        <span class="">{{ item.label || "-" }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>
    <component :is="component" :data="props[currentTab]" :tab="currentTab" is-master :salesData="salesData"></component>
    <FormCreateMasterFamily :showDialog="showDialog" :storeProductRoute="storeRoute"
                            @update:show-dialog="(value) => showDialog = value" :shopsData="shopsData" />
</template>

<style scoped>
/* Remove default breadcrumb styles */
:deep(.p-breadcrumb) {
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
}
</style>
