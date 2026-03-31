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
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

function orgStockRoute(stockSlug: string): string {
    return route(
        "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show.org_stock_history",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).warehouse,
            stockSlug,
        ]
    )
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
            <!-- Todo: fix link -->
            <!-- <Link :href="orgStockRoute(item.stock_slug)" class="primaryLink">
                {{ item.stock_code }}
            </Link> -->
            {{ item.stock_code }}
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

        <template #cell(actual_quantity_in_locations)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.actual_quantity_in_locations) }}</span>
        </template>
    </Table>
</template>
