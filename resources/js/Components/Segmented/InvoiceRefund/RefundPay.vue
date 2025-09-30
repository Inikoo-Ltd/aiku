<script setup lang="ts">
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { trans } from "laravel-vue-i18n"
import { inject, computed, watch, ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { routeType } from "@/types/route"
import { Link, router, usePage } from "@inertiajs/vue3"
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
    handleTabUpdate: Function
}>()
console.log('popop', props.refund)


const layout = inject('layout', layoutStructure)

const _formCell = ref({})
const locale = inject("locale", aikuLocaleStructure)
const _PureTable = ref(null)


// Section: Payment Refund
const isOpenModalRefund = ref(false)


const onSubmitRefundToPaymentsMethod = (data, form: any) => {
    if (data.selected_action === 'manual') {
        onClickManual(data, form)
    } else if (data.selected_action === 'balance') {
        onClickBalance(data, form)
    } else if (data.selected_action === 'automatic') {
        onClickAutomatic(data, form.refund_amount)
    }
}



const maxRefund = (data) => {
    if (!data) return 0
    const maxPossible = data.amount - data.refunded
    return Math.min(maxPossible, -props.invoice_pay.total_need_to_refund_in_payment_method || data.amount)
}

const onClickRefundPayments = () => {
    isOpenModalRefund.value = true
}

const listLoadingIconActions = ref<string[]>([])
const onClickManual = (paymentMethod, form) => {
    console.log('Manual clicked', paymentMethod)

    form
    .transform((data) => ({
        amount: data.refund_amount,
        reference: paymentMethod.manual_refund_reference,
        invoice_id: props.refund?.id
    }))
    .submit(
        paymentMethod.manual_refund_route.method || "post",
        route(paymentMethod.manual_refund_route.name, paymentMethod.manual_refund_route.parameters),
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                listLoadingIconActions.value.push(`${paymentMethod.id}-manual`)
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit manual refund"),
                    type: "success"
                })
                props.handleTabUpdate('payments')
                _PureTable.value?.fetchData()
            },
            onError: errors => {
                console.error('errors on manual', errors)
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to submit manual refund"),
                    type: "error"
                })
            },
            onFinish: () => {
                listLoadingIconActions.value = listLoadingIconActions.value.filter(i => i !== `${paymentMethod.id}-manual`)
            },
        },
    )
}

const onClickBalance = (paymentMethod, form) => {
    console.log('Balance clicked', paymentMethod)
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
                props.handleTabUpdate('payments')
                _PureTable.value?.fetchData()
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

            <!-- Field: Excess payment -->
            <div v-if="Number(invoice_pay.total_excess_payment) > 0" class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium" v-tooltip="trans('Auto add to customer balance')">
                        {{ trans("Excess Payment") }}
                    </dt>
                    <dd class="mt-1 text-sm/6 sm:mt-0 text-right text-gray-700">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_excess_payment)) }}
                    </dd>
                </div>
            </div>


            <!-- Field: Payed in -->
            <div class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium underline cursor-pointer" :style="{ padding: 0 }"
                        @click="() => handleTabUpdate('payments')">
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
                    {{ trans("Total to refund") }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    <template xv-if="layout.app.environment === 'local'">
                        <button v-if="Number(invoice_pay.total_need_to_pay) < 0" @click="onClickRefundPayments"
                            size="xxs" class="secondaryLink text-indigo-500">
                            {{ trans("Refund payment") }}
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


            <!-- <div class="px-2 pb-2">
                <Message severity="error" class="">
                    <div class="px-1 text-xs font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
                        <div>
                            <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-sm" fixed-width aria-hidden="true"/>
                            <div class="ml-1 inline items-center gap-x-2">
                                {{ trans("Sorry, the refund payment is not available until Wednesday, October 1st 2025") }}
                            </div>
                        </div>
                    </div>
                </Message>
            </div> -->
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
                                    <div class="w-fit font-medium">
                                        {{ useLocaleStore().currencyFormat(data.currency_code, data.amount) }}
                                    </div>

                                    <!-- Text: available refund -->
                                    <div v-if="data.refunded > 0">
                                        <div class="text-gray-500 text-xs">
                                            {{ trans("Refunded") }}: {{ useLocaleStore().currencyFormat(data.currency_code, data.refunded) }}
                                        </div>
                                        <div @click="() => set(_formCell, [index, 'form', 'refund_amount'], data.amount-data.refunded)" v-tooltip="trans('Click to fill the input')" class="cursor-pointer text-gray-500 hover:text-gray-700 w-fit text-xs">
                                            {{ trans("Available to refund") }}: {{ useLocaleStore().currencyFormat(data.currency_code, data.amount-data.refunded) }}
                                        </div>
                                    </div>
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
                                        
                                    </div>
                                </template>

                                <!-- Column: Actions -->
                                <template #actions="{ data, index }">
                                    <div class="min-w-64 w-fit">
                                        <div xv-if="layout.app.environment === 'local'" class="text-gray-500 flex gap-x-2 border-b border-gray-300 mb-1">
                                            <div v-if="data.can_manual_refund" @click="() => set(data, 'selected_action', 'manual')" v-tooltip="trans('Manual Refund (you wlll need to process refund externally and provide a transaction id)')" class="hover:text-blue-700 cursor-pointer" :class="get(data, 'selected_action', null) === 'manual' ? 'text-blue-700' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'manual' ? 'fas fa-digging' : 'fal fa-digging'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                            <div @click="() => set(data, 'selected_action', 'balance')" v-tooltip="trans('Refund to customer balance')" aclick="() => onClickBalance(data, 'balance')" class="hover:text-blue-700 cursor-pointer" :class="get(data, 'selected_action', null) === 'balance' ? 'text-blue-700' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'balance' ? 'fas fa-piggy-bank' : 'fal fa-piggy-bank'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                            <div v-if="data.can_api_refund" @click="() => set(data, 'selected_action', 'automatic')" v-tooltip="trans('Refund by API')" aclick="() => onClickAutomatic(data, 'automatic')" class="hover:text-blue-700 cursor-pointer" :class="get(data, 'selected_action', null) === 'automatic' ? 'text-blue-700' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'automatic' ? 'fas fa-robot' : 'fal fa-robot'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                        </div>

                                        <div v-if="data.selected_action === 'manual'" class="mb-2">
                                            <div :class="usePage().props.errors?.reference ? 'errorShake' : ''" class="w-fit rounded">
                                                <InputText
                                                    :modelValue="get(data, 'manual_refund_reference', '')"
                                                    @input="(e) => set(data, 'manual_refund_reference', e.target?.value)"
                                                    @update:modelValue="() => set(usePage().props, ['errors', 'reference'], null)"
                                                    size="small"
                                                    :placeholder="trans('Transaction ID')"
                                                    :invalid="!!usePage().props.errors?.reference"
                                                />
                                            </div>

                                            <div v-if="usePage().props.errors?.reference" class="text-red-500 text-xs mt-1 italic">
                                                *{{ usePage().props.errors.reference }}
                                            </div>
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
                                                @refund="(form) => onSubmitRefundToPaymentsMethod(data, form)"
                                            />
                                            <span v-else class="text-gray-400 font-medium italic">
                                                {{ trans("Refund Complete") }}
                                            </span>
                                        </div>
                                    </div>
                                </template>
                            </PureTable>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>
    </dd>
</template>
