<script setup lang="ts">
/*
* Author: Vika Aqordi
* Created on: 18-02-2025-09h-16m
* Github: https://github.com/aqordeon
* Copyright: 2025
*/
// import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
// import { RouteParams } from "@/types/route-params"
// import { MasterDepartment } from "@/types/master-department"

const props = defineProps<{
    data: {

    }
    tab?: string
}>()

// function departmentRoute(masterDepartment: MasterDepartment) {
//   if (route().current() == 'grp.masters.master_departments.index') {
//     return route('grp.masters.master_departments.show',
//       {
//         masterDepartment: masterDepartment.slug
//       }

//     )
//   } else {
//     return route('grp.masters.master_shops.show.master_departments.show',
//       {
//         masterShop: (route().params as RouteParams).masterShop,
//         masterDepartment: masterDepartment.slug
//       }
//     )

//   }
// }

// function masterShopRoute(masterDepartment: MasterDepartment) {
//   return route('grp.masters.master_shops.show',
//     {
//       masterShop: masterDepartment.master_shop_slug
//     }
//   )
// }

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- <template #cell(master_shop_code)="{ item: department }">
            <Link v-tooltip="department.master_shop_name" :href="(masterShopRoute(department) as string)"
                class="secondaryLink">
                {{ department["master_shop_code"] }}
            </Link>
        </template>
        <template #cell(code)="{ item: department }">
            <Link :href="(departmentRoute(department) as string)" class="primaryLink">
                {{ department["code"] }}
            </Link>
        </template> -->
    </Table>
</template>
