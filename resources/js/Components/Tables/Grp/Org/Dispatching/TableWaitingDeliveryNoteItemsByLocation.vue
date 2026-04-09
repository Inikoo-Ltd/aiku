<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faListOl, faHandHoldingBox, faClipboardListCheck, faUndoAlt, faDebug } from "@fal"
import { faSkull, faHeadset } from "@fas"
import Icon from "@/Components/Icon.vue"
import { reactive, ref } from "vue"
import { get, set } from "lodash-es"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"
import { routeType } from "@/types/route"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { RadioButton } from "primevue"
import NotesDisplay from "@/Components/NotesDisplay.vue"
import axios from "axios"
import { twBreakPoint } from "@/Composables/useWindowSize"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { notify } from "@kyvg/vue3-notification"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import Image from "@/Components/Image.vue"

library.add(faInventory, faListOl, faHandHoldingBox, faClipboardListCheck, faUndoAlt, faDebug, faSkull, faHeadset)

const locale = inject('locale', aikuLocaleStructure)

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

const generateLocationRoute = (location: any) => {
    if (!location.location_slug) return "#"
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        location.location_slug,
    ])
}

const findLocation = (locationsList: any[], selectedCode: string) => {
    return locationsList?.find(x => x.location_code === selectedCode) || locationsList?.[0]
}

const isModalLocation = ref(false)
const selectedItemValue = ref<any>(null)
const selectedItemProxy = ref<any>(null)

const onCloseModal = () => {
    isModalLocation.value = false
    setTimeout(() => { selectedItemValue.value = null }, 300)
}

const isLoadingUndoPick = reactive<Record<string, boolean>>({})

const onUndoPick = async (routeTarget: routeType, loadingKey: string) => {
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
const dataToSendAsWaiting = ref({
    note: '',
})
const isOpenModalPassToCs = ref(false)
const selectedTransactionToSetAsWaiting = ref(null)
const isLoadingSetAsWaiting = ref(false)
const onPassItemToCs = () => {
    // Section: Submit
    router.post(
        route('grp.models.delivery_note_item.set_as_waiting_crm', {
            deliveryNoteItem: selectedTransactionToSetAsWaiting.value?.id
        }),
        {
            ...dataToSendAsWaiting.value,
            transaction_id: selectedTransactionToSetAsWaiting.value?.id,
            quantity: Number(selectedTransactionToSetAsWaiting.value?.quantity_waiting_warehouse || 0) + Number(selectedTransactionToSetAsWaiting.value?.quantity_waiting_crm || 0)
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSetAsWaiting.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully set item as waiting"),
                    type: "success"
                })
                dataToSendAsWaiting.value.note = ''
                isOpenModalPassToCs.value = false
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set item as waiting. Try again"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSetAsWaiting.value = false
            },
        }
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

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
                <div v-tooltip="trans('Quantity of items waiting for CRM')" class="border-l-2 border-yellow-400 relative bg-yellow-500/20 py-1 pr-2 pl-1 text-yellow-700 whitespace-nowrap w-fit">
                    <FontAwesomeIcon icon="fal fa-hourglass-start" class="mr opacity-70" fixed-width aria-hidden="true" />
                    <!-- <FractionDisplay v-if="item.quantity_picked_fractional"
                        :fractionData="item.quantity_picked_fractional" /> -->
                    <span>
                        {{ trans(":quantityWaitingCRM items are waiting for CRM", { quantityWaitingCRM: Number(item.quantity_waiting_crm) }) }}
                    </span>

                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon icon="fas fa-circle" class="absolute top-0 -right-0.5 text-orange-500 text-[5px]" fixed-width aria-hidden="true" />
                </div>
            </div>
        </template>

        <!-- Column: Actions (location picker + quantity + not-picked + button pass to CS) -->
        <template #cell(picking_position)="{ item: itemValue, proxyItem }">
            <div v-if="Number(itemValue.quantity_waiting_warehouse) > 0">
                <div v-if="findLocation(itemValue.locations, proxyItem.org_stock_id)" class="rounded p-1 flex flex-col gap-2">
                    <div class="flex justify-between items-center gap-x-4">
                        <!-- Section: location -->
                        <div class="">
                            <Transition name="spin-to-down">
                                <div :key="findLocation(itemValue.locations, proxyItem.org_stock_id)?.location_code">

                                    <!-- Section: number of locations available to pick -->
                                    <span v-if="itemValue.locations?.length > 1" @click="() => {
                                            isModalLocation = true;
                                            selectedItemValue = itemValue;
                                            selectedItemProxy = proxyItem;
                                        }" v-tooltip="`Other ${itemValue.locations?.length - 1} locations`"
                                        class="mr-1 cursor-pointer hover:bg-orange-50 whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-orange-300 rounded px-1">
                                        <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width
                                            aria-hidden="true" />
                                        {{ itemValue.locations?.length - 1 }}
                                    </span>

                                    <span v-if="findLocation(itemValue.locations, proxyItem.org_stock_id)" class="text-base">
                                        <Link v-tooltip="`${itemValue.warehouse_area}`"
                                            :href="generateLocationRoute(findLocation(itemValue.locations, proxyItem.org_stock_id))"
                                            class="secondaryLink">
                                            {{ findLocation(itemValue.locations, proxyItem.org_stock_id).location_code }}
                                        </Link>
                                    </span>
                                    <span v-else v-tooltip="trans('Unknown location')" class="text-gray-400 italic">
                                        ({{ trans("Unknown") }})
                                    </span>
                                    
                                    <!-- Section: number of stocks -->
                                    <span
                                        v-tooltip="trans(':stockAvailable stock available on location :stockLocation', { stockAvailable: locale.number(findLocation(itemValue.locations, proxyItem.org_stock_id)?.quantity || 0), stockLocation: findLocation(itemValue.locations, proxyItem.org_stock_id)?.location_code || '' })"
                                        class="align-middle whitespace-nowrap text-base py-0.5 xopacity-70 tabular-nums xborder border-gray-300 rounded xpx-1"
                                    >
                                        <!-- <FontAwesomeIcon icon="fal fa-inventory" class="mr-1 text-base" fixed-width aria-hidden="true" /> -->
                                        (<span class="text-lg font-bold">
                                            <FractionDisplay
                                                v-if="findLocation(itemValue.locations, proxyItem.org_stock_id)?.quantity_fractional"
                                                :fractionData="findLocation(itemValue.locations, proxyItem.org_stock_id)?.quantity_fractional"
                                            />
                                            <template v-else>
                                                {{ locale.number(findLocation(itemValue.locations, proxyItem.org_stock_id).quantity) }}
                                            </template>
                                        </span>
                                        <span class="text-sm ml-1">{{ ctrans("stocks") }}</span>)
                                    </span>
                                </div>
                            </Transition>
                        </div>

                        <!-- Quantity + pick all + not picked + call CS -->
                        <div class="flex items-center gap-x-2">
                            <NumberWithButtonSave
                                v-if="!itemValue.is_handled && findLocation(itemValue.locations, proxyItem.org_stock_id).quantity > 0"
                                :key="findLocation(itemValue.locations, proxyItem.org_stock_id).location_code"
                                noUndoButton
                                @onError="(error: any) => { proxyItem.errors = Object.values(error || {}) }"
                                xmodelValue="findLocation(itemValue.locations, proxyItem.org_stock_id).quantity_picked"
                                :modelValue="null"
                                @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                saveOnForm
                                :routeSubmit="{
                                    name: itemValue.upsert_picking_route.name,
                                    parameters: itemValue.upsert_picking_route.parameters,
                                }"
                                :bindToTarget="{
                                    step: 1, min: 0,
                                    max: Math.min(
                                        findLocation(itemValue.locations, proxyItem.org_stock_id).quantity,
                                        itemValue.quantity_required,
                                        Number(itemValue.quantity_waiting_warehouse) + findLocation(itemValue.locations, proxyItem.org_stock_id).quantity_picked
                                    )
                                }"
                                :additionalData="{
                                    location_org_stock_id: findLocation(itemValue.locations, proxyItem.org_stock_id).id,
                                    picking_id: itemValue.pickings?.find((p: any) => p.location_id === findLocation(itemValue.locations, proxyItem.org_stock_id).location_id)?.id,
                                }"
                                autoSave
                                :readonly="itemValue.is_handled || itemValue.quantity_required === itemValue.quantity_picked"
                            >
                                <template #save="{ isProcessing }">
                                    <ButtonWithLink
                                        v-tooltip="trans('Pick all required quantity in this location')"
                                        icon="fal fa-clipboard-list-check"
                                        :disabled="itemValue.is_handled || itemValue.quantity_required === itemValue.quantity_picked"
                                        size="xs" type="secondary" :loading="isProcessing"
                                        :routeTarget="itemValue.picking_all_route"
                                        :bind-to-link="{ preserveScroll: true, preserveState: true }"
                                        :body="{ location_org_stock_id: findLocation(itemValue.locations, proxyItem.org_stock_id).id }"
                                        isWithError
                                    >
                                        <template #label>
                                            <FractionDisplay v-if="itemValue.quantity_waiting_warehouse_fractional" :fractionData="itemValue.quantity_waiting_warehouse_fractional" />
                                            <span v-else>{{ Number(itemValue.quantity_waiting_warehouse) }}</span>
                                        </template>
                                    </ButtonWithLink>
                                </template>
                            </NumberWithButtonSave>

                            <!-- <ButtonWithLink
                                v-if="!itemValue.is_handled"
                                type="negative" tooltip="Set as not picked" icon="fal fa-debug"
                                :size="twBreakPoint().includes('lg') ? undefined : 'lg'"
                                :routeTarget="itemValue.not_picking_route"
                                :bindToLink="{ preserveScroll: true }"
                            /> -->

                            <Button @click="() => (isOpenModalPassToCs = true, selectedTransactionToSetAsWaiting = itemValue, dataToSendAsWaiting.note = itemValue.notes)" icon="fal fa-user-headset" :label="trans('Pass to CS')" size="xs" type="tertiary" />
                        </div>
                    </div>

                    <!-- Error messages -->
                    <div v-if="proxyItem.errors?.length">
                        <p v-for="error in proxyItem.errors" class="text-xs text-red-500 italic">*{{ error }}</p>
                    </div>
                </div>
            </div>
            <div v-else class="flex gap-x-2 items-center justify-between">
                <div></div>
                <div>
                    <Button @click="() => (isOpenModalPassToCs = true, selectedTransactionToSetAsWaiting = itemValue, dataToSendAsWaiting.note = itemValue.notes)" icon="fal fa-user-headset" :label="trans('Pass to CS')" size="xs" type="tertiary" />

                </div>
            </div>
        </template>
    </Table>

    <!-- Modal: Locations -->
    <Modal :isOpen="isModalLocation" @onClose="onCloseModal" width="w-full max-w-2xl" :dialogStyle="{ background: '#ffffffcc' }">
        <div class="text-center font-semibold mb-4 text-2xl">
            {{ trans('Location list for') }} {{ selectedItemValue?.org_stock_code }}
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
                    <span v-else class="text-gray-400 italic">({{ trans('Unknown') }})</span>
                    <span v-tooltip="trans('Total stock in this location')" class="ml-1 whitespace-nowrap text-gray-400 tabular-nums border border-gray-300 rounded px-1 text-xs">
                        <FontAwesomeIcon icon="fal fa-inventory" fixed-width aria-hidden="true" />
                        {{ Number(location.quantity ?? 0) }}
                    </span>
                </label>
                <RadioButton
                    v-if="selectedItemProxy"
                    v-model="selectedItemProxy.org_stock_id"
                    @update:modelValue="onCloseModal"
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
        <div class="font-semibold text-center text-2xl mb-8">
            {{ trans("Pass item to CS") }}
        </div>

        <div class="flex items-center gap-4 mb-2">
            <div class="shrink-0 size-16 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center">
                <Image
                    v-if="selectedTransactionToSetAsWaiting?.org_stock_image_thumbnail"
                    :src="selectedTransactionToSetAsWaiting.org_stock_image_thumbnail"
                    :alt="selectedTransactionToSetAsWaiting.org_stock_name"
                />
                <FontAwesomeIcon v-else icon="fal fa-box" class="text-2xl text-gray-400" fixed-width aria-hidden="true" />
            </div>

            <div class="min-w-0">
                <div class="text-xl leading-tight">
                    {{ selectedTransactionToSetAsWaiting?.org_stock_name ?? '-' }}
                </div>
                <div class="text-sm opacity-75 italic">
                    {{ selectedTransactionToSetAsWaiting?.org_stock_code }}
                </div>
            </div>
        </div>

        <!-- Section: Quantity badge -->
        <div class="flex items-center gap-2 mb-6 p-3 rounded-lg bg-amber-50 border border-amber-200">
            <FontAwesomeIcon icon="fal fa-hourglass-half" class="text-amber-500" fixed-width aria-hidden="true" />
            <span class="text-sm text-amber-700">
                {{ trans('Quantity to pass to CS') }}:
            </span>
            <span class="font-bold text-amber-800">
                <!-- <FractionDisplay
                    v-if="GetQuantityToPickFractional(selectedTransactionToSetAsWaiting)"
                    :fractionData="GetQuantityToPickFractional(selectedTransactionToSetAsWaiting)"
                />
                <template v-else>{{ locale.number(selectedTransactionToSetAsWaiting.quantity_waiting_warehouse + Number(selectedTransactionToSetAsWaiting.quantity_waiting_warehouse || 0) + Number(selectedTransactionToSetAsWaiting.quantity_waiting_crm || 0)  ) }}</template> -->
                {{ selectedTransactionToSetAsWaiting.quantity_waiting_warehouse + Number(selectedTransactionToSetAsWaiting.quantity_waiting_warehouse || 0) + Number(selectedTransactionToSetAsWaiting.quantity_waiting_crm || 0)  }}
                
            </span>
        </div>

        <!-- Note textarea -->
        <div>
            <label class="font-medium mb-1 flex items-center gap-x-1 text-sm">
                {{ trans('Note') }}:
            </label>
            <PureTextarea v-model="dataToSendAsWaiting.note" :rows="4" />
        </div>

        <div class="flex gap-2 mt-6">
            <Button
                @click="() => isOpenModalPassToCs = false"
                :label="ctrans('Cancel')"
                type="negative"
            />
            <Button
                @click="() => onPassItemToCs()"
                :label="trans('Pass item to CS')"
                full
                iconRight="far fa-arrow-right"
                :loading="isLoadingSetAsWaiting"
            />
        </div>
    </Modal>
</template>
