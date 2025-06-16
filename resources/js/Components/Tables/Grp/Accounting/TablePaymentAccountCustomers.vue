<script setup lang="ts">

const props = defineProps<{
    data: {
        
    }
    tab?: string
}>()
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { PaymentServiceProvider } from "@/types/payment-service-provider"
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

const locale = inject('locale', aikuLocaleStructure)

function paymentServiceProviderRoute(paymentServiceAccount: PaymentServiceProvider) {
    console.log(route().current())
    switch (route().current()) {
        case 'grp.org.accounting.org_payment_service_providers.index':
            return route(
                'grp.org.accounting.org_payment_service_providers.show',
                [route().params['organisation'], paymentServiceAccount.slug])

        default:
            return null

    }
}

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
    <!-- <pre>{{ data }}</pre> -->
    <Table :resource="data" class="mt-5">
        <!-- <template #cell(reference)="{ item }">
            <Link :href="paymentServiceProviderRoute(item)" class="primaryLink">
                {{ item['slug'] }}
            </Link>
        </template> -->
        <!--

        
        -->
        <template #cell(shop_code)="{ item }">
            <div v-tooltip="item.shop_name" class="w-fit cursor-default">
                {{ item.shop_code }}
            </div>
        </template>

        <template #cell(total_amount_paid)="{ item }">
            {{ locale.currencyFormat(item.currency_code, item['total_amount_paid']) }}
        </template>


    </Table>
</template>
