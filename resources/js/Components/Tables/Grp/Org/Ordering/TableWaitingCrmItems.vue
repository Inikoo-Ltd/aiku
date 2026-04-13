<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 13 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"

defineProps<{
    data: TableTS
    tab?: string
}>()

const orderRoute = (item: Record<string, any>): string | null => {
    if (!item['order_slug'] || !item['shop_slug'] || !item['organisation_slug']) {
        return null
    }
    try {
        return route('grp.org.shops.show.ordering.orders.show', [
            item['organisation_slug'],
            item['shop_slug'],
            item['order_slug'],
        ])
    } catch {
        return null
    }
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(order_reference)="{ item }">
            <Link v-if="orderRoute(item)" :href="orderRoute(item)!" class="primaryLink">
                {{ item['order_reference'] }}
            </Link>
            <span v-else>{{ item['order_reference'] ?? '-' }}</span>
        </template>

        <template #cell(org_stock_code)="{ item }">
            <span class="font-mono font-semibold">{{ item['org_stock_code'] }}</span>
        </template>

        <template #cell(org_stock_name)="{ item }">
            <span>{{ item['org_stock_name'] }}</span>
        </template>

        <template #cell(quantity_waiting_crm)="{ item }">
            <span class="tabular-nums">{{ item['quantity_waiting_crm'] }}</span>
        </template>
    </Table>
</template>
