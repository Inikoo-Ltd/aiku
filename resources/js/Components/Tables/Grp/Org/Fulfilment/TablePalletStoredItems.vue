<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Table from '@/Components/Table/Table.vue';
import { ref } from 'vue';
import Button from '@/Components/Elements/Buttons/Button.vue';
import Icon from "@/Components/Icon.vue"
import Tag from "@/Components/Tag.vue"
import { ctrans } from '@/Composables/useTrans'

const props = defineProps<{
    data?: {}
    tab?: string
    state:any
    key:any 
    name: string
    tableKey?: string
}>()

function palletRoute(pallet) {
    switch (route().current()) {
        case 'grp.org.fulfilments.show.crm.customers.show.stored-items.index':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.pallets.show',
                [route().params['organisation'], route().params['fulfilment'], route().params['fulfilmentCustomer'], pallet.pallet_slug]);
        default:
            null
    }
}

function storedItemRoute(pallet) {
    switch (route().current()) {
        case 'grp.org.fulfilments.show.crm.customers.show.stored-items.index':
            return route(
                'grp.org.fulfilments.show.crm.customers.show.stored-items.show',
                [route().params['organisation'], route().params['fulfilment'], route().params['fulfilmentCustomer'], pallet.stored_item_slug]);
        default:
            null
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(pallet_reference)="{ item: palletDelivery }">
            <Link :href="palletRoute(palletDelivery)"
                class="primaryLink">
                {{ palletDelivery.pallet_reference }}
            </Link>
        </template>
        <template #cell(reference)="{ item: palletDelivery }">
            <Link :href="storedItemRoute(palletDelivery)"
                class="primaryLink">
                {{ palletDelivery.reference }}
            </Link>
        </template>
        <template #cell(state_icon)="{ item: palletDelivery }">
                <Icon  :data="palletDelivery.state_icon" class="px-1" />
        </template>
    </Table>
</template>
