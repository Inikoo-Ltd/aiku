<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { faBox, faHandHoldingBox, faPallet, faPencil, faSeedling, faCashRegister } from "@fal"
import { faCheckCircle, faTimesCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useLocaleStore } from "@/Stores/locale"
import Icon from "@/Components/Icon.vue"
import { PaymentAccountShop } from "@/types/payment-account-shop"
import { RouteParams } from "@/types/route-params"
import paymentAccount from "@/Pages/Grp/Overview/Accounting/PaymentAccount.vue"
import paymentAccountShops from "@/Pages/Grp/Org/Accounting/PaymentAccountShops.vue"
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"

library.add(faBox, faHandHoldingBox, faPallet, faPencil, faSeedling, faCashRegister, faCheckCircle, faTimesCircle)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore()

function paymentAccountShopRoute(paymentAccountShop: PaymentAccountShop) {
    switch (route().current()) {
        case "grp.org.accounting.payment-accounts.show.shops.index":
            return route(
                "grp.org.accounting.payment-accounts.show.shops.show",
                {
                    organisation: (route().params as RouteParams).organisation,
                    paymentAccount: (route().params as RouteParams).organisation,
                    paymentAccountShop: paymentAccountShop.id
                })
        case "grp.org.shops.show.dashboard.payments.accounting.accounts.index":
            return route(
                "grp.org.shops.show.dashboard.payments.accounting.accounts.show",
                {
                    organisation: (route().params as RouteParams).organisation,
                    shop: (route().params as RouteParams).shop,
                    paymentAccountShop: paymentAccountShop.id
                })
        case "grp.org.fulfilments.show.operations.accounting.accounts.index":
            return route(
                "grp.org.fulfilments.show.operations.accounting.accounts.show",
                {
                    organisation: (route().params as RouteParams).organisation,
                    fulfilment: (route().params as RouteParams).fulfilment,
                    paymentAccountShop: paymentAccountShop.id
                })

    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">


        <template #cell(payment_account_name)="{ item: paymentAccountShops }">
            <Link :href="paymentAccountShopRoute(paymentAccountShops)" class="primaryLink">
                {{ paymentAccountShops["payment_account_name"] }}
            </Link>
        </template>

        <template #cell(state)="{ item: paymentAccountShops }">
            <Icon :data="paymentAccountShops.state_icon" />
        </template>

        <template #cell(show_in_checkout)="{ item: paymentAccountShops }">
            <span v-if="paymentAccountShops.show_in_checkout" v-tooltip="trans('Shown on checkout')" class="opacity-70 hover:opacity-100">
                <FontAwesomeIcon icon="fal fa-cash-register" class="" fixed-width aria-hidden="true" />
            </span>
            <span v-else />
        </template>

        <template #cell(number_payments)="{ item: paymentAccountShops }">
            {{ useLocaleStore().number(paymentAccountShops.number_payments) }}
        </template>

        <template #cell(amount_successfully_paid)="{ item: paymentAccountShop }">
            <div class="text-gray-500">{{ locale.currencyFormat(paymentAccountShop.shop_currency_code, paymentAccountShop.amount_successfully_paid) }}</div>
        </template>
    </Table>
</template>
