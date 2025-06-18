<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"


const props = defineProps<{
    data: {}
    tab?: string
}>()
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item }">
            <Link :href="route('grp.org.shops.show.crm.polls.show', { organisation: route().params.organisation, shop: route().params.shop, poll: item.slug })" class="primaryLink">
                {{ item.name }}
            </Link>
        </template>
    </Table>
</template>
