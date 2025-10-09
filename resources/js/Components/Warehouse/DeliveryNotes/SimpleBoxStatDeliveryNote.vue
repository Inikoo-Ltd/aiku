<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUser, faWeight, faCube, faCubes, faEdit, faPlus, faTrashAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
library.add(faUser, faWeight, faCube, faCubes, faEdit, faPlus, faTrashAlt)

import { ref, toRaw, inject } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Fieldset, InputNumber } from "primevue"
import { set, values } from 'lodash-es'

const props = defineProps<{
    boxStats: {
        picker: {
            contact_name: string
        }
        products: {
            estimated_weight: number
            number_items?: number
        }
        parcels: {
            weight: number
            dimensions: [number, number, number]
        }[]
    }
    updateRoute: {
        name: string
        parameters?: Record<string, any>
    }
    deliveryNote: {
        state: string
    }
}>()

// Section: Parcels
const isLoadingSubmitParcels = ref(false)
const isModalParcels = ref(false)
const parcelsCopy = ref([...toRaw(props.boxStats?.parcels || [])])
const onDeleteParcel = (index: number) => {
    parcelsCopy.value.splice(index, 1)
}
const onSubmitParcels = () => {
    router.patch(route(props.updateRoute.name, props.updateRoute.parameters),
        {
            parcels: parcelsCopy.value,
            // parcels: [{
            // 	weight: 1,
            // 	dimensions: [5, 5, 5],
            // }],
        },
        {
            preserveScroll: true,
            onStart: () => {
                isLoadingSubmitParcels.value = true
            },
            onSuccess: () => {
                isModalParcels.value = false
                set(listError, 'box_stats_parcel', false)
            },
            onError: (errors) => {
                notify({
                    title: trans("Something went wrong."),
                    text: trans("Failed to add Shipment. Please try again or contact administrator."),
                    type: "error",
                })
            },
            onFinish: () => {
                isLoadingSubmitParcels.value = false
            },
        })
}

const listError = inject('listError', {})
</script>

<template>
    <div class="bg-white border border-b shadow-sm p-4 text-sm text-gray-700">
        <div class="flex flex-wrap gap-6">
            <!-- Picker -->
            <div class="flex items-center space-x-2 min-w-[160px]">
                <FontAwesomeIcon icon="fal fa-user" class="text-gray-400" fixed-width />
                <span class="font-medium">Picker:</span>
                <span class="text-gray-600 truncate max-w-[100px]">
                    {{ boxStats?.picker?.contact_name || '-' }}
                </span>
            </div>

            <!-- Weight -->
            <div class="flex items-center space-x-2 min-w-[140px]">
                <FontAwesomeIcon icon="fal fa-weight" class="text-gray-400" fixed-width />
                <span class="font-medium">Weight:</span>
                <span class="text-gray-600 truncate">{{ boxStats.products.estimated_weight || 0 }} kg</span>
            </div>

            <!-- Items -->
            <div class="flex items-center space-x-2 min-w-[140px]">
                <FontAwesomeIcon icon="fal fa-cube" class="text-gray-400" fixed-width />
                <span class="font-medium">Items:</span>
                <span class="text-gray-600 truncate">{{ boxStats.products.number_items || 0 }} pcs</span>
            </div>

            <!-- Parcels -->
            <!-- Parcel Summary with Delete Option -->
            <div v-if="['packed', 'dispatched', 'finalised'].includes(deliveryNote?.state)"
                class="flex items-start gap-2 min-w-fit">
                <FontAwesomeIcon icon="fal fa-cubes" class="text-gray-400 mt-0.5" fixed-width />
                <div class="w-full space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="font-medium">Parcels ({{ boxStats.parcels?.length || 0 }})</span>
                        <FontAwesomeIcon v-if="!(boxStats?.shipments?.length > 1) && deliveryNote?.state === 'packed'" icon="fal fa-edit" class="text-blue-500 hover:text-blue-700 cursor-pointer"
                            @click="() => {
                                parcelsCopy = [...toRaw(props.boxStats.parcels || [])]
                                isModalParcels = true
                            }" />
                    </div>

                    <!-- List of Parcels -->
                    <ul class="space-y-1">
                        <li v-for="(p, i) in parcelsCopy" :key="i"
                            class="bg-gray-50 border border-gray-200 rounded px-3 py-1.5 flex justify-between items-center text-xs text-gray-700">
                            <div class="truncate">
                                <span class="font-medium">{{ p.weight }} kg</span> &ndash;
                                <span class="text-gray-500">{{ p.dimensions.join(' x') }} cm</span>
                            </div>
                        </li>
                    </ul>

                    <div v-if="!parcelsCopy.length" class="text-gray-400 text-xs mt-1 italic">
                        {{ trans('No parcels added.') }}
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal -->
        <Modal v-if="true" :isOpen="isModalParcels" @onClose="isModalParcels = false" width="w-full max-w-lg">
            <div class="text-center font-bold mb-4">
                {{ trans('Add Parcels') }}
            </div>

            <div>
                <Fieldset :legend="`${trans('Parcels')} (${parcelsCopy?.length})`">
                    <!-- Header Row -->
                    <div class="grid grid-cols-12 items-center gap-x-6 mb-2">
                        <div class="flex justify-center">
                            <!-- <FontAwesomeIcon icon="fas fa-plus" class="" fixed-width aria-hidden="true" /> -->
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

                                    <!-- <Popover>
										<template #button="{ open }">
											<Button
												@click="() => (open ? false : onOpenModalAddService())"
												:style="action.style"
												:label="action.label"
												:icon="action.icon"
												:key="`ActionButton${action.label}${action.style}`"
												:tooltip="action.tooltip" />
										</template>

<template #content="{ close: closed }">
											<div class="w-[350px]">
												
											</div>
										</template>
</Popover> -->
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
    </div>
</template>
