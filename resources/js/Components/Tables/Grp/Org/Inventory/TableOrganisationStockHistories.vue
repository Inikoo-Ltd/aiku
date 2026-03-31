<!--
  - Author: Nickel
  - Created: Tue, 01 Apr 2026
  - Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

function formatPeriod(period: string, tab: string): string {
    if (!period) return "-"
    const date = new Date(period)

    if (tab === "yearly") {
        return date.getFullYear().toString()
    }

    if (tab === "monthly") {
        return date.toLocaleDateString(undefined, { year: "numeric", month: "short" })
    }

    if (tab === "weekly") {
        return date.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" })
    }

    return date.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" })
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(period)="{ item }">
            <span class="tabular-nums font-medium">{{ formatPeriod(item.period, tab ?? 'daily') }}</span>
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
            <span class="tabular-nums">{{ locale.number(item.org_stock_value) }}</span>
        </template>

        <template #cell(grp_stock_value)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.grp_stock_value) }}</span>
        </template>
    </Table>
</template>
