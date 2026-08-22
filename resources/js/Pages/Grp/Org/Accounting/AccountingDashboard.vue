<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 01 Mar 2023 14:57:23 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup>
import {Head} from '@inertiajs/vue3';
import PageHeading from '@/Components/Headings/PageHeading.vue';
import FlatTreeMap from '@/Components/Navigation/FlatTreeMap.vue';
import PaymentMethodsWidget from '@/Components/Accounting/PaymentMethodsWidget.vue';
import { capitalize } from "@/Composables/capitalize"
import {library} from '@fortawesome/fontawesome-svg-core';
import {
  faMoneyCheckAlt, faCashRegister, faFileInvoiceDollar, faCoins,
} from '@fal';
defineProps(['title', 'pageHead', 'flatTreeMaps', 'payment_methods']);



library.add(faCoins, faMoneyCheckAlt, faCashRegister, faFileInvoiceDollar);

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    <FlatTreeMap class="mx-4" v-for="(treeMap,idx) in flatTreeMaps" :key="idx" :nodes="treeMap"/>
    <div v-if="payment_methods" class="mx-4 mt-6 max-w-xl">
        <PaymentMethodsWidget :summary="payment_methods.summary" :tableRoute="payment_methods.route" />
    </div>
</template>

