<script setup lang='ts'>
import { inject } from 'vue'
import { FieldOrderSummary } from '@/types/Pallet'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faQuestionCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faQuestionCircle)

const props = defineProps<{
    currency_code?: string
    order_summary: FieldOrderSummary[][] | {
        [key: string]: FieldOrderSummary[]
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

</script>

<template>
    <dl class="flex flex-col space-y-2 text-gray-500 rounded-lg">
        <template v-for="(summaryGroup, summaryRowIndex) in order_summary" :key="'fieldSummary' + summaryRowIndex">
            <div v-if="summaryGroup.length" class="pt-2 first:pt-0 pr-2 flex flex-col gap-y-2 first:border-t-0 border-t border-gray-200 ">
                <div v-for="fieldSummary in summaryGroup" class="grid grid-cols-7 gap-x-4 items-center justify-between">
                    <dt class="col-span-2 flex flex-col">
                        <div class="flex items-center leading-none">
                            <span>{{ fieldSummary.label }}</span>
                            <FontAwesomeIcon v-if="fieldSummary.information_icon" icon='fal fa-question-circle' v-tooltip="fieldSummary.information_icon" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                        </div>
                        <span v-if="fieldSummary.information" v-tooltip="fieldSummary.information" class="text-xs text-gray-400 truncate">{{ fieldSummary.information }}</span>
                    </dt>
                    <Transition name="spin-to-down">
                        <dd :key="fieldSummary.quantity" class="justify-self-end">{{ typeof fieldSummary.quantity === 'number' ? locale.number(fieldSummary.quantity) : null}}</dd>
                    </Transition>
                    <div class="relative col-span-4 justify-self-end font-medium overflow-hidden">
                        <Transition name="spin-to-right">
                            <dd :key="fieldSummary.price_total" class="" :class="fieldSummary.price_total === 'free' ? 'text-green-600 animate-pulse' : ''">
                                {{ locale.currencyFormat(currency_code || 'usd', fieldSummary.price_total || 0) }}
                            </dd>
                        </Transition>
                    </div>
                </div>
            </div>
        </template>

    </dl>
</template>
