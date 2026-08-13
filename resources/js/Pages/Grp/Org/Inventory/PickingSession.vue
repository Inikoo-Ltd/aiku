<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:55:18 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { useTabChange } from "@/Composables/tab-change"
import { ref, computed, watch, inject } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableDeliveryNoteItemInPickingSessions from "@/Components/Warehouse/PickingSessions/TableDeliveryNoteItemInPickingSessions.vue"
import TablePalletReturnInPickingSessions from "@/Components/Warehouse/PickingSessions/TablePalletReturnInPickingSessions.vue"
import TableFulfilmentPickingSessionStoredItems from "@/Components/Warehouse/PickingSessions/TableFulfilmentPickingSessionStoredItems.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import SelectDeliveryNotesModal from "@/Components/Warehouse/PickingSessions/SelectDeliveryNotesModal.vue"
import SelectPalletReturnsModal from "@/Components/Warehouse/PickingSessions/SelectPalletReturnsModal.vue"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { notify } from "@kyvg/vue3-notification"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import ScanToPackDeliveryNote from "@/Components/DeliveryNote/ScanToPackDeliveryNote.vue"
import { routeType } from "@/types/route"
import { debounce } from "lodash-es"


const props = defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
    items: object
    itemized: object
    grouped: object
    returnType?: string
    dispatchableReturns?: any[]
    allow_waiting: boolean
    allow_picker_set_not_picked: boolean
    picker?: { id: number, contact_name: string } | null
    scan_to_pack?: {
        scan_route: routeType
    }
    routes?: {
        update: { name: string, parameters: object }
        pickers_list: { name: string, parameters: object }
    }
    timelines: {
        [key: string]: TSTimeline
    }
    tabs: {
        current: string;
        navigation: object;
    }
}>()
console.log("props", props)

const layout = inject("layout", layoutStructure)

const isModalChangePicker = ref(false)
const selectedPicker = ref(props.picker)
const isSavingPicker = ref(false)

// Every note in the session follows this picker, so one change moves the whole run at once
const takeSessionMyself = () => {
    selectedPicker.value = { id: layout?.user?.id, contact_name: layout?.user?.username }
    onUpdateSessionPicker()
}

const onUpdateSessionPicker = () => {
    if (!selectedPicker.value?.id || !props.routes?.update) {
        notify({ title: trans("Something went wrong"), text: trans("Picker is not selected"), type: "error" })
        return
    }

    router.patch(route(props.routes.update.name, props.routes.update.parameters),
        { user_id: selectedPicker.value.id },
        {
            preserveScroll: true,
            onStart: () => (isSavingPicker.value = true),
            onFinish: () => (isSavingPicker.value = false),
            onSuccess: () => (isModalChangePicker.value = false),
            onError: (error) => notify({
                title: trans("Something went wrong"),
                text: error?.message ?? trans("Unknown error"),
                type: "error",
            }),
        })
}

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const isFulfilmentSession = computed(() => props.data?.data?.type === "fulfilment")
const normalizedReturnType = computed(() => {
    const normalizeType = (value?: string): "stored_item" | "pallet" | null => {
        if (typeof value !== "string") {
            return null
        }

        const normalized = value.toLowerCase().replace(/-/g, "_")
        if (normalized === "stored_item") {
            return "stored_item"
        }
        if (normalized === "pallet") {
            return "pallet"
        }

        return null
    }

    const fromProp = normalizeType(props.returnType)
    if (fromProp) {
        return fromProp
    }

    const firstRowType = Array.isArray(props.data?.data)
        ? normalizeType(props.data.data.find((item) => item?.pallet_return_type)?.pallet_return_type)
        : null
    if (firstRowType) {
        return firstRowType
    }

    const firstDispatchableType = Array.isArray(props.dispatchableReturns)
        ? normalizeType(props.dispatchableReturns.find((item) => item?.type)?.type)
        : null

    return firstDispatchableType ?? "pallet"
})
const isFulfilmentStoredItems = computed(
    () => isFulfilmentSession.value && normalizedReturnType.value === "stored_item"
)

const component = computed(() => {
    const componentMap = isFulfilmentSession.value
        ? {
            items: isFulfilmentStoredItems.value ? TableFulfilmentPickingSessionStoredItems : TablePalletReturnInPickingSessions,
            itemized: isFulfilmentStoredItems.value ? TableFulfilmentPickingSessionStoredItems : TablePalletReturnInPickingSessions,
            grouped: isFulfilmentStoredItems.value ? TableFulfilmentPickingSessionStoredItems : TablePalletReturnInPickingSessions
        }
        : {
            items: TableDeliveryNoteItemInPickingSessions,
            itemized: TableDeliveryNoteItemInPickingSessions,
            grouped: TableDeliveryNoteItemInPickingSessions
        }

    return componentMap[currentTab.value]
})

watch(() => props.tabs.current, (newTab) => {
    currentTab.value = newTab
}, { immediate: true })

// A scan that moves the session to another state changes the header actions and the tabs as much as
// it changes the rows, so pageHead has to come back with it.
const debReloadPage = debounce(() => {
    router.reload({
        except: ["auth", "breadcrumbs", "flash", "layout", "localeData", "ziggy"]
    })
}, 1200)

type ScanOutcome = {
    status: string
    item?: { id: number } | null
    delivery_note?: { id: number } | null
    row?: Record<string, any> | null
    picking_session_state?: string
}

// A scan touches one item of one delivery note, so only the row it landed on changes. Patching that
// row in place keeps the operator on the same scroll position instead of re-rendering the whole table.
const patchRowScannedBy = (outcome: ScanOutcome, successStatus: string) => {
    if (outcome.status !== successStatus) {
        return
    }

    const scannedRowId = currentTab.value === "grouped" ? outcome.delivery_note?.id : outcome.item?.id
    const rows = (props[currentTab.value as keyof typeof props] as { data?: any[] } | undefined)?.data
    const scannedRow = rows?.find((row: any) => row.id === scannedRowId)

    if (scannedRow && outcome.row) {
        Object.assign(scannedRow, outcome.row)
    }
}

const onItemPackedByScan = (outcome: ScanOutcome) => {
    patchRowScannedBy(outcome, "packed")

    if (outcome.picking_session_state === "packing_finished") {
        debReloadPage()
    }
}





// Section: add/remove Delivery Notes to Picking Session
const isAddModalOpen = ref(false)
const isRemoveModalOpen = ref(false)
const openAddModal = () => {
    isAddModalOpen.value = true
}
const openRemoveModal = () => {
    isRemoveModalOpen.value = true
}
const closeAddModal = () => {
    isAddModalOpen.value = false
}
const closeRemoveModal = () => {
    isRemoveModalOpen.value = false
}
const handleModalSuccess = () => {
    router.reload()
}
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div v-if="routes?.update && !isFulfilmentSession" class="flex items-center gap-x-2 mr-2">
                <div v-if="picker?.contact_name" class="text-sm text-gray-500">
                    {{ trans('Picker') }}: <span class="text-gray-700">{{ picker.contact_name }}</span>
                </div>
                <Button
                    @click="isModalChangePicker = true"
                    :label="trans('Change Picker')"
                    type="tertiary"
                    size="xs"
                    v-tooltip="trans('Everything in this picking session is picked by this person. Changing it here moves the whole session at once.')"
                />
            </div>
            <div v-if="data.data.state === 'in_process'" class="flex gap-2">
                <Button
                    class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded transition-colors"
                    @click="openAddModal"
                    type="tertiary"
                >
                    {{
                        isFulfilmentSession
                            ? trans('Add/remove Pallet Returns')
                            : ctrans('Add Delivery Notes')
                    }}
                </Button>
                <Button
                    class="px-3 py-1.5 text-sm bg-red-50 hover:bg-red-100 text-red-700 rounded transition-colors"
                    @click="openRemoveModal"
                    type="negative"
                >
                    {{
                        isFulfilmentSession
                            ? ctrans('Remove Pallet Returns')
                            : ctrans('Remove Delivery Notes')
                    }}
                </Button>
            </div>
        </template>
    </PageHeading>
    <div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
        <Timeline :options="timelines" :state="data.data.state" :slidesPerView="6" :format-time="'MMMM d yyyy, HH:mm'" />
    </div>
    <!-- Section: Scan a barcode to pack the matching item of the delivery note it belongs to -->
    <ScanToPackDeliveryNote
        v-if="scan_to_pack"
        :scanRoute="scan_to_pack.scan_route"
        :tab="currentTab"
        @scanned="onItemPackedByScan"
    />

    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <div class="pb-12">
        <component
            :is="component"
            :data="props[currentTab as keyof typeof props]"
            :tab="currentTab"
            :pickingSession="data.data"
            :dispatchableReturns="dispatchableReturns"
            :allowWaiting="allow_waiting"
            :allowPickerSetNotPicked="allow_picker_set_not_picked"
            :key="`${currentTab}${props.data.state}`"
        />
    </div>

    <SelectDeliveryNotesModal
        v-if="data.data.state === 'in_process' && !isFulfilmentSession"
        :isOpen="isAddModalOpen"
        :pickingSessionId="data.data.id"
        mode="add"
        @onClose="closeAddModal"
        @success="handleModalSuccess"
    />

    <SelectDeliveryNotesModal
        v-if="data.data.state === 'in_process' && !isFulfilmentSession"
        :isOpen="isRemoveModalOpen"
        :pickingSessionId="data.data.id"
        mode="remove"
        @onClose="closeRemoveModal"
        @success="handleModalSuccess"
    />

    <SelectPalletReturnsModal
        v-if="data.data.state === 'in_process' && isFulfilmentSession"
        :isOpen="isAddModalOpen"
        :pickingSessionId="data.data.id"
        :returnType="normalizedReturnType"
        mode="add"
        @onClose="closeAddModal"
        @success="handleModalSuccess"
    />

    <SelectPalletReturnsModal
        v-if="data.data.state === 'in_process' && isFulfilmentSession"
        :isOpen="isRemoveModalOpen"
        :pickingSessionId="data.data.id"
        :returnType="normalizedReturnType"
        mode="remove"
        @onClose="closeRemoveModal"
        @success="handleModalSuccess"
    />

    <Modal :isOpen="isModalChangePicker" @close="isModalChangePicker = false" width="w-full max-w-lg">
        <div class="mt-1 flex flex-col items-start w-full pr-3 gap-y-1.5">
            <div class="mx-auto font-semibold text-lg">{{ trans("Select picker") }}</div>
            <div class="text-sm text-gray-500 mt-2">
                {{ trans('Everything in this picking session will be picked by the person you choose.') }}
            </div>
            <div class="w-full flex justify-end mt-2">
                <Button
                    v-if="picker?.id != layout?.user?.id"
                    @click="takeSessionMyself"
                    :loading="isSavingPicker"
                    :disabled="isSavingPicker"
                    :label="trans('I will do the picking myself')"
                    type="tertiary"
                    size="xs" />
            </div>
            <div class="mt-4 w-full">
                <PureMultiselectInfiniteScroll
                    v-model="selectedPicker"
                    required
                    :fetchRoute="routes.pickers_list"
                    :placeholder="trans('Select picker')"
                    labelProp="contact_name"
                    valueProp="id"
                    object
                    clearOnBlur
                    :disabled="isSavingPicker">
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
            </div>
            <div class="mt-4 w-full flex justify-end">
                <Button
                    @click="onUpdateSessionPicker"
                    :loading="isSavingPicker"
                    :disabled="isSavingPicker"
                    :label="trans('Change Picker')"
                    type="save" />
            </div>
        </div>
    </Modal>
</template>
