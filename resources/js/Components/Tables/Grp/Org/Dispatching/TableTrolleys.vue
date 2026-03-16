<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { trans } from 'laravel-vue-i18n'
import Icon from '@/Components/Icon.vue'

defineProps<{
    data: {}
    tab?: string
}>()


function trolleyRoute(trolley: {}) {
    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.trolleys.index':
            return route(
                'grp.org.warehouses.show.dispatching.trolleys.show',
                [route().params['organisation'], route().params['warehouse'], trolley.slug])
        default:
            return route(
                'grp.org.warehouses.show.dispatching.trolleys.index',
                [trolley.organisation_slug, trolley.slug])
    }
}

const deliveryNoteRoute = (trolley: {}) => {
    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.trolleys.show':
        case 'grp.org.warehouses.show.dispatching.trolleys.index':
            return route(
                'grp.org.warehouses.show.dispatching.delivery_notes.show',
                {
                    organisation: route().params['organisation'],
                    warehouse: route().params['warehouse'],
                    deliveryNote: trolley.current_delivery_note.slug
                })
        default:
            return '#'
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Name -->
        <template #cell(name)="{ item: trolley }">
            <Link :href="trolleyRoute(trolley)" class="primaryLink">
                {{ trolley['name'] }}
            </Link>
        </template>

        <!-- Column: Current Delivery Note -->
        <template #cell(current_delivery_note)="{ item: trolley }">
            <Link v-if="trolley['current_delivery_note']" :href="deliveryNoteRoute(trolley)" class="primaryLink w-fit">
                {{ trolley['current_delivery_note']['reference'] }}
                
                <Icon :data="trolley['current_delivery_note']['state_icon']" />
                <span v-tooltip="trans('Number of the total items')" class="tabular-nums">
                    ({{ trolley['current_delivery_note']['number_items'] }}
                        {{ trolley['current_delivery_note']['number_items'] > 1 ? trans('items') : trans('item') }})
                </span>
            </Link>
            <span v-else class="italic text-xs opacity-60">
                {{ trans('No current delivery note') }}
            </span>
        </template>
    </Table>
</template>
