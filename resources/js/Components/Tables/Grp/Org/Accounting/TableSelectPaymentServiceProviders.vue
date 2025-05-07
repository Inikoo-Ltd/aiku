<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 10 Apr 2024 16:09:45 Central Indonesia Time, Sanur , Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { SelectPaymentServiceProvider } from "@/types/select-payment-service-provider"
import { faPlus, faCheckDouble } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Modal from "@/Components/Utils/Modal.vue"
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import AccountProvidersForm from '@/Components/PaymentProviders/accountProvidersForm.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

import btree from '@/../art/payment_service_providers/btree.svg'
import cash from '@/../art/payment_service_providers/cash.svg'
import checkout from '@/../art/payment_service_providers/checkout.svg'
import hokodo from '@/../art/payment_service_providers/hokodo.svg'
import pastpay from '@/../art/payment_service_providers/pastpay.svg'
import paypal from '@/../art/payment_service_providers/paypal.svg'
import sofort from '@/../art/payment_service_providers/sofort.svg'
import worldpay from '@/../art/payment_service_providers/worldpay.svg'
import xendit from '@/../art/payment_service_providers/xendit.svg'
import bank from '@/../art/payment_service_providers/bank.svg'
import accounts from '@/../art/payment_service_providers/accounts.svg'
import cond from '@/../art/payment_service_providers/cond.svg'


library.add(faPlus, faCheckDouble)

const props = defineProps<{
    data: {},
    tab?: string,
    paymentAccountTypes: {}
    organisation_id: string
}>()

const openModal = ref(false)
const selectedProvider = ref(null)
const form = useForm({
    code: null,
    name: null
})

function paymentServiceProviderRoute(paymentServiceAccount: SelectPaymentServiceProvider) {
    switch (route().current()) {
        case 'grp.org.accounting.org_payment_service_providers.index':
            return route(
                'grp.org.accounting.org_payment_service_providers.show',
                [route().params['organisation'], paymentServiceAccount.org_slug])

        default:
            return null

    }

}
function paymentAccountRoute(paymentServiceAccount: SelectPaymentServiceProvider) {
    switch (route().current()) {
        case 'grp.org.accounting.org_payment_service_providers.index':
            return route(
                'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index',
                [
                    route().params['organisation'],
                    paymentServiceAccount.org_slug
                ]
            )

    }

}

function paymentsRoute(paymentServiceAccount: SelectPaymentServiceProvider) {
    switch (route().current()) {

        case 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index':
            return route(
                'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show.payments.index',
                [
                    route().params['organisation'],
                    route().params['paymentServiceProvider'],
                    route().params['paymentAccount']]
            )
        case 'grp.org.accounting.payment-accounts.index':
            return route(
                'grp.org.accounting.payment-accounts.show.payments.index',
                [
                    route().params['organisation'],
                    route().params['paymentAccount']
                ]
            )
        case 'grp.org.accounting.org_payment_service_providers.index':
            return route(
                'grp.org.accounting.org_payment_service_providers.show.payments.index',
                [
                    route().params['organisation'],
                    paymentServiceAccount.org_slug
                ]
            )

    }

}


const onOpenModal = (data) => {
    selectedProvider.value = data
    openModal.value = true
}

const onCloseModal = async (data) => {
    openModal.value = false
    setTimeout(() => {
        selectedProvider.value = null
    }, 300)
}


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
        case 'xendit':
            return xendit
        default:
            return null
    }
}

</script>


<template>
    <Table :resource="data" class="mt-5">
        <!-- Column: State -->
        <template #cell(adoption)="{ item: item }">
            <!--   <pre>{{ item }}</pre> -->
            <div class="flex justify-center">
                <template v-if="item.state == 'active'">
                    <div v-if="item.number_payment_accounts && item.number_payment_accounts > 0" v-tooltip="trans('Account')">
                        <FontAwesomeIcon icon='fal fa-check-double' class='' fixed-width aria-hidden='true' />
                    </div>
                    <div v-else v-tooltip="'Create Account'">
                        <Button @click="() => onOpenModal(item)" icon="fas fa-plus" size="xs" type="tertiary" />
                        <!-- <FontAwesomeIcon icon='fas fa-plus' class="px-1 cursor-pointer text-gray-400 hover:text-gray-600" fixed-width aria-hidden='true' /> -->
                    </div>
                </template>

                <template v-else-if="item.state === 'legacy'"></template>
            </div>
        </template>

        <!-- Column: Logo -->
        <template #cell(logo)="{ item: paymentServiceProvider }">
            <div class="w-20">
                <!-- {{ paymentServiceProvider.code }} -->
                <img v-if="selectImage(paymentServiceProvider.code)" :src="selectImage(paymentServiceProvider.code)" :alt="paymentServiceProvider.name" :title="paymentServiceProvider.name" class="mx-auto aspect-auto h-auto max-h-7 w-auto max-w-20">
                <div v-else class="h-12 w-20 text-gray-400 flex items-center justify-center">
                    {{ trans('No image') }}
                </div>
            </div>
        </template>

        <!-- Column: Code -->
        <template #cell(code)="{ item: paymentServiceProvider }">
            <Link v-if="paymentServiceProvider['org_slug']" :href="paymentServiceProviderRoute(paymentServiceProvider)"
                class="primaryLink">
                {{ paymentServiceProvider['org_code'] }}
            </Link>
            
            <span v-else class="px-1">
                {{ paymentServiceProvider['code'] }}
            </span>
        </template>

        <!-- Column: Account -->
        <template #cell(number_payment_accounts)="{ item: paymentServiceProvider }">
            <Link v-if="paymentServiceProvider['org_slug']" :href="paymentAccountRoute(paymentServiceProvider)"
                class="secondaryLink">
                {{ paymentServiceProvider['number_payment_accounts'] }}
            </Link>
        </template>
        
        <!-- Column: Payment -->
        <template #cell(number_payments)="{ item: paymentServiceProvider }">
            <Link v-if="paymentServiceProvider['org_slug']" :href="paymentsRoute(paymentServiceProvider)"
                class="secondaryLink">
                {{ paymentServiceProvider['number_payments'] }}
            </Link>
        </template>
    </Table>

    <!-- Section: Modal -->
    <Modal :isOpen="openModal" @onClose="onCloseModal" width="w-2/5" class="overflow-visible">
        <div>
            <AccountProvidersForm :organisation_id="organisation_id" :provider="selectedProvider"
                :onCloseModal="onCloseModal" :paymentAccountTypes="paymentAccountTypes" />
        </div>
    </Modal>
</template>
