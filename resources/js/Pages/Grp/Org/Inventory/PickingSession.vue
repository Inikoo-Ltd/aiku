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
import { ref, computed, watch } from "vue"
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


const props = defineProps<{
    data: object
    title: string
    pageHead: PageHeadingTypes
    items: object
    itemized: object
    grouped: object
    returnType?: string
    dispatchableReturns?: any[]
    timelines: {
        [key: string]: TSTimeline
    }
    tabs: {
        current: string;
        navigation: object;
    }
}>()


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
            <div v-if="data.data.state === 'in_process'" class="flex gap-2">
                <Button
                    class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 rounded transition-colors"
                    @click="openAddModal"
                    type="tertiary"
                >
                    {{
                        isFulfilmentSession
                            ? trans('Add/remove Pallet Returns')
                            : trans('Add/remove Delivery Notes')
                    }}
                </Button>
                <Button
                    class="px-3 py-1.5 text-sm bg-red-50 hover:bg-red-100 text-red-700 rounded transition-colors"
                    @click="openRemoveModal"
                    type="negative"
                >
                    {{
                        isFulfilmentSession
                            ? trans('Remove Pallet Returns')
                            : trans('Remove Delivery Notes')
                    }}
                </Button>
            </div>
        </template>
    </PageHeading>
    <div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
        <Timeline :options="timelines" :state="data.data.state" :slidesPerView="6" :format-time="'MMMM d yyyy, HH:mm'" />
    </div>
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <div class="pb-12">
        <component
            :is="component"
            :data="props[currentTab]"
            :tab="currentTab"
            :pickingSession="data.data"
            :dispatchableReturns="dispatchableReturns"
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
</template>
