<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import DashboardOld from '@/Components/DataDisplay/Dashboard/DashboardOld.vue'
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue"
import StatsBox from '@/Components/Stats/StatsBox.vue'

defineProps<{
    data: {
        dashboard_stats: {}
        statsBox: {
            title: string
            value: string
            icon: string
            color: string
        }[]
    }
    tab: string
}>()


</script>


<template>
    <div>
        <DashboardOld class="p-4"
            :dashboard="data.dashboard_stats"
        />

        <div class="flex justify-between gap-x-4 gap-y-4 px-4">
            <div class="w-full max-w-xl text-xl xh-[700px] mt-4 md:grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2">
                <StatsBox
                    v-for="(stat, idxStat) in data.data.statsBox"
                    :stat="stat"
                >
                    <!-- <template #background>
                        <div></div>
                    </template> -->
                </StatsBox>
            </div>
        </div>
    </div>
</template>
