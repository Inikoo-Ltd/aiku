<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import Button from '../Elements/Buttons/Button.vue'
import { ref } from 'vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureMultiselect from '../Pure/PureMultiselect.vue'
import PureInput from '../Pure/PureInput.vue'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { routeType } from '@/types/route'
import { router } from '@inertiajs/vue3'
import { watch } from 'vue'
import InputNumber from 'primevue/inputnumber'

    
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
    routes: {
        submit_route: routeType
        fetch_payment_accounts_route: routeType

    }
}>()

const locale = inject('locale', aikuLocaleStructure)

const errorInvoicePayment = ref({
    payment_method: null,
    payment_amount: null,
    payment_reference: null
})

const paymentData = ref({
    payment_method: null as number | null,
    payment_amount: 0 as number | null,
    payment_reference: ''
})


// Section: Payment invoice
const listPaymentMethod = ref([])
const isLoadingFetch = ref(false)
const fetchPaymentMethod = async () => {
    try {
        isLoadingFetch.value = true
        const { data } = await axios.get(route(props.routes.fetch_payment_accounts_route.name, props.routes.fetch_payment_accounts_route.parameters))
        listPaymentMethod.value = data.data
    } catch (error) {
        notify({
            title: trans('Something went wrong'),
            text: trans('Failed to fetch payment method list'),
            type: 'error',
        })
    }
    finally {
        isLoadingFetch.value = false
    }
}
const isOpenModalPayment = ref(false)
const isLoadingPayment = ref(false)
const errorPaymentMethod = ref<null | unknown>(null)
const onSubmitPayment = () => {
    router[props.routes.submit_route.method || 'post'](
        route(props.routes.submit_route.name, {
            ...props.routes.submit_route.parameters,
            paymentAccount: paymentData.value.payment_method
        }),
        {
            amount: paymentData.value.payment_amount,
            reference: paymentData.value.payment_reference,
            status: 'success',
            state: 'completed',
        },
        {
            onStart: () => isLoadingPayment.value = true,
            onFinish: () => {
                isLoadingPayment.value = false,
                isOpenModalPayment.value = false,
                notify({
                    title: trans('Success'),
                    text: 'Successfully add payment invoice',
                    type: 'success',
                })
            },
            onSuccess: () => {
                paymentData.value.payment_method = null,
                paymentData.value.payment_amount = 0,
                paymentData.value.payment_reference = ''
                isOpenModalInvoice.value = false
            },
            onError: (error) => {
                errorPaymentMethod.value = error
            }
        }
    )
}

watch(paymentData, () => {
    if (errorPaymentMethod.value) {
        errorPaymentMethod.value = null
    }
})

const isOpenModalInvoice = ref(false)

</script>

<template>
    <dd class="relative w-full flex flex-col border px-2.5 py-1 rounded-md border-gray-300 overflow-hidden">
        <!-- Block: Corner label (fully paid) -->
        <!-- <Transition>
            <div v-if="Number(invoice_pay.total_need_to_pay) <= 0" v-tooltip="trans('Fully paid')"
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
            <Button @click="() => (isOpenModalInvoice = true, fetchPaymentMethod())" size="xxs" type="secondary">Pay</Button>
        </div>

        <Modal :isOpen="isOpenModalInvoice" @onClose="isOpenModalInvoice = false" width="w-[600px]">
            <div class="isolate bg-white px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Invoice Payment') }}</h2>
                    <p class="text-xs leading-5 text-gray-400">
                        {{ trans('Information about payment from customer') }}
                    </p>
                </div>

                <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div class="col-span-2">
                        <label for="first-name" class="block text-sm font-medium leading-6">
                            <span class="text-red-500">*</span> {{ trans('Select payment method') }}
                        </label>
                        <div class="mt-1" :class="errorInvoicePayment.payment_method ? 'errorShake' : ''">
                            <PureMultiselect
                                v-model="paymentData.payment_method"
                                @update:modelValue="() => errorInvoicePayment.payment_method = null"
                                @input="() => errorInvoicePayment.payment_method = null"
                                :options="listPaymentMethod"
                                :isLoading="isLoadingFetch"
                                label="name"
                                valueProp="id"
                                required
                                caret
                            />
                        </div>
                        <Transition name="spin-to-down">
                            <p v-if="errorInvoicePayment.payment_method" class="text-red-500 italic text-sm mt-1">*{{ errorInvoicePayment.payment_method }}</p>
                        </Transition>
                    </div>

                    <div class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">{{ trans('Payment amount') }}</label>
                        <div class="mt-1" :class="errorInvoicePayment.payment_amount ? 'errorShake' : ''">
                            <!-- <PureInputNumber v-model="paymentData.payment_amount" /> -->
                            <InputNumber
                                v-model="paymentData.payment_amount" 
                                @update:modelValue="(e) => paymentData.payment_amount = e"
                                @input="(e) => paymentData.payment_amount = e.value"
                                buttonLayout="horizontal" 
                                :min="0"
                                :max="invoice_pay.total_need_to_pay || undefined"
                                :maxFractionDigits="2"
                                style="width: 100%"
                                inputClass="border border-gray-300"
                                :inputStyle="{
                                    fontSize: '14px',
                                    paddingTop: '10px',
                                    paddingBottom: '10px',
                                    width: '50px',
                                    background: 'transparent',
                                }"
                                mode="currency"
                                :currency="invoice_pay.currency_code"
                            />
                        </div>
                        <div class="space-x-1">
                            <span class="text-xxs text-gray-500">{{ trans('Need to pay') }}: {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_need_to_pay)) }}</span>
                            <Button @click="() => paymentData.payment_amount = invoice_pay.total_need_to_pay" :disabled="paymentData.payment_amount === invoice_pay.total_need_to_pay" type="tertiary" label="Pay all" size="xxs" />
                        </div>
                    </div>

                    <div class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">{{ trans('Reference') }}</label>
                        <div class="mt-1">
                            <PureInput v-model="paymentData.payment_reference" placeholder="#000000"/>
                        </div>
                    </div>

                    <!-- <div class="col-span-2">
                        <label for="message" class="block text-sm font-medium leading-6">Note</label>
                        <div class="mt-1">
                            <PureTextarea
                                v-model="paymentData.payment_reference"
                                name="message"
                                id="message" rows="4"
                            />
                        </div>
                    </div> -->
                </div>

                <div class="mt-6 mb-4 relative">
                    <div v-if="!(!!paymentData.payment_method)" @click="() => errorInvoicePayment.payment_method = trans('Payment method can\'t empty')" class="absolute inset-0" />
                    <Button @click="() => onSubmitPayment()" :label="trans('Submit')" :disabled="!(!!paymentData.payment_method)" :loading="isLoadingPayment" full />
                    <Transition name="spin-to-down">
                        <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{ errorPaymentMethod }}</p>
                    </Transition>
                </div>
            </div>
        </Modal>
    </dd>
</template>