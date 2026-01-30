<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

const props = defineProps<{
    data: {}
    tab?: string
}>()

const locale = useLocaleStore()

function trolleyRoute(trolley: trolley) {
    switch (route().current()) {
        case 'grp.org.warehouses.show.dispatching.picking_trolleys.index':
            return route(
                'grp.org.warehouses.show.dispatching.picking_trolleys.show',
                [route().params['organisation'], route().params['warehouse'], trolley.slug])
        default:
            return route(
                'grp.org.warehouses.show.dispatching.picking_trolleys.index',
                [trolley.organisation_slug, trolley.slug])
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Code -->
        <template #cell(code)="{ item: trolley }">
            <Link :href="trolleyRoute(trolley)" class="primaryLink">
                {{ trolley['code'] }}
            </Link>
        </template>
    </Table>
</template>
