<script setup lang="ts">
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, ref } from 'vue'
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

import { faTrash as falTrash, faEdit } from '@fal'
import { faCircle, faTrash, faDiamond } from '@fas'
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'
import { Currency } from '@/types/LayoutRules'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
library.add(faCircle, faTrash, faDiamond, falTrash, faEdit)

const props = defineProps<{
    data: {
        name: string
        code: string
        price: number
        unit: string
        units: number
        currency: Currency
    }
}>()


const locale = inject('locale', aikuLocaleStructure)

const stats = [
    { name: '2024', stat: '71,897', previousStat: '70,946', change: '12%', changeType: 'increase' },
    { name: '2023', stat: '58.16%', previousStat: '56.14%', change: '2.02%', changeType: 'increase' },
    { name: '2022', stat: '24.57%', previousStat: '28.62%', change: '4.05%', changeType: 'decrease' },
    { name: '2021', stat: '71,897', previousStat: '70,946', change: '12%', changeType: 'increase' },
    { name: '2020', stat: '58.16%', previousStat: '56.14%', change: '2.02%', changeType: 'increase' },
    { name: '2019', stat: '24.57%', previousStat: '28.62%', change: '4.05%', changeType: 'decrease' },
]


</script>


<template>
    <!-- <pre>{{ data }}</pre> -->
    <div class="grid gap-x-1 gap-y-4">
        <div class="p-5 space-y-5 w-full max-w-[500px]">

            <!-- Order summary -->
            <section aria-labelledby="summary-heading"
                class="border border-gray-200 rounded-lg lg:mt-0">
                <div class="bg-gray-100 px-6 py-4 border-b border-gray-200">
                    <h2 id="summary-heading" class="text-lg font-medium ">
                        {{ data?.name }}
                        <FontAwesomeIcon v-if="data?.is_pallet_handling" v-tooltip="trans('Special service')" icon="fas fa-diamond" class="text-teal-500 text-sm" fixed-width aria-hidden="true" />
                    </h2>
                    <div v-tooltip="trans('Date created')" class="text-sm text-gray-400 w-fit italic">
                        {{ useFormatTime(data.created_at) }}
                    </div>
                </div>

                <dl class="space-y-4 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <dt class="text-sm">{{ trans('Code') }}</dt>
                        <dd class="text-sm font-medium">{{ data?.code }}</dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-sm">{{ trans('Price') }}</dt>
                        <dd class="text-sm font-medium">{{ locale.currencyFormat(data?.currency?.code, data?.price)}}</dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-sm">{{ trans('Unit') }}</dt>
                        <dd class="text-sm font-medium">{{ data?.unit }}</dd>
                    </div>

                    <div class="flex items-center justify-between">
                        <dt class="text-sm">{{ trans('Units') }}</dt>
                        <dd class="text-sm font-medium">{{ data?.units }}</dd>
                    </div>
<!-- 
                    <div class="flex items-center justify-between">
                        <dt class="text-sm">{{ trans('Price') }}</dt>
                        <dd class="text-sm font-medium text-right">
                            {{ locale.currencyFormat('usd', data?.product?.data?.price) }}
                            <span class="font-light">margin (--)</span>
                        </dd>
                    </div> -->

                    <!-- <div class="flex items-center justify-between">
                        <dt class="text-sm">RRP</dt>
                        <dd class="text-sm font-medium text-right">--- <span class="font-light">margin (--)</span></dd>
                    </div> -->
                </dl>
            </section>
        </div>

        <!-- Revenue -->
        <div v-if="false" class="pt-8 p-4 md:col-span-3">
            <h3 class="text-base font-semibold leading-6">All sales since: Mon 20 August 2007</h3>
            <dl class="mt-5 grid grid-cols-1 overflow-hidden rounded bg-white md:grid-cols-3 md:gap-x-2 md:gap-y-4">
                <div v-for="item in stats" :key="item.name" class="px-4 py-5 sm:p-6 border border-gray-200 rounded-md">
                    <dt class="text-base font-normal">{{ item.name }}</dt>
                    <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                        <div class="flex items-baseline text-2xl font-semibold text-indigo-600">
                            {{ item.stat }}
                            <span class="ml-2 text-sm font-medium text-gray-500">from {{ item.previousStat }}</span>
                        </div>
                        <div
                            :class="[item.changeType === 'increase' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800', 'inline-flex items-baseline rounded-full px-2.5 py-0.5 text-sm font-medium md:mt-2 lg:mt-0']">
                            <!-- <ArrowUpIcon v-if="item.changeType === 'increase'"
                                class="-ml-1 mr-0.5 h-5 w-5 shrink-0 self-center text-green-500"
                                aria-hidden="true" />
                            <ArrowDownIcon v-else class="-ml-1 mr-0.5 h-5 w-5 shrink-0 self-center text-red-500"
                                aria-hidden="true" /> -->
                            <span class="sr-only"> {{ item.changeType === 'increase' ? 'Increased' : 'Decreased' }} by
                            </span>
                            {{ item.change }}
                        </div>
                    </dd>
                </div>
            </dl>
        </div>
        <!-- <pre>{{ data?.product }}</pre> -->
    </div>


</template>
