<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 16 Dec 2025 19:39:25 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCashRegister, faStore, faMoneyCheckAlt } from "@fal"
import { faCheckCircle, faTimesCircle } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Icon from "@/Components/Icon.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { PaymentAccountShop } from "@/types/payment-account-shop"
import paymentAccountShop from "@/Pages/Grp/Org/Accounting/PaymentAccountShop.vue"

library.add(faCashRegister, faStore, faMoneyCheckAlt, faCheckCircle, faTimesCircle)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    payment_account_shop: PaymentAccountShop
}>()

const locale = useLocaleStore()

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-5 sm:px-6">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-gray-200 pb-3">
                <dt class="text-sm font-medium text-gray-500">{{ trans("Payment account") }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ payment_account_shop.payment_account_name ? payment_account_shop.payment_account_name : '-'  }}
                    <span v-if="payment_account_shop.payment_account_code" class="text-gray-400">({{ payment_account_shop.payment_account_code }})</span>
                </dd>
            </div>

            <div class="border-b border-gray-200 pb-3">
                <dt class="text-sm font-medium text-gray-500">{{ trans("Amount successfully paid") }}</dt>
                <dd class="mt-1 flex items-center gap-x-1.5 text-sm text-gray-900">
                    <FontAwesomeIcon :icon="faMoneyCheckAlt" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ locale.currencyFormat(payment_account_shop.shop_currency_code, payment_account_shop.amount_successfully_paid) }}
                </dd>
            </div>
        </dl>

        <div class="mt-6">
            <h3 class="text-sm font-medium text-gray-500">{{ trans("Credit terms") }}</h3>
            <table v-if="payment_account_shop.pastpay_credit_terms?.options?.length" class="mt-2 w-full max-w-md divide-y divide-gray-200 border border-gray-200 rounded-md overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">{{ trans("Days") }}</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">{{ trans("Charge") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="option in payment_account_shop.pastpay_credit_terms.options" :key="option.days">
                        <td class="px-3 py-2 text-sm text-gray-900">{{ option.days }} {{ trans("days") }}</td>
                        <td class="px-3 py-2 text-sm text-gray-900">{{ option.charge }}%</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="mt-1 text-sm text-gray-400">{{ trans("No interest rates defined") }}</p>
        </div>
    </div>
</template>
