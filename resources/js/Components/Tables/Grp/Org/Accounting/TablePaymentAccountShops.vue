<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from "@inertiajs/vue3"
import { faBox, faHandHoldingBox, faPallet, faPencil, faSeedling } from "@fal"
import { PaymentAccount } from "@/types/payment-account"
import { faCheckCircle, faShoppingBasket, faTimesCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { useLocaleStore } from "@/Stores/locale"
import Icon from "@/Components/Icon.vue"
import { PaymentAccountShops } from "@/types/payment-account-shops"

library.add(faBox, faHandHoldingBox, faPallet, faPencil, faSeedling, faCheckCircle, faTimesCircle, faShoppingBasket)

defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore();

console.log(route().params);

function paymentRoute(PaymentAccountShops: PaymentAccountShops) {
    let routename = '';
    let parent = '';
    let slug = PaymentAccountShops.payment_account_slug;

    if(route().params['fulfilment']){
        routename = 'grp.org.fulfilments.show.operations.accounting.accounts.show';
        parent = route().params['fulfilment'];
    }else if(route().params['shop']){
        routename = 'grp.org.shops.show.dashboard.payments.accounting.accounts.show';
        parent = route().params['shop'];
    }else{
        routename = 'grp.org.accounting.payment-accounts.show.parent';
        parent = route().params['paymentAccount'];
        slug = PaymentAccountShops.shop_slug
    }

    return route(routename, [route().params['organisation'], parent, slug]);
}
</script>


<template>
    <!-- {{ props.shopsList }} -->
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: paymentAccountShops }">
            <Icon :data="paymentAccountShops.state_icon" v-on:click="() => { console.log(paymentAccountShops)}" />
        </template>
        <template #cell(show_in_checkout)="{ item: paymentAccountShops }">
            <Icon v-if="paymentAccountShops.show_in_checkout" :data="paymentAccountShops.show_in_checkout_icon" />
        </template>
        <template #cell(shop_name)="{ item: paymentAccountShops }">
            <Link :href="paymentRoute(paymentAccountShops)" class="primaryLink" >
                {{ paymentAccountShops.shop_name }}
            </Link>
        </template>
        <template #cell(payment_account_name)="{ item: paymentAccountShops }">
            <Link :href="paymentRoute(paymentAccountShops)" class="primaryLink" >
                {{ paymentAccountShops.payment_account_name }}
            </Link>
        </template>
        <template #cell(number_payments)="{ item: paymentAccountShops }">
            {{ useLocaleStore().number(paymentAccountShops.number_payments) }}
        </template>
        <template #cell(amount_successfully_paid)="{ item: paymentAccountShop }">
            <div class="text-gray-500">{{ locale.currencyFormat(paymentAccountShop.shop_currency_code, paymentAccountShop.amount_successfully_paid) }}</div>
        </template>
    </Table>
</template>
