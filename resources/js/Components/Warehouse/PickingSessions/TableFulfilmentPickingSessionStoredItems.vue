<script setup lang="ts">
import { Link, router, useForm } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import NumberWithButtonSave from "@/Components/NumberWithButtonSave.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Icon from "@/Components/Icon.vue"
import { trans } from "laravel-vue-i18n"
import type { Table as TableTS } from "@/types/Table"
import { Collapse } from "vue-collapsed"
import { get, set } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowDown, faExchange, faArrowAltLeft } from "@fal"
import { ref, computed, watch } from "vue"
import Modal from "@/Components/Utils/Modal.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import PureInput from "@/Components/Pure/PureInput.vue"
import InputNumber from "primevue/inputnumber"
import Fieldset from "primevue/fieldset"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import "@/Composables/Icon/PalletReturnStateEnum"

library.add(faArrowDown, faExchange, faArrowAltLeft)

const props = defineProps<{
    data: TableTS
    tab?: string
    pickingSession: any
    dispatchableReturns?: any[]
}>()

const isLoadingUndoPick = ref<Record<number, boolean>>({})
const buildUrl = (name: any, parameters?: any) => String(route(name, parameters))

const onUndoPick = (undoRoute: any, palletStoredItem: any) => {
    const id = palletStoredItem?.id
    if (!id || !undoRoute?.name) {
        return
    }

    isLoadingUndoPick.value[id] = true

    router.patch(
        buildUrl(undoRoute.name, undoRoute.parameters),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isLoadingUndoPick.value[id] = false
            },
        }
    )
}

const returnRoute = (item: any) => {
    if (!item?.pallet_return_slug) {
        return "#"
    }

    return buildUrl('grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show', [
        (route().params as any).organisation,
        (route().params as any).warehouse,
        item.pallet_return_slug
    ])
}

const storedItemRoute = (item: any) => {
    if (!item?.slug) {
        return "#"
    }

    return buildUrl('grp.org.warehouses.show.inventory.stored_items.current.show', [
        (route().params as any).organisation,
        (route().params as any).warehouse,
        item.slug
    ])
}

const palletRoute = (palletStoredItem: any) => {
    if (!palletStoredItem?.pallet_slug || !palletStoredItem?.location?.slug) {
        return "#"
    }

    return buildUrl('grp.org.warehouses.show.fulfilment.locations.show.pallets.show', {
        organisation: (route().params as any).organisation,
        warehouse: (route().params as any).warehouse,
        location: palletStoredItem.location.slug,
        pallet: palletStoredItem.pallet_slug,
    })
}

const isRowPicking = (item: any) => item?.pallet_return_state === 'picking'
const canRowDispatch = (item: any) => !!getDispatchableReturn(item)?.canDispatch
const canRowRevertToPicking = (item: any) => {
    const dispatchableReturn = getDispatchableReturn(item)
    return Boolean(dispatchableReturn?.revertToPickingRoute?.name && dispatchableReturn?.state === "picked")
}
const canRowSetAsPicked = (item: any) => !!getDispatchableReturn(item)?.canSetAsPicked
const isPickingFinished = () => props.pickingSession?.state === 'picking_finished'
const isPackingFinished = () => props.pickingSession?.state === 'packing_finished'
const groupMode = computed<'by_item' | 'by_return'>(() => {
    if (props?.tab === "grouped") return "by_return"
    return "by_item"
})

const getDispatchableReturn = (item: any) => {
    if (!props.dispatchableReturns?.length) {
        return null
    }

    const returnId = item?.pallet_return_id
    if (returnId) {
        return props.dispatchableReturns.find(r => r.id === returnId) ?? null
    }

    return props.dispatchableReturns.find(r => r.reference === item?.pallet_return_reference) ?? null
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

const collapsedGroups = ref<Record<number, boolean>>({})
const isGroupCollapsed = (item: any) => {
    const id = item?.pallet_return_id ?? item?.data?.pallet_return_id
    return !!collapsedGroups.value[id]
}
const toggleGroup = (item: any) => {
    const id = item?.pallet_return_id ?? item?.data?.pallet_return_id
    if (!id) return
    collapsedGroups.value[id] = !collapsedGroups.value[id]
}

const isFirstReturnRow = (item: any) => {
    const rawIndex = item?.rowIndex ?? item?.data?.rowIndex
    const index = typeof rawIndex === 'string' ? Number.parseInt(rawIndex, 10) : rawIndex
    const data = (props.data as any)?.data
    const palletReturnId = item?.pallet_return_id ?? item?.data?.pallet_return_id

    if (typeof index !== 'number' || !Number.isFinite(index) || index < 0 || !Array.isArray(data)) {
        return true
    }

    if (index === 0) {
        return true
    }

    return data[index - 1]?.pallet_return_id !== palletReturnId
}

const getItemsByReturn = (palletReturnId: number | null | undefined) => {
    const data = (props.data as any)?.data || []
    if (!palletReturnId) return []

    return data.filter(
        (row: any) =>
            row?.pallet_return_id === palletReturnId &&
            (row?.total_quantity_ordered ?? 0) > 0
    )
}

const rowClass = (item: any) => {
    if (groupMode.value !== "by_return") {
        return ""
    }

    const data = (props.data as any)?.data || []
    const palletReturnId = item?.pallet_return_id ?? item?.data?.pallet_return_id
    const currentId = item?.id ?? item?.data?.id

    if (!palletReturnId) {
        return "hidden"
    }

    const first = data.find(
        (row: any) =>
            row?.pallet_return_id === palletReturnId &&
            (row?.total_quantity_ordered ?? 0) > 0
    )

    if (!first) {
        return "hidden"
    }

    return first.id === currentId ? "" : "hidden"
}

const getRequestedPallets = (storedItem: any) => {
    const items = storedItem?.pallet_stored_items || []
    return items.filter((ps: any) => (ps?.selected_quantity ?? 0) > 0 || (ps?.available_to_pick_quantity ?? 0) > 0)
}
const getHiddenPallets = (storedItem: any) => {
    const items = storedItem?.pallet_stored_items || []
    return items.filter((ps: any) => !((ps?.selected_quantity ?? 0) > 0 || (ps?.available_to_pick_quantity ?? 0) > 0))
}

const isModalSetAsPicked = ref(false)
const isModalPickingUsers = ref(false)
const selectedDispatchableReturn = ref<any | null>(null)
const selectedPicker = ref<{ id: number; contact_name?: string | null } | null>(null)
const isLoadingSetAsPicked = ref(false)
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

const isStoredItemReturn = computed(() => selectedDispatchableReturn.value?.type === "stored_item")
const isPickingState = computed(() => selectedDispatchableReturn.value?.state === "picking")
const isPickedState = computed(() => selectedDispatchableReturn.value?.state === "picked")
const canChangePicker = computed(() => {
    return Boolean(
        isPickingState.value
        && selectedDispatchableReturn.value?.pickerPackerRoutes?.pickers_list?.name
        && selectedDispatchableReturn.value?.pickerPackerRoutes?.update?.name
    )
})
const canChangePacker = computed(() => {
    return Boolean(
        isPickedState.value
        && isStoredItemReturn.value
        && selectedDispatchableReturn.value?.pickerPackerRoutes?.packers_list?.name
        && selectedDispatchableReturn.value?.pickerPackerRoutes?.update?.name
    )
})
const changePickingUsersLabel = computed(() => {
    if (canChangePacker.value) {
        return trans("Change packer")
    }
    if (canChangePicker.value) {
        return trans("Change picker")
    }
    return trans("Change picker / packer")
})
const activePickingUsersLabel = computed(() => canChangePacker.value ? trans("Packer") : trans("Picker"))
const activePickingUsersPlaceholder = computed(() => canChangePacker.value ? trans("Select packer") : trans("Select picker"))
const activePickingUsersFetchRoute = computed(() => {
    if (canChangePacker.value) {
        return selectedDispatchableReturn.value?.pickerPackerRoutes?.packers_list
    }

    return selectedDispatchableReturn.value?.pickerPackerRoutes?.pickers_list
})
const modalPrimaryLabel = computed(() => {
    if (selectedDispatchableReturn.value?.state === "picking") {
        return trans("Set as Picked")
    }

    return selectedDispatchableReturn.value?.isCollection ? trans("Set as Collected") : trans("Dispatch")
})
const canShowModalPrimaryButton = computed(() => selectedDispatchableReturn.value?.state !== "dispatched")
const canEditParcels = computed(() => selectedDispatchableReturn.value?.state !== "dispatched")
const hasShipmentRequirementMet = computed(() => {
    if (selectedDispatchableReturn.value?.isCollection) {
        return true
    }

    return (selectedDispatchableReturn.value?.shipments?.length ?? 0) > 0
})
const canSubmitModalPrimaryButton = computed(() => {
    if (selectedDispatchableReturn.value?.state === "picking") {
        return true
    }

    return hasShipmentRequirementMet.value
})

const onOpenModalPickingUsers = () => {
    if (canChangePacker.value) {
        selectedPicker.value = selectedDispatchableReturn.value?.packer ?? null
    } else {
        selectedPicker.value = selectedDispatchableReturn.value?.picker ?? null
    }
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
            text: canChangePacker.value ? trans("Please select packer first") : trans("Please select picker first"),
            type: "error",
        })
        return
    }

    isLoadingSetAsPicked.value = true

    try {
        await axios.patch(
            buildUrl(
                dispatchableReturn.pickerPackerRoutes.update.name,
                dispatchableReturn.pickerPackerRoutes.update.parameters
            ),
            canChangePacker.value
                ? { packer_user_id: selectedPicker.value?.id ?? null }
                : { picker_user_id: selectedPicker.value?.id ?? null }
        )

        selectedDispatchableReturn.value = {
            ...dispatchableReturn,
            ...(canChangePacker.value ? { packer: selectedPicker.value } : { picker: selectedPicker.value }),
        }

        isModalPickingUsers.value = false
        notify({
            title: trans("Success"),
            text: canChangePacker.value ? trans("Packer updated successfully") : trans("Picker updated successfully"),
            type: "success",
        })
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || (canChangePacker.value ? trans("Failed to update packer") : trans("Failed to update picker")),
            type: "error",
        })
    } finally {
        isLoadingSetAsPicked.value = false
    }
}

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

const onSetAsPicked = async () => {
    const dispatchableReturn = selectedDispatchableReturn.value
    if (!dispatchableReturn?.pickAllRoute?.name) {
        return
    }

    isLoadingSetAsPicked.value = true

    try {
        const originalPickerId = dispatchableReturn?.picker?.id ?? null
        const selectedPickerId = selectedPicker.value?.id ?? null

        if (selectedPickerId !== originalPickerId && dispatchableReturn?.pickerPackerRoutes?.update?.name) {
            await axios.patch(
                buildUrl(
                    dispatchableReturn.pickerPackerRoutes.update.name,
                    dispatchableReturn.pickerPackerRoutes.update.parameters
                ),
                { picker_user_id: selectedPickerId }
            )
        }

        await axios.request({
            method: dispatchableReturn.pickAllRoute.method || "post",
            url: buildUrl(dispatchableReturn.pickAllRoute.name, dispatchableReturn.pickAllRoute.parameters),
        })

        notify({
            title: trans("Success"),
            text: trans("Pallet return set as picked"),
            type: "success",
        })

        onCloseSetAsPickedModal()
        router.reload()
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Failed to set as picked"),
            type: "error",
        })
    } finally {
        isLoadingSetAsPicked.value = false
    }
}

const onDispatchPalletReturn = async () => {
    const dispatchRoute = selectedDispatchableReturn.value?.dispatchRoute
    if (!dispatchRoute?.name) {
        return
    }

    if (!hasShipmentRequirementMet.value) {
        notify({
            title: trans("Something went wrong"),
            text: trans("Please add shipment before dispatch"),
            type: "error",
        })
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
</script>

<template>
    <Table :resource="data" :name="tab ?? ''" class="mt-5" :rowColorFunction="rowClass">
        <template #cell(pallet_return_reference)="{ item }">
            <template v-if="groupMode === 'by_return'">
                <template v-if="isFirstReturnRow(item)">
                    <div class="flex items-center gap-x-2">
                        <Icon v-if="item?.state_icon" :data="item.state_icon" class="px-1 shrink-0" />
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
                <template v-else>
                    <div class="-mt-px pt-px">
                        <span class="invisible">-</span>
                    </div>
                </template>
            </template>
            <template v-else>
                <div class="flex items-center gap-x-2">
                    <Icon v-if="item?.state_icon" :data="item.state_icon" class="px-1 shrink-0" />
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
        </template>

        <template #cell(reference)="{ item }">
            <template v-if="groupMode === 'by_return'">
                <template v-if="isFirstReturnRow(item)">
                    <div class="space-y-3">
                        <div
                            v-for="storedItem in getItemsByReturn(item.pallet_return_id)"
                            :key="storedItem.id"
                            class="border-b last:border-b-0 pb-3 last:pb-0"
                        >
                            <div
                                v-if="storedItem.pallet_stored_items?.length"
                                class="space-y-2"
                            >
                                <div
                                    v-for="palletStoredItem in getRequestedPallets(storedItem)"
                                    :key="palletStoredItem.id"
                                    class="flex items-center gap-x-4"
                                >
                                    <!-- Stored item reference -->
                                    <div class="min-w-[150px]">
                                        <Link
                                            v-if="storedItemRoute(storedItem)"
                                            :href="storedItemRoute(storedItem)"
                                            class="primaryLink"
                                        >
                                            {{ storedItem.reference }}
                                        </Link>
                                        <div v-else>
                                            {{ storedItem.reference || "-" }}
                                        </div>
                                    </div>

                                    <!-- Pallet reference + location -->
                                    <div class="min-w-[190px]">
                                        <Link
                                            v-if="palletRoute(palletStoredItem)"
                                            :href="palletRoute(palletStoredItem)"
                                            class="secondaryLink"
                                        >
                                            {{ palletStoredItem.reference }}
                                        </Link>
                                        <span v-else>
                                            {{ palletStoredItem.reference || "-" }}
                                        </span>
                                        <span
                                            v-if="palletStoredItem.location?.code"
                                            class="text-gray-400"
                                        >
                                            [{{ palletStoredItem.location.code }}]
                                        </span>
                                        <div class="text-gray-400 tabular-nums text-xs">
                                            {{ trans("Stocks in pallet") }}:
                                            {{ palletStoredItem.quantity_in_pallet ?? 0 }}
                                        </div>
                                    </div>

                                    <!-- Current stock / Requested quantity / pick controls -->
                                    <div
                                        v-if="
                                            isRowPicking(storedItem) ||
                                            isPickingFinished() ||
                                            isPackingFinished()
                                        "
                                        class="flex items-center gap-x-4 ml-auto"
                                    >
                                        <div class="tabular-nums text-xs text-gray-500 min-w-[150px] text-right">
                                            <span class="text-[10px] uppercase text-gray-400 mr-1">
                                                {{ trans("Current stock") }}
                                            </span>
                                            {{ storedItem.total_quantity ?? 0 }}
                                        </div>
                                        <div class="tabular-nums text-xs text-gray-500 min-w-[180px] text-right">
                                            <span class="text-[10px] uppercase text-gray-400 mr-1">
                                                {{ trans("Requested") }}
                                            </span>
                                            {{ storedItem.total_quantity_ordered ?? 0 }}
                                        </div>

                                        <div class="shrink-0">
                                            <template v-if="isRowPicking(storedItem)">
                                                <div
                                                    v-if="palletStoredItem.state === 'picked'"
                                                    class="flex items-center gap-x-2 tabular-nums"
                                                >
                                                    <Button
                                                        @click="() => onUndoPick(palletStoredItem.undoRoute, palletStoredItem)"
                                                        icon="fal fa-undo-alt"
                                                        :label="trans('Undo pick')"
                                                        size="xs"
                                                        type="tertiary"
                                                        :loading="get(isLoadingUndoPick, palletStoredItem.id, false)"
                                                        class="py-0"
                                                    />
                                                    <span class="text-gray-500 text-xs">
                                                        {{ palletStoredItem.picked_quantity ?? 0
                                                        }}/{{ palletStoredItem.selected_quantity ?? 0 }}
                                                    </span>
                                                </div>

                                                <NumberWithButtonSave
                                                    v-else
                                                    noUndoButton
                                                    :modelValue="palletStoredItem.selected_quantity ?? 0"
                                                    saveOnForm
                                                    :routeSubmit="
                                                        palletStoredItem.pallet_return_item_id
                                                            ? palletStoredItem.updateRoute
                                                            : palletStoredItem.newPickRoute
                                                    "
                                                    :keySubmit="
                                                        palletStoredItem.pallet_return_item_id
                                                            ? 'quantity_picked'
                                                            : 'quantity_ordered'
                                                    "
                                                    :bindToTarget="{
                                                        step: 1,
                                                        min: 0,
                                                        max: palletStoredItem.max_quantity ?? 0,
                                                    }"
                                                >
                                                    <template #save="{ isProcessing, onSaveViaForm }">
                                                        <Button
                                                            v-if="(palletStoredItem.selected_quantity ?? 0) > 0"
                                                            @click="() => onSaveViaForm()"
                                                            icon="fal fa-save"
                                                            :label="trans('pick')"
                                                            size="xs"
                                                            type="secondary"
                                                            :loading="isProcessing"
                                                            class="py-0"
                                                        />
                                                    </template>
                                                </NumberWithButtonSave>
                                            </template>
                                            <template v-else-if="isPickingFinished() || isPackingFinished()">
                                                <span
                                                    v-if="storedItem.pallet_return_state === 'cancel'"
                                                    class="tabular-nums text-xs text-red-500 flex items-center gap-x-1"
                                                >
                                                    <span>× {{ trans("Cancelled") }}</span>
                                                    <span class="border-l border-red-300 h-3 mx-1"></span>
                                                    <span>
                                                        {{ palletStoredItem.picked_quantity ?? 0
                                                        }}/{{ palletStoredItem.selected_quantity ?? 0 }}
                                                    </span>
                                                </span>
                                                <span
                                                    v-else
                                                    class="tabular-nums text-xs text-gray-500"
                                                >
                                                    {{ palletStoredItem.picked_quantity ?? 0
                                                    }}/{{ palletStoredItem.selected_quantity ?? 0 }}
                                                    <span
                                                        class="text-emerald-500 ml-1"
                                                    >
                                                        ✓
                                                    </span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-gray-400 italic text-xs">
                                {{ trans("No pallet") }}
                            </div>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <div class="-mt-px pt-px">
                        <span class="invisible">-</span>
                    </div>
                </template>
            </template>

            <template v-else>
                <Link v-if="storedItemRoute(item)" :href="storedItemRoute(item)" class="primaryLink">
                    {{ item.reference }}
                </Link>
                <div v-else>
                    {{ item.reference || "-" }}
                </div>
            </template>
        </template>

        <template #cell(total_quantity)="{ item }">
            <div class="tabular-nums text-right">
                {{ item.total_quantity ?? 0 }}
            </div>
        </template>

        <template #cell(pallet_stored_items)="{ item: storedItem, proxyItem }">
            <div
                v-if="storedItem.pallet_stored_items?.length && groupMode === 'by_item'"
                class="space-y-2"
            >
                <!-- Requested/selected pallets first -->
                <div v-for="palletStoredItem in getRequestedPallets(storedItem)" :key="`req-${palletStoredItem.id}`" class="flex justify-between gap-x-4">
                    <div>
                        <Link v-if="palletRoute(palletStoredItem)" :href="palletRoute(palletStoredItem)" class="primaryLink">
                            {{ palletStoredItem.reference }}
                        </Link>
                        <span v-else>
                            {{ palletStoredItem.reference || "-" }}
                        </span>
                        <span v-if="palletStoredItem.location?.code" class="text-gray-400"> [{{ palletStoredItem.location.code }}]</span>

                        <div class="text-gray-400 tabular-nums">
                            {{ trans('Stocks in pallet') }}: {{ palletStoredItem.quantity_in_pallet ?? 0 }}
                        </div>
                    </div>

                    <div v-if="isRowPicking(storedItem)" class="shrink-0">
                        <div v-if="palletStoredItem.state === 'picked'" class="flex items-center gap-x-2 tabular-nums">
                            <Button
                                @click="() => onUndoPick(palletStoredItem.undoRoute, palletStoredItem)"
                                icon="fal fa-undo-alt"
                                :label="trans('Undo pick')"
                                size="xs"
                                type="tertiary"
                                :loading="get(isLoadingUndoPick, palletStoredItem.id, false)"
                                class="py-0"
                            />
                            <span class="text-gray-500">
                                {{ palletStoredItem.picked_quantity ?? 0 }}/{{ palletStoredItem.selected_quantity ?? 0 }}
                            </span>
                        </div>

                        <NumberWithButtonSave
                            v-else
                            noUndoButton
                            :modelValue="palletStoredItem.selected_quantity ?? 0"
                            saveOnForm
                            :routeSubmit="palletStoredItem.pallet_return_item_id ? palletStoredItem.updateRoute : palletStoredItem.newPickRoute"
                            :keySubmit="palletStoredItem.pallet_return_item_id ? 'quantity_picked' : 'quantity_ordered'"
                            :bindToTarget="{
                                step: 1,
                                min: 0,
                                max: palletStoredItem.max_quantity ?? 0
                            }"
                        >
                            <template #save="{ isProcessing, onSaveViaForm }">
                                <Button
                                    v-if="(palletStoredItem.selected_quantity ?? 0) > 0"
                                    @click="() => onSaveViaForm()"
                                    icon="fal fa-save"
                                    :label="trans('pick')"
                                    size="xs"
                                    type="secondary"
                                    :loading="isProcessing"
                                    class="py-0"
                                />
                            </template>
                        </NumberWithButtonSave>
                    </div>
                </div>

                <Collapse
                    v-if="groupMode === 'by_item'"
                    as="section"
                    :when="get(proxyItem, ['is_open_collapsed'], false)"
                >
                    <div class="space-y-2">
                        <div v-for="palletStoredItem in getHiddenPallets(storedItem)" :key="`hid-${palletStoredItem.id}`" class="flex justify-between gap-x-4">
                            <div>
                                <Link v-if="palletRoute(palletStoredItem)" :href="palletRoute(palletStoredItem)" class="secondaryLink">
                                    {{ palletStoredItem.reference }}
                                </Link>
                                <span v-else>
                                    {{ palletStoredItem.reference || "-" }}
                                </span>
                                <span v-if="palletStoredItem.location?.code" class="text-gray-400"> [{{ palletStoredItem.location.code }}]</span>
                                <div class="text-gray-400 tabular-nums">
                                    {{ trans('Stocks in pallet') }}: {{ palletStoredItem.quantity_in_pallet ?? 0 }}
                                </div>
                            </div>
                        </div>
                    </div>
                </Collapse>

                <div
                    v-if="groupMode === 'by_item' && getHiddenPallets(storedItem).length"
                    class="w-full mt-2"
                >
                    <Button
                        type="dashed"
                        full
                        size="sm"
                        @click="() => set(proxyItem, ['is_open_collapsed'], !get(proxyItem, ['is_open_collapsed'], false))"
                    >
                        <div class="py-1 text-gray-500">
                            <FontAwesomeIcon
                                icon="fal fa-arrow-down"
                                class="transition-all"
                                :class="get(proxyItem, ['is_open_collapsed'], false) ? 'rotate-180' : ''"
                                fixed-width
                                aria-hidden="true"
                            />
                            {{ get(proxyItem, ['is_open_collapsed'], false) ? 'Close' : 'Open hidden pallets' }}
                        </div>
                    </Button>
                </div>
            </div>

            <div v-else class="text-gray-400 italic">
                {{ trans('No pallet') }}
            </div>
        </template>

        <template #cell(total_quantity_ordered)="{ item }">
            <div class="tabular-nums text-right">
                {{ item.total_quantity_ordered ?? 0 }}
            </div>
        </template>

        <template #cell(actions)="{ item }">
            <div v-if="isFirstReturnRow(item) && (isPickingFinished() || isPackingFinished())" class="flex justify-end gap-x-2">
                <template v-if="item?.pallet_return_state === 'picking'">
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
                    v-if="isPackingFinished() && canRowDispatch(item)"
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
            <div v-else class="-mt-px pt-px"></div>
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
                :disabled="!canSubmitModalPrimaryButton"
                v-tooltip="!canSubmitModalPrimaryButton ? trans('Please add shipment before dispatch') : ''"
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
                <!-- <div class="text-base font-semibold text-gray-800 mb-3">{{ trans("Pallet Return") }}</div> -->

                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-x-2 border-l-4 border-indigo-300 bg-indigo-50 px-2 py-0.5">
                        <div class="text-gray-500">{{ trans("Picker") }}:</div>
                        <div class="font-medium text-gray-800">
                            {{ selectedDispatchableReturn?.picker?.contact_name || "-" }}
                        </div>
                    </div>

                    <div v-if="isStoredItemReturn && isPickedState" class="flex items-center gap-x-2 border-l-4 border-indigo-300 bg-indigo-50 px-2 py-0.5">
                        <div class="text-gray-500">{{ trans("Packer") }}:</div>
                        <div class="font-medium text-gray-800">
                            {{ selectedDispatchableReturn?.packer?.contact_name || "-" }}
                        </div>
                    </div>

                    <Button
                        v-if="canChangePicker || canChangePacker"
                        @click="onOpenModalPickingUsers"
                        :icon="faExchange"
                        type="tertiary"
                        size="xs"
                        :label="changePickingUsersLabel" />

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
                        <FontAwesomeIcon v-tooltip="trans('Parcels')" icon="fas fa-cubes" class="text-gray-400"
                            fixed-width aria-hidden="true" />
                        <div class="font-medium">{{ trans("Parcels") }} ({{ selectedDispatchableReturn?.parcels?.length
                            ?? 0 }})
                        </div>
                    </div>
                    <div class="mt-2">
                        <ul v-if="selectedDispatchableReturn?.parcels?.length" class="list-disc pl-4 mt-2">
                            <li v-for="(parcel, parcelIdx) in selectedDispatchableReturn?.parcels" :key="parcelIdx"
                                class="text-sm tabular-nums">
                                <span>{{ parcel.weight }} kg</span>
                                <span class="text-gray-500"> ({{ parcel.dimensions?.[0] }}x{{ parcel.dimensions?.[1]
                                    }}x{{
                                        parcel.dimensions?.[2] }} cm)</span>
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
                        v-if="(selectedDispatchableReturn?.shipments?.length ?? 0) < 1"
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
                            <div v-else @click="() => onDeleteShipment(shipment.id)" v-tooltip="trans('Remove shipment')" class="cursor-pointer px-1">
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
            <div v-if="canChangePicker || canChangePacker" class="flex flex-col gap-2">
                <div class="text-sm font-medium">{{ activePickingUsersLabel }}</div>
                <PureMultiselectInfiniteScroll
                    v-if="activePickingUsersFetchRoute"
                    v-model="selectedPicker"
                    :fetchRoute="activePickingUsersFetchRoute"
                    :placeholder="activePickingUsersPlaceholder"
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
                <div v-if="isLoadingData === 'addTrackingNumber'" v-for="sip in 3" class="skeleton w-full max-w-52 h-20 rounded"></div>
                <div
                    v-else
                    v-for="shipment in optionShippingList.filter(shipment => shipment.api_shipper)"
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
