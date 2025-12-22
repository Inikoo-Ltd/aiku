<!--
  - Author: Oggie Sutrisna
  - Created: Sun, 22 Dec 2025 09:11:00 Makassar Time
  - Description: Table component for Returns with clickable links
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';

const props = defineProps<{
    data: object,
    tab?: string
}>()

function returnRoute(returnItem: { route?: { name: string, parameters: object }, slug?: string }) {
    if (returnItem.route?.name) {
        return route(returnItem.route.name, returnItem.route.parameters);
    }
    return route(
        'grp.org.warehouses.show.incoming.returns.show',
        [route().params['organisation'], route().params['warehouse'], returnItem.slug]
    );
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: returnItem }">
            <Link :href="returnRoute(returnItem)" class="primaryLink">
                {{ returnItem['reference'] }}
            </Link>
        </template>
    </Table>
</template>
