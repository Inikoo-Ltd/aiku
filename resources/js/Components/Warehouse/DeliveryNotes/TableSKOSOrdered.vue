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
import { onMounted, reactive } from "vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowDown } from "@fal"
import { faSkull } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import axios from "axios"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
library.add(faArrowDown, faSkull)


defineProps<{
    data: TableTS
    tab?: string
    state: string
}>()

const locale = inject('locale', aikuLocaleStructure)

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


// Button: undo pick
const isLoadingUndoPick = reactive({})
const onUndoPick = async (routeTarget: routeType, pallet_stored_item: any, loadingKey: string) => {
    try {
        pallet_stored_item.isLoadingUndo = true
        set(isLoadingUndoPick, loadingKey, true)
        await axios[routeTarget.method || 'get'](
            route(routeTarget.name, routeTarget.parameters)
        )
        pallet_stored_item.state = 'picking'
        // console.log('qqqqq', pallet_stored_item)
    } catch (error) {
        console.error('hehehe', error)
        
    } finally {
        set(isLoadingUndoPick, loadingKey, false)
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
            <!-- <pre>{{ deliveryNote }}</pre> -->
        </template>

        <!-- Column: Reference -->
        <template #cell(quantity_required)="{ item }">
            {{ locale.number(item.quantity_required) }}
        </template>

        <!-- Column: Reference -->
        <template #cell(quantity_picked)="{ item: itemValue, proxyItem }">
            <div v-if="state === 'handling'" class="text-left">
                <template v-for="(location, locIndex) in itemValue.locations" :key="location.location.id">
                    <Teleport v-if="isMounted" :to="`#row-${itemValue.id}`" :disabled="location.quantity > 0">
                        <div class="rounded p-1 flex justify-between gap-x-6 items-center even:bg-black/5">
    
                            <!-- Location code -->
                            <div class="">
                                <span v-if="location.code" class="block">
                                    <Link :href="generateLocationRoute(location)" class="secondaryLink">
                                        {{ location.code }}
                                    </Link>
                                    <span v-if="get(itemValue, ['pickings', location.location.id, 'quantity_picked'], 0)" v-tooltip="trans('Will be picked')" class="" >
                                        <FontAwesomeIcon icon='fas fa-circle' class='text-[7px] mb-0.5 text-blue-500 animate-pulse' fixed-width aria-hidden='true' />
                                    </span>
                                </span>
                                <span v-else class="text-gray-400 italic">({{ trans('No location code') }})</span>                                
    
                                <div xxv-if="palletReturn.state === 'picking'"
                                    axxclick="() => pallet_stored_item.picked_quantity = pallet_stored_item.quantity_in_pallet"
                                    v-tooltip="trans('Total stock in this location')"
                                    class="text-gray-400 tabular-nums text-xs xcursor-pointer xhover:text-gray-600">
                                    {{ trans("Stocks in location") }}: {{ location.quantity }}
                                </div>
                            </div>
    
                            <div class="flex items-center flex-nowrap gap-x-2">
    
                                <!-- Button: input number (picking) -->
                                <template v-if="true" xxv-else-if="palletReturn.state === 'picking' && pallet_stored_item.state !== 'picked'">
                                    <div class="">
                                        <!-- Not isUseAxios due timeline state is not auto updated -->
                                        <!-- {{ get(itemValue, ['pickings', location.location.id, 'quantity_picked'], 0) }} -->
                                        <!-- <pre>{{ itemValue.pickings[location.location.id] }}</pre> -->
                                        <NumberWithButtonSave
                                            v-if="location.quantity > 0"
                                            key="pickingpicked"
                                            noUndoButton
                                            @onError="(error: any) => {
                                                proxyItem.errors = Object.values(error || {})
                                            }"
                                            :modelValue="get(itemValue, ['pickings', location.location.id, 'quantity_picked'], 0)"
                                            @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                            saveOnForm
                                            :routeSubmit="get(itemValue, ['pickings', location.location.id, 'quantity_picked'], 0) > 0 ? get(itemValue, ['pickings', location.location.id, 'update_route'], 0) : itemValue.picking_route"                                            
                                            :bindToTarget="{
                                                step: 1,
                                                min: 0,
                                                max: Math.min(location.quantity, itemValue.quantity_required, itemValue.quantity_to_pick)
                                            }"
                                            :additionalData="{
                                                location_id: location.location.id,
                                            }"
                                            autoSave
                                            isWithRefreshModel
                                            :readonly="itemValue.is_completed || itemValue.quantity_required == itemValue.quantity_picked"
                                        >
                                            <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                                <!-- <Button
                                                    @click="() => (
                                                        // pallet_stored_item.error = null,  // make slow a little bit
                                                        onSaveViaForm()
                                                    )"
                                                    icon="fal fa-save"
                                                    :disabled="itemValue.quantity_required == itemValue.quantity_picked"
                                                    :label="trans('Pick all')"
                                                    size="xs"
                                                    :xdisabled="!isDirty"
                                                    type="secondary"
                                                    :loading="isProcessing"
                                                    class="py-0"
                                                /> -->
                                                <ButtonWithLink
                                                    v-tooltip="trans('Pick all required quantity in this location')"
                                                    icon="fal fa-save"
                                                    :disabled="itemValue.is_completed || itemValue.quantity_required == itemValue.quantity_picked"
                                                    :label="trans('Pick all')"
                                                    size="xs"
                                                    type="secondary"
                                                    :loading="isProcessing"
                                                    class="py-0"
                                                    :routeTarget="itemValue.picking_all_route"
                                                    :bind-to-link="{
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                    }"
                                                    :body="{
                                                        location_id: location.location.id
                                                    }"
                                                    isWithError
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
                            </div>
                        </div>
                    </Teleport>
                </template>
    
                <!-- Section: Open hidden locations -->
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

            <div v-else>
                {{ itemValue.quantity_picked }}
            </div>
        </template>


        <template #cell(quantity_to_pick)="{ item: deliveryNote }">
            <template v-if="state === 'handling'">
                <div v-if="!deliveryNote.is_completed || deliveryNote.quantity_not_picked === 0" class="whitespace-nowrap space-x-2">
                    <span class="mr-0.5">{{ deliveryNote.quantity_to_pick }}</span>
                    <ButtonWithLink
                        v-if="!deliveryNote.is_completed"
                        type="negative"
                        label="Set not picked"
                        icon="fas fa-skull"
                        size="xs"
                        :routeTarget="deliveryNote.not_picking_route"
                        :bindToLink="{
                            preserveScroll: true,
                        }"
                    />
                </div>
                <div v-else v-tooltip="trans('Quantity not gonna be picked')" class="text-red-500 w-fit ml-auto">
                    <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                    {{ deliveryNote.quantity_not_picked }}
                </div>
            </template>

            <div v-else>
                {{ deliveryNote.quantity_to_pick }}
            </div>

        </template>

        <template #cell(action)="{ item: deliveryNote }">
            <template v-if="deliveryNote.is_completed && (state === 'handling' || state === 'handling_blocked')">
                <ButtonWithLink
                    v-if="!deliveryNote.is_packed"
                    :routeTarget="deliveryNote.packing_route"
                    :bindToLink="{
                        preserveScroll: true,
                        preserveState: true,
                    }"
                    type="secondary"
                    size="xs"
                    :label="trans('Pack')"
                />

                <div v-else class="whitespace-nowrap text-green-600">
                    <FontAwesomeIcon icon="fal fa-check" class="" fixed-width aria-hidden="true" />
                    {{ trans("Packed") }}
                </div>
            </template>
        </template>
    </Table>
</template>
