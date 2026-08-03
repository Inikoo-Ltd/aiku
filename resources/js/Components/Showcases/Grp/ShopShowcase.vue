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
        price_exchanges?: {
            code: string
            name: string | null
            symbol: string | null
            is_major: boolean
            major: string | null
            exchange: number | null
        }[]
    }
    tab: string
}>()

const majorCurrencies = (props.data.price_exchanges || []).filter(currency => currency.is_major)
const minorCurrencies = (props.data.price_exchanges || []).filter(currency => !currency.is_major)

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
            <div class="flex flex-col lg:flex-row gap-6">
                <div class="flex-1">
                    <span class="font-semibold"> {{ trans('Catalogue') }} </span>
                    <dl class="pt-2 grid grid-cols-1 gap-2 lg:gap-5 sm:grid-cols-2"
                        :class="{ 'lg:grid-cols-4': !majorCurrencies.length }">
                        <StatsBox
                            v-for="(stat, idxStat) in statsWithoutAdditional"
                            :stat="stat"
                            :key="idxStat"
                        />
                    </dl>
                </div>
                <div v-if="majorCurrencies.length" class="lg:w-96 shrink-0">
                    <span class="font-semibold"> {{ trans('Currencies') }} </span>
                    <div class="pt-2 flex flex-col items-start gap-2 lg:gap-5">
                        <div v-for="major in majorCurrencies" :key="major.code"
                            class="rounded-lg border border-indigo-300 bg-indigo-50 px-4 py-3 w-full">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-lg">{{ major.code }}</span>
                                <span v-if="major.symbol" class="text-gray-500">{{ major.symbol }}</span>
                                <span class="text-xs text-gray-500">{{ major.name }}</span>
                                <span class="ml-auto rounded-full bg-indigo-600 text-white text-xs px-2 py-0.5">
                                    {{ trans('Major') }}
                                </span>
                            </div>
                            <div v-if="minorCurrencies.some(minor => minor.major === major.code)" class="mt-2 space-y-1 border-t border-indigo-200 pt-2">
                                <div v-for="minor in minorCurrencies.filter(minor => minor.major === major.code)" :key="minor.code"
                                    class="flex items-center justify-between text-sm gap-6">
                                    <span>
                                        <span class="font-medium">{{ minor.code }}</span>
                                        <span v-if="minor.name" class="text-gray-400 text-xs ml-1">{{ minor.name }}</span>
                                    </span>
                                    <span class="grid grid-cols-[auto_4.5rem_2.5rem] items-baseline gap-1 text-gray-600">
                                        <span class="text-xs text-gray-400">1 {{ major.code }} =</span>
                                        <span class="tabular-nums text-right">{{ minor.exchange }}</span>
                                        <span class="text-xs text-gray-400">{{ minor.code }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="statsOnlyAdditional.additionalStatBox" class="p-6">
            <span class="font-semibold"> {{ trans('Faulty Catalogue') }} </span>
            <div class="pt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 lg:gap-5 gap-2">
                <StatsBoxNegativeList :stats="statsOnlyAdditional.additionalStatBox" />
            </div>
        </div>
    </div>
</template>
