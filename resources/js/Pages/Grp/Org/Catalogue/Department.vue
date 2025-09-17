<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
    faBullhorn,
    faCameraRetro, faClock,
    faCube, faCubes,
    faFolder, faMoneyBillWave, faProjectDiagram, faTags, faUser, faFolders, faBrowser,faSeedling
} from "@fal";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, ref } from "vue";
import DepartmentShowcase from "@/Components/Showcases/Grp/DepartmentShowcase.vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons";
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue";
import TableFamilies from "@/Components/Tables/Grp/Org/Catalogue/TableFamilies.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { trans } from "laravel-vue-i18n"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons";
import ImagesManagement from "@/Components/Goods/ImagesManagement.vue";
import Breadcrumb from 'primevue/breadcrumb'

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faClock,
    faProjectDiagram,
    faBullhorn,
    faTags,
    faUser,
    faMoneyBillWave,
    faDiagramNext,
    faCubes,
    faFolders, faBrowser, faSeedling
);


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes,
    tabs: {
        current: string;
        navigation: object;
    }
    mini_breadcrumbs?: any[]
    products?: object
    families?: object;
    customers?: object;
    mailshots?: object;
    history?: object;
    showcase?: object
    url_master?:routeType
    images?:object
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components = {
        showcase: DepartmentShowcase,
        products: TableProducts,
        families: TableFamilies,
        mailshots: TableMailshots,
        customers: TableCustomers,
        details: ModelDetails,
        history: TableHistories,
        images :ImagesManagement
    };
    return components[currentTab.value];

});


function masterDepartmentRoute(department: Department) {
    if(!department.master_product_category_id){
        return '';
    }

    return route(
        "grp.helpers.redirect_master_product_category",
        [department.master_product_category_id]);
}


</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-delete="propx">
            <ModalConfirmationDelete
                :routeDelete="{
                    name: propx.action.route.name,
                    parameters: propx.action.route.parameters,
                }"
                :title="trans('Are you sure you want to delete department') + '?'"
                isFullLoading
            >
                <template #default="{ isOpenModal, changeModel }">
                    <div @click="changeModel" class="cursor-pointer bg-white/60 hover:bg-black/10 px-1 text-red-500 rounded-sm">
                        <Button type="delete"/>
                    </div>
                </template>
            </ModalConfirmationDelete>
        </template>

         <template #afterTitle>
           <div class="whitespace-nowrap">
            <Link v-if="url_master"  :href="route(url_master.name,url_master.parameters)"  v-tooltip="'Go to Master'" class="mr-1"  :class="'opacity-70 hover:opacity-100'">
                <FontAwesomeIcon
                    :icon="faOctopusDeploy"
                    color="#4B0082"
                />
            </Link>
            </div>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <div v-if="mini_breadcrumbs" class="bg-white shadow-sm rounded px-4 py-2 mx-4 mt-2 w-fit border border-gray-200 overflow-x-auto">
        <Breadcrumb  :model="mini_breadcrumbs">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <!-- Breadcrumb link or text -->
                    <component :is="item.to ? Link : 'span'" :href="route(item.to.name,item.to.parameters)" v-tooltip="item.tooltip"
                        :title="item.title" class="flex items-center gap-2 text-sm transition-colors duration-150"
                        :class="item.to
                            ? 'text-gray-500'
                            : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" />
                        <span class="truncate max-w-[150px]">{{ item.label || '-' }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
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

