<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"

defineProps<{
    data: {}
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(created_at)="{ item }">
            {{ useFormatTime(item.created_at, { formatTime: 'hms' }) }}
        </template>

        <template #cell(path)="{ item }">
            <span v-tooltip="item.payload ? JSON.stringify(item.payload) : trans('No body sent')" class="font-mono">
                /{{ item.path }}
            </span>
        </template>

        <template #cell(status)="{ item }">
            <span :class="item.is_success ? 'text-green-600' : 'text-red-600'">
                {{ item.status }}
            </span>
        </template>

        <template #cell(duration_ms)="{ item }">
            {{ item.duration_ms }} ms
        </template>
    </Table>
</template>
