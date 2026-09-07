<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 02 Sep 2026 21:40:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"

defineProps<{ data: object; tab?: string }>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: "hms", keepTimezone: true }) }}</span>
        </template>
        <template #cell(channel)="{ item }">
            <span class="rounded-full px-2 py-0.5 text-xs" :class="item.channel === 'internal' ? 'bg-indigo-50 text-indigo-700' : 'bg-emerald-50 text-emerald-700'">
                {{ item.channel === "internal" ? trans("Staff") : trans("Customer") }}
            </span>
        </template>
        <template #cell(text)="{ item }">
            <span class="line-clamp-2 text-gray-700">{{ item.text }}</span>
        </template>
    </Table>
</template>
