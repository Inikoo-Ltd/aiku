<script setup lang="ts">

const props = defineProps<{
    data: {
        
    }
    tab?: string
}>()
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { PaymentServiceProvider } from "@/types/payment-service-provider"


// function paymentServiceProviderRoute(paymentServiceAccount: PaymentServiceProvider) {
//     console.log(route().current())
//     switch (route().current()) {
//         case 'grp.org.accounting.org_payment_service_providers.index':
//             return route(
//                 'grp.org.accounting.org_payment_service_providers.show',
//                 [route().params['organisation'], paymentServiceAccount.slug])

//         default:
//             return null

//     }

// }
// function paymentAccountRoute(paymentServiceAccount: PaymentServiceProvider) {
//     switch (route().current()) {
//         case 'grp.org.accounting.org_payment_service_providers.index':
//             return route(
//                 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index',
//                 [
//                     route().params['organisation'],
//                     paymentServiceAccount.slug
//                 ]
//             )

//     }

// }

// function paymentsRoute(paymentServiceAccount: PaymentServiceProvider) {
//     switch (route().current()) {

//         case 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index':
//             return route(
//                 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show.payments.index',
//                 [
//                     route().params['organisation'],
//                     route().params['paymentServiceProvider'],
//                     route().params['paymentAccount']]
//             )
//         case 'grp.org.accounting.payment-accounts.index':
//             return route(
//                 'grp.org.accounting.payment-accounts.show.payments.index',
//                 [
//                     route().params['organisation'],
//                     route().params['paymentAccount']
//                 ]
//             )
//         case 'grp.org.accounting.org_payment_service_providers.index':
//             return route(
//                 'grp.org.accounting.org_payment_service_providers.show.payments.index',
//                 [
//                     route().params['organisation'],
//                     paymentServiceAccount.slug
//                 ]
//             )

//     }

// }
</script>


<template>
    <Table :resource="data" class="mt-5">
        <!-- <template #cell(codex)="{ item: paymentServiceProvider }">
            <Link :href="paymentServiceProviderRoute(paymentServiceProvider)" class="primaryLink">
                {{ paymentServiceProvider['slug'] }}
            </Link>
        </template>

        <template #cell(number_payment_accountsx)="{ item: paymentServiceProvider }">
            <Link :href="paymentAccountRoute(paymentServiceProvider)" class="secondaryLink">
                {{ paymentServiceProvider['number_payment_accounts'] }}
            </Link>
        </template>
        <template #cell(number_paymentsx)="{ item: paymentServiceProvider }">
            <Link :href="paymentsRoute(paymentServiceProvider)" class="secondaryLink">
                {{ paymentServiceProvider['number_payments'] }}
            </Link>
        </template> -->


    </Table>
</template>
