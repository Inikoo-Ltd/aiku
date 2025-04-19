<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faChartLine, faCoins, faInfoCircle, faSitemap } from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, defineAsyncComponent, ref } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
// import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue"
// import ModelDetails from "@/Components/ModelDetails.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import PaymentAccountOverview from "@/Components/Tables/Grp/Org/Accounting/PaymentAccountOverview.vue"
import PaymentAccountStats from "@/Components/Tables/Grp/Org/Accounting/PaymentAccountStats.vue"
import Dashboard from '@/Components/DataDisplay/Dashboard/DashboardOld.vue'

library.add(faCoins, faChartLine, faInfoCircle, faSitemap)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))

const props = defineProps<{
    title: string,
    pageHead: {}
    tabs: {
        current: string
        navigation: {}
    }
    payments?: {}
    history: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        overview: PaymentAccountOverview,
        stats: PaymentAccountStats,
        history: TableHistories
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
