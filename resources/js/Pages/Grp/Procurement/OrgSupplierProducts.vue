<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Tue, 28 Feb 2023 10:07:36 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TableOrgSupplierProducts from "@/Components/Tables/Grp/Org/Procurement/TableOrgSupplierProducts.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableAgentSuppliersDataQuality from "@/Components/Tables/Grp/Org/Procurement/TableAgentSuppliersDataQuality.vue"
import AgentProductsDashboard from "@/Components/Procurement/AgentProductsDashboard.vue"
import { useTabChange } from "@/Composables/tab-change"
import { computed, ref } from "vue"
import { routeType } from "@/types/route"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: object
    }
    index?: object
    history?: object
    dashboard?: object
    suppliers?: object
    copy_products?: object | null
    copy_targets?: { id: number, label: string }[]
    labels_print?: {
        plan_route: routeType
        pdf_route: routeType
        ids_parameter: string
    }
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => ({
    dashboard: AgentProductsDashboard,
    suppliers: TableAgentSuppliersDataQuality,
    index: TableOrgSupplierProducts,
    history: TableHistories,
})[currentTab.value])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :data="props[currentTab]"
        :tab="currentTab"
        v-bind="currentTab === 'index' ? { copyProducts: copy_products, copyTargets: copy_targets, labelsPrint: labels_print } : {}"
    />
</template>
