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
import SeoRankings from "@/Components/Seo/SeoRankings.vue"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
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
    spend: { month: number, budget: number }
    research?: object | null
    tracked_keywords?: object
    rankings?: object
    competitors?: object[]
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabComponent = computed(() => ({
    research: SeoKeywordResearch,
    tracked_keywords: TableSeoTrackedKeywords,
    rankings: SeoRankings,
    competitors: SeoCompetitors,
})[currentTab.value])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <p v-if="currentTab === 'research'" class="px-4 pt-3 text-xs text-gray-500">
        {{ ctrans("DataForSEO spend this month: :spend of :budget USD", { spend: spend.month.toFixed(2), budget: spend.budget.toFixed(2) }) }}
    </p>

    <component
        :is="tabComponent"
        :key="currentTab"
        :data="props[currentTab]"
        :tab="currentTab"
        :canEdit="canEdit"
        :routes="routes"
        :options="options"
        :query="query"
        :spend="spend" />
</template>
