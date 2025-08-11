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
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faHandPaper, faChair, faBoxCheck, faCheckDouble, faTimes } from "@fal";
import { faSkull, faStickyNote } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import axios from "axios";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import Modal from "@/Components/Utils/Modal.vue"
import { RadioButton } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue";
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import { faPencil } from "@far";
import MiniDeliveryNote from "@/Components/MiniDeliveryNote.vue";
import Drawer from 'primevue/drawer';
import InformationIcon from "@/Components/Utils/InformationIcon.vue"

library.add(faSkull, faStickyNote, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faHandPaper, faChair, faBoxCheck, faCheckDouble, faTimes);


const props = defineProps<{
    data: TableTS
    tab?: string
    pickingSession: object
}>();

const locale = inject("locale", aikuLocaleStructure);

const modalDetail = ref(false)



function showdeliveryNoteRoute(deliveryNoteItem) {
    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.picking_sessions.show':
        default:
            return route(
                "grp.org.warehouses.show.dispatching.delivery_notes.show",
                [
                    route().params["organisation"],
                    route().params["warehouse"],
                    deliveryNoteItem.delivery_note_slug
                ]
            );
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
    } else if (route().current() === 'grp.org.warehouses.show.dispatching.picking_sessions.show') {
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

// Method: to find location that Alt ed, fallback is index 0
const findLocation = (locationsList: { location_code: string }[], selectedHehe: string) => {
    return locationsList.find(x => x.location_code == selectedHehe) || locationsList[0]
}

const packedLoading = ref<Set<number>>(new Set());
const isPacking = (id: number) => packedLoading.value.has(id)


const DeliveryNoteInModal = ref(null)

const onCloseModalDetail = () => {
    modalDetail.value = false
    DeliveryNoteInModal.value = null
    router.reload()
}

const onOpenModalDetail = (deliveryNote) => {
    modalDetail.value = true
    DeliveryNoteInModal.value = deliveryNote
}

console.log('props', props.pickingSession)

// Section: Note
const isModalNote = ref(false)
const selectedDelivery = ref(null)
</script>

<template>
    <Table :resource="data" class="mt-5" rowAlignTop :name="tab">
        <!-- Column: state -->
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <template #cell(delivery_note_state)="{ item }">
            <Icon :data="item.delivery_note_state_icon" />
        </template>


        <template #cell(delivery_note_reference)="{ item }">
            <div>
                <Link :href="showdeliveryNoteRoute(item)" class="primaryLink">
                    {{ item?.delivery_note_reference }}
                </Link>
            </div>
            
            <div v-if="item.delivery_note_shipping_notes" @click="() => (isModalNote = true, selectedDelivery = item)" class="text-[rgb(56, 189, 248)] cursor-pointer mt-1">

                <Button
                    size="xxs"
                    key=""
                    type="tertiary"
                    :label="trans('Open shipping notes')"
                >
                    <template #icon>
                        <FontAwesomeIcon icon="fas fa-sticky-note" class="text-[rgb(56,189,248)]" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </template>



        <!-- Column: Quantity Required -->
        <template #cell(quantity_required)="{ item }">
            <span v-tooltip="item.quantity_required">
                <FractionDisplay v-if="item.quantity_required_fractional"
                    :fractionData="item.quantity_required_fractional" />
                <span v-else>{{ item.quantity_required }}</span>

            </span>

            <template v-if="state === 'handling'">
                <span v-if="item.quantity_to_pick > 0" class="whitespace-nowrap space-x-2">

                    <ButtonWithLink v-if="!item.is_handled" type="negative"
                        :label="locale.number(item.quantity_to_pick)" tooltip="Set as not picked" icon="fal fa-debug"
                        size="xs" :routeTarget="item.not_picking_route" :bindToLink="{
                            preserveScroll: true,
                        }" />
                </span>

                <div v-else v-tooltip="trans('Quantity not gonna be picked')" class="text-red-500 w-fit ml-auto">
                    <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                    {{ item.quantity_not_picked }}
                </div>
            </template>

        </template>

        <template #cell(quantity_picked)="{ item: item, proxyItem }">
            <FractionDisplay v-if="item.quantity_picked_fractional" :fractionData="item.quantity_picked_fractional" />
            <span v-else>{{ item.quantity_picked }}</span>
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
                        <Link :href="generateLocationRoute(picking)" class="secondaryLink" >
                        {{ picking.location_code }}
                        </Link>

                        <div v-tooltip="trans('Total picked quantity in this location')"
                            class="text-gray-500 whitespace-nowrap">
                            <FontAwesomeIcon icon="fal fa-hand-holding-box" class="mr text-gray-500" fixed-width
                                aria-hidden="true" />
                            <FractionDisplay v-if="picking.quantity_picked_fractional"
                                :fractionData="picking.quantity_picked_fractional" />
                            <span v-else>
                                {{ picking.quantity_picked }}
                            </span>
                        </div>
                    </div>

                    <div v-if="picking.type === 'not-pick'" v-tooltip="trans('Quantity not gonna be picked')"
                        class="text-red-500 w-fit mr-auto">
                        <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                        <FractionDisplay v-if="picking.quantity_picked_fractional"
                            :fractionData="picking.quantity_picked_fractional" />
                        <span v-else>
                            {{ picking.quantity_picked }}
                        </span>
                    </div>

                    <ButtonWithLink v-if="!item.is_packed && pickingSession.state == 'handling'"
                        v-tooltip="trans('Undo')" type="negative" size="xxs" icon="fal fa-undo-alt"
                        :routeTarget="picking.undo_picking_route" :bindToLink="{ preserveScroll: true }"
                        @click="onUndoPick(picking.undo_picking_route, item, `undo-pick-${picking.id}`)"
                        :loading="get(isLoadingUndoPick, `undo-pick-${picking.id}`, false)" />
                </div>

            </div>

            <div v-else class="text-xs text-gray-400 italic">
                {{ trans("No item picked yet") }}
            </div>
        </template>

        <template #cell(items)="{ item: itemValue, proxyItem }">
            <div v-for="(deliveryItem, index) in itemValue.items" :key="deliveryItem.id || index" class="space-y-2">

                <div class="flex justify-between items-center">
                    <div>{{ deliveryItem.org_stock_code }}</div>

                    <template v-if="deliveryItem.quantity_to_pick > 0 && deliveryItem.state == 'handling'">
                        <div v-if="findLocation(deliveryItem.locations, proxyItem.hehe)"
                            class="rounded p-1 flex flex-col justify-between gap-x-6 items-center">
                            <div class="mb-3 w-full flex justify-between gap-x-6 items-center">
                                <!-- Location Info -->
                                <div>
                                    <Transition name="spin-to-right">
                                        <div :key="findLocation(deliveryItem.locations, proxyItem.hehe).location_code">
                                            <Link v-if="findLocation(deliveryItem.locations, proxyItem.hehe)"
                                                :href="generateLocationRoute(findLocation(deliveryItem.locations, proxyItem.hehe))"
                                                class="secondaryLink">
                                            {{ findLocation(deliveryItem.locations, proxyItem.hehe).location_code }}
                                            </Link>
                                            <span v-else v-tooltip="trans('Unknown location')"
                                                class="text-gray-400 italic">
                                                ({{ trans("Unknown") }})
                                            </span>

                                            <span v-tooltip="trans('Total stock in this location')"
                                                class="whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                                                <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width
                                                    aria-hidden="true" />
                                                {{ findLocation(deliveryItem.locations, proxyItem.hehe).quantity }}
                                            </span>

                                            <span v-if="deliveryItem.locations?.length > 1" @click="() => {
                                                isModalLocation = true;
                                                selectedItemValue = deliveryItem;
                                                selectedItemProxy = proxyItem;
                                            }" v-tooltip="`Other ${deliveryItem.locations.length - 1} locations`"
                                                class="cursor-pointer hover:bg-orange-50 ml-1 whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-orange-300 rounded px-1">
                                                <FontAwesomeIcon icon="fal fa-list-ol" class="mr-1" fixed-width
                                                    aria-hidden="true" />
                                                {{ deliveryItem.locations.length - 1 }}
                                            </span>
                                        </div>
                                    </Transition>
                                </div>

                                <!-- Quantity Picker -->
                                <div class="flex items-center flex-nowrap gap-x-2">
                                    <NumberWithButtonSave
                                        v-if="!deliveryItem.is_handled && findLocation(deliveryItem.locations, proxyItem.hehe).quantity > 0"
                                        :key="findLocation(deliveryItem.locations, proxyItem.hehe).location_code"
                                        noUndoButton
                                        @onError="(error: any) => proxyItem.errors = Object.values(error || {})"
                                        :modelValue="findLocation(deliveryItem.locations, proxyItem.hehe).quantity_picked"
                                        @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                        saveOnForm :routeSubmit="{
                                            name: deliveryItem.upsert_picking_route.name,
                                            parameters: deliveryItem.upsert_picking_route.parameters
                                        }" :bindToTarget="{
                                            step: 1,
                                            min: 0,
                                            max: Math.min(
                                                findLocation(deliveryItem.locations, proxyItem.hehe).quantity,
                                                deliveryItem.quantity_required,
                                                deliveryItem.quantity_to_pick + findLocation(deliveryItem.locations, proxyItem.hehe).quantity_picked
                                            )
                                        }" :additionalData="{
                                            location_org_stock_id: findLocation(deliveryItem.locations, proxyItem.hehe).id,
                                            picking_id: deliveryItem.pickings.find(p => p.location_id === findLocation(deliveryItem.locations, proxyItem.hehe).location_id)?.id
                                        }" autoSave xxisWithRefreshModel
                                        :readonly="deliveryItem.is_handled || deliveryItem.quantity_required === deliveryItem.quantity_picked">
                                        <template #save="{ isProcessing }">
                                            <ButtonWithLink
                                                v-tooltip="trans('Pick all required quantity in this location')"
                                                icon="fal fa-clipboard-list-check"
                                                :disabled="deliveryItem.is_handled || deliveryItem.quantity_required === deliveryItem.quantity_picked"
                                                size="xs" type="secondary" :loading="isProcessing"
                                                :routeTarget="deliveryItem.picking_all_route" :bind-to-link="{
                                                    preserveScroll: true,
                                                    preserveState: true
                                                }" :body="{
                                                    location_org_stock_id: findLocation(deliveryItem.locations, proxyItem.hehe).id
                                                }" isWithError>
                                                <template #label>
                                                    <FractionDisplay v-if="deliveryItem.quantity_to_pick_fractional"
                                                        :fractionData="deliveryItem.quantity_to_pick_fractional" />
                                                    <span v-else>{{ locale.number(deliveryItem.quantity_to_pick ?? 0)
                                                        }}</span>
                                                </template>
                                            </ButtonWithLink>
                                        </template>
                                    </NumberWithButtonSave>

                                    <!-- Not Picked Button -->
                                    <ButtonWithLink v-if="!deliveryItem.is_handled" type="negative"
                                        tooltip="Set as not picked" icon="fal fa-debug" size="xs"
                                        :routeTarget="deliveryItem.not_picking_route"
                                        :bindToLink="{ preserveScroll: true }" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Set as Packed Button -->
                    <!-- <Link
                        v-if="pickingSession.state === 'picking_finished' && deliveryItem.delivery_note_state === 'handling'"
                        method="patch" @start="packedLoading.add(deliveryItem.id)"
                        :href="route('grp.models.delivery_note.state.packed', { deliveryNote: deliveryItem.delivery_note_id })"
                        @finish="packedLoading.delete(deliveryItem.id)" class="mx-3"> -->
                        <Button  v-if="pickingSession.state === 'picking_finished' && deliveryItem.delivery_note_state === 'handling'" type="save" label="Set as packed" size="sm"  @click="onOpenModalDetail(deliveryItem)"/>
                    <!-- </Link> -->



                    <div v-if="deliveryItem.pickings?.length && deliveryItem.state == 'handling'" class="space-y-1">
                        <div v-for="picking in deliveryItem.pickings" :key="picking.id" class="flex gap-x-2 w-fit">
                            <!-- {{ picking.location_code }} -->
                            <div v-if="picking.type === 'pick'" class="flex gap-x-2 items-center">
                                <Link :href="generateLocationRoute(picking)" class="secondaryLink">
                                {{ picking.location_code }}
                                </Link>

                                <div v-tooltip="trans('Total picked quantity in this location')"
                                    class="text-gray-500 whitespace-nowrap">
                                    <FontAwesomeIcon icon="fal fa-hand-holding-box" class="mr text-gray-500" fixed-width
                                        aria-hidden="true" />
                                    <FractionDisplay v-if="picking.quantity_picked_fractional"
                                        :fractionData="picking.quantity_picked_fractional" />
                                    <span v-else>
                                        {{ picking.quantity_picked }}
                                    </span>
                                </div>
                            </div>

                            <div v-if="picking.type === 'not-pick'" v-tooltip="trans('Quantity not gonna be picked')"
                                class="text-red-500 w-fit mr-auto">
                                <FontAwesomeIcon icon="fas fa-skull" class="" fixed-width aria-hidden="true" />
                                <FractionDisplay v-if="picking.quantity_picked_fractional"
                                    :fractionData="picking.quantity_picked_fractional" />
                                <span v-else>
                                    {{ picking.quantity_picked }}
                                </span>
                            </div>

                            <ButtonWithLink v-if="!deliveryItem.is_packed && deliveryItem.state == 'handling'"
                                v-tooltip="trans('Undo')" type="negative" size="xxs" icon="fal fa-undo-alt"
                                :routeTarget="picking.undo_picking_route" :bindToLink="{ preserveScroll: true }"
                                @click="onUndoPick(picking.undo_picking_route, deliveryItem, `undo-pick-${picking.id}`)"
                                :loading="get(isLoadingUndoPick, `undo-pick-${picking.id}`, false)" />
                        </div>

                    </div>

                </div>
            </div>


        </template>


        <!-- Column: actions -->
        <template #cell(picking_position)="{ item: itemValue, proxyItem }">
            <div v-if="itemValue.quantity_to_pick > 0 && pickingSession.state == 'handling'">
                <div v-if="findLocation(itemValue.locations, proxyItem.hehe)"
                    class="rounded p-1 flex flex-col justify-between gap-x-6 items-center even:bg-black/5">
                    <!-- Action: decrease and increase quantity -->
                    <div class="mb-3 w-full flex justify-between gap-x-6 items-center">
                        <div class="">
                            <Transition name="spin-to-right">
                                <div :key="findLocation(itemValue.locations, proxyItem.hehe).location_code">
                                    <span v-if="findLocation(itemValue.locations, proxyItem.hehe)">
                                        <Link v-tooltip="`${itemValue.warehouse_area}`"
                                            :href="generateLocationRoute(findLocation(itemValue.locations, proxyItem.hehe))"
                                            class="secondaryLink">
                                        {{ findLocation(itemValue.locations, proxyItem.hehe).location_code }}
                                        </Link>
                                    </span>
                                    <span v-else v-tooltip="trans('Unknown location')" class="text-gray-400 italic">
                                        ({{ trans("Unknown") }})
                                    </span>
                                    <span v-tooltip="trans('Total stock in this location')"
                                        class="whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                                        <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width
                                            aria-hidden="true" />
                                        {{ findLocation(itemValue.locations, proxyItem.hehe).quantity }}
                                    </span>

                                    <span v-if="itemValue.locations?.length > 1" @click="() => {
                                        isModalLocation = true;
                                        selectedItemValue = itemValue;
                                        selectedItemProxy = proxyItem;
                                    }" v-tooltip="`Other ${itemValue.locations?.length - 1} locations`"
                                        class="cursor-pointer hover:bg-orange-50 ml-1 whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-orange-300 rounded px-1">
                                        <FontAwesomeIcon icon="fal fa-list-ol" class="mr-1" fixed-width
                                            aria-hidden="true" />
                                        {{ itemValue.locations?.length - 1 }}
                                    </span>
                                </div>
                            </Transition>


                        </div>

                        <div class="flex items-center flex-nowrap gap-x-2">
                            <NumberWithButtonSave
                                v-if="!itemValue.is_handled && findLocation(itemValue.locations, proxyItem.hehe).quantity > 0"
                                :key="findLocation(itemValue.locations, proxyItem.hehe).location_code" noUndoButton
                                @onError="(error: any) => {
                                    proxyItem.errors = Object.values(error || {})
                                }" :modelValue="findLocation(itemValue.locations, proxyItem.hehe).quantity_picked"
                                @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                saveOnForm :routeSubmit="{
                                    name: itemValue.upsert_picking_route.name,
                                    parameters: itemValue.upsert_picking_route.parameters,
                                }" :bindToTarget="{
                                    step: 1,
                                    min: 0,
                                    max: Math.min(findLocation(itemValue.locations, proxyItem.hehe).quantity, itemValue.quantity_required, (itemValue.quantity_to_pick + findLocation(itemValue.locations, proxyItem.hehe).quantity_picked))
                                }" :additionalData="{
                                    location_org_stock_id: findLocation(itemValue.locations, proxyItem.hehe).id,
                                    picking_id: itemValue.pickings.find(picking => picking.location_id == findLocation(itemValue.locations, proxyItem.hehe).location_id)?.id,
                                }" autoSave xxisWithRefreshModel
                                :readonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked">
                                <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                    <div class="hidden lg:flex gap-x-8 w-fit">
                                        <ButtonWithLink v-tooltip="trans('Pick all required quantity in this location')"
                                            icon="fal fa-clipboard-list-check"
                                            :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                            size="xs" type="secondary" :loading="isProcessing" class="py-0"
                                            :routeTarget="itemValue.picking_all_route" :bind-to-link="{
                                                preserveScroll: true,
                                                preserveState: true,
                                            }" :body="{
                                                location_org_stock_id: findLocation(itemValue.locations, proxyItem.hehe).id
                                            }" isWithError>
                                            <template #label>
                                                <div>
                                                    <FractionDisplay v-if="itemValue.quantity_to_pick_fractional"
                                                        :fractionData="itemValue.quantity_to_pick_fractional" />
                                                    <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0)
                                                    }}</span>
                                                </div>
                                            </template>
                                        </ButtonWithLink>
                                    </div>

                                    <div class="lg:hidden space-y-1">
                                        <ButtonWithLink v-tooltip="trans('Pick all required quantity in this location')"
                                            icon="fal fa-clipboard-list-check"
                                            :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                            xsize="md" type="secondary" :loading="isProcessing" class="py-0"
                                            :routeTarget="itemValue.picking_all_route" :bind-to-link="{
                                                preserveScroll: true,
                                                preserveState: true,
                                            }" :body="{
                                                location_org_stock_id: findLocation(itemValue.locations, proxyItem.hehe).id
                                            }" isWithError full>
                                            <template #label>
                                                <div>
                                                    <FractionDisplay v-if="itemValue.quantity_to_pick_fractional"
                                                        :fractionData="itemValue.quantity_to_pick_fractional" />
                                                    <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0)
                                                    }}</span>
                                                </div>
                                            </template>
                                        </ButtonWithLink>
                                    </div>
                                </template>
                            </NumberWithButtonSave>


                            <div class="md:hidden">
                                <ButtonWithLink v-if="!itemValue.is_handled" type="negative" tooltip="Set as not picked"
                                    icon="fal fa-debug" size="lg" :routeTarget="itemValue.not_picking_route"
                                    :bindToLink="{ preserveScroll: true }" />
                            </div>
                            <div class="hidden md:block">
                                <ButtonWithLink v-if="!itemValue.is_handled" type="negative" tooltip="Set as not picked"
                                    icon="fal fa-debug" :routeTarget="itemValue.not_picking_route"
                                    :bindToLink="{ preserveScroll: true }" />
                            </div>

                            <!-- Section: Errors list -->
                            <div v-if="proxyItem.errors?.length">
                                <p v-for="error in proxyItem.errors" class="text-xs text-red-500 italic">*{{ error }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!itemValue.locations.every(location => { return location.quantity > 0 })" class="">
                </div>
            </div>



              <Button  v-if="pickingSession.state === 'picking_finished' && itemValue.delivery_note_state === 'handling'" type="save" label="Set as packed" size="sm"  @click="onOpenModalDetail(itemValue)"/>


            <Button v-if="itemValue.delivery_note_state == 'packed'" :icon="faPencil" label="Edit Detail" size="sm"
                @click="onOpenModalDetail(itemValue)" />
            <div>
                <!-- Empty div to avoid print unexpected from BE -->
            </div>
        </template>
    </Table>

    <Modal :isOpen="isModalLocation" @onClose="() => onCloseModal()" width="w-full max-w-2xl" :dialogStyle="{
        background: '#ffffffcc'
    }">
        <div class="text-center font-semibold mb-4 text-2xl">
            Location list for {{ selectedItemValue?.org_stock_code }}:
            <!-- <pre>{{ selectedItemValue }}</pre> -->
        </div>

        <div class="rounded p-1 grid grid-cols-3 justify-between gap-x-6 items-center divide-x divide-gray-300">
            <div v-for="location in selectedItemValue?.locations"
                class="bg-white rounded mb-3 w-full xeven:bg-black/5 flex gap-x-3 items-center px-2 py-1">
                <label :for="location.location_code">
                    <span v-if="location.location_code"
                        v-tooltip="location.quantity <= 0 ? 'Location has no stock' : ''"
                        :class="location.quantity <= 0 ? 'text-gray-400' : ''">
                        <Link :href="generateLocationRoute(location)"
                            class="bg-gradient-to-t from-yellow-300/50 to-yellow-200/50 focus:ring-0 focus:outline-none focus:border-none bg-no-repeat [background-position:0%_100%] [background-size:100%_0.2em] motion-safe:transition-all motion-safe:duration-200 hover:[background-size:100%_100%] focus:[background-size:100%_100%] px-1;">
                        {{ location.location_code }}
                        </Link>
                    </span>
                    <span v-else v-tooltip="trans('Unknown location')" class="text-gray-400 italic">({{ trans("Unknown")
                    }})</span>

                    <span v-tooltip="trans('Total stock in this location')"
                        class="ml-1 whitespace-nowrap text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                        <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                        {{ location.quantity }}
                    </span>
                </label>
                <RadioButton v-model="selectedItemProxy.hehe" @update:modelValue="() => {
                    onCloseModal()
                }" :inputId="location.location_code" :disabled="location.quantity <= 0" name="location"
                    :value="location.location_code" />

                <div v-if="false" class="flex items-center flex-nowrap gap-x-2">
                    <!-- Button: input number (picking) -->
                    <NumberWithButtonSave v-if="location.quantity > 0" key="picking_picked" noUndoButton @onError="(error: any) => {
                        selectedItemProxy.errors = Object.values(error || {})
                    }" :modelValue="location.quantity_picked"
                        @update:modelValue="() => selectedItemProxy?.errors ? selectedItemProxy.errors = null : undefined"
                        saveOnForm :routeSubmit="{
                            name: selectedItemValue.upsert_picking_route.name,
                            parameters: selectedItemValue.upsert_picking_route.parameters,
                        }" :bindToTarget="{
                            step: 1,
                            min: 0,
                            max: Math.min(location.quantity, selectedItemValue.quantity_required, selectedItemValue.quantity_to_pick)
                        }" :additionalData="{
                            location_org_stock_id: location.id,
                            picking_id: selectedItemValue.pickings.find(picking => picking.location_id == location.location_id)?.id,
                        }" autoSave xxisWithRefreshModel
                        :readonly="selectedItemValue.is_handled || selectedItemValue.quantity_required == selectedItemValue.quantity_picked">
                        <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                            <ButtonWithLink v-tooltip="trans('Pick all required quantity in this location')"
                                icon="fal fa-clipboard-list-check"
                                :disabled="selectedItemValue.is_handled || selectedItemValue.quantity_required == selectedItemValue.quantity_picked"
                                :label="locale.number(selectedItemValue.quantity_to_pick)" size="xs" type="secondary"
                                :loading="isProcessing" class="py-0" :routeTarget="selectedItemValue.picking_all_route"
                                :bind-to-link="{
                                    preserveScroll: true,
                                    preserveState: true,
                                }" :body="{
                                    location_org_stock_id: location.id
                                }" isWithError />
                            <ButtonWithLink class="ml-8" v-if="!selectedItemValue.is_handled" type="negative"
                                tooltip="Set as not picked" icon="fal fa-debug" size="xs"
                                :routeTarget="selectedItemValue.not_picking_route"
                                :bindToLink="{ preserveScroll: true }" />
                        </template>
                    </NumberWithButtonSave>

                    <div v-else class="text-gray-400 italic">
                        {{ trans("No quantity available to pick") }}
                    </div>

                    <!-- Section: Errors list -->
                    <div v-if="selectedItemProxy?.errors?.length">
                        <p v-for="error in selectedItemProxy.errors" class="text-xs text-red-500 italic">*{{ error }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </Modal>

    <Modal :isOpen="modalDetail" @onClose="() => onCloseModalDetail()" width="w-1/2">
        <MiniDeliveryNote :deliveryNote="DeliveryNoteInModal"
            @SuccsesUpdateState="() => { onCloseModalDetail() }" />
    </Modal>

    <!-- Modal: Note -->
    <Modal :isOpen="isModalNote" @onClose="() => isModalNote = false" width="max-w-md w-full">
        <div class="">
            <div class="text-center text-xl font-semibold">
                Delivery Note: {{ selectedDelivery?.delivery_note_reference }}
            </div>


            <div class="relative w-full xpt-4 rounded overflow-hidden mt-2"  :style="{
                backgroundColor: `rgba(56, 189, 248, 0.1)`,
                border:`1px solid rgb(56, 189, 248)`
            }">
                <!-- Section: Header -->
                <div class="xabsolute top-0 left-0 w-full flex gap-x-1 lg:pr-0 justify-between lg:justify-normal">
                    <div class="w-full flex items-center justify-between text-xs truncate gap-x-2 text-center py-0.5 pl-3 pr-3" :style="{
                        backgroundColor: 'rgb(56, 189, 248)',
                    }">
                        <div
                            class="flex flex-wrap items-center gap-x-1"
                        >
                            <FontAwesomeIcon icon='fas fa-sticky-note' class='' fixed-width aria-hidden='true' />
                            {{ trans("Delivery Instructions") }}
                            <InformationIcon :information="trans('This note will be printed in the shipping label')" />
                            
                        </div>

                    </div>
                </div>

                <!-- Section: Note -->
                <p class="h-full max-h-32 mx-auto items-center px-4 pt-2 pb-2 text-xxs break-words">
                    <template v-if="selectedDelivery?.delivery_note_shipping_notes">{{ selectedDelivery?.delivery_note_shipping_notes }}</template>
                    <div v-else class="text-gray-400 italic">
                        {{ 'No notes' }}
                    </div>
                </p>
            </div>
        </div>
    </Modal>


</template>
