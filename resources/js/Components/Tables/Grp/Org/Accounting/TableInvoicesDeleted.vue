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
import Icon from '@/Components/Icon.vue'
library.add(faFileInvoiceDollar, faCircle,faCheckCircle,faQuestionCircle)


const props = defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore();

function invoiceRoute(invoice: Invoice) {
 
    switch (route().current()) {
        default:
            return route(
                'grp.org.accounting.deleted_invoices.show',
                [route().params['organisation'], invoice.slug])
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: invoice }">
            <Link :href="invoiceRoute(invoice)" class="primaryLink py-0.5">
            {{ invoice.reference }}
            </Link>
        </template>

        <!-- Column: Date -->
        <template #cell(type)="{ item }">
            <div class="text-center">
            <!-- {{ item.type }} -->
                <FontAwesomeIcon :icon='item.type?.icon?.icon' v-tooltip="item.type?.icon?.tooltip" :class='item.type?.icon?.class' fixed-width aria-hidden='true' />
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
