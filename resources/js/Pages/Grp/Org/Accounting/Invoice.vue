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
import { faIdCardAlt, faMapMarkedAlt, faPhone, faChartLine, faCreditCard, faCube, faFolder, faPercent, faCalendarAlt, faDollarSign, faMapMarkerAlt, faPencil, faDraftingCompass, faEnvelope, faArrowCircleLeft, faTrashAlt } from '@fal'
import { faClock, faFileInvoice, faFileAlt, faFilePdf } from '@fas'
import { faCheck } from '@far'
import { usePage } from '@inertiajs/vue3';

library.add(faCheck, faEnvelope ,faIdCardAlt, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine, faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faDollarSign, faFilePdf, faMapMarkerAlt, faPencil, faFileAlt, faDraftingCompass, faArrowCircleLeft, faTrashAlt)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))


// import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from '@/Composables/useFormatTime'
import { PageHeading as TSPageHeading } from '@/types/PageHeading'
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
import TableDispatchedEmails from '@/Components/Tables/TableDispatchedEmails.vue'
import InputNumber from 'primevue/inputnumber'
import TableItemizedTransactions from '@/Components/Tables/Grp/Org/Accounting/TableItemizedTransactions.vue'
import TableRefunds from '@/Components/Tables/Grp/Org/Accounting/TableRefunds.vue'
import InvoiceRefundPay from '@/Components/Segmented/InvoiceRefund/InvoiceRefundPay.vue'
import ModalAfterConfirmationDelete from '@/Components/Utils/ModalAfterConfirmationDelete.vue'
import ModalSupervisorList from '@/Components/Utils/ModalSupervisorList.vue'
// const locale = useLocaleStore()
const locale = inject('locale', aikuLocaleStructure)


const props = defineProps<{
    title: string,
    pageHead: TSPageHeading
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
    grouped: {}
    itemized: {}
    payments: {}
    email?:{}
    details: {}
    history: {}
    refunds: {}

    outbox: {
        state: string
        workshop_route: routeType
    }
    list_refunds: {}[]
    invoice_pay: {
        routes: {
            fetch_payment_accounts: routeType
            submit_payment: routeType
            payments: routeType
        }
        currency_code: string
        total_invoice: number
        total_paid_account : number
        total_refunds: number
        total_balance: number
        total_paid_in: number
        total_need_to_refund_in_payment_method : number
        total_paid_out: {
            data: {}[]
        }
        total_need_to_pay: number
    }
    exportOptions: {
        type: string
        name: string
        label: string
        parameters: any
        tooltip: string
        icon: Icon
    }[]
}>()
console.log('sdsdsd',props)
const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        grouped: TableInvoiceTransactions,
        itemized: TableItemizedTransactions,
        payments: TablePayments,
        details: ModelDetails,
        history: ModelChangelog,
        email: TableDispatchedEmails,
        refunds: TableRefunds,
    }

    return components[currentTab.value]
})


const onPayInOnClick = () => {
    handleTabUpdate('payments')
}


// Section: Send Invoice
const isVisitWorkshopOutbox = ref(false)
const isModalSendInvoice = ref(false)




const generateRefundRoute = (refundSlug: string) => {
    return route('grp.org.fulfilments.show.operations.invoices.show.refunds.show', {
        organisation: route().params?.organisation,
        fulfilment: route().params?.fulfilment,
        invoice: props.invoice.slug,
        refund: refundSlug
    })
}
</script>


<template>
    <Head :title="capitalize(title)" />
    <!-- <pre>{{exportOptions}}</pre> -->

    <PageHeading :data="pageHead">

        <!-- Button: PDF -->
        <template #otherBefore>
            <div v-if="props.exportOptions?.length" class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit">
                <a v-for="exportOption in props.exportOptions"
                    :href="exportOption.name ? route(exportOption.name, exportOption.parameters) : '#'"
                    target="_blank"
                    class="w-auto mt-0 sm:flex-none text-base"
                    v-tooltip="exportOption.tooltip"
                >
                    <Button
                        :label="exportOption.label"
                        :icon="exportOption.icon"
                        type="tertiary"
                        class="rounded-none border-transparent"
                    />
                </a>
            </div>
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

        <template #button-delete-booked-in="{ action }">
            <div>
                <template v-if="action.supervisor">
                <ModalAfterConfirmationDelete
                    :routeDelete="action.route"
                    :invoice="invoice"
                    isFullLoading
                    isWithMessage
                >
                    <template #default="{ isOpenModal, changeModel }">

                        <Button
                            @click="() => changeModel()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :iconRight="action.iconRight"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                        />

                    </template>
                </ModalAfterConfirmationDelete>
            </template>
            <template v-else>
                    <ModalSupervisorList
                    :routeDelete="action.route"
                    :routeSupervisor="action.supervisors_route"
                    isFullLoading
                    isWithMessage
                >
                    <template #default="{ isOpenModal, changeModel }">

                        <Button
                            @click="() => changeModel()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :iconRight="action.iconRight"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                        />

                    </template>
                    </ModalSupervisorList>
                </template>
            </div>
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

        <!-- Section: Detail (2nd box) -->
        <BoxStatPallet class="col-span-2 py-2 px-3">
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
                    class="flex items-center flex-none gap-x-2 w-fit">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-calendar-alt' fixed-width aria-hidden='true' class="text-gray-500" />
                    </dt>
                    <dd class="text-base text-gray-500" :class='"ff"'>
                        {{ useFormatTime(props.invoice.date) }}
                    </dd>
                </div>

                <div class="relative flex items-start w-full flex-none gap-x-2 mt-2">
                    <!-- <NeedToPay
                        @click="() => Number(box_stats.information.pay_amount) > 0 ? (isOpenModalPayment = true, fetchPaymentMethod()) : false"
                        :totalAmount="Number(props.invoice.total_amount)"
                        :paidAmount="Number(box_stats.information.paid_amount)"
                        :payAmount="Number(box_stats.information.pay_amount)"
                        :currencyCode="invoice.currency_code"
                        :class="[Number(box_stats.information.pay_amount) ? 'hover:bg-gray-100 cursor-pointer' : '']"
                    /> -->
                    
                    <InvoiceRefundPay
                        :invoice_pay
                        @onPayInOnClick="onPayInOnClick"
                        :routes="{
                            submit_route: invoice_pay.routes.submit_payment,
                            fetch_payment_accounts_route: invoice_pay.routes.fetch_payment_accounts,
                            payments : invoice_pay.routes.payments
                        }"
                    />

                </div>
            </div>
        </BoxStatPallet>

        <!-- Section: Order Summary -->
        <BoxStatPallet class="py-2 px-3">
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
