<script setup lang='ts'>
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { inject } from 'vue'
import CountUp from 'vue-countup-v3'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faDollarSign } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
library.add(faDollarSign)

const props = defineProps<{
    data: {
        number_orders: number
        total: number
    }
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class="flex gap-x-3 gap-y-4 p-4 flex-wrap">
        <!-- Box: Total Orders -->
        <div class="bg-gray-50 min-w-64 border border-gray-300 rounded-md p-6">
            <div class="flex justify-between items-center mb-1">
                <div class="">{{ trans("Total Orders") }}</div>
                <FontAwesomeIcon icon='fal fa-shopping-cart ' class=' text-xl text-gray-400' fixed-width aria-hidden='true' />
            </div>

            <div class="mb-1 text-2xl font-semibold">
                <CountUp
                    :endVal="data.number_orders"
                    :duration="1.5"
                    :scrollSpyOnce="true"
                    :options="{
                        formattingFn: (value: number) => locale.number(value)
                    }"
                />
            </div>
        </div>

        <!-- Box: Total Income -->
        <div class="bg-gray-50 min-w-64 border border-gray-300 rounded-md p-6">
            <div class="flex justify-between items-center mb-1">
                <div class="">{{ trans("Total income") }}</div>
                <FontAwesomeIcon icon='fal fa-dollar-sign ' class=' text-xl text-gray-400' fixed-width aria-hidden='true' />
            </div>

            <div class="mb-1 text-2xl font-semibold">
                <CountUp
                    :endVal="data.total"
                    :duration="1.5"
                    :scrollSpyOnce="true"
                    :options="{
                        formattingFn: (value: number) => locale.currencyFormat('', value)
                    }"
                />
            </div>
        </div>

        <!-- Box: Customer Lifetime Value -->
        <div class="bg-gray-50 min-w-64 border border-gray-300 rounded-md p-6">
            <div class="flex justify-between items-center mb-1">
                <div class="">{{ trans("Lifetime Value") }}</div>
                <FontAwesomeIcon icon='fal fa-dollar-sign ' class=' text-xl text-gray-400' fixed-width aria-hidden='true' />
            </div>

            <div class="mb-1 text-2xl font-semibold">
                <CountUp
                    :endVal="data.total"
                    :duration="1.5"
                    :scrollSpyOnce="true"
                    :options="{
                        formattingFn: (value: number) => locale.currencyFormat('', value)
                    }"
                />
            </div>
        </div>
    </div>
</template>