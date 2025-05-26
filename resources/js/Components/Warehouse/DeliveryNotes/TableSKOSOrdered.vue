<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 13 September 2024 11:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Order } from "@/types/order"
import type { Links, Meta, Table as TableTS } from "@/types/Table"
import Icon from "@/Components/Icon.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { debounce, get, set } from "lodash"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { Collapse } from "vue-collapsed"
import { ref } from "vue"
import { onMounted } from "vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowDown } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faArrowDown)


defineProps<{
    data: TableTS
    tab?: string
}>()


function deliveryNoteRoute(deliveryNote: Order) {
    // console.log(route().current())
    switch (route().current()) {
        case "grp.org.warehouses.show.dispatching.delivery-notes.show":
            // return route(
            //     "grp.org.shops.show.discounts.campaigns.show",
            //     [route().params["organisation"], , route().params["shop"], route().params["customer"], deliveryNote.slug])
        default:
            return ''
    }
}

const isMounted = ref(false)
onMounted(() => {
    isMounted.value = true
})

const onPickingQuantity = (pick_route: routeType, quantity: number) => {
    router[pick_route.method || 'post'](
        route(pick_route.name, pick_route.parameters),
        {
            quantity: quantity,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: "",
                    type: "error",
                })
            },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: "",
                    type: "error",
                })
            }
        }
    )
}
const debounceOnPickingQuantity = debounce(onPickingQuantity, 500)


const generateLocationRoute = (location: any) => {
    if (!location.code) {
        return '#'
    }

    if (route().current() === 'grp.org.warehouses.show.dispatching.delivery-notes.show') {
        return route(
            'grp.org.warehouses.show.infrastructure.locations.show',
            [
                route().params['organisation'],
                route().params['warehouse'],
                location.code
            ]
        )
    } else {
        return '#'
    }

}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>

        <!-- Column: state -->
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <!-- Column: Reference -->
        <template #cell(org_stock_code)="{ item: deliveryNote }">
            <Link :href="deliveryNoteRoute(deliveryNote)" class="primaryLink">
                {{ deliveryNote.org_stock_code }}
            </Link>
            <pre>{{ deliveryNote.pickings }}</pre>
        </template>

        <!-- Column: Reference -->
        <template #cell(quantity_required)="{ item }">
            {{ item.quantity_required }}
        </template>

        <!-- Column: Reference -->
        <template #cell(quantity_picked)="{ item: itemValue, proxyItem }">
            <div class="text-left">
                <!-- <pre>{{ proxyItem }}</pre> -->
    
                <!-- <NumberWithButtonSave
                    :modelValue="get(proxyItem, 'quantity_required', 1)"
                    :bindToTarget="{ min: 1 }"
                    @update:modelValue="(e: number) => (set(proxyItem, 'quantity_picked', e), debounceOnPickingQuantity(proxyItem.picking_route, e))"
                    noUndoButton
                    noSaveButton
                    parentClass="w-min"
                /> -->
    
                <template v-for="(location, locIndex) in itemValue.locations" :key="location.id">
                    <!-- <pre>{{ location }}</pre> -->
                    <Teleport v-if="isMounted" :to="`#row-${itemValue.id}`" :disabled="location.quantity > 0">
                        <div class="rounded p-1 flex justify-between gap-x-6 items-center even:bg-indigo-50">
                            <!-- <Tag :label="pallet_stored_item.reference" stringToColor>
                                <template #label>
                                    <div class="">
                                        {{ pallet_stored_item.reference }} ({{ pallet_stored_item.quantity }})
                                    </div>
                                </template>
                            </Tag> -->
    
                            <!-- Location code -->
                            <div class="">
                                <span v-if="location.code">
                                    <Link :href="generateLocationRoute(location)" class="secondaryLink">
                                        {{ location.code }} <span v-tooltip="trans('Quantity in this location')" class="text-gray-400">({{ location.quantity }})</span>
                                    </Link>
                                </span>
                                <span v-else class="text-gray-400 italic">({{ trans('No location code') }})</span>
    
                                <!-- <span v-if="pallet_stored_item.location?.code" v-tooltip="trans('Location code of the pallet')" class="text-gray-400"> [{{ pallet_stored_item.location?.code }}]</span>
                                <div  v-if="pallet_stored_item.selected_quantity && palletReturn.state === 'in_process'" v-tooltip="trans('Will be picked')" class="pl-1 pb-1 inline" >
                                    <FontAwesomeIcon icon='fas fa-circle' class='text-[7px] text-blue-500 animate-pulse' fixed-width aria-hidden='true' />
                                </div> -->
    
                                <!-- <div v-if="palletReturn.state === 'picking'"
                                    @xxclick="() => pallet_stored_item.picked_quantity = pallet_stored_item.quantity_in_pallet"
                                    v-tooltip="trans('Total Customer\'s SKU in this pallet')"
                                    class="text-gray-400 tabular-nums xcursor-pointer xhover:text-gray-600">
                                    {{ trans("Stocks in pallet") }}: {{ pallet_stored_item.quantity_in_pallet }}
                                </div> -->
                            </div>
    
                            <div class="flex items-center flex-nowrap gap-x-2">
                                <!-- {{ state === 'picked' || state === 'dispatched' }} -->
                                <!-- <ModalConfirmation
                                    v-if="pallet_stored_item.all_items_returned && (state === 'picked' || state === 'dispatched') && !pallet_stored_item.is_pallet_returned"
                                    :routeYes="{
                                        name: 'grp.models.pallet.return',
                                        parameters: {
                                            pallet: pallet_stored_item.pallet_id
                                        },
                                        method: 'patch'
                                    }"
                                    :title="trans(`Return pallet ${pallet_stored_item.reference} to customer?`)"
                                    :description="trans(`The pallet ${pallet_stored_item.reference} will be set as returned to the customer, and no longer exist in warehouse. This action cannot be reverse.`)"
                                >
                                    <template #default="{ changeModel }">
                                        <Button
                                            @click="() => changeModel()"
                                            :label="trans('Return pallet')"
                                            size="xs"
                                        />
                                    </template>
    
                                    <template #btn-yes="{ isLoadingdelete, clickYes}">
                                        <Button
                                            :loading="isLoadingdelete"
                                            @click="() => clickYes()"
                                            :label="trans('Yes, return the pallet')"
                                        />
                                    </template>
                                </ModalConfirmation> -->
    
                                <!-- <Tag
                                    v-if="pallet_stored_item.is_pallet_returned"
                                    v-tooltip="trans('Pallet was returned to customer')"
                                    :label="trans('Pallet returned')"
                                    :theme="8"
                                    size="xs"
                                    noHoverColor
                                /> -->
    
                                <!-- <div v-if="palletReturn.state === 'in_process'" v-tooltip="trans('Available quantity')" class="text-base">
                                    {{ pallet_stored_item.available_quantity }}
                                </div> -->
                                <!-- <div v-else-if="palletReturn.state === 'picking'" v-tooltip="trans('Quantity of Customer\'s SKU that should be picked')" class="text-base">{{ pallet_stored_item.selected_quantity }}</div> -->
    
                                <!-- Button: input number (in_process) -->
                                <!-- <NumberWithButtonSave
                                    v-if="palletReturn.state === 'in_process'"
                                    key="in_process"
                                    noUndoButton
                                    isUseAxios
                                    @onSuccess="(newVal: number, oldVal: number) => {
                                        proxyItem.total_quantity_ordered += newVal - oldVal
                                        pallet_stored_item.selected_quantity = newVal
                                        emits('isStoredItemAdded', newVal > 0 ? true : false)
                                        router.reload({
                                            only: ['pageHead'],
                                        })
                                    }"
                                    :modelValue="pallet_stored_item.selected_quantity"
                                    saveOnForm
                                    :routeSubmit="{
                                        name: pallet_stored_item.syncRoute.name,
                                        parameters: {
                                            ...pallet_stored_item.syncRoute.parameters,
                                            palletReturn: palletReturn.id
                                        },
                                        method: pallet_stored_item.syncRoute.method
                                    }"
                                    keySubmit="quantity_ordered"
                                    :bindToTarget="{
                                        step: 1,
                                        min: 0,
                                        max: pallet_stored_item.max_quantity
                                    }"
                                >
                                </NumberWithButtonSave>
    
                                <div v-else-if="palletReturn.state === 'submitted' || palletReturn.state === 'confirmed'" class="flex flex-nowrap gap-x-1 items-center">
                                    {{ locale.number(pallet_stored_item.selected_quantity) }}
                                </div> -->
    
                                <!-- Button: input number (picking) -->
                                <template v-if="true" xxv-else-if="palletReturn.state === 'picking' && pallet_stored_item.state !== 'picked'">
                                    <div class="">
                                        <!-- Not isUseAxios due timeline state is not auto updated -->
                                        <NumberWithButtonSave
                                            v-if="location.quantity > 0"
                                            key="pickingpicked"
                                            noUndoButton
                                            xisUseAxios
                                            axonSuccess="(newVal: number, oldVal: number) => {
                                                pallet_stored_item.state = 'picked',
                                                pallet_stored_item.picked_quantity = newVal
                                            }"
                                            @onError="(error: any) => {
                                                proxyItem.errors = Object.values(error || {})
                                            }"
                                            :modelValue="get(itemValue, ['pickings', location.id, 'quantity_picked'], 0)"
                                            @update:modelValue="() => proxyItem.errors = null"
                                            saveOnForm
                                            :routeSubmit=" itemValue.picking_route "
                                            xxkeySubmit="
                                                pallet_stored_item.pallet_return_item_id
                                                    ? 'quantity_picked'
                                                    : 'quantity_ordered'
                                            "
                                            :bindToTarget="{
                                                step: 1,
                                                min: 0,
                                                max: Math.min(location.quantity, itemValue.quantity_required)
                                            }"
                                            :additionalData="{
                                                location_id: location.id,
                                            }"
                                            xxcolorTheme="
                                                pallet_stored_item.selected_quantity == pallet_stored_item.picked_quantity
                                                    ? '#374151'
                                                    : pallet_stored_item.selected_quantity < pallet_stored_item.picked_quantity
                                                        ? '#22c55e'
                                                        : '#ff0000'
                                            "
                                            xxparentClass="''
                                                // pallet_stored_item.error ? 'errorShake' : ''
                                            "
                                        >
                                            <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                                <Button
                                                    v-if="true"
                                                    @click="() => (
                                                        // pallet_stored_item.error = null,  // make slow a little bit
                                                        onSaveViaForm()
                                                    )"
                                                    icon="fal fa-save"
                                                    :disabled="itemValue.quantity_required == itemValue.quantity_picked"
                                                    :label="trans('pick')"
                                                    size="xs"
                                                    :xdisabled="!isDirty"
                                                    type="secondary"
                                                    :loading="isProcessing"
                                                    class="py-0"
                                                />
                                            </template>
                                        </NumberWithButtonSave>
                                        <div v-else class="text-xs text-gray-500 italic text-center">
                                            No quantity in this location
                                        </div>

                                        <!-- Section: Errors list -->
                                        <div v-if="proxyItem.errors?.length">
                                            <p v-for="error in proxyItem.errors" class="text-xs text-red-500 italic">*{{ error }}</p>
                                        </div>
                                    </div>
                                </template>
    
                                <!-- <div v-else class="flex flex-nowrap gap-x-1 items-center tabular-nums">
                                    <Button
                                        v-if="palletReturn.state == 'picking' && pallet_stored_item.state == 'picked'"
                                        @click="() => onUndoPick(pallet_stored_item.undoRoute, pallet_stored_item, `row${value.rowIndex}.id${pallet_stored_item.id}`)"
                                        icon="fal fa-undo-alt"
                                        :label="trans('Undo pick')"
                                        size="xs"
                                        :loading="get(isLoadingUndoPick, [`row${value.rowIndex}`, `id${pallet_stored_item.id}`], false)"
                                        type="tertiary"
                                    />
                                    {{ locale.number(pallet_stored_item.picked_quantity) }}/{{ locale.number(pallet_stored_item.selected_quantity) }}
                                    <FontAwesomeIcon v-if="pallet_stored_item.state == 'picked'" v-tooltip="trans('Picked')" icon='fal fa-check' class='text-green-500' fixed-width aria-hidden='true' />
                                </div> -->
    
    
                            </div>
                            <!-- {{ get(isLoadingUndoPick, [`row${value.rowIndex}.id${pallet_stored_item.id}`], '000') }} --  -->
                            <!-- {{ pallet_stored_item.isLoadingUndo }} -->
    
                        </div>
                    </Teleport>
                </template>
    
                <div v-if="!itemValue.locations.every(location => {return location.quantity > 0})" class="">
                    <Collapse as="section" :when="get(proxyItem, ['is_open_collapsed'], false)" class="">
                        <div :id="`row-${itemValue.id}`">
                            <!-- Something will teleport here -->
                        </div>
                    </Collapse>

                    <div class="w-full mt-2">
                        <Button
                            v-if="!itemValue.locations.every(location => {return location.quantity > 0})"
                            @click="() => set(proxyItem, ['is_open_collapsed'], !get(proxyItem, ['is_open_collapsed'], false))"
                            type="dashed"
                            full
                            size="sm"
                        >
                            <div class="py-1 text-gray-500">
                                <FontAwesomeIcon icon='fal fa-arrow-down' class="transition-all" :class="get(proxyItem, ['is_open_collapsed'], false) ? 'rotate-180' : ''" fixed-width aria-hidden='true' />
                                {{ get(proxyItem, ['is_open_collapsed'], false) ? trans('Close') : trans('Open hidden locations') }}
                            </div>
                        </Button>
                    </div>
                </div>
            </div>
        </template>


        <template #cell(action)="{ item: deliveryNote }">
            <!-- <pre>
                {{ data.data[0].pickings }}
            </pre> -->
        </template>

    </Table>
</template>
