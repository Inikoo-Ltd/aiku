<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'
import Fieldset from 'primevue/fieldset'

import btree from '@/../art/payment_service_providers/btree.svg'
import cash from '@/../art/payment_service_providers/cash.svg'
import checkout from '@/../art/payment_service_providers/checkout.svg'
import hokodo from '@/../art/payment_service_providers/hokodo.svg'
import pastpay from '@/../art/payment_service_providers/pastpay.svg'
import paypal from '@/../art/payment_service_providers/paypal.svg'
import sofort from '@/../art/payment_service_providers/sofort.svg'
import worldpay from '@/../art/payment_service_providers/worldpay.svg'
import bank from '@/../art/payment_service_providers/bank.svg'
import accounts from '@/../art/payment_service_providers/accounts.svg'
import cond from '@/../art/payment_service_providers/cond.svg'

const props = defineProps<{
    data: {
        payment_service_provider: {
            code: string
            name: string
        }
    }
}>()

const selectImage = (code: string) => {
    if (!code) return null

    switch (code) {
        case 'btree':
            return btree
        case 'cash':
            return cash
        case 'checkout':
            return checkout
        case 'hokodo':
            return hokodo
        case 'accounts':
            return accounts
        case 'cond':
            return cond
        case 'bank':
            return bank
        case 'pastpay':
            return pastpay
        case 'paypal':
            return paypal
        case 'sofort':
            return sofort
        case 'worldpay':
            return worldpay
        default:
            return null
    }
}
</script>

<template>
    <div class="w-44">
        <Fieldset :legend="trans('Logo Provider')">
            <div class="p-2 flex justify-center ">
                <!-- {{ paymentServiceProvider.code }} -->
                <img
                    v-if="selectImage(data?.payment_service_provider?.code)"
                    :src="selectImage(data?.payment_service_provider?.code)"
                    :alt="data?.payment_service_provider.name"
                    :title="data?.payment_service_provider.name"
                    class="aspect-auto h-auto max-h-20 w-auto max-w-full"
                >
                <div v-else class="h-12 w-20 text-gray-400 flex items-center justify-center">
                    {{ trans('No image') }}
                </div>
            </div>
        </Fieldset>
    </div>
</template>