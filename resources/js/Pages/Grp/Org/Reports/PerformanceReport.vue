<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TablePickerPerformanceReport from "@/Components/Tables/Grp/Org/Reports/TablePickerPerformanceReport.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    },
    overview?: {}
    bonus?: {}
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const currentData = computed(() => (props as any)[currentTab.value])

const component = computed(() => {
    const components: any = {
        overview: TablePickerPerformanceReport,
        bonus: TablePickerPerformanceReport,
    }

    return components[currentTab.value]
})

</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"/>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :key="currentTab" :tab="currentTab" :data="currentData"></component>
</template>
