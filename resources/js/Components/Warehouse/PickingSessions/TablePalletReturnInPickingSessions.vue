<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import { Link, router, useForm } from "@inertiajs/vue3"
import Tag from "@/Components/Tag.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Icon from "@/Components/Icon.vue"
import Modal from "@/Components/Utils/Modal.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faStickyNote, faUndo, faCheck, faDebug, faSave, faArrowAltLeft } from "@fal"
import { ref, reactive, computed, watch } from "vue"
import Popover from "@/Components/Popover.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import InputNumber from "primevue/inputnumber"
import Fieldset from "primevue/fieldset"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import type { routeType } from "@/types/route"
import "@/Composables/Icon/PalletStateEnum"

library.add(faStickyNote, faUndo, faCheck, faDebug, faSave, faArrowAltLeft)

const props = defineProps<{
    data: TableTS
    tab?: string
    pickingSession: any
    dispatchableReturns?: any[]
}>()

const isPickingLoading = ref<number | boolean>(false)
const isSubmitNotPickedLoading = ref<number | boolean>(false)
const isModalSetAsPicked = ref(false)
const isLoadingSetAsPicked = ref(false)
const selectedDispatchableReturn = ref<any | null>(null)
const isModalPickingUsers = ref(false)
const selectedPicker = ref<{ id: number; contact_name?: string | null } | null>(null)
const isModalShipment = ref(false)
const isModalParcels = ref(false)
const optionShippingList = ref<any[]>([])
const isLoadingData = ref<string | boolean>(false)
const isLoadingButton = ref<string | boolean>(false)
const isDeleteShipment = ref<number | null>(null)
const isLoadingSubmitParcels = ref(false)
const parcelsCopy = ref<any[]>([])
const formTrackingNumber = useForm<{ shipping_id: any; tracking_number: string }>({
    shipping_id: "",
    tracking_number: "",
})

const listStatusNotPicked = [
    { label: trans("Damaged"), value: "damaged" },
    { label: trans("Lost"), value: "lost" },
    { label: trans("Other incident"), value: "other_incident" },
]

const selectedStatusNotPicked = reactive({
    status: "other_incident",
    notes: "",
})

const errorNotPicked = reactive<{
    status: string | null
    notes: string | null
}>({
    status: null,
    notes: null,
})

const isPickingFinished = () => props.pickingSession?.state === "picking_finished"
const isPackingFinished = () => props.pickingSession?.state === "packing_finished"
const buildUrl = (name: any, parameters?: any) => String(route(name, parameters))

const getDispatchableReturn = (item: any) => {
    if (!props.dispatchableReturns?.length) {
        return null
    }

    const returnId = item?.pallet_return_id
    if (returnId) {
        return props.dispatchableReturns.find((r) => r.id === returnId) ?? null
    }

    return props.dispatchableReturns.find((r) => r.reference === item?.pallet_return_reference) ?? null
}

watch(
    () => props.dispatchableReturns,
    (dispatchableReturns) => {
        const selectedId = selectedDispatchableReturn.value?.id
        if (!selectedId || !dispatchableReturns?.length) {
            return
        }

        const latestDispatchableReturn = dispatchableReturns.find((dispatchableReturn: any) => dispatchableReturn.id === selectedId)
        if (!latestDispatchableReturn) {
            return
        }

        selectedDispatchableReturn.value = { ...latestDispatchableReturn }
    },
    { deep: true }
)

const canShowModalPrimaryButton = computed(() => selectedDispatchableReturn.value?.state !== "dispatched")
const isPickedState = computed(() => selectedDispatchableReturn.value?.state === "picked")
const modalPrimaryLabel = computed(() => {
    if (selectedDispatchableReturn.value?.state === "picking") {
        return trans("Set as Picked")
    }

    return selectedDispatchableReturn.value?.isCollection ? trans("Set as Collected") : trans("Dispatch")
})
const canChangePicker = computed(() => {
    return Boolean(
        selectedDispatchableReturn.value?.state === "picking" &&
        selectedDispatchableReturn.value?.pickerPackerRoutes?.pickers_list?.name &&
        selectedDispatchableReturn.value?.pickerPackerRoutes?.update?.name
    )
})
const canEditParcels = computed(() => selectedDispatchableReturn.value?.state !== "dispatched")
const shippingAddressLines = computed(() => {
    const address = selectedDispatchableReturn.value?.shippingAddress
    if (!address) {
        return []
    }

    return [
        address.address_line_1,
        address.address_line_2,
        address.locality,
        address.administrative_area,
        address.postal_code,
        address.country,
    ].filter((line) => Boolean(line))
})

const isFirstReturnRow = (item: any) => {
    const rawIndex = item?.rowIndex ?? item?.data?.rowIndex
    const index = typeof rawIndex === "string" ? Number.parseInt(rawIndex, 10) : rawIndex
    const data = (props.data as any)?.data

    if (typeof index !== "number" || !Number.isFinite(index) || index < 0 || !Array.isArray(data)) {
        return true
    }

    if (index === 0) {
        return true
    }

    return data[index - 1]?.pallet_return_id !== item?.pallet_return_id
}

const canRowSetAsPicked = (item: any) => {
    const dispatchableReturn = getDispatchableReturn(item)
    return Boolean(dispatchableReturn?.state === "picking" && dispatchableReturn?.canSetAsPicked && dispatchableReturn?.pickAllRoute?.name)
}
const canRowDispatch = (item: any) => Boolean(getDispatchableReturn(item)?.canDispatch)
const canRowRevertToPicking = (item: any) => {
    const dispatchableReturn = getDispatchableReturn(item)
    return Boolean(dispatchableReturn?.revertToPickingRoute?.name && dispatchableReturn?.state === "picked")
}

const onOpenSetAsPickedModal = (item: any) => {
    const dispatchableReturn = getDispatchableReturn(item)
    if (!dispatchableReturn) {
        return
    }

    selectedDispatchableReturn.value = dispatchableReturn
    selectedPicker.value = dispatchableReturn.picker ?? null
    parcelsCopy.value = [...(dispatchableReturn.parcels || [])]
    isModalSetAsPicked.value = true
}

const onCloseSetAsPickedModal = () => {
    isModalSetAsPicked.value = false
    isModalPickingUsers.value = false
    selectedDispatchableReturn.value = null
    selectedPicker.value = null
}

const onSetAsPicked = () => {
    const dispatchableReturn = selectedDispatchableReturn.value
    const pickAllRoute = dispatchableReturn?.pickAllRoute
    if (!pickAllRoute?.name) {
        return
    }

    isLoadingSetAsPicked.value = true
    router.visit(route(pickAllRoute.name, pickAllRoute.parameters), {
        method: (pickAllRoute.method || "post") as any,
        preserveScroll: true,
        onFinish: () => {
            isLoadingSetAsPicked.value = false
        },
        onSuccess: () => {
            onCloseSetAsPickedModal()
        },
    })
}

const onDispatchPalletReturn = async () => {
    const dispatchRoute = selectedDispatchableReturn.value?.dispatchRoute
    if (!dispatchRoute?.name) {
        return
    }

    isLoadingSetAsPicked.value = true
    try {
        await axios.request({
            method: dispatchRoute.method || "post",
            url: buildUrl(dispatchRoute.name, dispatchRoute.parameters),
        })
        notify({
            title: trans("Success"),
            text: selectedDispatchableReturn.value?.isCollection ? trans("Pallet return set as collected") : trans("Pallet return dispatched"),
            type: "success",
        })
        onCloseSetAsPickedModal()
        router.reload()
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Failed to dispatch pallet return"),
            type: "error",
        })
    } finally {
        isLoadingSetAsPicked.value = false
    }
}

const onOpenModalPickingUsers = () => {
    selectedPicker.value = selectedDispatchableReturn.value?.picker ?? null
    isModalPickingUsers.value = true
}

const onUpdatePickingUsers = async () => {
    const dispatchableReturn = selectedDispatchableReturn.value
    if (!dispatchableReturn?.pickerPackerRoutes?.update?.name) {
        return
    }

    if (!selectedPicker.value?.id) {
        notify({
            title: trans("Something went wrong"),
            text: trans("Please select picker first"),
            type: "error",
        })
        return
    }

    isLoadingSetAsPicked.value = true
    try {
        await axios.patch(
            buildUrl(dispatchableReturn.pickerPackerRoutes.update.name, dispatchableReturn.pickerPackerRoutes.update.parameters),
            { picker_user_id: selectedPicker.value?.id ?? null }
        )

        selectedDispatchableReturn.value = {
            ...dispatchableReturn,
            picker: selectedPicker.value,
        }
        isModalPickingUsers.value = false
        notify({
            title: trans("Success"),
            text: trans("Picker updated successfully"),
            type: "success",
        })
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Failed to update picker"),
            type: "error",
        })
    } finally {
        isLoadingSetAsPicked.value = false
    }
}

const onOpenModalTrackingNumber = async () => {
    const fetchRoute = selectedDispatchableReturn.value?.shipmentsRoutes?.fetch_route
    if (!fetchRoute?.name) {
        return
    }

    isLoadingData.value = "addTrackingNumber"
    try {
        const response = await axios.get(buildUrl(fetchRoute.name, fetchRoute.parameters))
        optionShippingList.value = response?.data?.data || []
        isModalShipment.value = true
    } catch (error) {
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to retrieve shipper list"),
            type: "error",
        })
    } finally {
        isLoadingData.value = false
    }
}

const onSubmitShipment = () => {
    const submitRoute = selectedDispatchableReturn.value?.shipmentsRoutes?.submit_route
    if (!submitRoute?.name) {
        return
    }

    formTrackingNumber
        .transform((data) => ({
            shipper_id: data.shipping_id?.id,
            tracking: data.shipping_id?.api_shipper ? undefined : data.tracking_number,
        }))
        .post(buildUrl(submitRoute.name, submitRoute.parameters), {
            preserveScroll: true,
            onStart: () => {
                isLoadingButton.value = "addTrackingNumber"
            },
            onSuccess: () => {
                isModalShipment.value = false
                formTrackingNumber.reset()
                router.reload()
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong."),
                    text: trans("Failed to add Shipment. Please try again."),
                    type: "error",
                })
            },
            onFinish: () => {
                isLoadingButton.value = false
            },
        })
}

const onDeleteShipment = async (shipmentId: number) => {
    const deleteRoute = selectedDispatchableReturn.value?.shipmentsRoutes?.delete_route
    if (!deleteRoute?.name) {
        return
    }

    isDeleteShipment.value = shipmentId
    try {
        await axios.delete(
            buildUrl(deleteRoute.name, {
                ...deleteRoute.parameters,
                shipment: shipmentId,
            })
        )
        notify({
            title: trans("Success!"),
            text: trans("Shipment has deleted."),
            type: "success",
        })
        router.reload()
    } catch (error) {
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to delete shipment."),
            type: "error",
        })
    } finally {
        isDeleteShipment.value = null
    }
}

const onDeleteParcel = (index: number) => {
    parcelsCopy.value.splice(index, 1)
}

const onSubmitParcels = () => {
    const updateRoute = selectedDispatchableReturn.value?.updateRoute
    if (!updateRoute?.name) {
        return
    }

    router.patch(
        buildUrl(updateRoute.name, updateRoute.parameters),
        { parcels: parcelsCopy.value },
        {
            preserveScroll: true,
            onStart: () => {
                isLoadingSubmitParcels.value = true
            },
            onSuccess: () => {
                isModalParcels.value = false
                selectedDispatchableReturn.value = {
                    ...selectedDispatchableReturn.value,
                    parcels: [...parcelsCopy.value],
                }
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong."),
                    text: trans("Failed to update parcels."),
                    type: "error",
                })
            },
            onFinish: () => {
                isLoadingSubmitParcels.value = false
            },
        }
    )
}

const onSubmitNotPicked = async (id: number, closePopup: () => void, routeNotPicked: routeType) => {
    isSubmitNotPickedLoading.value = id
    errorNotPicked.status = null
    errorNotPicked.notes = null

    router[routeNotPicked.method || "get"](
        route(routeNotPicked.name, routeNotPicked.parameters),
        {
            state: selectedStatusNotPicked.status,
            notes: selectedStatusNotPicked.notes,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedStatusNotPicked.status = "other_incident"
                selectedStatusNotPicked.notes = ""
                closePopup()
            },
            onError: (errors: any) => {
                errorNotPicked.status = errors?.status ?? null
                errorNotPicked.notes = errors?.notes ?? null
            },
            onFinish: () => {
                isSubmitNotPickedLoading.value = false
            },
        }
    )
}

const returnRoute = (item: any) => {
    if (!item?.pallet_return_slug) {
        return null
    }

    if (item?.pallet_return_type === 'stored_item') {
        return route('grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show', [
            (route().params as any).organisation,
            (route().params as any).warehouse,
            item.pallet_return_slug
        ])
    }

    return route('grp.org.warehouses.show.dispatching.pallet-returns.show', [
        (route().params as any).organisation,
        (route().params as any).warehouse,
        item.pallet_return_slug
    ])
}

const palletRoute = (item: any) => {
    const params: any = route().params as any

    if (!item?.slug || !item?.fulfilment_slug || !item?.fulfilment_customer_slug || !params.organisation) {
        return null
    }

    return route('grp.org.fulfilments.show.crm.customers.show.pallets.show', [
        params.organisation,
        item.fulfilment_slug,
        item.fulfilment_customer_slug,
        item.slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab ?? ''" class="mt-5">
        <template v-if="tab === 'grouped'" #cell(pallet_return_reference)="{ item }">
            <div class="flex items-center gap-x-2">
                <Icon v-if="item?.state_icon" :data="item['state_icon']" class="px-1 shrink-0" />
                <div>
                    <Link v-if="returnRoute(item)" :href="returnRoute(item)" class="primaryLink">
                        {{ item.pallet_return_reference }}
                    </Link>
                    <div v-else>
                        {{ "-" }}
                    </div>
                </div>
            </div>
        </template>

        <template v-if="tab === 'grouped'" #cell(pallets)="{ item }">
            <div class="flex flex-col gap-y-3">
                <div
                    v-for="pallet in item.pallets"
                    :key="pallet.id"
                    class="border-b last:border-b-0 py-2"
                >
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                        <div class="min-w-[140px]">
                            <Link v-if="palletRoute(pallet)" :href="palletRoute(pallet)" class="primaryLink">
                                {{ pallet.reference }}
                            </Link>
                            <div v-else>
                                {{ pallet.reference || "-" }}
                            </div>
                        </div>

                        <div class="min-w-[160px]">
                            <div>
                                {{ pallet.customer_reference || "-" }}
                                <div v-if="pallet.notes" class="text-gray-400">
                                    <FontAwesomeIcon icon="fal fa-sticky-note" fixed-width aria-hidden="true" />
                                    <span>{{ pallet.notes }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex-1">
                            <div v-if="pallet.stored_items?.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
                                <Tag
                                    v-for="storedItem of pallet.stored_items"
                                    :key="`${storedItem.reference}-${storedItem.quantity}`"
                                    :label="`${storedItem.reference} (${storedItem.quantity})`"
                                    :closeButton="false"
                                    :stringToColor="true"
                                >
                                    <template #label>
                                        <div class="whitespace-nowrap text-xs">
                                            {{ storedItem.reference }} (<span class="font-light">{{ storedItem.quantity }}</span>)
                                        </div>
                                    </template>
                                </Tag>
                            </div>
                            <div v-else class="text-gray-400 text-xs italic">
                                {{ trans("No SKUs items") }}
                            </div>
                        </div>

                        <div class="flex items-center gap-x-3 ml-auto">
                            <div>
                                <Tag v-if="pallet.location_code" :label="pallet.location_code" />
                                <div v-else class="text-gray-400 text-xs">-</div>
                            </div>

                            <div class="flex gap-x-2">
                                <template v-if="pallet.updateRoute?.name && (pallet.state === 'picking' || pallet.pivot_state === 'picking')">
                                    <Link
                                        as="div"
                                        :href="route(pallet.updateRoute.name, pallet.updateRoute.parameters)"
                                        method="patch"
                                        preserveScroll
                                        @start="() => (isPickingLoading = pallet.id)"
                                        @finish="() => (isPickingLoading = false)"
                                        v-tooltip="trans('Set as picked')"
                                    >
                                        <Button icon="fal fa-clipboard-list-check" type="secondary" size="sm" :loading="isPickingLoading === pallet.id" class="py-0" />
                                    </Link>

                                    <Popover v-if="pallet.notPickedRoute?.name">
                                        <template #button="{ open }">
                                            <Button
                                                icon="fal fa-debug"
                                                :type="'negative'"
                                                size="sm"
                                                :key="pallet.id + open"
                                                :loading="isSubmitNotPickedLoading === pallet.id"
                                                v-tooltip="trans('Set as not picked')"
                                            />
                                        </template>
                                        <template #content="{ close }">
                                            <div class="w-[250px]">
                                                <div class="mb-3">
                                                    <div class="text-xs px-1 mb-1">
                                                        <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans("Select status:") }}
                                                    </div>
                                                    <PureMultiselect
                                                        v-model="selectedStatusNotPicked.status"
                                                        @update:modelValue="() => (errorNotPicked.status = null)"
                                                        :options="listStatusNotPicked"
                                                        required
                                                        caret
                                                        :class="errorNotPicked.status ? 'errorShake' : ''"
                                                    />
                                                    <div v-if="errorNotPicked.status" class="mt-1 text-red-500 italic text-xxs">
                                                        {{ errorNotPicked.status }}
                                                    </div>
                                                </div>

                                                <div class="mb-4">
                                                    <div class="text-xs px-1 mb-1">
                                                        <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans("Description:") }}
                                                    </div>
                                                    <PureTextarea
                                                        v-model="selectedStatusNotPicked.notes"
                                                        @update:modelValue="() => (errorNotPicked.notes = null)"
                                                        :placeholder="trans('Enter reason why the pallet is not picked')"
                                                        :class="errorNotPicked.notes ? 'errorShake' : ''"
                                                    />
                                                    <div v-if="errorNotPicked.notes" class="mt-1 text-red-500 italic text-xxs">
                                                        {{ errorNotPicked.notes }}
                                                    </div>
                                                </div>

                                                <div class="flex justify-end mt-2">
                                                    <Button
                                                        @click="async () => onSubmitNotPicked(pallet.id, close, pallet.notPickedRoute)"
                                                        full
                                                        :label="trans('Submit')"
                                                        :disabled="!selectedStatusNotPicked.status || !selectedStatusNotPicked.notes"
                                                        :loading="isSubmitNotPickedLoading === pallet.id"
                                                    />
                                                </div>
                                            </div>
                                        </template>
                                    </Popover>

                                </template>

                                <Link
                                    v-else-if="isPickingFinished() && pallet.undoPickingRoute?.name && (pallet.state === 'picked' || pallet.pivot_state === 'picked')"
                                    as="div"
                                    :href="route(pallet.undoPickingRoute.name, pallet.undoPickingRoute.parameters)"
                                    method="patch"
                                    preserveScroll
                                    @start="() => (isPickingLoading = pallet.id)"
                                    @finish="() => (isPickingLoading = false)"
                                    v-tooltip="trans('Undo picking')"
                                >
                                    <Button icon="fal fa-undo" label="Undo picking" type="tertiary" size="xs" :loading="isPickingLoading === pallet.id" class="py-0" />
                                </Link>

                                <div v-else-if="pallet.status === 'incident' && pallet.state === 'lost'" class="text-red-300 italic">
                                    {{ trans("Pallet lost") }}
                                </div>
                                <div v-else-if="pallet.status === 'incident' && pallet.state === 'damaged'" class="text-red-300 italic">
                                    {{ trans("Pallet damaged") }}
                                </div>
                                <div v-else-if="pallet.pivot_state === 'cancel'" class="text-red-300 italic">
                                    {{ trans("Pallet set back to storing") }}
                                </div>
                                <div v-else class="text-gray-400"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template v-if="tab === 'grouped'" #cell(actions)="{ item }">
            <div v-if="(isPickingFinished() || isPackingFinished()) && isFirstReturnRow(item)" class="flex justify-end gap-x-2">
                <template v-if="getDispatchableReturn(item)?.state === 'picking'">
                    <Button
                        v-if="canRowSetAsPicked(item)"
                        icon="fal fa-save"
                        :label="trans('Set as Picked')"
                        type="secondary"
                        size="xs"
                        class="py-0"
                        @click="onOpenSetAsPickedModal(item)"
                    />
                    <Button
                        v-else
                        icon="fal fa-save"
                        :label="trans('Set as Picked')"
                        type="secondary"
                        size="xs"
                        class="py-0"
                        :disabled="true"
                        v-tooltip="trans('Set all items as picked or not picked first')"
                    />
                </template>
                <Button
                    v-if="canRowDispatch(item)"
                    icon="fal fa-pencil"
                    :label="trans('Edit Detail')"
                    type="secondary"
                    size="xs"
                    class="py-0"
                    @click="onOpenSetAsPickedModal(item)"
                />
                <Link
                    v-if="canRowRevertToPicking(item)"
                    as="div"
                    :href="route(getDispatchableReturn(item).revertToPickingRoute.name, getDispatchableReturn(item).revertToPickingRoute.parameters)"
                    :method="getDispatchableReturn(item).revertToPickingRoute.method || 'post'"
                    preserveScroll
                    v-tooltip="trans('Revert to Picking')"
                >
                    <Button
                        icon="fal fa-arrow-alt-left"
                        :label="trans('Revert to Picking')"
                        type="negative"
                        size="xs"
                        class="py-0"
                    />
                </Link>
            </div>
        </template>

        <template v-else #cell(pallet_return_reference)="{ item }">
            <div class="flex items-center gap-x-2">
                <Icon v-if="item?.state_icon" :data="item['state_icon']" class="px-1 shrink-0" />
                <div>
                    <Link v-if="returnRoute(item)" :href="returnRoute(item)" class="primaryLink">
                        {{ item.pallet_return_reference }}
                    </Link>
                    <div v-else>
                        {{ item.pallet_return_reference || "-" }}
                    </div>
                </div>
            </div>
        </template>

        <template #cell(reference)="{ item }">
            <Link v-if="palletRoute(item)" :href="palletRoute(item)" class="primaryLink">
                {{ item.reference }}
            </Link>
            <div v-else>
                {{ item.reference || "-" }}
            </div>
        </template>

        <template #cell(customer_reference)="{ item }">
            <div>
                {{ item.customer_reference || "-" }}
                <div v-if="item.notes" class="text-gray-400">
                    <FontAwesomeIcon icon="fal fa-sticky-note" fixed-width aria-hidden="true" />
                    <span>{{ item.notes }}</span>
                </div>
            </div>
        </template>

        <template #cell(stored_items)="{ item }">
            <div v-if="item.stored_items?.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
                <Tag
                    v-for="storedItem of item.stored_items"
                    :key="`${storedItem.reference}-${storedItem.quantity}`"
                    :label="`${storedItem.reference} (${storedItem.quantity})`"
                    :closeButton="false"
                    :stringToColor="true"
                >
                    <template #label>
                        <div class="whitespace-nowrap text-xs">
                            {{ storedItem.reference }} (<span class="font-light">{{ storedItem.quantity }}</span>)
                        </div>
                    </template>
                </Tag>
            </div>
            <div v-else class="text-gray-400 text-xs italic">
                No items
            </div>
        </template>

        <template #cell(location)="{ item }">
            <Tag v-if="item.location_code" :label="item.location_code" />
            <div v-else class="text-gray-400">-</div>
        </template>

        <template #cell(actions)="{ item }">
            <div class="flex flex-col items-end gap-y-1">
                <div class="flex gap-x-2">
                    <template v-if="item.updateRoute?.name && (item.state === 'picking' || item.pivot_state === 'picking')">
                        <Link
                            as="div"
                            :href="route(item.updateRoute.name, item.updateRoute.parameters)"
                            method="patch"
                            preserveScroll
                            @start="() => (isPickingLoading = item.id)"
                            @finish="() => (isPickingLoading = false)"
                            v-tooltip="trans('Set as picked')"
                        >
                            <Button icon="fal fa-clipboard-list-check" type="secondary" size="sm" :loading="isPickingLoading === item.id" class="py-0" />
                        </Link>

                        <Popover v-if="item.notPickedRoute?.name">
                            <template #button="{ open }">
                                <Button
                                    icon="fal fa-debug"
                                    :type="'negative'"
                                    size="sm"
                                    :key="item.id + open"
                                    :loading="isSubmitNotPickedLoading === item.id"
                                    v-tooltip="trans('Set as not picked')"
                                />
                            </template>
                            <template #content="{ close }">
                                <div class="w-[250px]">
                                    <div class="mb-3">
                                        <div class="text-xs px-1 mb-1">
                                            <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans("Select status:") }}
                                        </div>
                                        <PureMultiselect
                                            v-model="selectedStatusNotPicked.status"
                                            @update:modelValue="() => (errorNotPicked.status = null)"
                                            :options="listStatusNotPicked"
                                            required
                                            caret
                                            :class="errorNotPicked.status ? 'errorShake' : ''"
                                        />
                                        <div v-if="errorNotPicked.status" class="mt-1 text-red-500 italic text-xxs">
                                            {{ errorNotPicked.status }}
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <div class="text-xs px-1 mb-1">
                                            <span class="text-red-500 text-sm mr-0.5">*</span>{{ trans("Description:") }}
                                        </div>
                                        <PureTextarea
                                            v-model="selectedStatusNotPicked.notes"
                                            @update:modelValue="() => (errorNotPicked.notes = null)"
                                            :placeholder="trans('Enter reason why the pallet is not picked')"
                                            :class="errorNotPicked.notes ? 'errorShake' : ''"
                                        />
                                        <div v-if="errorNotPicked.notes" class="mt-1 text-red-500 italic text-xxs">
                                            {{ errorNotPicked.notes }}
                                        </div>
                                    </div>

                                    <div class="flex justify-end mt-2">
                                        <Button
                                            @click="async () => onSubmitNotPicked(item.id, close, item.notPickedRoute)"
                                            full
                                            :label="trans('Submit')"
                                            :disabled="!selectedStatusNotPicked.status || !selectedStatusNotPicked.notes"
                                            :loading="isSubmitNotPickedLoading === item.id"
                                        />
                                    </div>
                                </div>
                            </template>
                        </Popover>

                    </template>

                    <Link
                        v-else-if="isPickingFinished() && item.undoPickingRoute?.name && (item.state === 'picked' || item.pivot_state === 'picked')"
                        as="div"
                        :href="route(item.undoPickingRoute.name, item.undoPickingRoute.parameters)"
                        method="patch"
                        preserveScroll
                        @start="() => (isPickingLoading = item.id)"
                        @finish="() => (isPickingLoading = false)"
                        v-tooltip="trans('Undo picking')"
                    >
                        <Button icon="fal fa-undo" label="Undo picking" type="tertiary" size="xs" :loading="isPickingLoading === item.id" class="py-0" />
                    </Link>

                    <div v-else-if="item.status === 'incident' && item.state === 'lost'" class="text-red-300 italic">
                        {{ trans("Pallet lost") }}
                    </div>
                    <div v-else-if="item.status === 'incident' && item.state === 'damaged'" class="text-red-300 italic">
                        {{ trans("Pallet damaged") }}
                    </div>
                    <div v-else-if="item.pivot_state === 'cancel'" class="text-red-300 italic">
                        {{ trans("Pallet set back to storing") }}
                    </div>
                    <div v-else class="text-gray-400">-</div>
                </div>

                <div v-if="isFirstReturnRow(item) && (isPickingFinished() || isPackingFinished())" class="flex justify-end gap-x-2">
                    <template v-if="getDispatchableReturn(item)?.state === 'picking'">
                        <Button
                            v-if="canRowSetAsPicked(item)"
                            icon="fal fa-save"
                            :label="trans('Set as Picked')"
                            type="secondary"
                            size="xs"
                            class="py-0"
                            @click="onOpenSetAsPickedModal(item)"
                        />
                        <Button
                            v-else
                            icon="fal fa-save"
                            :label="trans('Set as Picked')"
                            type="secondary"
                            size="xs"
                            class="py-0"
                            :disabled="true"
                            v-tooltip="trans('Set all items as picked or not picked first')"
                        />
                    </template>
                    <Button
                        v-if="canRowDispatch(item)"
                        icon="fal fa-pencil"
                        :label="trans('Edit Detail')"
                        type="secondary"
                        size="xs"
                        class="py-0"
                        @click="onOpenSetAsPickedModal(item)"
                    />
                    <Link
                        v-if="canRowRevertToPicking(item)"
                        as="div"
                        :href="route(getDispatchableReturn(item).revertToPickingRoute.name, getDispatchableReturn(item).revertToPickingRoute.parameters)"
                        :method="getDispatchableReturn(item).revertToPickingRoute.method || 'post'"
                        preserveScroll
                        v-tooltip="trans('Revert to Picking')"
                    >
                        <Button
                            icon="fal fa-arrow-alt-left"
                            :label="trans('Revert to Picking')"
                            type="negative"
                            size="xs"
                            class="py-0"
                        />
                    </Link>
                </div>
            </div>
        </template>
    </Table>

    <Modal :isOpen="isModalSetAsPicked" @onClose="onCloseSetAsPickedModal" width="w-full max-w-3xl" :isClosableInBackground="false" closeButton>
        <div class="flex items-center justify-between border-b border-gray-200 pb-3">
            <div class="text-xl font-semibold">
                {{ trans("Pallet Return") }} {{ selectedDispatchableReturn?.reference || "-" }}
            </div>
            <Button
                v-if="canShowModalPrimaryButton"
                icon="fal fa-save"
                :label="modalPrimaryLabel"
                type="save"
                size="sm"
                :loading="isLoadingSetAsPicked"
                @click="selectedDispatchableReturn?.state === 'picking' ? onSetAsPicked() : onDispatchPalletReturn()"
            />
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div v-if="!selectedDispatchableReturn?.isCollection" class="text-base font-semibold text-gray-800 mb-3">{{ trans("Shipping") }}</div>

                <div v-if="selectedDispatchableReturn?.isCollection" class="border-l-4 border-indigo-300 bg-indigo-50 px-2 py-0.5">
                    {{ trans("For Collection: Yes") }}
                </div>
                <div v-else class="space-y-1 text-sm text-gray-700">
                    <div v-if="shippingAddressLines.length">
                        <div v-for="line in shippingAddressLines" :key="line">{{ line }}</div>
                    </div>
                    <div v-else class="text-gray-500 italic">
                        {{ trans("No shipping address") }}
                    </div>
                </div>

                <div class="mt-4 border-t border-gray-200 pt-3 space-y-1 text-sm">
                    <div v-if="selectedDispatchableReturn?.customer?.name" class="flex justify-between">
                        <div class="text-gray-500">{{ trans("Customer") }}</div>
                        <div class="font-medium text-gray-800">{{ selectedDispatchableReturn.customer.name }}</div>
                    </div>
                    <div v-if="selectedDispatchableReturn?.platform?.name" class="flex justify-between">
                        <div class="text-gray-500">{{ trans("Platform") }}</div>
                        <div class="font-medium text-gray-800">{{ selectedDispatchableReturn.platform.name }}</div>
                    </div>
                    <div v-if="selectedDispatchableReturn?.salesChannel?.name" class="flex justify-between">
                        <div class="text-gray-500">{{ trans("Sales Channel") }}</div>
                        <div class="font-medium text-gray-800">{{ selectedDispatchableReturn.salesChannel.name }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-x-2 border-l-4 border-indigo-300 bg-indigo-50 px-2 py-0.5">
                        <div class="text-gray-500">{{ trans("Picker") }}:</div>
                        <div class="font-medium text-gray-800">
                            {{ selectedDispatchableReturn?.picker?.contact_name || "-" }}
                        </div>
                    </div>

                    <Button
                        v-if="canChangePicker"
                        @click="onOpenModalPickingUsers"
                        icon="fal fa-exchange"
                        type="tertiary"
                        size="xs"
                        :label="trans('Change picker')" />

                    <div class="flex items-center gap-x-2">
                        <Icon v-if="selectedDispatchableReturn?.stateIcon" :data="selectedDispatchableReturn.stateIcon" />
                        <div class="text-gray-800">{{ selectedDispatchableReturn?.stateLabel || selectedDispatchableReturn?.state || "-" }}</div>
                    </div>

                    <div class="flex items-center gap-x-2">
                        <FontAwesomeIcon icon="fal fa-hand-holding-box" class="mr text-gray-500" fixed-width aria-hidden="true" />
                        <div class="font-medium text-gray-800">{{ selectedDispatchableReturn?.itemsCount ?? 0 }}</div>
                        <div class="text-gray-500">{{ trans("Items") }}</div>
                    </div>

                    <div class="flex items-center gap-x-2">
                        <FontAwesomeIcon v-tooltip="trans('Parcels')" icon="fas fa-cubes" class="text-gray-400" fixed-width aria-hidden="true" />
                        <div class="font-medium">{{ trans("Parcels") }} ({{ selectedDispatchableReturn?.parcels?.length ?? 0 }})</div>
                    </div>
                    <div class="mt-2">
                        <ul v-if="selectedDispatchableReturn?.parcels?.length" class="list-disc pl-4 mt-2">
                            <li v-for="(parcel, parcelIdx) in selectedDispatchableReturn?.parcels" :key="parcelIdx" class="text-sm tabular-nums">
                                <span>{{ parcel.weight }} kg</span>
                                <span class="text-gray-500"> ({{ parcel.dimensions?.[0] }}x{{ parcel.dimensions?.[1] }}x{{ parcel.dimensions?.[2] }} cm)</span>
                            </li>
                        </ul>
                        <Button v-if="canEditParcels && selectedDispatchableReturn?.parcels?.length"
                            @click="() => (isModalParcels = true, parcelsCopy = [...(selectedDispatchableReturn?.parcels || [])])"
                            :label="trans('Edit')" icon="fal fa-pencil" type="tertiary" size="xs" />
                        <Button v-else-if="canEditParcels"
                            @click="() => (parcelsCopy = [{ weight: 1, dimensions: [5, 5, 5] }], onSubmitParcels())"
                            :label="trans('Add')" icon="fas fa-plus" type="tertiary" size="xs" />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="!selectedDispatchableReturn?.isCollection && isPickedState" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-x-2">
                        <FontAwesomeIcon v-tooltip="trans('Shipments')" icon="fal fa-shipping-fast" class="text-gray-400" fixed-width aria-hidden="true" />
                        <div class="font-medium">{{ trans("Shipments") }} ({{ selectedDispatchableReturn?.shipments?.length ?? 0 }})</div>
                    </div>
                    <Button
                        v-if="canEditParcels && (selectedDispatchableReturn?.shipments?.length ?? 0) < 1"
                        @click="() => selectedDispatchableReturn?.parcels?.length ? onOpenModalTrackingNumber() : notify({ title: trans('Something went wrong'), text: trans('Please add at least one parcel'), type: 'error' })"
                        :label="trans('Shipment')"
                        icon="fal fa-shipping-fast"
                        type="tertiary"
                        size="xs"
                    />
                </div>

                <ul v-if="selectedDispatchableReturn?.shipments?.length" class="list-disc pl-4 mt-2">
                    <li v-for="(shipment, shipmentIdx) in selectedDispatchableReturn.shipments" :key="shipmentIdx" class="hover:bg-gray-100 text-sm tabular-nums relative">
                        <div class="flex justify-between items-center gap-2">
                            <div>
                                <span>{{ shipment.name }}</span>
                                <span v-if="shipment.tracking" class="text-gray-400"> ({{ shipment.tracking }})</span>
                            </div>
                            <div v-if="isDeleteShipment === shipment.id" class="px-1">
                                <LoadingIcon />
                            </div>
                            <div v-else-if="canEditParcels" @click="() => onDeleteShipment(shipment.id)" v-tooltip="trans('Remove shipment')" class="cursor-pointer px-1">
                                <FontAwesomeIcon icon="fal fa-times" class="text-red-400 hover:text-red-600" fixed-width aria-hidden="true" />
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </Modal>

    <Modal :isOpen="isModalPickingUsers" @onClose="() => (isModalPickingUsers = false)" width="w-full max-w-xl" :isClosableInBackground="false" closeButton>
        <div class="flex flex-col gap-4">
            <div class="text-center text-lg font-semibold">{{ trans("Return Customer's SKUs") }}</div>
            <div v-if="canChangePicker" class="flex flex-col gap-2">
                <div class="text-sm font-medium">{{ trans("Picker") }}</div>
                <PureMultiselectInfiniteScroll
                    v-if="selectedDispatchableReturn?.pickerPackerRoutes?.pickers_list"
                    v-model="selectedPicker"
                    :fetchRoute="selectedDispatchableReturn?.pickerPackerRoutes?.pickers_list"
                    :placeholder="trans('Select picker')"
                    labelProp="contact_name"
                    valueProp="id"
                    object
                    clearOnBlur
                    :disabled="isLoadingSetAsPicked">
                    <template #singlelabel="{ value }">
                        <div class="w-full text-left pl-3 pr-2 text-sm whitespace-nowrap truncate">
                            {{ value.contact_name }}
                        </div>
                    </template>
                    <template #option="{ option }">
                        <div class="w-full text-left text-sm whitespace-nowrap truncate">
                            {{ option.contact_name }}
                        </div>
                    </template>
                </PureMultiselectInfiniteScroll>
                <div v-else class="text-xs text-gray-500">{{ trans('User list route is not available') }}</div>
            </div>
            <Button
                @click="onUpdatePickingUsers"
                :label="trans('Save')"
                type="save"
                full
                :loading="isLoadingSetAsPicked" />
        </div>
    </Modal>

    <Modal
        v-if="!selectedDispatchableReturn?.isCollection"
        :isOpen="isModalShipment"
        @onClose="isModalShipment = false"
        width="w-full max-w-2xl"
        closeButton>
        <div class="text-center font-bold mb-4">
            {{ trans('Add shipment') }}
        </div>

        <div class="w-full mt-3">
            <span class="text-xs px-1 my-2">{{ trans("Shipping options") }}: </span>

            <div class="grid grid-cols-3 gap-x-2 gap-y-2 mb-2">
                <div v-if="isLoadingData === 'addTrackingNumber'" v-for="sip in 3" :key="`shipment-skeleton-${sip}`" class="skeleton w-full max-w-52 h-20 rounded"></div>
                <div
                    v-else
                    v-for="shipment in optionShippingList.filter(shipment => shipment.api_shipper)"
                    :key="shipment.id"
                    @click="() => formTrackingNumber.shipping_id = shipment"
                    class="relative w-full max-w-52 h-20 border rounded-md px-5 py-3 cursor-pointer"
                    :class="[formTrackingNumber.shipping_id?.id == shipment.id ? 'bg-indigo-200 border-indigo-300' : 'hover:bg-gray-100 border-gray-300']"
                >
                    <div class="font-bold text-sm">{{ shipment.name }}</div>
                    <div class="text-xs text-gray-500 italic">{{ shipment.phone }}</div>
                </div>
            </div>

            <PureMultiselectInfiniteScroll
                v-model="formTrackingNumber.shipping_id"
                :fetchRoute="selectedDispatchableReturn?.shipmentsRoutes?.fetch_route"
                required
                :placeholder="trans('Select shipping')"
                object
                @optionsList="(e) => optionShippingList = e"
            >
                <template #singlelabel="{ value }">
                    <div class="w-full text-left pl-4">
                        {{ value.name }} <span class="text-sm text-gray-400">({{ value.code }})</span>
                    </div>
                </template>
                <template #option="{ option }">
                    <div>{{ option.name }} <span class="text-sm text-gray-400">({{ option.code }})</span></div>
                </template>
            </PureMultiselectInfiniteScroll>

            <div v-if="formTrackingNumber.shipping_id && !formTrackingNumber.shipping_id?.api_shipper" class="mt-3">
                <span class="text-xs px-1 my-2">{{ trans("Tracking number") }}: </span>
                <PureInput v-model="formTrackingNumber.tracking_number" placeholder="ABC-DE-1234567" />
            </div>

            <div class="flex justify-end mt-3">
                <Button
                    :style="'save'"
                    :loading="isLoadingButton == 'addTrackingNumber'"
                    :label="'save'"
                    :disabled="!formTrackingNumber.shipping_id || !(formTrackingNumber.shipping_id?.api_shipper ? true : formTrackingNumber.tracking_number)"
                    full
                    @click="() => onSubmitShipment()"
                />
            </div>
        </div>
    </Modal>

    <Modal :isOpen="isModalParcels" @onClose="isModalParcels = false" width="w-full max-w-lg" closeButton>
        <div class="text-center font-bold mb-4">{{ trans('Add Parcels') }}</div>
        <Fieldset :legend="`${trans('Parcels')} (${parcelsCopy?.length})`">
            <div class="grid grid-cols-12 items-center gap-x-6 mb-2">
                <div></div>
                <div class="col-span-2 flex items-center space-x-1">
                    <FontAwesomeIcon icon="fal fa-weight" fixed-width aria-hidden="true" />
                    <span>kg</span>
                </div>
                <div class="col-span-9 flex items-center space-x-1">
                    <FontAwesomeIcon icon="fal fa-ruler-triangle" fixed-width aria-hidden="true" />
                    <span>cm</span>
                </div>
            </div>
            <div class="grid gap-y-1 max-h-64 overflow-y-auto pr-2">
                <TransitionGroup v-if="parcelsCopy?.length" name="list">
                    <div v-for="(parcel, parcelIndex) in parcelsCopy" :key="parcelIndex" class="grid grid-cols-12 items-center gap-x-6">
                        <div @click="() => onDeleteParcel(parcelIndex)" class="flex justify-center">
                            <FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-400 hover:text-red-600 cursor-pointer" fixed-width aria-hidden="true" />
                        </div>
                        <div class="col-span-2"><InputNumber :min="0.001" v-model="parcel.weight" class="w-16" size="small" placeholder="0" fluid /></div>
                        <div class="col-span-9 flex items-center gap-x-1 font-light">
                            <InputNumber :min="0.001" v-model="parcel.dimensions[0]" class="w-16" size="small" placeholder="0" fluid />
                            <div class="text-gray-400">x</div>
                            <InputNumber :min="0.001" v-model="parcel.dimensions[1]" class="w-16" size="small" placeholder="0" fluid />
                            <div class="text-gray-400">x</div>
                            <InputNumber :min="0.001" v-model="parcel.dimensions[2]" class="w-16" size="small" placeholder="0" fluid />
                        </div>
                    </div>
                </TransitionGroup>
            </div>
            <div class="grid grid-cols-12 mt-2">
                <div></div>
                <div @click="() => parcelsCopy.push({ weight: 1, dimensions: [5, 5, 5]})" class="hover:bg-gray-200 cursor-pointer border border-dashed border-gray-400 col-span-11 text-center py-1.5 text-xs rounded">
                    <FontAwesomeIcon icon="fas fa-plus" class="text-gray-500" fixed-width aria-hidden="true" />
                    {{ trans("Add another parcel") }}
                </div>
            </div>
        </Fieldset>
        <div class="flex justify-end mt-3">
            <Button :style="'save'" :loading="isLoadingSubmitParcels" :label="'save'" full @click="() => onSubmitParcels()" />
        </div>
    </Modal>
</template>
