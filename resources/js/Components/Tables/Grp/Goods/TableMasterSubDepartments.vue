<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from '@/Components/Table/Table.vue'
import { RouteParams } from "@/types/route-params";
import { Link } from '@inertiajs/vue3'

defineProps<{
    data: object,
    tab?: string
}>()

function masterSubDepartmentRoute(subDepartment: {}) {
    switch (route().current()) {
        case 'grp.masters.departments.sub_departments.index':
            return route(
                'grp.masters.departments.sub_departments.show',
                [
                    (route().params as RouteParams).masterDepartment,
                    subDepartment.slug
                ]);
        default:
            return route(
                'grp.masters.shops.show.sub-departments.show',
                [
                    (route().params as RouteParams).masterShop,
                    (route().params as RouteParams).masterDepartment,
                    subDepartment.slug
                ]
        );
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: subDepartment }">
            <Link v-if="masterSubDepartmentRoute(subDepartment)" :href="masterSubDepartmentRoute(subDepartment)" class="primaryLink">
                {{ subDepartment["code"] }}
            </Link>
        </template>
    </Table>
</template>
