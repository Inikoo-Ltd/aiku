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
import { faTruck, faHandHoldingBox, faHourglassStart, faDolly } from "@fal"
import { faSkull, faCircle } from "@fas"
import { inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"
import NotesDisplay from "@/Components/NotesDisplay.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import LabelItemsWaitingForWarehouse from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForWarehouse.vue"
import LabelItemsWaitingForCrm from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForCrm.vue"
import PickingItemActionsPanel from "@/Components/Warehouse/DeliveryNotes/PickingItemActionsPanel.vue"
import WaitingOppositeCountBadge from "@/Components/Warehouse/DeliveryNotes/WaitingOppositeCountBadge.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import TrolleyChipsManager from "@/Components/Warehouse/DeliveryNotes/TrolleyChipsManager.vue"

library.add(faTruck, faHandHoldingBox, faHourglassStart, faDolly, faSkull, faCircle)

const locale = inject("locale", aikuLocaleStructure)

const props = defineProps<{
    data: TableTS
    tab?: string
    allowStockControllerSetNotPicked: boolean
    isStillPicking: boolean
    isReadOnly?: boolean
    waitingType?: string
    highlightDeliveryNoteSlug?: string
}>()

const rowHighlightClass = (item: any) => {
    if (props.highlightDeliveryNoteSlug && item.delivery_note_slug === props.highlightDeliveryNoteSlug) {
        return 'bg-amber-100 hover:bg-amber-200/70'
    }
    return ''
}

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

/*
 * A part of an outer can be left waiting by any shop now that ecom replaces a product in part, not
 * only by dropshipping, so what decides the fraction is the pack the item comes in. An item packed
 * individually has nothing to cut and reads as a plain count.
 */
const getWaitingWarehouseFractional = (deliveryItem: any) => {
    if (Number(deliveryItem?.packed_in) > 1) {
        return deliveryItem?.quantity_waiting_warehouse_fractional_ds
    }
    return null
}

const getWaitingCrmFractional = (deliveryItem: any) => {
    if (Number(deliveryItem?.packed_in) > 1) {
        return deliveryItem?.quantity_waiting_crm_fractional_ds
    }
    return null
}
</script>

<template>


    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop :rowColorFunction="rowHighlightClass">
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
                <WaitingOppositeCountBadge
                    v-if="props.waitingType && Number(item.opposite_waiting_count) > 0"
                    :count="Number(item.opposite_waiting_count)"
                    :type="props.waitingType === 'crm' ? 'warehouse' : 'crm'"
                    :href="routeToDeliveryNote(item.delivery_note_slug)"
                />
            </div>
            <TrolleyChipsManager
                class="mt-1"
                :deliveryNote="{
                    id: item.delivery_note_id,
                    slug: item.delivery_note_slug,
                    reference: item.delivery_note_reference,
                }"
                :trolleys="item.trolleys"
                :isEditable="!isReadOnly"
            />
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
                                    <span v-tooltip="trans('Total picked in this location')" class="inline-flex items-center gap-x-1 text-gray-500 whitespace-nowrap text-xs">
                                            <FontAwesomeIcon icon="fal fa-hand-holding-box" fixed-width aria-hidden="true" />
                                            <FractionDisplay v-if="picking.quantity_picked_fractional" :fractionData="picking.quantity_picked_fractional" />
                                            <template v-else>{{ picking.quantity_picked }}</template>
                                        </span>
                                </div>
                                <div v-if="picking.type === 'not-pick'" v-tooltip="trans('Quantity not gonna be picked')" class="inline-flex items-center gap-x-1 text-red-500 text-xs">
                                    <FontAwesomeIcon icon="fas fa-skull" fixed-width aria-hidden="true" />
                                    <FractionDisplay v-if="picking.quantity_picked_fractional" :fractionData="picking.quantity_picked_fractional" />
                                    <template v-else>{{ picking.quantity_picked }}</template>
                                </div>
                            </li>
                        </ol>

                        <!-- Section: Waiting for CRM -->
                        <div class="flex gap-x-4">
                            <LabelItemsWaitingForCrm v-if="Number(deliveryItem.quantity_waiting_crm) > 0" :qty_waiting_crm="Number(deliveryItem.quantity_waiting_crm)" :fractionData="getWaitingCrmFractional(deliveryItem)" />
                        </div>

                        <!-- Section: Waiting for warehouse -->
                        <div v-if="Number(deliveryItem.quantity_waiting_warehouse) > 0" class="flex gap-x-4 mt-2 items-center w-full">
                            <LabelItemsWaitingForWarehouse :qty_waiting_warehouse="Number(deliveryItem.quantity_waiting_warehouse)" :fractionData="getWaitingWarehouseFractional(deliveryItem)" />
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

