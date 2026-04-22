<!--
* Author: Vika Aqordi
* Created on: 2026-04-22 09:25
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTruck, faHandHoldingBox, faHourglassStart } from "@fal"
import { faSkull, faCircle } from "@fas"
import { inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"
import NotesDisplay from "@/Components/NotesDisplay.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import LabelItemsWaitingForWarehouse from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForWarehouse.vue"
import LabelItemsWaitingForCrm from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForCrm.vue"
import PickingItemActionsPanel from "@/Components/Warehouse/DeliveryNotes/PickingItemActionsPanel.vue"

library.add(faTruck, faHandHoldingBox, faHourglassStart, faSkull, faCircle)

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
        slug
    ])
}

const generateLocationRoute = (location: any) => {
    if (!location.location_slug) return "#"
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        location.location_slug
    ])
}
</script>

<template>


    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>
        <template #cell(delivery_note_reference)="{ item }">
            <div class="flex gap-2 flex-wrap items-center">
                <Link :href="routeToDeliveryNote(item.delivery_note_slug)" class="primaryLink">
                    <FontAwesomeIcon icon="fal fa-truck" class="opacity-60 mr-1" fixed-width aria-hidden="true" />
                    {{ item.delivery_note_reference }}
                </Link>
                <FontAwesomeIcon v-if="item.delivery_note_is_premium_dispatch" v-tooltip="trans('Priority dispatch')" icon="fas fa-star" class="text-yellow-500 animate-bounce" fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-if="item.delivery_note_has_extra_packing" v-tooltip="trans('Extra packing')" icon="fas fa-box-heart" class="text-yellow-500 animate-bounce" fixed-width aria-hidden="true" />
                <NotesDisplay reference-field="delivery_note_reference" :item="item" :note-fields="{
                        shipping: 'delivery_note_shipping_notes',
                        customer: 'delivery_note_customer_notes',
                        internal: 'delivery_note_internal_notes',
                        public:   'delivery_note_public_notes',
                    }" />
            </div>
        </template>

        <template #cell(items)="{ item: deliveryNoteRow }">
            <div v-if="deliveryNoteRow.items?.length" class="divide-y divide-gray-100">
                <div
                    v-for="deliveryItem in deliveryNoteRow.items"
                    :key="deliveryItem.id"
                    class="py-3 first:pt-1"
                >
                    <div class="flex flex-col">
                        <!-- Section: Stock name + code -->
                        <div class="flex-1 min-w-0">
                            <span class="text-xs font-bold tabular-nums mr-1">{{ deliveryItem.org_stock_code }}</span>
                            <span>{{ deliveryItem.org_stock_name }}</span>
                            <span v-if="deliveryItem.packed_in_message" class="text-xs italic opacity-70 ml-1">{{ deliveryItem.packed_in_message }}</span>
                        </div>

                        <!-- List: pickings -->
                        <ol v-if="deliveryItem.pickings?.length" class="mt-1 space-y-1 list-disc">
                            <li v-for="picking in deliveryItem.pickings" :key="picking.id" class=" flex gap-x-2 w-fit">
                                <div v-if="picking.type === 'pick'" class="flex gap-x-2 items-center">
                                    <Link :href="generateLocationRoute(picking)" class="secondaryLink text-xs">{{ picking.location_code }}</Link>
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

                        <!-- Section: Waiting for CRM -->
                        <div class="flex gap-x-4">
                            <LabelItemsWaitingForCrm v-if="Number(deliveryItem.quantity_waiting_crm) > 0" :qty_waiting_crm="Number(deliveryItem.quantity_waiting_crm)" />
                        </div>

                        <!-- Section: Waiting for warehouse -->
                        <div v-if="Number(deliveryItem.quantity_waiting_warehouse) > 0" class="flex gap-x-4 mt-2 items-center w-full">
                            <LabelItemsWaitingForWarehouse :qty_waiting_warehouse="Number(deliveryItem.quantity_waiting_warehouse)" />
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

