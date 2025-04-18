<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { Invoice } from "@/types/invoice"
import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faFileInvoiceDollar, faCircle,faCheckCircle,faQuestionCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { RouteParams } from "@/types/route-params";
library.add(faFileInvoiceDollar, faCircle,faCheckCircle,faQuestionCircle)


defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore();

function invoiceRoute(invoice: Invoice) {

  if(route().current()=='grp.overview.accounting.deleted_invoices.index'){
    return route(
      'grp.org.accounting.deleted_invoices.show',
      [
        invoice.organisation_slug,
        invoice.slug])
  }

  return route(
    'grp.org.accounting.deleted_invoices.show',
    [
      (route().params as RouteParams).organisation,
      invoice.slug])
}

function customerRoute(invoice: Invoice) {

  return route(
    'grp.org.accounting.deleted_invoices.show',
    [
      (route().params as RouteParams).organisation,
      invoice.slug])
}

function organisationRoute(invoice: Invoice) {
  return route(
    "grp.org.accounting.deleted_invoices.index",
    [invoice.organisation_slug]);
}

function shopRoute(invoice: Invoice) {
  return route(
    "grp.helpers.redirect_deleted_invoices_in_shop",
    [invoice.shop_id]);
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
        </template>

        <template #cell(type)="{ item }">
            <div class="text-center">
                <FontAwesomeIcon :icon='item.type?.icon?.icon' v-tooltip="item.type?.icon?.tooltip" :class='item.type?.icon?.class' fixed-width aria-hidden='true' />
            </div>
        </template>

      <template #cell(customer_name)="{ item: invoice }">
        <Link :href="customerRoute(invoice)" class="secondaryLink py-0.5">
          {{ invoice.customer_name }}
        </Link>
      </template>


        <!-- Column: Date -->
        <template #cell(deleted_at)="{ item }">
            <div class="text-gray-500 text-right">
                {{ useFormatTime(item.deleted_at, { localeCode: locale.language.code, formatTime: "aiku" }) }}
            </div>
        </template>

      <template #cell(deleted_by_name)="{ item }">
       {{item.deleted_by_name}}
      </template>



        <!-- Column: Total -->
        <template #cell(total_amount)="{ item: invoice }">
            <div :class="invoice.total_amount >= 0 ? 'text-gray-500' : 'text-red-400'">
                {{ useLocaleStore().currencyFormat(invoice.currency_code, invoice.total_amount) }}
            </div>
        </template>

    </Table>
</template>
