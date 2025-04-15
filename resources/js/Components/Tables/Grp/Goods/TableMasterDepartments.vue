<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'

defineProps<{
    data: object,
    tab?: string
}>()

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: department }">
            <Link :href="route('grp.goods.catalogue.shops.show.departments.show', 
            { masterShop: route().params['masterShop'], masterDepartment: department.slug })" class="primaryLink">
                {{ department["code"] }}
            </Link>
        </template>
    </Table>
</template>
