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
import { Location } from "@/types/location"

defineProps<{
    data: object
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


function formatPeriodx(period: string, tab: string): string {
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

function toYmd(date: Date): string {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, "0")
    const d = String(date.getDate()).padStart(2, "0")
    return `${y}${m}${d}`
}

function periodDateRange(period: string, tab: string): string {
    if (!period) return ""
    const date = new Date(period)

    if (tab === "yearly") {
        const start = new Date(date.getFullYear(), 0, 1)
        const end = new Date(date.getFullYear(), 11, 31)
        return `${toYmd(start)}-${toYmd(end)}`
    }

    if (tab === "monthly") {
        const start = new Date(date.getFullYear(), date.getMonth(), 1)
        const end = new Date(date.getFullYear(), date.getMonth() + 1, 0)
        return `${toYmd(start)}-${toYmd(end)}`
    }

    if (tab === "weekly") {
        const day = date.getDay()
        const diff = (day === 0 ? -6 : 1 - day)
        const monday = new Date(date)
        monday.setDate(date.getDate() + diff)
        const sunday = new Date(monday)
        sunday.setDate(monday.getDate() + 6)
        return `${toYmd(monday)}-${toYmd(sunday)}`
    }

    return `${toYmd(date)}-${toYmd(date)}`
}

function locationHistoriesRoute(period: string, tab: string): string {
    const dateRange = periodDateRange(period, tab)
    return route(
        "grp.org.warehouses.show.inventory.org_stock_histories.location_histories.index",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).warehouse,
        ]
    ) + (dateRange ? `?between[date]=${dateRange}` : "")
}
</script>

<template>
    <Table :resource="data" class="mt-5">
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
    </Table>
</template>
