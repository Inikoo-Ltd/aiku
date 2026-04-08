<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"

defineProps<{
    data: TableTS
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(org_stock_name)="{ item }">
            <div class="flex items-center gap-2">
                <span class="font-mono text-xs text-gray-500">{{ item.org_stock_code }}</span>
                <span>{{ item.org_stock_name }}</span>
            </div>
        </template>

        <template #cell(action)="{ item }">
            <button
                type="button"
                class="px-3 py-1 text-xs font-medium text-white bg-orange-500 rounded hover:bg-orange-600 transition-colors"
            >
                {{ ctrans('Call Customer Service') }}
            </button>
        </template>
    </Table>
</template>
