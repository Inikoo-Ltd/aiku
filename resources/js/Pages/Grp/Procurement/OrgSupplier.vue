<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Fri, 24 Feb 2023 10:21:46 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import {Head, useForm} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faInventory,
    faWarehouse,
    faMapSigns,
    faMoneyBill,
    faHandReceiving,
    faPoop, faClipboard, faTruck, faCameraRetro, faPaperclip, faPaperPlane, faClock,
    faPersonDolly
} from '@fal';
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import TableSupplierProducts from "@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue";
import ModelDetails from "@/Components/ModelDetails.vue";
import TableStockDeliveries from "@/Components/Tables/Grp/Org/Procurement/TableStockDeliveries.vue";
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue";
import SupplierShowcase from "@/Components/Showcases/Grp/SupplierShowcase.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { capitalize } from "@/Composables/capitalize"
import TableOrgSupplierProducts from '@/Components/Tables/Grp/Org/Procurement/TableOrgSupplierProducts.vue';
library.add( faInventory, faWarehouse, faMapSigns, faMoneyBill, faHandReceiving, faPoop, faClipboard, faTruck, faCameraRetro, faPaperclip, faPaperPlane, faClock, faPersonDolly );


const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    }
    showcase?: object
    sales?: object
    supplier_products?: object,
    purchase_orders?: object,
    deliveries?:object,
    errors?: object,
    history?: object,
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components = {
        showcase: SupplierShowcase,
        sales: TableSupplierProducts,
        supplier_products: TableOrgSupplierProducts,
        purchase_orders: TablePurchaseOrders,
        deliveries: TableStockDeliveries,
        details: ModelDetails,
        history: TableHistories
    };
    return components[currentTab.value];

});

const getErrors = () => {
    if (props.errors.purchase_order) {
        if (confirm(props.errors.purchase_order)) {
            let fields = {
                force: true
            };

            const form = useForm(fields);

            form.post(route(
                props.pageHead.create_direct.route.name,
                props.pageHead.create_direct.route.parameters
            ));
        }
    }
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <div v-if="props.errors.purchase_order">{{ getErrors() }}</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

