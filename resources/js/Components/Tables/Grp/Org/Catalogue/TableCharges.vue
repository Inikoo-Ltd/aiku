<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { inject } from "vue"
import Icon from "@/Components/Icon.vue"

defineProps<{
    data: {
        data: {}
        links: Links
        meta: Meta
    },
    tab?: string
}>()


function shopRoute(charge: {}) {
    console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.billables.charges.index":
            return route(
                "grp.org.shops.show.billables.charges.show",
                [route().params["organisation"], route().params["shop"], charge.slug])
        default:
            return null
    }
}

function customersRoute(charge: {}) {
    console.log(route().current())
    switch (route().current()) {
        case "grp.org.shops.show.billables.charges.index":
            return route(
                "grp.org.shops.show.billables.charges.show.customers.index",
                [route().params["organisation"], route().params["shop"], charge.slug])
        default:
            return null
    }
}


const locale = inject('locale', aikuLocaleStructure)


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: charge }">
            <Icon :data="charge['state_icon']" />
        </template>
        <template #cell(code)="{ item: charge }">
            <Link :href="shopRoute(charge)" class="primaryLink">
            {{ charge["code"] }}
            </Link>
        </template>
        <template #cell(customers_invoiced_all)="{ item: charge }">
            <Link :href="customersRoute(charge)" class="secondaryLink">
            {{ charge["customers_invoiced_all"] }}
            </Link>
        </template>
        <template #cell(sales_all)="{ item: charge }">
            {{ locale.currencyFormat(charge.currency_code, charge.sales_all) }}
        </template>

        
    </Table>
</template>