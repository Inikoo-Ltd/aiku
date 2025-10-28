<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, defineAsyncComponent, ref } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChartLine, faCoins, faInfoCircle, faMoneyCheckAlt } from '@fal'
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import PaymentAccountOverview from "@/Components/Tables/Grp/Org/Accounting/PaymentAccountOverview.vue"
import PaymentAccountStats from "@/Components/Tables/Grp/Org/Accounting/PaymentAccountStats.vue"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Shop } from "@/types/shop"


library.add(faCoins, faChartLine, faInfoCircle, faMoneyCheckAlt)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: {}
    tabs: {
        current: string
        navigation: {}
    }
    // payments?: {}
    // history: {}
}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        overview: PaymentAccountOverview,
        stats: PaymentAccountStats,
        // payments: TablePayments,
        // details: ModelDetails,
        // history: TableHistories
    }
    
    return components[currentTab.value]
})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
