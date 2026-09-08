<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';
import Table from '@/Components/Table/Table.vue';
import { useFormatTime } from '@/Composables/useFormatTime';

defineProps<{
    data: object,
    tab?: string
}>()

function amountFormat(amount: number) {
    return Number(amount).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function stockDeliveryRoute(stockDelivery: { slug: string, organisation_slug?: string }) {
    const organisation = route().params['organisation'] ?? stockDelivery.organisation_slug
    if (!organisation) {
        return null
    }

    return route(
        'grp.org.procurement.stock_deliveries.show',
        [organisation, stockDelivery.slug])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: stockDelivery }">
            <div class="flex items-center gap-1">
                <Icon :data="stockDelivery.state_icon" />
                <span>{{ stockDelivery.state_label }}</span>
            </div>
        </template>

        <template #cell(reference)="{ item: stockDelivery }">
            <Link v-if="stockDeliveryRoute(stockDelivery)" :href="stockDeliveryRoute(stockDelivery)" class="primaryLink">
                {{ stockDelivery['reference'] }}
            </Link>
            <span v-else>{{ stockDelivery['reference'] }}</span>
        </template>

        <template #cell(date)="{ item }">
            {{ useFormatTime(item.date, { formatTime: "EEE, do MMM yy, HH:mm" }) }}
        </template>

        <template #cell(items)="{ item }">
            {{ item.items ?? '-' }}
        </template>

        <template #cell(cbm)="{ item }">
            {{ item.cbm != null ? `${Number(item.cbm).toLocaleString('en-GB', { maximumFractionDigits: 1 })} m³` : '-' }}
        </template>

        <template #cell(gross_weight)="{ item }">
            {{ item.gross_weight ?? '-' }}
        </template>

        <template #cell(amount)="{ item }">
            <span v-if="item.amount != null">{{ amountFormat(item.amount) }} {{ item.currency_code }}</span>
            <span v-else>-</span>
        </template>

        <template #cell(converted_amount)="{ item }">
            <span v-if="item.converted_amount != null && item.converted_currency_code">
                {{ amountFormat(item.converted_amount) }} {{ item.converted_currency_code }}
            </span>
            <span v-else>-</span>
        </template>
    </Table>
</template>
