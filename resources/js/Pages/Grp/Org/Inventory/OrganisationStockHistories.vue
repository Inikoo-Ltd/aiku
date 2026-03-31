<!--
  -  Author: Nickel
  -  Created: Tue, 01 Apr 2026
  -  Copyright (c) 2026, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrganisationStockHistories from "@/Components/Tables/Grp/Org/Inventory/TableOrganisationStockHistories.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { computed, ref } from "vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { useTabChange } from "@/Composables/tab-change"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendarDay, faCalendarWeek, faCalendarAlt, faCalendar } from "@fal"

library.add(faCalendarDay, faCalendarWeek, faCalendarAlt, faCalendar)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }
    daily?: {}
    weekly?: {}
    monthly?: {}
    yearly?: {}
}>()

const currentTab = ref<string>(props?.tabs?.current ?? "daily")
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, any> = {
        daily: TableOrganisationStockHistories,
        weekly: TableOrganisationStockHistories,
        monthly: TableOrganisationStockHistories,
        yearly: TableOrganisationStockHistories,
    }

    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab]"
    />
</template>
