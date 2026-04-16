<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { ref, computed, watch } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import TableWaitingDeliveryNoteItemsByLocation from '@/Components/Tables/Grp/Org/Dispatching/TableWaitingDeliveryNoteItemsByLocation.vue'
import TableWaitingDeliveryNoteItemsGrouped from '@/Components/Tables/Grp/Org/Dispatching/TableWaitingDeliveryNoteItemsGrouped.vue'

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: Record<string, any>
    }
    grouped?: object
    itemized?: object
    allow_stock_controller_set_not_picked: boolean
    is_still_picking: boolean
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, any> = {
        itemized: TableWaitingDeliveryNoteItemsByLocation,
        grouped: TableWaitingDeliveryNoteItemsGrouped,
    }
    return components[currentTab.value]
})

const tabData = computed(() => {
    return (props as Record<string, any>)[currentTab.value]
})

watch(() => props.tabs.current, (newTab) => {
    currentTab.value = newTab
}, { immediate: true })
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :data="tabData"
        :tab="currentTab"
        :allowStockControllerSetNotPicked="allow_stock_controller_set_not_picked"
        :isStillPicking="is_still_picking"
    />
</template>

