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
</script>

<template>
    <Table :resource="data" :name="tab ?? 'default'" class="mt-5">
        <template #cell(date)="{ item }">
            <span class="tabular-nums font-medium">
                {{ new Date(item.date).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) }}
            </span>
        </template>

        <template #cell(quantity_in_locations)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.quantity_in_locations) }}</span>
        </template>

        <template #cell(number_locations)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.number_locations) }}</span>
        </template>

        <template #cell(org_stock_value)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.org_stock_value) }}</span>
        </template>

        <template #cell(unit_value)="{ item }">
            <span class="tabular-nums">{{ item.unit_value != null ? locale.number(item.unit_value) : '-' }}</span>
        </template>
    </Table>
</template>
