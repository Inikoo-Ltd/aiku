<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 09 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTruck, faInventory, faListOl, faHandHoldingBox, faClipboardListCheck, faUndoAlt, faDebug, faHourglassStart } from "@fal"
import { faSkull, faHeadset, faCircle } from "@fas"
import { inject, reactive, ref } from "vue"
import { get, set } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"
import { routeType } from "@/types/route"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { Message, RadioButton } from "primevue"
import NotesDisplay from "@/Components/NotesDisplay.vue"
import axios from "axios"
import { twBreakPoint } from "@/Composables/useWindowSize"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import PassWaitingItemsToCs from "@/Components/Warehouse/DeliveryNotes/PassWaitingItemsToCs.vue"
import LabelItemsWaitingForWarehouse from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForWarehouse.vue"
import LabelItemsWaitingForCrm from "@/Components/Warehouse/DeliveryNotes/LabelItemsWaitingForCrm.vue"
import UnderConstruction from "@/Pages/Iris/Disclosure/UnderConstruction.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"

library.add(faTruck, faInventory, faListOl, faHandHoldingBox, faClipboardListCheck, faUndoAlt, faDebug, faHourglassStart, faSkull, faHeadset, faCircle)


const locale = inject("locale", aikuLocaleStructure)
const layout = inject("layout", layoutStructure)

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

const findLocation = (locationsList: any[], selectedCode: string) => {
    return locationsList?.find(x => x.location_code === selectedCode) || locationsList?.find(x => Number(x.quantity)) || locationsList?.[0]
}

const isModalLocation = ref(false)
const selectedItemValue = ref<any>(null)
const selectedItemProxy = ref<any>(null)

const onCloseModal = () => {
    isModalLocation.value = false
    setTimeout(() => {
        selectedItemValue.value = null
    }, 300)
}

const isLoadingUndoPick = reactive<Record<string, boolean>>({})

const onUndoPick = async (routeTarget: routeType, item: any, loadingKey: string) => {
    try {
        set(isLoadingUndoPick, loadingKey, true)
        await axios[routeTarget.method || "delete"](route(routeTarget.name, routeTarget.parameters))
        router.reload()
    } catch (error) {
        console.error(error)
    } finally {
        set(isLoadingUndoPick, loadingKey, false)
    }
}


// Section: method Pass to CS
const isOpenModalPassToCs = ref(false)
const selectedTransactionToSetAsWaiting = ref(null)
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

        <template #cell(items)="{ item: deliveryNoteRow, proxyItem }">
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
                            <LabelItemsWaitingForWarehouse v-if="Number(deliveryItem.quantity_waiting_warehouse) > 0" :qty_waiting_warehouse="Number(deliveryItem.quantity_waiting_warehouse)" />
                            <span class="ml-8 mr-4 whitespace-nowrap">--></span>

                            <div class="flex justify-end w-full items-center gap-x-3">

                                <!-- Actions: Locations, input Quantity -->
                                <div v-if="!isStillPicking && findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)" class="flex items-center gap-x-2">
                                    <!-- Section: Location -->
                                    <Transition name="spin-to-down">
                                        <div :key="findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)?.location_code">
                                            <!-- Section: number of locations available to pick -->
                                            <span v-if="deliveryItem.locations?.length > 1" @click="() => {
                                                    isModalLocation = true;
                                                    selectedItemValue = deliveryItem;
                                                    selectedItemProxy = proxyItem;
                                                }" v-tooltip="`Other ${deliveryItem.locations?.length - 1} locations`"
                                                class="mr-1 cursor-pointer hover:bg-orange-50 whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-orange-300 rounded px-1">
                                                    <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                                                    {{ deliveryItem.locations?.length - 1 }}
                                                </span>
                                            <span v-if="findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)" class="text-base">
                                                    <Link v-tooltip="`${deliveryItem.warehouse_area}`"
                                                        :href="generateLocationRoute(findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode))"
                                                        class="secondaryLink">
                                                        {{ findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode).location_code }}
                                                    </Link>
                                                </span>
                                            <span v-else v-tooltip="trans('Unknown location')" class="text-gray-400 italic">
                                                    ({{ trans("Unknown") }})
                                                </span>
    
                                            <!-- Section: number of stocks -->
                                            <span
                                                v-tooltip="trans(':stockAvailable stock available on location :stockLocation', { stockAvailable: locale.number(findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)?.quantity || 0), stockLocation: findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)?.location_code || '' })"
                                                class="align-middle whitespace-nowrap text-base py-0.5 xopacity-70 tabular-nums xborder border-gray-300 rounded xpx-1"
                                            >
                                                    (<span class="text-lg font-bold">
                                                        <FractionDisplay
                                                            v-if="findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)?.quantity_fractional"
                                                            :fractionData="findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)?.quantity_fractional"
                                                        />
                                                        <template v-else>
                                                            {{ locale.number(findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)?.quantity || 0) }}
                                                        </template>
                                                    </span>
                                                    <span class="text-sm ml-1">{{ ctrans("stocks") }}</span>)
                                                </span>
                                        </div>
                                    </Transition>

                                    <!-- Section: input Quantity-->
                                    <NumberWithButtonSave
                                        v-if="!isStillPicking"
                                        :key="findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode).location_code"
                                        noUndoButton
                                        @onError="(error: any) => { proxyItem.errors = Object.values(error || {}) }"
                                        :modelValue="get(deliveryItem, 'waiting_warehouse_quantity', 0)"
                                        @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                        saveOnForm
                                        :routeSubmit="{
                                            name: deliveryItem.upsert_picking_route.name,
                                            parameters: deliveryItem.upsert_picking_route.parameters,
                                        }"
                                        :bindToTarget="{
                                            step: 1,
                                            min: 0,
                                            max: Math.min(
                                                Number(findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode)?.quantity),
                                                Number(get(deliveryItem, 'waiting_warehouse_quantity', 0)) + Number(deliveryItem.quantity_waiting_warehouse) )
                                        }"
                                        :additionalData="{
                                            location_org_stock_id: findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode).id,
                                            picking_id: deliveryItem.pickings?.find(picking => picking.location_id == findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode).location_id)?.id,
                                        }"
                                        autoSave
                                        isWithRefreshModel
                                        :readonly="findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode).quantity <= 0"
                                    >
                                        <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                            <div class="flex gap-x-8 w-fit">
                                                <ButtonWithLink
                                                    v-tooltip="trans('Pick all required quantity in location :xlocation', { xlocation: findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode).location_code || '-' })"
                                                    icon="fal fa-clipboard-list-check"
                                                    :size="twBreakPoint().includes('lg') ? 'xs' : 'lg'"
                                                    type="secondary"
                                                    :loading="isProcessing"
                                                    class="py-0"
                                                    :routeTarget="deliveryItem.picking_all_route"
                                                    :bind-to-link="{
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                    }"
                                                    :body="{
                                                        location_org_stock_id: findLocation(deliveryItem.locations, proxyItem.selectedRadioLocationCode).id
                                                    }"
                                                    isWithError
                                                >
                                                    <template #label>
                                                        <div>
                                                            <span>{{ locale.number(deliveryItem.quantity_waiting_warehouse ?? 0) }}</span>
                                                        </div>
                                                    </template>
                                                </ButtonWithLink>
                                            </div>
                                        </template>
                                    </NumberWithButtonSave>

                                    <div class="mx-1">
                                        or
                                    </div>
                                </div>

                                <template v-if="allowStockControllerSetNotPicked">
                                    <!-- Button: Not Picked -->
                                    <ButtonWithLink
                                        type="negative"
                                        iconRight="fal fa-debug"
                                        :size="twBreakPoint().includes('lg') ? 'xs' : 'lg'"
                                        :routeTarget="{
                                            method: 'post',
                                            name: 'grp.models.delivery_note_item.not_picking_from_waiting_warehouse.store',
                                            parameters: {
                                                deliveryNoteItem: deliveryItem.id
                                            }
                                        }"
                                        :bindToLink="{ preserveScroll: true }"
                                        v-tooltip="trans('Set :numberNotPicked as not picked', { numberNotPicked: locale.number(deliveryItem.quantity_waiting_warehouse ) || '0'})"
                                    >
                                        <template #label>
                                            <div>
                                                <!-- <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)" :fractionData="GetQuantityToPickFractional(itemValue)" /> -->
                                                <span vxelse>{{ locale.number(deliveryItem.quantity_waiting_warehouse ?? 0) }}</span>
                                            </div>
                                        </template>
                                    </ButtonWithLink>
                                    <span>or</span>
                                </template>

                                <Button
                                    @click="() => (isOpenModalPassToCs = true, selectedTransactionToSetAsWaiting = deliveryItem)"
                                    icon="fal fa-user-headset"
                                    :label="trans('Pass :qtyInWarehouse to CS', { qtyInWarehouse: String(Number(deliveryItem.quantity_waiting_warehouse)) })"
                                    :size="twBreakPoint().includes('lg') ? 'xs' : 'lg'"
                                    type="tertiary"
                                    class="!bg-purple-300 hover:!bg-purple-400/80 !text-purple-700 !border-purple-400 !py-2"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <span v-else class="text-gray-400 italic text-xs">{{ trans("No items") }}</span>
        </template>
    </Table>


    <!-- Location list modal -->
    <Modal :isOpen="isModalLocation" @onClose="onCloseModal" width="w-full max-w-2xl" :dialogStyle="{ background: '#ffffffcc' }">
        <div class="text-center font-semibold mb-4 text-2xl">
            {{ trans("Location list for") }} {{ selectedItemValue?.org_stock_code }}
        </div>
        <div class="rounded p-1 grid grid-cols-2 lg:grid-cols-3 gap-3">
            <div
                v-for="location in selectedItemValue?.locations"
                :key="location.location_code"
                class="bg-white rounded w-full flex justify-between gap-x-3 items-center px-2 py-1"
            >
                <label :for="location.location_code">
                    <span v-if="location.location_code" v-tooltip="location.quantity <= 0 ? trans('Location has no stock') : ''" :class="location.quantity <= 0 ? 'text-gray-400' : ''">
                        <Link :href="generateLocationRoute(location)" class="bg-gradient-to-t from-yellow-300/50 to-yellow-200/50 px-1">
                            {{ location.location_code }}
                        </Link>
                    </span>
                    <span v-else class="text-gray-400 italic">({{ trans("Unknown") }})</span>
                    <span v-tooltip="trans('Total stock in this location')" class="ml-1 whitespace-nowrap text-gray-400 tabular-nums border border-gray-300 rounded px-1 text-xs">
                        <FontAwesomeIcon icon="fal fa-inventory" fixed-width aria-hidden="true" />
                        {{ Number(location.quantity ?? 0) }}
                    </span>
                </label>
                <RadioButton
                    v-if="selectedItemProxy && selectedItemValue"
                    :modelValue="get(selectedItemProxy, 'selectedRadioLocationCode')"
                    @update:modelValue="(e: string) => { set(selectedItemProxy, 'selectedRadioLocationCode', e); onCloseModal() }"
                    :size="twBreakPoint().includes('lg') ? undefined : 'large'"
                    :inputId="location.location_code"
                    :disabled="location.quantity <= 0"
                    name="location"
                    :value="location.location_code"
                />
            </div>
        </div>
    </Modal>


    <!-- Modal: Set Transaction as Waiting -->
    <Modal :isOpen="isOpenModalPassToCs" width="w-full max-w-lg" @close="isOpenModalPassToCs = false">
        <!-- Product info header -->
        <div>
            <PassWaitingItemsToCs
                v-if="selectedTransactionToSetAsWaiting"
                v-model="isOpenModalPassToCs"
                :transaction="selectedTransactionToSetAsWaiting"
            />
        </div>
    </Modal>
</template>

