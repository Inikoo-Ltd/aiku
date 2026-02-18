<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'

defineProps<{
    data: {}
    tab?: string
}>()


function trolleyRoute(trolley: trolley) {
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
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Code -->
        <template #cell(name)="{ item: trolley }">
            <Link :href="trolleyRoute(trolley)" class="primaryLink">
                {{ trolley['name'] }}
            </Link>
        </template>
    </Table>
</template>
