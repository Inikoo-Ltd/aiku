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
        dimension: {
            key: string
            label: string
            items: { key: string; label: string }[]
        }
        metrics: {
            key: string
            label: string
            type: string
            icon?: string[]
            items?: { key: string; label: string; icon?: string[] }[]
        }[]
        data: {
            [rowKey: string]: {
                [metricKey: string]: {
                    value: number | null
                    route_target?: {
                        name: string
                        parameters?: object
                    }
                }
            }
        }
        row_totals: {
            [rowKey: string]: { value: number }
        }
        totals: {
            [metricKey: string]: { value: number }
        }
        grand_total: {
            value: number
            icon?: string[]
        }
    }
    intervals: any
    settings: any
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: Component = {
        dashboard: DispatchDashboard
    };

    return components[currentTab.value];
});
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="dashboard"></component>
</template>