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
    delivery_note: object
    picking_session: object
    intervals: any
    settings: any
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: Component = {
        delivery_note: DispatchDashboard,
        picking_session: DispatchDashboard,
    };

    return components[currentTab.value];
});

const tabData = computed(() => {
    if (currentTab.value === 'picking_session') return props.picking_session;
    return props.delivery_note;
});
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="tabData"></component>
</template>
