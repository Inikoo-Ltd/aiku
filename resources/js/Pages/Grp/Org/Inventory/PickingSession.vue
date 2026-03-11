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

const component = computed(() => {
    const isFulfilment = props.data?.data?.type === "fulfilment"
    const isFulfilmentStoredItems = isFulfilment && props.returnType === 'stored_item'
    const componentMap = isFulfilment
        ? {
            items: isFulfilmentStoredItems ? TableFulfilmentPickingSessionStoredItems : TablePalletReturnInPickingSessions,
            itemized: isFulfilmentStoredItems ? TableFulfilmentPickingSessionStoredItems : TablePalletReturnInPickingSessions,
            grouped: isFulfilmentStoredItems ? TableFulfilmentPickingSessionStoredItems : TablePalletReturnInPickingSessions
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


</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
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
</template>
