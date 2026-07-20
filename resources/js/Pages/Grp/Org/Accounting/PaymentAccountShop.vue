<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 16 Dec 2025 19:39:25 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCashRegister, faStore, faMoneyCheckAlt, faIdCard, faStopwatch, faShoePrints, faShoppingCart, faReceipt } from "@fal"
import { faCheckCircle, faTimesCircle, faCircle } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { PaymentAccountShop } from "@/types/payment-account-shop"

library.add(faCashRegister, faStore, faMoneyCheckAlt, faIdCard, faStopwatch, faShoePrints, faShoppingCart, faReceipt, faCheckCircle, faTimesCircle, faCircle)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    payment_account_shop: PaymentAccountShop
}>()

const locale = useLocaleStore()

const stateClasses: Record<string, string> = {
    active: "bg-green-100 text-green-800 ring-green-600/20",
    inactive: "bg-red-100 text-red-700 ring-red-600/20",
    in_process: "bg-yellow-100 text-yellow-800 ring-yellow-600/20",
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-4 py-5 sm:px-6 max-w-5xl">
        <!-- Section: status + stats cards -->
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-gray-200 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ trans("Status") }}</dt>
                <dd class="mt-2">
                    <span
                        class="inline-flex items-center gap-x-1.5 rounded-full px-2.5 py-1 text-sm font-medium ring-1 ring-inset"
                        :class="stateClasses[payment_account_shop.state] ?? 'bg-gray-100 text-gray-700 ring-gray-500/20'">
                        <FontAwesomeIcon :icon="payment_account_shop.state === 'active' ? faCheckCircle : faTimesCircle" fixed-width aria-hidden="true" />
                        {{ payment_account_shop.state_label ?? payment_account_shop.state }}
                    </span>
                    <div v-if="payment_account_shop.activated_at" class="mt-1.5 text-xs text-gray-500">
                        {{ trans("Activated") }} {{ useFormatTime(payment_account_shop.activated_at) }}
                    </div>
                </dd>
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ trans("Visible in checkout") }}</dt>
                <dd class="mt-2 flex items-center gap-x-1.5 text-sm text-gray-900">
                    <FontAwesomeIcon
                        :icon="payment_account_shop.show_in_checkout ? faCheckCircle : faTimesCircle"
                        :class="payment_account_shop.show_in_checkout ? 'text-green-500' : 'text-red-400'"
                        fixed-width aria-hidden="true" />
                    {{ payment_account_shop.show_in_checkout ? trans("Yes") : trans("No") }}
                    <span v-if="payment_account_shop.checkout_display_position" class="text-xs text-gray-400">
                        ({{ trans("position") }} {{ payment_account_shop.checkout_display_position }})
                    </span>
                </dd>
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ trans("Payments") }}</dt>
                <dd class="mt-2 flex items-center gap-x-1.5 text-sm text-gray-900">
                    <FontAwesomeIcon :icon="faReceipt" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ locale.number(payment_account_shop.number_payments ?? 0) }}
                </dd>
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ trans("Amount successfully paid") }}</dt>
                <dd class="mt-2 flex items-center gap-x-1.5 text-sm text-gray-900">
                    <FontAwesomeIcon :icon="faMoneyCheckAlt" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ locale.currencyFormat(payment_account_shop.shop_currency_code, payment_account_shop.amount_successfully_paid) }}
                </dd>
            </div>
        </dl>

        <!-- Section: account info -->
        <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="border-b border-gray-200 pb-3">
                <dt class="text-sm font-medium text-gray-500">{{ trans("Payment account") }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ payment_account_shop.payment_account_name ?? '-' }}
                    <span v-if="payment_account_shop.payment_account_code" class="text-gray-400">({{ payment_account_shop.payment_account_code }})</span>
                </dd>
            </div>

            <div class="border-b border-gray-200 pb-3">
                <dt class="text-sm font-medium text-gray-500">{{ trans("Shop") }}</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    {{ payment_account_shop.shop_name }}
                    <span class="text-gray-400">({{ payment_account_shop.shop_code }})</span>
                </dd>
            </div>

            <div v-if="payment_account_shop.pastpay" class="border-b border-gray-200 pb-3">
                <dt class="text-sm font-medium text-gray-500">
                    <FontAwesomeIcon :icon="faIdCard" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ trans("Creditor tax number") }}
                </dt>
                <dd class="mt-1 text-sm" :class="payment_account_shop.pastpay.tax_number ? 'text-gray-900' : 'text-red-500 italic'">
                    {{ payment_account_shop.pastpay.tax_number ?? trans("Not set") }}
                </dd>
            </div>
        </dl>

        <!-- Section: PastPay settings -->
        <template v-if="payment_account_shop.pastpay">
            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div>
                    <h3 class="flex items-center gap-x-1.5 text-sm font-medium text-gray-500">
                        <FontAwesomeIcon :icon="faStopwatch" class="text-gray-400" fixed-width aria-hidden="true" />
                        {{ trans("Credit terms") }}
                    </h3>
                    <table v-if="payment_account_shop.pastpay.credit_terms?.length" class="mt-2 w-full divide-y divide-gray-200 border border-gray-200 rounded-md overflow-hidden">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">{{ trans("Days") }}</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">{{ trans("Charge") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="option in payment_account_shop.pastpay.credit_terms" :key="option.days">
                                <td class="px-3 py-2 text-sm text-gray-900">{{ option.days }} {{ trans("days") }}</td>
                                <td class="px-3 py-2 text-sm text-gray-900">{{ option.charge }}%</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="mt-2 text-sm text-red-500 italic">{{ trans("No credit terms defined") }}</p>

                    <h3 class="mt-6 flex items-center gap-x-1.5 text-sm font-medium text-gray-500">
                        {{ trans("Setup checklist") }}
                    </h3>
                    <ul class="mt-2 space-y-1.5">
                        <li v-for="item in payment_account_shop.pastpay.setup_checklist" :key="item.label" class="flex items-center gap-x-2 text-sm">
                            <FontAwesomeIcon
                                :icon="item.done ? faCheckCircle : faTimesCircle"
                                :class="item.done ? 'text-green-500' : 'text-red-400'"
                                fixed-width aria-hidden="true" />
                            <span :class="item.done ? 'text-gray-900' : 'text-gray-500'">{{ item.label }}</span>
                        </li>
                    </ul>
                </div>

                <div>
                    <h3 class="flex items-center gap-x-1.5 text-sm font-medium text-gray-500">
                        <FontAwesomeIcon :icon="faShoePrints" class="text-gray-400" fixed-width aria-hidden="true" />
                        {{ trans("Invoice footer") }}
                    </h3>
                    <div
                        v-if="payment_account_shop.pastpay.invoice_footer"
                        class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 prose prose-sm max-w-none"
                        v-html="payment_account_shop.pastpay.invoice_footer" />
                    <p v-else class="mt-2 text-sm text-red-500 italic">{{ trans("No invoice footer defined") }}</p>
                    <p class="mt-1.5 text-xs text-gray-400">{{ trans("This text is printed at the bottom of invoices paid with PastPay.") }}</p>
                </div>
            </div>
        </template>
    </div>
</template>
