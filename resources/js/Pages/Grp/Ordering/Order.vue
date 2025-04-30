
<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 21 Jun 2023 08:06:24 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Head} from '@inertiajs/vue3';
import {library} from '@fortawesome/fontawesome-svg-core';
import {
    faBars,
    faDollarSign, faEnvelope, faFileInvoiceDollar,
    faPaperclip, faRoad, faStickyNote, faTag, faThList, faTruck, faUserTag
} from '@fal';
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue";
import TableInvoices from "@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue";
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue";
import ModelDetails from "@/Components/ModelDetails.vue";
import {useTabChange} from "@/Composables/tab-change";
import {computed, defineAsyncComponent, ref} from "vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import PageHeading from '@/Components/Headings/PageHeading.vue';
import { capitalize } from "@/Composables/capitalize";


library.add(
    faBars,
    faThList,
    faUserTag,
    faDollarSign,
    faEnvelope,
    faTag,
    faFileInvoiceDollar,
    faTruck,
    faPaperclip,
    faRoad,
    faStickyNote,
);


const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    }
    invoices?: object
    delivery_notes?: object
    payments?: object
}>()

let currentTab = ref(props.tabs?.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        payments: TablePayments,
        invoices: TableInvoices,
        delivery_notes: TableDeliveryNotes,
        details: ModelDetails,
        history: ModelChangelog,
    };
    return components[currentTab.value];

});

</script>


<template>

    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs?.['navigation']" @update:tab="handleTabUpdate"/>
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>

</template>

