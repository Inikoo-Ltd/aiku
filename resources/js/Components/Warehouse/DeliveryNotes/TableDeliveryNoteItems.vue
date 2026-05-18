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
import { ref, onMounted, reactive, inject, computed, watch } from "vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faHourglassHalf, faUndo, faBox, faBarcode } from "@fal";
import { faSkull, faWandMagic } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import Modal from "@/Components/Utils/Modal.vue"
import { RadioButton } from "primevue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import ExpiryDateLabel from "@/Components/Utils/Label/ExpiryDateLabel.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import axios from "axios";
import Image from "@common/Components/Image.vue"
import LabelItemsWaitingForWarehouse from "./LabelItemsWaitingForWarehouse.vue"
import LabelItemsWaitingForCrm from "./LabelItemsWaitingForCrm.vue"
import LoadingOverlay2 from "@/Components/Utils/LoadingOverlay2.vue"
import { ctrans } from "@/Composables/useTrans"
import LabelPickingLocation from "./LabelPickingLocation.vue"
import PickingLocationModal from "./PickingLocationModal.vue"
import SelectPickingLocation from "./SelectPickingLocation.vue"
library.add(faSkull, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faHourglassHalf, faWandMagic, faBox, faBarcode);


const props = defineProps<{
    data: TableTS
    tab?: string
    state: string
    shop_type : string
    allowWaiting: boolean
    allowPickerSetNotPicked: boolean
    isEditable: boolean
}>();

const emit = defineEmits<{
    'update:quantity-to-resend': [itemId: string | number, value: number]
    'validation-error': [itemId: string | number, hasError: boolean]
}>();

const screenType = inject('screenType', ref('desktop'))

const locale = inject("locale", aikuLocaleStructure);
const layout = inject('layout', layoutStructure)

function orgStockRoute(deliveryNoteItem: DeliverNoteItem) {
    if(!deliveryNoteItem.org_stock_id){
        return '';
    }

    return route(
        "grp.helpers.redirect_org_stock",
        [deliveryNoteItem.org_stock_id])

}


const isMounted = ref(false);
onMounted(() => {
    isMounted.value = true;
});

// const onPickingQuantity = (pick_route: routeType, quantity: number) => {
//     router[pick_route.method || "post"](
//         route(pick_route.name, pick_route.parameters),
//         {
//             quantity: quantity
//         },
//         {
//             preserveState: true,
//             preserveScroll: true,
//             onSuccess: () => {
//                 notify({
//                     title: trans("Success"),
//                     text: "",
//                     type: "error"
//                 });
//             },
//             onError: (error) => {
//                 notify({
//                     title: trans("Something went wrong"),
//                     text: "",
//                     type: "error"
//                 });
//             }
//         }
//     );
// };
// const debounceOnPickingQuantity = debounce(onPickingQuantity, 500);


const generateLocationRoute = (location: any) => {
    if (!location.location_slug || !(route().params["organisation"]) || !(route().params["warehouse"])) {
        return "";
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
        return "";
    }

};


// Button: undo pick
const isLoadingUndoPick = reactive({});

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
const selectedLocationCode = reactive({})
const findLocation = (locationsList: { location_code: string }[], locationCode: string) => {
    return locationsList.find(x => x.location_code == locationCode) || locationsList[0]
}

const exceptPropsToLoad = ['tabs', 'quick_pickers', 'routes', 'queryBuilderProps', 'warehouse', 'shipments_routes', 'address', 'navigation', 'breadcrumbs']

// Section: Modal for edit expiry date and batch code
const selectedItemToEditExpiryDate = ref(null)
const isModalEditExpiryDate = ref(false)
const selectedBatchCode = ref(null)

const batchCodeFetchRoute = computed(() => {
    if (!selectedItemToEditExpiryDate.value?.org_stock_id || !selectedItemToEditExpiryDate.value?.organisation_id) {
        return null
    }
    return {
        name: 'grp.json.org_stock.batch_codes.index',
        parameters: {
            organisation: selectedItemToEditExpiryDate.value.organisation_id,
            orgStock: selectedItemToEditExpiryDate.value.org_stock_id,
        },
    }
})

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

const onCloseModalExpiryDate = () => {
    isModalEditExpiryDate.value = false

    setTimeout(() => {
        selectedItemToEditExpiryDate.value = null
        selectedBatchCode.value = null
    }, 300);
}
const isLoadingSubmitExpiryDate = ref(false)
const onSubmitEditExpiryDate = () => {
    if (!selectedItemToEditExpiryDate.value) {
        return
    }

    router.patch(
        route('grp.models.delivery_note_item.update', {
            deliveryNoteItem: selectedItemToEditExpiryDate.value?.id
        }),
        {
            batch_code_id: selectedBatchCode.value?.id ?? null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSubmitExpiryDate.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully set batch code"),
                    type: "success"
                })
                onCloseModalExpiryDate()
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to set batch code. Try again"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmitExpiryDate.value = false
            },
        }
    )
}

const countStockInAllLocations = (loc?: {}[]) => {
    if (!!(loc?.length)) {
        return loc?.reduce((sum, item) => sum + Number(item.quantity), 0) ?? 0
    } else {
        return 0
    }
}


// Section: Modal pick from magic place
const selectedItemToPickMagicPlace = ref(null)
const isModalEPickMagicPlace = ref(false)
const onCloseModalPickMagicPlace = () => {
    isModalEPickMagicPlace.value = false

    setTimeout(() => {
        selectedItemToPickMagicPlace.value = null
    }, 300);
}
const isLoadingSubmitPickMagicPlace = ref(false)
const onSubmitPickMagicPlace = () => {

    if (!selectedItemToPickMagicPlace.value) {
        console.log('No item expiry date selected')
        return
    }

    router.post(
        route('grp.models.delivery_note_item.picking.magic_place', {
            deliveryNoteItem: selectedItemToPickMagicPlace.value?.id
        }),
        {
            
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingSubmitPickMagicPlace.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully pick from magic place"),
                    type: "success"
                })
                onCloseModalPickMagicPlace()
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to pick from magic place. Try again"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmitPickMagicPlace.value = false
            },
        }
    )
}

const GetQuantityToPickFractional = (item) => {
    if(props.shop_type == 'dropshipping'){
        return item.quantity_to_pick_fractional_ds
    }else return item.quantity_to_pick_fractional
}


// Section: Set Transaction as waiting
const dataToSendAsWaiting = ref({
    note: '',

})
const isOpenModalSetAsWaiting = ref(false)
const selectedTransactionToSetAsWaiting = ref(null)
const isLoadingSetAsWaiting = ref(false)
const submitTransactionAsWaiting = () => {
    // Section: Submit
    router.post(
        route('grp.models.delivery_note_item.set_as_waiting_warehouse', {
            deliveryNoteItem: selectedTransactionToSetAsWaiting.value?.id
        }),
        {
            ...dataToSendAsWaiting.value,
            transaction_id: selectedTransactionToSetAsWaiting.value?.id,
            quantity: selectedTransactionToSetAsWaiting.value?.quantity_to_pick + Number(selectedTransactionToSetAsWaiting.value?.quantity_waiting_warehouse || 0)
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
                isOpenModalSetAsWaiting.value = false
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

const isModalPackingInfo = ref(false)
const selectedPackingItem = ref<any>(null)

const packingDetailRows = ref<any[]>([])
const isLoadingPackingDetail = ref(false)

const openPackingModal = async (item: any) => {
    selectedPackingItem.value = item
    // console.log("selectedPackingItem",selectedPackingItem.value)
    isModalPackingInfo.value = true
    isLoadingPackingDetail.value = true

    // console.log("modalResource NOW", modalResource.value)
    try {
        const res = await axios.get(
            route("grp.json.fetch_single_delivery_note_item", {
                deliveryNoteItem: item.data.id
            })
        )
        // console.log("res",res)
        packingDetailRows.value = res.data
        // console.log("packingDetailRows",packingDetailRows.value)
    } catch (e) {
        console.log(e)
        packingDetailRows.value = []
    } finally {
        isLoadingPackingDetail.value = false
    }
}

const closePackingModal = () => {
    isModalPackingInfo.value = false
    setTimeout(() => {
        selectedPackingItem.value = null
    }, 200)
}

const modalResource = computed(() => {
    if (!selectedPackingItem.value) return null

    return {
        ...props.data,
        data: props.data.data.filter(
            r => r.id === selectedPackingItem.value.id
        )
    }
})


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

// watch(modalResource, (val) => {
//     // console.log("modalResource", val)
// }, { deep: true })


// Section: Picking batch code
const isModalPickingBatchCode = ref(false)
const selectedPickingForBatchCode = ref(null)
const selectedPickingBatchCode = ref(null)
const isLoadingSubmitPickingBatchCode = ref(false)

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

const onCloseModalPickingBatchCode = () => {
    isModalPickingBatchCode.value = false
    setTimeout(() => {
        selectedPickingForBatchCode.value = null
        selectedPickingBatchCode.value = null
    }, 300)
}

const onSubmitPickingBatchCode = () => {
    if (!selectedPickingForBatchCode.value) return

    router.patch(
        route(selectedPickingForBatchCode.value.update_route.name, selectedPickingForBatchCode.value.update_route.parameters),
        { batch_code_id: selectedPickingBatchCode.value?.id ?? null },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { isLoadingSubmitPickingBatchCode.value = true },
            onSuccess: () => {
                notify({ title: trans("Success"), text: trans("Successfully set batch code"), type: "success" })
                onCloseModalPickingBatchCode()
            },
            onError: () => {
                notify({ title: trans("Something went wrong"), text: trans("Failed to set batch code. Try again"), type: "error" })
            },
            onFinish: () => { isLoadingSubmitPickingBatchCode.value = false },
        }
    )
}

// Section: Undo Quantity Waiting Warehouse
const isOpenModalUndoWaitingWarehouse = ref(false)
const selectedItemToUndoWaitingWarehouse = ref(null)
const isLoadingUndoWaitingWarehouse = ref(false)
const onSetItemToUndoWaitingWarehouse = () => {
    router.post(route('grp.models.delivery_note_item.undo_set_as_waiting_warehouse', {
        deliveryNoteItem: selectedItemToUndoWaitingWarehouse.value?.id
    }),
    { },
    {
        preserveScroll: true,
        onStart: () => {
            isLoadingUndoWaitingWarehouse.value = true
        },
        onSuccess: () => {
            isOpenModalUndoWaitingWarehouse.value = false
            notify({
                title: trans("Success") + '!',
                text: ctrans('Item :itemName undo the quantity waiting warehouse', { itemName: selectedItemToUndoWaitingWarehouse.value?.org_stock_name}),
                type: "success",
            })
        },
        onFinish: () => isLoadingUndoWaitingWarehouse.value = false,
    }
)}
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
                <FractionDisplay v-if="item.quantity_required_fractional"
                    :fractionData="item.quantity_required_fractional" />
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
        </template>

        <!-- Column: Name -->
        <template #cell(org_stock_name)="{ item: deliveryNoteItem }">
            <div>{{ deliveryNoteItem.org_stock_name }} <span class="italic opacity-80">{{deliveryNoteItem.packed_in_message}}</span></div>

            <!-- Section: DNI Expired date -->
            <div v-if="false" class="flex items-center flex-wrap">
                <!-- Label: expired date -->
                <ExpiryDateLabel v-if="(deliveryNoteItem.expiry_date || deliveryNoteItem.batch_code)" :expiry_date="deliveryNoteItem.expiry_date" :batch_code="deliveryNoteItem.batch_code" />

                <!-- Button: add/edit expiry date and batch code -->
                <div v-if="(deliveryNoteItem.is_picked || Number(deliveryNoteItem.quantity_picked) > 0) && state !== 'cancelled'">
                    <Button
                        v-if="deliveryNoteItem.expiry_date || deliveryNoteItem.batch_code"
                        @click="() => (isModalEditExpiryDate = true, selectedItemToEditExpiryDate = deliveryNoteItem)"
                        type="transparent"
                        v-tooltip="ctrans('Edit expiry date and batch code')"
                        size="xs"
                        icon="fal fa-pencil"
                    />
                    <Button v-else
                        @click="() => (isModalEditExpiryDate = true, selectedItemToEditExpiryDate = deliveryNoteItem)"
                        type="tertiary"
                        size="xs"
                        :label="ctrans('Add expiry date and batch code')"
                        icon="fas fa-plus"
                        key="1"
                    >
                        <template #iconRight="">
                            <FontAwesomeIcon icon="fad fa-viruses" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </template>

        <!-- Section: Pickings -->
        <template #cell(picking_locations)="{ item }">
            <div v-if="item.picking_locations && item.picking_locations.length > 0" class="flex flex-col gap-1">
                <div v-for="picking in item.picking_locations" :key="picking.id" class="text-sm flex items-center gap-2 flex-wrap">
                    <Link v-if="picking.location_code"
                          :href="route('grp.org.warehouses.show.infrastructure.locations.show', [route().params.organisation, picking.warehouse_slug, picking.location_slug])"
                          :class="['primaryLink font-medium', picking.location_code ? '' : 'text-gray-400 italic']">
                        {{ picking.location_code }}
                    </Link>
                    <span v-else class="text-gray-400 italic">No Location</span>
                    <div class="px-2 py-0.5 bg-gray-100 rounded-full text-xs font-medium">
                        {{ picking.quantity_picked }}
                    </div>

                    <!-- Batch code display and edit -->
                    <button
                        v-if="picking.show_batch_code_ui"
                        @click="() => (isModalPickingBatchCode = true, selectedPickingForBatchCode = picking)"
                        v-tooltip="picking.batch_code ? ctrans('Change batch code: :code', { code: picking.batch_code }) : ctrans('Set batch code')"
                        class="text-xs px-1.5 py-0.5 rounded border transition-colors"
                        :class="picking.batch_code ? 'border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100' : 'border-dashed border-gray-300 text-gray-400 hover:border-gray-400 hover:text-gray-600'"
                    >
                        <FontAwesomeIcon icon="fal fa-barcode" class="mr-1" fixed-width aria-hidden="true" />
                        {{ picking.batch_code ?? ctrans('Batch code') }}
                    </button>
                </div>
            </div>
            <div v-else class="text-gray-400 italic text-sm">No items picked yet</div>
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

        <template #cell(quantity_dispatched)="{ item: item, proxyItem }">
            <FractionDisplay v-if="item.quantity_dispatched_fractional"
                :fractionData="item.quantity_dispatched_fractional" />
            <span v-else>{{ item.quantity_dispatched }}</span>

        </template>

        <template #cell(quantity_picked)="{ item: item, proxyItem }">
            <FractionDisplay v-if="item.quantity_picked_fractional" :fractionData="item.quantity_picked_fractional" />
            <span v-else>{{ item.quantity_picked }}</span>
            
            <span v-if="Number(item.quantity_not_picked) > 0" v-tooltip="ctrans('Not picked')"  class="text-red-500 rounded-sm border-red-400 bg-red-100  border px-1.5 ml-2">
                {{ Number(item.quantity_not_picked) }}
            </span>

            <!-- Number: waiting warehouse -->
            <Link v-if="isEditable && Number(item.quantity_waiting_warehouse) > 0" v-tooltip="ctrans('Waiting for warehouse')" :href="routeItemsWaitingWarehouse(item)" class="relative text-amber-500 rounded-sm border-amber-400 bg-amber-100  border px-1.5 ml-2">
                {{ Number(item.quantity_waiting_warehouse) }}
                <FontAwesomeIcon icon="fas fa-circle" class="absolute -top-0.5 xright-0.5 text-amber-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                <FontAwesomeIcon icon="fas fa-circle" class="absolute -top-0.5 xright-0.5 text-amber-500 text-[5px]" fixed-width aria-hidden="true" />
            </Link>
            <span v-else-if="Number(item.quantity_waiting_warehouse) > 0" v-tooltip="ctrans('Waiting for warehouse')"  class="relative text-amber-500 rounded-sm border-amber-400 bg-amber-100  border px-1.5 ml-2">
                {{ Number(item.quantity_waiting_warehouse) }}
                <FontAwesomeIcon icon="fas fa-circle" class="absolute -top-0.5 xright-0.5 text-amber-500 text-[5px] animate-ping" fixed-width aria-hidden="true" />
                <FontAwesomeIcon icon="fas fa-circle" class="absolute -top-0.5 xright-0.5 text-amber-500 text-[5px]" fixed-width aria-hidden="true" />
            </span>


            <!-- Number: waiting CRM -->
            <Link
                v-if="Number(item.quantity_waiting_crm) > 0"
                :href="routeItemsWaitingCrm(item)"
            >
                <span v-tooltip="ctrans('Waiting for customer services')"  class="text-purple-500 rounded-sm border-purple-400 bg-purple-100  border px-1.5 ml-2">
                    {{ Number(item.quantity_waiting_crm) }}
                </span>
            </Link>


        </template>

        <template #cell(quantity_packed)="{ item: item, proxyItem }">
            <FractionDisplay v-if="item.quantity_packed_fractional" :fractionData="item.quantity_packed_fractional" />
            <span v-else>{{ item.quantity_packed }}</span>

        </template>


        <template #cell(quantity_to_pick)="{ item: item }">
            {{ item.quantity_to_pick }}
        </template>


        <!-- Column: Pickings -->
        <template #cell(pickings)="{ item }">
            <div v-if="item.pickings?.length" class="space-y-1">
                <div v-for="picking in item.pickings" :key="picking.id" class="flex gap-x-2 w-fit">
                    <!-- {{ picking.location_code }} -->
                    <div v-if="picking.type === 'pick'" class="flex gap-x-2 items-center flex-wrap">
                        <Link v-if="!!(generateLocationRoute(picking))" :href="generateLocationRoute(picking)" class="secondaryLink">
                            {{ picking.location_code }}
                        </Link>
                        <span v-else>
                            {{ picking.location_code }}
                        </span>

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

                        <!-- Section: Picking Batch Code -->
                        <button
                            v-if="picking.show_batch_code_ui"
                            @click="() => (isModalPickingBatchCode = true, selectedPickingForBatchCode = picking)"
                            v-tooltip="picking.batch_code ? ctrans('Change batch code: :code', { code: picking.batch_code }) : ctrans('Set batch code')"
                            class="text-xs px-1.5 py-0.5 rounded border transition-colors"
                            :class="picking.batch_code ? 'border-blue-300 bg-blue-50 text-blue-700 hover:bg-blue-100' : 'border-dashed border-gray-300 text-gray-400 hover:border-gray-400 hover:text-gray-600'"
                        >
                            <FontAwesomeIcon icon="fal fa-barcode" class="mr-1" fixed-width aria-hidden="true" />
                            {{ picking.batch_code ?? ctrans('Batch code') }}
                        </button>

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

                    <!-- Label: number of magic pick -->
                    <div
                        v-if="picking.type === 'magic_pick'"
                        v-tooltip="ctrans(':qtyPicked items are picked from magic place', { qtyPicked: Number(picking.quantity_picked).toString()})"
                        class="bg-yellow-200 text-yellow-600 px-1 whitespace-nowrap"
                    >
                        <FontAwesomeIcon icon="fas fa-wand-magic" class="" fixed-width aria-hidden="true" />
                        <FractionDisplay v-if="picking.quantity_picked_fractional"
                            :fractionData="picking.quantity_picked_fractional" />
                        <span v-else>
                            {{ picking.quantity_picked }}
                        </span>
                    </div>

                    <!-- Button: Undo Pick from Pickings -->
                    <div v-if="isEditable" class="">
                        <ButtonWithLink
                            v-if="item.quantity_picked!=0 || item.quantity_not_picked!=0"
                            v-tooltip="ctrans('Undo pick :qtyPicked items', { qtyPicked: Number(picking.quantity_picked).toString()})"
                            type="negative"
                            :size="screenType != 'mobile' ? 'xxs' : 'md'"
                            icon="fal fa-undo-alt"
                            :routeTarget="picking.undo_picking_route"
                            :bindToLink="{ preserveScroll: true }"
                            :loading="get(isLoadingUndoPick, `undo-pick-${picking.id}`, false)"
                        />
                    </div>
                </div>

            </div>

            <div v-else class="text-xs text-gray-400 italic">
                {{ trans("No item picked yet") }}
            </div>

            <!-- Section: items are waiting for warehouse -->
            <div v-if="!isEditable && Number(item.quantity_waiting_warehouse) > 0" class="mt-2 xmx-auto w-fit flex gap-x-2">
                <LabelItemsWaitingForWarehouse :qty_waiting_warehouse="Number(item.quantity_waiting_warehouse)" />
            </div>
        </template>

        <template #cell(picking_position)="{ item: itemValue, proxyItem }">
            <div class="hidden">
                <div><span class="bg-yellow-400">itemValue.is_picked</span>: {{ itemValue.is_picked }}</div>
                <div><span class="bg-yellow-400">itemValue.is_handled</span>: {{ itemValue.is_handled }}</div>
                <div><span class="bg-yellow-400">itemValue.is_packed</span>: {{ itemValue.is_packed }}</div>
                <div><span class="bg-yellow-400">itemValue.quantity_to_pick</span>: {{ itemValue.quantity_to_pick }}</div>
                <div><span class="bg-yellow-400">itemValue.locations</span>: {{ itemValue.locations }}</div>
                <div><span class="bg-yellow-400">proxyItem.org_stock_id</span>: {{ proxyItem.org_stock_id }}</div>
                <div><span class="bg-yellow-400">findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null))</span>: {{ findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)) }}</div>
                <div><span class="bg-yellow-400">itemValue.is_handled</span>: {{ itemValue.is_handled }}</div>
                <div><span class="bg-yellow-400">itemValue.quantity_required</span>: {{ itemValue.quantity_required }}</div>
            </div>
            
            <div v-if="itemValue.quantity_to_pick > 0">
                <div v-if="findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null))"
                    class="flex flex-col justify-between gap-x-6 items-center">
                    <!-- Action: decrease and increase quantity -->
                    <div class="mb-3 w-full flex justify-between gap-x-6 xitems-center">
                        <!-- Section: Locations -->
                        <LabelPickingLocation
                            :locations="itemValue.locations"
                            :selectedOrgStockId="get(selectedLocationCode, [itemValue.id], null)"
                            :warehouseArea="itemValue.warehouse_area"
                            @openLocationModal="() => { isModalLocation = true; selectedItemValue = itemValue; selectedItemProxy = proxyItem; }"
                        />

                        <div class="flex items-center flex-nowrap gap-x-2">
                            <!-- Button: input number (picking) -->
                            <NumberWithButtonSave
                                v-if="!itemValue.is_handled && findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity > 0"
                                :key="findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_code" noUndoButton
                                @onError="(error: any) => {
                                    proxyItem.errors = Object.values(error || {})
                                }" :modelValue="findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity_picked"
                                @update:modelValue="() => proxyItem.errors ? proxyItem.errors = null : undefined"
                                saveOnForm :routeSubmit="{
                                    name: itemValue.upsert_picking_route.name,
                                    parameters: itemValue.upsert_picking_route.parameters,
                                }" :bindToTarget="{
                                    step: 1,
                                    min: 0,
                                    max: Math.min(findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity, itemValue.quantity_required, (itemValue.quantity_to_pick + findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).quantity_picked))
                                }" :additionalData="{
                                    location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id,
                                    picking_id: itemValue.pickings.find(picking => picking.location_id == findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_id)?.id,
                                }" autoSave xxisWithRefreshModel
                                :readonly="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked">
                                <template #save="{ isProcessing, isDirty, onSaveViaForm }">
                                    <div class="flex gap-x-8 w-fit">
                                        <ButtonWithLink
                                            v-tooltip="trans('Pick all required quantity in location :xlocation', { xlocation: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).location_code || '-' })"
                                            icon="fal fa-clipboard-list-check"
                                            :disabled="itemValue.is_handled || itemValue.quantity_required == itemValue.quantity_picked"
                                            :size="screenType != 'mobile' ? 'xs' : 'md'"
                                            type="secondary"
                                            :loading="isProcessing"
                                            class="py-0"
                                            :routeTarget="itemValue.picking_all_route"
                                            :bind-to-link="{
                                                preserveScroll: true,
                                                preserveState: true,
                                            }"
                                            :body="{
                                                location_org_stock_id: findLocation(itemValue.locations, get(selectedLocationCode, [itemValue.id], null)).id
                                            }"
                                            isWithError
                                        >
                                            <template #label>
                                                <div>
                                                    <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)" :fractionData="GetQuantityToPickFractional(itemValue)" />
                                                    <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0) }}</span>
                                                </div>
                                            </template>
                                        </ButtonWithLink>
                                    </div>
                                </template>
                            </NumberWithButtonSave>
                            
                            <!-- Button: Pick from magic place -->
                            <Button
                                v-if="!itemValue.is_handled
                                    && Number(countStockInAllLocations(itemValue.locations)) < itemValue.quantity_to_pick
                                "
                                @click="() => (isModalEPickMagicPlace = true, selectedItemToPickMagicPlace = itemValue)"
                                type="warning"
                                key="4"
                                v-tooltip="trans('Pick :numberNotPicked from magic place', { numberNotPicked: itemValue.quantity_to_pick || '0'})"
                                :size="screenType == 'desktop' ? 'sm' : 'lg'"
                                method="post"
                            >
                                <template #label>
                                    <span class="flex items-center">
                                        <div>
                                            <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)" :fractionData="GetQuantityToPickFractional(itemValue)" />
                                            <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0) }}</span>
                                        </div>
                                        <FontAwesomeIcon icon="fas fa-wand-magic" class="text-yellow-600" fixed-width aria-hidden="true" />
                                    </span>
                                </template>
                            </Button>

                            <!-- Button: Not Picked || Set as Waiting -->
                            <template v-if="!itemValue.is_handled">
                                <!-- Button: Set Transaction as Waiting (only on Ecom) -->
                                <template v-if="allowWaiting">                                   
                                    <!-- Button: Not picked -->
                                    <ButtonWithLink
                                        v-if="allowPickerSetNotPicked"
                                        type="negative"
                                        iconRight="fal fa-debug"
                                        :size="screenType == 'desktop' ? 'sm' : 'lg'"
                                        :routeTarget="itemValue.not_picking_route"
                                        :bindToLink="{preserveScroll: true}"
                                        v-tooltip="trans('Set :numberNotPicked as not picked', { numberNotPicked: locale.number(itemValue.quantity_to_pick ) || '0'})"
                                    >
                                        <template #label>
                                            <div>
                                                <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)" :fractionData="GetQuantityToPickFractional(itemValue)" />
                                                <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0) }}</span>
                                            </div>
                                        </template>
                                    </ButtonWithLink>

                                    <Button
                                        @click="() => (isOpenModalSetAsWaiting = true, dataToSendAsWaiting.note = itemValue.notes, selectedTransactionToSetAsWaiting = itemValue)"
                                        type="tertiary"
                                        iconRight="fal fa-hourglass-half"
                                        :size="screenType == 'desktop' ? 'sm' : 'lg'"
                                        v-tooltip="trans('Set :numberNotPicked as waiting', { numberNotPicked: locale.number(itemValue.quantity_to_pick ) || '0'})"
                                    >
                                        <template #label>
                                            <div>
                                                <FractionDisplay v-if="GetQuantityToPickFractional ? GetQuantityToPickFractional(itemValue) : null" :fractionData="GetQuantityToPickFractional(itemValue)" />
                                                <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0) }}</span>
                                            </div>
                                        </template>
                                    </Button>
                                </template>

                                <!-- Button: Not picked -->
                                <ButtonWithLink
                                    v-else
                                    type="negative"
                                    iconRight="fal fa-debug"
                                    :size="screenType == 'desktop' ? 'sm' : 'lg'"
                                    :routeTarget="itemValue.not_picking_route"
                                    :bindToLink="{preserveScroll: true}"
                                    v-tooltip="trans('Set :numberNotPicked as not picked', { numberNotPicked: locale.number(itemValue.quantity_to_pick ) || '0'})"
                                >
                                    <template #label>
                                        <div>
                                            <FractionDisplay v-if="GetQuantityToPickFractional(itemValue)" :fractionData="GetQuantityToPickFractional(itemValue)" />
                                            <span v-else>{{ locale.number(itemValue.quantity_to_pick ?? 0) }}</span>
                                        </div>
                                    </template>
                                </ButtonWithLink>
                            </template>


                        </div>
                    </div>
                        
                    <!-- Section: Errors list -->
                    <div v-if="proxyItem.errors?.length" class="">
                        <p v-for="error in proxyItem.errors" class="text-xs text-red-500 italic">
                            *{{ error }}
                        </p>
                    </div>
                </div>

                <div v-else class="flex justify-between gap-x-2">
                    <div class="italic text-gray-400">{{ trans("No locations found") }}</div>
                    <!-- {{ itemValue.quantity_to_pick }} -->

                    <div class="flex gap-x-2 gap-y-1 items-center">
                        <Button
                            @click="() => (isModalEPickMagicPlace = true, selectedItemToPickMagicPlace = itemValue)"
                            type="warning"
                            key="4"
                            v-tooltip="trans('Pick :numberNotPicked from magic place', { numberNotPicked: itemValue.quantity_to_pick || '0'})"
                            :size="screenType == 'desktop' ? 'sm' : 'lg'"
                        >
                            <template #label>
                                <span>
                                    {{ itemValue.quantity_to_pick.toString() || '0' }}
                                    <FontAwesomeIcon icon="fas fa-wand-magic" class="text-yellow-600" fixed-width aria-hidden="true" />
                                </span>
                            </template>
                        </Button>
                        
                        <ButtonWithLink
                            type="negative"
                            v-tooltip="trans('Set :numberNotPicked as not picked', { numberNotPicked: itemValue.quantity_to_pick || '0'})"
                            iconRight="fal fa-debug"
                            :label="itemValue.quantity_to_pick.toString() || '0'"
                            :size="screenType == 'desktop' ? 'sm' : 'lg'"
                            :routeTarget="itemValue.not_picking_route"
                            :bindToLink="{ preserveScroll: true }"
                        />
                    </div>
                </div>
            </div>

            <div v-else-if="Number(itemValue.quantity_waiting_warehouse) < 1 && Number(itemValue.quantity_waiting_crm) < 1" class="flex justify-between gap-x-2 gap-y-1">
                <div v-if="!itemValue.is_handled" class="text-gray-400 italic text-sm">
                    {{ trans("No quantity to pick") }}
                </div>

                <div class="flex gap-x-2 gap-y-1">
                    <ButtonWithLink
                        v-if="!itemValue.is_handled"
                        type="negative"
                        tooltip="No quantity to pick. Click to ignore."
                        xicon="fal fa-debug"
                        label="Click to ignore"
                        :size="screenType == 'desktop' ? 'sm' : 'lg'"
                        :routeTarget="itemValue.not_picking_route"
                        :bindToLink="{preserveScroll: true}"
                    />
                </div>
            </div>

            <!-- Section: items are waiting for warehouse -->
            
            <div v-if="Number(itemValue.quantity_waiting_warehouse) > 0" class="mt-2 mx-auto w-fit flex gap-x-2">
                <Link :href="routeItemsWaitingWarehouse(itemValue)" class="hover:underline">
                    <LabelItemsWaitingForWarehouse :qty_waiting_warehouse="Number(itemValue.quantity_waiting_warehouse)">
                    </LabelItemsWaitingForWarehouse>
                </Link>
                <Button
                    @click="isOpenModalUndoWaitingWarehouse = true, selectedItemToUndoWaitingWarehouse = itemValue"
                    v-tooltip="ctrans('Reset :qtyWaiting waiting items for warehouse', { qtyWaiting: Number(itemValue.quantity_waiting_warehouse).toString()})"
                    type="negative"
                    :size="screenType != 'mobile' ? 'xxs' : 'md'"
                    icon="fal fa-undo-alt"
                />
            </div>

            <!-- Section: items are waiting for CRM -->
            
            <div v-if="Number(itemValue.quantity_waiting_crm) > 0" class="mx-auto w-fit">
                <Link :href="routeItemsWaitingCrm(itemValue)" class="hover:underline">
                    <LabelItemsWaitingForCrm v-if="Number(itemValue.quantity_waiting_crm) > 0" :qty_waiting_crm="Number(itemValue.quantity_waiting_crm)" />
                </Link>
            </div>

        </template>

        <template #cell(action)="{ item: item }">
                <template v-if="(state === 'packing' || state === 'packed') && props.shop_type !== 'dropshipping' && item.quantity_picked > 0" >
                    
                    <div class="flex justify-start items-center">
                    <ButtonWithLink
                        v-if="!item.is_done_packing"
                        :label="ctrans('Pack :countToPack items', { countToPack: Number(item.quantity_picked) })"
                        type="secondary"
                        xlabel="ctrans('Packing')"
                        :size="screenType == 'desktop' ? 'xs' : 'lg'"
                        :key="screenType"
                        :bindToLink="{preserveScroll: true}"
                        :routeTarget="{
                            name: 'grp.models.delivery_note_item.packing.store',
                            method: 'patch',
                            parameters: {
                                deliveryNoteItem: item.id
                            }
                        }"
                    />
                    <ButtonWithLink
                        v-else
                        v-tooltip="ctrans('Undo packing')"
                        type="negative"
                        :size="screenType == 'desktop' ? 'xs' : 'lg'"
                        :bindToLink="{preserveScroll: true}"
                        :routeTarget="{
                            name: 'grp.models.delivery_note_item.packing.delete',
                            method: 'delete',
                            parameters: {
                                deliveryNoteItem: item.id
                            }
                        }"
                        :icon="faUndo"
                    />
                     <Button
                     v-if="layout.app.environment === 'local' && !item.is_done_packing"
                        type="negative"
                        class="ml-4"
                        icon="fal fa-debug"
                        :size="screenType == 'desktop' ? 'xs' : 'lg'"
                        v-tooltip="'Packing info'"
                        @click="openPackingModal(item)"
                    />
                    </div>
                </template>

                <div v-else-if="(state === 'packing' || state === 'packed') && props.shop_type !== 'dropshipping' && !(item.quantity_picked > 0)" class="italic text-xs opacity-70">
                    {{ ctrans("Nothing to pack") }}
                </div>
        </template>
    </Table>

    <Modal
        :isOpen="isModalPackingInfo"
        @onClose="closePackingModal"
        width="w-full max-w-4xl"
    >
        <div class="text-center text-xl font-semibold mb-4">
            Packing Info — {{ selectedPackingItem?.org_stock_code }}
        </div>
        <Table :resource="modalResource" :name="tab" class="mt-5" rowAlignTop>
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

            <template #cell(action)="{ item: item }">
                <!-- {{ item.not_picking_route }} -->
                <!-- icon="fal fa-debug" -->
                <ButtonWithLink
                    type="negative"
                    tooltip="No quantity to pick. Click to ignore."
                             label="Click to ignore"
                            :size="screenType == 'desktop' ? 'sm' : 'lg'"
                            :routeTarget="item.not_picking_route"
                            :bindToLink="{preserveScroll: true}"
                             @success="closePackingModal"
                        />
                    
            </template>
        </Table>
       
        <Button
            class="mt-6"
            full
            label="Close"
            @click="closePackingModal"
        />
    </Modal>


    <!-- Modal: Location picker -->
    
    <Modal :isOpen="isModalLocation" @onClose="isModalLocation = false" width="w-full max-w-3xl" xdialogStyle="{ background: '#ffffff' }">
        <SelectPickingLocation
            :item="selectedItemValue"
            :selectedLocationCode="get(selectedLocationCode, [selectedItemValue?.id], null)"
            @select="(code) => { set(selectedLocationCode, [selectedItemValue?.id], code); isModalLocation = false; }"
        />
    </Modal>

    <!-- Modal: Select batch code -->
    <Modal :isOpen="isModalEditExpiryDate" @onClose="() => onCloseModalExpiryDate()" width="w-full max-w-lg">
        <div class="text-center mb-4">
            <div class="font-semibold text-2xl">{{ trans('Batch Code for') }} {{ selectedItemToEditExpiryDate?.org_stock_code }}:</div>
            <div class="opacity-80 italic text-sm">
                {{ selectedItemToEditExpiryDate?.org_stock_name }}
            </div>
        </div>

        <div class="flex flex-col items-center gap-4">
            <div class="w-full">
                <label class="block text-sm font-medium mb-2">
                    {{ trans("Batch code") }}:
                </label>
                <PureMultiselectInfiniteScroll
                    v-if="batchCodeFetchRoute"
                    v-model="selectedBatchCode"
                    :fetchRoute="batchCodeFetchRoute"
                    :initOptions="selectedBatchCode ? [selectedBatchCode] : []"
                    labelProp="label"
                    valueProp="id"
                    object
                    :placeholder="trans('Search batch code...')"
                    :disabled="isLoadingSubmitExpiryDate"
                />
            </div>

            <div class="w-full flex gap-4 mt-4">
                <Button
                    type="negative"
                    size="md"
                    :disabled="isLoadingSubmitExpiryDate"
                    icon="far fa-arrow-left"
                    @click="onCloseModalExpiryDate"
                    :label="trans('Cancel')"
                />

                <Button
                    type="primary"
                    size="md"
                    :loading="isLoadingSubmitExpiryDate"
                    icon="fad fa-save"
                    @click="onSubmitEditExpiryDate"
                    full
                    :label="trans('Save')"
                />
            </div>
        </div>
    </Modal>

    <!-- Modal: Magic Place -->
    <Modal :isOpen="isModalEPickMagicPlace" @onClose="() => onCloseModalPickMagicPlace()" width="w-full max-w-lg">
        <div
            class="relative text-left sm:w-full sm:max-w-lg py-2">

            <div class="sm:flex sm:items-start">
                <div
                    class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-amber-100 sm:mx-0 sm:size-10">
                    <FontAwesomeIcon
                        icon="fal fa-exclamation-triangle"
                        class="text-amber-600"
                        fixed-width
                        aria-hidden="true" />
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <div class="text-base font-semibold">
                        {{ trans("Are you sure want to pick all from magic place?") }}
                    </div>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                            {{ trans("Yes, magic place.") }}
                        </p>
                    </div>

                    <div class="mt-5 flex flex-row-reverse gap-2">
                        <div class="xw-full sm:w-fit">
                            <Button
                                @click="() => onSubmitPickMagicPlace()"
                                type="warning"
                                key="2"
                                :loading="isLoadingSubmitPickMagicPlace"
                                iconRight="fas fa-wand-magic"
                                full>
                                <template #label>
                                    <div class="whitespace-nowrap">
                                        Yes, pick <FractionDisplay v-if="GetQuantityToPickFractional(selectedItemToPickMagicPlace)" :fractionData="GetQuantityToPickFractional(selectedItemToPickMagicPlace)" />
                                        <span v-else>{{ locale.number(selectedItemToPickMagicPlace?.quantity_to_pick ?? 0) }}</span>
                                    </div>
                                </template>
                            </Button>
                        </div>
                        <Button
                            type="tertiary"
                            icon="far fa-arrow-left"
                            :disabled="isLoadingSubmitPickMagicPlace"
                            :label="trans('Cancel')"
                            full
                            @click=" () => (isModalEPickMagicPlace = false)" />
                    </div>
                </div>
            </div>
        </div>
    </Modal>

    <!-- Modal: Set Transaction as Waiting -->
    <Modal :isOpen="isOpenModalSetAsWaiting" width="w-full max-w-lg" @close="isOpenModalSetAsWaiting = false">
        <!-- Product info header -->
        <div class="font-semibold text-center text-2xl mb-8">
            {{ trans("Set item as waiting") }}
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
                {{ trans('Quantity to set as waiting') }}:
            </span>
            <span class="font-bold text-amber-800">
                <!-- <FractionDisplay
                    v-if="GetQuantityToPickFractional(selectedTransactionToSetAsWaiting)"
                    :fractionData="GetQuantityToPickFractional(selectedTransactionToSetAsWaiting)"
                />
                <template v-else>{{ locale.number(selectedTransactionToSetAsWaiting.quantity_to_pick + Number(selectedTransactionToSetAsWaiting.quantity_waiting_warehouse || 0) ?? 0) }}</template> -->
                {{ selectedTransactionToSetAsWaiting.quantity_to_pick + Number(selectedTransactionToSetAsWaiting.quantity_waiting_warehouse || 0) }}
                
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
                @click="() => isOpenModalSetAsWaiting = false"
                :label="ctrans('Cancel')"
                type="negative"
            />
            <Button
                @click="() => submitTransactionAsWaiting()"
                :label="trans('Set as waiting')"
                full
                iconRight="far fa-arrow-right"
                :loading="isLoadingSetAsWaiting"
            />
        </div>
    </Modal>

    <!-- Modal: Set Transaction to undo waiting warehouse -->
    <Modal :isOpen="isOpenModalUndoWaitingWarehouse" width="w-full max-w-xl relative" @close="isOpenModalUndoWaitingWarehouse = false">
        <div class="flex min-h-full xitems-end justify-center p-4 text-center items-center sm:py-4">
            <!-- Button: Close -->
            <div class="absolute top-0 right-0 pt-4 pr-4 hidden sm:block">
                <button
                    type="button"
                    class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-hidden"
                    @click="isOpenModalUndoWaitingWarehouse = false">
                    <span class="sr-only">Close</span>
                    <FontAwesomeIcon
                        :icon="'fal fa-times'"
                        class=""
                        fixed-width
                        aria-hidden="true" />
                </button>
            </div>

            <div class="sm:flex sm:items-start">
                <div class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10">
                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-red-600" fixed-width aria-hidden="true" />
                </div>

                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <div as="h3" class="text-base font-semibold">
                        {{ ctrans("Are you sure want to undo quantity waiting for warehouse?") }}
                    </div>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500">
                            {{ ctrans( "Other people may on the progress to picking this item, you need to tell the others about this action" ) }}
                        </p>
                    </div>

                    <div class="mt-4 bg-amber-50 border border-amber-200 rounded flex items-center gap-4 mb-2 py-2 px-3">
                        <div class="shrink-0 size-16 rounded-lg overflow-hidden xbg-gray-100 border border-black/10 flex items-center justify-center">
                            <Image
                                v-if="selectedItemToUndoWaitingWarehouse?.org_stock_image_thumbnail"
                                :src="selectedItemToUndoWaitingWarehouse.org_stock_image_thumbnail"
                                :alt="selectedItemToUndoWaitingWarehouse.org_stock_name"
                            />
                            <FontAwesomeIcon v-else icon="fal fa-image" class="text-2xl text-gray-400" fixed-width aria-hidden="true" />
                        </div>

                        <div class="min-w-0">
                            <div class="text-xl leading-tight font-bold">
                                {{ selectedItemToUndoWaitingWarehouse?.org_stock_code }}
                            </div>
                            <div class="text- opacity-75">
                                {{ selectedItemToUndoWaitingWarehouse?.org_stock_name ?? '-' }}
                            </div>
                            <div class="text-sm text-red-500 opacity-75 italic">
                                {{ ctrans("Quantity waiting for warehouse") }}: {{ Number(selectedItemToUndoWaitingWarehouse?.quantity_waiting_warehouse) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-row-reverse gap-2">
                        <div class="xw-full sm:w-fit">
                            <Button
                                :loading="isLoadingUndoWaitingWarehouse"
                                @click="() => (onSetItemToUndoWaitingWarehouse())"
                                type="red"
                                xlabel="props.noLabel ?? trans('Delete')"
                                :icon="'far fa-trash-alt'"
                                full
                                :label="ctrans('Yes, undo waiting')"
                            />
                        </div>

                        <Button
                            type="tertiary"
                            icccon="far fa-arrow-left"
                            :label="ctrans('Cancel')"
                            full
                            @click="
                                () => ((isOpenModalUndoWaitingWarehouse = false))
                            "
                        />
                    </div>
                </div>
            </div>
            <LoadingOverlay2 v-if="isLoadingUndoWaitingWarehouse" class="rounded-2xl" />
        </div>
    </Modal>

    <!-- Modal: Set batch code per picking (2) -->
    <Modal :isOpen="isModalPickingBatchCode" @onClose="onCloseModalPickingBatchCode" width="w-full max-w-lg">
        <div class="text-center mb-4">
            <div class="font-semibold text-2xl">{{ trans('Batch Code') }}</div>
            <div class="opacity-80 italic text-sm">
                <span>{{ selectedPickingForBatchCode?.location_code ? ctrans('Location: :loc', { loc: selectedPickingForBatchCode.location_code }) : '' }} || </span>
                <span>{{ ctrans("Quantity") }}: {{ selectedPickingForBatchCode?.quantity_picked }}</span>
            </div>
        </div>

        <div class="flex flex-col items-center gap-4">
            <div class="w-full">
                <label class="block text-sm font-medium mb-2">{{ trans("Batch code") }}:</label>
                <PureMultiselectInfiniteScroll
                    v-if="selectedPickingForBatchCode?.batch_codes_fetch_route"
                    v-model="selectedPickingBatchCode"
                    :fetchRoute="selectedPickingForBatchCode.batch_codes_fetch_route"
                    :initOptions="selectedPickingBatchCode ? [selectedPickingBatchCode] : []"
                    labelProp="label"
                    valueProp="id"
                    object
                    :placeholder="trans('Search batch code...')"
                    :disabled="isLoadingSubmitPickingBatchCode"
                >
                    <template #afterlist>
                        <div class="text-center m-2 py-1 cursor-auto text-blue-400 text-sm">
                            {{ trans("Don't see the batch code") }}?

                            <Link
                                :href="route('grp.org.warehouses.show.inventory.batch_codes.index', {
                                    organisation: route().params.organisation,
                                    warehouse: selectedPickingForBatchCode?.warehouse_slug ?? route().params.warehouse,
                                })"
                                class="underline hover:text-blue-700 cursor-pointer"
                            >
                                {{ trans("See the batch codes list") }}
                            </Link>
                        </div>
                    </template>
                </PureMultiselectInfiniteScroll>
            </div>

            <div class="w-full flex gap-4 mt-4">
                <Button
                    type="negative"
                    size="md"
                    :disabled="isLoadingSubmitPickingBatchCode"
                    icon="far fa-arrow-left"
                    @click="onCloseModalPickingBatchCode"
                    :label="trans('Cancel')"
                />
                <Button
                    type="primary"
                    size="md"
                    :loading="isLoadingSubmitPickingBatchCode"
                    icon="fad fa-save"
                    @click="onSubmitPickingBatchCode"
                    full
                    :label="trans('Save')"
                />
            </div>
        </div>
    </Modal>
</template>
