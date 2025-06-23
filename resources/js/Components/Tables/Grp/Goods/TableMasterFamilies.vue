<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { RouteParams } from "@/types/route-params";
import { MasterFamily } from "@/types/master-family";
import { trans } from "laravel-vue-i18n";

defineProps<{
    data: object,
    tab?: string
}>();


function familyRoute(masterFamily: MasterFamily) {
    console.log(route().current());
    if (route().current() == "grp.masters.master_departments.show.master_families.index") {
        return route(
            "grp.masters.master_departments.show.master_families.show",
            { masterDepartment: (route().params as RouteParams).masterDepartment, masterFamily: masterFamily.slug });
    } else {
        return route(
            "grp.masters.master_families.show",
            { masterFamily: masterFamily.slug });
    }
}

function masterDepartmentRoute(masterFamily: MasterFamily) {
    if (route().current() == "grp.masters.master_families.index") {
        return route(
            "grp.masters.master_departments.show",
            { masterDepartment: masterFamily.master_department_slug });
    } else {
        return route(
            "grp.masters.master_shops.show.master_departments.show",
            { masterShop: (route().params as RouteParams).masterShop, masterDepartment: masterFamily.master_department_slug });
    }
}

function masterShopRoute(masterFamily: MasterFamily) {
    return route("grp.masters.master_shops.show",
        {
            masterShop: masterFamily.master_shop_slug
        }
    );
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">

        <template #cell(master_shop_code)="{ item: department }">
            <Link v-tooltip="department.master_shop_name" :href="masterShopRoute(department) as string" class="secondaryLink">
                {{ department["master_shop_code"] }}
            </Link>
        </template>

        <template #cell(master_department_code)="{ item: department }">
            <Link v-if="department.master_department_slug" v-tooltip="department.master_department_name" :href="masterDepartmentRoute(department) as string" class="secondaryLink">
                {{ department["master_department_code"] }}
            </Link>
            <span v-else class="opacity-70  text-red-500">
        {{ trans("No department") }}
      </span>
        </template>

        <template #cell(code)="{ item: family }">
            <Link :href="familyRoute(family)" class="primaryLink">
                {{ family["code"] }}
            </Link>
        </template>

    </Table>
</template>
