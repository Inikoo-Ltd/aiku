<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 02 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
    data: object
    tab?: string
}>()

const locale = useLocaleStore()

const countKeys = ["requests", "requests_hit", "requests_reached", "requests_none", "websites"]
const shareKeys = ["hit_share", "reached_share"]

const formatShare = (value: number | null) => (value === null ? "—" : `${value.toFixed(1)}%`)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(colo)="{ item }">
            <span class="whitespace-nowrap font-medium">{{ item.colo }}</span>
        </template>
        <template v-for="key in countKeys" :key="key" #[`cell(${key})`]="{ item }">
            <span class="tabular-nums">{{ locale.number(item[key]) }}</span>
        </template>
        <template v-for="key in shareKeys" :key="key" #[`cell(${key})`]="{ item }">
            <span class="tabular-nums">{{ formatShare(item[key]) }}</span>
        </template>
    </Table>
</template>
