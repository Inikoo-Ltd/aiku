<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 19 Jul 2025 11:19:28 British Summer Time, Trnava, Slovakia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { router, useForm } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { useTruncate } from '@/Composables/useTruncate'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'
import { routeType } from '@/types/route'
import axios from 'axios'
import PureAddress from '@/Components/Pure/PureAddress.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { get, set } from 'lodash'
import ConfirmDialog from 'primevue/confirmdialog';
import { faExclamationCircle } from '@fal'
import { useConfirm } from "primevue/useconfirm";
import { twBreakPoint } from '@/Composables/useWindowSize'
import { RadioButton } from 'primevue'

const props = defineProps<{
    shipments: {
        id: number
        name: string
        tracking: string
        label?: string
        label_type?: string
        combined_label_url?: string
        is_printable?: boolean
        formatted_tracking_urls: {
            url: string
            tracking: string
        }[]
    }[]
    shipments_routes: {
        submit_route: routeType
        fetch_route: routeType
        delete_route: routeType
    }
    address: {

    }

    updateAddressRoute: routeType
}>()

const emits = defineEmits<{
    (e: 'addSuccsess', value: string | number): void
    (e: 'deleteSuccsess', value: string | number): void
    (e: 'editAddressSuccsess', value: string | number): void
}>()

// Shipment deletion
const isDeleteShipment = ref<number | null>(null)
// const onDeleteShipment = (idShipment: number) => {
//     router.delete(route(props.deleteRoute.name, {
//         ...props.deleteRoute.parameters,
//         shipment: idShipment,
//     }),
//         {
//             preserveScroll: true,
//             onStart: () => {
//                 isDeleteShipment.value = idShipment
//             },
//             onSuccess: () => {
//                 notify({
//                     title: trans("Success!"),
//                     text: trans("Shipment has deleted."),
//                     type: "success",
//                 })
//             },
//             onError: (errors) => {
//                 notify({
//                     title: trans("Something went wrong."),
//                     text: trans("Failed to delete shipment. Please try again or contact administrator."),
//                     type: "error",
//                 })
//             },
//             onFinish: () => {
//                 isDeleteShipment.value = null
//             },
//         })
// }

// PDF conversion
const base64ToPdf = (base: string) => {
    // Convert base64 to byte array
    const byteCharacters = atob(base)
    const byteNumbers = Array.from(byteCharacters, char => char.charCodeAt(0))
    const byteArray = new Uint8Array(byteNumbers)

    // Create a Blob and generate object URL
    const blob = new Blob([byteArray], { type: 'application/pdf' })
    const blobUrl = URL.createObjectURL(blob)

    // Create a temporary link to trigger download
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = 'file.pdf'
    link.click()

    // Clean up the object URL
    URL.revokeObjectURL(blobUrl)
}

// Print shipment
const isLoadingPrint = ref(false)
const onPrintShipment = async (ship) => {
    isLoadingPrint.value = true
    try {
        const response = await axios.post(
            route(
                'grp.models.printing.shipment.label',
                {
                    shipment: ship.id
                }
            )
        )

        if (response.data.state === 'queued') {
            notify({
                title: trans("Got it!"),
                text: trans("Your shipment label is queued for printing."),
                type: "info",
            })
        } else if (response.data.state === 'error') {
            throw new Error(response.data.message || 'Failed to print shipment label.')
        }

    } catch (error) {
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to print shipment label. Please try again or contact administrator."),
            type: "error",
        })
    } finally {
        isLoadingPrint.value = false
    }
}

// ----------------------------------------------------------------------

// Section: Shipment
const isLoadingButton = ref<string | boolean>(false);
const isLoadingData = ref<string | boolean>(false);
const formTrackingNumber = useForm({ shipping_id: "", tracking_number: "" });
const isModalShipment = ref(false);
const optionShippingList = ref([]);
const onOpenModalTrackingNumber = async () => {
    isLoadingData.value = "addTrackingNumber";
    try {
        const xxx = await axios.get(
            route(props.shipments_routes.fetch_route.name, props.shipments_routes.fetch_route.parameters)
        );
        optionShippingList.value = xxx?.data?.data || [];
    } catch (error) {
        console.error(error);
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to retrieve shipper list"),
            type: "error"
        });
    }
    isLoadingData.value = false;
};

const onSubmitShipment = () => {
    formTrackingNumber
        .transform((data) => ({
            shipper_id: data.shipping_id?.id,
            tracking: data.shipping_id?.api_shipper ? undefined : data.tracking_number
        }))
        .post(route(props.shipments_routes.submit_route.name, { ...props.shipments_routes.submit_route.parameters }), {
            preserveScroll: true,
            onStart: () => {
                isLoadingButton.value = "addTrackingNumber";
            },
            onSuccess: () => {
                emits('addSuccsess', null)
                isModalShipment.value = false;
                isModalErrorShipment.value = false; // Close the error modal
                formTrackingNumber.reset();
            },
            onError: (errors) => {
                // TODO: Make condition if the error related to delivery address then set to true
                // set(listError.value, 'box_stats_delivery_address', true) // To make the Box stats delivery address error
                if (errors.address) {
                    shipmentErrorMessage.value = errors.address
                    isModalErrorShipment.value = true; // Open the modal if the error related to address
                }
                
                notify({
                    title: trans("Something went wrong."),
                    text: errors.message,
                    type: "error"
                });
            },
            onFinish: () => {
                isLoadingButton.value = false;
            }
        });
}

const onSaveAddress = (submitShipment: Function) => {
    if (!props.updateAddressRoute?.name) return

    const filterDataAddress = { ...xxxCopyAddress.value }
    delete filterDataAddress.formatted_address
    delete filterDataAddress.country
    delete filterDataAddress.country_code
    delete filterDataAddress.id
    delete filterDataAddress.can_edit
    delete filterDataAddress.can_delete

    router.patch(
        route(props.updateAddressRoute.name, props.updateAddressRoute.parameters),
        {
            address: filterDataAddress
        },
        {
            preserveScroll: true,
            onStart: () => isLoadingButton.value = true,
            onFinish: () => {
                isLoadingButton.value = false

            },
            onSuccess: () => {
                emits('editAddressSuccsess', { ...xxxCopyAddress.value })
                submitShipment()
            },
            onError: () => notify({
                title: trans("Something went wrong"),
                text: trans("Failed to update the address, try again."),
                type: "error"
            })
        }
    )
}


const onSubmitAddressThenShipment = () => {
    onSaveAddress(onSubmitShipment)
}

// const DeleteShipment = (index) => {
//     isModalShipment.value = false  // ✅ Close the modal after delete
//     emits('deleteSuccsess', index)
// }

const confirm = useConfirm("confirm-delete");

const confirmdelete = (event: MouseEvent, shipment) => {
    confirm.require({
        target: event.currentTarget,
        group: "confirm-delete",
        message: trans('Are you sure you want to delete this shipment (:ship)?', { ship: shipment.name }),
        header: trans('Confirm Delete'),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: trans('Cancel'),
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: trans('Yes, delete it'),
            severity: 'danger',
        },
        accept: () => {
            // Call your delete logic here
            router.delete(route('grp.models.shipment.delete', {
                shipment: shipment.id,
            }), {
                preserveScroll: true,
                onStart: () => {
                    isDeleteShipment.value = shipment.id
                },
                onSuccess: () => {
                    notify({
                        title: trans("Success!"),
                        text: trans("Shipment has been deleted."),
                        type: "success",
                    })
                    emits('deleteSuccsess', shipment.id)
                },
                onError: () => {
                    notify({
                        title: trans("Something went wrong."),
                        text: trans("Failed to delete shipment. Please try again or contact administrator."),
                        type: "error",
                    })
                },
                onFinish: () => {
                    isDeleteShipment.value = null
                }
            })
        },
    });
};


const xxxCopyAddress = ref({ ...props.address?.delivery })

function handleShipmentClick(shipment: number) {
  if (isLoadingButton.value === 'addTrackingNumber') return

  formTrackingNumber.shipping_id = shipment

  if (formTrackingNumber?.errors?.address) {
    onSubmitAddressThenShipment()
  } else {
    onSubmitShipment()
  }
}

const selectedShipment = ref('create_label')


// Section: Shipment Error
const isModalErrorShipment = ref(false)
const shipmentErrorMessage = ref('')
</script>

<template>
    <div class="flex gap-x-1 py-0.5">
        <div class="w-full">
            <div v-if="props.shipments_routes?.submit_route?.name"
                class="leading-4 xtext-base flex justify-between w-full py-1">
                <div>{{ trans("Shipments") }}</div>
            </div>

            <ul v-if="shipments.length" class="list-none">
                <li v-for="(shipment, shipmentIdx) in shipments" :key="shipmentIdx"
                    class="px-2.5 py-2 rounded bg-gray-50 border border-gray-200 tabular-nums ">
                    <div class="flex flex-col justify-between gap-x-2 relative">
                        <div class="font-semibold text-sm">{{ shipment.name }}</div>

                        <div v-if="shipment.formatted_tracking_urls && shipment.formatted_tracking_urls.length > 0">
                            <div v-for="(trackingItem, trackingIndex) in shipment.formatted_tracking_urls"
                                :key="trackingIndex" class="text-sm">
                                <a v-tooltip="trans('Open tracking in new tab')" :href="trackingItem.url" target="_blank" class="-ml-1 secondaryLink">
                                    {{ trackingItem.tracking }}
                                    <FontAwesomeIcon icon="fal fa-external-link" class="opacity-70"
                                        fixed-width aria-hidden="true" />
                                </a>
                            </div>
                        </div>
                        
                        <a v-if="shipment.combined_label_url" v-tooltip="trans('Click to open file')" target="_blank"
                            :href="shipment.combined_label_url" class="w-fit text-gray-400 hover:text-blue-600">
                            <FontAwesomeIcon icon="fal fa-barcode-read" class=""
                                fixed-width aria-hidden="true" />
                        </a>

                        <div v-else-if="shipment.label && shipment.label_type === 'pdf'"
                            v-tooltip="trans('Click to download file')" @click="base64ToPdf(shipment.label)"
                            class="group cursor-pointer hover:underline w-fit">
                            <span v-if="shipment.tracking" class="text-gray-400">
                                ({{ useTruncate(shipment.tracking, 18) }})
                            </span>
                            <FontAwesomeIcon icon="fal fa-external-link" class="text-gray-400 group-hover:text-gray-700"
                                fixed-width aria-hidden="true" />
                        </div>

                        <div v-else-if="shipment.tracking" class="text-gray-400 text-base">
                            {{ useTruncate(shipment.tracking, 18) }}
                        </div>

                        <div v-if="isDeleteShipment === shipment.id" class="px-1 absolute top-0 right-0">
                            <LoadingIcon />
                        </div>

                        <!--  <ModalConfirmationDelete v-else :routeDelete="{
                            name: 'grp.models.shipment.delete',
                            parameters: {

                                shipment: shipment.id,
                            }
                        }" :title="trans('Are you sure you want to delete this shipment (:ship)?', { ship: shipment.name })"
                            @success="DeleteShipment(shipmentIdx)"
                            isFullLoading>
                            <template #default="{ isOpenModal, changeModel }">
                                <div @click="changeModel" class="cursor-pointer">
                                    <FontAwesomeIcon icon="fal fa-times" class="text-red-400 hover:text-red-600"
                                        fixed-width aria-hidden="true" />
                                </div>
                            </template>
</ModalConfirmationDelete> -->

                        <div v-else class="cursor-pointer px-2 py-1 lg:py-0 lg:px-1 absolute top-0 right-0 text-red-400 hover:text-red-700" v-tooltip="trans('Remove shipment')" @click="(e) => confirmdelete(e, shipment)">
                            <FontAwesomeIcon icon="fal fa-times" class=" " fixed-width
                                aria-hidden="true" />
                        </div>
                    </div>

                    <Button v-if="shipment.is_printable" @click="(e) => onPrintShipment(shipment)"
                        :size="twBreakPoint().includes('lg') ? 'xs' : undefined"
                        icon="fal fa-print"
                        :label="trans('Print label')"
                        type="tertiary"
                        :loading="isLoadingPrint"
                    />
                </li>
            </ul>

            <div>
                <!-- Button: Shipment -->
                <Button
                    xv-if="['packed', 'finalised', 'dispatched'].includes(delivery_note_state.value) && !(box_stats?.shipments?.length)"
                    v-if="!shipments.length && props.shipments_routes?.submit_route?.name"
                    :disabled="props.shipments_routes?.submit_route?.name ? false : true"
                    @click="() => (isModalShipment = true, onOpenModalTrackingNumber())"
                    xv-tooltip="box_stats.parcels?.length ? '' : trans('Please add at least one parcel')"
                    :label="trans('Shipment')" icon="fas fa-plus" type="dashed"
                    :size="twBreakPoint().includes('lg') ? 'xs' : undefined"
                />
                <div v-else-if="!shipments.length" class="italic text-gray-400 text-xs">
                    {{ trans("No shipment yet. Waiting for warehouse team to add shipment..") }}
                </div>
            </div>
        </div>

        <!-- Modal: Shipment -->
        <Modal xv-if="['packed', 'finalised', 'dispatched'].includes(delivery_note_state.value)"
            :isOpen="isModalShipment" @onClose="!isModalErrorShipment ? isModalShipment = false : null" width="w-full max-w-2xl">
            <div>
                <!-- <div class="text-center font-bold mb-4">
                    {{ trans("Add shipment") }}
                </div> -->

                <!-- Section: Create label -->
                <div class="w-full mt-3">
                    <div class="text-xs px-1 my-2">
                        <RadioButton v-model="selectedShipment" inputId="create_label" name="select_shipment" value="create_label" size="small" />
                        <label for="create_label" class="ml-1 cursor-pointer">{{ trans("Create label") }}:</label>
                    </div>

                    <div v-if="selectedShipment === 'create_label'" class="ml-6 relative">
                        <div class="grid grid-cols-3 gap-x-2 gap-y-2 mb-2">
                            <div v-if="isLoadingData === 'addTrackingNumber'" v-for="sip in 3"
                                class="skeleton w-full max-w-52 h-20 rounded">
                            </div>
                            <div v-else
                                v-for="(shipment, index) in optionShippingList.filter(shipment => shipment.api_shipper)"
                                @click="() => (set(formTrackingNumber, ['errors', 'address'], null), handleShipmentClick(shipment))"
                                class="relative isolate w-full max-w-52 h-20 border rounded-md px-4 py-3 cursor-pointer"
                                :class="[
                                    formTrackingNumber.shipping_id?.id == shipment.id
                                        ? 'bg-indigo-200 border-indigo-300'
                                        : 'hover:bg-gray-100 border-gray-300',
                                ]"
                            >
                                <div v-tooltip="shipment.name" class="font-bold tesm">{{ shipment.trade_as }}</div>
                                <div class="text-sm text-gray-500 italic">
                                    {{ shipment.code }}
                                    <!-- {{ shipment.phone }} -->
                                </div>
                                <!-- <div class="text-xs text-gray-500 italic">
                                    {{ shipment.tracking_url }}
                                </div> -->
                                <FontAwesomeIcon v-tooltip="trans('Barcode print')" icon="fal fa-print"
                                    class="text-gray-500 absolute top-3 right-3" fixed-width aria-hidden="true" />
                                <div v-if="isLoadingButton == 'addTrackingNumber'"
                                    class="bg-black/40 rounded-md absolute inset-0 z-10">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Field: Address -->
                        <!-- <template v-if="formTrackingNumber?.errors?.address">
                            <div class="relative my-3 p-2 rounded bg-gray-100"
                                :class="formTrackingNumber?.errors?.address ? 'errorShake' : ''">
                                <PureAddress v-model="xxxCopyAddress" :options="address?.options" xfieldLabel />
                                <div v-if="isLoadingButton"
                                    class="absolute inset-0 bg-black/40 text-white flex place-content-center items-center text-4xl">
                                    <LoadingIcon />
                                </div>
                            </div>
                            
                            <div class="flex justify-end mt-3">
                                <Button :style="'save'" :loading="isLoadingButton == 'addTrackingNumber'" :label="'save'"
                                    :disabled="!formTrackingNumber.shipping_id || !(formTrackingNumber.shipping_id?.api_shipper ? true : formTrackingNumber.tracking_number)
                                        " full
                                    @click="() => onSubmitAddressThenShipment()" />
                            </div>
                        </template> -->
                    </div>
                </div>
                
                <!-- Section: Other options -->
                <div class="text-xs px-1 my-2">
                    <RadioButton v-model="selectedShipment" inputId="other_options" name="select_shipment" value="other_options" size="small" />
                    <label for="other_options" class="ml-1 cursor-pointer">{{ trans("Other options") }}:</label>
                </div>

                <div v-if="selectedShipment === 'other_options'" class="ml-6">
                    <div class="">
                        <PureMultiselectInfiniteScroll
                            v-model="formTrackingNumber.shipping_id"
                            @update:modelValue="() => set(formTrackingNumber, ['errors', 'address'], null)"
                            :fetchRoute="shipments_routes.fetch_route" required
                            :disabled="isLoadingButton == 'addTrackingNumber'" :placeholder="trans('Select shipping')"
                            object @optionsList="(e) => optionShippingList = e">
                            <template #singlelabel="{ value }">
                                <div class="w-full text-left pl-4">
                                    {{ value.name }}
                                    <span class="text-sm text-gray-400">({{ value.code }})</span>
                                </div>
                            </template>
                            <template #option="{ option, isSelected, isPointed }">
                                <div class="">
                                    {{ option.name }}
                                    <span class="text-sm text-gray-400">({{ option.code }})</span>
                                </div>
                            </template>
                        </PureMultiselectInfiniteScroll>
                        <p v-if="get(formTrackingNumber, ['errors', 'shipping_id'])" class="mt-2 text-sm text-red-500">
                            {{ formTrackingNumber.errors.shipping_id }}
                        </p>
                    </div>

                    <!-- Tracking number -->
                    <div v-if="formTrackingNumber.shipping_id && !formTrackingNumber.shipping_id?.api_shipper"
                        class="mt-3">
                        <span class="text-xs px-1 my-2">{{ trans("Tracking number") }}: </span>
                        <PureInput v-model="formTrackingNumber.tracking_number" placeholder="ABC-DE-1234567"
                            xxkeydown.enter="() => onSubmitAddService(action, closed)" />
                        <p v-if="get(formTrackingNumber, ['errors', 'tracking_number'])"
                            class="mt-2 text-sm text-red-600">
                            {{ formTrackingNumber.errors.tracking_number }}
                        </p>
                    </div>

                    <!-- Section: error -->
                    <div v-if="Object.keys(get(formTrackingNumber, ['errors'], {}))?.length"
                        class="mt-2 text-sm text-red-600">
                        <p v-if="typeof formTrackingNumber?.errors?.address === 'string'" class="italic">
                            *{{ formTrackingNumber?.errors?.address }}
                        </p>
                        <p v-else v-for="errorx in formTrackingNumber?.errors?.address">
                            {{ errorx }}
                        </p>
                    </div>

                    <!-- Field: Address -->
                    <!-- <div v-if="formTrackingNumber?.errors?.address" class="relative my-3 p-2 rounded bg-gray-100"
                        :class="formTrackingNumber?.errors?.address ? 'errorShake' : ''">
                        <PureAddress v-model="xxxCopyAddress" :options="address?.options" xfieldLabel />
                        <div v-if="isLoadingButton"
                            class="absolute inset-0 bg-black/40 text-white flex place-content-center items-center text-4xl">
                            <LoadingIcon />
                        </div>
                    </div>
                    <div class="flex justify-end mt-3">
                        <Button :style="'save'" :loading="isLoadingButton == 'addTrackingNumber'" :label="'save'"
                            :disabled="!formTrackingNumber.shipping_id || !(formTrackingNumber.shipping_id?.api_shipper ? true : formTrackingNumber.tracking_number)
                                " full
                            @click="() => formTrackingNumber?.errors?.address ? onSubmitAddressThenShipment() : onSubmitShipment()" />
                    </div> -->
                </div>

                <!-- Loading: fetching service list -->
                <div v-if="isLoadingData === 'addTrackingNumber'"
                    class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                    <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin text-5xl" fixed-width
                        aria-hidden="true" />
                </div>
            </div>
        </Modal>
    </div>

    <!-- Modal: Error Modal -->
    <Modal
        :isOpen="isModalErrorShipment" @onClose="() => isModalErrorShipment = false" width="w-full max-w-xl" closeButton>
        <div>
            <div class="text-center font-bold mb-4 text-xl text-red-600">
                {{ trans("Error on save create label") }}
            </div>

            <div class="bg-red-100 border border-red-300 text-red-500 rounded px-4 py-2">
                {{ shipmentErrorMessage }}
            </div>

            <!-- Section: Create label -->
            <div class="w-full mt-3">

                <div  class="relative">
                    <!-- Field: Address -->
                    <div class="relative my-3 p-2 rounded bg-gray-100"
                        :class="formTrackingNumber?.errors?.address ? 'errorShake' : ''">
                        <PureAddress v-model="xxxCopyAddress" :options="address?.options" xfieldLabel />
                        <div v-if="isLoadingButton"
                            class="absolute inset-0 bg-black/40 text-white flex place-content-center items-center text-4xl">
                            <LoadingIcon />
                        </div>
                    </div>
                    
                    <!-- Button: Save -->
                    <div class="flex justify-end mt-3">
                        <Button :style="'save'" :loading="isLoadingButton == 'addTrackingNumber'" :label="'try again'"
                            :disabled="!formTrackingNumber.shipping_id || !(formTrackingNumber.shipping_id?.api_shipper ? true : formTrackingNumber.tracking_number)
                                " full
                            @click="() => onSubmitAddressThenShipment()" />
                    </div>
                </div>
            </div>

            <!-- Loading: fetching service list -->
            <div v-if="isLoadingData === 'addTrackingNumber'"
                class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin text-5xl" fixed-width
                    aria-hidden="true" />
            </div>
        </div>
    </Modal>

    <ConfirmDialog :group="'confirm-delete'">
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationCircle" class="text-yellow-500" />
        </template>
    </ConfirmDialog>
</template>