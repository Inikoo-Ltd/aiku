<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { RouteParams } from "@/types/route-params"
import { CustomerBalance } from "@/types/customer-balance"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
    data: object,
    tab?: string
}>()

const locale = useLocaleStore()


function customerRoute(customerBalance: CustomerBalance) {
    if (route().current() === "grp.org.accounting.balances.index") {
        if (customerBalance.shop_type === "fulfilment") {
            return route(
                "grp.org.fulfilments.show.crm.customers.show",
                [
                    (route().params as RouteParams).organisation,
                    customerBalance.fulfilment_slug,
                    customerBalance.slug]
            )
        } else {
            return route(
                "grp.org.shops.show.crm.customers.show",
                [
                    (route().params as RouteParams).organisation,
                    customerBalance.shop_slug,
                    customerBalance.slug
                ])
        }
    }
}

function shopRoute(customerBalance: CustomerBalance) {
    if (route().current() === "grp.org.accounting.balances.index") {
        if (customerBalance.shop_type === "fulfilment") {
            return route(
                "grp.org.fulfilments.show.operations.accounting.customer_balances.index",
                [
                    (route().params as RouteParams).organisation,
                    customerBalance.fulfilment_slug
                ]
            )
        } else {
            return route(
                "grp.org.shops.show.dashboard.payments.accounting.customer_balances.index",
                [
                    (route().params as RouteParams).organisation,
                    customerBalance.shop_slug

                ])
        }
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: customerBalance }">
            <Link :href="customerRoute(customerBalance)" class="primaryLink">
                {{ customerBalance.reference }}
            </Link>
        </template>

        <template #cell(shop_code)="{ item: customerBalance }">
            <Link :href="shopRoute(customerBalance)" class="secondaryLink">
                {{ customerBalance.shop_code }}
            </Link>
        </template>

        <template #cell(balance)="{ item: customerBalance }">
            {{ locale.currencyFormat(customerBalance.currency_code, customerBalance.balance) }}
        </template>
    </Table>
</template>
