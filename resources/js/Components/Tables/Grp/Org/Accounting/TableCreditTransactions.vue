<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { RouteParams } from "@/types/route-params"
import { CreditTransaction } from "@/types/credit-transaction"
import { faStickyNote } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { useBasicColor } from '@/Composables/useColors'
// Import the new NotesDisplay component
import NotesDisplay from "@/Components/NotesDisplay.vue"

const props = defineProps<{
    data: object,
    tab?: string
}>();


function paymentRoute(credit_transaction?: CreditTransaction) {

    if(route().current()=='grp.org.shops.show.crm.customers.show' && credit_transaction?.payment_id){
        return route(
            "grp.org.shops.show.crm.customers.show.payments.show",
            {
              payment: credit_transaction.payment_id,
              organisation: (route().params as RouteParams).organisation,
              shop: (route().params as RouteParams).shop,
              customer: (route().params as RouteParams).customer
            }
        );
    } else if ((route().current() == 'grp.org.shops.show.dashboard.payments.accounting.credit_transactions.index' || route().current() == 'grp.org.accounting.credit_transactions.index') && (
        credit_transaction?.payment_id &&
        credit_transaction?.org_slug &&
        credit_transaction?.shop_slug &&
        credit_transaction?.customer_slug
    )) {
        return route(
            "grp.org.shops.show.crm.customers.show.payments.show", 
            {
                payment: credit_transaction.payment_id,
                organisation: credit_transaction?.org_slug,
                shop: credit_transaction?.shop_slug,
                customer: credit_transaction?.customer_slug
            }
        );
    }

    return '';
}

function orderRoute(credit_transaction?: CreditTransaction){

    if (route().current()=='grp.org.shops.show.crm.customers.show' && credit_transaction?.order_slug){
        return route(
            "grp.org.shops.show.crm.customers.show.orders.show", 
            {
                order: credit_transaction.order_slug,
                organisation: (route().params as RouteParams).organisation,
                shop: (route().params as RouteParams).shop,
                customer: (route().params as RouteParams).customer
            }
        );
    } else if ((route().current() == 'grp.org.shops.show.dashboard.payments.accounting.credit_transactions.index' || route().current() == 'grp.org.accounting.credit_transactions.index') && (
        credit_transaction?.order_slug &&
        credit_transaction?.org_slug &&
        credit_transaction?.shop_slug &&
        credit_transaction?.customer_slug
    )) {
        return route(
            "grp.org.shops.show.crm.customers.show.orders.show", 
            {
                order: credit_transaction?.order_slug,
                organisation: credit_transaction?.org_slug,
                shop: credit_transaction?.shop_slug,
                customer: credit_transaction?.customer_slug
            }
        );
    }
    
    return '';
}

function customerRoute(credit_transaction?: CreditTransaction){

    if ((route().current() == 'grp.org.shops.show.dashboard.payments.accounting.credit_transactions.index' || route().current() == 'grp.org.accounting.credit_transactions.index') && (
        credit_transaction?.org_slug &&
        credit_transaction?.shop_slug &&
        credit_transaction?.customer_slug
    )) {
        return route(
            "grp.org.shops.show.crm.customers.show", 
            {
                organisation: credit_transaction?.org_slug,
                shop: credit_transaction?.shop_slug,
                customer: credit_transaction?.customer_slug
            }
        );
    }
}

function creditNoteRoute(credit_transaction: CreditTransaction) {
    return route('grp.org.accounting.refunds.show', {
        organisation: credit_transaction.org_slug ?? (route().params as RouteParams).organisation,
        refund: credit_transaction.credit_note_slug
    })
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(customer_ref)="{ item: credit_transaction }">
            <Link :href="(customerRoute(credit_transaction) as string)" class="primaryLink">
                {{ credit_transaction.customer_ref }}
            </Link>
        </template>
        <template #cell(payment_reference)="{ item: credit_transaction }">
            <Link v-if="credit_transaction?.payment_id" :href="(paymentRoute(credit_transaction) as string)" class="primaryLink">
                {{ credit_transaction.payment_reference }}
            </Link>
            <div v-else>
              {{ credit_transaction.payment_reference }}
            </div>
        </template>
        <template #cell(type)="{ item: credit_transaction }">
            <div class="flex">
                <div class="pr-2" style="max-width:90%; width:100%">
                    {{ credit_transaction.type }}
                </div>
                <NotesDisplay v-if="credit_transaction.notes" :item="credit_transaction" reference-field="type" :class="'ml-3'"/>
            </div>
        </template>
        <template #cell(credit_note_reference)="{ item: credit_transaction }">
            <div v-if="credit_transaction.credit_note_slug">
                <Link :href="creditNoteRoute(credit_transaction)" class="primaryLink">
                    {{ credit_transaction.credit_note_reference }}
                </Link>
                <div v-if="credit_transaction.requested_by" class="text-xs text-gray-500">
                    {{ ctrans('Requested by') }} {{ credit_transaction.requested_by }}
                </div>
            </div>
        </template>
        <template #cell(order_reference)="{ item: credit_transaction }">
            <Link v-if="credit_transaction?.order_slug" :href="(orderRoute(credit_transaction) as string)" class="primaryLink">
                {{ credit_transaction.order_reference }}
            </Link>
            <div v-else>
                {{ credit_transaction.order_reference }}
            </div>
        </template>
    </Table>
</template>
