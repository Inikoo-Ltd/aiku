<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import type { Component } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableSnapshots from "@/Components/Tables/TableSnapshots.vue"
import WebsiteDialogShowcase from "./WebsiteDialogShowcase.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import type { PageHeadingTypes } from "@/types/PageHeading"
import type { Tabs as TSTabs } from "@/types/Tabs"
import { faStop } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faStop)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: {}
    snapshots?: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, Component> = {
        showcase: WebsiteDialogShowcase,
        snapshots: TableSnapshots,
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
