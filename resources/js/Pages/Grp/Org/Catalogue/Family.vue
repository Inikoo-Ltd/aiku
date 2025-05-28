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
    faCameraRetro,
    faCube,
    faFolder, faMoneyBillWave, faProjectDiagram, faTag, faUser, faBrowser
} from "@fal";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons";
import { capitalize } from "@/Composables/capitalize";
import FamilyShowcase from "@/Components/Showcases/Grp/FamilyShowcase.vue";

library.add(
    faFolder,
    faCube,
    faCameraRetro,
    faTag,
    faBullhorn,
    faProjectDiagram,
    faUser,
    faMoneyBillWave,
    faDiagramNext, faBrowser
);

const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"));

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string
        navigation: object
    }
    customers: object
    mailshots: object
    showcase: object
    details: object
    history: object
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        showcase: FamilyShowcase,
        mailshots: TableMailshots,
        customers: TableCustomers,
        details: ModelDetails,
        history: ModelChangelog
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
