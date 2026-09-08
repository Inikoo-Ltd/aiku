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
        case 'grp.org.fulfilments.show.crm.customers.show.pallets.show':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                [route().params['organisation'], route().params['fulfilment'], route().params['fulfilmentCustomer'], storedItem.slug]);
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

</script>

<template>
    <Table :resource="data" :name="'stored_items'" class="mt-5">
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
        
        <template #cell(actions)="{ item: value }">
            <div v-if="value.state == 'in_process' || route().current() == 'grp.org.fulfilments.show.crm.customers.show.pallet_returns.show'">
                <Link
                    :href="route(value.deleteRoute.name, value.deleteRoute.parameters)"
                    method="delete"
                    preserve-scroll
                    as="div"
                    @start="() => isLoading = 'delete' + value.id"
                    v-tooltip="'Delete Stored Item'"
                >
                    <Button icon="far fa-trash-alt" :loading="isLoading === 'delete' + value.id" type="negative" />
                </Link>
            </div>
        </template>

        <template #cell(pallets)="{ item: pallet }">
            <div v-if="pallet.pallets.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
                <Tag v-for="item of pallet.pallets"
                    :label="`${item.reference}`" :closeButton="false" :stringToColor="true">
                    <template #label>
                        <div class="whitespace-nowrap text-xs">
                            {{ `${item.reference} (${item.quantity_stored_item})` }} 
                        </div>
                    </template>
                </Tag>
            </div>
            <div v-else class="text-gray-400 text-xs italic">
                {{ trans("This Customer's SKO have no pallet") }}
            </div>
        </template>
    </Table>
</template>
