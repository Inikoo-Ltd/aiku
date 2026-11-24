<script setup lang="ts">
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { ctrans } from "@/Composables/useTrans"
import { inject, computed, watch, ref } from "vue"
import { routeType } from "@/types/route"
import { usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faSave } from "@far"
import { faExclamationTriangle } from "@fad"
import { faDigging as fasDigging, faRobot as fasRobot, faPiggyBank as fasPiggyBank } from "@fas"
import { faPlus, faMinus, faArrowRight, faDigging, faRobot, faPiggyBank, faUndoAlt } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import BluePrintTableRefund from "@/Components/Segmented/InvoiceRefund/BlueprintTableRefund"
import PureTable from "@/Components/Pure/PureTable/PureTable.vue"
import Dialog from "primevue/dialog"
import { useLocaleStore } from "@/Stores/locale"
import ActionCell from "./ActionCell.vue"
import { InputText, Message } from "primevue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { get, set } from "lodash"
import { InvoiceResource } from "@/types/invoice"

const screenType = inject('screenType', ref('desktop'))

library.add(fasDigging, fasRobot, fasPiggyBank, faExclamationTriangle, faCheck, faSave, faPlus, faMinus, faArrowRight, faDigging, faRobot, faPiggyBank, faUndoAlt)


const props = defineProps<{
    refund?: InvoiceResource
    invoice_pay: {
        can_refund_payments: boolean
        can_refund_by_api?: boolean
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
    is_tax_only?: boolean
    handleTabUpdate: Function
}>()


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
        onClickAutomatic(data, form)
    }
}



const maxRefund = (data) => {
    if (!data) return 0
    
    if(props.is_tax_only){
        return -(props.invoice_pay.total_need_to_pay);
    }

    const maxPossible = data.amount - data.refunded
    return Math.min(maxPossible, leftToPayOnRefund.value)
}

const leftToPayOnRefund = computed(() => Math.max(0, -Number(props.invoice_pay.total_need_to_pay)))

const errorText = (errors: Record<string, string>, fallback: string) => Object.values(errors ?? {}).join(", ") || fallback

const onClickRefundPayments = () => {
    isOpenModalRefund.value = true
}

const listLoadingIconActions = ref<string[]>([])
const refundErrors = ref<Record<number, string>>({})

const submitRefund = (paymentMethod, form, refundRoute: routeType, loadingKey: string, fallbackError: string, payload: (data: any) => object) => {
    form
    .transform(payload)
    .submit(
        refundRoute.method || "post",
        route(refundRoute.name, refundRoute.parameters),
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                delete refundErrors.value[paymentMethod.id]
                listLoadingIconActions.value.push(`${paymentMethod.id}-${loadingKey}`)
            },
            onSuccess: () => {
                form.defaults()
                props.handleTabUpdate('payments')
                _PureTable.value?.fetchData()
            },
            onError: errors => {
                refundErrors.value[paymentMethod.id] = errorText(errors, fallbackError)
            },
            onFinish: () => {
                listLoadingIconActions.value = listLoadingIconActions.value.filter(i => i !== `${paymentMethod.id}-${loadingKey}`)
            },
        },
    )
}

const onClickManual = (paymentMethod, form) => {
    submitRefund(paymentMethod, form, paymentMethod.manual_refund_route, "manual", ctrans("Failed to submit manual refund"), (data) => ({
        amount: data.refund_amount,
        reference: paymentMethod.manual_refund_reference,
        invoice_id: props.refund?.id
    }))
}

const onClickBalance = (paymentMethod, form) => {
    submitRefund(paymentMethod, form, paymentMethod.balance_refund_route, "balance", ctrans("Failed to submit refund balance"), (data) => ({
        amount: data.refund_amount,
        invoice_id: props.refund?.id
    }))
}

const onClickAutomatic = (paymentMethod, form) => {
    submitRefund(paymentMethod, form, paymentMethod.api_refund_route, "automatic", ctrans("Failed to refund by API"), (data) => ({
        amount: data.refund_amount,
        invoice_id: props.refund?.id
    }))
}

const compToolTip = computed(() => {
    if (Number(props.invoice_pay.total_need_to_pay).toFixed(2) > 0) {
        return ctrans('Over refunded. Customer need to pay :amount', { amount: locale.currencyFormat(props.invoice_pay.currency_code, Number(props.invoice_pay.total_need_to_pay).toFixed(2)) })
    }

    if (Number(props.invoice_pay.total_need_to_pay).toFixed(2) < 0) {
        return ctrans('Need to refund :amount to customer', { amount: locale.currencyFormat(props.invoice_pay.currency_code, Math.abs(Number(props.invoice_pay.total_need_to_pay)).toFixed(2)) })

    }

    return ''
})
</script>

<template>
    <dd class="relative w-full flex flex-col border rounded-md border-gray-300 overflow-hidden">
        <dl class="">

            <!-- Field: Excess payment -->
            <div v-if="Number(invoice_pay.total_excess_payment) > 0" class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium" v-tooltip="ctrans('Auto add to customer balance')">
                        {{ ctrans("Excess Payment") }}
                    </dt>
                    <dd class="mt-1 text-sm/6 sm:mt-0 text-right text-gray-700">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_excess_payment)) }}
                    </dd>
                </div>
            </div>


            <!-- Field: Expected to refund -->
            <div class="xborder-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium" :style="{ padding: 0 }"
                        @click="() => handleTabUpdate('payments')">
                        {{ ctrans("Expected to refund") }}
                    </dt>
                    <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_invoice)) }}
                    </dd>
                </div>
            </div>


            <!-- Field: Payed in -->
            <div class="border-b border-gray-300">
                <div class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                    <dt class="text-sm/6 font-medium underline cursor-pointer" :style="{ padding: 0 }"
                        @click="() => handleTabUpdate('payments')">
                        {{ ctrans("Payments") }}
                    </dt>
                    <dd v-tooltip="ctrans('Amount refunded to customer')" class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Math.abs(Number(invoice_pay.total_paid_in))) }}
                    </dd>
                </div>
            </div>


            <!-- Field: Total to refund -->
            <div v-if="Number(invoice_pay.total_need_to_pay) != 0"
                class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    {{ ctrans("Total to refund") }}
                </dt>
                <dd class="mt-1 text-sm/6 text-gray-700 sm:mt-0 text-right">
                    <!-- <div xv-if="layout.app.environment === 'local'"> -->
                        <Button v-if="Number(invoice_pay.total_need_to_pay) < 0 && invoice_pay.can_refund_payments" @click="onClickRefundPayments"
                            type="tertiary" size="xs" :label="ctrans('Refund payment')">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-undo-alt" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                            </template>
                            <template #iconRight>
                                <span v-if="invoice_pay.can_refund_by_api" v-tooltip="ctrans('Can be refunded by Checkout.com to what the customer paid with')" class="rounded border border-gray-400 px-1 text-[10px] font-semibold leading-4 text-gray-600">API</span>
                            </template>
                        </Button>
                    <!-- </div> -->

                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0"
                        v-tooltip="ctrans('No need to pay anything')" icon="far fa-check" class="text-green-500"
                        fixed-width aria-hidden="true" />
                    <span v-tooltip="compToolTip" class="ml-2 whitespace-nowrap" xclass="[Number(invoice_pay.total_need_to_pay) < 0 ? 'text-red-500' : '', 'ml-2']">
                        {{ locale.currencyFormat(invoice_pay.currency_code, Number(invoice_pay.total_need_to_pay).toFixed(2)) }}
                    </span>
                </dd>
            </div>


            <!-- Field: Paid -->
            <div v-else class="px-4 py-1 flex justify-between sm:gap-4 sm:px-3">
                <dt class="text-sm/6 font-medium">
                    {{ ctrans("Paid") }}
                    <FontAwesomeIcon v-if="Number(invoice_pay.total_need_to_pay) == 0"
                        v-tooltip="ctrans('No need to pay anything')" icon="far fa-check" class="text-green-500"
                        fixed-width aria-hidden="true" />
                </dt>
            </div>


            <!-- <div class="px-2 pb-2">
                <Message severity="error" class="">
                    <div class="px-1 text-xs font-normal flex flex-col gap-x-4 items-center sm:flex-row justify-between w-full">
                        <div>
                            <FontAwesomeIcon icon="fad fa-exclamation-triangle" class="text-sm" fixed-width aria-hidden="true"/>
                            <div class="ml-1 inline items-center gap-x-2">
                                {{ ctrans("Sorry, the refund payment is not available until Wednesday, October 1st 2025") }}
                            </div>
                        </div>
                    </div>
                </Message>
            </div> -->
        </dl>

        <!-- Modal: Pay refund -->
        <Dialog v-model:visible="isOpenModalRefund" :style="{ width: '100%', maxWidth: '1000px', position: 'relative' }"
            maximizable modal :draggable="false" :dismissableMask="screenType === 'desktop'">
            <template #header>
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-lg font-bold tracking-tight sm:text-2xl">{{ ctrans("Refund Payment") }}</h2>
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
                                        {{ useLocaleStore().currencyFormat(data.currency_code, is_tax_only ? -(invoice_pay.total_need_to_pay) : data.amount) }}
                                    </div>

                                    <!-- Text: available refund -->
                                    <div v-if="data.refunded > 0">
                                        <div class="text-gray-500 text-xs">
                                            {{ ctrans("Refunded") }}: {{ useLocaleStore().currencyFormat(data.currency_code, data.refunded) }}
                                        </div>
                                        <div @click="() => set(_formCell, [index, 'form', 'refund_amount'], data.amount-data.refunded)" v-tooltip="ctrans('Click to fill the input')" class="cursor-pointer text-gray-500 hover:text-gray-700 w-fit text-xs">
                                            {{ ctrans("Available to refund") }}: {{ useLocaleStore().currencyFormat(data.currency_code, is_tax_only ? -(invoice_pay.total_need_to_pay) : (data.amount-data.refunded).toFixed(2)) }}
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
                                    <div class="min-w-72 w-fit">
                                        <div xv-if="layout.app.environment === 'local'" class="text-gray-500 flex gap-x-2 border-b border-gray-300 pb-1 mb-2.5">
                                            <div>
                                                {{ ctrans("Select method") }}:
                                            </div>
                                            <div v-if="data.can_manual_refund" @click="() => set(data, 'selected_action', 'manual')" v-tooltip="ctrans('Manual Refund (you will need to process refund externally and provide a transaction id)')" class="hover:text-[var(--app-accent)] cursor-pointer" :class="get(data, 'selected_action', null) === 'manual' ? 'text-[var(--app-accent)]' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'manual' ? 'fas fa-digging' : 'fal fa-digging'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                            <div @click="() => set(data, 'selected_action', 'balance')" v-tooltip="ctrans('Refund to customer balance')" class="hover:text-[var(--app-accent)] cursor-pointer" :class="get(data, 'selected_action', null) === 'balance' ? 'text-[var(--app-accent)]' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'balance' ? 'fas fa-piggy-bank' : 'fal fa-piggy-bank'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                            <div v-if="data.can_api_refund" @click="() => set(data, 'selected_action', 'automatic')" v-tooltip="ctrans('Refund by Checkout.com to what the customer paid with')" class="hover:text-[var(--app-accent)] cursor-pointer" :class="get(data, 'selected_action', null) === 'automatic' ? 'text-[var(--app-accent)]' : ''">
                                                <FontAwesomeIcon :icon="get(data, 'selected_action', '') === 'automatic' ? 'fas fa-robot' : 'fal fa-robot'" class="" fixed-width aria-hidden="true" />
                                            </div>
                                        </div>

                                        <div v-if="!get(data, 'selected_action', null)" class="text-center text-gray-400 text-sm italic mb-2">
                                            {{ ctrans("No method selected") }}
                                        </div>

                                        <div v-if="data.selected_action === 'manual'" class="mb-2">
                                            <div :class="usePage().props.errors?.reference ? 'errorShake' : ''" class="w-fit rounded">
                                                <InputText
                                                    :modelValue="get(data, 'manual_refund_reference', '')"
                                                    @input="(e) => set(data, 'manual_refund_reference', e.target?.value)"
                                                    @update:modelValue="() => set(usePage().props, ['errors', 'reference'], null)"
                                                    size="small"
                                                    :placeholder="ctrans('Transaction ID')"
                                                    :invalid="!!usePage().props.errors?.reference"
                                                />
                                            </div>
                                            <div v-if="usePage().props.errors?.reference" class="text-red-500 text-xs mt-1 italic">
                                                *{{ usePage().props.errors.reference }}
                                            </div>
                                        </div>
                                        <div v-if="data.selected_action">
                                            <ActionCell
                                                v-if="(data.amount - data.refunded) > 0 && (props.is_tax_only || leftToPayOnRefund > 0)"
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
                                                {{ ctrans("Refund Complete") }}
                                            </span>
                                            <div v-if="refundErrors[data.id]" class="text-red-500 text-xs mt-1">
                                                {{ refundErrors[data.id] }}
                                            </div>
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
