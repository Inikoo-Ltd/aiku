<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { trans } from 'laravel-vue-i18n'
import { inject, computed } from 'vue'
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
import BluePrintTableRefund from '@/Components/Segmented/BlueprintTableRefund'
import PureTable from '@/Components/Pure/PureTable/PureTable.vue'
import Dialog from 'primevue/dialog';
import ColumnGroup from 'primevue/columngroup';
import Row from 'primevue/row'; 
import Column from 'primevue/column';
import { useLocaleStore } from "@/Stores/locale"
import { data } from '../CMS/Website/Product/ProductTemplates/Product1/Descriptor'
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
        payments : routeType
    }
}>()

const emits = defineEmits<{
    (e: 'onPayInOnClick'): void
}>()

const locale = inject('locale', aikuLocaleStructure)
const _PureTable = ref(null)
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
    payment_method: "credit_balance",
    payment_account: null as number | null,
    payment_amount: 0 as number | null,
    //   payment_reference: ""
})
const isLoadingPaymentRefund = ref(false)
const errorPaymentMethodRefund = ref<null | unknown>(null)
const sendSubmitPaymentRefund = (url, data) => {
    try {
        router.post(
            url,data,
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


const onSubmitPaymentRefund = () => {
    let url
    if (paymentRefund.value.payment_method === 'credit_balance') {
        url = route('grp.models.refund.refund_to_credit', {
            refund: props.invoice_pay.invoice_id,
        })
        sendSubmitPaymentRefund(url, {
            amount: paymentRefund.value.payment_amount,
        })
    } else {
        url = route('grp.models.refund.refund_to_payment_account', {
            refund: props.invoice_pay.invoice_id,
        })
        let finalData = []
        _PureTable.value.data.map((item)=>{
            finalData.push({
                id : item.id,
                refund : item.refund
            })
        })
        sendSubmitPaymentRefund(url,finalData)
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

const totalAmount = computed(() => {
    return _PureTable.value ?  _PureTable.value?.data.reduce((sum, item) => sum + Number(item.amount || 0), 0) : 0
})

const totalRefunded = computed(() => {
    return _PureTable.value ?  _PureTable.value?.data.reduce((sum, item) => sum + Number(item.refunded || 0), 0) : 0
})

const totalRefund = computed(() => {
    return _PureTable.value ?  _PureTable.value?.data.reduce((sum, item) => sum + Number(item.refund || 0), 0) : 0
})


</script>

<template>
    <dd class="relative w-full flex flex-col border rounded-md border-gray-400 overflow-hidden">
        <dl class="">
            <!-- Invoice -->
            <div class="border-b border-gray-400 px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium ">
                    <FontAwesomeIcon v-tooltip="trans('Invoice')" icon="fal fa-file-invoice-dollar"
                        class="text-gray-400" fixed-width aria-hidden="true" />
                    {{ invoice_pay.invoice_reference }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_invoice)) }}
                </dd>
            </div>

            <!-- Refunds -->
            <div v-if="Number(invoice_pay.total_refunds) < 0" class="border-b border-gray-400">
                <div v-for="refund in invoice_pay.list_refunds.data"
                    class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium ">
                        <FontAwesomeIcon v-tooltip="trans('Refund')" icon="fal fa-arrow-circle-left"
                            class="text-gray-400" fixed-width aria-hidden="true" />
                        <Link :href="generateRefundRoute(refund.slug)" class="secondaryLink">{{ refund.reference }}
                        </Link>
                    </dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(refund.total_amount)) }}
                    </dd>
                </div>
            </div>

            <!-- I+R total -->
            <div v-if="Number(invoice_pay.total_refunds) < 0"
                class="border-b border-gray-400 px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium ">I+R total</dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right"
                    :class="Number(invoice_pay.total_balance) > 0 ? '' : Number(invoice_pay.total_balance) < 0 ? '' : ''">
                    {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_balance)) }}
                </dd>
            </div>
            <!-- addition excees payment -->
            <div class="border-b border-gray-400" v-if="Number(invoice_pay.total_excees_payment) > 0">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium" v-tooltip="'auto add to customer balance'">Excess Payment</dt>
                    <dd class="mt-1 text-sm/6 sm:mt-0 text-right text-gray-700">
                        {{ locale.currencyFormat(invoice_pay.currency_code || 'usd',
                        Number(invoice_pay.total_excees_payment)) }}
                    </dd>
                </div>
            </div>


            <!-- Pay in -->
            <div class="border-b border-gray-400">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium secondaryLink" @click="()=>emits('onPayInOnClick')">Pay in</dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_paid_in))
                        }}
                    </dd>
                </div>
                <!-- Pay out -->
                <div v-if="Number(invoice_pay.total_refunds) < 0"
                    class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium ">Pay back</dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_paid_out))
                        }}
                    </dd>
                </div>
            </div>

            <!-- Total to pay -->
            <div v-if="Number(invoice_pay.total_need_to_pay) != 0 "
                class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    {{ Number(invoice_pay.total_need_to_pay) < 0 ? 'Total to refund' : 'Total to pay' }} </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    <button v-if="Number(invoice_pay.total_need_to_pay) > 0"
                        @click="() => (isOpenModalInvoice = true, fetchPaymentMethod())" size="xxs"
                        class="secondaryLink text-indigo-500">
                        Pay Invoice
                    </button>

                    <button v-else-if="Number(invoice_pay.total_need_to_pay) < 0"
                        @click="() => (isOpenModalRefund = true)" size="xxs" class="secondaryLink text-indigo-500">
                        Refund payment
                    </button>

                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0"
                        v-tooltip="trans('No need to pay anything')" icon="far fa-check" class="text-green-500"
                        fixed-width aria-hidden="true" />
                    <span :class="[Number(invoice_pay.total_need_to_pay) < 0 ? 'text-red-500' : '', 'ml-2']">{{
                        locale.currencyFormat(invoice_pay.currency_code || 'usd', Number(invoice_pay.total_need_to_pay))
                        }}</span>
                </dd>
            </div>

            <div v-else class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    Paid
                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0"
                        v-tooltip="trans('No need to pay anything')" icon="far fa-check" class="text-green-500"
                        fixed-width aria-hidden="true" />
                </dt>
            </div>
        </dl>

        <!-- Modal: Pay Invoice -->
        <Dialog v-model:visible="isOpenModalInvoice" :style="{ width: '600px' }" modal dismissableMask>
            <template #header>
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Invoice Payment') }}</h2>
                </div>
            </template>
            <!--         <Modal :isOpen="isOpenModalInvoice" @onClose="isOpenModalInvoice = false" width="w-[600px]"> -->
            <div class="isolate bg-white px-6 lg:px-8">
                <!--   <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Invoice Payment') }}</h2>
                </div> -->

                <div class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
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

                    <div v-if="paymentData.payment_method" class="col-span-2">
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
                                :disabled="paymentData.payment_amount === invoice_pay.total_need_to_pay" type="tertiary"
                                label="Pay all" size="xxs" />
                        </div>
                    </div>

                    <div v-if="paymentData.payment_method" class="col-span-2">
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
            <!--  </Modal> -->
        </Dialog>

        <!-- Modal: Pay refund -->
        <!--  <Modal :isOpen="isOpenModalRefund" @onClose="isOpenModalRefund = false" width="w-[600px]"> -->
        <Dialog v-model:visible="isOpenModalRefund" :style="{ width: '75vw' }" maximizable modal :draggable="false">
            <template #header>
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans('Refund Payment') }}</h2>
                </div>
            </template>
            <div class="isolate bg-white px-6 lg:px-8">

                <div class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div class="col-span-2">
                        <label for="first-name" class="block text-sm font-medium leading-6">
                            <span class="text-red-500">*</span> {{ trans('Select refund method') }}
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



                    <!-- <Transition name="slide-to-left">
                        <div v-if="paymentRefund.payment_method === 'invoice_payment_method'" class="col-span-2">
                            <label for="first-name" class="block text-sm font-medium leading-6">
                                <span class="text-red-500">*</span> {{ trans('Select payment account') }}
                            </label>
                            <div class="mt-1">
                                <PureMultiselect v-model="paymentRefund.payment_account" :options="listPaymentMethod"
                                    label="name" valueProp="id" :isLoading="isLoadingFetch" required caret />
                            </div>
                        </div>
                    </Transition> -->

                     <div v-if="paymentRefund.payment_method == 'credit_balance'" class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">
                            {{ trans('Refund amount') }}
                        </label>
                        <div class="mt-1">
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
                            <span class="text-xxs text-gray-500">{{ trans('Need to refund') }}: {{
                                locale.currencyFormat(props.invoice_pay.currency_code || 'usd',
                                Number(-invoice_pay.total_need_to_pay)) }}</span>
                            <Button @click="() => paymentRefund.payment_amount = -invoice_pay.total_need_to_pay"
                                :disabled="paymentRefund.payment_amount === -invoice_pay.total_need_to_pay"
                                type="tertiary" :label="trans('Refund all')" size="xxs" />
                        </div>
                    </div>

                    <div v-if="paymentRefund.payment_method == 'invoice_payment_method'" class="col-span-2">
                        <div class="my-2">
                            <PureTable ref="_PureTable"  :route="routes.payments" :blueprint="BluePrintTableRefund" :tableProps="{
                                    size: 'small',
                                    showGridlines: true
                                }">
                                <template #amount="{data}">
                                    <div class="text-gray-500">{{ useLocaleStore().currencyFormat( data.currency_code,data.amount) }}</div>
                                </template>

                                <template #refunded="{data}">
                                    <div class="text-gray-500">{{useLocaleStore().currencyFormat( data.currency_code,data.refunded) }}</div>
                                </template>

                                <template #refund="{ data }">
                                    <InputNumber v-model="data.refund"
                                        @update:modelValue="(e) => data.refund = e"
                                         buttonLayout="horizontal"
                                         @input="(e) => data.refund = e.value" 
                                        :maxFractionDigits="2" style="width: 100%" inputClass="border border-gray-300"
                                        :inputStyle="{
                                            fontSize: '14px',
                                            paddingTop: '10px',
                                            paddingBottom: '10px',
                                            width: '50px',
                                            background: 'transparent',
                                        }" mode="currency" :currency="invoice_pay.currency_code" />
                                </template>


                                <template #footer :data="{data}">
                                    <ColumnGroup type="footer">
                                        <Row>
                                            <Column footer="Totals:"  footerStyle="text-align:right" />
                                            <Column :footer="useLocaleStore().currencyFormat(invoice_pay.currency_code, totalAmount)" />
                                            <Column :footer="useLocaleStore().currencyFormat(invoice_pay.currency_code, totalRefunded)" />
                                            <Column :footer="useLocaleStore().currencyFormat(invoice_pay.currency_code, totalRefund)" />
                                        </Row>
                                    </ColumnGroup>
                                </template>
                            </PureTable>
                        </div>
                    </div>
                </div>

                <div class="mt-6 mb-4 relative">
                    <Button @click="() => onSubmitPaymentRefund()" label="Submit"
                        :loading="isLoadingPayment" full />
                    <Transition name="spin-to-down">
                        <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{
                            errorPaymentMethod }}</p>
                    </Transition>
                </div>
            </div>
            <!--  </Modal> -->
        </Dialog>
    </dd>
</template>