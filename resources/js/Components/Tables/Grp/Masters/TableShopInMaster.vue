<script setup lang="ts">
import Table from '@/Components/Table/Table.vue';
import { faPlus, faMinus } from "@fas";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faArrowRight, faCheck, faTimesCircle } from '@fal';
import { trans } from 'laravel-vue-i18n';
import { Link } from '@inertiajs/vue3';

library.add(faPlus, faMinus, faArrowRight, faCheck, faTimesCircle);
defineProps<{
    data: object,
    tab?: string
}>()

const getShopRoute = (shop: any) => {
    return route('grp.org.shops.show.dashboard.show', {organisation: shop.org_slug, shop: shop.slug})
}

</script>

<template>
    <Table :resource="data" class="mt-5" :name="tab" >
        <template #cell(state)="{ item }">
            <FontAwesomeIcon v-if="item.state == 'open'" :icon="faCheck" class="text-green-500" v-tooltip="trans('Shop is Open and Active')"/>
            <FontAwesomeIcon v-else :icon="faTimesCircle" class="text-red-500" v-tooltip="trans('Shop is Inactive')"/>
        </template>
        <template #cell(code)="{ item }">
            <Link :href="getShopRoute(item)" class="primaryLink">
                {{ item.code }}
            </Link>
        </template>
        <template #cell(name)="{ item }">
            {{ item.name }}
        </template>
        <template #cell(type)="{ item }">
            {{ item.type.toUpperCase() }}
        </template>
    </Table>
</template>
