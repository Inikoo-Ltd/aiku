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

library.add(faPlus)

const props = defineProps<{
    data: {}
    tab?: string
    location?: string
}>()

const layout = inject('layout', layoutStructure)

function palletReturnRoute(palletReturn: PalletDelivery) {
    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.pallet-returns.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.confirmed.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.picking.index':
        case 'grp.org.warehouses.show.dispatching.pallet-returns.picked.index':
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
            return route(
                'grp.org.warehouses.show.dispatching.pallet-return-with-stored-items.show',
                [
                    route().params['organisation'],
                    route().params['warehouse'],
                    palletReturn.slug
                ]);
        case 'grp.org.fulfilments.show.operations.pallet-returns.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.confirmed.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.picking.index':
        case 'grp.org.fulfilments.show.operations.pallet-returns.picked.index':
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
        case 'retina.dropshipping.platforms.orders.index':
            return route(
                'retina.fulfilment.storage.pallet_returns.with-stored-items.show',
                [
                    palletReturn.slug
                ]);
        default:
            return route(
                'grp.org.fulfilments.show.crm.customers.show.pallet_returns.with_stored_items.show',
                [
                    route().params['organisation'],
                    route().params['fulfilment'],
                    route().params['fulfilmentCustomer'],
                    palletReturn.slug
                ]);
    }
}

function orderRoute(palletReturn: PalletDelivery) {
    switch (route().current()) {
        case 'retina.dropshipping.orders.index':
        case 'retina.dropshipping.platforms.orders.index':
            return route(
                'retina.dropshipping.orders.show',
                [
                    palletReturn.slug
                ]);
        default:
            return route(
                'retina.dropshipping.orders.show',
                [
                    palletReturn.slug
                ]);
        }
    }

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Reference -->
        <template #cell(reference)="{ item: palletReturn }">
            <Link v-if="palletReturn.type === 'pallet'" :href="palletReturnRoute(palletReturn)" class="primaryLink">
                {{ palletReturn['reference'] }}
            </Link>

            <div v-else-if="palletReturn.type === 'stored_item'">
                <Link v-if="location !== 'pupil'" :href="storedItemReturnRoute(palletReturn)" class="primaryLink">
                    {{ palletReturn['reference'] }}
                </Link>
                <div v-else>
                    {{ palletReturn['reference'] }}
                </div>
            </div>

            <Link v-else-if="palletReturn.model === 'Order'" :href="orderRoute(palletReturn)" class="primaryLink">
                {{ palletReturn['reference'] }}
            </Link>

            <Link v-else :href="orderRoute(palletReturn)" class="primaryLink">
                {{ palletReturn.reference }}
            </Link>

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

        <template #cell(shopify_order_id)="{ item: palletReturn }">
            <div class="tabular-nums">
                {{ palletReturn.shopify_order_id }}
            </div>
        </template>

        <template #cell(tiktok_order_id)="{ item: palletReturn }">
            <div class="tabular-nums">
                {{ palletReturn.tiktok_order_id }}
            </div>
        </template>

        <template #cell(shopify_fulfilment_id)="{ item: palletReturn }">
            <div class="tabular-nums">
                {{ palletReturn.shopify_fulfilment_id }}
            </div>
        </template>

        <!-- Column: State -->
        <template #cell(state)="{ item: palletReturn }">
            <Icon :data="palletReturn['type_icon']" class="px-1"/>
            <TagPallet v-if="layout.app.name == 'retina'" :stateIcon="palletReturn.state_icon" />
            <Icon v-else :data="palletReturn['state_icon']" class="px-1"/>
        </template>

        <template #cell(customer)="{ item: palletReturn }">
            {{ palletReturn.customer.contact_name || '-' }} <span v-if="palletReturn.customer.company_name">({{ palletReturn.customer.company_name }})</span>
            <span class="text-xs text-gray-500">
                <AddressLocation :data="palletReturn.customer.location" />
            </span>
            <!-- <pre>{{ palletReturn.customer }}</pre> -->
        </template>

        <!-- Column: Pallets -->
        <template #cell(pallets)="{ item: palletReturn }">
            <div class="tabular-nums">
                {{ palletReturn.number_pallets }}
            </div>
        </template>

        <template #cell(date)="{ item: palletReturn }">
            {{ useFormatTime(palletReturn.dispatched_at) }}
        </template>

        <template #cell(actions)="{ item: palletReturn }">
            <Link
                v-if="palletReturn?.release_hold_route?.name && palletReturn.state == 'hold'"
                method="post"
                :href="route(palletReturn?.release_hold_route?.name, palletReturn?.release_hold_route?.parameters)"
                class="ring-1 ring-gray-300 overflow-hidden first:rounded-l last:rounded-r">
                <Button
                    :style="'primary'"
                    :label="'Release Hold'"
                    class="h-full capitalize inline-flex items-center rounded-none text-sm border-none font-medium shadow-sm focus:ring-transparent focus:ring-offset-transparent focus:ring-0">
                </Button>
            </Link>
        </template>
    </Table>
</template>
