<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
    data: object
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(total_amount)="{ item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(item.currency_code, item.total_amount) }}</div>
        </template>
        <template #cell(avg_order_amount)="{ item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(item.currency_code, item.avg_order_amount) }}</div>
        </template>
    </Table>
</template>
