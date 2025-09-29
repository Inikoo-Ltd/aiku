<script setup lang="ts">
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import { trans } from "laravel-vue-i18n";
import { inject, computed, watch, ref } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { notify } from "@kyvg/vue3-notification";
import axios from "axios";
import { routeType } from "@/types/route";
import { Link, router } from "@inertiajs/vue3";
import InputNumber from "primevue/inputnumber";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faCheck, faSave } from "@far";
import { faPlus, faMinus, faArrowRight } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import BluePrintTableRefund from "@/Components/Segmented/InvoiceRefund/BlueprintTableRefund";
import PureTable from "@/Components/Pure/PureTable/PureTable.vue";
import Dialog from "primevue/dialog";
import ColumnGroup from "primevue/columngroup";
import Row from "primevue/row";
import Column from "primevue/column";
import { useLocaleStore } from "@/Stores/locale";
import ActionCell from "./ActionCell.vue";
import { InputText } from "primevue"

library.add(faCheck, faSave, faPlus, faMinus, faArrowRight);


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
    is_in_refund?: boolean
}>();

const emits = defineEmits<{
    (e: "onPayInOnClick"): void
}>();

const _formCell = ref({});
const locale = inject("locale", aikuLocaleStructure);
const _PureTable = ref(null);
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
                });
            }
        }
    );
};

watch(paymentData, () => {
    if (errorPaymentMethod.value) {
        errorPaymentMethod.value = null;
    }
});


// Section: Payment Refund
const isOpenModalRefund = ref(false);


const paymentRefund = ref({
    payment_method: "credit_balance",
    payment_account: null as number | null,
    payment_amount: 0 as number | null
});
/* const isLoadingPaymentRefund = ref(false) */
const errorPaymentMethodRefund = ref<null | unknown>(null);
const sendSubmitPaymentRefund = (url: string, data: any) => {
    try {
        router.post(
            url, data,
            {
                onStart: () => isLoadingPayment.value = true,
                onFinish: () => {
                    isLoadingPayment.value = false;
                    isOpenModalRefund.value = false;
                },
                onSuccess: () => {
                    paymentRefund.value.payment_account = null,
                        paymentRefund.value.payment_amount = 0,
                        notify({
                            title: trans("Success"),
                            text: "Successfully add payment invoice",
                            type: "success"
                        });
                },
                onError: (error) => {
                    errorPaymentMethodRefund.value = error;
                    notify({
                        title: trans("Something went wrong"),
                        text: error.message,
                        type: "error"
                    });
                },
                preserveScroll: true
            }
        );

    } catch (error: unknown) {
        errorPaymentMethodRefund.value = error;
    }
};


const onSubmitPaymentRefund = () => {
    let url;
    if (paymentRefund.value.payment_method === "credit_balance") {
        url = route("grp.models.refund.refund_to_credit", {
            refund: props.invoice_pay.invoice_id
        });
        sendSubmitPaymentRefund(url, {
            amount: paymentRefund.value.payment_amount
        });
    }
};

const onSubmitRefundToPaymentsMethod = (form, data: any) => {
    console.log('ffform', data);
    let url, finalData;
    if (paymentRefund.value.payment_method === "invoice_payment_method") {
        url = route("grp.models.refund.refund_to_payment_account", {
            refund: props.invoice_pay.invoice_id,
            paymentAccount: data.payment_account_slug
        });
        finalData = {
            amount: form.refund_amount,
            original_payment_id: data.id,
            reference: data.reference
        };
        console.log('fffozzzzrm', finalData);

        router.post(
            url, finalData,
            {
                onStart: () => data.processing = true,
                onFinish: () => data.processing = false,
                onSuccess: () => {
                    if (_PureTable.value) _PureTable.value.fetchData();
                    if (props.invoice_pay.total_need_to_refund_in_payment_method == 0) {
                        isOpenModalRefund.value = false;
                    }
                    notify({
                        title: trans("Success"),
                        text: "Successfully add payment invoice",
                        type: "success"
                    });
                },
                onError: (error) => {
                    console.log(error);
                    notify({
                        title: trans("Something went wrong"),
                        text: error.message,
                        type: "error"
                    });
                }
            }
        );
    }
};

watch(paymentRefund, () => {
    if (errorPaymentMethodRefund.value) {
        errorPaymentMethodRefund.value = null;
    }
});

// const generateRefundRoute = (refundSlug: string) => {

//     if (route().current() === 'grp.org.fulfilments.show.crm.customers.show.invoices.show') {
//         return route("grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show", {
//             fulfilment: route().params?.fulfilment,
//             fulfilmentCustomer: route().params?.fulfilmentCustomer,
//             organisation: route().params?.organisation,
//             shop: route().params?.shop,
//             refund: refundSlug,
//             invoice: props.invoice_pay.invoice_slug
//         })
//     }


//     if (route().params?.fulfilment) {
//         return route("grp.org.fulfilments.show.operations.invoices.show.refunds.show", {
//             organisation: route().params?.organisation,
//             fulfilment: route().params?.fulfilment,
//             invoice: props.invoice_pay.invoice_slug,
//             refund: refundSlug
//         });
//     } else {
//         return route("grp.org.accounting.invoices.show.refunds.show", {
//             organisation: route().params?.organisation,
//             invoice: props.invoice_pay.invoice_slug,
//             refund: refundSlug
//         });
//     }

// };


// const generateInvoiceRoute = () => {
//     if (route().current() === 'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show') {
//         return route("grp.org.fulfilments.show.crm.customers.show.invoices.show", {
//             fulfilment: route().params?.fulfilment,
//             fulfilmentCustomer: route().params?.fulfilmentCustomer,
//             organisation: route().params?.organisation,
//             shop: route().params?.shop,
//             invoice: props.invoice_pay.invoice_slug
//         })
//     }


//     if (route().params?.fulfilment) {
//         return route("grp.org.fulfilments.show.operations.invoices.show", {
//             organisation: route().params?.organisation,
//             fulfilment: route().params?.fulfilment,
//             invoice: props.invoice_pay.invoice_slug
//         });
//     } else {
//         switch (route().current()) {
//             case 'grp.org.shops.show.dashboard.invoices.refunds.show':
//                 return route("grp.org.shops.show.dashboard.invoices.show", {
//                     organisation: route().params?.organisation,
//                     shop: route().params?.shop,
//                     invoice: props.invoice_pay.invoice_slug
//                 });
//             default:
//                 return route("grp.org.accounting.invoices.show", {
//                     organisation: route().params?.organisation,
//                     invoice: props.invoice_pay.invoice_slug
//                 });
//         }
//     }
// };

// const generateShowOrderRoute = () => {
//     return route("grp.org.shops.show.ordering.orders.show", {
//         organisation: route().params?.organisation,
//         shop: props.invoice_pay.shop_slug,
//         order: props.invoice_pay.order_slug
//     });
// };

const totalAmount = computed(() => {
    return _PureTable.value ? _PureTable.value?.data.reduce((sum, item) => sum + Number(item.amount || 0), 0) : 0;
});

const totalRefunded = computed(() => {
    return _PureTable.value ? _PureTable.value?.data.reduce((sum, item) => sum + Number(item.refunded || 0), 0) : 0;
});


const maxRefund = (data) => {
    if (!data) return 0;
    const maxPossible = data.amount - data.refunded;
    return Math.min(maxPossible, -props.invoice_pay.total_need_to_refund_in_payment_method);
};

const onClickRefundPayments = () => {
    isOpenModalRefund.value = true;
    if (props.invoice_pay.total_need_to_refund_in_payment_method < 0)
        paymentRefund.value.payment_method = "invoice_payment_method";
    else if (props.invoice_pay.total_need_to_refund_in_payment_method <= 0)
        paymentRefund.value.payment_method = "credit_balance";
};

const listPaymentRefund = computed(() => [
    {
        label: trans("Refund money to customer's credit balance"),
        value: "credit_balance",
        disable: false
    },
    {
        label: trans("Refund money to payment method of the invoice"),
        value: "invoice_payment_method",
        disable: Number(props.invoice_pay.total_need_to_refund_in_payment_method) >= 0
    }
]);

const setRefundAllOutsideFulfilmentShop = (value, index) => {
    if (_formCell.value[index])
        _formCell.value[index].form.refund_amount = -value;
};


</script>

<template>
    <dd class="relative w-full flex flex-col border rounded-md border-gray-300 overflow-hidden">
        <dl class="">

            <!-- Field: Total -->
            <div v-if="invoice_pay.order_reference"
                class="border-b border-gray-300 px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt v-tooltip="invoice?.reference ? trans('Total of invoice :invoice', { invoice: invoice?.reference }) : ''" class="text-sm/6 font-medium ">
                    {{ trans("Total") }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_invoice)) }}
                </dd>
            </div>

            <!-- Field: Excess payment -->
            <div v-if="Number(invoice_pay.total_excess_payment) > 0" class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium" v-tooltip="trans('Auto add to customer balance')">{{ trans("Excess Payment") }}</dt>
                    <dd class="mt-1 text-sm/6 sm:mt-0 text-right text-gray-700">
                        {{
                            locale.currencyFormat(invoice_pay.currency_code,
                                Number(invoice_pay.total_excess_payment))
                        }}
                    </dd>
                </div>
            </div>


            <!-- Field: Payed in & Payed out -->
            <div class="border-b border-gray-300">
                <!-- Pay in -->
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium underline cursor-pointer"
                        :style="{ padding : 0 }"
                        @click="()=>emits('onPayInOnClick')"
                    >
                        {{ trans("Payed in") }}
                    </dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_paid_in)) }}
                    </dd>
                </div>
                

            </div>

            <!-- Total to pay -->
            <div v-if="Number(invoice_pay.total_need_to_pay) != 0 " class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    {{ Number(invoice_pay.total_need_to_pay) < 0 ? "Total to refund" : "Total to pay" }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
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
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_need_to_pay)) }}
                    </span>
                </dd>
            </div>

            <!-- Field: Paid -->
            <div v-else class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    {{ trans("Paid") }}
                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0"
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
        <Dialog v-model:visible="isOpenModalInvoice" :style="{ width: '100%', maxWidth: '800px'}" modal dismissableMask>
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
                            <span class="text-xxs text-gray-500">{{ trans("Need to pay") }}: {{
                                    locale.currencyFormat(invoice_pay.currency_code,
                                        Number(invoice_pay.total_need_to_pay))
                                }}</span>
                            <Button @click="() => paymentData.payment_amount = invoice_pay.total_need_to_pay"
                                    :disabled="paymentData.payment_amount === invoice_pay.total_need_to_pay"
                                    type="tertiary"
                                    label="Pay all" size="xxs"/>
                        </div>
                    </div>

                    <div v-if="paymentData.payment_method" class="col-span-2">
                        <label for="last-name" class="block text-sm font-medium leading-6">{{
                                trans("Reference")
                            }}</label>
                        <div class="mt-1">
                            <PureInput v-model="paymentData.payment_reference" placeholder="#000000"/>
                        </div>
                    </div>


                </div>

                <div class="mt-6 mb-4 relative">
                    <div v-if="!(!!paymentData.payment_method)"
                        @click="() => errorInvoicePayment.payment_method = trans('Payment method can\'t empty')"
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
