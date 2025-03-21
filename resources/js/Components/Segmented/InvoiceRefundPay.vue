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
import { Link, router } from '@inertiajs/vue3'
import { watch } from 'vue'
import InputNumber from 'primevue/inputnumber'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCheck)

    
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
const isOpenModalInvoice = ref(false)
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
                isLoadingPayment.value = false
            },
            onSuccess: () => {
                paymentData.value.payment_method = null,
                paymentData.value.payment_amount = 0,
                paymentData.value.payment_reference = ''
                isOpenModalInvoice.value = false
                notify({
                    title: trans('Success'),
                    text: 'Successfully add payment invoice',
                    type: 'success',
                })
            },
            onError: (error) => {
                errorPaymentMethod.value = error
                notify({
                    title: trans('Something went wrong'),
                    text: error.message,
                    type: 'error',
                })
            }
        }
    )
}

watch(paymentData, () => {
    if (errorPaymentMethod.value) {
        errorPaymentMethod.value = null
    }
})




// Section: Payment Refund
const isOpenModalRefund = ref(false)
const listPaymentRefund = ref([
    {
        label: trans("Refund money to customer's credit balance"),
        value: 'credit_balance',
    },
    {
        label: trans("Refund money to payment method of the invoice"),
        value: 'invoice_payment_method',
    }
])

const paymentRefund = ref({
    payment_method: null as string | null,
    payment_account: null as number | null,
    payment_amount: 0 as number | null,
    //   payment_reference: ""
})
const isLoadingPaymentRefund = ref(false)
const errorPaymentMethodRefund = ref<null | unknown>(null)
const onSubmitPaymentRefund = () => {

    let url
    if (paymentRefund.value.payment_method === 'credit_balance') {
        url = route('grp.models.refund.refund_to_credit', {
            refund: props.invoice_pay.invoice_id,
        })
    } else {
        url = route('grp.models.refund.refund_to_payment_account', {
            refund: props.invoice_pay.invoice_id,
            paymentAccount: paymentRefund.value.payment_account
        })
    }

    try {
        router.post(
            url,
            {
                amount: paymentRefund.value.payment_amount,
            },
            {
                onStart: () => isLoadingPayment.value = true,
                onFinish: () => {
                    isLoadingPayment.value = false,
                    isOpenModalRefund.value = false
                },
                onSuccess: () => {
                    paymentRefund.value.payment_account = null,
                    paymentRefund.value.payment_amount = 0,
                    notify({
                        title: trans("Success"),
                        text: "Successfully add payment invoice",
                        type: "success"
                    })
                    // paymentRefund.value.payment_reference = ""
                },
                onError: (error) => {
                    errorPaymentMethodRefund.value = error
                    notify({
                        title: trans("Something went wrong"),
                        text: error.message,
                        type: "error",
                    })
                },
                preserveScroll: true,
            }
        )

    } catch (error: unknown) {
        errorPaymentMethodRefund.value = error
    }
}

watch(paymentRefund, () => {
    if (errorPaymentMethodRefund.value) {
        errorPaymentMethodRefund.value = null
    }
});

const generateRefundRoute = (refundSlug: string) => {
    return route('grp.org.fulfilments.show.operations.invoices.show.refunds.show', {
        organisation: route().params?.organisation,
        fulfilment: route().params?.fulfilment,
        invoice: props.invoice_pay.invoice_slug,
        refund: refundSlug
    })
}
</script>

<template>
    <dd class="relative w-full flex flex-col border rounded-md border-gray-300 overflow-hidden">
        <dl class="">
            <!-- Invoice -->
            <div class="border-b border-gray-200 px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium ">
                    <FontAwesomeIcon v-tooltip="trans('Invoice')" icon="fal fa-file-invoice-dollar" class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ invoice_pay.invoice_reference }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_invoice)) }}
                </dd>
            </div>

            <!-- Refunds -->
            <div v-if="Number(invoice_pay.total_refunds) < 0" class="border-b border-gray-200">
                <div v-for="refund in invoice_pay.list_refunds.data" class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium ">
                        <FontAwesomeIcon v-tooltip="trans('Refund')" icon="fal fa-arrow-circle-left" class="text-gray-400" fixed-width aria-hidden="true" />
                        <Link :href="generateRefundRoute(refund.slug)" class="secondaryLink">{{ refund.reference }}</Link>
                    </dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(refund.total_amount)) }}
                    </dd>
                </div>
            </div>

            <!-- I+R total -->
            <div v-if="Number(invoice_pay.total_refunds) < 0" class="border-b border-gray-200 px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium ">I+R total</dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right"
                    :class="Number(invoice_pay.total_balance) > 0 ? 'text-green-500' : Number(invoice_pay.total_balance) < 0 ? 'text-red-500' : ''"
                >
                    {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_balance)) }}
                </dd>
            </div>

            <!-- Pay in -->
            <div class="border-b border-gray-200">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium ">Pay in</dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_paid_in)) }}
                    </dd>
                </div>
                <!-- Pay out -->
                <div v-if="Number(invoice_pay.total_refunds) < 0" class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium ">Pay out</dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_paid_out)) }}
                    </dd>
                </div>
                <!-- addition customer balance -->
                <div v-if="Number(invoice_pay.addition_customer_balance) > 0" class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium ">Customer Balance</dt>
                    <dd class="mt-1 text-sm/6 sm:mt-0 text-right text-green-600">
                        +{{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.addition_customer_balance)) }}
                    </dd>
                </div>
            </div>

            <!-- Total to pay -->
            <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    {{ Number(invoice_pay.total_need_to_pay) < 0 ? 'Total to refund' : 'Total to pay' }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0" v-tooltip="trans('No need to pay anything')" icon="far fa-check" class="text-green-500" fixed-width aria-hidden="true" />
                    {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_need_to_pay)) }}
                    <Button v-if="Number(invoice_pay.total_need_to_pay) > 0"
                        @click="() => (isOpenModalInvoice = true, fetchPaymentMethod())" size="xxs" type="secondary">Pay
                        Invoice
                    </Button>
                    <Button v-else-if="Number(invoice_pay.total_need_to_pay) < 0"
                        @click="() => (isOpenModalRefund = true, fetchPaymentMethod())" size="xxs" type="secondary">Pay
                        Refunds
                    </Button>
                </dd>
            </div>
        </dl>

        <!-- Modal: Pay Invoice -->
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
                            <PureMultiselect v-model="paymentData.payment_method"
                                @update:modelValue="() => errorInvoicePayment.payment_method = null"
                                @input="() => errorInvoicePayment.payment_method = null" :options="listPaymentMethod"
                                :isLoading="isLoadingFetch" label="name" valueProp="id" required caret />
                        </div>
                        <Transition name="spin-to-down">
                            <p v-if="errorInvoicePayment.payment_method" class="text-red-500 italic text-sm mt-1">*{{
                                errorInvoicePayment.payment_method }}</p>
                        </Transition>
                    </div>

                    <div class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">{{ trans('Payment amount')
                            }}</label>
                        <div class="mt-1" :class="errorInvoicePayment.payment_amount ? 'errorShake' : ''">
                            <!-- <PureInputNumber v-model="paymentData.payment_amount" /> -->
                            <InputNumber v-model="paymentData.payment_amount"
                                @update:modelValue="(e) => paymentData.payment_amount = e"
                                @input="(e) => paymentData.payment_amount = e.value" buttonLayout="horizontal" :min="0"
                                :xxmax="invoice_pay.total_need_to_pay || undefined" :maxFractionDigits="2"
                                style="width: 100%" inputClass="border border-gray-300" :inputStyle="{
                                    fontSize: '14px',
                                    paddingTop: '10px',
                                    paddingBottom: '10px',
                                    width: '50px',
                                    background: 'transparent',
                                }" mode="currency" :currency="invoice_pay.currency_code" />
                        </div>
                        <div class="space-x-1">
                            <span class="text-xxs text-gray-500">{{ trans('Need to pay') }}: {{
                                locale.currencyFormat(invoice_pay.currency_code || 'usd',
                                Number(invoice_pay.total_need_to_pay)) }}</span>
                            <Button @click="() => paymentData.payment_amount = invoice_pay.total_need_to_pay"
                                :disabled="paymentData.payment_amount === invoice_pay.total_need_to_pay"
                                type="tertiary" label="Pay all" size="xxs" />
                        </div>
                    </div>

                    <div class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">{{ trans('Reference')
                            }}</label>
                        <div class="mt-1">
                            <PureInput v-model="paymentData.payment_reference" placeholder="#000000" />
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
                    <div v-if="!(!!paymentData.payment_method)"
                        @click="() => errorInvoicePayment.payment_method = trans('Payment method can\'t empty')"
                        class="absolute inset-0" />
                    <Button @click="() => onSubmitPayment()" :label="trans('Submit')"
                        :disabled="!(!!paymentData.payment_method)" :loading="isLoadingPayment" full />
                    <Transition name="spin-to-down">
                        <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{
                            errorPaymentMethod }}</p>
                    </Transition>
                </div>
            </div>
        </Modal>

        <!-- Modal: Pay refund -->
        <Modal :isOpen="isOpenModalRefund" @onClose="isOpenModalRefund = false" width="w-[600px]">
            <div class="isolate bg-white px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Refund Payment') }}</h2>
                    <p class="text-xs leading-5 text-gray-400">
                        {{ trans('Fill the information about refund payment') }}
                    </p>
                </div>

                <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div class="col-span-2">
                        <label for="first-name" class="block text-sm font-medium leading-6">
                            <span class="text-red-500">*</span> {{ trans('Select payment method') }}
                        </label>
                        <div class="mt-1 grid grid-cols-2 gap-x-3">
                            <div @click="() => paymentRefund.payment_method = item.value"
                                v-for="item in listPaymentRefund" :key="item.value"
                                class="flex justify-center items-center border  px-3 py-2 rounded text-center cursor-pointer"
                                :class="paymentRefund.payment_method === item.value ? 'bg-indigo-200 border-indigo-400' : 'border-gray-300'">
                                {{ item.label }}
                            </div>
                        </div>
                    </div>

                    <Transition name="slide-to-left">
                        <div v-if="paymentRefund.payment_method === 'invoice_payment_method'" class="col-span-2">
                            <label for="first-name" class="block text-sm font-medium leading-6">
                                <span class="text-red-500">*</span> {{ trans('Select payment account') }}
                            </label>
                            <div class="mt-1">
                                <PureMultiselect v-model="paymentRefund.payment_account"
                                    :options="listPaymentMethod" label="name" valueProp="id" :isLoading="isLoadingFetch"
                                    required caret />
                            </div>
                        </div>
                    </Transition>

                    <div class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">
                            {{ trans('Payment amount') }}
                        </label>
                        <div class="mt-1">
                            <!-- <PureInputNumber v-model="paymentRefund.payment_amount" /> -->
                            <InputNumber v-model="paymentRefund.payment_amount"
                                @update:modelValue="(e) => paymentRefund.payment_amount = e"
                                @input="(e) => paymentRefund.payment_amount = e.value" buttonLayout="horizontal"
                                :min="0" :xxmax="invoice_pay.total_need_to_pay || undefined" :maxFractionDigits="2"
                                style="width: 100%" inputClass="border border-gray-300" :inputStyle="{
                                    fontSize: '14px',
                                    paddingTop: '10px',
                                    paddingBottom: '10px',
                                    width: '50px',
                                    background: 'transparent',
                                }" mode="currency" :currency="invoice_pay.currency_code" />
                        </div>

                        <div class="space-x-1">
                            <span class="text-xxs text-gray-500">{{ trans('Need to refund') }}: {{ locale.currencyFormat(props.invoice_pay.currency_code || 'usd', Number(-invoice_pay.total_need_to_pay)) }}</span>
                            <Button @click="() => paymentRefund.payment_amount = -invoice_pay.total_need_to_pay"
                                :disabled="paymentRefund.payment_amount === -invoice_pay.total_need_to_pay"
                                type="tertiary" :label="trans('Pay all')" size="xxs" />
                        </div>
                    </div>
                </div>

                <div class="mt-6 mb-4 relative">
                    <Button @click="() => onSubmitPaymentRefund()" label="Submit"
                        :disabled="paymentRefund.payment_method === 'credit_balance' ? false : !(!!paymentRefund.payment_account)"
                        :loading="isLoadingPayment" full />
                    <Transition name="spin-to-down">
                        <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{
                            errorPaymentMethod }}</p>
                    </Transition>
                </div>
            </div>
        </Modal>
    </dd>
</template>