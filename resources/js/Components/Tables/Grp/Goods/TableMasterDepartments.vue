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
  if (route().current()=='grp.masters.master_departments.index') {
    return route('grp.masters.master_departments.show',
      {
        masterDepartment: masterDepartment.slug }

    )
  }else{
    return route('grp.masters.master_shops.show.master_departments.show',
      {
        masterShop: (route().params as RouteParams).masterShop,
        masterDepartment: masterDepartment.slug }
    )

  }
}

function masterShopRoute(masterDepartment: MasterDepartment) {
  return route('grp.masters.master_shops.show',
    {
      masterShop: masterDepartment.master_shop_slug
    }
  )
}


function subdepartmentRoute(masterDepartment: MasterDepartment) {
  if (route().current()=='grp.masters.master_shops.show.master_departments.index') {
    return route('grp.masters.master_shops.show.master_departments.show.master_sub_departments.index',
      {
        masterDepartment: masterDepartment.slug,
        masterShop: (route().params as RouteParams).masterShop
      }
    )
  } 
  
  return route('grp.masters.master_shops.show.master_departments.show.master_sub_departments.index',
    {
      masterShop: (route().params as RouteParams).masterShop,
      masterDepartment: masterDepartment.slug }
  )
}


function CollectionsRoute(masterDepartment: MasterDepartment) {
  if (route().current()=='grp.masters.master_shops.show.master_departments.index') {
    return route('grp.masters.master_shops.show.master_departments.show.master_collections.index',
      {
        masterDepartment: masterDepartment.slug,
        masterShop: (route().params as RouteParams).masterShop,
      }
    )
  } 
  
  return route('grp.masters.master_shops.show.master_departments.show.master_collections.index',
    {
      masterShop: (route().params as RouteParams).masterShop,
      masterDepartment: masterDepartment.slug }
  )
}

function familiesRoute(masterDepartment: MasterDepartment) {
  if (route().current()=='grp.masters.master_shops.show.master_departments.index') {
    return route('grp.masters.master_shops.show.master_departments.show.master_families.index',
      {
        masterDepartment: masterDepartment.slug,
        masterShop: (route().params as RouteParams).masterShop
    }
    )
  } 
  
  return route('grp.masters.master_shops.show.master_departments.show.master_families.index',
    {
      masterShop: (route().params as RouteParams).masterShop,
      masterDepartment: masterDepartment.slug }
  )
}

function ProductRoute(masterDepartment: MasterDepartment) {
  if (route().current()=='grp.masters.master_shops.show.master_departments.index') {
    return route('grp.masters.master_shops.show.master_departments.show.master_products.index',
      {
        masterDepartment: masterDepartment.slug,
        masterShop: (route().params as RouteParams).masterShop
    }
    )
  } 
  
  return route('grp.masters.master_shops.show.master_departments.show.master_products.index',
    {
      masterShop: (route().params as RouteParams).masterShop,
      masterDepartment: masterDepartment.slug }
  )
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
      <template #cell(master_shop_code)="{ item: department }">
        <Link v-tooltip="department.master_shop_name" :href="masterShopRoute(department) as string" class="secondaryLink">
          {{ department["master_shop_code"] }}
        </Link>
      </template>
      <template #cell(code)="{ item: department }">
            <Link :href="departmentRoute(department) as string" class="primaryLink">
                {{ department["code"] }}
            </Link>
      </template>
       <template #cell(sub_departments)="{ item: department }">
            <Link :href="subdepartmentRoute(department) as string" class="primaryLink">
                {{ department["sub_departments"] }}
            </Link>
      </template>
       <template #cell(collections)="{ item: department }">
            <Link :href="CollectionsRoute(department) as string" class="primaryLink">
                {{ department["collections"] }}
           </Link>
      </template>
      <template #cell(families)="{ item: department }">
            <Link :href="familiesRoute(department) as string" class="primaryLink">
                {{ department["families"] }}
            </Link>
      </template>
      <template #cell(products)="{ item: department }">
            <Link :href="ProductRoute(department) as string" class="primaryLink">
                {{ department["products"] }}
            </Link>
      </template>
    </Table>
</template>
