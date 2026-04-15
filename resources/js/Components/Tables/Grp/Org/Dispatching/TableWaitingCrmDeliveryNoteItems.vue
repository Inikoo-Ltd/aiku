<!--
  - Author: Steven Wicca stewicalf@gmail.com
  - Created: Wed, 15 Apr 2026 00:00:00 Central Indonesia Time, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faStickyNote } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faStickyNote)

defineProps<{
    data: TableTS
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(delivery_note_reference)="{ item }">
            <span class="font-medium">{{ item['delivery_note_reference'] ?? '-' }}</span>
        </template>

        <template #cell(org_stock_code)="{ item }">
            <div>
                <div class="font-bold">{{ item['org_stock_code'] }}</div>
                <div class="text-sm opacity-75">{{ item['org_stock_name'] }}</div>
            </div>
        </template>

        <template #cell(quantity_waiting_crm)="{ item }">
            <div class="flex flex-col gap-0.5">
                <span class="tabular-nums">{{ parseInt(item['quantity_waiting_crm']) }} {{ ctrans("items") }}</span>
                <span v-if="item['notes']" class="text-left border border-gray-300 bg-gray-100 px-2 py-1 rounded">
                    <div class="font-medium text-xs">
                        <FontAwesomeIcon icon="fal fa-sticky-note" fixed-width aria-hidden="true" />
                        {{ ctrans("Notes") }}
                    </div>
                    <div class="opacity-70 italic text-xs">{{ item['notes'] }}</div>
                </span>
            </div>
        </template>
    </Table>
</template>
