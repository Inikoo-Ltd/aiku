<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 13 September 2024 11:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import type { Table as TableTS } from "@/types/Table";
import Icon from "@/Components/Icon.vue";
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue";
import { debounce, get, set } from 'lodash-es';
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import { routeType } from "@/types/route";
import { ref, onMounted, reactive, inject } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl } from "@fal";
import { faSkull } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import axios from "axios";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import Modal from "@/Components/Utils/Modal.vue"
import { RadioButton } from "primevue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"

library.add(faSkull, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl);


defineProps<{
    data: TableTS
    tab?: string
    state: string
}>();

const locale = inject("locale", aikuLocaleStructure);

function orgStockRoute(deliveryNoteItem: DeliverNoteItem) {
    console.log(deliveryNoteItem.org_stock_slug)
    switch (route().current()) {
        case "grp.org.warehouses.show.dispatching.delivery_notes.show":
         return route(
            "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show",
           [route().params["organisation"], route().params["warehouse"], deliveryNoteItem.org_stock_slug])
        default:
            return "";
    }
}


const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});

const onPickingQuantity = (pick_route: routeType, quantity: number) => {
    router[pick_route.method || "post"](
        route(pick_route.name, pick_route.parameters),
        {
            quantity: quantity
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: "",
                    type: "error"
                });
            },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: "",
                    type: "error"
                });
            }
        }
    );
};
const debounceOnPickingQuantity = debounce(onPickingQuantity, 500);


const generateLocationRoute = (location: any) => {
    if (!location.location_slug) {
        return "#";
    }

    if (route().current() === "grp.org.warehouses.show.dispatching.delivery_notes.show") {
        return route(
            "grp.org.warehouses.show.infrastructure.locations.show",
            [
                route().params["organisation"],
                route().params["warehouse"],
                location.location_slug
            ]
        );
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
        return "#";
    }

};


// Button: undo pick
const isLoadingUndoPick = reactive({});
const onUndoPick = async (routeTarget: routeType, pallet_stored_item: any, loadingKey: string) => {
    try {
        pallet_stored_item.isLoadingUndo = true;
        set(isLoadingUndoPick, loadingKey, true);
        await axios[routeTarget.method || "get"](
            route(routeTarget.name, routeTarget.parameters)
        );
        pallet_stored_item.state = "picking";
    } catch (error) {
        console.error("error:", error);

    } finally {
        set(isLoadingUndoPick, loadingKey, false);
    }

};

// Section: Modal for a location list
const isModalLocation = ref(false) 
const selectedItemValue = ref()
const selectedItemProxy = ref()
const onCloseModal = () => {
    isModalLocation.value = false

    setTimeout(() => {
        selectedItemValue.value = null
    }, 300);
}

// Method: to find the location that Alt ed, fallback is index 0
const findLocation = (locationsList: {location_code: string}[], selectedHehe: string) => {
    return locationsList.find(x => x.location_code == selectedHehe) || locationsList[0]
}

const breakpoint = ref('')
const innerWidth = ref(0)
onMounted(() => {
    innerWidth.value = window.innerWidth
})
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>
        <!-- Column: state -->
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <!-- Column: Reference -->
        <template #cell(org_stock_code)="{ item: deliveryNoteItem }">
            <Link :href="orgStockRoute(deliveryNoteItem)" class="primaryLink">
                {{ deliveryNoteItem.org_stock_code }}
            </Link>
        </template>

        <!-- Column: Quantity Required -->
        <template #cell(quantity_required)="{ item }">

            <span v-tooltip="item.quantity_required">
                <FractionDisplay  v-if="item.quantity_required_fractional"   :fractionData="item.quantity_required_fractional" />
                <span v-else>{{item.quantity_required}}</span>

            </span>

            <template v-if="state === 'handling'">
                <span v-if="item.quantity_to_pick > 0" class="whitespace-nowrap space-x-2">

                    <ButtonWithLink
                        v-if="!item.is_handled"
                        type="negative"
                        :label="locale.number(item.quantity_to_pick)"
                        tooltip="Set as not picked"
                        icon="fal fa-debug"
                        size="xs"
                        :routeTarget="item.not_picking_route"
                        :bindToLink="{
                            preserveScroll: true,
                        }"
                    />
                </span>

                <div v-else v-tooltip="trans('Quantity not gonna be picked')" class="text-red-500 w-fit ml-auto">
                    <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                    {{ item.quantity_not_picked }}
                </div>
            </template>

        </template>

        <template #cell(quantity_picked)="{ item: item, proxyItem }">
            <FractionDisplay  v-if="item.quantity_picked_fractional"   :fractionData="item.quantity_picked_fractional" />
            <span v-else>{{item.quantity_picked}}</span>

        </template>

        <template #cell(quantity_packed)="{ item: item, proxyItem }">
            <FractionDisplay  v-if="item.quantity_packed_fractional"   :fractionData="item.quantity_packed_fractional" />
            <span v-else>{{item.quantity_packed}}</span>

        </template>


        <template #cell(quantity_to_pick)="{ item: item }">
            {{ item.quantity_to_pick }}
        </template>


        <!-- Column: Pickings -->
        <template #cell(pickings)="{ item }">
            <div v-if="item.pickings?.length" class="space-y-1">
                <div v-for="picking in item.pickings" :key="picking.id" class="flex gap-x-2 w-fit">
                    <!-- {{ picking.location_code }} -->
                    <div v-if="picking.type === 'pick'" class="flex gap-x-2 items-center">
                        <Link :href="generateLocationRoute(picking)" class="secondaryLink">
                            {{ picking.location_code }}
                        </Link>
                    
                        <div v-tooltip="trans('Total picked quantity in this location')" class="text-gray-500 whitespace-nowrap">
                            <FontAwesomeIcon icon="fal fa-hand-holding-box" class="mr text-gray-500" fixed-width aria-hidden="true" />
                            <FractionDisplay v-if="picking.quantity_picked_fractional" :fractionData="picking.quantity_picked_fractional" />
                            <span v-else>
                                {{ picking.quantity_picked }}
                            </span>
                        </div>
                    </div>
                    
                    <div v-if="picking.type === 'not-pick'" v-tooltip="trans('Quantity not gonna be picked')" class="text-red-500 w-fit mr-auto">
                        <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                        <FractionDisplay v-if="picking.quantity_picked_fractional" :fractionData="picking.quantity_picked_fractional" />
                        <span v-else>
                            {{ picking.quantity_picked }}
                        </span>
                    </div>

                    <div class="hidden lg:block">
                        <ButtonWithLink
                            v-if="!item.is_packed"
                            v-tooltip="trans('Undo')"
                            type="negative"
                            size="xxs"
                            icon="fal fa-undo-alt"
                            :routeTarget="picking.undo_picking_route"
                            :bindToLink="{ preserveScroll: true }"
                            @click="onUndoPick(picking.undo_picking_route, item, `undo-pick-${picking.id}`)"
                            :loading="get(isLoadingUndoPick, `undo-pick-${picking.id}`, false)"
                        />
                    </div>

                    <div class="lg:hidden">
                        <ButtonWithLink
                            v-if="!item.is_packed"
                            v-tooltip="trans('Undo')"
                            type="negative"
                            size="sm"
                            icon="fal fa-undo-alt"
                            :routeTarget="picking.undo_picking_route"
                            :bindToLink="{ preserveScroll: true }"
                            @click="onUndoPick(picking.undo_picking_route, item, `undo-pick-${picking.id}`)"
                            :loading="get(isLoadingUndoPick, `undo-pick-${picking.id}`, false)"
                        />
                    </div>
                </div>

            </div>

            <div v-else class="text-xs text-gray-400 italic">
                {{ trans("No item picked yet") }}
            </div>
        </template>

        <!-- Column: actions -->
        <template #cell(picking_position)="{ item: itemValue, proxyItem }">

            <div v-if="itemValue.quantity_to_pick > 0">
                <div v-if="findLocation(itemValue.locations, proxyItem.hehe)" class="flex flex-col justify-between gap-x-6 items-center">
                    <!-- Action: decrease and increase quantity -->
                    <div class="mb-3 w-full flex justify-between gap-x-6 xitems-center">
                        <div class="">
                            <Transition name="spin-to-right">
                                <div :key="findLocation(itemValue.locations, proxyItem.hehe).location_code">
                                    <span v-if="findLocation(itemValue.locations, proxyItem.hehe)">
                                        <Link
                                            v-tooltip="`${itemValue.warehouse_area}`"
                                            :href="generateLocationRoute(findLocation(itemValue.locations, proxyItem.hehe))"
                                              class="secondaryLink">
                                            {{ findLocation(itemValue.locations, proxyItem.hehe).location_code }}
                                        </Link>
                                    </span>
                                    <span v-else  v-tooltip="trans('Unknown location')" class="text-gray-400 italic">
                                        ({{ trans("Unknown") }})
                                    </span>
                                    <span
                                        v-tooltip="trans('Total stock is :quantity in location :location_code', {quantity: locale.number(findLocation(itemValue.locations, proxyItem.hehe)?.quantity), location_code: findLocation(itemValue.locations, proxyItem.hehe)?.location_code})"
                                        class="whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                                        <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                                        <FractionDisplay v-if="findLocation(itemValue.locations, proxyItem.hehe)?.quantity_fractional" :fractionData="findLocation(itemValue.locations, proxyItem.hehe)?.quantity_fractional" />
                                        <template v-else>{{ locale.number(findLocation(itemValue.locations, proxyItem.hehe).quantity) }}</template>
                                    </span>

                                    <span
                                        v-if="itemValue.locations?.length > 1"
                                        @click="() => {
                                            isModalLocation = true;
                                            selectedItemValue = itemValue;
                                            selectedItemProxy = proxyItem;
                                        }"
                                        v-tooltip="`Other ${itemValue.locations?.length - 1} locations`"
                                        class="cursor-pointer hover:bg-orange-50 ml-1 whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-orange-300 rounded px-1">
                                        <FontAwesomeIcon icon="fal fa-list-ol" class="mr-1" fixed-width aria-hidden="true" />
                                        {{ itemValue.locations?.length - 1 }}
                                    </span>
                                </div>
                            </Transition>
                            

                        </div>
                        
                        <div class="flex items-center flex-nowrap gap-x-2">
                            <!-- Button: input number (picking) -->
                            <NumberWithButtonSave
                                v-if="!itemValue.is_handled && findLocation(itemValue.locations, proxyItem.hehe).quantity > 0"
                                :key="findLocation(itemValue.locations, proxyItem.hehe).location_code"
                                noUndoButton
                                @onError="(error: any) => {
                                    proxyItem.errors = Object.values(error || {})
                                }"
                                :modelValue="findLocation(itemValue.locations, proxyItem.hehe).quantity_picked"
                                @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                saveOnForm
                                :routeSubmit="{
                                    name: itemValue.upsert_picking_route.name,
                                    parameters: itemValue.upsert_picking_route.parameters,
                                }"
                                :bindToTarget="{
                                    step: 1,
                                    min: 0,
                                    max: Math.min(findLocation(itemValue.locations, proxyItem.hehe).quantity, itemValue.quantity_required, (itemValue.quantity_to_pick + findLocation(itemValue.locations, proxyItem.hehe).quantity_picked))
                                }"
                                :additionalData="{
                                    location_org_stock_id: findLocation(itemValue.locations, proxyItem.hehe).id,
                                    picking_id: itemValue.pickings.find(picking => picking.location_id == findLocation(itemValue.locations, proxyItem.hehe).location_id)?.id,
                                }"
                                autoSave
                                xxisWithRefreshModel
                                :readonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                            >
                                <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                    <div class="hidden lg:flex gap-x-8 w-fit">
                                        <ButtonWithLink
                                            v-tooltip="trans('Pick all required quantity in this location')"
                                            icon="fal fa-clipboard-list-check"
                                            :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
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
                                                location_org_stock_id: findLocation(itemValue.locations, proxyItem.hehe).id
                                            }"
                                            isWithError
                                        >
                                            <template #label>
                                                <div>
                                                    <FractionDisplay v-if="itemValue.quantity_to_pick_fractional" :fractionData="itemValue.quantity_to_pick_fractional" />
                                                    <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0 ) }}</span>
                                                </div>
                                            </template>
                                        </ButtonWithLink>
                                    </div>
                                    
                                    <div class="lg:hidden space-y-1">
                                        <ButtonWithLink
                                            v-tooltip="trans('Pick all required quantity in this location')"
                                            icon="fal fa-clipboard-list-check"
                                            :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                            xsize="md"
                                            type="secondary"
                                            :loading="isProcessing"
                                            class="py-0"
                                            :routeTarget="itemValue.picking_all_route"
                                            :bind-to-link="{
                                                preserveScroll: true,
                                                preserveState: true,
                                            }"
                                            :body="{
                                                location_org_stock_id: findLocation(itemValue.locations, proxyItem.hehe).id
                                            }"
                                            isWithError
                                            full
                                        >
                                            <template #label>
                                                <div>
                                                    <FractionDisplay v-if="itemValue.quantity_to_pick_fractional" :fractionData="itemValue.quantity_to_pick_fractional" />
                                                    <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0 ) }}</span>
                                                </div>
                                            </template>
                                        </ButtonWithLink>
                                    </div>
                                </template>
                            </NumberWithButtonSave>

                            
                            <ButtonWithLink
                                v-if="!itemValue.is_handled"
                                type="negative"
                                tooltip="Set as not picked"
                                icon="fal fa-debug"
                                :size="innerWidth > 768 ? undefined : 'lg'"
                                :routeTarget="itemValue.not_picking_route"
                                :bindToLink="{preserveScroll: true}"
                            />

                            <!-- Section: Errors list -->
                            <div v-if="proxyItem.errors?.length">
                                <p v-for="error in proxyItem.errors" class="text-xs text-red-500 italic">*{{ error }}</p>
                            </div>
                        </div>
                    </div>


                </div>
            </div>

            <div v-else>
                <ButtonWithLink
                    v-if="!itemValue.is_handled"
                    type="negative"
                    tooltip="Set as not picked"
                    icon="fal fa-debug"
                    :size="innerWidth > 768 ? undefined : 'lg'"
                    :routeTarget="itemValue.not_picking_route"
                    :bindToLink="{preserveScroll: true}"
                />
            </div>




        </template>
    </Table>

    <Modal :isOpen="isModalLocation" @onClose="() => onCloseModal()" width="w-full max-w-2xl" :dialogStyle="{
        background: '#ffffffcc'
    }">
        <div class="text-center font-semibold mb-4 text-2xl">
            Location list for {{ selectedItemValue?.org_stock_code }}:

        </div>

        <div class="rounded p-1 grid grid-cols-3 justify-between gap-x-6 items-center divide-x divide-gray-300">
            <div v-for="location in selectedItemValue?.locations" class="bg-white rounded mb-3 w-full xeven:bg-black/5 flex gap-x-3 items-center px-2 py-1">
                <label :for="location.location_code">
                    <span
                        v-if="location.location_code"
                        v-tooltip="location.quantity <= 0 ? 'Location has no stock' : ''"
                        :class="location.quantity <= 0 ? 'text-gray-400' : ''"

                    >
                        <Link :href="generateLocationRoute(location)" class="bg-gradient-to-t from-yellow-300/50 to-yellow-200/50 focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1;">
                            {{ location.location_code }}
                        </Link>
                    </span>
                    <span v-else  v-tooltip="trans('Unknown location')" class="text-gray-400 italic">({{ trans("Unknown") }})</span>

                    <span
                        v-tooltip="trans('Total stock is :quantity in location :location_code', {quantity: locale.number(location.quantity), location_code: location.location_code})"
                        class="ml-1 whitespace-nowrap text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                        <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                        <FractionDisplay v-if="location.quantity_fractional" :fractionData="location.quantity_fractional" />
                        <template v-else>{{ location.quantity }}</template>
                    </span>
                </label>
                <RadioButton
                    v-model="selectedItemProxy.hehe"
                    @update:modelValue="() => {
                        onCloseModal()
                    }"
                    :inputId="location.location_code"
                    :disabled="location.quantity <= 0"
                    name="location"
                    :value="location.location_code"
                />

                <div v-if="false" class="flex items-center flex-nowrap gap-x-2">
                    <!-- Button: input number (picking) -->
                    <NumberWithButtonSave
                        v-if="location.quantity > 0"
                        key="picking_picked"
                        noUndoButton
                        @onError="(error: any) => {
                            selectedItemProxy.errors = Object.values(error || {})
                        }"
                        :modelValue="location.quantity_picked"
                        @update:modelValue="() => selectedItemProxy?.errors ? selectedItemProxy.errors = null : undefined"
                        saveOnForm
                        :routeSubmit="{
                            name: selectedItemValue.upsert_picking_route.name,
                            parameters: selectedItemValue.upsert_picking_route.parameters,
                        }"
                        :bindToTarget="{
                            step: 1,
                            min: 0,
                            max: Math.min(location.quantity, selectedItemValue.quantity_required, selectedItemValue.quantity_to_pick)
                        }"
                        :additionalData="{
                            location_org_stock_id: location.id,
                            picking_id: selectedItemValue.pickings.find(picking => picking.location_id == location.location_id)?.id,
                        }"
                        autoSave
                        xxisWithRefreshModel
                        :readonly="selectedItemValue.is_handled || selectedItemValue.quantity_required == selectedItemValue.quantity_picked"
                    >
                        <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                            <ButtonWithLink
                                v-tooltip="trans('Pick all required quantity in this location')"
                                icon="fal fa-clipboard-list-check"
                                :disabled="selectedItemValue.is_handled || selectedItemValue.quantity_required == selectedItemValue.quantity_picked"
                                :label="locale.number(selectedItemValue.quantity_to_pick )"
                                size="xs"
                                type="secondary"
                                :loading="isProcessing"
                                class="py-0"
                                :routeTarget="selectedItemValue.picking_all_route"
                                :bind-to-link="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }"
                                :body="{
                                    location_org_stock_id: location.id
                                }"
                                isWithError
                            />
                            <ButtonWithLink
                                class="ml-8"
                                v-if="!selectedItemValue.is_handled"
                                type="negative"
                                tooltip="Set as not picked"
                                icon="fal fa-debug"
                                size="xs"
                                :routeTarget="selectedItemValue.not_picking_route"
                                :bindToLink="{preserveScroll: true}"
                            />
                        </template>
                    </NumberWithButtonSave>

                    <div v-else class="text-gray-400 italic">
                        {{ trans("No quantity available to pick") }}
                    </div>
                    
                    <!-- Section: Errors list -->
                    <div v-if="selectedItemProxy?.errors?.length">
                        <p v-for="error in selectedItemProxy.errors" class="text-xs text-red-500 italic">*{{ error }}</p>
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>
