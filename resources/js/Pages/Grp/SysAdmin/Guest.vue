<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Tue, 20 Sept 2022 19:04:29 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { Head } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { capitalize } from "@/Composables/capitalize"
import TableGuests from "@/Components/Tables/Grp/SysAdmin/TableGuests.vue";
import SysadminUserShowcase from '@/Components/Showcases/Grp/SysadminUserShowcase.vue'


const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    },
    guests?: object
    history?: object
    showcase?: {}

}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        showcase: SysadminUserShowcase,
        details: ModelDetails,
        history: TableHistories
    };
    return components[currentTab.value];

});

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
