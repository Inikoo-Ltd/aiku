<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'

import { capitalize } from "@/Composables/capitalize"
import { ref } from 'vue'

import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import Table from "@/Components/Table/Table.vue"
import { useLocaleStore } from '@/Stores/locale'
import { RecurringBill } from '@/types/recurring_bill'

import { useFormatTime } from '@/Composables/useFormatTime'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faReceipt } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faReceipt)

// import FileShowcase from '@/xxxxxxxxxxxx'

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    data: {}

}>()

const locale = useLocaleStore();

function invoiceRoute(invoice: RecurringBill) {
    switch (route().current()) {
        case 'retina.dropshipping.invoices.index':
            return route(
                'retina.dropshipping.invoices.show',
                [
                    invoice.slug
                ])
        default:
            return route(
                'retina.fulfilment.billing.invoices.show',
                [
                invoice.slug
                ])
    }
}

function channelRoute(invoice: {}) {
    switch (route().current()) {
        case 'retina.dropshipping.invoices.index':
            return route(
                'retina.dropshipping.customer_sales_channels.show',
                [
                    invoice.customer_sales_channel_slug
                ])
        default:
            return route(
                'retina.dropshipping.customer_sales_channels.show',
                [
                invoice.customer_sales_channel_slug
                ])
    }
}

</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data" class="mt-5">
        <template #cell(reference)="{ item: invoice }">
            <Link :href="invoiceRoute(invoice)" class="primaryLink py-0.5">
            {{ invoice.reference }}
            </Link>
        </template>

        <template #cell(customer_sales_channel_name)="{ item: invoice }">
            <div  v-if="invoice.customer_sales_channel_slug" class="flex items-center gap-2 w-7">
                <img v-tooltip="invoice.platform_name" :src="invoice.platform_image" :alt="invoice.platform_name"
                    class="w-6 h-6"/>

                <Link :href="channelRoute(invoice)" class="primaryLink py-0.5">
                    {{ invoice.customer_sales_channel_name }}
                </Link>
            </div>
            <div v-else class="text-gray-500 text-sm">
                N/A
            </div>
        </template>

        <!-- Column: Date -->
        <template #cell(type)="{ item }">
            <div class="text-center">
            <!-- {{ item.type }} -->
                <FontAwesomeIcon :icon='item.type?.icon?.icon' v-tooltip="item.type?.icon?.tooltip" :class='item.type?.icon?.class' fixed-width aria-hidden='true' />
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