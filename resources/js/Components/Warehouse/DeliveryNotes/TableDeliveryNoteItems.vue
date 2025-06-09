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
import { faArrowDown, faDebug, faClipboardListCheck } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import axios from "axios";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import deliveryNote from "@/Pages/Grp/Org/Dispatching/DeliveryNote.vue";

library.add(faArrowDown, faDebug, faClipboardListCheck);


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
    if (!location.code) {
        return "#";
    }

    if (route().current() === "grp.org.warehouses.show.dispatching.delivery-notes.show") {
        return route(
            "grp.org.warehouses.show.infrastructure.locations.show",
            [
                route().params["organisation"],
                route().params["warehouse"],
                location.code
            ]
        );
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
                {{ deliveryNote.org_stock_code }}
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

        <template #cell(pickings)="{ item: item }">
            {{ item.pickings }}
        </template>

        <template #cell(handing_actions)="{ item: itemValue, proxyItem }">

            {{ itemValue.id }}
            {{ itemValue.locations }}

            <div v-if="itemValue.quantity_to_pick>0">
                <template v-for="location_org_stock in itemValue.locations" :key="location_org_stock.location_id">
                    <Teleport v-if="isMounted" :to="`#row-${itemValue.id}`" :disabled="location_org_stock.quantity > 0">
                        <div class="rounded p-1 flex justify-between gap-x-6 items-center even:bg-black/5">

                            <!-- Location code -->
                            <div class="">
                                <span v-if="location_org_stock.location_code">
                                    <Link :href="generateLocationRoute(location_org_stock)" class="secondaryLink">
                                        {{ location_org_stock.location_code }}
                                    </Link>
                                </span>
                                <span v-else  v-tooltip="trans('Unknown location')" class="text-gray-400 italic">({{ trans("Unknown") }})</span>

                                <span
                                    v-tooltip="trans('Total stock in this location')"
                                    class="text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                                        <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />

                                    {{ location_org_stock.quantity }}
                                </span>
                            </div>


                            <div class="flex items-center flex-nowrap gap-x-2">


                                <!-- Button: input number (picking) -->

                                <NumberWithButtonSave
                                    v-if="location_org_stock.quantity > 0"
                                    key="picking_picked"
                                    noUndoButton
                                    @onError="(error: any) => {
                                                proxyItem.errors = Object.values(error || {})
                                            }"
                                    :modelValue="location_org_stock.quantity_picked"
                                    @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                    saveOnForm
                                    :routeSubmit="itemValue.upsert_picking_route"
                                    :bindToTarget="{
                                                step: 1,
                                                min: 0,
                                                max: Math.min(location_org_stock.quantity, itemValue.quantity_required, itemValue.quantity_to_pick)
                                            }"
                                    :additionalData="{
                                                location_org_stock_id: location_org_stock.id,
                                                picking_id: location_org_stock.picking_id,
                                            }"
                                    autoSave
                                    isWithRefreshModel
                                    :readonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                >
                                    <template #save="{ isProcessing, isDirty, onSaveViaForm }">


                                        <ButtonWithLink
                                            v-tooltip="trans('Pick all required quantity in this location')"
                                            icon="fal fa-clipboard-list-check"
                                            :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                            :label="locale.number(itemValue.quantity_to_pick )"
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
                                                        location_org_stock_id: location_org_stock.id
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
                                <FontAwesomeIcon icon="fal fa-arrow-down" class="transition-all" :class="get(proxyItem, ['is_open_collapsed'], false) ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
                                {{ get(proxyItem, ["is_open_collapsed"], false) ? trans("Close") : trans("Open hidden locations") }}
                            </div>
                        </Button>
                    </div>
                </div>
            </div>


            <ButtonWithLink
                v-if="itemValue.is_picked && !itemValue.is_packed"
                :routeTarget="deliveryNote.packing_route"
                :bindToLink="{
                        preserveScroll: true,
                        preserveState: true,
                    }"
                type="secondary"
                size="xs"
                :label="trans('Pack')"
            />

        </template>


    </Table>
</template>
