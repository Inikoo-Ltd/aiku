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
import Timeline from "@/Components/Utils/Timeline.vue"
import SelectDeliveryNotesModal from "@/Components/Warehouse/PickingSessions/SelectDeliveryNotesModal.vue"
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

const component = computed(() => {

    const components = {
        items: TableDeliveryNoteItemInPickingSessions,
        itemized: TableDeliveryNoteItemInPickingSessions,
        grouped: TableDeliveryNoteItemInPickingSessions
    }
    return components[currentTab.value]

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
                    {{ trans('Add/remove Delivery Notes') }}
                </Button>
                <Button
                    class="px-3 py-1.5 text-sm bg-red-50 hover:bg-red-100 text-red-700 rounded transition-colors"
                    @click="openRemoveModal"
                    type="negative"
                >
                    {{ trans('Remove Delivery Notes') }}
                </Button>
            </div>
        </template>
    </PageHeading>
    <div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
        <Timeline :options="timelines" :state="data.data.state" :slidesPerView="6" :format-time="'MMMM d yyyy, HH:mm'" />
    </div>
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <div class="pb-12">
        <component :is="component" :data="props[currentTab]" :tab="currentTab" :pickingSession="data.data" :key="`${currentTab}${props.data.state}`" />
    </div>

    <SelectDeliveryNotesModal
        v-if="data.data.state === 'in_process'"
        :isOpen="isAddModalOpen"
        :pickingSessionId="data.data.id"
        mode="add"
        @onClose="closeAddModal"
        @success="handleModalSuccess"
    />

    <SelectDeliveryNotesModal
        v-if="data.data.state === 'in_process'"
        :isOpen="isRemoveModalOpen"
        :pickingSessionId="data.data.id"
        mode="remove"
        @onClose="closeRemoveModal"
        @success="handleModalSuccess"
    />
</template>
