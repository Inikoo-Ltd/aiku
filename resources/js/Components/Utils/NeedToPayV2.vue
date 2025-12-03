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
    <dd class="relative w-full flex flex-col border px-2.5 py-3 rounded-md  xoverflow-hidden"
        :class="[
            isPaidOff || Number(payAmount) <= 0 ? 'bg-green-50 border-green-300 pr-5' : 'bg-red-100 shadow border-red-500',
        ]"
    >
        <div v-if="Number(payAmount) > 0" class="text-xs text-gray-500 font-light whitespace-nowrap">
            {{ trans('Balance') }}: {{ locale.currencyFormat(currencyCode, Number(balance)) }}
            <Button
                v-if="Number(balance) >= Number(payAmount) && Number(payAmount) > 0"
                size="xxs"
                :label="trans('Pay with balance')"
                type="secondary"
                @click="() => onPayWithBalance()"
                :loading="isLoadingPayWithBalance"
            />
        </div>

        <!-- Section: if 0 paid -->
        <div v-if="Number(paidAmount) === 0">
            <!-- Section: Progress bar -->
            <div class="my-3">
                <div v-tooltip="trans(':paidAmount remaining of :totalAmount', { paidAmount: locale.currencyFormat(currencyCode, Number(paidAmount)), totalAmount: locale.currencyFormat(currencyCode, Number(totalAmount)) })"
                    class="h-2 w-full bg-black/20 rounded-full relative overflow-hidden">
                    <div class="bg-green-500 absolute h-full shimmer"
                        :style="{
                            width: (Number(paidAmount)/Number(totalAmount))*100 + '%'
                        }"
                    />
                </div>
            </div>

            <!-- Section: Remaining -->
            <div class="text-center relative">
                <div class="text-2xl font-bold">
                    {{ trans("Unpaid") }}
                    <FontAwesomeIcon v-tooltip="trans('Not fully paid yet')" icon="fas fa-times-circle" class="text-red-600" fixed-width aria-hidden="true" />
                </div>
            
                <div class="opacity-70">
                    remaining of {{ locale.currencyFormat(currencyCode, Number(totalAmount)) }}
                </div>
            </div>
        </div>

        <!-- Section: if partially paid -->
        <div v-else-if="Number(payAmount) > 0">
            <!-- Section: Progress bar -->
            <div class="my-3">
                <div v-tooltip="trans(':paidAmount remaining of :totalAmount', { paidAmount: locale.currencyFormat(currencyCode, Number(paidAmount)), totalAmount: locale.currencyFormat(currencyCode, Number(totalAmount)) })"
                    class="h-2 w-full bg-black/20 rounded-full relative overflow-hidden">
                    <div class="bg-green-500 absolute h-full shimmer"
                        :style="{
                            width: (Number(paidAmount)/Number(totalAmount))*100 + '%'
                        }"
                    />
                </div>
            </div>

            <!-- Section: Remaining -->
            <div class="text-center relative">
                <div class="text-2xl font-bold">
                    {{ trans("Unpaid") }}
                    <FontAwesomeIcon v-tooltip="trans('Not fully paid yet')" icon="fas fa-times-circle" class="text-red-600" fixed-width aria-hidden="true" />
                </div>
            
                <div class="opacity-70">
                    {{ locale.currencyFormat(currencyCode, Number(payAmount)) }} remaining of {{ locale.currencyFormat(currencyCode, Number(totalAmount)) }}
                </div>
            </div>
        </div>
        
        <!-- Section: if fully paid -->
        <div v-if="Number(paidAmount) >= Number(totalAmount)" class="text-center relative">
            <div @click="() => handleTabUpdate('payments')" v-tooltip="locale.currencyFormat(currencyCode, Number(paidAmount))" class="text-2xl font-bold text-green-600 hover:underline cursor-pointer">
                {{ trans("Paid") }}
                <FontAwesomeIcon v-tooltip="trans('Fully paid')" icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
            </div>
        
            <!-- <div class="opacity-70 text-xs">
                {{ trans("Paid amount") }}: {{ locale.currencyFormat(currencyCode, Number(paidAmount)) }}
            </div> -->
        </div>


        <slot name="default" />
    </dd>
</template>
