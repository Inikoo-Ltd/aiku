<!--
  - Author: Raul Perusquia <raul@inikoo.com>  
  - Created: Tue, 28 Jan 2025 01:32:54 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { Invoice } from "@/types/invoice"
import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from "@/Composables/useFormatTime"
import { faFileInvoiceDollar, faCircle,faCheckCircle,faQuestionCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faFileInvoiceDollar, faCircle,faCheckCircle,faQuestionCircle)


defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore();

console.log(route().current())

function refundRoute(invoice: Invoice) {
  console.log(route().current())

    switch (route().current()) {
      case 'grp.org.fulfilments.show.operations.invoices.show':
        return route(
          'grp.org.fulfilments.show.operations.invoices.show.refunds.show',
          [
            route().params["organisation"],
            route().params["fulfilment"],
            route().params["invoice"],
            invoice.slug
          ])
      case 'grp.org.fulfilments.show.operations.invoices.show.refunds.index':
        return route(
          'grp.org.fulfilments.show.operations.invoices.show.refunds.show',
          [
            route().params["organisation"],
            route().params["fulfilment"],
            route().params["invoice"],
            invoice.slug
          ])
        case 'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.index':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show',
                [
                  route().params["organisation"],
                  route().params["fulfilment"],
                  route().params["fulfilmentCustomer"],
                  route().params["invoice"],
                  invoice.slug
                ])
        case 'grp.org.fulfilments.show.crm.customers.show.invoices.index':
            if (invoice.parent_invoice?.slug) {
                return route(
                    'grp.org.fulfilments.show.crm.customers.show.invoices.show.refunds.show',
                    [
                      route().params["organisation"],
                      route().params["fulfilment"],
                      route().params["fulfilmentCustomer"],
                      invoice.parent_invoice?.slug,
                      invoice.slug
                    ])
            } else {
                return null
            }
        default:
            return null
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: refund }">
            <Link v-if="refundRoute(refund)" :href="refundRoute(refund)" class="primaryLink py-0.5">
                {{ refund.slug }}
            </Link>

            <div v-else>
                {{ refund.slug }}
            </div>
            
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
