<script setup lang="ts">
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { trans } from "laravel-vue-i18n"
import { inject, computed, watch, ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { routeType } from "@/types/route"
import { Link, router } from "@inertiajs/vue3"
import InputNumber from "primevue/inputnumber"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faSave } from "@far"
import { faExclamationTriangle } from "@fad"
import { faDigging as fasDigging, faRobot as fasRobot, faPiggyBank as fasPiggyBank } from "@fas"
import { faPlus, faMinus, faArrowRight, faDigging, faRobot, faPiggyBank } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import BluePrintTableRefund from "@/Components/Segmented/InvoiceRefund/BlueprintTableRefund"
import PureTable from "@/Components/Pure/PureTable/PureTable.vue"
import Dialog from "primevue/dialog"
import ColumnGroup from "primevue/columngroup"
import Row from "primevue/row"
import Column from "primevue/column"
import { useLocaleStore } from "@/Stores/locale"
import ActionCell from "./ActionCell.vue"
import { InputText, Message } from "primevue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { get, set } from "lodash"
import { InvoiceResource } from "@/types/invoice"

library.add(fasDigging, fasRobot, fasPiggyBank, faExclamationTriangle, faCheck, faSave, faPlus, faMinus, faArrowRight, faDigging, faRobot, faPiggyBank)


const props = defineProps<{
    refund?: InvoiceResource
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
}>()
console.log('popop', props.refund)

const emits = defineEmits<{
    (e: "onPayInOnClick"): void
}>()

const layout = inject('layout', layoutStructure)

const _formCell = ref({})
const locale = inject("locale", aikuLocaleStructure)
const _PureTable = ref(null)
// const errorInvoicePayment = ref({
//     payment_method: null,
//     payment_amount: null,
//     payment_reference: null
// })

const paymentData = ref({
    payment_method: null as number | null,
    payment_amount: 0 as number | null,
    payment_reference: ""
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
            title: trans("Something went wrong"),
            text: trans("Failed to fetch payment method list"),
            type: "error"
        })
    } finally {
        isLoadingFetch.value = false
    }
}
const isLoadingPayment = ref(false)
const errorPaymentMethod = ref<null | unknown>(null)
// const onSubmitPayment = () => {
//     router[props.routes.submit_route.method || "post"](
//         route(props.routes.submit_route.name, {
//             ...props.routes.submit_route.parameters,
//             paymentAccount: paymentData.value.payment_method
//         }),
//         {
//             amount: paymentData.value.payment_amount,
//             reference: paymentData.value.payment_reference,
//             status: "success",
//             state: "completed"
//         },
//         {
//             onStart: () => isLoadingPayment.value = true,
//             onFinish: () => {
//                 isLoadingPayment.value = false
//             },
//             onSuccess: () => {
//                 paymentData.value.payment_method = null,
//                     paymentData.value.payment_amount = 0,
//                     paymentData.value.payment_reference = ""
//                 isOpenModalInvoice.value = false
//                 notify({
//                     title: trans("Success"),
//                     text: "Successfully add payment invoice",
//                     type: "success"
//                 })
//             },
//             onError: (error) => {
//                 errorPaymentMethod.value = error
//                 notify({
//                     title: trans("Something went wrong"),
//                     text: error.message,
//                     type: "error"
//                 })
//             }
//         }
//     )
// }

watch(paymentData, () => {
    if (errorPaymentMethod.value) {
        errorPaymentMethod.value = null
    }
})


// Section: Payment Refund
const isOpenModalRefund = ref(false)


const paymentRefund = ref({
    payment_method: "credit_balance",
    payment_account: null as number | null,
    payment_amount: 0 as number | null
})
/* const isLoadingPaymentRefund = ref(false) */
const errorPaymentMethodRefund = ref<null | unknown>(null)
const sendSubmitPaymentRefund = (url: string, data: any) => {
    try {
        router.post(
            url, data,
            {
                onStart: () => isLoadingPayment.value = true,
                onFinish: () => {
                    isLoadingPayment.value = false
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
                },
                onError: (error) => {
                    errorPaymentMethodRefund.value = error
                    notify({
                        title: trans("Something went wrong"),
                        text: error.message,
                        type: "error"
                    })
                },
                preserveScroll: true
            }
        )

    } catch (error: unknown) {
        errorPaymentMethodRefund.value = error
    }
}


const onSubmitPaymentRefund = () => {
    let url
    if (paymentRefund.value.payment_method === "credit_balance") {
        url = route("grp.models.refund.refund_to_credit", {
            refund: props.invoice_pay.invoice_id
        })
        sendSubmitPaymentRefund(url, {
            amount: paymentRefund.value.payment_amount
        })
    }
}

const onSubmitRefundToPaymentsMethod = (form, data: any) => {
    if (data.selected_action === 'manual') {
        onClickManual(data)
    } else if (data.selected_action === 'balance') {
        onClickBalance(data, form)
    } else if (data.selected_action === 'automatic') {
        onClickAutomatic(data, form.refund_amount)
    }
    
    // let url, finalData
    // if (paymentRefund.value.payment_method === "invoice_payment_method") {
    //     url = route("grp.models.refund.refund_to_payment_account", {
    //         refund: props.invoice_pay.invoice_id,
    //         paymentAccount: data.payment_account_slug
    //     })
    //     finalData = {
    //         amount: form.refund_amount,
    //         original_payment_id: data.id,
    //         reference: data.reference
    //     }
    //     console.log('fffozzzzrm', finalData)

    //     router.post(
    //         url, finalData,
    //         {
    //             onStart: () => data.processing = true,
    //             onFinish: () => data.processing = false,
    //             onSuccess: () => {
    //                 if (_PureTable.value) _PureTable.value.fetchData()
    //                 if (props.invoice_pay.total_need_to_refund_in_payment_method == 0) {
    //                     isOpenModalRefund.value = false
    //                 }
    //                 notify({
    //                     title: trans("Success"),
    //                     text: "Successfully add payment invoice",
    //                     type: "success"
    //                 })
    //             },
    //             onError: (error) => {
    //                 console.log(error)
    //                 notify({
    //                     title: trans("Something went wrong"),
    //                     text: error.message,
    //                     type: "error"
    //                 })
    //             }
    //         }
    //     )
    // }
}

watch(paymentRefund, () => {
    if (errorPaymentMethodRefund.value) {
        errorPaymentMethodRefund.value = null
    }
})

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
//         })
//     } else {
//         return route("grp.org.accounting.invoices.show.refunds.show", {
//             organisation: route().params?.organisation,
//             invoice: props.invoice_pay.invoice_slug,
//             refund: refundSlug
//         })
//     }

// }


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
//         })
//     } else {
//         switch (route().current()) {
//             case 'grp.org.shops.show.dashboard.invoices.refunds.show':
//                 return route("grp.org.shops.show.dashboard.invoices.show", {
//                     organisation: route().params?.organisation,
//                     shop: route().params?.shop,
//                     invoice: props.invoice_pay.invoice_slug
//                 })
//             default:
//                 return route("grp.org.accounting.invoices.show", {
//                     organisation: route().params?.organisation,
//                     invoice: props.invoice_pay.invoice_slug
//                 })
//         }
//     }
// }

// const generateShowOrderRoute = () => {
//     return route("grp.org.shops.show.ordering.orders.show", {
//         organisation: route().params?.organisation,
//         shop: props.invoice_pay.shop_slug,
//         order: props.invoice_pay.order_slug
//     })
// }

const totalAmount = computed(() => {
    return _PureTable.value ? _PureTable.value?.data.reduce((sum, item) => sum + Number(item.amount || 0), 0) : 0
})

const totalRefunded = computed(() => {
    return _PureTable.value ? _PureTable.value?.data.reduce((sum, item) => sum + Number(item.refunded || 0), 0) : 0
})


const maxRefund = (data) => {
    if (!data) return 0
    const maxPossible = data.amount - data.refunded
    return Math.min(maxPossible, -props.invoice_pay.total_need_to_refund_in_payment_method || data.amount)
}

const onClickRefundPayments = () => {
    isOpenModalRefund.value = true
    if (props.invoice_pay.total_need_to_refund_in_payment_method < 0)
        paymentRefund.value.payment_method = "invoice_payment_method"
    else if (props.invoice_pay.total_need_to_refund_in_payment_method <= 0)
        paymentRefund.value.payment_method = "credit_balance"
}

const listPaymentRefund = computed(() => [
    {
        label: trans("Refund all to customer's credit balance"),
        value: "credit_balance",
        disable: false
    },
    {
        label: trans("Refund to original payment method"),
        value: "invoice_payment_method",
        disable: Number(props.invoice_pay.total_need_to_refund_in_payment_method) >= 0
    }
])

const setRefundAllOutsideFulfilmentShop = (value, index) => {
    if (_formCell.value[index])
        _formCell.value[index].form.refund_amount = -value
};

const listLoadingIconActions = ref<string[]>([])
const onClickManual = (paymentMethod) => {
    // router[paymentMethod.balance_refund_route.method || 'patch'](
    //     route(paymentMethod.manual_refund_route.name, paymentMethod.manual_refund_route.parameters),
    //     {
    //         data: 'qqq'
    //     },
    //     {
    //         preserveScroll: true,
    //         preserveState: true,
    //         onStart: () => { 
    //             listLoadingIconActions.value.push(paymentMethod.id)
    //         },
    //         onSuccess: () => {
    //             notify({
    //                 title: trans("Success"),
    //                 text: trans("Successfully submit the data"),
    //                 type: "success"
    //             })
    //         },
    //         onError: errors => {
    //             notify({
    //                 title: trans("Something went wrong"),
    //                 text: trans("Failed to set location"),
    //                 type: "error"
    //             })
    //         },
    //         onFinish: () => {
    //             listLoadingIconActions.value = listLoadingIconActions.value.filter(i => i !== paymentMethod.id)
    //         },
    //     }
    // )
    console.log('Manual clicked', paymentMethod)
}

const onClickBalance = (paymentMethod, form) => {
    console.log('Manual clicked', paymentMethod)
    console.log('zzzz', paymentMethod.balance_refund_route.name)

    form
    .transform((data) => ({
        amount: data.refund_amount,
        invoice_id: props.refund?.id
    }))
    .submit(
        paymentMethod.balance_refund_route.method || "post",
        route(paymentMethod.balance_refund_route.name, paymentMethod.balance_refund_route.parameters),
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                listLoadingIconActions.value.push(`${paymentMethod.id}-balance`)
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to submit refund balance"),
                    type: "error"
                })
            },
            onFinish: () => {
                listLoadingIconActions.value = listLoadingIconActions.value.filter(i => i !== `${paymentMethod.id}-balance`)
            },
        },
    )
}

const onClickAutomatic = (paymentMethod, loadingKey: string) => {
    // router[paymentMethod.balance_refund_route.method || 'patch'](
    //     route(paymentMethod.api_refund_route.name, paymentMethod.api_refund_route.parameters),
    //     {
    //         data: 'qqq'
    //     },
    //     {
    //         preserveScroll: true,
    //         preserveState: true,
    //         onStart: () => { 
    //             listLoadingIconActions.value.push(`${paymentMethod.id}-${loadingKey}`)
    //         },
    //         onSuccess: () => {
    //             notify({
    //                 title: trans("Success"),
    //                 text: trans("Successfully submit the data"),
    //                 type: "success"
    //             })
    //         },
    //         onError: errors => {
    //             notify({
    //                 title: trans("Something went wrong"),
    //                 text: trans("Failed to set location"),
    //                 type: "error"
    //             })
    //         },
    //         onFinish: () => {
    //             listLoadingIconActions.value = listLoadingIconActions.value.filter(i => i !== `${paymentMethod.id}-${loadingKey}`)
    //         },
    //     }
    // )
    console.log('Manual clicked', paymentMethod)
}
</script>

<template>
    <dd class="relative w-full flex flex-col border rounded-md border-gray-300 overflow-hidden">
        <dl class="">

            <!-- Field: Total -->
            <!-- <div v-if="invoice_pay.order_reference"
                class="border-b border-gray-300 px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt v-tooltip="invoice?.reference ? trans('Total of invoice :invoice', { invoice: invoice?.reference }) : ''"
                    class="text-sm/6 font-medium ">
                    {{ trans("Total") }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_invoice)) }}
                </dd>
            </div> -->


            <!-- Field: Excess payment -->
            <div v-if="Number(invoice_pay.total_excess_payment) > 0" class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium" v-tooltip="trans('Auto add to customer balance')">
                        {{ trans("Excess Payment") }}
                    </dt>
                    <dd class="mt-1 text-sm/6 sm:mt-0 text-right text-gray-700">
                        {{
                            locale.currencyFormat(invoice_pay.currency_code,
                                Number(invoice_pay.total_excess_payment))
                        }}
                    </dd>
                </div>
            </div>


            <!-- Field: Payed in -->
            <div class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium underline cursor-pointer" :style="{ padding: 0 }"
                        @click="() => emits('onPayInOnClick')">
                        {{ trans("Payed in") }}
                    </dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_paid_in)) }}
                    </dd>
                </div>
            </div>

            <!-- Field: Total to refund -->
            <div v-if="Number(invoice_pay.total_need_to_pay) != 0"
                class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    <!-- {{ Number(invoice_pay.total_need_to_pay) < 0 ? "Total to refund" : "Total to pay" }} -->
                    Total to refund
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    <template v-if="layout.app.environment === 'local'">
                        <button v-if="Number(invoice_pay.total_need_to_pay) > 0"
                            @click="() => (isOpenModalInvoice = true, fetchPaymentMethod())" size="xxs"
                            class="secondaryLink text-indigo-500">
                            Pay Invoice
                        </button>
                        <button v-else-if="Number(invoice_pay.total_need_to_pay) < 0" @click="onClickRefundPayments"
                            size="xxs" class="secondaryLink text-indigo-500">
                            Refund payment
                        </button>
                    </template>

                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0"
                        v-tooltip="trans('No need to pay anything')" icon="far fa-check" class="text-green-500"
                        fixed-width aria-hidden="true" />
                    <span class="ml-2" xclass="[Number(invoice_pay.total_need_to_pay) < 0 ? 'text-red-500' : '', 'ml-2']">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_need_to_pay)) }}
                    </span>
                </dd>
            </div>

            <!-- Field: Paid -->
            <div v-else class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    {{ trans("Paid") }}
                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0"
                        v-tooltip="trans('No need to pay anything')" icon="far fa-check" class="text-green-500"
                        fixed-width aria-hidden="true" />
                </dt>
            </div>

            <div class="px-2 pb-2">
                <Message severity="error" class="">
                    <div class="ml-2 text-xs font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
                        <div>
                            <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-sm" fixed-width aria-hidden="true"/>
                            <div class="ml-1 inline items-center gap-x-2">
                                {{ trans("Sorry, the refund payment is not available until Tuesday, Sept 30th 2025") }}
                            </div>
                        </div>
                    </div>
                </Message>
            </div>
        </dl>

        <!-- Modal: Pay refund -->
        <Dialog v-model:visible="isOpenModalRefund" :style="{ width: '100%', maxWidth: '1000px', position: 'relative' }"
            maximizable modal :draggable="false" :dismissableMask="true">
            <template #header>
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ trans("Refund Payment") }}</h2>
                </div>
            </template>
            <div class="isolate bg-white px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                    <!-- <div class="col-span-2">
                        <label for="first-name" class="block text-sm font-medium leading-6">
                            <span class="text-red-500">*</span> {{ trans("Select refund method") }}
                        </label>
                        <div class="mt-1 grid grid-cols-2 gap-x-3">
                            <div @click="() => !item.disable ? paymentRefund.payment_method = item.value : null"
                                v-for="item in listPaymentRefund" :key="item.value"
                                class="flex justify-center items-center border px-3 py-2 rounded text-center cursor-pointer transition"
                                :class="[
                                    paymentRefund.payment_method === item.value ? 'bg-indigo-200 border-indigo-400' : 'border-gray-300',
                                    item.disable ? 'opacity-50 cursor-not-allowed bg-gray-100 border-gray-200 text-gray-500' : 'hover:bg-indigo-100'
                                ]">
                                {{ item.label }}
                            </div>
                        </div>
                    </div> -->

                    <div class="col-span-2">
                        <!-- Table Container -->
                        <div class="overflow-x-auto border-t border-gray-300">
                            <PureTable ref="_PureTable" :route="routes.payments" :blueprint="BluePrintTableRefund"
                                :tableProps="{
                                    size: 'small',
                                    showGridlines: true
                                }" class="w-full">

                                <!-- Column: amount -->
                                <template #amount="{ data, index }">
                                    <div @click="() => set(_formCell, [index, 'form', 'refund_amount'], data.amount)" v-tooltip="trans('Click to fill the input')" class="w-fit font-medium cursor-pointer">
                                        {{ useLocaleStore().currencyFormat(data.currency_code, data.amount) }}
                                    </div>
                                    
                                    <button
                                        v-if="data.amount > -props.invoice_pay.total_need_to_refund_in_payment_method && props.invoice_pay.total_need_to_refund_in_payment_method != 0"
                                        @click="() => setRefundAllOutsideFulfilmentShop(props.invoice_pay.total_need_to_refund_in_payment_method, index)"
                                        :disabled="false"
                                        class="px-2 py-1 text-xs bg-gray-300 rounded disabled:bg-gray-300 disabled:cursor-not-allowed hover:text-blue-500 disabled:hover:bg-gray-300 transition">
                                        Pay {{
                                            locale.currencyFormat(invoice_pay.currency_code,
                                                props.invoice_pay.total_need_to_refund_in_payment_method)
                                        }}
                                    </button>
                                </template>

                                <!-- Refunded Column -->
                                <template #payment_account_name="{ data }">
                                    <div class="">
                                        <div>{{data.payment_account_name}}</div>
                                        <div class="text-gray-500 italic text-xs">
                                            {{data.reference}}
                                        </div>
                                    </div>
                                </template>

                                <!-- Refunded Column -->
                                <template #refunded="{ data }">
                                    <div class="text-gray-500">
                                        {{ useLocaleStore().currencyFormat(data.currency_code, data.refunded) }}
                                    </div>
                                </template>

                                <!-- Column: Actions -->
                                <template #actions="{ data, index }">
                                    <div class="min-w-64 w-fit">
                                        <div v-if="layout.app.environment === 'local'" class="text-gray-500 flex gap-x-2 border-b border-gray-300 mb-1">
                                            <div @click="() => set(data, 'selected_action', 'manual')" v-tooltip="trans('Manual Refund (you wlll need to process refund externally and provide a transaction id)')" class="hover:text-blue-700 cursor-pointer" :class="get(data, 'selected_action', null) === 'manual' ? 'text-blue-700' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'manual' ? 'fas fa-digging' : 'fal fa-digging'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                            <div @click="() => set(data, 'selected_action', 'balance')" v-tooltip="trans('Refund to customer balance')" aclick="() => onClickBalance(data, 'balance')" class="hover:text-blue-700 cursor-pointer" :class="get(data, 'selected_action', null) === 'balance' ? 'text-blue-700' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'balance' ? 'fas fa-piggy-bank' : 'fal fa-piggy-bank'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                            <div v-if="data.api_refund" @click="() => set(data, 'selected_action', 'automatic')" v-tooltip="trans('Refund by API')" aclick="() => onClickAutomatic(data, 'automatic')" class="hover:text-blue-700 cursor-pointer" :class="get(data, 'selected_action', null) === 'automatic' ? 'text-blue-700' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'automatic' ? 'fas fa-robot' : 'fal fa-robot'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                        </div>

                                        <div v-if="data.selected_action === 'manual'" class="mb-2">
                                            <InputText
                                                :modelValue="get(data, 'manual_refund_id', '')"
                                                @input="(e) => get(data, 'manual_refund_id', e.target.value)"
                                                size="small"
                                                placeholder="Transaction ID"
                                            />
                                        </div>

                                        <div v-if="data.selected_action">
                                            <ActionCell
                                                v-if="(data.amount - data.refunded) > 0 && props.invoice_pay.total_need_to_refund_in_payment_method !== 0"
                                                :ref="(e) => _formCell[index] = e"
                                                v-model="data.refund"
                                                @input="(e) => data.amount = e.value"
                                                @update:model-value="(e) => data.amount = e"
                                                :max="maxRefund(data)"
                                                :min="0"
                                                noIcon
                                                :currency="invoice_pay.currency_code"
                                                @refund="(form) => onSubmitRefundToPaymentsMethod(form, data)"
                                            />
                                            <span v-else class="text-gray-400 font-medium italic">
                                                {{ trans("Refund Complete") }}
                                            </span>
                                        </div>
                                    </div>
                                </template>

                                <!-- Footer Section -->
                                <!-- <template #footer>
                                    <ColumnGroup type="footer">
                                        <Row class="bg-gray-50 border-t border-gray-300">
                                            <Column footer="Totals:"
                                                footerClass="text-right font-semibold text-gray-700" />
                                            <Column
                                                :footer="useLocaleStore().currencyFormat(invoice_pay.currency_code, totalAmount)"
                                                footerClass="font-medium text-gray-700" />
                                            <Column
                                                :footer="useLocaleStore().currencyFormat(invoice_pay.currency_code, totalRefunded)"
                                                footerClass="font-medium text-gray-700" />
                                            <Column footer="" />
                                        </Row>
                                    </ColumnGroup>
                                </template> -->
                            </PureTable>
                        </div>
                    </div>
                </div>

                
            </div>

        </Dialog>
    </dd>
</template>
