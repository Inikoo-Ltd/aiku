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
    faClock,
    faCameraRetro,
    faPaperclip,
    faCube,
    faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign, faChartLine
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
import SalesAnalysis from "@/Components/SalesAnalysis/SalesAnalysis.vue"
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
    faChartLine,

);

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: object,
    sales_analysis?: object
    sales_analysis_teaser?: object
    org_stocks?: object
    trade_units?: object

}>()

let currentTab = ref(props.tabs.current);
const deferredPropsOfTab: Record<string, string[]> = { showcase: ["sales_analysis_teaser"] }
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab, deferredPropsOfTab[tabSlug] ?? []);

const component = computed(() => {

    const components = {
        showcase: StockShowcase,
        sales_analysis: SalesAnalysis,
        supplier_products: TableSupplierProducts,
        org_stocks: TableOrgStocks,
        trade_units: TableTradeUnits,
        history: ModelChangelog,
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :salesAnalysisTeaser="sales_analysis_teaser"></component>
</template>
