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
            {{ item.cbm ?? '-' }}
        </template>

        <template #cell(gross_weight)="{ item }">
            {{ item.gross_weight != null ? `${item.gross_weight} kg` : '-' }}
        </template>

        <template #cell(amount)="{ item }">
            <span v-if="item.amount != null">
                {{ Number(item.amount).toFixed(2) }} {{ item.currency_code }}
                <template v-if="item.converted_amount != null && item.converted_currency_code && item.converted_currency_code !== item.currency_code">
                    &asymp; {{ Number(item.converted_amount).toFixed(2) }} {{ item.converted_currency_code }}
                </template>
            </span>
            <span v-else>-</span>
        </template>
    </Table>
</template>
