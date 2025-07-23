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
import { debounce, get, set } from 'lodash-es';
import { notify } from "@kyvg/vue3-notification";
import { trans } from "laravel-vue-i18n";
import { routeType } from "@/types/route";
import { Collapse } from "vue-collapsed";
import { ref, onMounted, reactive, inject } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl } from "@fal";
import { faSkull } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import axios from "axios";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"

library.add(faSkull, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl);


defineProps<{
    data: TableTS
    tab?: string
    state: string
}>();

const locale = inject("locale", aikuLocaleStructure);


const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});


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
const isLoadingUndoPick = reactive({});;

// Section: Modal for location list
const isModalLocation = ref(false)
const selectedItemValue = ref()
const selectedItemProxy = ref()


// Method: to find location that Alt ed, fallback is index 0
const findLocation = (locationsList: { location_code: string }[], selectedHehe: string) => {
    return locationsList.find(x => x.location_code == selectedHehe) || locationsList[0]
}
</script>

<template>
    <div v-if="itemValue.quantity_to_pick > 0">
        <div v-if="findLocation(itemValue.locations, proxyItem.hehe)"
            class="rounded p-1 flex flex-col justify-between gap-x-6 items-center even:bg-black/5">
            <!-- Action: decrease and increase quantity -->
            <div class="mb-3 w-full flex justify-between gap-x-6 items-center">
                <div class="">
                    <Transition name="spin-to-right">
                        <div :key="findLocation(itemValue.locations, proxyItem.hehe).location_code">
                            <span v-if="findLocation(itemValue.locations, proxyItem.hehe)">
                                <Link :href="generateLocationRoute(findLocation(itemValue.locations, proxyItem.hehe))"
                                    class="secondaryLink">
                                {{ findLocation(itemValue.locations, proxyItem.hehe).location_code }}
                                </Link>
                            </span>
                            <span v-else v-tooltip="trans('Unknown location')" class="text-gray-400 italic">
                                ({{ trans("Unknown") }})
                            </span>
                            <span v-tooltip="trans('Total stock in this location')"
                                class="whitespace-nowrap py-0.5 text-gray-400 tabular-nums border border-gray-300 rounded px-1">
                                <FontAwesomeIcon icon="fal fa-inventory" class="mr-1" fixed-width aria-hidden="true" />
                                {{ findLocation(itemValue.locations, proxyItem.hehe).quantity }}
                            </span>

                            <span v-if="itemValue.locations?.length > 1" @click="() => {
                                isModalLocation = true;
                                selectedItemValue = itemValue;
                                selectedItemProxy = proxyItem;
                            }" v-tooltip="`Other ${itemValue.locations?.length - 1} locations`"
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
                        :key="findLocation(itemValue.locations, proxyItem.hehe).location_code" noUndoButton @onError="(error: any) => {
                            proxyItem.errors = Object.values(error || {})
                        }" :modelValue="findLocation(itemValue.locations, proxyItem.hehe).quantity_picked"
                        @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined" saveOnForm
                        :routeSubmit="{
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
                                            <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0) }}</span>
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
                                            <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0) }}</span>
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
                        <p v-for="error in proxyItem.errors" class="text-xs text-red-500 italic">*{{ error }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
