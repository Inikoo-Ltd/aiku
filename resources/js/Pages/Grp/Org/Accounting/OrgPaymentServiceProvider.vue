<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
    faCashRegister,
    faCoins,
    faMoneyCheckAlt,
    faChartLine,
    faFileInvoiceDollar,
} from '@fal';

import PageHeading from "@/Components/Headings/PageHeading.vue";
import ModelStats from "@/Components/Navigation/FlatTreeMap.vue";
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue";
import TablePaymentAccounts from "@/Components/Tables/Grp/Org/Accounting/TablePaymentAccounts.vue";
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import PaymentProviderShowcase from "@/Components/Accounting/PaymentProviderShowcase.vue"
import TableInvoices from "@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue"

const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"));

library.add(faCoins, faMoneyCheckAlt, faCashRegister, faChartLine, faFileInvoiceDollar);

const props = defineProps<{
    title: string,
    pageHead: object,
    tabs: {
        current: string;
        navigation: object;
    },
    payment_accounts?: object
    payments?: object,
    invoices?: object,
    history?: object
    showcase?: {}

}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

    const components = {
        showcase: PaymentProviderShowcase,
        stats: ModelStats,
        payment_accounts: TablePaymentAccounts,
        payments: TablePayments,
        details: ModelDetails,
        history: TableHistories,
        invoices: TableInvoices
    };
    return components[currentTab.value];

});

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <div class="px-4 py-2">
        <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
    </div>

</template>

