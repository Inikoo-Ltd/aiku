<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Fri, 31 Mar 2023 09:40:16 Central European Summer Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {library} from '@fortawesome/fontawesome-svg-core';
import { capitalize } from "@/Composables/capitalize"
import { faShoppingCart,faStoreAlt } from '@fal';
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { computed, ref } from "vue";
import CustomersDashboard from "@/Pages/Grp/Org/Shop/CRM/CustomerDashboard.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import ProspectsDashboard from "@/Pages/Grp/Org/Shop/CRM/ProspectsDashboard.vue";

library.add(
    faShoppingCart,faStoreAlt
);

const props = defineProps<{
    title: string;
    pageHead: PageHeadingTypes;
    tabs: {
        current: string;
        navigation: {};
    };
    customers?: {};
    prospects?: {};
}>();

const currentTab = ref<string>(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: any = {
        customers: CustomersDashboard,
        prospects: ProspectsDashboard
    }

    return components[currentTab.value];
});
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab]"></component>
</template>
