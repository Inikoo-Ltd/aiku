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

function stockDeliveryRoute(stockDelivery: { } ) {
    switch (route().current()) {
        case 'grp.org.procurement.stock_deliveries.index':
            return route(
                'grp.org.procurement.stock_deliveries.show',
                [route().params['organisation'], stockDelivery.slug]);
        default:
            return route(
                'grp.org.procurement.stock_deliveries.show',
                [route().params['organisation'], stockDelivery.slug]);
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: stockDelivery }">
            <Icon :data="stockDelivery.state_icon" />
        </template>

        <template #cell(reference)="{ item: stockDelivery }">
            <Link :href="stockDeliveryRoute(stockDelivery)" class="primaryLink">
                {{ stockDelivery['reference'] }}
            </Link>
        </template>

        <template #cell(date)="{ item }">
            {{ useFormatTime(item.date, { formatTime: "EEE, do MMM yy, HH:mm" }) }}
        </template>
    </Table>
</template>
