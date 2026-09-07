<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 02 Sep 2026 21:40:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{ data: object; tab?: string }>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: "hms", keepTimezone: true }) }}</span>
        </template>
        <template #cell(query)="{ item }">
            <span class="font-medium text-gray-800">{{ item.query }}</span>
        </template>
        <template #cell(scope)="{ item }">
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ item.scope }}</span>
        </template>
        <template #cell(clicked_at)="{ item }">
            <a v-if="item.clicked_url" :href="item.clicked_url" class="text-xs text-indigo-600 hover:underline">{{ useFormatTime(item.clicked_at, { formatTime: "hms", keepTimezone: true }) }}</a>
            <span v-else class="text-gray-300">-</span>
        </template>
    </Table>
</template>
