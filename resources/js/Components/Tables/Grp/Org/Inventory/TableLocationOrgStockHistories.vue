<!--
  - Author: Nickel
  - Created: Tue, 01 Apr 2026
  - Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { Link } from "@inertiajs/vue3"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

function orgStockRoute(stockId: number): string {
    return route("grp.helpers.redirect_org_stock", [stockId])
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'default'" class="mt-5">
        <template #cell(date)="{ item }">
            <span class="tabular-nums font-medium">
                {{ new Date(item.date).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) }}
            </span>
        </template>

        <template #cell(stock_code)="{ item }">
            <Link :href="orgStockRoute(item.stock_id)" class="primaryLink">
                {{ item.stock_code }}
            </Link>
        </template>

        <template #cell(stock_name)="{ item }">
            <span>{{ item.stock_name }}</span>
        </template>

        <template #cell(location_code)="{ item }">
            <span class="font-mono text-xs">{{ item.location_code }}</span>
        </template>

        <template #cell(quantity_in_locations)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.quantity_in_locations) }}</span>
        </template>

        <template #cell(org_stock_value)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat(item.currency_code, item.org_stock_value) }}</span>
        </template>
    </Table>
</template>
