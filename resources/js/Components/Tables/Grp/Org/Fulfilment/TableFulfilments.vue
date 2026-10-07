<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 24 Jan 2024 14:52:41 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { Fulfilment } from "@/types/fulfilment"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { useLayoutStore } from '@/Stores/layout'
library.add(faCircle)

const props = defineProps<{
    data: object,
    tab?: string,
}>()


function fulfilmentRoute(fulfilment: Fulfilment) {
    switch (route().current()) {
        case 'grp.org.fulfilments.index':
            return route(
                'grp.org.fulfilments.show.operations.dashboard',
                [route().params['organisation'], fulfilment.slug])
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: fulfilment }">
            <div class="flex">
                <Link :href="fulfilmentRoute(fulfilment)" class="primaryLink">
                    {{ fulfilment.code }}
                </Link>
                <div v-if="fulfilment.code == useLayoutStore().organisationsState?.[useLayoutStore().currentParams.organisation]?.currentFulfilment" v-tooltip="trans('Recently selected')" class="px-0.5 leading-none">
                    <FontAwesomeIcon icon='fas fa-circle' class='text-lime-500 text-[6px]' fixed-width aria-hidden='true' />
                </div>
            </div>
        </template>
    </Table>
</template>


