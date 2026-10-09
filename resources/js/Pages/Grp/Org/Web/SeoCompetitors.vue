<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import SeoCompetitors from "@/Components/Seo/SeoCompetitors.vue"
import SeoDomainComparison from "@/Components/Seo/SeoDomainComparison.vue"
import SeoKeywordGap from "@/Components/Seo/SeoKeywordGap.vue"
import { capitalize } from "@/Composables/capitalize"
import { useTabChange } from "@/Composables/tab-change"
import { ctrans } from "@/Composables/useTrans"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"
import { Navigation } from "@/types/Tabs"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string, navigation: Navigation }
    canEdit: boolean
    isUsable: boolean
    isConfigured: boolean
    addRoute: routeType
    market: { domain: string, country: string, language: string } | null
    domains?: object | null
    comparison?: object | null
    keyword_gap?: object | null
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabComponent = computed(() => ({
    domains: SeoCompetitors,
    comparison: SeoDomainComparison,
    keyword_gap: SeoKeywordGap,
})[currentTab.value])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <p v-if="!isConfigured" role="alert" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-gray-700 ring-1 ring-gray-200">
        {{ ctrans("DataForSEO is not set up, so nothing is fetched for the competitors. Add DATAFORSEO_LOGIN and DATAFORSEO_PASSWORD to the environment.") }}
    </p>

    <p v-if="!isUsable && currentTab !== 'domains'" class="mx-4 mt-4 rounded-xl bg-white px-5 py-4 text-sm text-gray-600 ring-1 ring-gray-200">
        {{ ctrans("This shop needs a website, a country and a language for the comparison and the keyword gap.") }}
    </p>

    <component
        v-else
        :is="tabComponent"
        :key="currentTab"
        :data="props[currentTab as keyof typeof props]"
        :tab="currentTab"
        :canEdit="canEdit"
        :addRoute="addRoute"
        :market="market" />
</template>
