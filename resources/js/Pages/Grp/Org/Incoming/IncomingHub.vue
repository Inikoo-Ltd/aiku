<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import IncomingDashboard from "@/Components/Warehouse/IncomingDashboard.vue"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    stock_deliveries: object
    pallet_deliveries: object
    return_delivery_notes: object
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabData = computed(() => {
    if (currentTab.value === "pallet_deliveries") return props.pallet_deliveries
    if (currentTab.value === "return_delivery_notes") return props.return_delivery_notes
    return props.stock_deliveries
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <IncomingDashboard :tab="currentTab" :data="tabData" />
</template>
