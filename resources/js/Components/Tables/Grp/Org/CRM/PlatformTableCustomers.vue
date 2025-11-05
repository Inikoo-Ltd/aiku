<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
    data: object,
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item }">
            <div>
                {{ item.reference }}
            </div>
        </template>

        <template #cell(shop)="{ item }">
            <div>
                {{ item.shop }}
            </div>
        </template>

        <template #cell(location)="{ item }">
            <AddressLocation :data="item.location" />
        </template>

        <template #cell(invoiced_net_amount)="{ item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(item.currency_code, item.sales_all) }}</div>
        </template>

        <template #cell(sales_all)="{ item }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(item.currency_code, item.sales_all) }}</div>
        </template>
    </Table>
</template>
