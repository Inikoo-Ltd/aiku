<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import Button from '../Elements/Buttons/Button.vue'

    
const props = defineProps<{
    invoice_pay: {
        currency_code: string
        total_invoice: number
        total_refunds: number
        total_balance: number
        total_paid_in: number
        total_paid_out: {
            data: {}[]
        }
        total_need_to_pay: number
    }
}>()

const locale = inject('locale', aikuLocaleStructure)

</script>

<template>
    <dd class="relative w-full flex flex-col border px-2.5 py-1 rounded-md border-gray-300 overflow-hidden">
        <!-- Block: Corner label (fully paid) -->
        <!-- <Transition>
            <div v-if="Number(box_stats.information.pay_amount) <= 0" v-tooltip="trans('Fully paid')"
                class="absolute top-0 right-0 text-green-500 p-1 text-xxs">
                <div
                    class="absolute top-0 right-0 w-0 h-0 border-b-[25px] border-r-[25px] border-transparent border-r-green-500">
                </div>
                <FontAwesomeIcon icon='far fa-check' class='absolute top-1/2 right-1/2 text-white text-[8px]'
                    fixed-width aria-hidden='true' />
            </div>
        </Transition> -->

        <div v-tooltip="trans('Amount need to pay by customer')" class="text-sm w-fit">
            Invoice: {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_invoice)) }}
        </div>

        <div v-tooltip="trans('Amount need to pay by customer')" class="text-sm w-fit">
            Refunds: {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_refunds)) }}
            <!-- <span v-if="Number(box_stats.information.paid_amount) > 0" class='text-gray-400'>. Paid</span> -->
        </div>

        <div v-tooltip="trans('Amount need to pay by customer')" class="text-sm w-fit">
            Balance: {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_balance)) }}
            <!-- <span v-if="Number(box_stats.information.paid_amount) > 0" class='text-gray-400'>. Paid</span> -->
        </div>

        <div class="text-sm">
            {{ trans('Paid in') }}: {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_paid_in)) }}
        </div>

        <div class="text-xs">
            {{ trans('Pay out') }}:

            <ul class="list-disc list-inside">
                <li v-for="paid_out in invoice_pay.total_paid_out.data">
                    <span class="text-red-500">{{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(paid_out.payment_amount)) }}</span> ({{ paid_out.reference }})
                </li>
            </ul>
        </div>

        <div class="text-xs">
            {{ trans('Refund to pay left') }}: {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_need_to_refund)) }}
            <Button size="xxs" type="secondary">Pay Refund</Button>
        </div>

        <div class="text-xs">
            {{ trans('Total to pay') }}: {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_need_to_pay)) }}
            <Button size="xxs" type="secondary">Pay</Button>
        </div>
    </dd>
</template>