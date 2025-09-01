<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 27 Jan 2025 20:05:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faTimesCircle } from "@fas"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import StatsBox from '@/Components/Stats/StatsBox.vue'


library.add(faCheckCircle, faTimesCircle )

defineProps<{
    pageHead: PageHeadingTS
    tabs: {
        current: string
        navigation: {}
    },
    title: string
    stats: {
        id: number
        label: string
        value: number
        change: number
        changeType: string
        icon: string
        color: string
        backgroundColor?: string
        is_negative?: boolean
        route: {
            name: string
            parameters: {}
        }
        metaRight?: {
            count: number
            icon: {
                icon: string
                class: string
                tooltip: string
            }
            route: routeType
            tooltip: string
        }
        metas?: {
            count: number
            icon: {
                icon: string
                class: string
                tooltip: string
            }
            route: routeType
            tooltip: string
        }[]
    }[]
}>()





</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <!-- Stats: box -->
    <div class="p-6">
        <dl class="grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <StatsBox
                v-for="(stat, idxStat) in stats"
                :stat="stat"
            >
            </StatsBox>
        </dl>
    </div>


</template>

