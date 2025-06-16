<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
    faBullhorn,
    faCameraRetro, faClock,
    faCube, faCubes,
    faFolder, faMoneyBillWave, faProjectDiagram, faTags, faUser, faFolders, faBrowser,faSeedling
} from "@fal";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, ref } from "vue";
import DepartmentShowcase from "@/Components/Showcases/Grp/DepartementShowcase.vue";
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
    products?: object
    families?: object;
    customers?: object;
    mailshots?: object;
    history?: object;
    showcase?: object
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
        history: TableHistories
    };
    return components[currentTab.value];

});


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
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

