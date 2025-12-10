<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->



<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';


import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faInventory,
    faBox,
    faDollarSign,
    faPoop,
    faCubes,
    faCube,
    faCameraRetro
} from '@fal';
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";

import Tabs from "@/Components/Navigation/Tabs.vue";
import { faX } from "@fortawesome/free-solid-svg-icons";
import { capitalize } from "@/Composables/capitalize"
import TableOrgStocks from "@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"

library.add(
    faInventory,
    faBox,
    faDollarSign,
    faPoop,
    faCubes,
    faCube,
    faCameraRetro,
    faX
);


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: object
    org_stocks?: object
    history?: object
}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        org_stocks: TableOrgStocks,
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
