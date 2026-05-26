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
import { faHandHoldingBox, faDolly, faMapMarkerAlt, faHourglassStart } from "@fal"
import { faSkull, faCircle } from "@fas"
import { inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"
import NotesDisplay from "@/Components/NotesDisplay.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import PickingItemActionsPanel from "@/Components/Warehouse/DeliveryNotes/PickingItemActionsPanel.vue"
import LabelItemsWaitingForCrm from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForCrm.vue"

library.add(faHandHoldingBox, faDolly, faMapMarkerAlt, faHourglassStart, faSkull, faCircle)

const locale = inject('locale', aikuLocaleStructure)

defineProps<{
    data: TableTS
    tab?: string
    allowStockControllerSetNotPicked: boolean
    isStillPicking: boolean
    isReadOnly?: boolean
}>()

const routeToDeliveryNote = (slug: string) => {
    return route('grp.org.warehouses.show.dispatching.delivery_notes.show', [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        slug,
    ])
}

const routeItemsWaitingCrm = (item: any): string => {
    if (!item.shop_slug || !(route().params as RouteParams).organisation) {
        return '#'
    }
    return route('grp.org.shops.show.ordering.backlog.waiting_items', {
        organisation: (route().params as RouteParams).organisation,
        shop: item.shop_slug,
    })
}

const generateLocationRoute = (location: any) => {
    if (!location.location_slug) return "#"
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        location.location_slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>

        <!-- Column: Reference Delivery Note -->
        <template #cell(delivery_note_reference)="{ item }">
            <div class="flex gap-2 flex-wrap items-center">
                <Link :href="routeToDeliveryNote(item.delivery_note_slug)" class="primaryLink">
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
            <div class="flex gap-x-2 mt-1 flex-wrap">
                <span v-if="item.trolley_names" v-tooltip="trans('Trolley')" class="inline-flex items-center gap-x-1 text-xs text-gray-500 bg-gray-100 border rounded px-1.5 py-0.5">
                    <FontAwesomeIcon icon="fal fa-dolly-flatbed-alt" fixed-width aria-hidden="true" />
                    {{ item.trolley_names }}
                </span>
                <span v-if="item.picked_bay_codes" v-tooltip="trans('Picked Bay')" class="inline-flex items-center gap-x-1 text-xs text-gray-500 bg-gray-100 rounded px-1.5 py-0.5">
                    <FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width aria-hidden="true" />
                    {{ item.picked_bay_codes }}
                </span>
            </div>
        </template>

        <!-- Column: Code -->
        <template #cell(org_stock_code)="{ item }">
            <div class="min-w-40">
                <div class="font-bold">
                    {{ item.org_stock_code }}
                </div>
                <div class="opacity-75 text-justify">
                    {{ item.org_stock_name }}
                </div>
            </div>
        </template>

        <!-- Section: Pickings -->
        <template #cell(pickings)="{ item }">
            <div v-if="item.pickings?.length" class="space-y-1">
                <div v-for="picking in item.pickings" :key="picking.id" class="flex gap-x-2 w-fit">
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

                    <!-- <ButtonWithLink
                        v-tooltip="trans('Undo')" type="negative" size="xxs" icon="fal fa-undo-alt"
                        :routeTarget="picking.undo_picking_route"
                        :bindToLink="{ preserveScroll: true }"
                        @click="onUndoPick(picking.undo_picking_route, `undo-pick-${picking.id}`)"
                        :loading="get(isLoadingUndoPick, `undo-pick-${picking.id}`, false)"
                    /> -->
                </div>
            </div>
            <span v-else class="text-xs text-gray-400 italic">{{ trans('No item picked yet') }}</span>

            
            <!-- Section: items are waiting for warehouse -->
            <div v-if="Number(item.quantity_waiting_warehouse) > 0" class="mt-2 xmx-auto w-fit">
                <div v-tooltip="trans('Quantity of items waiting for warehouse')" class="border-l-2 border-yellow-400 relative bg-yellow-500/20 py-1 pr-2 pl-1 text-yellow-700 whitespace-nowrap w-fit">
                    <FontAwesomeIcon icon="fal fa-hourglass-start" class="mr opacity-70" fixed-width aria-hidden="true" />
                    <!-- <FractionDisplay v-if="item.quantity_picked_fractional"
                        :fractionData="item.quantity_picked_fractional" /> -->
                    <span>
                        {{ trans(":quantityWaitingWarehouse items are waiting for warehouse", { quantityWaitingWarehouse: Number(item.quantity_waiting_warehouse) }) }}
                    </span>

                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px]" fixed-width aria-hidden="true" />
                </div>
            </div>

            <!-- Section: items are waiting for CRM -->
            <div v-if="Number(item.quantity_waiting_crm) > 0" class="mt-2 xmx-auto w-fit">
                <Link :href="routeItemsWaitingCrm(item)" class="hover:underline">
                    <LabelItemsWaitingForCrm :qty_waiting_crm="Number(item.quantity_waiting_crm)" />
                </Link>
            </div>
        </template>

        <!-- Column: Actions (location picker + quantity + not-picked + button pass to CS) -->
        <template #cell(picking_position)="{ item: itemValue }">
            <div class="rounded p-1">
                <PickingItemActionsPanel
                    v-if="!isReadOnly"
                    :item="itemValue"
                    :isStillPicking="isStillPicking"
                    :allowStockControllerSetNotPicked="allowStockControllerSetNotPicked"
                />
            </div>
        </template>
    </Table>
</template>
