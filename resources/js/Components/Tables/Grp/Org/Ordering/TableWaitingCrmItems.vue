<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 13 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
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

const orderRoute = (item: Record<string, any>): string | null => {
    if (!item['order_slug'] || !item['shop_slug'] || !item['organisation_slug']) {
        return null
    }
    try {
        return route('grp.org.shops.show.ordering.orders.show', [
            item['organisation_slug'],
            item['shop_slug'],
            item['order_slug'],
        ])
    } catch {
        return null
    }
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(order_reference)="{ item }">
            <Link v-if="orderRoute(item)" :href="orderRoute(item)!" class="primaryLink">
                {{ item['order_reference'] }}
            </Link>
            <span v-else>{{ item['order_reference'] ?? '-' }}</span>
        </template>

        <template #cell(org_stock_code)="{ item }">
            <span class="font-mono font-semibold">{{ item['org_stock_code'] }}</span>
        </template>

        <template #cell(org_stock_name)="{ item }">
            <span>{{ item['org_stock_name'] }}</span>
        </template>

        <template #cell(quantity_waiting_crm)="{ item }">
            <div class="flex flex-col gap-0.5">
                <span class="tabular-nums">
                    {{ item['quantity_waiting_crm'] }} {{ ctrans("items") }}
                </span>
                <span v-if="item['notes']" class="text-left border border-gray-300 bg-gray-100 px-2 py-1 rounded">
                    <div class="font-medium">
                        <FontAwesomeIcon icon="fal fa-sticky-note" class="" fixed-width aria-hidden="true" />
                        {{ ctrans("Notes") }} ({{ ctrans("may from Picker or other staff") }})
                    </div>
                    <div class="opacity-70 italic text-xs ">{{ item['notes'] }}</div>
                </span>
            </div>
        </template>
    </Table>
</template>
