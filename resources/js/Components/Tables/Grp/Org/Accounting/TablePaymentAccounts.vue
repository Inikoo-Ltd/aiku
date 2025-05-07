<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { PaymentAccount } from "@/types/payment-account"
import { faBox, faHandHoldingBox, faPallet, faPencil } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useLocaleStore } from '@/Stores/locale'
library.add(faBox, faHandHoldingBox, faPallet, faPencil)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore();

function paymentAccountRoute(paymentAccount: PaymentAccount) {
    switch (route().current()) {

        case 'grp.org.accounting.org_payment_service_providers.show':
        case 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index':
            return route(
                'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show',
                [route().params['organisation'], route().params['orgPaymentServiceProvider'], paymentAccount.slug])
        case 'grp.org.accounting.payment-accounts.index':
            return route(
                'grp.org.accounting.payment-accounts.show',
                [route().params['organisation'], paymentAccount.slug])

    }

}

function providersRoute(paymentAccount: PaymentAccount) {
    // console.log(route().current())
    switch (route().current()) {


        case 'grp.org.accounting.payment-accounts.index':
            return route(
                'grp.org.accounting.org_payment_service_providers.show',
                [route().params['organisation'], paymentAccount.slug])

    }
}

function paymentsRoute(paymentAccount: PaymentAccount) {
    switch (route().current()) {

        case 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index':
            return route(
                'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show.payments.index',
                [route().params['organisation'], route().params['orgPaymentServiceProvider'], paymentAccount.payment_service_provider_slug])
        case 'grp.org.accounting.payment-accounts.index':
            return route(
                'grp.org.accounting.payment-accounts.show.payments.index',
                [route().params['organisation'], paymentAccount.slug])

    }
}

function shopsRoute(paymentAccount: PaymentAccount) {
    switch (route().current()) {
        case 'grp.org.accounting.payment-accounts.index':
            return route(
                'grp.org.accounting.payment-accounts.show.shops.index',
                [route().params['organisation'], paymentAccount.slug])

    }
}



</script>


<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Code-->
        <template #cell(code)="{ item: paymentAccount }">
            <Link :href="paymentAccountRoute(paymentAccount)" class="primaryLink">
                {{ paymentAccount['code'] }}
            </Link>
        </template>

        <!-- Column: Provider -->
        <template #cell(payment_service_provider_code)="{ item: paymentAccount }">
            <Link :href="providersRoute(paymentAccount)" class="secondaryLink">
                {{ paymentAccount['payment_service_provider_code'] }}
            </Link>
        </template>

        <!-- Column: Payment -->
        <template #cell(number_payments)="{ item: paymentAccount }">
            <Link :href="paymentsRoute(paymentAccount)" class="secondaryLink">
                {{ useLocaleStore().number(paymentAccount['number_payments']) }}
            </Link>
        </template>

        <!-- Column: Payment -->
        <template #cell(number_pas_state_active)="{ item: paymentAccount }">
            <Link :href="shopsRoute(paymentAccount)" class="secondaryLink">
                {{ useLocaleStore().number(paymentAccount['number_pas_state_active']) }}
            </Link>
        </template>

        <template #cell(org_amount_successfully_paid)="{ item: paymentAccount }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat( paymentAccount.org_currency_code, paymentAccount.org_amount_successfully_paid)  }}</div>
        </template>
    </Table>
</template>
