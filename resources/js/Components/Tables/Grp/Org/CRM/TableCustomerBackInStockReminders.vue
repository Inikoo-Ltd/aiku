<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { RouteParams } from "@/types/route-params"
import { BackInStockRemainder } from "@/types/back-in-stock_remainder"

defineProps<{
    data: object,
    tab?: string
}>()


function backInStockRoute(backInStockReminder: BackInStockRemainder) {
    return route(
        "grp.org.shops.show.catalogue.products.all_products.show",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            backInStockReminder.product_slug
        ])
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: backInStockReminder }">
            <Link :href="backInStockRoute(backInStockReminder)" class="primaryLink">
                {{ backInStockReminder["product_code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: backInStockReminder }">
            {{ backInStockReminder["product_name"] }}
        </template>
    </Table>
</template>


