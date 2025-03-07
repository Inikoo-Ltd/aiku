<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faChartLine,
    faCoins,
    faInfoCircle,
    faMoneyCheckAlt
} from '@fal';

import PageHeading from '@/Components/Headings/PageHeading.vue';
import {computed, defineAsyncComponent, ref} from "vue";
import {useTabChange} from "@/Composables/tab-change";
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";

library.add(faCoins, faChartLine, faInfoCircle,faMoneyCheckAlt);

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    }
    payments?: object;
    history: object
}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        payments: TablePayments,
        details: ModelDetails,
        history: TableHistories
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

