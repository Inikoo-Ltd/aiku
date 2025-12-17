<script setup lang='ts'>
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { inject, ref } from 'vue'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck, faEllipsisV } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '../Elements/Buttons/Button.vue'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'
import ButtonWithLink from '../Elements/Buttons/ButtonWithLink.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { useStringToHex } from '@/Composables/useStringToHex'

library.add(faCheck, faEllipsisV)

const props = defineProps<{
    payAmount?: number
    paidAmount?: number
    totalAmount: number
    currencyCode: string
    isPaidOff?: boolean
    balance: number
    toBePaidBy: {
        value: string
        label: string
    }
    order: {
        id: number
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const isLoadingPayWithBalance = ref(false)
const onPayWithBalance = () => {
    // Section: Submit
    router.post(
        route('retina.models.order.pay_with_balance_after_submitted', { order: props.order?.id }),
        {
            data: 'qqq'
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingPayWithBalance.value = true
            },
            onSuccess: () => {
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to pay order with customer balance"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingPayWithBalance.value = false
            },
        }
    )
}
</script>

<template>
    <dd class="relative w-full flex flex-col xpy-3">
        <!-- Section: Balance (pay with balance) -->
        <div v-if="Number(payAmount) > 0" class="mt-2 text-sm text-gray-500 font-light whitespace-nowrap px-2.5 mb-1.5">
            {{ trans('Current balance') }}: {{ locale.currencyFormat(currencyCode, Number(balance)) }}
            <Button
                v-if="Number(balance) >= Number(payAmount) && Number(payAmount) > 0"
                size="xxs"
                :label="trans('Pay with balance')"
                xtype="secondary"
                @click="() => onPayWithBalance()"
                :loading="isLoadingPayWithBalance"
            />
        </div>

        <!-- Section: background green/red -->
        <div class="px-2.5 pt-1 pb-2" :class="[
            isPaidOff || Number(payAmount) <= 0 ? 'bg-green-50 border-green-300 rounded-md' : 'bg-red-100',
        ]">    
            <!-- Section: if 0 paid -->
            <div v-if="Number(paidAmount) === 0">
    
                <!-- Section: Remaining -->
                <div class="text-center relative">
                    <div class="text-lg font-bold">
                        <span v-if="toBePaidBy?.value">{{ trans("Waiting :toBePaid", { toBePaid: toBePaidBy?.label }) }}</span>
                        <span v-else>
                            {{ trans("Unpaid") }}
                        </span>
                        <!-- <FontAwesomeIcon v-tooltip="trans('Not fully paid yet')" icon="fas fa-times-circle" class="text-red-600" fixed-width aria-hidden="true" /> -->
                    </div>
    
                    <div class="opacity-70">
                        {{ trans("Total to pay") }}: {{ locale.currencyFormat(currencyCode, Number(totalAmount)) }}
                    </div>
                </div>
            </div>
    
            <!-- Section: if partially paid -->
            <div v-else-if="Number(payAmount) > 0">
                <!-- Section: Remaining -->
                <div class="text-center relative">
                    <div class="text-lg font-bold">
                        <span v-if="toBePaidBy?.value">{{ trans("Waiting :toBePaid", { toBePaid: toBePaidBy?.label }) }}</span>
                        <span v-else>
                            {{ trans("Unpaid") }}
                        </span>
                        <!-- <FontAwesomeIcon v-tooltip="trans('Not fully paid yet')" icon="fas fa-times-circle" class="text-red-600" fixed-width aria-hidden="true" /> -->
                    </div>
    
                    <div class="opacity-70">
                        {{ trans("Need to pay :payAmount of :totalAmount", { payAmount: locale.currencyFormat(currencyCode, Number(payAmount)), totalAmount: locale.currencyFormat(currencyCode, Number(totalAmount)) }) }}
                    </div>
                </div>
            </div>
    
            <!-- Section: if fully paid -->
            <div v-if="Number(paidAmount) >= Number(totalAmount)" class="text-center relative w-full">
                <div v-tooltip="locale.currencyFormat(currencyCode, Number(paidAmount))" class="text-2xl font-bold text-green-600">
                    {{ trans("Paid") }}
                    <FontAwesomeIcon v-tooltip="trans('Fully paid')" icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                </div>
            </div>
        </div>


        <slot name="default" />
    </dd>
</template>
