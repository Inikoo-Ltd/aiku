<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 12:20:38 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TableOrders from '@/Components/Tables/Grp/Org/Ordering/TableOrders.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import OrdersStats from '@/Components/Dropshipping/Orders/OrdersStats.vue'
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTags, faTasksAlt, faChartPie } from "@fal"
import TableInvoices from "@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue";
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue";


library.add(faTags, faTasksAlt, faChartPie)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: {}
    },
    backlog?: {}
    orders?: {}
    invoices?: {}
    delivery_notes?: {}
    mailshots?: {}
    stats?: {}
    history?: {}

}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: any = {
        stats: OrdersStats,
        orders: TableOrders,
        invoices: TableInvoices,
        delivery_notes: TableDeliveryNotes,
        history: TableHistories
    }

    return components[currentTab.value]
})


</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>
