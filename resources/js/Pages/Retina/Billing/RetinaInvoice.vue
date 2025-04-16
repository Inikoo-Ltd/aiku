<script setup lang="ts">
import { Head, usePage,Link } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, ref, watchEffect } from "vue";
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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faIdCardAlt, faMapMarkedAlt, faPhone, faChartLine, faCreditCard, faCube, faFolder, faPercent, faCalendarAlt, faDollarSign, faMapMarkerAlt, faPencil, faBuilding, faMoneyBillAlt } from "@fal";
import { faClock, faFileExcel, faFileInvoice, faFilePdf } from "@fas";
import { faCheck } from "@far";
import { useFormatTime } from "@/Composables/useFormatTime";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import TableInvoiceTransactions from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceTransactions.vue";
import { InvoiceResource } from "@/types/invoice";
import NeedToPay from "@/Components/Utils/NeedToPay.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import type { Component } from "vue";

library.add(faCheck, faIdCardAlt, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine, faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faBuilding, faDollarSign, faFilePdf, faMapMarkerAlt, faPencil, faMoneyBillAlt, faFileExcel);


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
  exportTransactionsRoute: routeType
  order_summary: FieldOrderSummary[][]
  recurring_bill_route: routeType
  invoice: InvoiceResource
  grouped_fulfilment_invoice_transactions?: {}
  itemized_fulfilment_invoice_transactions?: {}
  payments?: {}
  details?: {}
  history?: {}
}>();


const currentTab = ref<string>(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
  const components: Component = {
    grouped_fulfilment_invoice_transactions: TableInvoiceTransactions,
    itemized_fulfilment_invoice_transactions: TableInvoiceTransactions,
    payments: TablePayments,
    details: ModelDetails,
    history: TableHistories
  };

  return components[currentTab.value];
});

const urlPage = ref(location);
const cleanedSearchUrl = ref(location.search ? location.search.slice(1) : "");

watchEffect(() => {
  urlPage.value = location;
  cleanedSearchUrl.value = location.search ? location.search.slice(1) : "";
  usePage().url;  // to trigger watchEffect on filter table changed
});


</script>


<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">

    <!-- Button: PDF -->
    <template #other>
      <a v-if="exportPdfRoute?.name" :href="route(exportPdfRoute.name, exportPdfRoute.parameters)" target="_blank"
         class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none text-base" v-tooltip="trans('Download in')">
        <Button label="PDF" icon="fas fa-file-pdf" type="tertiary" />
      </a>
      <a v-if="exportTransactionsRoute?.name" :href="
              exportTransactionsRoute
                        ? `${route(exportTransactionsRoute.name, exportTransactionsRoute.parameters)}?type=xlsx`
                        : `${urlPage.origin}${urlPage.pathname}/export?type=xlsx&${cleanedSearchUrl}`
                "
         target="_blank"
         class="mt-4  sm:mt-0 sm:flex-none text-base" v-tooltip="trans('Download in')">
        <Button label="Excel" icon="fas fa-file-excel" type="tertiary" />
      </a>
    </template>
  </PageHeading>

  <div class="grid grid-cols-4 divide-x divide-gray-300 border-b border-gray-200">
    <!-- Box: Customer -->
    <BoxStatPallet class="gap-y-2 py-2 px-3" icon="fal fa-user">
      <!-- Field: Contact name -->
      <dl v-if="box_stats?.customer.contact_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Contact name'" class="flex-none">
          <span class="sr-only">{{trans('Contact name')}}</span>
          <FontAwesomeIcon icon="fal fa-user" size="xs" class="text-gray-400" fixed-width
                           aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ box_stats?.customer.contact_name }}</dd>
      </dl>

      <!-- Field: Company name -->
      <dl v-if="box_stats?.customer.company_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Company name'" class="flex-none">
          <span class="sr-only">{{trans('Company name')}}</span>
          <FontAwesomeIcon icon="fal fa-building" size="xs" class="text-gray-400" fixed-width
                           aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ box_stats?.customer.company_name }}</dd>
      </dl>

      <!-- Field: Phone -->
      <dl v-if="box_stats?.customer.phone" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Phone'" class="flex-none">
          <span class="sr-only">Phone</span>
          <FontAwesomeIcon icon="fal fa-phone" size="xs" class="text-gray-400" fixed-width
                           aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ box_stats?.customer.phone }}</dd>
      </dl>

      <!-- Field: Address -->
      <dl class="pl-1 flex items-start w-full gap-x-2">
        <dt v-tooltip="'Phone'" class="flex-none">
          <span class="sr-only">Phone</span>
          <FontAwesomeIcon icon="fal fa-map-marker-alt" size="xs" class="text-gray-400" fixed-width
                           aria-hidden="true" />
        </dt>

        <dd class="text-base text-gray-500 w-full">
          <div v-if="invoice.address" class="relative bg-gray-50 border border-gray-300 rounded px-2 py-1">
            <div v-html="invoice.address.formatted_address" />
          </div>

          <div v-else class="text-gray-400 italic">
            {{ trans("No address") }}
          </div>
        </dd>
      </dl>
    </BoxStatPallet>

    <!-- Section: Detail -->
    <BoxStatPallet class="py-2 px-3">
      <div class="mt-1 space-y-1.5">
        <dl v-tooltip="'Recurring bill'"
             class="w-fit flex items-center flex-none gap-x-2">
          <dt class="flex-none">
            <FontAwesomeIcon icon="fal fa-receipt" fixed-width aria-hidden="true" class="text-gray-500" />
          </dt>
          <component :is="box_stats.information.recurring_bill?.route?.name ? Link : 'div'"
                     as="dd"
                     :href="box_stats.information.recurring_bill?.route?.name ? route(box_stats.information.recurring_bill?.route?.name, box_stats.information.recurring_bill.route.parameters) : ''"
                     class="text-base text-gray-500"
                     :class="box_stats.information.recurring_bill?.route?.name ? 'cursor-pointer primaryLink' : ''">
            {{ box_stats.information.recurring_bill?.reference || "-" }}
          </component>
        </dl>

        <dl v-tooltip="'Invoice created'"
             class="flex items-center w-full flex-none gap-x-2">
          <dt class="flex-none">
            <FontAwesomeIcon icon="fal fa-calendar-alt" fixed-width aria-hidden="true" class="text-gray-500" />
          </dt>
          <dd class="text-base text-gray-500" :class='"ff"'>
            {{ useFormatTime(props.invoice.date) }}
          </dd>
        </dl>

        <dl class="relative flex items-start w-full flex-none gap-x-2">
          <dt class="flex-none pt-1">
            <FontAwesomeIcon icon="fal fa-money-bill-alt" fixed-width aria-hidden="true" class="text-gray-500" />
          </dt>
          <NeedToPay

            :totalAmount="Number(props.invoice.total_amount)"
            :paidAmount="Number(box_stats.information.paid_amount)"
            :payAmount="Number(box_stats.information.pay_amount)"
            :currencyCode="props.invoice.currency_code"
            :class="[Number(box_stats.information.pay_amount) ? 'hover:bg-gray-100 cursor-pointer' : '']"
          />


        </dl>
      </div>
    </BoxStatPallet>

    <!-- Section: Order Summary -->
    <BoxStatPallet class="col-start-3 col-span-2 py-2 px-3">
      <OrderSummary :order_summary :currency_code="invoice.currency_code" />
    </BoxStatPallet>

  </div>

  <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
  <component :is="component" :data="props[currentTab]" :tab="currentTab" />


</template>