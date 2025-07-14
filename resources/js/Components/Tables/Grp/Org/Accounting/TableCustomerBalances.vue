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

defineProps<{
    data: object,
    tab?: string
}>()


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

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: customerBalance }">
            <Link :href="customerRoute(customerBalance) as string" class="primaryLink">
                {{ customerBalance.name }}
            </Link>
        </template>
    </Table>
</template>
