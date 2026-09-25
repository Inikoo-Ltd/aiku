<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->



<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {useLocaleStore} from '@/Stores/locale';


import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faInventory,
    faBox,
    faDollarSign,
    faPoop,
    faCubes,
    faCube,
    faCameraRetro,
    faChartLine
} from '@fal';
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { faX } from "@fortawesome/free-solid-svg-icons";
import { capitalize } from "@/Composables/capitalize"
import TableStocks from "@/Components/Tables/Grp/Goods/TableStocks.vue";
import { Link } from "@inertiajs/vue3"
import Button from '@/Components/Elements/Buttons/Button.vue';
import SalesAnalysis from "@/Components/SalesAnalysis/SalesAnalysis.vue"
import StockFamilyShowcase from "@/Components/Showcases/Grp/StockFamilyShowcase.vue"

library.add(
    faInventory,
    faBox,
    faDollarSign,
    faPoop,
    faCubes,
    faCube,
    faCameraRetro,
    faX,
    faChartLine
);

const locale = useLocaleStore();

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    }
    stocks?: object
    sales_analysis?: object
    sales_analysis_teaser?: object
    createStockRoute: {
        name: string;
        parameters?: {
            stockFamily?: string;
        };
    };
}>()

let currentTab = ref(props.tabs.current);
const deferredPropsOfTab: Record<string, string[]> = { showcase: ["sales_analysis_teaser"] }
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab, deferredPropsOfTab[tabSlug] ?? []);

const breakdownRoute = (row: { slug: string | null }) => {
    return row.slug ? route("grp.goods.stocks.show", [row.slug]) : null
}

const component = computed(() => {

    const components = {
        showcase: StockFamilyShowcase,
        stocks: TableStocks,
        sales_analysis: SalesAnalysis,
        details: ModelDetails,
        history: ModelChangelog,
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Link v-if="currentTab === 'stocks'"  :href="route(createStockRoute.name, createStockRoute.parameters)" >
                <Button  label="Create" icon="create"/>
            </Link>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :breakdownRoute="breakdownRoute" :salesAnalysisTeaser="sales_analysis_teaser"></component>
</template>
