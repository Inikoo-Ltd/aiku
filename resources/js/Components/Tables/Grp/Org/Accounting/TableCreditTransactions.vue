<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import { Link } from "@inertiajs/vue3"
import { RouteParams } from "@/types/route-params"
import { CreditTransaction } from "@/types/credit-transaction"

defineProps<{
    data: object,
    tab?: string
}>();

function paymentRoute(credit_transaction: CreditTransaction) {

    if(route().current()=='grp.org.shops.show.crm.customers.show'){
        return route(
            "grp.org.shops.show.crm.customers.show.payments.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                (route().params as RouteParams).customer,
                credit_transaction.payment_id
            ]
        );
    }

    return null;
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(payment_reference)="{ item: credit_transaction }">
            <Link :href="paymentRoute(credit_transaction) as string" class="primaryLink">
                {{ credit_transaction.payment_reference }}
            </Link>
        </template>
    </Table>
</template>


