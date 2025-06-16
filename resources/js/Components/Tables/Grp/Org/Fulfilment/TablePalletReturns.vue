<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:46:51 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from "@fas"
import TagPallet from '@/Components/TagPallet.vue'

import { PalletDelivery } from "@/types/pallet-delivery"
import Icon from "@/Components/Icon.vue"
import { inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useFormatTime } from "@/Composables/useFormatTime"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(faPlus)

const props = defineProps<{
    data: {}
    tab?: string
}>()

const layout = inject('layout', layoutStructure)

function palletReturnRoute(palletReturn: PalletDelivery) {
    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.pallet-returns.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.picking.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.dispatched.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.cancelled.index':
            return route(
                'grp.org.warehouses.show.dispatching.pallet-returns.show',
                [
                    route().params['organisation'],
                    route().params['warehouse'],
                    palletReturn.slug
                ]);
        case 'grp.org.fulfilments.show.operations.pallet-returns.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.confirmed.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.picking.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.picked.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.dispatched.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.new.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.cancelled.index':
            return route(
                'grp.org.fulfilments.show.operations.pallet-returns.show',
                [
                    route().params['organisation'],
                    route().params['fulfilment'],
                    palletReturn.slug
                ]);
        case 'retina.fulfilment.storage.pallet_returns.index':
            return route(
                'retina.fulfilment.storage.pallet_returns.show',
                [
                    palletReturn.slug
                ]);
        default:
            return route(
                'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show',
                [
                    route().params['organisation'],
                    route().params['fulfilment'],
                    route().params['fulfilmentCustomer'],
                    palletReturn.slug
                ]);
    }
}

function storedItemReturnRoute(palletReturn: PalletDelivery) {
    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.pallet-returns.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.picking.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.dispatched.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.cancelled.index':
            return route(
                'grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show',
                [
                    route().params['organisation'],
                    route().params['warehouse'],
                    palletReturn.slug
                ]);
        case 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.index':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show',
                [
                    route().params['organisation'],
                    route().params['fulfilment'],
                    route().params['fulfilmentCustomer'],
                    palletReturn.slug
                ]);
        case 'grp.org.fulfilments.show.operations.pallet-returns.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.confirmed.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.picking.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.picked.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.dispatched.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.cancelled.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.new.index':
            return route(
                'grp.org.fulfilments.show.operations.pallet-return-with-stored-items.show',
                [
                    route().params['organisation'],
                    route().params['fulfilment'],
                    palletReturn.slug
                ]);
        case 'retina.fulfilment.storage.pallet_returns.index':
            return route(
                'retina.fulfilment.storage.pallet_returns.with-stored-items.show',
                [
                    palletReturn.slug
                ]);
        case 'retina.dropshipping.orders.index':
            return route(
                'retina.fulfilment.storage.pallet_returns.with-stored-items.show',
                [
                    palletReturn.slug
                ]);
        case 'retina.fulfilment.dropshipping.customer_sales_channels.basket.index':
            return route(
                'retina.fulfilment.dropshipping.customer_sales_channels.basket.show',
                [
                    route().params['customerSalesChannel'],
                    palletReturn.slug
                ]);
        case 'retina.fulfilment.dropshipping.customer_sales_channels.orders.index':
            return route(
                'retina.fulfilment.dropshipping.customer_sales_channels.orders.show',
                [
                    route().params['customerSalesChannel'],
                    palletReturn.slug
                ]);
        default:
            return ''
    }
}

const locale = inject('locale', aikuLocaleStructure)

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Reference -->
        <template #cell(reference)="{ item: palletReturn }">
            <Link v-if="palletReturn.type === 'pallet'" :href="palletReturnRoute(palletReturn)" class="primaryLink">
                {{ palletReturn['reference'] }}
            </Link>

            <Link v-else-if="palletReturn.type === 'stored_item'" :href="storedItemReturnRoute(palletReturn)" class="primaryLink">
                {{ palletReturn['reference'] }}
            </Link>

            <div v-else>
                {{ palletReturn.reference }}
            </div>
        </template>

        <!-- Column: Customer Reference -->
        <template #cell(customer_reference)="{ item: palletReturn }">
            <div v-if="palletReturn.customer_reference">
                {{ palletReturn.customer_reference }}
            </div>

            <div v-else class="text-gray-400">
                -
            </div>
        </template>

        <!-- Column: State -->
        <template #cell(state)="{ item: palletReturn }">
            <Icon :data="palletReturn['type_icon']" class="px-1"/>
            <TagPallet v-if="layout.app.name == 'retina'" :stateIcon="palletReturn.state_icon" />
            <!-- <Icon v-else :data="palletReturn['state_icon']" class="px-1"/> -->
        </template>

        <template #cell(customer)="{ item: palletReturn }">
            {{ palletReturn.customer.contact_name || '-' }} <span v-if="palletReturn.customer.company_name">({{ palletReturn.customer.company_name }})</span>
            <span class="text-xs text-gray-500">
                <AddressLocation :data="palletReturn.customer.location" />
            </span>
            <!-- <pre>{{ palletReturn.customer }}</pre> -->
        </template>

        <!-- Column: Pallets -->
        <template #cell(number_pallets)="{ item: palletReturn }">
            <div v-if="palletReturn.number_pallets" class="tabular-nums">
                {{ palletReturn.number_pallets }}
            </div>
            <div v-else class="text-gray-400">
                -
            </div>
        </template>

        <template #cell(number_stored_items)="{ item: palletReturn }">
            <div v-if="palletReturn.number_stored_items" class="tabular-nums">
                {{ palletReturn.number_stored_items }}
            </div>
            <div v-else class="text-gray-400">
                -
            </div>
        </template>

        <template #cell(platform_name)="{ item: palletReturn }">
            <div v-if="palletReturn.platform_name">
                {{ palletReturn.platform_name }}
            </div>

            <div v-else class="text-gray-400">
                -
            </div>
            
        </template>

        <template #cell(confirmed_at)="{ item: palletReturn }">
            {{ useFormatTime(palletReturn.confirmed_at, { localeCode: locale.language.code, formatTime: "hm" })}}
        </template>
        <template #cell(picked_at)="{ item: palletReturn }">
            {{ useFormatTime(palletReturn.picked_at, { localeCode: locale.language.code, formatTime: "hm" })}}
        </template>
        <template #cell(picking_at)="{ item: palletReturn }">
            {{ useFormatTime(palletReturn.picking_at, { localeCode: locale.language.code, formatTime: "hm" })}}
        </template>
        <template #cell(dispatched_at)="{ item: palletReturn }">
            {{ useFormatTime(palletReturn.dispatched_at, { localeCode: locale.language.code, formatTime: "hm" })}}
        </template>
        <template #cell(cancel_at)="{ item: palletReturn }">
            {{ useFormatTime(palletReturn.cancel_at, { localeCode: locale.language.code, formatTime: "hm" })}}
        </template>
        <template #cell(date)="{ item: palletReturn }">
            {{ useFormatTime(palletReturn.date, { localeCode: locale.language.code, formatTime: "hm" })}}
        </template>

        <template #buttonreturns="{ linkButton: linkButton }">
            <Link
                v-if="linkButton?.route?.name"
                method="post"
                :href="route(linkButton?.route?.name, linkButton?.route?.parameters)"
                class="ring-1 ring-gray-300 overflow-hidden first:rounded-l last:rounded-r">
                <Button
                    :style="linkButton.style"
                    :label="linkButton.label"
                    class="h-full capitalize inline-flex items-center rounded-none text-sm border-none font-medium shadow-sm focus:ring-transparent focus:ring-offset-transparent focus:ring-0">
                </Button>
            </Link>
        </template>
    </Table>
</template>
