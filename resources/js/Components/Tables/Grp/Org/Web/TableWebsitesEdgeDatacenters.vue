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
const shareKeys = ["requests_share", "hit_share", "reached_share"]

const formatShare = (value: number | null) => (value === null ? "—" : `${value.toFixed(1)}%`)
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(colo)="{ item }">
            <span class="inline-flex items-center gap-2 whitespace-nowrap font-medium">
                <img v-if="item.country_code" :src="`/flags/${item.country_code.toLowerCase()}.png`" :alt="item.country_code" class="h-3 w-4 rounded-sm object-cover" />
                <span v-tooltip.top="item.location">{{ item.colo }}</span>
            </span>
        </template>
        <template v-for="key in countKeys" :key="key" #[`cell(${key})`]="{ item }">
            <span class="tabular-nums">{{ locale.number(item[key]) }}</span>
        </template>
        <template v-for="key in shareKeys" :key="key" #[`cell(${key})`]="{ item }">
            <span class="tabular-nums">{{ formatShare(item[key]) }}</span>
        </template>
    </Table>
</template>
