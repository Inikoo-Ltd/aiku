<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 Feb 2023 14:32:57 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, ref } from "vue";
import type { Component } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import { faHandsHelping, faBan, faCheckCircle, faList, faCheck } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import DispatchDashboard from "@/Components/Warehouse/DispatchDashboard.vue";
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue"
import { PageHeadingTypes } from "@/types/PageHeading";

library.add(faHandsHelping, faBan, faCheckCircle, faList, faCheck);

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    dashboard: {
        [key: string]: {
            label: string
            count: number
            cases: {
                key: string
                label: string
                value?: number
                icon: string | string[]
                class?: string
                route?: {
                    name: string
                    parameters?: object
                }
            }[]
        }
    }
    intervals: any
    settings: any
    blocks: any
}>();

// let currentTab = ref(props.tabs.current);
// const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

// const component: Component = computed(() => {
//     const components = {
//         ["dashboard" as string]: DispatchDashboard
//     };

//     return components[currentTab.value];
// });
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <!-- <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" /> -->
    <!-- <component :is="component" :tab="currentTab" :data="props[currentTab]"></component> -->
    <DashboardTable
        v-if="blocks"
    	class="border-t border-gray-200"
    	:idTable="blocks.id"
    	:tableData="blocks"
        :intervals="intervals"
        :settings="settings"
        :currentTab="blocks.current_tab"
    />
</template>
