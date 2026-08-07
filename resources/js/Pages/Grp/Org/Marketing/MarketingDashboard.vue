<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 05 Jan 2025 14:40:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, ref } from 'vue'
import type { Component } from 'vue'

import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import SimpleBox from '@/Components/DataDisplay/SimpleBox.vue'
import MarketingOverview from '@/Components/DataDisplay/MarketingOverview.vue'

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    dashboard_stats: {
        label: string
        count: number
        icon: string
    }[]
    marketing_overview: InstanceType<typeof MarketingOverview>['$props']['overview']


}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        dashboard: {}
    }

    return components[currentTab.value]

})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
    <MarketingOverview v-if="currentTab === 'dashboard' && marketing_overview" :overview="marketing_overview" />
    <SimpleBox v-if="currentTab === 'dashboard' && dashboard_stats" :box_stats="dashboard_stats" />
</template>