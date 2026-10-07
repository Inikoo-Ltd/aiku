<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import SeoKeywordResearch from "@/Components/Seo/SeoKeywordResearch.vue"
import TableSeoTrackedKeywords from "@/Components/Tables/Grp/Org/Web/TableSeoTrackedKeywords.vue"
import SeoCompetitors from "@/Components/Seo/SeoCompetitors.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Navigation } from "@/types/Tabs"
import type { SeoKeywordOptions, SeoKeywordRoutes, SeoResearchQuery } from "@/Components/Seo/types"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string, navigation: Navigation }
    canEdit: boolean
    routes: SeoKeywordRoutes
    options: SeoKeywordOptions
    query: SeoResearchQuery
    research?: object | null
    tracked_keywords?: object
    competitors?: object[]
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabComponent = computed(() => ({
    research: SeoKeywordResearch,
    tracked_keywords: TableSeoTrackedKeywords,
    competitors: SeoCompetitors,
})[currentTab.value])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <component
        :is="tabComponent"
        :key="currentTab"
        :data="props[currentTab]"
        :tab="currentTab"
        :canEdit="canEdit"
        :routes="routes"
        :options="options"
        :query="query" />
</template>
