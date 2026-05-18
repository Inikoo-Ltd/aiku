<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 04 May 2024 13:04:40 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faInventory,
    faWarehouse,
    faBoxUsd,
    faTerminal,
    faPeopleArrows,
    faClipboard, faTruck, faCameraRetro,
    faPersonDolly,faAddressBook
} from '@fal';
import Tabs from "@/Components/Navigation/Tabs.vue";
import {computed, ref} from "vue";
import ModelDetails from "@/Components/ModelDetails.vue";
import {useTabChange} from "@/Composables/tab-change";
import TableSuppliers from "@/Components/Tables/Grp/SupplyChain/TableSuppliers.vue";
import TableSupplierProducts from "@/Components/Tables/Grp/SupplyChain/TableSupplierProducts.vue";
import AgentShowcase from "@/Components/Showcases/Grp/AgentShowcase.vue";
import { capitalize } from "@/Composables/capitalize"
import TablePurchaseOrders from "@/Components/Tables/Grp/Org/Procurement/TablePurchaseOrders.vue";
import {useForm} from "@inertiajs/vue3";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    },
    showcase?: object
    suppliers?: object
    supplier_products?: object,
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
    faClipboard,
    faPeopleArrows,
    faAddressBook
);

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        showcase: AgentShowcase,
        suppliers: TableSuppliers,
        supplier_products: TableSupplierProducts,
        purchase_orders: TablePurchaseOrders,
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
    <!--suppress HtmlRequiredTitleElement -->
    <Head :title="capitalize(title)" />
    <!-- {{ typeof props.errors.purchase_orders }} -->
    <PageHeading :data="pageHead"></PageHeading>
    <!--suppress TypeScriptUnresolvedReference -->
    <div v-if="props.errors.purchase_order">{{ getErrors() }}</div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>

