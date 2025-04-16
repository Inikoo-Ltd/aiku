<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { Link } from '@inertiajs/vue3'

import { computed, defineAsyncComponent, inject, ref, watch } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import AddressSelector from "@/Components/DataDisplay/AddressSelector.vue"
import ModelDetails from "@/Components/ModelDetails.vue"
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue"
import OperationsInvoiceShowcase from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceTransactions.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from 'laravel-vue-i18n'
import BoxStatPallet from '@/Components/Pallet/BoxStatPallet.vue'
import { Calculation, ProductTransaction } from '@/types/Invoices'
import { routeType } from '@/types/route'
import OrderSummary from '@/Components/Summary/OrderSummary.vue'
import { FieldOrderSummary } from '@/types/Pallet'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faIdCardAlt, faMapMarkedAlt, faPhone, faChartLine, faCreditCard, faCube, faFolder, faPercent, faCalendarAlt, faDollarSign, faMapMarkerAlt, faPencil, faDraftingCompass } from '@fal'
import { faClock, faFileInvoice, faFilePdf } from '@fas'
import { faCheck } from '@far'
import { usePage } from '@inertiajs/vue3';

library.add(faCheck, faIdCardAlt, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine, faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faDollarSign, faFilePdf, faMapMarkerAlt, faPencil, faDraftingCompass)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))


// import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from '@/Composables/useFormatTime'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import TableInvoiceTransactions from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceTransactions.vue";
import { Address } from '@/types/PureComponent/Address'
import { Icon } from '@/types/Utils/Icon'
// import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { InvoiceResource } from '@/types/invoice'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import NeedToPay from '@/Components/Utils/NeedToPay.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
// const locale = useLocaleStore()
const locale = inject('locale', aikuLocaleStructure)


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }

    box_stats: {
        customer: {
            company_name: string
            contact_name: string
            route: routeType
            location: string[]
            phone: string
            reference: string
            slug: string
        }
        information: {
            recurring_bill: {
                reference: string
                route: routeType
            }
            routes: {
                fetch_payment_accounts: routeType
                submit_payment: routeType
            }
            paid_amount: number | null
            pay_amount: number | null
        }
    }
    exportPdfRoute: routeType
    order_summary: FieldOrderSummary[][]
    recurring_bill_route: routeType
    invoice: InvoiceResource
    items: {}
    payments: {}
    details: {}
    history: {}

    outbox: {
        state: string
        workshop_route: routeType
    }
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        items: TableInvoiceTransactions,
        payments: TablePayments,
        details: ModelDetails,
        history: ModelChangelog,
    }

    return components[currentTab.value]
})



// Section: Payment invoice
const listPaymentMethod = ref([])
const isLoadingFetch = ref(false)
const fetchPaymentMethod = async () => {
    try {
        isLoadingFetch.value = true
        const { data } = await axios.get(route(props.box_stats.information.routes.fetch_payment_accounts.name, props.box_stats.information.routes.fetch_payment_accounts.parameters))
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

const paymentData = ref({
    payment_method: null as number | null,
    payment_amount: 0 as number | null,
    payment_reference: ''
})
const isOpenModalPayment = ref(false)
const isLoadingPayment = ref(false)
const errorPaymentMethod = ref<null | unknown>(null)
const onSubmitPayment = () => {
    try {
        router[props.box_stats.information.routes.submit_payment.method || 'post'](
            route(props.box_stats.information.routes.submit_payment.name, {
                ...props.box_stats.information.routes.submit_payment.parameters,
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
                }
            }
        )

    } catch (error: unknown) {
        errorPaymentMethod.value = error
    }
}

watch(paymentData, () => {
    if (errorPaymentMethod.value) {
        errorPaymentMethod.value = null
    }
})

// Section: Send Invoice
const isVisitWorkshopOutbox = ref(false)
const isModalSendInvoice = ref(false)
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">

        <!-- Button: PDF -->
        <template #otherBefore>
            <a v-if="exportPdfRoute?.name" :href="route(exportPdfRoute.name, exportPdfRoute.parameters)" target="_blank"
                class="mt-4 sm:mt-0 sm:flex-none text-base" v-tooltip="trans('Download in')">
                <Button label="PDF" icon="fas fa-file-pdf" type="tertiary" />
            </a>
        </template>

        <!-- Button: delete Refund -->
        <template v-if="outbox.state === 'in_process'" #button-send-invoice="{ action }">
            <Button
                @click="() => isModalSendInvoice = true"
                :style="action.style"
                :label="action.label"
                :icon="action.icon"
                :loading="isVisitWorkshopOutbox"
                :iconRight="action.iconRight"
                :key="`ActionButton${action.label}${action.style}`"
                :tooltip="action.tooltip"
            />
        </template>
    </PageHeading>

    <div class="grid grid-cols-4 divide-x divide-gray-300 border-b border-gray-200">
        <!-- Box: Customer -->
        <BoxStatPallet class=" py-2 px-3" icon="fal fa-user">

            <!-- Field: Registration Number -->
            <Link as="a" v-if="box_stats?.customer.reference" :href="route(box_stats?.customer.route.name, box_stats?.customer.route.parameters)"
                class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
                <dt v-tooltip="'Company name'" class="flex-none">
                    <span class="sr-only">Registration number</span>
                    <FontAwesomeIcon icon='fal fa-id-card-alt' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">#{{ box_stats?.customer.reference }}</dd>
            </Link>

            <!-- Field: Contact name -->
            <div v-if="box_stats?.customer.contact_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Contact name'" class="flex-none">
                    <span class="sr-only">Contact name</span>
                    <FontAwesomeIcon icon='fal fa-user' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ box_stats?.customer.contact_name }}</dd>
            </div>

            <!-- Field: Company name -->
            <div v-if="box_stats?.customer.company_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Company name'" class="flex-none">
                    <span class="sr-only">Company name</span>
                    <FontAwesomeIcon icon='fal fa-building' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ box_stats?.customer.company_name }}</dd>
            </div>

            <!-- Field: Tax number -->
            <!-- <div v-if="box_stats?.customer.tax_number"
                class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Email'" class="flex-none">
                    <span class="sr-only">Tax Number</span>
                    <FontAwesomeIcon icon='fal fa-passport' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ box_stats?.customer.tax_number }}</dd>
            </div> -->

            <!-- Field: Location -->
            <!-- <div v-if="box_stats?.customer.location"
                class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Location'" class="flex-none">
                    <span class="sr-only">Location</span>
                    <FontAwesomeIcon icon='fal fa-map-marked-alt' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">
                    <AddressLocation :data="box_stats?.customer.location" />
                </dd>
            </div> -->

            <!-- Field: Phone -->
            <div v-if="box_stats?.customer.phone" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Phone'" class="flex-none">
                    <span class="sr-only">Phone</span>
                    <FontAwesomeIcon icon='fal fa-phone' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ box_stats?.customer.phone }}</dd>
            </div>

            <!-- Field: Address -->
            <div class="pl-1 flex items-start w-full gap-x-2">
                <dt v-tooltip="'Phone'" class="flex-none">
                    <span class="sr-only">Phone</span>
                    <FontAwesomeIcon icon='fal fa-map-marker-alt' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>

                <dd class="text-base text-gray-500 w-full">
                    <div v-if="invoice.address" class="relative bg-gray-50 border border-gray-300 rounded px-2 py-1">
                        <div v-html="invoice.address.formatted_address" />
                    </div>

                    <div v-else class="text-gray-400 italic">
                        No address
                    </div>
                </dd>
            </div>
        </BoxStatPallet>

        <!-- Section: Detail -->
        <BoxStatPallet class="py-2 px-3">
            <div class="mt-1">
                <div v-tooltip="'Recurring bill'"
                    class="w-fit flex items-center flex-none gap-x-2">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-receipt' fixed-width aria-hidden='true' class="text-gray-500" />
                    </dt>
                    <component :is="box_stats.information.recurring_bill?.route?.name ? Link : 'div'"
                        as="dd"
                        :href="box_stats.information.recurring_bill?.route?.name ? route(box_stats.information.recurring_bill?.route?.name, box_stats.information.recurring_bill.route.parameters) : ''"
                        class="text-base text-gray-500"
                        :class="box_stats.information.recurring_bill?.route?.name ? 'cursor-pointer primaryLink' : ''">
                        {{ box_stats.information.recurring_bill?.reference || '-' }}
                    </component>
                </div>

                <div v-tooltip="'Invoice created'"
                    class="flex items-center w-full flex-none gap-x-2">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-calendar-alt' fixed-width aria-hidden='true' class="text-gray-500" />
                    </dt>
                    <dd class="text-base text-gray-500" :class='"ff"'>
                        {{ useFormatTime(props.invoice.date) }}
                    </dd>
                </div>

                <div class="relative flex items-start w-full flex-none gap-x-2">
                    <dt class="flex-none pt-1">
                        <FontAwesomeIcon icon='fal fa-dollar-sign' fixed-width aria-hidden='true' class="text-gray-500" />
                    </dt>
                    <NeedToPay
                        @click="() => Number(box_stats.information.pay_amount) > 0 ? (isOpenModalPayment = true, fetchPaymentMethod()) : false"
                        :totalAmount="Number(props.invoice.total_amount)"
                        :paidAmount="Number(box_stats.information.paid_amount)"
                        :payAmount="Number(box_stats.information.pay_amount)"
                        :currencyCode="invoice.currency_code"
                        :class="[Number(box_stats.information.pay_amount) ? 'hover:bg-gray-100 cursor-pointer' : '']"
                    />


                </div>
            </div>
        </BoxStatPallet>

        <!-- Section: Order Summary -->
        <BoxStatPallet class="col-start-3 col-span-2 py-2 px-3">
            <OrderSummary :order_summary :currency_code="invoice.currency_code" />
        </BoxStatPallet>

        <!-- Section: Invoice Information (looping) -->
        <!-- <BoxStatPallet class="col-start-4 py-2 px-3"')">
            <div class="pt-1 text-gray-500">
                <template v-for="invoiceGroup in boxInvoiceInformation">
                    <div class="space-y-1">
                        <div v-for="invoice in invoiceGroup" class="flex justify-between"
                            :class="invoice.label == 'Total' ? 'font-semibold' : ''"
                        >
                            <div>{{ invoice.label }} <span v-if="invoice.label == 'Tax'" class="text-sm text-gray-400">(VAT {{invoice.tax_percentage || 0}}%)</span></div>
                            <div>{{ locale.currencyFormat(box_stats?.currency, invoice.value || 0) }}</div>
                        </div>
                    </div>
                    <hr class="last:hidden my-1.5 border-gray-300">
                </template>
            </div>
        </BoxStatPallet> -->
    </div>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" />

    <Modal :isOpen="isOpenModalPayment" @onClose="isOpenModalPayment = false" width="w-[600px]">
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
                    <div class="mt-1">
                        <PureMultiselect
                            v-model="paymentData.payment_method"
                            :options="listPaymentMethod"
                            :isLoading="isLoadingFetch"
                            label="name"
                            valueProp="id"
                            required
                            caret
                        />
                    </div>
                </div>

                <div class="col-span-2">
                    <label for="last-name" class="block text-sm font-medium leading-6">{{ trans('Payment amount') }}</label>
                    <div class="mt-1">
                        <PureInputNumber v-model="paymentData.payment_amount" />
                    </div>
                    <div class="space-x-1">
                        <span class="text-xxs text-gray-500">{{ trans('Need to pay') }}: {{ locale.currencyFormat(props.invoice.currency_code || 'usd', Number(box_stats.information.pay_amount)) }}</span>
                        <Button @click="() => paymentData.payment_amount = box_stats.information.pay_amount" :disabled="paymentData.payment_amount === box_stats.information.pay_amount" type="tertiary" label="Pay all" size="xxs" />
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
                <Button @click="() => onSubmitPayment()" label="Submit" :disabled="!(!!paymentData.payment_method)" :loading="isLoadingPayment" full />
                <Transition name="spin-to-down">
                    <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{ errorPaymentMethod }}</p>
                </Transition>
            </div>
        </div>
    </Modal>

    <Modal :isOpen="isModalSendInvoice" @onClose="isModalSendInvoice = false" width="w-[600px]">
        <div>
            <EmptyState
                :data="{
                    title: trans('Outbox is still in process'),
                    description: trans('You can edit it in workshop')
                }"
                class="py-7"
            >
                <template #button-empty-state>
                    <Link :href="route(outbox.workshop_route.name, outbox.workshop_route.parameters)" @start="() => isVisitWorkshopOutbox = true" class="mt-4 block w-fit mx-auto">
                        <Button
                            label="workshop"
                            type="secondary"
                            icon="fal fa-drafting-compass"
                            :loading="isVisitWorkshopOutbox"
                        />
                    </Link>
                </template>
            </EmptyState>
        </div>
    </Modal>
</template>
