<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Mon, 30 Dec 2025 17:00:00 Western Indonesia Time, Bali, Indonesia
  - Copyright (c) 2025, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps<{
    data: object
    tab?: string
}>()

const locale = useLocaleStore()
const page = usePage()

// Get currency from page props (organization or shop currency)
const currency = computed(() => {
    return page.props?.auth?.organisation?.currency?.code ||
           page.props?.shop?.currency?.code ||
           'USD'
})
</script>

<template>
    <Table
        :resource="data"
        :name="tab"
        class="mt-5"
    >
        <template #cell(period)="{ item }">
            <span class="font-medium text-gray-700">{{ item.period }}</span>
        </template>

        <template #cell(sales)="{ item }">
            <div :class="item.sales >= 0 ? 'text-gray-700' : 'text-red-500'">
                {{ locale.currencyFormat(currency, item.sales) }}
            </div>
        </template>

        <template #cell(invoices)="{ item }">
            <div class="text-gray-700">
                {{ locale.number(item.invoices) }}
            </div>
        </template>

        <template #cell(refunds)="{ item }">
            <div :class="item.refunds > 0 ? 'text-red-500' : 'text-gray-700'">
                {{ locale.number(item.refunds) }}
            </div>
        </template>

        <template #cell(customers_invoiced)="{ item }">
            <div class="text-gray-700">
                {{ locale.number(item.customers_invoiced) }}
            </div>
        </template>
    </Table>
</template>
