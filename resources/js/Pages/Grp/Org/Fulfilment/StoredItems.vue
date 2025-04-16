<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"

import { faNarwhal, faBallotCheck } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { computed, ref } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import Tabs from '@/Components/Navigation/Tabs.vue'
import TableStoredItemsAudits from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsAudits.vue'
import TableStoredItemsInWarehouse from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsInWarehouse.vue";
import TablePalletStoredItems from '@/Components/Tables/Grp/Org/Fulfilment/TablePalletStoredItems.vue'
library.add(faNarwhal, faBallotCheck)

const props = defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string;
        navigation: object;
    }
    stored_items : {}
    pallet_stored_items : {}
    stored_item_audits : {}
}>()
let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);



const component = computed(() => {

    const components = {
        stored_items:TableStoredItemsInWarehouse,
        pallet_stored_items: TablePalletStoredItems,
        stored_item_audits: TableStoredItemsAudits
    };
    return components[currentTab.value];

});

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab as keyof typeof props]"></component>
</template>
