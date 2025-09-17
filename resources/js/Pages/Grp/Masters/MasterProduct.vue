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
import Breadcrumb from 'primevue/breadcrumb'

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
    mini_breadcrumbs?: any[]

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

console.log(props)
</script>


<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"/>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <div v-if="mini_breadcrumbs" class="bg-white shadow-sm rounded px-4 py-2 mx-4 mt-2 w-fit border border-gray-200 overflow-x-auto">
        <Breadcrumb  :model="mini_breadcrumbs">
            <template #item="{ item, index }">
                <div class="flex items-center gap-1 whitespace-nowrap">
                    <!-- Breadcrumb link or text -->
                    <component :is="item.to ? Link : 'span'" :href="item.to" v-tooltip="item.tooltip"
                        :title="item.title" class="flex items-center gap-2 text-sm transition-colors duration-150"
                        :class="item.to
                            ? 'text-gray-500'
                            : 'text-gray-500 cursor-default'">
                        <FontAwesomeIcon :icon="item.icon" class="w-4 h-4" />
                        <span class="truncate max-w-[150px]">{{ item.label || '-' }}</span>
                    </component>
                </div>
            </template>
        </Breadcrumb>
    </div>
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>
