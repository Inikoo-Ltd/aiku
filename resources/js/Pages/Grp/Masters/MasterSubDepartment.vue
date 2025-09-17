<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 18 Jun 2025 02:34:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
    faBullhorn,
    faCameraRetro,
    faCube,
    faFolder, faMoneyBillWave, faProjectDiagram, faTag, faUser
} from "@fal";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, defineAsyncComponent, ref, inject } from "vue";
import type { Component } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons";
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue";
import { capitalize } from "@/Composables/capitalize";
import { trans } from "laravel-vue-i18n";
import SubDepartmentShowcase from "@/Components/Shop/SubDepartmentShowcase.vue";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { routeType } from "@/types/route";
import FormCreateMasterFamily from "@/Components/Master/FormCreateMasterFamily.vue"
import { sub } from "date-fns";
import TableSubDepartments from "@/Components/Tables/Grp/Org/Catalogue/TableSubDepartments.vue";
import ImagesManagement from "@/Components/Goods/ImagesManagement.vue";

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faDiagramNext
);

const layout = inject("layout", layoutStructure);
const locale = inject("locale", aikuLocaleStructure);
const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"));

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

    showcase: {}
    customers: {}
    mailshots: {}
    products: {}
    sub_departments?: {}
    storeRoute: routeType
    shopsData: {}
    images?: object
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component: Component = computed(() => {
    const components = {
        showcase: SubDepartmentShowcase,
        products: TableProducts,
        mailshots: TableMailshots,
        customers: TableCustomers,
        sub_departments: TableSubDepartments,
        details: ModelDetails,
        history: ModelChangelog,
        images: ImagesManagement
    };
    return components[currentTab.value];

});

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
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
    <div v-if="mini_breadcrumbs"
        class="bg-white shadow-sm rounded px-4 py-2 mx-4 mt-2 w-fit border border-gray-200 overflow-x-auto">
        <Breadcrumb :model="mini_breadcrumbs">
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

    <FormCreateMasterFamily :showDialog="showDialog" :storeProductRoute="storeRoute"
        @update:show-dialog="(value) => showDialog = value" :shopsData="shopsData" />
</template>
