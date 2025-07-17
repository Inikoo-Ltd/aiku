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
import RetinaDepartmentShowcase from "@/Components/Showcases/Retina/Catalouge/RetinaDepartementShowcase.vue";
import { useTabChange } from "@/Composables/tab-change";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons";
import RetinaTableProducts from "@/Components/Tables/Retina/RetinaTableProducts.vue";
import RetinaTableFamilies from "@/Components/Tables/Retina/RetinaTableFamilies.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import RetinaTableCollections from "@/Components/Tables/Retina/RetinaTableCollections.vue";
import TableSubDepartments from "@/Components/Tables/Retina/RetinaTableSubDepartements.vue";

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
    collections?: object
    sub_departments?: object;
}>();

console.log("props", props);
let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components = {
        showcase: RetinaDepartmentShowcase,
        products: RetinaTableProducts,
        families: RetinaTableFamilies,
        collections : RetinaTableCollections,
        sub_departments: TableSubDepartments,
    };
    return components[currentTab.value];
});


</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"> </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

