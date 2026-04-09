<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 09 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTruck } from "@fal"
import { faHeadset } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { RouteParams } from "@/types/route-params"

library.add(faTruck, faHeadset)

defineProps<{
    data: TableTS
    tab?: string
}>()

const routeToDeliveryNote = (slug: string) => {
    return route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        slug,
    ])
}

const onCallCustomerService = (item: any) => {
    console.log('Call Customer Service', item)
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>
        <template #cell(delivery_note_reference)="{ item }">
            <Link :href="routeToDeliveryNote(item.delivery_note_slug)" class="primaryLink">
                <FontAwesomeIcon icon="fal fa-truck" class="opacity-60 mr-1" fixed-width aria-hidden="true" />
                {{ item.delivery_note_reference }}
            </Link>
        </template>

        <template #cell(items)="{ item }">
            <div v-if="item.items?.length" class="space-y-3 py-1">
                <div
                    v-for="deliveryItem in item.items"
                    :key="deliveryItem.id"
                    class="flex items-center justify-between gap-4 border-b border-gray-100 last:border-b-0 pb-2 last:pb-0"
                >
                    <div class="flex-1 min-w-0">
                        <span class="text-xs opacity-75 tabular-nums mr-1">({{ deliveryItem.org_stock_code }})</span>
                        <span>{{ deliveryItem.org_stock_name }}</span>
                    </div>
                    <div class="text-right tabular-nums text-sm shrink-0">
                        {{ deliveryItem.quantity_waiting }}
                    </div>
                    <div class="shrink-0">
                        <Button
                            @click="() => onCallCustomerService(deliveryItem)"
                            icon="fas fa-headset"
                            label="Call Customer Service"
                            size="xs"
                            type="tertiary"
                        />
                    </div>
                </div>
            </div>
            <span v-else class="text-gray-400 italic text-xs">{{ $t('No items') }}</span>
        </template>
    </Table>
</template>
