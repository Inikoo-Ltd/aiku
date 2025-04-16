<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"

import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import RecurringBillTransactions from '@/Pages/Grp/Org/Fulfilment/RecurringBillTransactions.vue'
import BoxStatsRecurringBills from '@/Components/Fulfilment/BoxStatsRecurringBills.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import TableUserRequestLogs from "@/Components/Tables/Grp/SysAdmin/TableUserRequestLogs.vue"

import StartEndDate from '@/Components/Utils/StartEndDate.vue'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { BoxStats } from '@/types/Pallet'


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    status_rb: string
    transactions: {}
    timeline_rb: {
        start_date: string
        end_date: string
    }
    box_stats: BoxStats
    history: {},
    currency:{}

}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {

    const components: Component = {
        transactions: RecurringBillTransactions,
        history: TableHistories,
        request_logs: TableUserRequestLogs,
    }

    return components[currentTab.value]

})

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="grid grid-cols-2">
        <div class="py-4 px-3">
            <div class="flex flex-col justify-center h-full w-full rounded-md px-4 py-2"
                :class="[status_rb === 'current' ? 'bg-green-100 ring-1 ring-green-500 text-green-700' : 'bg-gray-200 ring-1 ring-gray-500 text-gray-700']"
            >
                <div class="text-xs">Status</div>
                <div class="font-semibold capitalize">{{ status_rb }}</div>
            </div>
        </div>
        <div class="py-1 px-3 flex items-center">
            <StartEndDate :isEndDateNotEditable="true" :startDate="timeline_rb.start_date" :endDate="timeline_rb.end_date" />
        </div>
    </div>

    <BoxStatsRecurringBills :boxStats="box_stats" :currency="currency" />

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
</template>
