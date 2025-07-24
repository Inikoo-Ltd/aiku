<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 13 September 2024 11:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl } from "@fal";
import { faSkull } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { onMounted } from "vue";
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import ShipmentSection from "@/Components/Warehouse/DeliveryNotes/ShipmentSection.vue"
import { trans } from "laravel-vue-i18n"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faIdCardAlt, faEnvelope, faPhone, faGift, faBoxFull, faWeight, faCube, faBarcodeRead, faPrint } from "@fal"
import { faCubes } from "@fas"
import { router } from "@inertiajs/vue3"
import { inject, ref, toRaw } from "vue"
import { set } from 'lodash-es'
import { notify } from "@kyvg/vue3-notification"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Skeleton from 'primevue/skeleton';
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Fieldset, InputNumber } from "primevue"
import Icon from "@/Components/Icon.vue"
import axios from "axios"
import PageHeading from "./Headings/PageHeading.vue";

library.add(
    faIdCardAlt, faEnvelope, faPhone, faGift, faBoxFull, faWeight, faCube, faCubes,
    faPrint, faBarcodeRead, faSkull, faArrowDown, faDebug, faClipboardListCheck,
    faUndoAlt, faHandHoldingBox, faListOl
);

const props = defineProps<{
    deliveryNote: Number
}>()

const emits = defineEmits<{
    (e: 'SuccsesUpdateState'): void
}>()

const data = ref(null)
const shipments = ref(null)
const isLoading = ref(true)
const locale = inject('locale', aikuLocaleStructure)

const pageHead = {
    title: props.deliveryNote.delivery_note_reference,
    model: "Delivery Note",
    icon: {
        icon: "fal fa-truck",
        title: "delivery note"
    },
}

// Section: Parcels
const isLoadingSubmitParcels = ref(false)
const isModalParcels = ref(false)
const parcelsCopy = ref()
const onDeleteParcel = (index: number) => {
    parcelsCopy.value.splice(index, 1)
}

const onSubmitParcels = () => {
    router.patch(route('grp.models.delivery_note.update', {
        deliveryNote: props.deliveryNote.delivery_note_id
    }), {
        parcels: parcelsCopy.value,
    }, {
        preserveScroll: true,
        onStart: () => isLoadingSubmitParcels.value = true,
        onSuccess: () => {
            isModalParcels.value = false
            data.value.delivery_note.parcels = parcelsCopy.value
            set(listError, 'box_stats_parcel', false)
        },
        onError: () => {
            notify({
                title: trans("Something went wrong."),
                text: trans("Failed to add Shipment. Please try again or contact administrator."),
                type: "error",
            })
        },
        onFinish: () => isLoadingSubmitParcels.value = false
    })
}

const listError = inject('listError', {})

const getDataDeliveryNote = async () => {
    if (props.deliveryNote?.delivery_note_id) {
        isLoading.value = true
        try {
            const response = await axios.get(route('grp.json.mini_delivery_note', {
                deliveryNote: props.deliveryNote.delivery_note_id,
            }), {
                headers: { Accept: 'application/json' },
            })
            data.value = response.data
            parcelsCopy.value = response.data.delivery_note.parcels || []
        } catch (error) {
            console.error('❌ Error:', error)
        } finally {
            isLoading.value = false
        }
    }
}

const getDataShipment = async () => {
    if (props.deliveryNote?.delivery_note_id) {
        isLoading.value = true
        try {
            const response = await axios.get(route('grp.json.mini_delivery_note_shipments', {
                deliveryNote: props.deliveryNote.delivery_note_id,
            }), {
                headers: { Accept: 'application/json' },
            })
            shipments.value = response.data
        } catch (error) {
            console.error('❌ Error:', error)
        } finally {
            isLoading.value = false
        }
    }
}

const onSuccessPacked = () => {
    props.deliveryNote.delivery_note_state = 'packed'
    getDataDeliveryNote()
    getDataShipment()
}

// 🔁 Finalise + Packed Button Logic (router)
const loadingFinal = ref(false)

const handleFinaliseAndDispatch = () => {
    if (!props.deliveryNote?.delivery_note_id) return

    loadingFinal.value = true
    router.patch(
        route('grp.models.delivery_note.state.finalise_and_dispatch', {
            deliveryNote: props.deliveryNote.delivery_note_id,
        }),
        {},
        {
            onFinish: () => loadingFinal.value = false,
            onSuccess: () => emits('SuccsesUpdateState'),
        }
    )
}


const handleSetAsPacked = async () => {
    if (!props.deliveryNote?.delivery_note_id) return

    loadingFinal.value = true
    try {
        await axios.patch(
            route('grp.models.delivery_note.state.packed', {
                deliveryNote: props.deliveryNote.delivery_note_id,
            }),
            {},
            {
                headers: {
                    Accept: 'application/json',
                },
            }
        )

        onSuccessPacked()
    } catch (error) {
        console.error('❌ Failed to set as packed:', error)
        notify({
            type: "error",
            title: trans("Failed"),
            text: trans("Unable to set delivery note as packed."),
        })
    } finally {
        loadingFinal.value = false
    }
}


onMounted(() => {
    getDataDeliveryNote()
    getDataShipment()
})
</script>

<template>
    <div class="max-h-[500px] overflow-auto">

    <PageHeading v-if="props.deliveryNote" :data="pageHead" isButtonGroupWithBorder :key="props.deliveryNote?.state">
        <template #other>
            <Button v-if="props.deliveryNote?.delivery_note_id && props.deliveryNote.delivery_note_state === 'packed'"
                type="save" label="Finalise and Dispatch" :loading="loadingFinal" @click="handleFinaliseAndDispatch" />

            <Button v-if="props.deliveryNote?.delivery_note_id && props.deliveryNote.delivery_note_state === 'handling'"
                type="save" label="Set as packed" size="sm" class="mx-3" :loading="loadingFinal"
                @click="handleSetAsPacked" />
        </template>
    </PageHeading>

    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4 py-4">
        <div v-for="n in 2" :key="n" class="p-4 border border-gray-200 rounded-lg shadow-sm space-y-4">
            <Skeleton height="1.25rem" width="60%" class="rounded" />
            <Skeleton height="1.25rem" width="80%" class="rounded" />
            <Skeleton height="1.25rem" width="80%" class="rounded" />
            <Skeleton height="1.25rem" width="80%" class="rounded" />
        </div>
    </div>

    <div v-if="!isLoading && data" class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-gray-200 pt-4">
        <!-- Box 1: Shipping -->
        <BoxStatPallet class="p-4 space-y-2 border rounded-lg shadow-sm bg-white">
            <h3 class="text-base font-semibold text-gray-800 mb-2">{{ trans("Shipping") }}</h3>

            <div v-if="data.delivery_note?.delivery_address">
                <div class="border border-gray-300 p-4 rounded-lg bg-gray-50 space-y-2 text-sm text-gray-700">
                    <div v-if="data.delivery_note.customer_client">
                        <p><strong>{{ trans("Name") }}:</strong> {{ data.delivery_note.customer_client.contact_name || data.delivery_note.customer_client.name }}</p>
                        <p v-if="data.delivery_note.customer_client.email"><strong>{{ trans("Email") }}:</strong> {{ data.delivery_note.customer_client.email }}</p>
                        <p v-if="data.delivery_note.customer_client.phone"><strong>{{ trans("Phone") }}:</strong> {{ data.delivery_note.customer_client.phone }}</p>
                    </div>
                    <div v-html="data.delivery_note.delivery_address.formatted_address" class="text-gray-600 text-sm"></div>
                </div>
            </div>

            <div v-else class="text-gray-500 italic">
                {{ trans("No shipping information available.") }}
            </div>
        </BoxStatPallet>

        <!-- Box 2: Delivery Note -->
        <BoxStatPallet class="p-4 space-y-2 border rounded-lg shadow-sm bg-white">
            <h3 class="text-base font-semibold text-gray-800 mb-2">{{ trans("Delivery Note") }}</h3>

            <div class="space-y-1 text-sm text-gray-700">
                <div v-if="data.delivery_note?.picker?.contact_name">
                    <dl class="border-l-4 border-indigo-300 bg-indigo-100 pl-2 py-1">
                        <dt>{{ trans("Picker") }}:</dt>
                        <dd class="text-gray-600">{{ data.delivery_note.picker.contact_name }}</dd>
                    </dl>
                </div>

                <dl class="flex items-center gap-2">
                    <dt><Icon :data="data.delivery_note?.state_icon" /></dt>
                    <dd class="text-gray-600">{{ data.delivery_note.state_label }}</dd>
                </dl>

                <dl class="flex items-center gap-2">
                    <dt><FontAwesomeIcon icon="fal fa-cube" class="text-gray-500" /></dt>
                    <dd>{{ locale.number(data.delivery_note.products?.number_items || 0) }} items</dd>
                </dl>

                <dl class="flex items-center gap-2">
                    <dt><FontAwesomeIcon icon="fal fa-weight" class="text-gray-500" /></dt>
                    <dd>{{ locale.number(data.delivery_note?.products.estimated_weight) || '-' }} kg</dd>
                </dl>

                <div v-if="['packed', 'dispatched', 'finalised'].includes(data.delivery_note?.state)">
                    <div class="flex justify-between items-center text-sm">
                        <div class="font-medium text-gray-700">
                            {{ trans("Parcels") }} ({{ data.delivery_note?.parcels?.length ?? 0 }})
                        </div>
                        <div v-if="data.delivery_note?.state === 'packed'"
                            class="text-gray-500 cursor-pointer hover:text-gray-700"
                            @click="() => (isModalParcels = true, parcelsCopy = [...data.delivery_note?.parcels || []])">
                            {{ trans("Edit") }}
                            <FontAwesomeIcon icon="fal fa-pencil" size="sm" />
                        </div>
                    </div>
                    <ul class="list-disc pl-4 mt-1 text-gray-600 text-xs space-y-0.5">
                        <li v-for="(parcel, idx) in data.delivery_note?.parcels" :key="idx">
                            {{ parcel.weight }} kg ({{ parcel.dimensions?.join('×') }} cm)
                        </li>
                    </ul>
                </div>

                <div
                    v-if="['packed', 'dispatched', 'finalised'].includes(data.delivery_note?.state) && props.deliveryNote">
                    <ShipmentSection
                        :shipments="shipments?.shipment.shipments"
                        :shipments_routes="shipments.shipment.shipments_routes"
                        :address="data.delivery_note.address"
                        @addSuccsess="getDataShipment()"
                        @editAddressSuccsess="getDataDeliveryNote()"
                        @deleteSuccsess="getDataShipment()"
                        :updateAddressRoute="{
                            name: 'grp.models.delivery_note.update_address',
                            parameters: { deliveryNote: props.deliveryNote.delivery_note_id }
                        }"
                    />
                </div>
            </div>
        </BoxStatPallet>
    </div>

</div>


    <Modal :isOpen="isModalParcels" @onClose="isModalParcels = false" width="w-full max-w-lg">
        <div class="text-center font-bold mb-4">
            {{ trans('Add shipment') }}
        </div>

        <div>
            <Fieldset :legend="`${trans('Parcels')} (${parcelsCopy?.length})`">
                <!-- Header Row -->
                <div class="grid grid-cols-12 items-center gap-x-6 mb-2">
                    <div class="flex justify-center">
                    </div>

                    <div class="col-span-2 flex items-center space-x-1">
                        <FontAwesomeIcon icon="fal fa-weight" class="" fixed-width aria-hidden="true" />
                        <span>kg</span>
                    </div>
                    <div class="col-span-9 flex items-center space-x-1">
                        <FontAwesomeIcon icon="fal fa-ruler-triangle" class="" fixed-width aria-hidden="true" />
                        <span>cm</span>
                    </div>
                </div>

                <!--  -->
                <div class="grid gap-y-1 max-h-64 overflow-y-auto pr-2">
                    <!-- {{parcelsCopy.length}} xx {{ boxStats.parcels.length }} -->
                    <TransitionGroup v-if="parcelsCopy?.length" name="list">
                        <div v-for="(parcel, parcelIndex) in parcelsCopy" :key="parcelIndex"
                            class="grid grid-cols-12 items-center gap-x-6">
                            <div @click="() => onDeleteParcel(parcelIndex)" class="flex justify-center">
                                <FontAwesomeIcon icon="fal fa-trash-alt"
                                    class="text-red-400 hover:text-red-600 cursor-pointer" fixed-width
                                    aria-hidden="true" />
                            </div>
                            <div class="col-span-2 flex items-center space-x-2">
                                <InputNumber :min="0.001" v-model="parcel.weight" class="w-16" size="small"
                                    placeholder="0" fluid />
                            </div>
                            <div class="col-span-9 flex items-center gap-x-1 font-light">
                                <InputNumber :min="0.001" v-model="parcel.dimensions[0]" class="w-16" size="small"
                                    placeholder="0" fluid />
                                <div class="text-gray-400">x</div>
                                <InputNumber :min="0.001" v-model="parcel.dimensions[1]" class="w-16" size="small"
                                    placeholder="0" fluid />
                                <div class="text-gray-400">x</div>
                                <InputNumber :min="0.001" v-model="parcel.dimensions[2]" class="w-16" size="small"
                                    placeholder="0" fluid />
                                <!-- <button class="text-gray-600">≡</button> -->
                            </div>
                        </div>
                    </TransitionGroup>
                    <div v-else class="text-center text-gray-400">
                        {{ trans('No parcels') }}
                    </div>
                </div>

                <!-- Repeat for more rows -->
                <div class=" grid grid-cols-12 mt-2">
                    <div></div>
                    <div @click="() => parcelsCopy.push({ weight: 1, dimensions: [5, 5, 5] })"
                        class="hover:bg-gray-200 cursor-pointer border border-dashed border-gray-400 col-span-11 text-center py-1.5 text-xs rounded">
                        <FontAwesomeIcon icon="fas fa-plus" class="text-gray-500" fixed-width aria-hidden="true" />
                        {{ trans("Add another parcel") }}
                    </div>
                </div>
            </Fieldset>

            <div class="flex justify-end mt-3">
                <Button :style="'save'" :loading="isLoadingSubmitParcels" :label="'save'" xdisabled="
							!formTrackingNumber.shipping_id || !(formTrackingNumber.shipping_id.api_shipper ? true : formTrackingNumber.tracking_number)
						" full @click="() => onSubmitParcels()" />
            </div>
        </div>
    </Modal>
    
</template>
