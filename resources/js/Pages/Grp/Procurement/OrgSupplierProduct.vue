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
  faCameraRetro, faPaperclip, faPoop, faMoneyBill, faClipboard, faBoxOpen
} from '@fal';
import SupplierProductShowcase from "@/Components/Showcases/Grp/SupplierProductShowcase.vue";
import TableSupplierProducts from "@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue";
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue";
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import TableSupplierAgreedTerms from '@/Components/Tables/Grp/Org/Procurement/TableSupplierAgreedTerms.vue'
import TableProcurementNotes from '@/Components/Tables/Grp/Org/Procurement/TableProcurementNotes.vue'
import AgreementSummary from '@/Components/Procurement/AgreementSummary.vue'
import MicroStats from '@/Components/DataDisplay/MicroStats.vue'
import PackingSpecTab from '@/Components/Procurement/PackingSpecTab.vue'
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
    agreed_terms?: object
    notes?: object
    packing?: object
    agreement: object | null
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
    faBoxOpen,
);

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);
const component = computed(() => {

    const components = {
        showcase: SupplierProductShowcase,
        supplier_products: TableSupplierProducts,
        purchase_orders: TablePurchaseOrders,
        details: ModelDetails,
        agreed_terms: TableSupplierAgreedTerms,
        notes: TableProcurementNotes,
        packing: PackingSpecTab,
        history: TableHistories
    };
    return components[currentTab.value];

});


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <div v-if="currentTab === 'showcase'" class="grid items-start gap-6 px-6 pt-6 md:grid-cols-3">
        <AgreementSummary v-if="agreement" :agreement="agreement" showLevel :costs="showcase?.costs" class="min-w-0 md:col-span-2" />
        <MicroStats v-if="showcase?.stats?.length" :stats="showcase.stats" />
    </div>
    <component :is="component" :data="props[currentTab]" :tab="currentTab" v-bind="['agreed_terms', 'notes'].includes(currentTab) ? { agreement } : {}"></component>
</template>

