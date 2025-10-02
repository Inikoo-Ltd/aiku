<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Invoice } from "@/types/invoice";
import { useLocaleStore } from "@/Stores/locale";
import { useFormatTime } from "@/Composables/useFormatTime";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faFileInvoiceDollar, faCircle, faCheckCircle, faQuestionCircle } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "@/Components/Icon.vue";
import { trans } from "laravel-vue-i18n";
import { RouteParams } from "@/types/route-params";


library.add(faFileInvoiceDollar, faCircle, faCheckCircle, faQuestionCircle);


defineProps<{
  data: {}
  tab?: string
}>();

const locale = useLocaleStore();

function invoiceRoute(invoice: Invoice) {
  switch (route().current()) {
    case "shops.show.invoices.index":
      return route(
        "shops.show.invoices.show",
        [invoice.slug, invoice.slug]);
    case "grp.org.fulfilments.show.operations.invoices.all.index":
      return route(
        "grp.org.fulfilments.show.operations.invoices.show",
        [(route().params as RouteParams).organisation, (route().params as RouteParams).fulfilment, invoice.slug]);
    case "grp.org.fulfilments.show.crm.customers.show.invoices.index":
      return route(
        invoice.in_process
          ? "grp.org.fulfilments.show.crm.customers.show.invoices.in-process.show"
          : "grp.org.fulfilments.show.crm.customers.show.invoices.show",
        [(route().params as RouteParams).organisation, (route().params as RouteParams).fulfilment, (route().params as RouteParams).fulfilmentCustomer, invoice.slug]
      );
    case "grp.overview.accounting.invoices.index":
      return route(
        "grp.org.accounting.invoices.show",
        [invoice.organisation_slug, invoice.slug]);
    case "grp.org.fulfilments.show.operations.unpaid_invoices.index":
      return route(
        "grp.org.fulfilments.show.operations.invoices.unpaid_invoices.show",
        [(route().params as RouteParams).organisation, (route().params as RouteParams).fulfilment, invoice.slug]);
    default:
      return route(
        "grp.org.accounting.invoices.show",
        [(route().params as RouteParams).organisation, invoice.slug]);
  }
}

function organisationRoute(invoice: Invoice) {
  return route(
    "grp.org.overview.invoices.index",
    [invoice.organisation_slug]);
}

function shopRoute(invoice: Invoice) {
  return route(
    "grp.helpers.redirect_invoices_in_shop",
    [invoice.id]);
}

function customerRoute(invoice: Invoice) {
  return route(
    "grp.helpers.redirect_invoices_in_customer",
    [invoice.id]);
}
</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(organisation_code)="{ item: refund }">
      <Link v-tooltip='refund["organisation_name"]' :href="organisationRoute(refund)" class="secondaryLink">
        {{ refund["organisation_code"] }}
      </Link>
    </template>

    <template #cell(shop_code)="{ item: refund }">
      <Link v-tooltip='refund["shop_name"]' :href="shopRoute(refund)" class="secondaryLink">
        {{ refund["shop_code"] }}
      </Link>
    </template>

    <template #cell(reference)="{ item: invoice }">
      <Link :href="invoiceRoute(invoice)" class="primaryLink py-0.5">
        {{ invoice.reference }}
      </Link>
      <FontAwesomeIcon v-if="invoice.in_process" v-tooltip="trans('In process')" icon="fal fa-seedling" class="text-green-500" fixed-width aria-hidden="true" />
    </template>

    <template #cell(customer_name)="{ item: invoice }">
      <Link :href="customerRoute(invoice)" class="secondaryLink">
        {{ invoice["customer_name"] }}
      </Link>
    </template>

    <!-- Column: Date -->
    <template #cell(type)="{ item }">
      <div class="text-center">
        <!-- {{ item.type }} -->
        <FontAwesomeIcon :icon="item.type?.icon?.icon" v-tooltip="item.type?.icon?.tooltip" :class="item.type?.icon?.class" fixed-width aria-hidden="true" />
      </div>
    </template>

    <!-- Column: Status -->
    <template #cell(pay_status)="{ item }">
      <div class="text-center">
        <Icon :data="item.pay_status" />
      </div>
    </template>

    <!-- Column: Date -->
    <template #cell(date)="{ item }">
      <div class="text-gray-500 text-right">
        {{ useFormatTime(item.date, { localeCode: locale.language.code, formatTime: "aiku" }) }}
      </div>
    </template>

    <!-- Column: Net -->
    <template #cell(net_amount)="{ item: invoice }">
      <div class="text-gray-500">
        {{ useLocaleStore().currencyFormat(invoice.currency_code, invoice.net_amount) }}
      </div>
    </template>

    <!-- Column: Total -->
    <template #cell(total_amount)="{ item: invoice }">
      <div :class="invoice.total_amount >= 0 ? 'text-gray-500' : 'text-red-400'">
        {{ useLocaleStore().currencyFormat(invoice.currency_code, invoice.total_amount) }}
      </div>
    </template>

  </Table>
</template>
