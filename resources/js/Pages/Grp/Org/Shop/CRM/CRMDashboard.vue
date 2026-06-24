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
import { faShoppingCart, faStoreAlt, faGlobeEurope, faComments } from '@fal';
import { PageHeadingTypes } from "@/types/PageHeading";
import { computed, ref } from "vue";
import CustomersDashboard from "@/Pages/Grp/Org/Shop/CRM/CustomerDashboard.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import ProspectsDashboard from "@/Pages/Grp/Org/Shop/CRM/ProspectsDashboard.vue";
import TableCustomerCountries from '@/Components/Tables/Grp/Org/CRM/TableCustomerCountries.vue';
import TableChatSessions from '@/Components/Tables/Grp/Org/CRM/TableChatSessions.vue';
import TableTopCustomers from '@/Components/Tables/Grp/Org/CRM/TableTopCustomers.vue';

library.add(
    faShoppingCart,
    faStoreAlt,
    faGlobeEurope,
    faComments
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
    countries?: {};
    chats?: {};
    top_customers?: {};
}>();

const currentTab = ref<string>(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: any = {
        customers: CustomersDashboard,
        prospects: ProspectsDashboard,
        countries: TableCustomerCountries,
        chats: TableChatSessions,
        top_customers: TableTopCustomers,
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
