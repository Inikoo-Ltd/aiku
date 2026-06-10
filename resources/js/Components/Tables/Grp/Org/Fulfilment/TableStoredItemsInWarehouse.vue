<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import { ref } from 'vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import Icon from "@/Components/Icon.vue"
import Tag from "@/Components/Tag.vue"
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShare, faSeedling, faInventory, faSignOutAlt, faGhost } from "@fal"

library.add(faShare, faSeedling, faInventory, faSignOutAlt, faGhost)

const props = defineProps<{
    data?: {}
    tab?: string
    state:any
    key:any 
    tableKey?: string
}>()
console.log('test')
const isLoading = ref<string | boolean>(false)
function storedItemRoute(storedItem) {
    switch (route().current()) {
        case 'grp.org.fulfilments.show.crm.customers.show.pallets.show':
        case 'grp.org.fulfilments.show.crm.customers.show.stored-items.index':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                [route().params['organisation'], route().params['fulfilment'], route().params['fulfilmentCustomer'], storedItem.slug]);
        case 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                [route().params['organisation'], route().params['fulfilment'], route().params['fulfilmentCustomer'], storedItem.slug]);
        case 'grp.org.warehouses.show.inventory.stored_items.current.index':
            return route(
                'grp.org.warehouses.show.inventory.stored_items.current.show',
                [route().params['organisation'], route().params['warehouse'], storedItem.slug]);
        
        case 'retina.fulfilment.itemised_storage.stored_items.index':
            return route(
                'retina.fulfilment.itemised_storage.stored_items.show',
                [storedItem.slug]
            )
        case 'retina.fulfilment.storage.pallets.show':
            return route(
                'retina.fulfilment.itemised_storage.stored_items.show',
                [storedItem.slug]
            )
        default:
            null
    }
}

const generateLinkPallets = (storedItem: {}) => {
    switch (route().current()) {
        case 'grp.org.fulfilments.show.crm.customers.show.pallets.show':
        case 'grp.org.fulfilments.show.crm.customers.show.stored-items.index':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                {
                    organisation: route().params['organisation'],
                    fulfilment: route().params['fulfilment'],
                    fulfilmentCustomer: route().params['fulfilmentCustomer'],
                    storedItem: storedItem.slug,
                    tab: 'pallets'
                });
        case 'retina.fulfilment.storage.pallets.show':
        case 'retina.fulfilment.itemised_storage.stored_items.index':
            return route(
                'retina.fulfilment.itemised_storage.stored_items.show',
                {
                    storedItem: storedItem.slug,
                    tab: 'pallets'
                });
        default:
            '#'
    }
}

const generateLinkAudits = (storedItem: {}) => {
    switch (route().current()) {
        case 'grp.org.fulfilments.show.crm.customers.show.pallets.show':
        case 'grp.org.fulfilments.show.crm.customers.show.stored-items.index':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                {
                    organisation: route().params['organisation'],
                    fulfilment: route().params['fulfilment'],
                    fulfilmentCustomer: route().params['fulfilmentCustomer'],
                    storedItem: storedItem.slug,
                    tab: 'audits'
                });
        case 'retina.fulfilment.storage.pallets.show':
        case 'retina.fulfilment.itemised_storage.stored_items.index':
            return route(
                'retina.fulfilment.itemised_storage.stored_items.show',
                {
                    storedItem: storedItem.slug,
                    tab: 'audits'
                });
        default:
            '#'
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: value }">
            {{ tableKey }}
            <Link v-if="route().current() != 'retina.fulfilment.storage.pallet_returns.show'" :href="storedItemRoute(value)"
                class="primaryLink">
                {{ value.reference }}
            </Link>
        </template>
        <template #cell(state)="{ item: palletDelivery }">
                <Icon  :data="palletDelivery['state_icon']" class="px-1" />
        </template>

        <template #cell(number_pallets)="{ item: value }">
            <Link v-if="generateLinkPallets(value)" :href="generateLinkPallets(value)" class="primaryLink">
                {{ value.number_pallets || 0 }}
            </Link>
        </template>

        <template #cell(number_audits)="{ item: value }">
            <Link v-if="generateLinkAudits(value)" :href="generateLinkAudits(value)" class="primaryLink">
                {{ value.number_audits || 0 }}
            </Link>
        </template>
    </Table>
</template>
