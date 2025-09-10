<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 15 Apr 2025 13:06:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from "@inertiajs/vue3";
import {library} from "@fortawesome/fontawesome-svg-core";
import {
    faCube,
    faFileInvoice,
    faFolder,
    faFolderOpen,
    faAtom,
    faFolderTree,
    faChartLine,
    faShoppingCart, faStickyNote, faMoneyBillWave
} from "@fal";
import {faCheckCircle} from "@fas";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import {capitalize} from "@/Composables/capitalize";
import Tabs from "@/Components/Navigation/Tabs.vue";
import {computed, ref} from "vue";
import {useTabChange} from "@/Composables/tab-change";
import TableTradeUnits from '@/Components/Tables/Grp/Goods/TableTradeUnits.vue'

import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import MasterProductShowcase from "@/Components/Showcases/Grp/MasterProductShowcase.vue";
import {PageHeading as PageHeadingTypes} from "@/types/PageHeading";
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue";
import TradeUnitImagesManagement from "@/Components/Goods/ImagesManagement.vue"

library.add(faChartLine, faCheckCircle, faFolderTree, faFolder, faCube, faShoppingCart, faFileInvoice, faStickyNote,
    faMoneyBillWave, faFolderOpen, faAtom
);

const props = defineProps<{
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    },
    title: string
    showcase?: {}
    history?: {}
    language?:{}
    products?: {}
    trade_units?: {}
    images?: {}

}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        showcase: MasterProductShowcase,
        history: TableHistories,
        products: TableProducts,
        images: TradeUnitImagesManagement,
        trade_units: TableTradeUnits,
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"/>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>
