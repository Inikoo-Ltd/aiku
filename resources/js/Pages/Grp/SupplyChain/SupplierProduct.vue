<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 15 Sept 2022 16:07:20 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from '@/Components/Headings/PageHeading.vue';
import Tabs from "@/Components/Navigation/Tabs.vue";
import {computed, defineAsyncComponent, ref} from "vue";
import ModelDetails from "@/Components/ModelDetails.vue";
import {useTabChange} from "@/Composables/tab-change";
import {library} from '@fortawesome/fontawesome-svg-core';
import {
  faInventory,
  faWarehouse,
  faPersonDolly,
  faBoxUsd,
  faTruck,
  faTerminal,
  faCameraRetro, faPaperclip, faPoop, faMoneyBill, faClipboard
} from '@fal';
import SupplierProductShowcase from "@/Components/Showcases/Grp/SupplierProductShowcase.vue";
import TableSupplierProducts from "@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue";
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue";
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
        showcase?: object
        supplier_products?: object,
        purchase_orders?: object,
        errors?: object,
        history: object

    },
    showcase?: object
    org_suppliers?: object
    org_supplier_products?: object,
    purchase_orders?: object,
    errors?: object,
    history?: object
}>()


library.add(
    faInventory,
    faWarehouse,
    faPersonDolly,
    faBoxUsd,
    faTruck,
    faTerminal,
    faCameraRetro,
    faPaperclip,
    faPoop,
    faMoneyBill,
    faClipboard,
);

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);
const component = computed(() => {

    const components = {
        showcase: SupplierProductShowcase,
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

