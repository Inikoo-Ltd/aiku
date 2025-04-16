<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { Head, Link } from "@inertiajs/vue3";
import { computed, defineAsyncComponent, ref } from "vue";
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
  faDraftingCompass,
  faEnvelope,
  faArrowCircleLeft,
  faTrashAlt,faExpandArrows
} from "@fal";
import { faClock, faFileInvoice, faFileAlt, faFilePdf, faHockeyPuck } from "@fas";
import { faCheck } from "@far";
import { useFormatTime } from "@/Composables/useFormatTime";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import TableGroupedFulfilmentInvoiceTransactions from "@/Components/Tables/Grp/Org/Accounting/TableGroupedFulfilmentInvoiceTransactions.vue";
import TableItemizedFulfilmentInvoiceTransactions from "@/Components/Tables/Grp/Org/Accounting/TableItemizedFulfilmentInvoiceTransactions.vue";
import TableInvoiceTransactions from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceTransactions.vue";

import { Icon } from "@/types/Utils/Icon";
import Modal from "@/Components/Utils/Modal.vue";
import { InvoiceResource } from "@/types/invoice";
import EmptyState from "@/Components/Utils/EmptyState.vue";
import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue";
import TableRefunds from "@/Components/Tables/Grp/Org/Accounting/TableRefunds.vue";
import InvoiceRefundPay from "@/Components/Segmented/InvoiceRefund/InvoiceRefundPay.vue";
import ModalAfterConfirmationDelete from "@/Components/Utils/ModalAfterConfirmationDelete.vue";
import ModalSupervisorList from "@/Components/Utils/ModalSupervisorList.vue";


library.add(faExpandArrows,faHockeyPuck, faCheck, faEnvelope, faIdCardAlt, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine, faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faDollarSign, faFilePdf, faMapMarkerAlt, faPencil, faFileAlt, faDraftingCompass, faArrowCircleLeft, faTrashAlt);

const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"));


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
  order_summary: FieldOrderSummary[][]
  recurring_bill_route?: routeType
  invoice: InvoiceResource
  invoice_transactions?: {}
  grouped_fulfilment_invoice_transactions?: {}
  itemized_fulfilment_invoice_transactions?: {}
  payments?: {}
  email?: {}
  details?: {}
  history?: {}
  refunds?: {}

  outbox: {
    state: string
    workshop_route: routeType
  }
  list_refunds: {}[] | {}
  invoice_pay: {
    routes: {
      fetch_payment_accounts: routeType
      submit_payment: routeType
      payments: routeType
    }
    currency_code: string
    total_invoice: number
    total_paid_account: number
    total_refunds: number
    total_balance: number
    total_paid_in: number
    total_need_to_refund_in_payment_method: number
    total_paid_out: {
      data: {}[]
    }
    total_need_to_pay: number
  }
  invoiceExportOptions: {
    type: string
    name: string
    label: string
    parameters: any
    tooltip: string
    icon: Icon
  }[]
}>();
const currentTab = ref<string>(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
  const components: Component = {
    invoice_transactions: TableInvoiceTransactions,
    grouped_fulfilment_invoice_transactions: TableGroupedFulfilmentInvoiceTransactions,
    itemized_fulfilment_invoice_transactions: TableItemizedFulfilmentInvoiceTransactions,
    payments: TablePayments,
    details: ModelDetails,
    history: ModelChangelog,
    email: TableDispatchedEmails,
    refunds: TableRefunds
  };

  return components[currentTab.value];
});


const onPayInOnClick = () => {
  handleTabUpdate("payments");
};


// Section: Send Invoice
const isVisitWorkshopOutbox = ref(false);
const isModalSendInvoice = ref(false);

</script>


<template>
  <Head :title="capitalize(title)" />

  <PageHeading :data="pageHead">

    <!-- Export Buttons -->
    <template #otherBefore>
      <div v-if="props.invoiceExportOptions?.length" class="flex flex-wrap border border-gray-300 rounded-md overflow-hidden h-fit">
        <a v-for="exportOption in props.invoiceExportOptions"
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
      <dl>
        <Link as="a" v-if="box_stats?.customer.reference" :href="route(box_stats?.customer.route.name, box_stats?.customer.route.parameters)"
              class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
          <dt v-tooltip="'Company name'" class="flex-none">
            <span class="sr-only">Registration number</span>
            <FontAwesomeIcon icon="fal fa-id-card-alt" size="xs" class="text-gray-400" fixed-width
                             aria-hidden="true" />
          </dt>
          <dd class="text-base text-gray-500">#{{ box_stats?.customer.reference }}</dd>
        </Link>
      </dl>
      <!-- Field: Contact name -->
      <dl v-if="box_stats?.customer.contact_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Contact name'" class="flex-none">
          <span class="sr-only">Contact name</span>
          <FontAwesomeIcon icon="fal fa-user" size="xs" class="text-gray-400" fixed-width
                           aria-hidden="true" />
        </dt>
        <dd class="text-base text-gray-500">{{ box_stats?.customer.contact_name }}</dd>
      </dl>

      <!-- Field: Company name -->
      <dl v-if="box_stats?.customer.company_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Company name'" class="flex-none">
          <span class="sr-only">Company name</span>
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
            No address
          </div>
        </dd>
      </dl>
    </BoxStatPallet>

    <!-- Section: Detail (2nd box) -->
    <BoxStatPallet class="col-span-2 py-2 px-3">
      <div class="mt-1">
        <dl v-if="box_stats.information.recurring_bill" v-tooltip="trans('Recurring bill')"
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

        <dl v-tooltip="trans('Invoice date')"
            class="flex items-center flex-none gap-x-2 w-fit">
          <dt class="flex-none">
            <FontAwesomeIcon icon="fal fa-calendar-alt" fixed-width aria-hidden="true" class="text-gray-500" />
          </dt>
          <dd class="text-base text-gray-500" :class='"ff"'>
            {{ useFormatTime(props.invoice.date) }}
          </dd>
        </dl>

        <div class="relative flex items-start w-full flex-none gap-x-2 mt-2">

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
