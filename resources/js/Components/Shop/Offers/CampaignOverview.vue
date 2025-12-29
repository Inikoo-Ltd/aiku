<script setup lang='ts'>

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDollarSign } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import CountUp from 'vue-countup-v3'
library.add(faDollarSign)

const props = defineProps<{
    data: {
        stats: {
            label: string
            icon: string
            value: number
            meta: {
                value: number
                label: string
            }
        }[]
    }
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class="flex">
        <div class="w-full qwezxc flex justify-center items-center h-fit py-12">
            <div class="bg-green-500/20 px-1 py-0.5 text-xs border flex items-center border-green-500/50 rounded-sm w-fit text-green-700">
                <svg class="svg-inline--fa fa-badge-percent fa-fw text-green-500 text-sm align-middle" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="badge-percent" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path class="" fill="currentColor" d="M512 256c0-37.7-23.7-69.9-57.1-82.4 14.7-32.4 8.8-71.9-17.9-98.6-26.7-26.7-66.2-32.6-98.6-17.9C325.9 23.7 293.7 0 256 0s-69.9 23.7-82.4 57.1c-32.4-14.7-72-8.8-98.6 17.9-26.7 26.7-32.6 66.2-17.9 98.6C23.7 186.1 0 218.3 0 256s23.7 69.9 57.1 82.4c-14.7 32.4-8.8 72 17.9 98.6 26.6 26.6 66.1 32.7 98.6 17.9 12.5 33.3 44.7 57.1 82.4 57.1s69.9-23.7 82.4-57.1c32.6 14.8 72 8.7 98.6-17.9 26.7-26.7 32.6-66.2 17.9-98.6 33.4-12.5 57.1-44.7 57.1-82.4zm-320-96c17.67 0 32 14.33 32 32s-14.33 32-32 32-32-14.33-32-32 14.33-32 32-32zm12.28 181.65c-6.25 6.25-16.38 6.25-22.63 0l-11.31-11.31c-6.25-6.25-6.25-16.38 0-22.63l137.37-137.37c6.25-6.25 16.38-6.25 22.63 0l11.31 11.31c6.25 6.25 6.25 16.38 0 22.63L204.28 341.65zM320 352c-17.67 0-32-14.33-32-32s14.33-32 32-32 32 14.33 32 32-14.33 32-32 32z"></path></svg>
                <span class="ml-0.5 font-bold mr-1">10%</span> First Order Bonus
            </div>
        </div>

        <div class="w-[780px] flex gap-x-3 gap-y-4 p-4 flex-wrap qwezxc">
            <div v-for="stat in data.stats" class="bg-gray-50 min-w-64 border border-gray-300 rounded-md p-6">
                <div class="flex justify-between items-center mb-1">
                    <div class="">{{ stat.label }}</div>
                    <FontAwesomeIcon :icon="stat.icon" class=" text-xl text-gray-400" fixed-width aria-hidden="true" />
                </div>
                <div class="mb-1 text-2xl font-semibold">
                    <CountUp
                        :endVal="stat.value"
                        :duration="1.5"
                        :scrollSpyOnce="true"
                        :options="{
                            formattingFn: (value: number) => locale.number(value)
                        }"
                    />
                </div>
                <!-- <div class="text-sm text-gray-400">{{ stat.meta.value }} {{ stat.meta.label }}</div> -->
            </div>
        </div>
    </div>
</template>