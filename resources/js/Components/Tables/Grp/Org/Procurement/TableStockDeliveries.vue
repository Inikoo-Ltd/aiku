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
import { ctrans } from '@/Composables/useTrans';

defineProps<{
    data: object,
    tab?: string
}>()

function amountFormat(amount: number) {
    return Number(amount).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const parentRouteNames: Record<string, string> = {
    OrgSupplier: 'grp.org.procurement.org_suppliers.show',
    OrgAgent: 'grp.org.procurement.org_agents.show',
    OrgPartner: 'grp.org.procurement.org_partners.show',
}

function parentRoute(stockDelivery: { parent_type?: string, parent_route_key?: string | null, organisation_slug?: string }) {
    const organisation = route().params['organisation'] ?? stockDelivery.organisation_slug
    const routeName = parentRouteNames[stockDelivery.parent_type ?? '']
    if (!organisation || !routeName || !stockDelivery.parent_route_key) {
        return null
    }

    return route(routeName, [organisation, stockDelivery.parent_route_key])
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
            <span v-if="stockDelivery.number_new_org_stocks > 0"
                v-tooltip="ctrans('Items that have never been in stock')"
                class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
                {{ ctrans(':count new', { count: stockDelivery.number_new_org_stocks }) }}
            </span>
        </template>

        <template #cell(parent_name)="{ item: stockDelivery }">
            <Link v-if="parentRoute(stockDelivery)" :href="parentRoute(stockDelivery)!" class="primaryLink">
                {{ stockDelivery['parent_name'] }}
            </Link>
            <span v-else>{{ stockDelivery['parent_name'] }}</span>
        </template>

        <template #cell(date)="{ item }">
            {{ useFormatTime(item.date, { formatTime: "EEE, do MMM yy, HH:mm" }) }}
        </template>

        <template #cell(estimated_receiving_date)="{ item }">
            {{ item.estimated_receiving_date ? useFormatTime(item.estimated_receiving_date, { formatTime: "EEE, do MMM yy" }) : '-' }}
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
