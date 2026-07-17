<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 17 Jul 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'

const props = defineProps<{
    data: object,
    tab?: string
}>()

function orgStockRoute(item: { org_stock_id?: number }) {
    if (!item.org_stock_id) {
        return ''
    }

    return route('grp.majordomo.redirect_org_stock', [item.org_stock_id])
}

function formatQuantity(value: number | string) {
    const num = Number(value)
    if (Number.isNaN(num)) {
        return value
    }

    return parseFloat(num.toFixed(2)).toString()
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(org_stock_code)="{ item }">
            <Link v-if="orgStockRoute(item)" :href="orgStockRoute(item)" class="primaryLink">
                {{ item.org_stock_code }}
            </Link>
            <span v-else>{{ item.org_stock_code }}</span>
        </template>

        <template #cell(org_stock_name)="{ item }">
            {{ item.org_stock_name }}
        </template>

        <template #cell(unit_quantity)="{ item }">
            {{ formatQuantity(item.unit_quantity) }}
        </template>
    </Table>
</template>
