<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 24 Jan 2024 14:52:40 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import TableFulfilments from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilments.vue";
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, ref } from "vue";
import { library } from "@fortawesome/fontawesome-svg-core";


import { useTabChange } from "@/Composables/tab-change";
import {
    faCube,faFolder,faFolderTree, faBars
} from '@fal';

library.add(
    faCube,faFolder,faFolderTree, faBars
);

const props = defineProps <{
    pageHead: object
    tabs: {
        current: string;
        navigation: object;
    },
    title: string
    fulfilments?: object


}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        fulfilments: TableFulfilments,
    };
    return components[currentTab.value];

});

</script>

<template>
    <!--suppress HtmlRequiredTitleElement -->
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']"  @update:tab="handleTabUpdate"/>
    <component :is="component" :tab="currentTab"  :data="props[currentTab]"></component>
</template>

