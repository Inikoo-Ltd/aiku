<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import StatsBox from '@/Components/Stats/StatsBox.vue'
import StatsBoxNegativeList from '@/Components/Stats/StatsBoxNegativeList.vue'
import { trans } from 'laravel-vue-i18n';
import { clone } from 'lodash';

const props = defineProps<{
    data: {
        dashboard_stats: {}
        statsBox: Record<string, {
            title: string
            value: string
            icon: string
            color: string
        }>
    }
    tab: string
}>()

const statsWithoutAdditional = Object.fromEntries(
  Object.entries(props.data.statsBox).filter(
    ([key]) => key !== 'additionalStatBox'
  )
);

const statsOnlyAdditional = Object.fromEntries(
  Object.entries(props.data.statsBox).filter(
    ([key]) => key === 'additionalStatBox'
  )
);

</script>


<template>
    <div>
        <div class="p-6 !pb-0">
            <span class="font-semibold"> {{ trans('Catalogue') }} </span>
            <dl class="pt-2 grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <StatsBox
                    v-for="(stat, idxStat) in statsWithoutAdditional"
                    :stat="stat"
                    :key="idxStat"
                />
            </dl>
        </div>
        <div v-if="statsOnlyAdditional.additionalStatBox" class="p-6">
            <span class="font-semibold"> {{ trans('Faulty Catalogue') }} </span>
            <div class="pt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5 gap-2">
                <StatsBoxNegativeList :stats="statsOnlyAdditional.additionalStatBox" />
            </div>
        </div>
    </div>
</template>
