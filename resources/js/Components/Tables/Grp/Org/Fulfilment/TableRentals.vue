<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 25 Apr 2024 15:15:47 British Summer Time, Sheffield, UK
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

defineProps<{
    data: object
    tab?: string,
}>()


function rentalRoute(rental: {}) {
    switch (route().current()) {

        case "grp.org.fulfilments.show.catalogue.rentals.index":
            return route(
                'grp.org.fulfilments.show.catalogue.rentals.show',
                [route().params['organisation'], route().params['fulfilment'], rental.slug])
        default:
            return null
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Code -->
        <template #cell(code)="{ item: rental }">
            <Link v-if="rentalRoute(rental)" :href="rentalRoute(rental)" class="primaryLink">
                {{ rental['code'] }}
            </Link>
        </template>

        <!-- Column: Sales -->
        <template #cell(sales)="{ item: rental }">
            
            <div
                v-tooltip="
                    useLocaleStore().currencyFormat(
                        rental.currency_code,
                        rental?.sales || 0
                    )
                "
                :key="rental?.sales">
                {{
                    useLocaleStore().currencyFormat(
                        rental.currency_code,
                        rental?.sales || 0
                    )
                }}
            </div>
            
        </template>

        <!-- Column: Shop Code -->
        <template #cell(shop_code)="{ item: rental }">
            <Link v-if="rental['shop_slug']" :href="rentalRoute(rental)" class="secondaryLink">
                {{ rental['shop_slug'] }}
            </Link>
        </template>

        <!-- Column: Icon -->
        <template #cell(state)="{ item: rental }">
            <Icon :data="rental['state_icon']" />
        </template>

        <template #cell(rental_price)="{ item: rental }">
            {{ useLocaleStore().currencyFormat(rental['currency_code'], rental['rental_price']) }} /{{
                rental['unit_abbreviation'] }}
        </template>

        <!-- Column: Workflow -->
        <template #cell(workflow)="{ item: rental }">
            <template v-if="rental['auto_assign_asset']">
                <FontAwesomeIcon icon='fal fa-robot' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                {{ rental['auto_assign_asset'] }}: {{ rental['auto_assign_asset_type'] }}
            </template>
        </template>
    </Table>
</template>
