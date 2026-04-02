<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Tue, 01 Apr 2026
  - Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from "@inertiajs/vue3"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

function locationRoute(organisationStockHistory) {
    return route(
        "grp.org.warehouses.show.inventory.org_stock_histories.show",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).warehouse,
            organisationStockHistory.id
        ]);
}


function toYmd(date: Date): string {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, "0")
    const d = String(date.getDate()).padStart(2, "0")
    return `${y}${m}${d}`
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(bucket)="{ item }">
            <Link :href="locationRoute(item)" class="primaryLink">
                {{ item.bucket }}
            </Link>
        </template>

        <template #cell(number_org_stocks)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.number_org_stocks) }}</span>
        </template>

        <template #cell(number_out_of_stock_org_stocks)="{ item }">
            <span
                class="tabular-nums"
                :class="item.number_out_of_stock_org_stocks > 0 ? 'text-red-500' : 'text-green-500'"
            >
                {{ locale.number(item.number_out_of_stock_org_stocks) }}
            </span>
        </template>

        <template #cell(number_location_org_stocks)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.number_location_org_stocks) }}</span>
        </template>

        <template #cell(org_stock_value)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.org_currency_code, item.org_stock_value) }}</span>
        </template>

        <template #cell(grp_stock_value)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.grp_currency_code, item.grp_stock_value) }}</span>
        </template>

        <template #cell(number_org_stocks_not_sold_1y)="{ item }">
             <span class="tabular-nums text-red-500">
                    {{ locale.number(item.number_org_stocks_not_sold_1y) }}
             </span>
        </template>
        <template #cell(value_dormant_stock_1y)="{ item }">
             <span class="tabular-nums text-red-500">
            {{ locale.currencyFormat(item.org_currency_code, item.value_dormant_stock_1y) }}
             </span>
        </template>
    </Table>
</template>
