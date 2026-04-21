<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faInventory,
    faBox,
    faClock,
    faCameraRetro,
    faPaperclip,
    faCube,
    faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign
} from '@fal';
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import TableSupplierProducts from "@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import StockShowcase from "@/Components/Showcases/Grp/StockShowcase.vue";
import { capitalize } from "@/Composables/capitalize"
import TableOrgStocks from "@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue"
import { Tabs as TSTabs } from "@/types/Tabs"
import { PageHeadingTypes } from "@/types/PageHeading"
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue"
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue';
library.add(
    faInventory,
    faBox,
    faClock,
    faCameraRetro,
    faPaperclip,
    faCube,
    faHandReceiving,
    faClipboard,
    faPoop,
    faScanner,
    faDollarSign,

);

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
}>()

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components = {
        // showcase: StockShowcase,
        history: TableHistories,
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
