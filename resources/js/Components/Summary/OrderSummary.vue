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
    size?: 'sm'
}>()

const locale = inject('locale', aikuLocaleStructure)

</script>

<template>
    <dl class="flex flex-col text-gray-500 rounded-lg" :class="size === 'sm' ? 'text-xs' : 'space-y-2'">
        <template v-for="(summaryGroup, summaryRowIndex) in order_summary" :key="'fieldSummary' + summaryRowIndex">
            <div v-if="summaryGroup.length" class="first:pt-0 pr-2 flex flex-col first:border-t-0 border-t border-gray-200 " :class="size === 'sm' ? 'gap-y-1 pt-1 pb-1.5' : 'gap-y-2 pt-2'">
                <div v-for="fieldSummary in summaryGroup" class="grid grid-cols-7 gap-x-4 items-center justify-between">
                    <slot :name="'cell_' + fieldSummary?.slot_name + '_1'" :fieldSummary="fieldSummary">
                        <dt class="col-span-3 flex flex-col">
                            <div class="flex items-center leading-none" :class="fieldSummary.label_class">
                                <span>{{ fieldSummary.label }}</span>
                                <FontAwesomeIcon v-if="fieldSummary.information_icon" icon='fal fa-question-circle' v-tooltip="fieldSummary.information_icon" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                            </div>
                            <span v-if="fieldSummary.information" v-tooltip="fieldSummary.information" class="text-xs text-gray-400 truncate">{{ fieldSummary.information }}</span>
                        </dt>
                    </slot>

                    <Transition name="spin-to-down">
                        <dd :key="fieldSummary.quantity" class="justify-self-end">{{ typeof fieldSummary.quantity === 'number' ? locale.number(fieldSummary.quantity) : null}}</dd>
                    </Transition>

                    <slot :name="'cell_' + fieldSummary?.slot_name + '_3'" :fieldSummary="fieldSummary">
                        <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                            <Transition name="spin-to-right">
                                <dd :key="fieldSummary.price_total" class="" :class="[fieldSummary.price_total_class, fieldSummary.price_total === 'free' ? 'text-green-600 animate-pulse' : '']">
                                    {{ locale.currencyFormat(currency_code, fieldSummary.price_total || 0) }}
                                </dd>
                            </Transition>
                        </div>
                    </slot>
                </div>
            </div>
        </template>

    </dl>
</template>
