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

    }
    handleTabUpdate: Function
    payments: {
        id: number
        amount: number
        created_at: string
        payment_account: {
            type: string
            code: string
            name: string
        } | null
    }[]
}>()


const locale = inject('locale', aikuLocaleStructure)

const isLoadingPayWithBalance = ref(false)
const onPayWithBalance = () => {
    // Section: Submit
    router.post(
        route('grp.models.order.pay_order_with_balance', { order: props.order?.id }),
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
        <div v-if="Number(payAmount) > 0" class="mt-2 text-xs text-gray-500 font-light whitespace-nowrap px-2.5 mb-1.5">
            {{ trans('Balance') }}: {{ locale.currencyFormat(currencyCode, Number(balance)) }}
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
            isPaidOff || Number(payAmount) <= 0 ? 'bg-green-50 border-green-300 rounded-md' : 'text-red-600',
        ]">
    
            <!-- Section: Progress bar -->
            <div v-if="payments?.length" class="my-3">
                <div class="h-3 w-full bg-black/20 rounded-full relative overflow-hidden flex">
                    <div v-for="(payment, idx) in payments.sort((a, b) => new Date(a.created_at) - new Date(b.created_at))"
                        v-tooltip="trans('(:paymentName) Paid :paymentAmount at :datePayment', { paymentName: payment.payment_account?.name || 'Unknown', paymentAmount: locale.currencyFormat(currencyCode, Number(payment.amount)), datePayment: useFormatTime(payment.created_at, { formatTime: 'hm'}) })"
                        class="h-full opacity-50 hover:opacity-100"
                        :class="idx != payments.length - 1 ? 'border-r border-black/70' : ''"
                        :style="{
                            width: (Number(payment.amount)/Number(totalAmount))*100 + '%',
                            backgroundColor: useStringToHex(payment.payment_account?.code || 'gray')
                        }"
                    />
                </div>
            </div>
    
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
                        Need to pay {{ locale.currencyFormat(currencyCode, Number(payAmount)) }} of {{ locale.currencyFormat(currencyCode, Number(totalAmount)) }}
                    </div>
                </div>
            </div>
    
            <!-- Section: if fully paid -->
            <div v-if="Number(paidAmount) >= Number(totalAmount)" class="text-center relative w-full">
                <div @click="() => handleTabUpdate('payments')" v-tooltip="locale.currencyFormat(currencyCode, Number(paidAmount))" class="text-2xl font-bold text-green-600 hover:underline cursor-pointer">
                    {{ trans("Paid") }}
                    <FontAwesomeIcon v-tooltip="trans('Fully paid')" icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                </div>
            </div>
        </div>


        <slot name="default" />
    </dd>
</template>
