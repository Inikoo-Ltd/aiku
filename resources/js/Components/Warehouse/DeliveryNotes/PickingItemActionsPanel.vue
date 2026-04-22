<!--
* Author: Vika Aqordi
* Created on: 2026-04-22 09:25
* Github: https://github.com/aqordeon
* Copyright: 2026
-->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faInventory, faClipboardListCheck, faDebug } from "@fal"
import { computed, inject, ref } from "vue"
import { trans } from "laravel-vue-i18n"
import { RouteParams } from "@/types/route-params"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { twBreakPoint } from "@/Composables/useWindowSize"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import PassWaitingItemsToCs from "@/Components/Warehouse/DeliveryNotes/PassWaitingItemsToCs.vue"
import PickingLocationModal from "@/Components/Warehouse/DeliveryNotes/PickingLocationModal.vue"

library.add(faInventory, faClipboardListCheck, faDebug)

const locale = inject("locale", aikuLocaleStructure)

const props = defineProps<{
    item: any
    isStillPicking: boolean
    allowStockControllerSetNotPicked: boolean
}>()

const generateLocationRoute = (location: any): string => {
    if (!location.location_slug) return "#"
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        (route().params as RouteParams).organisation,
        (route().params as RouteParams).warehouse,
        location.location_slug,
    ])
}

const findLocation = (locationsList: any[], selectedCode: string) => {
    return locationsList?.find(x => x.location_code === selectedCode) || locationsList?.find(x => Number(x.quantity)) || locationsList?.[0]
}

const selectedLocationCode = ref<string>(props.item.selectedRadioLocationCode ?? "")
const currentLocation = computed(() => findLocation(props.item.locations, selectedLocationCode.value))

const isModalLocation = ref(false)
const isOpenModalPassToCs = ref(false)
const errors = ref<string[]>([])
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-3">
        <!-- Section: Location display -->
        <template v-if="!isStillPicking && currentLocation">
            <Transition name="spin-to-down">
                <div :key="currentLocation?.location_code" class="flex items-center gap-x-1">
                    <!-- Other locations badge -->
                    <span
                        v-if="item.locations?.length > 1"
                        @click="isModalLocation = true"
                        v-tooltip="`Other ${item.locations?.length - 1} locations`"
                        class="mr-1 cursor-pointer hover:bg-orange-50 whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-orange-300 rounded px-1"
                    >
                        <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                        {{ item.locations?.length - 1 }}
                    </span>

                    <!-- Location link -->
                    <span class="text-base">
                        <Link v-tooltip="`${item.warehouse_area}`" :href="generateLocationRoute(currentLocation)" class="secondaryLink">
                            {{ currentLocation.location_code }}
                        </Link>
                    </span>

                    <!-- Stock count -->
                    <span
                        v-tooltip="trans(':stockAvailable stock available on location :stockLocation', { stockAvailable: locale.number(currentLocation?.quantity || 0), stockLocation: currentLocation?.location_code || '' })"
                        class="align-middle whitespace-nowrap text-base py-0.5 tabular-nums border-gray-300 rounded"
                    >
                        (<span class="text-lg font-bold">
                            <FractionDisplay
                                v-if="currentLocation?.quantity_fractional"
                                :fractionData="currentLocation?.quantity_fractional"
                            />
                            <template v-else>
                                {{ locale.number(currentLocation?.quantity || 0) }}
                            </template>
                        </span>
                        <span class="text-sm ml-1">{{ ctrans("stocks") }}</span>)
                    </span>
                </div>
            </Transition>
        </template>

        <!-- Action buttons -->
        <div class="flex items-center gap-x-2">
            <!-- Section: input Quantity -->
            <NumberWithButtonSave
                v-if="!isStillPicking && currentLocation"
                :key="currentLocation.location_code"
                noUndoButton
                @onError="(error: any) => { errors = Object.values(error || {}) }"
                :modelValue="item.pickings?.find((p: any) => p.type === 'pick' && p.location_id == currentLocation?.location_id)?.quantity_picked ?? 0"
                @update:modelValue="() => errors = []"
                saveOnForm
                :routeSubmit="{
                    name: item.upsert_picking_route.name,
                    parameters: item.upsert_picking_route.parameters,
                }"
                :bindToTarget="{
                    step: 1,
                    min: 0,
                    max: Math.min(
                        Number(currentLocation?.quantity),
                        Number(item.quantity_waiting_warehouse) + Number(currentLocation?.quantity_picked)
                    ),
                }"
                :additionalData="{
                    location_org_stock_id: currentLocation.id,
                    picking_id: item.pickings?.find((p: any) => p.location_id == currentLocation?.location_id)?.id,
                }"
                autoSave
                isWithRefreshModel
                xreadonly="currentLocation.quantity <= 0"
            >
                <template #save="{ isProcessing }">
                    <div class="flex gap-x-8 w-fit">
                        <ButtonWithLink
                            v-tooltip="trans('Pick all required quantity in location :xlocation', { xlocation: currentLocation.location_code || '-' })"
                            icon="fal fa-clipboard-list-check"
                            :size="twBreakPoint().includes('lg') ? 'xs' : 'lg'"
                            type="secondary"
                            :loading="isProcessing"
                            class="py-0"
                            :routeTarget="item.picking_all_route"
                            :bind-to-link="{
                                preserveScroll: true,
                                preserveState: true,
                            }"
                            :body="{
                                location_org_stock_id: currentLocation.id,
                            }"
                            isWithError
                        >
                            <template #label>
                                <span>{{ locale.number(item.quantity_waiting_warehouse ?? 0) }}</span>
                            </template>
                        </ButtonWithLink>
                    </div>
                </template>
            </NumberWithButtonSave>

            <!-- Button: Not Picked -->
            <ButtonWithLink
                v-if="allowStockControllerSetNotPicked"
                type="negative"
                iconRight="fal fa-debug"
                :size="twBreakPoint().includes('lg') ? 'xs' : 'lg'"
                :routeTarget="{
                    method: 'post',
                    name: 'grp.models.delivery_note_item.not_picking_from_waiting_warehouse.store',
                    parameters: { deliveryNoteItem: item.id },
                }"
                :bindToLink="{ preserveScroll: true }"
                v-tooltip="trans('Set :numberNotPicked as not picked', { numberNotPicked: locale.number(item.quantity_waiting_warehouse) || '0' })"
            >
                <template #label>
                    <span>{{ locale.number(item.quantity_waiting_warehouse ?? 0) }}</span>
                </template>
            </ButtonWithLink>

            <!-- Button: Pass to CS -->
            <Button
                @click="isOpenModalPassToCs = true"
                icon="fal fa-user-headset"
                :label="trans('Pass :qtyInWarehouse to CS', { qtyInWarehouse: String(Number(item.quantity_waiting_warehouse)) })"
                :size="twBreakPoint().includes('lg') ? 'xs' : 'lg'"
                type="tertiary"
                class="!bg-purple-300 hover:!bg-purple-400/80 !text-purple-700 !border-purple-400 !py-2"
            />
        </div>
    </div>

    <!-- Error messages -->
    <div v-if="errors.length" class="mt-1">
        <p v-for="error in errors" :key="error" class="text-xs text-red-500 italic">*{{ error }}</p>
    </div>

    <!-- Modal: Location picker -->
    <PickingLocationModal
        :isOpen="isModalLocation"
        :item="item"
        :selectedLocationCode="selectedLocationCode"
        @close="isModalLocation = false"
        @select="(code) => { selectedLocationCode = code; isModalLocation = false; }"
    />

    <!-- Modal: Pass to CS -->
    <Modal :isOpen="isOpenModalPassToCs" width="w-full max-w-lg" @onClose="isOpenModalPassToCs = false">
        <PassWaitingItemsToCs
            v-model="isOpenModalPassToCs"
            :transaction="item"
        />
    </Modal>
</template>
