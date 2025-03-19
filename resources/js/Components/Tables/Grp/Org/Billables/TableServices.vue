<!--
- Author: Raul Perusquia <raul@inikoo.com>
- Created: Thu, 23 May 2024 09:45:43 British Summer Time, Sheffield, UK
- Copyright (c) 2024, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRobot } from '@fal'
import { useLocaleStore } from '@/Stores/locale'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faRobot)

const props = defineProps<{
    data: {}
    tab?: string
}>()

const emits = defineEmits<{
    (e: 'renderTableKey'): void
}>()

function serviceRoute(service: {}) {
    switch (route().current()) {

        // case "grp.org.fulfilments.show.catalogue.services.index":
        //     return route(
        //         'grp.org.fulfilments.show.catalogue.services.show',
        //         [route().params['organisation'], route().params['fulfilment'], service.slug])
        default:
            return null
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Code -->
        <template #cell(code)="{ item: service }">
            <component :is="serviceRoute(service) ? Link : 'div'" :href="serviceRoute(service) || '#'" :class="serviceRoute(service) ? 'primaryLink' : ''">
                {{ service['code'] }}
            </component>
        </template>

        <!-- Column: Shop Code -->
        <!-- <template #cell(shop_code)="{ item: service }">
            <Link v-if="service['shop_slug']" :href="serviceRoute(service)" class="secondaryLink">
                {{ service['shop_slug'] }}
            </Link>
        </template> -->

        <!-- Column: Icon -->
        <template #cell(state)="{ item: service }">
            <Icon :data="service['state_icon']" />
        </template>

        <!-- Column: Price -->
        <template #cell(price)="{ item: service }">
            {{ useLocaleStore().currencyFormat(service['currency_code'], service['price']) }} /{{
                service['unit_abbreviation'] }}
        </template>

        <!-- Column: Total -->
        <!-- <template #cell(total)="{ item: service }">
            {{ useLocaleStore().currencyFormat(service['currency_code'], service['total']) }}
        </template> -->

        <!-- Column: Workflow -->
        <template #cell(workflow)="{ item: service }">
            <template v-if="service['is_auto_assign']">
                <FontAwesomeIcon icon='fal fa-robot' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                {{ service['auto_label'] }}
            </template>
        </template>
    </Table>
</template>
