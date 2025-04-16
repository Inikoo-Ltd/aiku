<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { Link } from "@inertiajs/vue3";

import { computed, defineAsyncComponent, inject, ref, watch } from "vue";
import type { Component } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { capitalize } from "@/Composables/capitalize";
import { trans } from "laravel-vue-i18n";
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue";
import { routeType } from "@/types/route";
import OrderSummary from "@/Components/Summary/OrderSummary.vue";
import { FieldOrderSummary } from "@/types/Pallet";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import InvoiceRefundPay from '@/Components/Segmented/InvoiceRefund/InvoiceRefundPay.vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faIdCardAlt, faMapMarkedAlt, faPhone, faChartLine, faCreditCard, faCube, faFolder, faPercent, faCalendarAlt, faDollarSign, faMapMarkerAlt, faPencil, faFileMinus, faUndoAlt, faStarHalfAlt, faArrowCircleLeft } from "@fal";
import { faClock, faFileInvoice, faFilePdf, faArrowAltCircleLeft } from "@fas";
import { faCheck, faTrash } from "@far";

library.add(
  faFileMinus, faUndoAlt, faCheck, faIdCardAlt, faArrowCircleLeft, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine,
  faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faDollarSign, faFilePdf, faArrowAltCircleLeft, faMapMarkerAlt, faPencil, faStarHalfAlt);

const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"));


import { useFormatTime } from "@/Composables/useFormatTime";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import TableInvoiceRefundsTransactions from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceRefundsTransactions.vue";
import TableInvoiceRefundsInProcessTransactions from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceRefundsInProcessTransactions.vue";

import Modal from "@/Components/Utils/Modal.vue";
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue";
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { InvoiceResource } from "@/types/invoice";
import axios from "axios";
import { notify } from "@kyvg/vue3-notification";
import { faHandHoldingUsd } from "@fal";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";

const locale = inject("locale", aikuLocaleStructure);


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
    refund_id: number
  }
  exportPdfRoute: routeType
  order_summary: FieldOrderSummary[][]
  recurring_bill_route: routeType
  invoice_refund: InvoiceResource
  invoice: InvoiceResource
  invoice_pay: {
    routes: {
      fetch_payment_accounts: routeType
      submit_payment: routeType,
      payments : routeType
    }
    currency_code: string
    total_invoice: number
    total_paid_account : number
    total_refunds: number
    total_balance: number
    total_paid_in: number
    total_paid_out: {
      data: {}[]
    }
    total_need_to_pay: number
  }
  items_in_process: {}
  items: {}
  payments: {}
  details: {}
  history: {}
  layout: {
    group: {}
  }
}>();
const currentTab = ref<string>(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);
const _refComponents = ref({})
const component = computed(() => {
  const components: Component = {
    items: TableInvoiceRefundsInProcessTransactions,
    items_in_process: TableInvoiceRefundsInProcessTransactions,
    payments: TablePayments,
    details: ModelDetails,
    history: ModelChangelog
  };

  return components[currentTab.value];
});


// Section: Payment invoice
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
    payment_method: null as string | null,
    payment_account: null as number | null,
    payment_amount: 0 as number | null,
//   payment_reference: ""
});
const isOpenModalPayment = ref(false);
const isLoadingPayment = ref(false);
const errorPaymentMethod = ref<null | unknown>(null);
const onSubmitPayment = () => {
    let url
    if (paymentData.value.payment_method === 'credit_balance') {
        url = route('grp.models.refund.refund_to_credit', {
            refund: props.box_stats.refund_id,
        })
    } else {
        url = route('grp.models.refund.refund_to_payment_account', {
            refund: props.box_stats.refund_id,
            paymentAccount: paymentData.value.payment_account
        })
    }

    try {
        router.post(
            url,
            {
                amount: paymentData.value.payment_amount,
            },
            {
                onStart: () => isLoadingPayment.value = true,
                onFinish: () => {
                    isLoadingPayment.value = false,
                        isOpenModalPayment.value = false,
                        notify({
                            title: trans("Success"),
                            text: "Successfully add payment invoice",
                            type: "success"
                        })
                },
                onSuccess: () => {
                    paymentData.value.payment_account = null,
                    paymentData.value.payment_amount = 0
                        // paymentData.value.payment_reference = ""
                },
                preserveScroll: true,
            }
        )

    } catch (error: unknown) {
        errorPaymentMethod.value = error
    }
}

const onPayInOnClick = () => {
    handleTabUpdate('payments')
}


const afterRefundAll = () => {
  if(_refComponents.value.items_in_process){
    _refComponents.value.items_in_process.reloadForm()
  }
}


watch(paymentData, () => {
  if (errorPaymentMethod.value) {
    errorPaymentMethod.value = null;
  }
});


console.log(props.pageHead)
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
    <template #button-delete-refund="{ action }">
      <div>
        <ModalConfirmationDelete :routeDelete="action.route" isFullLoading>
          <template #default="{ isOpenModal, changeModel, isLoadingdelete }">
            <Button @click="() => changeModel()" :style="'negative'"  :icon="faTrash"
              :loading="isLoadingdelete" :iconRight="action.iconRight" :label="''"
              :key="`ActionButton${action.label}${action.style}`" :tooltip="action.tooltip" />

          </template>
        </ModalConfirmationDelete>
      </div>
    </template>


    <template #button-finalise-refund="{ action }">
      <Link :href="route(action.route?.name,action.route?.parameters)" :method="action.route?.method" v-on:success="() => handleTabUpdate('items')">
            <Button :style="action.style"  :icon="action.icon"
             :iconRight="action.iconRight" :label="action.label"
              :key="`ActionButton${action.label}${action.style}`" :tooltip="action.tooltip" />
      </Link>
    </template>

    <template #button-refund-all="{ action }">
      <Link :href="route(action.route?.name,action.route?.parameters)" :method="action.route?.method" v-on:success="() => afterRefundAll()">
            <Button :style="action.style"  :icon="action.icon"
             :iconRight="action.iconRight" :label="action.label"
              :key="`ActionButton${action.label}${action.style}`" :tooltip="action.tooltip" />
      </Link>
    </template>



  </PageHeading>

  <div class="grid grid-cols-8 divide-x divide-gray-300 border-b border-gray-200">
    <!-- Box: Customer -->
    <BoxStatPallet class="col-span-2 py-2 px-3" icon="fal fa-user">

      <!-- Field: Registration Number -->
      <Link as="a" v-if="box_stats?.customer.reference"
        :href="route(box_stats?.customer.route.name, box_stats?.customer.route.parameters)"
        class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
      <dt v-tooltip="'Company name'" class="flex-none">
        <span class="sr-only">Registration number</span>
        <FontAwesomeIcon icon="fal fa-id-card-alt" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
      </dt>
      <dd class="text-base text-gray-500">#{{ box_stats?.customer.reference }}</dd>
      </Link>

      <!-- Field: Contact name -->
      <div v-if="box_stats?.customer.contact_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Contact name'" class="flex-none">
          <span class="sr-only">Contact name</span>
          <FontAwesomeIcon icon="fal fa-user" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ box_stats?.customer.contact_name }}</dd>
      </div>

      <!-- Field: Company name -->
      <div v-if="box_stats?.customer.company_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Company name'" class="flex-none">
          <span class="sr-only">Company name</span>
          <FontAwesomeIcon icon="fal fa-building" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
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
          <FontAwesomeIcon icon="fal fa-phone" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ box_stats?.customer.phone }}</dd>
      </div>

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
    <BoxStatPallet class="col-span-3 py-2 px-3 ">
      <div class="mt-1">

        <div v-tooltip="trans('Refund created')" class="flex items-center w-fit flex-none gap-x-2">
          <dt class="flex-none">
            <FontAwesomeIcon icon="fal fa-calendar-alt" fixed-width aria-hidden="true" class="text-gray-500" />
          </dt>
          <dd class="text-base text-gray-500" :class='"ff"'>
            {{ useFormatTime(props.invoice_refund.date) }}
          </dd>
        </div>

        <InvoiceRefundPay v-if="!invoice_refund?.in_process" :invoice_pay :routes="{
          submit_route: invoice_pay.routes.submit_payment,
          fetch_payment_accounts_route: invoice_pay.routes.fetch_payment_accounts,
          payments : invoice_pay.routes.payments
        }"  @onPayInOnClick="onPayInOnClick" />
      </div>
    </BoxStatPallet>

    <!-- Section: Order Summary -->
    <BoxStatPallet class="col-span-3 py-2 px-3">
      <OrderSummary :order_summary :currency_code="invoice_refund.currency_code" />
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
  <component :is="component" :data="props[currentTab]" :tab="currentTab" :ref="(e) => _refComponents[currentTab] = e"/>

  <Modal :isOpen="isOpenModalPayment" @onClose="isOpenModalPayment = false" width="w-[600px]">
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
            <div @click="() => paymentData.payment_method = item.value" v-for="item in listPaymentRefund"
              :key="item.value"
              class="flex justify-center items-center border  px-3 py-2 rounded text-center cursor-pointer"
              :class="paymentData.payment_method === item.value ? 'bg-indigo-200 border-indigo-400' : 'border-gray-300'">
              {{ item.label }}
            </div>
          </div>
        </div>

        <Transition name="slide-to-left">
          <div v-if="paymentData.payment_method === 'invoice_payment_method'" class="col-span-2">
            <label for="first-name" class="block text-sm font-medium leading-6">
              <span class="text-red-500">*</span> {{ trans('Select payment account') }}
            </label>
            <div class="mt-1">
              <PureMultiselect v-model="paymentData.payment_account" :options="listPaymentMethod" label="name"
                valueProp="id" required caret />
            </div>
          </div>
        </Transition>

        <div class="col-span-2">
          <label for="last-name" class="block text-sm font-medium leading-6">{{ trans('Amount to refund') }}</label>
          <div class="mt-1">
            <PureInputNumber v-model="paymentData.payment_amount" />
          </div>
          <div class="space-x-1 mt-1 ">
            <span class="text-sm  text-gray-500">{{ trans('Need to refund') }}: {{
              locale.currencyFormat(props.invoice_refund.currency_code || 'usd',
              Math.abs(Number(box_stats.information.pay_amount))) }}</span>
            <Button @click="() => paymentData.payment_amount = Math.abs(box_stats.information.pay_amount)"
              :disabled="paymentData.payment_amount === Math.abs(box_stats.information.pay_amount)" type="tertiary"
              :label="trans('Refund all')" size="sm" />
          </div>
        </div>

        <!-- <div class="col-span-2">
                    <label for="last-name" class="block text-sm font-medium leading-6">{{ trans('Reference') }}</label>
                    <div class="mt-1">
                        <PureInput v-model="paymentData.payment_reference" placeholder="#000000"/>
                    </div>
                </div> -->

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
        <Button @click="() => onSubmitPayment()" label="Submit"
          :disabled="paymentData.payment_method === 'credit_balance' ? false : !(!!paymentData.payment_account)"
          :loading="isLoadingPayment" full />
        <Transition name="spin-to-down">
          <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{ errorPaymentMethod }}</p>
        </Transition>
      </div>
    </div>
  </Modal>
</template>
