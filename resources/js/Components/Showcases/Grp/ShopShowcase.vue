<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
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
        <div class="p-6">
            <dl class="grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatsBox
                    v-for="(stat, idxStat) in data.statsBox"
                    :stat="stat"
                    :key="idxStat"
                />
            </dl>
        </div>
    </div>
</template>
