<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, defineAsyncComponent, ref } from "vue";
import type { Component } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { capitalize } from "@/Composables/capitalize";
import { ctrans } from "@/Composables/useTrans";
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue";
import { routeType } from "@/types/route";
import OrderSummary from "@/Components/Summary/OrderSummary.vue";
import { FieldOrderSummary } from "@/types/Pallet";
import RefundPay from "@/Components/Segmented/InvoiceRefund/RefundPay.vue";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
  faIdCardAlt,
  faMapMarkedAlt,
  faPhone,
  faChartLine,
  faCreditCard,
  faCube,
  faFolder,
  faPercent,
  faCalendarAlt,
  faDollarSign,
  faMapMarkerAlt,
  faPencil,
  faFileMinus,
  faUndoAlt,
  faStarHalfAlt,
  faArrowCircleLeft,
  faFileInvoiceDollar, 
  faShoppingCart,
  faAddressCard
} from "@fal";
import { faClock, faFileInvoice, faFilePdf, faArrowAltCircleLeft, faOmega, faHockeyPuck, faExclamationCircle, faCheckCircle } from "@fas";
import { faCheck, faTrashAlt } from "@far";
import { useFormatTime } from "@/Composables/useFormatTime";
import { PageHeadingTypes } from "@/types/PageHeading";
import TableInvoiceRefundsInProcessTransactions from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceRefundsInProcessTransactions.vue";
import { InvoiceResource } from "@/types/invoice";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import invoice from "@/Pages/Grp/Org/Accounting/Invoice.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";


library.add(
  faFileMinus, faUndoAlt, faCheck, faIdCardAlt, faArrowCircleLeft, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine,faExclamationCircle, faCheckCircle,
  faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faDollarSign, faFilePdf, faArrowAltCircleLeft, faMapMarkerAlt, faPencil, faStarHalfAlt, faOmega,
  faHockeyPuck
);

const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"));




const props = defineProps<{
  title: string,
  pageHead: PageHeadingTypes
  original_invoice_route : routeType
  original_order : any
  original_order_route : routeType
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
  original_invoice: InvoiceResource
  credit_transaction?: {
    requested_by?: string
    applied_by?: string
    notes?: string
    amount: string
    date: string
    route: routeType
  }
  invoice_pay: {
    routes: {
      fetch_payment_accounts: routeType
      submit_payment: routeType,
      payments: routeType
    }
    currency_code: string
    total_invoice: number
    total_paid_account: number
    total_refunds: number
    total_balance: number
    total_paid_in: number
    
    total_paid_out: {
      data: {}[]
    }
    total_need_to_pay: number
  }
  items_in_process?: {}
  items?: {}
  payments?: {}
  details?: {}
  history?: {},
  invoiceExportOptions?: {}
  layout: {
    group: {}
  }
  is_tax_only?: boolean
}>()

const currentTab = ref<string>(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);
const _refComponents = ref({});
const component = computed(() => {
  const components: Component = {
    items: TableInvoiceRefundsInProcessTransactions,
    items_in_process: TableInvoiceRefundsInProcessTransactions,
    payments: TablePayments,
    details: ModelDetails,
    history: TableHistories
  };

  return components[currentTab.value];
});


const afterRefundAll = () => {
  if (_refComponents.value.items_in_process) {
    _refComponents.value.items_in_process.reloadForm();
  }
};


// Tax number validation helper functions
const getStatusIcon = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return 'fa-exclamation-circle'
    }
    if (status === 'valid' || valid) {
        return 'fa-check-circle'
    }
    return 'fa-spinner-third'
}

const getStatusColor = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return 'text-red-600'
    }
    if (status === 'valid' || valid) {
        return 'text-green-600'
    }
    return 'text-yellow-600'
}

const taxNumberStatusText = computed(() => {
    if (props.original_invoice.tax_number_status === 'invalid' || !props.original_invoice.tax_number_valid) {
        return ctrans('Invalid')
    }
    if (props.original_invoice.tax_number_status === 'valid' || props.original_invoice.tax_number_valid) {
        return ctrans('Valid')
    }
    return ctrans('Pending')
})

// Method: get Invoice route
const getInvoiceRoute = () => {
    if (['grp.org.shops.show.dashboard.invoices.refunds.show', 'grp.org.shops.show.dashboard.invoices.show.refunds.show'].includes(route().current())) {
        return route('grp.org.shops.show.dashboard.invoices.show', {
            organisation: (route().params as RouteParams).organisation,
            shop: (route().params as RouteParams).shop,
            invoice: props.original_invoice.slug

        });
    } else if (route().current() === 'grp.org.fulfilments.show.operations.invoices.show.refunds.show') {
        return route('grp.org.fulfilments.show.operations.invoices.show', {
            organisation: (route().params as RouteParams).organisation,
            fulfilment: (route().params as RouteParams).fulfilment,
            invoice: props.original_invoice.slug

        });
    } else {
        return route('grp.org.accounting.invoices.show', {
            organisation: (route().params as RouteParams).organisation,
            invoice: props.original_invoice.slug
        });
    }
}
</script>


<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">

    <template #otherBefore>
      <div v-if="props.invoiceExportOptions?.length"
        class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit">
        <a v-for="exportOption in props.invoiceExportOptions"
          :href="exportOption.name ? route(exportOption.name, exportOption.parameters) : '#'" target="_blank"
          class="w-auto mt-0 sm:flex-none text-base" v-tooltip="exportOption.tooltip">
          <Button :label="exportOption.label" :icon="exportOption.icon" type="tertiary"
            class="rounded-none border-transparent" />
        </a>
      </div>
    </template>

    <!-- Button: delete Refund -->
    <template #button-delete-refund="{ action }">
      <div>
        <ModalConfirmationDelete :routeDelete="action.route" isFullLoading isWithMessage keyMessage="deleted_note">
          <template #default="{ isOpenModal, changeModel, isLoadingdelete }">
            <Button @click="() => changeModel()" :style="'negative'" :icon="faTrashAlt" :loading="isLoadingdelete"
              :iconRight="action.iconRight" :label="''" :key="`ActionButton${action.label}${action.style}`"
              :tooltip="action.tooltip" />

          </template>
        </ModalConfirmationDelete>
      </div>
    </template>


    <template #button-finalise-refund="{ action }">
      <Link :href="action.route?.name ? route(action.route?.name,action.route?.parameters) : ''" :method="action.route?.method"
        v-on:success="() => handleTabUpdate('items')">
      <Button :style="action.style" :icon="action.icon" :iconRight="action.iconRight" :label="action.label"
        :key="`ActionButton${action.label}${action.style}`" :tooltip="action.tooltip" />
      </Link>
    </template>

    <template #button-refund-all="{ action }">
      <Link :href="action.route?.name ? route(action.route?.name,action.route?.parameters) : ''" :method="action.route?.method"
        v-on:success="() => afterRefundAll()">
      <Button :style="action.style" :icon="action.icon" :iconRight="action.iconRight" :label="action.label"
        :key="`ActionButton${action.label}${action.style}`" :tooltip="action.tooltip" />
      </Link>
    </template>


  </PageHeading>

  <div class="grid grid-cols-8 divide-x divide-gray-300 border-b border-gray-200">
    <!-- Box: Customer -->
    <BoxStatPallet class="col-span-2 py-2 px-3" icon="fal fa-user">
      <!-- Field: Registration Number -->
      <dl>
        <Link as="a" v-if="box_stats?.customer.reference"
          :href="box_stats?.customer?.route?.name ? route(box_stats?.customer.route.name, box_stats?.customer.route.parameters) : ''"
          class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
        <dt v-tooltip="'Company name'" class="flex-none">
          <span class="sr-only">Registration number</span>
          <FontAwesomeIcon icon="fal fa-id-card-alt" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>


        <dd class="text-base text-gray-500">#{{ box_stats?.customer.reference }}</dd>
        </Link>
      </dl>

      <!-- Field: Customer name -->
      <dl v-if="original_invoice?.name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="ctrans('Customer name')" class="flex-none">
          <span class="sr-only">{{ctrans('Customer name')}}</span>
          <FontAwesomeIcon icon="fal fa-user" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ original_invoice?.name }}</dd>
      </dl>


      <!-- Field: Contact name -->
      <dl v-if="original_invoice?.contact_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Contact name'" class="flex-none">
          <span class="sr-only">Contact name</span>
          <FontAwesomeIcon :icon="faAddressCard" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ original_invoice?.contact_name }}</dd>
      </dl>

      <!-- Field: Company name -->
      <dl v-if="box_stats?.customer.company_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Company name'" class="flex-none">
          <span class="sr-only">Company name</span>
          <FontAwesomeIcon icon="fal fa-building" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ box_stats?.customer.company_name }}</dd>
      </dl>


      <!-- Field: Phone -->
      <dl v-if="box_stats?.customer.phone" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Phone'" class="flex-none">
          <span class="sr-only">Phone</span>
          <FontAwesomeIcon icon="fal fa-phone" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ box_stats?.customer.phone }}</dd>
      </dl>

      <dl v-if="original_invoice?.tax_number" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="ctrans('Tax Number')" class="flex-none">
          <span class="sr-only">Tax Number</span>
          <FontAwesomeIcon icon="fal fa-receipt" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500 flex items-center gap-x-2">
          <span>{{ original_invoice?.tax_number }}</span>
          <FontAwesomeIcon :icon="getStatusIcon(original_invoice.tax_number_status, original_invoice.tax_number_valid)"
            :class="getStatusColor(original_invoice.tax_number_status, original_invoice.tax_number_valid)" size="xs"
            v-tooltip="taxNumberStatusText" fixed-width />
        </dd>
      </dl>

      <dl class="pl-1 flex items-start w-full gap-x-2">
        <dt v-tooltip="'Phone'" class="flex-none">
          <span class="sr-only">Phone</span>
          <FontAwesomeIcon icon="fal fa-map-marker-alt" size="xs" class="text-gray-400" fixed-width
            aria-hidden="true" />
        </dt>

        <dd class="text-base text-gray-500 w-full">
          <div v-if="original_invoice?.address" class="relative bg-gray-50 border border-gray-300 rounded px-2 py-1">
            <div v-html="original_invoice?.address.formatted_address" />
          </div>

          <div v-else class="text-gray-400 italic">
            No address
          </div>
        </dd>
      </dl>

    </BoxStatPallet>

    <!-- Section: Detail (2nd box) -->
    <BoxStatPallet class="col-span-3 py-2 px-3 ">
      <div class="mt-1">

      <!-- Refund Date -->
      <dl v-tooltip="ctrans('Refund created')" class="flex items-center w-fit flex-none gap-x-2">
        <dt class="flex-none">
          <FontAwesomeIcon icon="fal fa-calendar-alt" fixed-width aria-hidden="true" class="text-gray-500" />
        </dt>
        <dd class="text-base text-gray-500 ff">
          {{ useFormatTime(props.invoice_refund.date) }}
        </dd>
      </dl>


        <!-- Section: Order -->
        <dl v-if="original_order" v-tooltip="ctrans('Order')" class="flex items-center w-fit flex-none gap-x-2 my-2">
            <dt class="flex-none">
                <FontAwesomeIcon :icon="faShoppingCart" fixed-width aria-hidden="true" class="text-gray-500" />
            </dt>
            <dd class="text-base text-gray-500 ff">
                <Link
                class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink"
                :href="original_order_route?.name ? route(original_order_route.name, original_order_route.parameters) : ''"
                >
                    {{ original_order?.data?.reference }}
                </Link>
            </dd>
        </dl>

        <!-- Credit transaction (standalone credit note settled on the customer balance) -->
        <dl v-if="credit_transaction" class="flex flex-col w-fit gap-y-1 my-2 text-base text-gray-500">
            <Link class="primaryLink w-fit" :href="route(credit_transaction.route.name, credit_transaction.route.parameters)">
                {{ ctrans('Settled on customer balance') }} {{ useFormatTime(credit_transaction.date) }}
            </Link>
            <div v-if="credit_transaction.requested_by">{{ ctrans('Requested by') }}: {{ credit_transaction.requested_by }}</div>
            <div v-if="credit_transaction.applied_by">{{ ctrans('Applied by') }}: {{ credit_transaction.applied_by }}</div>
            <div v-if="credit_transaction.notes" class="italic">{{ credit_transaction.notes }}</div>
        </dl>

        <!-- Invoice -->
        <dl v-if="original_invoice" v-tooltip="ctrans('Invoice')" class="flex items-center w-fit flex-none gap-x-2 my-2">
            <dt class="flex-none">
                <FontAwesomeIcon :icon="faFileInvoiceDollar" fixed-width aria-hidden="true" class="text-gray-500" />
            </dt>
            <dd class="text-base text-gray-500 ff">
                <Link
                class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink"
                :href="getInvoiceRoute()"
                >
                    {{ original_invoice?.reference }}
                </Link>
            </dd>
        </dl>

      <!-- Refund Payment -->
        <RefundPay
          v-if="!invoice_refund?.in_process && invoice_pay"
          :invoice_pay
          :handleTabUpdate
          :refund="invoice_refund"
          :routes="{
            submit_route: invoice_pay.routes.submit_payment,
            fetch_payment_accounts_route: invoice_pay.routes.fetch_payment_accounts,
            payments: invoice_pay.routes.payments
          }"
          :is_in_refund="true"
          :is_tax_only="is_tax_only"
        />
    </div>

    </BoxStatPallet>

    <!-- Section: Order Summary -->
    <BoxStatPallet class="col-span-3 py-2 px-3">
      <OrderSummary :order_summary :currency_code="invoice_refund.currency_code" />
    </BoxStatPallet>


  </div>

  <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
  <component :is="component" :data="props[currentTab]" :tab="currentTab" :ref="(e) => _refComponents[currentTab] = e" :is_tax_only="is_tax_only" />
</template>
