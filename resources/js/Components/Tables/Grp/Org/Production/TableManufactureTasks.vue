<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import {Production} from "@/types/production";

const props = defineProps<{
    data: object,
    tab?: string
}>()


console.log(route().current())
function productionRoute(manufactureTask: {}) {
    switch (route().current()) {
        case 'grp.org.productions.show.crafts.manufacture_tasks.index':
            return route(
                'grp.org.productions.show.crafts.manufacture_tasks.show',
                [route().params['organisation'], route().params['production'], manufactureTask.slug]);
    }
}


function locationsRoute(production: Production) {
    switch (route().current()) {
        case 'grp.org.productions.index':
            return route(
                'grp.org.productions.show.infrastructure.locations.index',
                [route().params['organisation'], production.slug]);
    }
}

</script>


<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: production }">
            <Link :href="productionRoute(production)" class="primaryLink">
                {{ production['code'] }}
            </Link>
        </template>

        <template #cell(number_locations)="{ item: production }">
            <Link :href="locationsRoute(production)" class="primaryLink">
                {{ production['number_locations'] }}
            </Link>
        </template>
    </Table>
</template>
