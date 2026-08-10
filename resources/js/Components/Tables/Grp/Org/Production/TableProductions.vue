<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import {Production} from "@/types/production";
import Icon from '@/Components/Icon.vue'

const props = defineProps<{
    data: object,
    tab?: string
}>()


function productionRoute(production: Production) {
    switch (route().current()) {
        case 'grp.org.productions.index':
            return route(
                'grp.org.productions.show',
                [route().params['organisation'], production.slug]);
    }
}

</script>


<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state_icon)="{ item: production }">
            <Icon :data="production['state_icon']" class="px-1" />
        </template>

        <template #cell(code)="{ item: production }">
            <Link :href="productionRoute(production)" class="primaryLink">
                {{ production['code'] }}
            </Link>
        </template>

    </Table>
</template>
