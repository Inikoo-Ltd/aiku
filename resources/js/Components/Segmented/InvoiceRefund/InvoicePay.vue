<script setup lang="ts">
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { trans } from "laravel-vue-i18n"
import { inject, computed, watch, ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { routeType } from "@/types/route"
import { Link, router } from "@inertiajs/vue3"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faSave } from "@far"
import { faPlus, faMinus, faArrowRight } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Dialog from "primevue/dialog"

library.add(faCheck, faSave, faPlus, faMinus, faArrowRight)


const props = defineProps<{
    invoice?: {
        slug: string
        reference: string
    }
    invoice_pay: {
        currency_code: string
        total_invoice: number
        total_refunds: number
        total_balance: number
        total_paid_in: number
        total_paid_account: number
        total_excess_payment: number
        total_need_to_refund_in_payment_method: number
        total_need_to_refund_in_credit_method: number
        total_paid_out: {
            data: {}[]
        }
        total_need_to_pay: number
    }
    routes: {
        submit_route: routeType
        fetch_payment_accounts_route: routeType
        payments: routeType
    }
    list_refunds: {
        data: {
            id: number
            slug: string
            reference: string
            currency_code: string
            total_amount: number
            payment_amount: number
        }[]
    }
}>();

const emits = defineEmits<{
    (e: "onPayInOnClick"): void
}>();

const locale = inject("locale", aikuLocaleStructure);
const errorInvoicePayment = ref({
    payment_method: null,
    payment_amount: null,
    payment_reference: null
});

const paymentData = ref({
    payment_method: null as number | null,
    payment_amount: 0 as number | null,
    payment_reference: ""
});


// Section: Payment invoice
const isOpenModalInvoice = ref(false);
const listPaymentMethod = ref([]);
const isLoadingFetch = ref(false);
const fetchPaymentMethod = async () => {
    try {
        isLoadingFetch.value = true;
        const {data} = await axios.get(route(props.routes.fetch_payment_accounts_route.name, props.routes.fetch_payment_accounts_route.parameters));
        listPaymentMethod.value = data.data;
    } catch (error) {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to fetch payment method list"),
            type: "error"
        });
    } finally {
        isLoadingFetch.value = false;
    }
};
const isLoadingPayment = ref(false);
const errorPaymentMethod = ref<null | unknown>(null);
const onSubmitPayment = () => {
    router[props.routes.submit_route.method || "post"](
        route(props.routes.submit_route.name, {
            ...props.routes.submit_route.parameters,
            paymentAccount: paymentData.value.payment_method
        }),
        {
            amount: paymentData.value.payment_amount,
            reference: paymentData.value.payment_reference,
            status: "success",
            state: "completed"
        },
        {
            onStart: () => isLoadingPayment.value = true,
            onFinish: () => {
                isLoadingPayment.value = false;
            },
            onSuccess: () => {
                paymentData.value.payment_method = null,
                    paymentData.value.payment_amount = 0,
                    paymentData.value.payment_reference = "";
                isOpenModalInvoice.value = false;
                notify({
                    title: trans("Success"),
                    text: "Successfully add payment invoice",
                    type: "success"
                });
            },
            onError: (error) => {
                errorPaymentMethod.value = error;
                notify({
                    title: trans("Something went wrong"),
                    text: error.message,
                    type: "error"
                })
            }
        }
    )
}

watch(paymentData, () => {
    if (errorPaymentMethod.value) {
        errorPaymentMethod.value = null;
    }
})


const paymentRefund = ref({
    payment_method: "credit_balance",
    payment_account: null as number | null,
    payment_amount: 0 as number | null
})
const errorPaymentMethodRefund = ref<null | unknown>(null);



watch(paymentRefund, () => {
    if (errorPaymentMethodRefund.value) {
        errorPaymentMethodRefund.value = null;
    }
})


const getRefundRoute = (refund: { slug: string }) => {
    return route('grp.org.accounting.refunds.show', {
        organisation: route().params?.organisation,
        refund: refund.slug
    })
}

const compTotalPayment = computed(() => {
    const total = props.list_refunds?.data?.reduce((sum, refund) => sum + Number(refund.total_amount || 0), 0) || 0;
    const result = total + Number(props.invoice_pay.total_invoice);
    return result;
})

const compPayment = computed(() => {
    const total = props.list_refunds?.data?.reduce((sum, refund) => sum + Number(refund.payment_amount || 0), 0) || 0;
    const result = total + Number(props.invoice_pay.total_paid_in);
    return result;
})

const compTotalToPay = computed(() => {
    const total = props.list_refunds?.data?.reduce((sum, refund) => sum + Number(refund.total_amount - refund.payment_amount), 0) || 0;
    const result = total + Number(props.invoice_pay.total_need_to_pay);
    return result.toFixed(2);
})

const compTooltipTotalToPay = computed(() => {
    if (Number(compTotalToPay.value) < 0 ) {
        return trans("We need to refund to customer :amount", { amount: locale.currencyFormat(props.invoice_pay.currency_code, Math.abs(Number(compTotalToPay.value)).toFixed(2)) })
    }
    if (Number(compTotalToPay.value) > 0) {
        return trans("Customer need to pay :amount", { amount: locale.currencyFormat(props.invoice_pay.currency_code, Number(compTotalToPay.value).toFixed(2)) }) 
    }
    
    return ''
})
</script>

<template>
    <dd class="relative w-full flex flex-col border rounded-md border-gray-300 overflow-hidden">
        <dl v-if="props.list_refunds?.data?.length" class="">

            <!-- Field: Excess payment -->
            <div v-if="!props.list_refunds?.data?.length && Number(invoice_pay.total_excess_payment) > 0" class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium" xv-tooltip="trans('Auto add to customer balance')">{{ trans("Excess Payment") }}</dt>
                    <dd class="mt-1 text-sm/6 sm:mt-0 text-right text-gray-700">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_excess_payment).toFixed(2)) }}
                    </dd>
                </div>
            </div>

            <!-- List Refunds -->
            <div>
                <div class="px-2 text-xs py-1 tabular-nums">
                    <table class="w-full xborder border-gray-300 rounded">
                        <tr class="font-bold ">
                            <td class="px-2 py-1">{{ trans("Reference") }}</td>
                            <td class="px-2 text-right">{{ trans("Total") }}</td>
                            <td class="px-2 text-right">{{ trans("Payments") }}</td>
                            <td class="px-2 text-right">{{ trans("Total to Pay") }}</td>
                        </tr>
                        <tr class="xfont-bold border-t border-gray-300">
                            <td class="px-1 pt-1">{{ invoice_pay.invoice_reference }}</td>
                            <td class="text-right px-2 pt-1">{{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_invoice).toFixed(2)) }}</td>
                            <td class="text-right px-2 pt-1">{{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_paid_in).toFixed(2)) }}</td>
                            <td class="text-right px-2 pt-1">{{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_need_to_pay).toFixed(2)) }}</td>
                        </tr>
                        <tr v-for="refund in props.list_refunds?.data" :key="refund.id">
                            <td class="">
                                <Link :href="getRefundRoute(refund)" class="secondaryLink py-0.5">
                                    {{ refund.reference }}
                                </Link>
                                <FontAwesomeIcon v-tooltip="trans('Refund')" icon="fal fa-arrow-circle-left" class="text-gray-500" fixed-width aria-hidden="true" />
                            </td>
                            <td class="px-2 text-right">
                                {{ locale.currencyFormat(refund.currency_code, Number(refund.total_amount).toFixed(2)) }}
                            </td>
                            <td class="px-2 text-right">
                                {{ locale.currencyFormat(refund.currency_code, Number(refund.payment_amount).toFixed(2)) }}
                            </td>
                            <td class="px-2 text-right">
                                {{ locale.currencyFormat(refund.currency_code, Number(refund.total_amount-refund.payment_amount).toFixed(2)) }}
                            </td>
                        </tr>

                        <tr class="border-t border-gray-300">
                            <td></td>
                            <td class="text-right align-top px-2 py-1">{{ locale.currencyFormat(invoice_pay.currency_code, Number(compTotalPayment).toFixed(2)) }}</td>
                            <td class="text-right align-top px-2 py-1">{{ locale.currencyFormat(invoice_pay.currency_code, Number(compPayment).toFixed(2)) }}</td>
                            <td class="text-right flex items-end flex-col">
                                <div
                                    v-tooltip="compTooltipTotalToPay"
                                    class="px-2 py-1 w-fit rounded-sm"
                                    :class="Number(compTotalToPay) < 0 ? 'bg-indigo-100 border border-dashed border-indigo-500' : ''"
                                >
                                    {{ locale.currencyFormat(invoice_pay.currency_code, Number(compTotalToPay).toFixed(2)) }}
                                    <FontAwesomeIcon v-if="Number(compTotalToPay).toFixed(2) == 0" v-tooltip="trans('All well. No need to do anything.')" icon="fas fa-check-circle" class="text-green-500 -ml-0.5 -mr-4 text-xs" fixed-width aria-hidden="true" />
                                </div>

                                <button v-if="Number(compTotalToPay) > 0"
                                    @click="() => (isOpenModalInvoice = true, fetchPaymentMethod())" size="xxs"
                                    class="secondaryLink text-indigo-500"
                                >
                                    {{ trans("Pay Invoice") }}
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </dl>

        <!-- If have no refunds -->
        <dl v-else class="">
            <!-- Field: Total -->
            <div v-if="invoice_pay.order_reference"
                class="xborder-b border-gray-300 px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt v-tooltip="invoice?.reference ? trans('Total of invoice :invoice', { invoice: invoice?.reference }) : ''" class="text-sm/6 font-medium ">
                    {{ trans("Total") }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_invoice)) }}
                </dd>
            </div>

            <!-- Field: Payment -->
            <div class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium"
                        :style="{ padding : 0 }"
                    >
                        {{ trans("Payment") }}
                    </dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_paid_in)) }}
                    </dd>
                </div>
            </div>

            <!-- Field: Excess payment -->
            <div v-if="Number(invoice_pay.total_excess_payment) > 0" class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium" v-tooltip="trans('Auto add to customer balance')">{{ trans("Excess Payment") }}</dt>
                    <dd v-tooltip="trans('We need to refund to customer :amount', { amount: locale.currencyFormat(invoice_pay.currency_code, Math.abs(Number(invoice_pay.total_excess_payment)).toFixed(2)) })"
                        class="mt-1 text-sm/6 sm:mt-0 text-right text-gray-700 bg-indigo-100 border border-dashed border-indigo-500 px-1.5 -mr-1.5">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_excess_payment)) }}
                    </dd>
                </div>
            </div>

            <!-- Need to Pay -->
            <div v-if="Number(invoice_pay.total_need_to_pay) > 0 " class="px-4 pt-2 pb-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    {{ trans("Need to pay") }}
                </dt>

                <dd class="text-sm/6 text-gray-700 sm:mt-0 text-right">
                    <button v-if="Number(invoice_pay.total_need_to_pay) > 0"
                            @click="() => (isOpenModalInvoice = true, fetchPaymentMethod())" size="xxs"
                            class="secondaryLink text-indigo-500">
                        {{ trans("Pay Invoice") }}
                    </button>

                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0"
                        v-tooltip="trans('No need to pay anything')" icon="far fa-check"
                        class="text-green-500"
                        fixed-width
                        aria-hidden="true"
                    />
                    <span :class="[Number(invoice_pay.total_need_to_pay) < 0 ? 'text-red-500' : '', 'ml-2']">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_need_to_pay).toFixed(2)) }}
                    </span>
                </dd>
            </div>

            <!-- Field: Paid -->
            <div v-if="Number(invoice_pay.total_need_to_pay) === 0" class="bg-green-100 px-4 py-1 flex justify-between sm:gap-4 sm:px-3"
                xclass="Number(invoice_pay.total_need_to_pay) == 0 ? 'bg-green-100' : ''"
            >
                <dt class="text-sm/6 font-medium">
                    {{ trans("Paid") }}
                    <FontAwesomeIcon xv-if="Number(invoice_pay.total_need_to_pay) == 0"
                        v-tooltip="trans('No need to pay anything')"
                        icon="far fa-check"
                        class="text-green-500"
                        fixed-width
                        aria-hidden="true"
                    />
                </dt>
            </div>
        </dl>

        <!-- Modal: Pay Invoice -->
        <Dialog v-model:visible="isOpenModalInvoice" :style="{ width: '100%', maxWidth: '600px'}" modal dismissableMask>
            <template #header>
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans("Invoice Payment") }}</h2>
                </div>
            </template>
            <div class="isolate bg-white px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div class="col-span-2">
                        <label for="first-name" class="block text-sm font-medium leading-6">
                            <span class="text-red-500">*</span> {{ trans("Select payment method") }}
                        </label>
                        <div class="mt-1 relative" :class="errorInvoicePayment.payment_method ? 'errorShake' : ''">
                            <PureMultiselect v-model="paymentData.payment_method"
                                @update:modelValue="() => errorInvoicePayment.payment_method = null"
                                @input="() => errorInvoicePayment.payment_method = null"
                                :options="listPaymentMethod"
                                :isLoading="isLoadingFetch" label="name" valueProp="id" required caret/>
                        </div>
                        <Transition name="spin-to-down">
                            <p v-if="errorInvoicePayment.payment_method" class="text-red-500 italic text-sm mt-1">*{{
                                    errorInvoicePayment.payment_method
                                }}</p>
                        </Transition>
                    </div>

                    <div v-if="paymentData.payment_method" class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">{{
                                trans("Payment amount")
                            }}</label>
                        <div class="mt-1" :class="errorInvoicePayment.payment_amount ? 'errorShake' : ''">
                            <InputNumber v-model="paymentData.payment_amount"
                                @update:modelValue="(e) => paymentData.payment_amount = e"
                                @input="(e) => paymentData.payment_amount = e.value" buttonLayout="horizontal"
                                :min="0"
                                :xxmax="invoice_pay.total_need_to_pay || undefined" :maxFractionDigits="2"
                                style="width: 100%" inputClass="border border-gray-300" :inputStyle="{
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
                            <span class="text-xxs text-gray-500">
                                {{ trans("Need to pay") }}: {{ locale.currencyFormat(invoice_pay.currency_code, Number(compTotalToPay).toFixed(2)) }}
                            </span>
                            <Button @click="() => paymentData.payment_amount = compTotalToPay"
                                    :disabled="paymentData.payment_amount === compTotalToPay"
                                    type="tertiary"
                                    :label="trans('Pay all')" size="xxs"/>
                        </div>
                    </div>

                    <div v-if="paymentData.payment_method" class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">
                            {{ trans("Reference") }}
                        </label>
                        <div class="mt-1">
                            <PureInput v-model="paymentData.payment_reference" placeholder="#000000"/>
                        </div>
                    </div>


                </div>

                <div class="mt-6 mb-4 relative">
                    <div v-if="!(!!paymentData.payment_method)"
                        @click="() => errorInvoicePayment.payment_method = trans(`Payment method can't empty`)"
                        class="absolute inset-0"/>
                    <Button @click="() => onSubmitPayment()" :label="trans('Submit')"
                            :disabled="!(!!paymentData.payment_method)" :loading="isLoadingPayment" full/>
                    <Transition name="spin-to-down">
                        <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{
                                errorPaymentMethod
                            }}</p>
                    </Transition>
                </div>
            </div>
        </Dialog>

    </dd>
</template>
