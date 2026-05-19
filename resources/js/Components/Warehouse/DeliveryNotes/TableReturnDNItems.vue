<!--
* Author: Vika Aqordi
* Created on: 2026-05-04 11:57
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import Icon from "@/Components/Icon.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import { debounce, get, set } from 'lodash-es'
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { ref, onMounted, reactive, inject, computed, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faHourglassHalf, faUndo, faBox, faBarcode, faCheckCircle } from "@fal"
import { faFragile, faGhost, faSkull, faWandMagic } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Modal from "@/Components/Utils/Modal.vue"
import { RadioButton } from "primevue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import ExpiryDateLabel from "@/Components/Utils/Label/ExpiryDateLabel.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import axios from "axios"
import Image from "@/Components/Image.vue"
import LabelItemsWaitingForWarehouse from "./LabelItemsWaitingForWarehouse.vue"
import LabelItemsWaitingForCrm from "./LabelItemsWaitingForCrm.vue"
import LoadingOverlay2 from "@/Components/Utils/LoadingOverlay2.vue"
import { ctrans } from "@/Composables/useTrans"
import LabelPickingLocation from "./LabelPickingLocation.vue"
import PickingLocationModal from "./PickingLocationModal.vue"
import SelectPickingLocation from "./SelectPickingLocation.vue"
library.add(faSkull, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faHourglassHalf, faWandMagic, faBox, faBarcode, faCheckCircle)


const props = defineProps<{
    data: TableTS
    tab?: string
    state: string
    shop_type: string
    allowWaiting: boolean
    allowPickerSetNotPicked: boolean
    isEditable: boolean
}>()

const emit = defineEmits<{
    'update:quantity-to-resend': [itemId: string | number, value: number]
    'validation-error': [itemId: string | number, hasError: boolean]
}>()

const screenType = inject('screenType', ref('desktop'))

const locale = inject("locale", aikuLocaleStructure)
const layout = inject('layout', layoutStructure)

function orgStockRoute(deliveryNoteItem: DeliverNoteItem) {
    if (!deliveryNoteItem.org_stock_id) {
        return ''
    }

    return route(
        "grp.helpers.redirect_org_stock",
        [deliveryNoteItem.org_stock_id])

}


const isMounted = ref(false)
onMounted(() => {
    isMounted.value = true
})

const generateLocationRoute = (location: any) => {
    if (!location.location_slug || !(route().params["organisation"]) || !(route().params["warehouse"])) {
        return ""
    }

    if (route().current() === "grp.org.warehouses.show.dispatching.delivery_notes.show") {
        return route(
            "grp.org.warehouses.show.infrastructure.locations.show",
            [
                route().params["organisation"],
                route().params["warehouse"],
                location.location_slug
            ]
        )
    } else if (route().current() === "grp.org.warehouses.show.dispatching.delivery_notes.show") {
        return route(
            "grp.org.warehouses.show.infrastructure.locations.show",
            [
                route().params["organisation"],
                route().params["warehouse"],
                location.location_slug
            ]
        )
    } else {
        return ""
    }

}


// Section: Modal for edit expiry date and batch code
const selectedItemToEditExpiryDate = ref(null)
const isModalEditExpiryDate = ref(false)
const selectedBatchCode = ref(null)
const isModalLocation = ref(false)

watch(isModalEditExpiryDate, (isOpen) => {
    if (isOpen && selectedItemToEditExpiryDate.value?.batch_code_id) {
        selectedBatchCode.value = {
            id: selectedItemToEditExpiryDate.value.batch_code_id,
            code: selectedItemToEditExpiryDate.value.batch_code,
            expiry_date: selectedItemToEditExpiryDate.value.expiry_date,
            label: selectedItemToEditExpiryDate.value.batch_code,
        }
    } else {
        selectedBatchCode.value = null
    }
})


const GetQuantityToPickFractional = (item) => {
    if (props.shop_type == 'dropshipping') {
        return item.quantity_to_sow_fractional_ds
    } else return item.quantity_to_sow_fractional
}



const routeItemsWaitingWarehouse = (item) => {
    if (!route().params.warehouse || !route().params.organisation) {
        return '#'
    }

    return route('grp.org.warehouses.show.dispatching.waiting_items', {
        organisation: route().params.organisation,
        warehouse: route().params.warehouse,
    })
}

const routeItemsWaitingCrm = (item) => {
    if (!item.shop_slug || !route().params.organisation) {
        return '#'
    }

    return route('grp.org.shops.show.ordering.backlog.waiting_items', {
        organisation: route().params.organisation,
        shop: item.shop_slug
    })
}

// Section: Picking batch code
const isModalPickingBatchCode = ref(false)
const selectedPickingForBatchCode = ref(null)
const selectedPickingBatchCode = ref(null)

const selectedLocationCode = reactive({})
const selectedItemValue = ref()
const selectedItemProxy = ref()

const isLoadingUndoSowing = reactive({});

watch(isModalPickingBatchCode, (isOpen) => {
    if (isOpen && selectedPickingForBatchCode.value?.batch_code_id) {
        selectedPickingBatchCode.value = {
            id: selectedPickingForBatchCode.value.batch_code_id,
            code: selectedPickingForBatchCode.value.batch_code,
            label: selectedPickingForBatchCode.value.batch_code,
        }
    } else {
        selectedPickingBatchCode.value = null
    }
})

const findLocation = (locationsList: { location_code: string }[], locationCode: string) => {
    return locationsList.find(x => x.location_code == locationCode) || locationsList[0]
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>
        <template #cell(quantity_packed_readonly)="{ item }">
            <span v-tooltip="item.quantity_packed">
                <FractionDisplay v-if="item.quantity_packed_fractional" :fractionData="item.quantity_packed_fractional" />
                <span v-else>{{ item.quantity_packed }}</span>
            </span>
        </template>

        <template #cell(quantity_required_readonly)="{ item }">
            <span v-tooltip="item.quantity_required">
                <FractionDisplay v-if="item.quantity_required_fractional" :fractionData="item.quantity_required_fractional" />
                <span v-else>{{ item.quantity_required }}</span>
            </span>
        </template>

        <template #cell(quantity_picked_readonly)="{ item }">
            <FractionDisplay v-if="item.quantity_picked_fractional" :fractionData="item.quantity_picked_fractional" />
            <span v-else>{{ item.quantity_picked }}</span>
        </template>

        <!-- Column: state -->
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <!-- Column: Reference -->
        <template #cell(org_stock_code)="{ item: deliveryNoteItem }">
            <Link :href="orgStockRoute(deliveryNoteItem)" class="primaryLink">
                {{ deliveryNoteItem.org_stock_code }}
            </Link>
            <div class="text-xs opacity-80 italic text-justify">
                {{ deliveryNoteItem.org_stock_name }}
            </div>
        </template>

        <!-- Column: Name -->
        <template #cell(org_stock_name)="{ item: deliveryNoteItem }">
            <div>{{ deliveryNoteItem.org_stock_name }} <span
                    class="italic opacity-80">{{ deliveryNoteItem.packed_in_message }}</span></div>

            <!-- Section: DNI Expired date -->
            <div v-if="false" class="flex items-center flex-wrap">
                <!-- Label: expired date -->
                <ExpiryDateLabel v-if="(deliveryNoteItem.expiry_date || deliveryNoteItem.batch_code)"
                    :expiry_date="deliveryNoteItem.expiry_date" :batch_code="deliveryNoteItem.batch_code" />

                <!-- Button: add/edit expiry date and batch code -->
                <div
                    v-if="(deliveryNoteItem.is_picked || Number(deliveryNoteItem.quantity_picked) > 0) && state !== 'cancelled'">
                    <Button v-if="deliveryNoteItem.expiry_date || deliveryNoteItem.batch_code"
                        @click="() => (isModalEditExpiryDate = true, selectedItemToEditExpiryDate = deliveryNoteItem)"
                        type="transparent" v-tooltip="ctrans('Edit expiry date and batch code')" size="xs"
                        icon="fal fa-pencil" />
                    <Button v-else
                        @click="() => (isModalEditExpiryDate = true, selectedItemToEditExpiryDate = deliveryNoteItem)"
                        type="tertiary" size="xs" :label="ctrans('Add expiry date and batch code')" icon="fas fa-plus"
                        key="1">
                        <template #iconRight="">
                            <FontAwesomeIcon icon="fad fa-viruses" class="text-red-500" fixed-width
                                aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </template>

        <template #cell(expected_quantity)="{ item }">
            <span v-tooltip="item.quantity_required">
                <FractionDisplay v-if="item.expected_quantity_fractional"
                    :fractionData="item.expected_quantity_fractional" />
                <span v-else>{{ item.expected_quantity }}</span>
            </span>
        </template>

        <template #cell(sowings)="{ item: itemValue, proxyItem }">
            <div v-if="itemValue.sowings?.length" class="space-y-1 grid pt-2">
                <div v-for="sowing in itemValue.sowings" :key="sowing.id" class="flex gap-x-2 w-fit">
                    <!-- If sowing returned -->
                    <div v-if="sowing.type === 'sow'" class="flex gap-x-2 items-center flex-wrap">
                        <!-- <Link v-if="!!(generateLocationRoute(sowing))" :href="generateLocationRoute(sowing)" class="secondaryLink">
                            {{ sowing.location_code }}
                        </Link> -->
                        <span>
                            {{ sowing.location_code }}
                        </span>
                        <div v-tooltip="trans('Total returned quantity in this location')" class="text-gray-500 whitespace-nowrap">
                            <FontAwesomeIcon icon="fal fa-hand-holding-box" class="mr text-gray-500" fixed-width
                                aria-hidden="true" />
                            <FractionDisplay v-if="sowing.quantity_fractional"
                                :fractionData="sowing.quantity_fractional" />
                            <span v-else>
                                {{ sowing.quantity }}
                            </span>
                        </div>
                    </div>
                    <!-- If sowing not returned -->
                    <div v-if="sowing.type === 'not-sow'" v-tooltip="trans('Quantity not returned')"
                        class="text-red-500 w-fit mr-auto">
                        <FontAwesomeIcon :icon="faGhost" class="" fixed-width aria-hidden="true" />
                        <FractionDisplay v-if="sowing.quantity_fractional"
                            :fractionData="sowing.quantity_fractional" />
                        <span v-else>
                            {{ sowing.quantity }}
                        </span>
                    </div>
                    <!-- If sowing damaged -->
                    <div v-if="sowing.type === 'damaged'" v-tooltip="trans('Quantity damaged')"
                        class="text-red-500 w-fit mr-auto">
                        <FontAwesomeIcon :icon="faFragile" class="" fixed-width aria-hidden="true" />
                        <FractionDisplay v-if="sowing.quantity_fractional"
                            :fractionData="sowing.quantity_fractional" />
                        <span v-else>
                            {{ sowing.quantity }}
                        </span>
                    </div>
                    <!-- Undo Button -->
                    <div vxif="isEditable" class="">
                        <ButtonWithLink
                            v-if="sowing.quantity"
                            v-tooltip="ctrans('Undo sowing :qtyPicked items', { qtyPicked: Number(sowing.quantity).toString()})"
                            type="negative"
                            :size="screenType != 'mobile' ? 'xxs' : 'md'"
                            :icon="faUndoAlt"
                            :routeTarget="sowing.undo_sowing_route"
                            :bindToLink="{ preserveScroll: true }"
                            :loading="get(isLoadingUndoSowing, `undo-pick-${sowing.id}`, false)"
                        />
                    </div>
                </div>
            </div>
            <span v-else>
            </span>
        </template>

        <!-- Column: Total Item Damaged -->
        <template #cell(total_item_damaged)="{ item: itemValue, proxyItem }">
            <div class="grid justify-items-end gap-y-2" v-if="itemValue.has_available_qty && itemValue.state != 'processed'">
                <NumberWithButtonSave
                    vxif="!itemValue.is_handled && findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity > 0"
                    noUndoButton
                    @onError="(error: any) => {
                        proxyItem.errors = Object.values(error || {})
                    }"
                    :modelValue="itemValue.total_item_damaged"
                    @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                    saveOnForm
                    :routeSubmit="itemValue.upsert_damaged_route"
                    :bindToTarget="{
                        step: 1,
                        min: 0,
                        max: Math.min(itemValue.quantity, itemValue.quantity_required)
                    }"
                    xadditionalData="{
                        location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id,
                        picking_id: itemValue.pickings.find(picking => picking.location_id == findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_id)?.id,
                    }"
                    autoSave
                    xxisWithRefreshModel
                    xreadonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                >
                    <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                        <div class="flex gap-x-8 w-fit">
                            <ButtonWithLink
                                vxtooltip="trans('Pick all required quantity in location :xlocation', { xlocation: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_code || '-' })"
                                icon="fal fa-fragile"
                                :size="screenType != 'mobile' ? 'xs' : 'md'"
                                type="negative"
                                :loading="isProcessing"
                                class="py-0"
                                :routeTarget="itemValue.set_all_damaged_route"
                                :bind-to-link="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }"
                                xbody="{
                                    location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id
                                }"
                                isWithError>
                                <template #label>
                                    <div>
                                        <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)" :fractionData="GetQuantityToPickFractional(itemValue)" />
                                        <span v-else>
                                            {{ locale.number(itemValue.quantity_to_sow ?? 0) }}
                                        </span>
                                    </div>
                                </template>
                            </ButtonWithLink>
                        </div>
                    </template>
                </NumberWithButtonSave>
            </div>
            <FractionDisplay v-else-if="itemValue.total_item_damaged" :fractionData="itemValue.total_item_damaged_fractional" />
            <span v-else>
            </span>
        </template>
        
        <!-- Column: item not returned -->
        <template #cell(total_item_not_returned)="{ item: itemValue, proxyItem }">
            <div class="grid justify-items-end gap-y-2" v-if="itemValue.has_available_qty && itemValue.state != 'processed'">
                <NumberWithButtonSave v-if="itemValue.has_available_qty"
                    vxif="!itemValue.is_handled && findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity > 0"
                    noUndoButton
                    @onError="(error: any) => {
                        proxyItem.errors = Object.values(error || {})
                    }"
                    :modelValue="itemValue.total_item_not_returned"
                    @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                    saveOnForm
                    :routeSubmit="itemValue.upsert_not_returned_route"
                    :bindToTarget="{
                        step: 1,
                        min: 0,
                        max: Math.min(itemValue.quantity, itemValue.quantity_required)
                    }"
                    xadditionalData="{
                        location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id,
                        picking_id: itemValue.pickings.find(picking => picking.location_id == findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_id)?.id,
                    }"
                    autoSave
                    xxisWithRefreshModel
                    xreadonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                >
                    <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                        <div class="flex gap-x-8 w-fit">
                            <ButtonWithLink
                                vxtooltip="trans('Pick all required quantity in location :xlocation', { xlocation: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_code || '-' })"
                                icon="far fa-ghost"
                                :size="screenType != 'mobile' ? 'xs' : 'md'"
                                type="negative"
                                :loading="isProcessing"
                                class="py-0"
                                :routeTarget="itemValue.set_all_not_returned_route"
                                :bind-to-link="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }"
                                xbody="{
                                    location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id
                                }"
                                isWithError>
                                <template #label>
                                    <div>
                                        <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)" :fractionData="GetQuantityToPickFractional(itemValue)" />
                                        <span v-else>
                                            {{ locale.number(itemValue.expected_quantity ?? 0) }}
                                        </span>
                                    </div>
                                </template>
                            </ButtonWithLink>
                        </div>
                    </template>
                </NumberWithButtonSave>
            </div>
            <FractionDisplay v-else-if="itemValue.total_item_not_returned_fractional" :fractionData="itemValue.total_item_not_returned_fractional" />
            <span v-else>
            </span>
        </template>

        <!-- Column: item returned -->
        <template #cell(total_item_returned)="{ item: itemValue, proxyItem }">
            <div class="grid justify-items-end gap-y-2" v-if="itemValue.has_available_qty && itemValue.state != 'processed'">
                <NumberWithButtonSave
                    vxif="!itemValue.is_handled && findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity > 0"
                    noUndoButton
                    @onError="(error: any) => {
                        proxyItem.errors = Object.values(error || {})
                    }"
                    :modelValue="itemValue.total_item_returned"
                    @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                    saveOnForm
                    :routeSubmit="itemValue.upsert_returned_route"
                    :bindToTarget="{
                        step: 1,
                        min: 0,
                        max: Math.min(itemValue.quantity, itemValue.quantity_required)
                    }"
                    :additionalData="{
                        location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id,
                        // picking_id: itemValue.pickings.find(picking => picking.location_id == findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_id)?.id,
                    }"
                    autoSave
                    xxisWithRefreshModel
                    xreadonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                >
                    <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                        <div class="flex gap-x-8 w-fit">
                            <ButtonWithLink
                                v-tooltip="trans('Pick all required quantity in location :xlocation', { xlocation: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_code || '-' })"
                                icon="fal fa-check"
                                :size="screenType != 'mobile' ? 'xs' : 'md'"
                                type="positive"
                                :loading="isProcessing"
                                class="py-0"
                                :routeTarget="itemValue.set_all_returned_route"
                                :body="{
                                    location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id
                                }"
                                :bind-to-link="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }"
                                isWithError>
                                <template #label>
                                    <div>
                                        <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)" :fractionData="GetQuantityToPickFractional(itemValue)" />
                                        <span v-else>
                                            {{ locale.number(itemValue.expected_quantity ?? 0) }}
                                        </span>
                                    </div>
                                </template>
                            </ButtonWithLink>
                        </div>
                    </template>
                </NumberWithButtonSave>
                <LabelPickingLocation
                    :locations="itemValue.locations"
                    :selectedOrgStockId="get(selectedLocationCode, [itemValue.id], null)"
                    :warehouseArea="itemValue.warehouse_area"
                    @openLocationModal="() => { 
                        isModalLocation = true; selectedItemValue = itemValue; selectedItemProxy = proxyItem; 
                    }"
                />
            </div>
            <FractionDisplay v-else-if="itemValue.total_item_returned_fractional" :fractionData="itemValue.total_item_returned_fractional" />
            <span v-else>
            </span>
        </template>

        <template #cell(action)="{ item: item }">
            <!-- <template
                v-if="(state === 'packing' || state === 'packed') && props.shop_type !== 'dropshipping' && item.quantity_picked > 0">

                <div class="flex justify-start items-center">
                    <ButtonWithLink v-if="!item.is_done_packing"
                        :label="ctrans('Pack :countToPack items', { countToPack: Number(item.quantity_picked) })"
                        type="secondary" xlabel="ctrans('Packing')" :size="screenType == 'desktop' ? 'xs' : 'lg'"
                        :key="screenType" :bindToLink="{ preserveScroll: true }" :routeTarget="{
                            name: 'grp.models.delivery_note_item.packing.store',
                            method: 'patch',
                            parameters: {
                                deliveryNoteItem: item.id
                            }
                        }" />
                    <ButtonWithLink v-else v-tooltip="ctrans('Undo packing')" type="negative"
                        :size="screenType == 'desktop' ? 'xs' : 'lg'" :bindToLink="{ preserveScroll: true }" :routeTarget="{
                            name: 'grp.models.delivery_note_item.packing.delete',
                            method: 'delete',
                            parameters: {
                                deliveryNoteItem: item.id
                            }
                        }" :icon="faUndo" />
                    <Button v-if="layout.app.environment === 'local' && !item.is_done_packing" type="negative"
                        class="ml-4" icon="fal fa-debug" :size="screenType == 'desktop' ? 'xs' : 'lg'"
                        v-tooltip="'Packing info'" @click="openPackingModal(item)" />
                </div>
            </template>

            <div v-else-if="(state === 'packing' || state === 'packed') && props.shop_type !== 'dropshipping' && !(item.quantity_picked > 0)"
                class="italic text-xs opacity-70">
                {{ ctrans("Nothing to pack") }}
            </div> -->

            <div class="flex gap-2">
            </div>
        </template>
    </Table>

    <Modal :isOpen="isModalLocation" @onClose="isModalLocation = false" width="w-full max-w-3xl" xdialogStyle="{ background: '#ffffff' }">
        <SelectPickingLocation
            :item="selectedItemValue"
            :selectedLocationCode="get(selectedLocationCode, [selectedItemValue?.id], null)"
            @select="(code) => { set(selectedLocationCode, [selectedItemValue?.id], code); isModalLocation = false; }"
            :ignoreNoQty="true"
        />
    </Modal>
</template>
