<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { inject } from 'vue'
import Table from '@/Components/Table/Table.vue'
import Icon from '@/Components/Icon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
    faSeedling,
    faCheckCircle,
    faGhost,
    faTimesCircle,
    faInfinity,
    faArrowCircleUp,
    faExclamationCircle,
    faExclamationTriangle,
    faQuestionCircle,
} from '@fal'

library.add(
    faSeedling,
    faCheckCircle,
    faGhost,
    faTimesCircle,
    faInfinity,
    faArrowCircleUp,
    faExclamationCircle,
    faExclamationTriangle,
    faQuestionCircle,
)

const props = defineProps<{
    data: object,
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)

function productionRoute(rawMaterial: {}) {
    switch (route().current()) {
        case 'grp.org.productions.show.crafts.raw_materials.index':
            return route(
                'grp.org.productions.show.crafts.raw_materials.show',
                [route().params['organisation'], route().params['production'], rawMaterial.slug]);
    }
}

</script>


<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: rawMaterial }">
            <Icon :data="rawMaterial.state" />
        </template>

        <template #cell(code)="{ item: rawMaterial }">
            <Link :href="productionRoute(rawMaterial)" class="primaryLink">
                {{ rawMaterial['code'] }}
            </Link>
        </template>

        <template #cell(unit_cost)="{ item: rawMaterial }">
            <span v-if="rawMaterial.unit_cost != null">
                {{ locale.currencyFormat(rawMaterial.currency_code, rawMaterial.unit_cost) }}
            </span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(quantity_on_location)="{ item: rawMaterial }">
            <span v-if="rawMaterial.quantity_on_location != null">
                {{ locale.number(Number(rawMaterial.quantity_on_location)) }}
            </span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(stock_status)="{ item: rawMaterial }">
            <Icon :data="rawMaterial.stock_status" />
        </template>

        <template #cell(number_artefacts)="{ item: rawMaterial }">
            <span v-if="rawMaterial.number_artefacts" class="tabular-nums">
                {{ rawMaterial.number_artefacts }}
            </span>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
