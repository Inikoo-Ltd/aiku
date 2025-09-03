<script setup lang='ts'>
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faCheck)

const props = defineProps<{
    payAmount?: number
    paidAmount?: number
    totalAmount: number
    currencyCode?: string
    isPaidOff?: boolean
}>()

const locale = inject('locale', aikuLocaleStructure)

</script>
<template>
    <dd class="relative w-full flex flex-col border px-2.5 py-1 rounded-md  overflow-hidden"
        :class="[
            isPaidOff || Number(payAmount) <= 0 ? 'bg-green-50 border-green-300' : 'bg-white border-gray-300',
        ]"
    >
        <!-- Block: Corner label (fully paid) -->
        <Transition>
            <div v-if="isPaidOff || Number(payAmount) <= 0" v-tooltip="trans('Fully paid')"
                class="absolute top-0 right-0 text-green-500 p-1 text-xxs">
                <div
                    class="absolute top-0 right-0 w-0 h-0 border-b-[25px] border-r-[25px] border-transparent border-r-green-500">
                </div>
                <FontAwesomeIcon icon='far fa-check' class='absolute top-1/2 right-1/2 text-white text-[8px]'
                    fixed-width aria-hidden='true' />
            </div>
        </Transition>

        <div v-tooltip="trans('Amount need to pay by customer')" class="text-sm w-fit">
            {{ locale.currencyFormat(currencyCode, Number(totalAmount)) }}
            <span v-if="Number(paidAmount) > 0" class='text-gray-400'>. {{ trans("Paid") }}</span>
        </div>

        <div v-if="paidAmount !== undefined && payAmount !== 0" class="text-xs text-gray-500 font-light">
            {{ trans('Paid') }}: {{ locale.currencyFormat(currencyCode || 'usd', Number(paidAmount)) }}
        </div>
        
        <div v-if="paidAmount !== undefined && payAmount !== 0" class="text-xs text-gray-500 font-light">
            {{ trans('Need to pay') }}: {{ locale.currencyFormat(currencyCode || 'usd', Number(payAmount)) }}
        </div>

        <slot name="default" />
    </dd>
</template>
