<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 13 September 2024 11:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Order } from "@/types/order";
import type { Table as TableTS } from "@/types/Table";
import Icon from "@/Components/Icon.vue";
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue";
import { debounce, get, set } from "lodash";
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import { routeType } from "@/types/route";
import { Collapse } from "vue-collapsed";
import { ref, onMounted, reactive, inject } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt } from "@fal";
import { faSkull } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import axios from "axios";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import deliveryNote from "@/Pages/Grp/Org/Dispatching/DeliveryNote.vue";
import Modal from "@/Components/Utils/Modal.vue"

library.add(faSkull, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt);


defineProps<{
    data: TableTS
    tab?: string
    state: string
}>();

const locale = inject("locale", aikuLocaleStructure);

function orgStockRoute(deliveryNoteItem: DeliverNoteItem) {
    // console.log(route().current())
    switch (route().current()) {
        case "grp.org.warehouses.show.dispatching.delivery-notes.show":
        // return route(
        //     "grp.org.shops.show.discounts.campaigns.show",
        //     [route().params["organisation"], , route().params["shop"], route().params["customer"], deliveryNote.slug])
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

    if (route().current() === "grp.org.warehouses.show.dispatching.delivery-notes.show") {
        return route(
            "grp.org.warehouses.show.infrastructure.locations.show",
            [
                route().params["organisation"],
                route().params["warehouse"],
                location.location_slug
            ]
        );
    } else if (route().current() === "grp.org.warehouses.show.dispatching.delivery-notes.show") {
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
        // console.log('qqqqq', pallet_stored_item)
    } catch (error) {
        console.error("hehehe", error);

    } finally {
        set(isLoadingUndoPick, loadingKey, false);
    }

};

// Section: Modal for location list
const isModalLocation = ref(false) 
const selectedItemValue = ref()
const selectedItemProxy = ref()
const onCloseModal = () => {
    isModalLocation.value = false

    setTimeout(() => {
        selectedItemValue.value = null
    }, 300);
}
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

        <!-- Column: Reference -->
        <template #cell(quantity_required)="{ item }">
            <span v-tooltip="item.quantity_required">{{ locale.number(item.quantity_required) }}</span>

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

        <template #cell(quantity_picked)="{ item: itemValue, proxyItem }">
            {{ itemValue.quantity_picked }}
        </template>


        <template #cell(quantity_to_pick)="{ item: item }">
            {{ item.quantity_to_pick }}
        </template>


        <!-- Column: Pickings -->
        <template #cell(pickings)="{ item }">
            <!-- <pre>{{ item.pickings }}</pre> -->
            <div v-if="item.pickings?.length" class="space-y-1">
                <div v-for="picking in item.pickings" :key="picking.id" class="flex gap-x-2 items-center">
                    <Link :href="generateLocationRoute(picking)" class="secondaryLink">
                        {{ picking.location_code }}
                    </Link>
                    
                    <div v-tooltip="trans('Total picked quantity in this location')" class="text-gray-500 whitespace-nowrap">
                        <FontAwesomeIcon icon="fal fa-inventory" class="mr text-gray-500" fixed-width aria-hidden="true" />
                        {{ picking.quantity_picked }}
                    </div>

                    <ButtonWithLink
                        v-if="!item.is_packed"
                        type="negative"
                        size="xs"
                        icon="fal fa-undo-alt"
                        label="Undo pick"
                        :routeTarget="picking.undo_picking_route"
                        :bindToLink="{ preserveScroll: true }"
                        @click="onUndoPick(picking.undo_picking_route, item, `undo-pick-${picking.id}`)"
                        :loading="get(isLoadingUndoPick, `undo-pick-${picking.id}`, false)"
                    />
                </div>

                <div v-if="item.quantity_not_picked" v-tooltip="trans('Quantity not gonna be picked')" class="text-red-500 w-fit mr-auto">
                    <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                    {{ item.quantity_not_picked }}
                </div>
            </div>

            <div v-else class="text-xs text-gray-400 italic">
                No item picked yet
            </div>
        </template>

        <!-- Column: actions -->
        <template #cell(handing_actions)="{ item: itemValue, proxyItem }">
            <!-- <pre>{{ itemValue }}</pre> -->
            <div v-if="itemValue.quantity_to_pick > 0">
                <div v-if="itemValue.locations?.[0]" class="rounded p-1 flex flex-col justify-between gap-x-6 items-center even:bg-black/5">
                    <!-- Action: decrease and increase quantity -->
                    <div class="mb-3 w-full flex justify-between gap-x-6 items-center">
                        <div class="">
                            <span v-if="itemValue.locations[0].location_code">
                                <Link :href="generateLocationRoute(itemValue.locations[0])" class="secondaryLink">
                                    {{ itemValue.locations[0].location_code }}
                                </Link>
                            </span>
                            <span v-else  v-tooltip="trans('Unknown location')" class="text-gray-400 italic">({{ trans("Unknown") }})</span>
                            <span
                                v-tooltip="trans('Total stock in this location')"
                                class="text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                                    <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                                {{ itemValue.locations[0].quantity }}
                            </span>
                        </div>
                        <div class="flex items-center flex-nowrap gap-x-2">
                            <!-- Button: input number (picking) -->
                            <NumberWithButtonSave
                                v-if="itemValue.locations[0].quantity > 0"
                                key="picking_picked"
                                noUndoButton
                                @onError="(error: any) => {
                                    proxyItem.errors = Object.values(error || {})
                                }"
                                :modelValue="itemValue.locations[0].quantity_picked"
                                @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                saveOnForm
                                :routeSubmit="{
                                    name: itemValue.upsert_picking_route.name,
                                    parameters: itemValue.upsert_picking_route.parameters,
                                }"
                                :bindToTarget="{
                                    step: 1,
                                    min: 0,
                                    max: Math.min(itemValue.locations[0].quantity, itemValue.quantity_required, itemValue.quantity_to_pick)
                                }"
                                :additionalData="{
                                    location_org_stock_id: itemValue.locations[0].id,
                                    picking_id: itemValue.pickings.find(picking => picking.location_id == itemValue.locations[0].location_id)?.id,
                                }"
                                autoSave
                                xxisWithRefreshModel
                                :readonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                            >
                                <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                    <ButtonWithLink
                                        v-tooltip="trans('Pick all required quantity in this location')"
                                        icon="fal fa-clipboard-list-check"
                                        :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                        :label="locale.number(itemValue.quantity_to_pick ?? 0 )"
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
                                            location_org_stock_id: itemValue.locations[0].id
                                        }"
                                        isWithError
                                    />

                                    <ButtonWithLink
                                        class="ml-8"
                                        v-if="!itemValue.is_handled"
                                        type="negative"
                                        tooltip="Set as not picked"
                                        icon="fal fa-debug"
                                        size="xs"
                                        :routeTarget="itemValue.not_picking_route"
                                        :bindToLink="{preserveScroll: true}"
                                    />
                                </template>
                            </NumberWithButtonSave>

                            <!-- Section: Errors list -->
                            <div v-if="proxyItem.errors?.length">
                                <p v-for="error in proxyItem.errors" class="text-xs text-red-500 italic">*{{ error }}</p>
                            </div>
                        </div>
                    </div>

                    <Button
                        v-if="itemValue.locations?.length > 1"
                        @click="() => {
                            isModalLocation = true;
                            selectedItemValue = itemValue;
                            selectedItemProxy = proxyItem;
                        }"
                        xlabel="See another locations"
                        type="tertiary"
                        full
                    >
                        <template #label>
                            <div class="text-gray-500">See another {{ itemValue.locations?.length - 1 }} locations</div>
                        </template>
                    </Button>
                </div>

                <div v-if="!itemValue.locations.every(location => {return location.quantity > 0})" class="">
                    <!-- <Collapse as="section" :when="get(proxyItem, ['is_open_collapsed'], false)" class="">
                        <div :id="`row-${itemValue.id}`">
                        </div>
                    </Collapse> -->

                    <!-- <div class="w-full mt-2">
                        <Button
                            v-if="!itemValue.locations.every(location => {return location.quantity > 0})"
                            @click="() => set(proxyItem, ['is_open_collapsed'], !get(proxyItem, ['is_open_collapsed'], false))"
                            type="dashed"
                            full
                            size="sm"
                        >
                            <div class="py-1 text-gray-500">
                                <FontAwesomeIcon icon="fal fa-arrow-down" class="transition-all" :class="get(proxyItem, ['is_open_collapsed'], false) ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
                                {{ get(proxyItem, ["is_open_collapsed"], false) ? trans("Close") : trans("Open hidden locations") }}
                            </div>
                        </Button>
                    </div> -->
                </div>
            </div>


            <!-- Button: Pack -->
            <div class="w-full max-w-32 mx-auto">
                <ButtonWithLink
                    v-if="itemValue.is_picked && !itemValue.is_packed"
                    :routeTarget="itemValue.packing_route"
                    :bindToLink="{
                        preserveScroll: true,
                        preserveState: true,
                    }"
                    full
                    type="secondary"
                    size="xs"
                    :label="trans('Pack')"
                />
            </div>

        </template>
    </Table>

    <Modal :isOpen="isModalLocation" @onClose="() => onCloseModal()" width="w-full max-w-xl">
        <div class="text-center font-semibold mb-4 text-2xl">
            Location list for {{ selectedItemValue?.org_stock_code }}:
            <!-- <pre>{{ selectedItemValue }}</pre> -->
        </div>

        <div class="rounded p-1 flex flex-col justify-between gap-x-6 items-center ">
            <div v-for="location in selectedItemValue?.locations" class="mb-3 w-full even:bg-black/5 flex justify-between gap-x-6 items-center px-2 py-1">
                <div class="">
                    <span v-if="location.location_code">
                        <Link :href="generateLocationRoute(location)" class="secondaryLink">
                            {{ location.location_code }}
                        </Link>
                    </span>
                    <span v-else  v-tooltip="trans('Unknown location')" class="text-gray-400 italic">({{ trans("Unknown") }})</span>

                    <span
                        v-tooltip="trans('Total stock in this location')"
                        class="text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                            <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                        {{ location.quantity }}
                    </span>
                </div>
            
                <div class="flex items-center flex-nowrap gap-x-2">
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
