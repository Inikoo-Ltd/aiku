<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 22 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHandHoldingBox, faHourglassStart, faTruck } from "@fal"
import { faSkull, faCircle } from "@fas"
import { inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import NotesDisplay from "@/Components/NotesDisplay.vue"
import LabelItemsWaitingForWarehouse from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForWarehouse.vue"
import LabelItemsWaitingForCrm from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForCrm.vue"
import PickingItemActionsPanel from "@/Components/Warehouse/DeliveryNotes/PickingItemActionsPanel.vue"

library.add(faHandHoldingBox, faHourglassStart, faTruck, faSkull, faCircle)

const locale = inject("locale", aikuLocaleStructure)

defineProps<{
    data: TableTS
    tab?: string
    allowStockControllerSetNotPicked: boolean
    isStillPicking: boolean
}>()

const routeToDeliveryNote = (slug: string) => {
    return route("grp.org.warehouses.show.dispatching.delivery_notes.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>

        <!-- Column: Item (org_stock_code + name) -->
        <template #cell(org_stock_code)="{ item }">
            <div class="min-w-40">
                <div class="font-bold tabular-nums">{{ item.org_stock_code }}</div>
                <div class="opacity-75 text-sm">{{ item.org_stock_name }}</div>
                <div v-if="item.warehouse_area" class="text-xs text-gray-400 mt-0.5">{{ item.warehouse_area }}</div>
            </div>
        </template>

        <!-- Column: Delivery notes with picking actions per item -->
        <template #cell(delivery_notes)="{ item: stockRow }">
            <div v-if="stockRow.delivery_notes?.length" class="divide-y divide-dashed divide-gray-300">
                <div
                    v-for="deliveryItem in stockRow.delivery_notes"
                    :key="deliveryItem.id"
                    class="py-3 first:pt-1"
                >
                    <div class="flex flex-col gap-y-1">
                        <!-- Delivery note reference + notes -->
                        <div class="flex gap-x-2 flex-wrap items-center">
                            <Link :href="routeToDeliveryNote(deliveryItem.delivery_note_slug)" class="primaryLink">
                                <FontAwesomeIcon icon="fal fa-truck" class="opacity-60 mr-1" fixed-width aria-hidden="true" />
                                {{ deliveryItem.delivery_note_reference }}
                            </Link>
                            <FontAwesomeIcon
                                v-if="deliveryItem.delivery_note_is_premium_dispatch"
                                v-tooltip="trans('Priority dispatch')"
                                icon="fas fa-star"
                                class="text-yellow-500 animate-bounce"
                                fixed-width
                                aria-hidden="true"
                            />
                            <FontAwesomeIcon
                                v-if="deliveryItem.delivery_note_has_extra_packing"
                                v-tooltip="trans('Extra packing')"
                                icon="fas fa-box-heart"
                                class="text-yellow-500 animate-bounce"
                                fixed-width
                                aria-hidden="true"
                            />
                            <NotesDisplay
                                reference-field="delivery_note_reference"
                                :item="deliveryItem"
                                :note-fields="{
                                    shipping: 'delivery_note_shipping_notes',
                                    customer: 'delivery_note_customer_notes',
                                    internal: 'delivery_note_internal_notes',
                                    public:   'delivery_note_public_notes',
                                }"
                            />
                        </div>

                        <!-- Pickings list -->
                        <ol v-if="deliveryItem.pickings?.length" class="space-y-1 list-disc ml-4">
                            <li v-for="picking in deliveryItem.pickings" :key="picking.id" class="flex gap-x-2 w-fit">
                                <div v-if="picking.type === 'pick'" class="flex gap-x-2 items-center">
                                    <span class="text-xs">{{ picking.location_code }}</span>
                                    <span v-tooltip="trans('Total picked in this location')" class="text-gray-500 whitespace-nowrap text-xs">
                                        <FontAwesomeIcon icon="fal fa-hand-holding-box" fixed-width aria-hidden="true" />
                                        {{ picking.quantity_picked }}
                                    </span>
                                </div>

                                <div v-if="picking.type === 'not-pick'" v-tooltip="trans('Quantity not gonna be picked')" class="text-red-500 text-xs">
                                    <FontAwesomeIcon icon="fas fa-skull" fixed-width aria-hidden="true" />
                                    {{ picking.quantity_picked }}
                                </div>
                            </li>
                        </ol>

                        <!-- Waiting labels + picking actions -->
                        <div class="flex gap-x-4">
                            <LabelItemsWaitingForCrm v-if="Number(deliveryItem.quantity_waiting_crm) > 0" :qty_waiting_crm="Number(deliveryItem.quantity_waiting_crm)" />
                        </div>

                        <!-- Actions: picking engine -->
                        <div v-if="Number(deliveryItem.quantity_waiting_warehouse) > 0" class="flex gap-x-4 items-center w-full">
                            <LabelItemsWaitingForWarehouse :qty_waiting_warehouse="Number(deliveryItem.quantity_waiting_warehouse)" />
                            <span v-if="deliveryItem.trolley_names" v-tooltip="trans('Trolley')" class="inline-flex items-center gap-x-1 text-xs text-gray-500 bg-gray-100 border rounded px-1.5 py-0.5">
                                <FontAwesomeIcon icon="fal fa-dolly-flatbed-alt" fixed-width aria-hidden="true" />
                                {{ deliveryItem.trolley_names }}
                            </span>
                            <span class="ml-8 mr-4 whitespace-nowrap">--></span>
                            <div class="flex justify-end w-full">
                                <PickingItemActionsPanel
                                    :item="deliveryItem"
                                    :isStillPicking="isStillPicking"
                                    :allowStockControllerSetNotPicked="allowStockControllerSetNotPicked"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <span v-else class="text-gray-400 italic text-xs">{{ trans("No items") }}</span>
        </template>
    </Table>
</template>
