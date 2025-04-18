<!--
  - Author: Raul Perusquia <raul@inikoo.com>  
  - Created: Tue, 28 Jan 2025 01:32:54 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import Icon from "@/Components/Icon.vue"
import { Invoice } from "@/types/invoice"
import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from "@/Composables/useFormatTime"
import { faFileInvoiceDollar, faCircle,faCheckCircle,faQuestionCircle, faArrowCircleLeft, faSeedling } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Order } from "@/types/order";
import { RouteParams } from "@/types/route-params";

library.add(faFileInvoiceDollar, faCircle,faCheckCircle,faQuestionCircle, faArrowCircleLeft, faSeedling)


defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore();

console.log(route().current())

function refundRoute(refund: Invoice) {

    switch (route().current()) {
      case 'grp.overview.accounting.refunds.index':
      case 'grp.org.accounting.refunds.index':
        return route(
          'grp.org.accounting.refunds.show',
          [
            refund.organisation_slug,
            refund.slug
          ])
      case 'grp.org.shops.show.dashboard.invoices.refunds.index':
        return route(
          'grp.org.shops.show.dashboard.invoices.refunds.show',
          [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).shop,
            refund.slug
          ])
      case 'grp.org.fulfilments.show.operations.invoices.refunds.index':
        return route(
          'grp.org.fulfilments.show.operations.invoices.refunds.show',
          [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).fulfilment,
            refund.slug
          ])

      case 'grp.org.fulfilments.show.operations.invoices.show':
      case 'grp.org.fulfilments.show.operations.invoices.show.refunds.index':
      case 'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.index':

        return route(
          'grp.org.fulfilments.show.operations.invoices.show.refunds.show',
          [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).fulfilment,
            (route().params as RouteParams).invoice,
            refund.slug
          ])

        case 'grp.org.fulfilments.show.crm.customers.show.invoices.index':
            if (refund.parent_invoice?.slug) {
                return route(
                    'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show',
                    [
                      (route().params as RouteParams).organisation,
                      (route().params as RouteParams).fulfilment,
                      (route().params as RouteParams).fulfilmentCustomer,
                      refund.parent_invoice?.slug,
                      refund.slug
                    ])
            } else {
                return null
            }
        default:
            return null
    }
}

function organisationRoute(order: Order) {
  return route(
    'grp.org.accounting.refunds.index',
    [order.organisation_slug]);
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

        <template #cell(reference)="{ item: refund }">
            <Link :href="refundRoute(refund) as string" class="primaryLink py-0.5">
                {{ refund.reference }}
            </Link>
        </template>

        <template #cell(in_process)="{ item: item }">
            <Icon :data="item['state_icon']" class="px-1" />
        </template>

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

      <template #cell(customer_name)="{ item: refund }">
        <Link v-tooltip='refund["customer_name"]' :href="customerRoute(refund)" class="secondaryLink">
          {{ refund["customer_name"] }}
        </Link>
      </template>


        <!-- Column: Date -->
        <template #cell(date)="{ item }">
            <div class="text-gray-500 text-right">
                {{ useFormatTime(item.date, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>

        <!-- Column: Net -->
        <template #cell(net_amount)="{ item: refund }">
            <div class="text-gray-500">
                {{ useLocaleStore().currencyFormat(refund.currency_code, refund.net_amount) }}
            </div>
        </template>

        <!-- Column: Total -->
        <template #cell(total_amount)="{ item: refund }">
            <div :class="refund.total_amount >= 0 ? 'text-gray-500' : 'text-red-400'">
                {{ useLocaleStore().currencyFormat(refund.currency_code, refund.total_amount) }}
            </div>
        </template>

    </Table>
</template>
