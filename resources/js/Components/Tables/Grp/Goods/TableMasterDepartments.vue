<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { RouteParams } from "@/types/route-params";
import { MasterDepartment} from "@/types/master-department";

defineProps<{
    data: object,
    tab?: string
}>()

function departmentRoute(masterDepartment: MasterDepartment) {
  if (route().current()=='grp.masters.departments.index') {
    return route('grp.masters.departments.show',
      {
        masterDepartment: masterDepartment.slug }

    )
  }else{
    return route('grp.masters.shops.show.departments.show',
      {
        masterShop: (route().params as RouteParams).masterShop,
        masterDepartment: masterDepartment.slug }
    )

  }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item: department }">
            <Link :href="departmentRoute(department) as string" class="primaryLink">
                {{ department["code"] }}
            </Link>
        </template>
    </Table>
</template>
