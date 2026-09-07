<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 02 Sep 2026 21:40:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes } from "@fal"

library.add(faCheck, faTimes)

defineProps<{ data: object; tab?: string }>()

const formatArguments = (args: unknown): string => {
    if (!args || (typeof args === "object" && Object.keys(args as object).length === 0)) {
        return "-"
    }
    return typeof args === "string" ? args : JSON.stringify(args)
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap text-gray-500">{{ useFormatTime(item.created_at, { formatTime: "hms", keepTimezone: true }) }}</span>
        </template>
        <template #cell(tool)="{ item }">
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ item.tool }}</span>
        </template>
        <template #cell(arguments)="{ item }">
            <span class="break-all font-mono text-xs text-gray-500">{{ formatArguments(item.arguments) }}</span>
        </template>
        <template #cell(is_error)="{ item }">
            <FontAwesomeIcon :icon="item.is_error ? faTimes : faCheck" :class="item.is_error ? 'text-red-500' : 'text-green-500'" fixed-width />
        </template>
        <template #cell(duration_ms)="{ item }">
            <span class="tabular-nums text-gray-500">{{ item.duration_ms != null ? `${item.duration_ms.toLocaleString()}ms` : "-" }}</span>
        </template>
    </Table>
</template>
