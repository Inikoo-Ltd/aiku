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
    faFolder, faMoneyBillWave, faProjectDiagram, faTags, faUser, faFolders, faBrowser
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
    faFolders, faBrowser
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
    history: object;
    department: object
    showcase: object
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

console.log(props);

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

