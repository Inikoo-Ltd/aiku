<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import AcquisitionTab from "@/Components/Marketing/V2/AcquisitionTab.vue"
import OrdersTab from "@/Components/Marketing/V2/OrdersTab.vue"
import JourneyTab from "@/Components/Marketing/V2/JourneyTab.vue"
import ComparePeriod from "@/Components/DataDisplay/Dashboard/ComparePeriod.vue"
import { type CompareProp, keepCompareQuery } from "@/Components/DataDisplay/Dashboard/comparePeriod"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { Intervals, Settings } from "@/types/Components/Dashboard"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBullhorn, faUserPlus, faShoppingCart, faRoute } from "@fal"

library.add(faBullhorn, faUserPlus, faShoppingCart, faRoute)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    intervals: Intervals
    settings: Settings
    compare: CompareProp & { tracking_started_at: string | null }
    acquisition?: InstanceType<typeof AcquisitionTab>["$props"]["data"]
    orders?: InstanceType<typeof OrdersTab>["$props"]["data"]
    journey?: InstanceType<typeof JourneyTab>["$props"]["data"]
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => {
    if (tabSlug === currentTab.value) {
        return
    }

    const url = new URL(window.location.href)
    const target = keepCompareQuery(new URLSearchParams({ tab: tabSlug }), url.searchParams)

    router.get(`${url.pathname}?${target.toString()}`, {}, {
        only: [tabSlug],
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { currentTab.value = tabSlug },
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <div class="pt-3">
        <DashboardSettings
            :intervals="intervals"
            :settings="settings"
            :currentTab="currentTab"
            :reloadOnly="['intervals', 'compare', currentTab]"
        >
            <template #actions>
                <ComparePeriod
                    v-if="currentTab !== 'journey'"
                    :compare="compare"
                    :reloadOnly="[currentTab, 'compare']"
                    :note="compare.tracking_started_at ? ctrans('Touches are recorded since :date, so a period before then has nothing to compare.', { date: compare.tracking_started_at }) : undefined" />
            </template>
        </DashboardSettings>
    </div>
    <AcquisitionTab v-if="currentTab === 'acquisition' && acquisition" :key="`${acquisition.from}-${acquisition.to}`" :data="acquisition" />
    <OrdersTab v-if="currentTab === 'orders' && orders" :data="orders" />
    <JourneyTab v-if="currentTab === 'journey' && journey" :data="journey" />
</template>
