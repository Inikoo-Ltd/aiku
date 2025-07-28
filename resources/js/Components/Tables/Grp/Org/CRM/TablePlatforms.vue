<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import {Link} from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"



const props = defineProps<{
    data: {}
    tab?: string
}>()

function platformRoute(platform) {
    return route(
        "grp.org.shops.show.crm.platforms.show",
        [route().params['organisation'], route().params['shop'], platform.slug]);
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: platform }">
            <Link :href="platformRoute(platform)" class="primaryLink">
                {{ platform.name }}
            </Link>
        </template>

        <template #cell(number_customer_sales_channels)="{ item: platform }">
            <span class="text-red-500">{{
                    platform.number_customer_sales_channel_broken
                }}</span>/{{ platform.number_customer_sales_channels }}
        </template>
    </Table>
</template>
