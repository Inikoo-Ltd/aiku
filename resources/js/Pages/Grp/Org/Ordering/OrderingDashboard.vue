<script setup lang="ts">
import { ref, provide } from "vue"
import { Head } from '@inertiajs/vue3'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faTimesCircle } from "@fas"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import StatsBox from '@/Components/Stats/StatsBox.vue'
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { Intervals } from "@/types/Components/Dashboard"

library.add(faCheckCircle, faTimesCircle)

interface Stat {
    id?: number
    label: string
    value: number
    subtitle?: string
    change?: number
    changeType?: string
    icon: string
    color?: string
    backgroundColor?: string
    is_negative?: boolean
    route?: {
        name: string
        parameters: {}
    }
    metaRight?: {
        count: number
        icon: { icon: string; class: string; tooltip: string }
        route: routeType
        tooltip: string
    }
    metas?: {
        count: number
        icon: { icon: string; class: string; tooltip: string }
        route: routeType
        tooltip: string
    }[]
}

defineProps<{
    pageHead: PageHeadingTypes
    title: string
    intervals: Intervals
    excess_orders: Stat
    stats: Stat[]
}>()

const isLoadingOnTable = ref(false)
provide("isLoadingOnTable", isLoadingOnTable)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="px-6 pt-4 pb-2">
        <dl class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
            <StatsBox :stat="excess_orders" />
        </dl>
    </div>

    <div class="px-6">
        <DashboardSettings
            :intervals="intervals"
            :settings="{}"
            currentTab="ordering"
        />
    </div>

    <div class="relative px-6 pt-2 pb-6">
        <div v-if="isLoadingOnTable" class="absolute inset-0 bg-white/50 flex items-center justify-center z-20 rounded">
            <LoadingIcon class="text-indigo-500 text-3xl" />
        </div>
        <dl class="grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <StatsBox v-for="(stat, idx) in stats" :key="idx" :stat="stat" />
        </dl>
    </div>
</template>
