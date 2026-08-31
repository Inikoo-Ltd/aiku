<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 May 2025 12:26:31 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup>
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import FlatTreeMap from '@/Components/Navigation/FlatTreeMap.vue';
import PaymentMethodsWidget from '@/Components/Accounting/PaymentMethodsWidget.vue';
import DashboardSettings from '@/Components/DataDisplay/Dashboard/DashboardSettings.vue';
import { capitalize } from "@/Composables/capitalize"
import {library} from '@fortawesome/fontawesome-svg-core';
import {
  faMoneyCheckAlt, faCashRegister, faFileInvoiceDollar, faCoins,
} from '@fal';
defineProps(['title', 'pageHead', 'flatTreeMaps', 'payment_methods', 'intervals', 'settings']);



library.add(faCoins, faMoneyCheckAlt, faCashRegister, faFileInvoiceDollar);

</script>

<template>
  <Head :title="capitalize(title)"/>
  <PageHeading :data="pageHead"></PageHeading>
  <FlatTreeMap class="mx-4" v-for="(treeMap,idx) in flatTreeMaps" :key="idx" :nodes="treeMap"/>
    <template v-if="payment_methods">
        <div class="mx-4 mt-6">
            <DashboardSettings v-if="intervals" :intervals="intervals" :settings="settings" currentTab="payment_methods" :reloadOnly="['payment_methods', 'intervals']" />
        </div>
        <div class="mx-4 mt-3 max-w-xl">
            <PaymentMethodsWidget :summary="{ ...payment_methods.summary, period_label: payment_methods.period_label }" :tableRoute="payment_methods.route" />
        </div>
    </template>
</template>


