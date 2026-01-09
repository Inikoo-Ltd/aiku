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
import Image from "@/Components/Image.vue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { faTriangle, faEquals, faMinus } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

defineProps<{
    data: object,
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)

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
    console.log(route().current())
  if (route().current()=='grp.masters.master_departments.index') {
    return route('grp.masters.master_departments.show.master_sub_departments.index',
      {
        masterDepartment: masterDepartment.slug,
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
  if (route().current()=='grp.masters.master_departments.index') {
    return route('grp.masters.master_departments.show.master_collections.index',
      {
        masterDepartment: masterDepartment.slug,
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
  if (route().current()=='grp.masters.master_departments.index') {
    return route('grp.masters.master_departments.show.master_families.index',
      {
        masterDepartment: masterDepartment.slug,
    }
    )
  }

  return route("grp.masters.master_shops.show.master_departments.show.master_families.index",
    {
      masterShop: (route().params as RouteParams).masterShop,
      masterDepartment: masterDepartment.slug }
  )
}

function ProductRoute(masterDepartment: MasterDepartment) {
  if (route().current()=='grp.masters.master_departments.index') {
    return route('grp.masters.master_departments.show.master_products.index',
      {
        masterDepartment: masterDepartment.slug,
    }
    )
  }

  return route('grp.masters.master_shops.show.master_departments.show.master_products.index',
    {
      masterShop: (route().params as RouteParams).masterShop,
      masterDepartment: masterDepartment.slug }
  )
}

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return {
            icon: faTriangle
        }
    } else if (!isPositive) {
        return {
            icon: faTriangle,
            class: 'rotate-180'
        }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    if (isPositive) {
        return 'text-green-500'
    } else if (!isPositive) {
        return 'text-red-500'
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(image_thumbnail)="{ item: collection }">
            <div class="flex justify-center">
                <Image :src="collection['image_thumbnail']" class="w-6 aspect-square rounded-full overflow-hidden shadow" />
            </div>
        </template>
      <template #cell(master_shop_code)="{ item: department }">
        <Link v-tooltip="department.master_shop_name" :href="masterShopRoute(department) as string" class="secondaryLink">
          {{ department["master_shop_code"] }}
        </Link>
      </template>

        <template #cell(sales)="{ item: department }">
            <span class="tabular-nums">{{ locale.currencyFormat(department.currency_code, department.sales) }}</span>
        </template>

        <template #cell(sales_delta)="{ item }">
            <div v-if="item.sales_delta">
                <span>{{ item.sales_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_delta.is_positive).class,
                        getIntervalStateColor(item.sales_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faEquals"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
        </template>

        <template #cell(invoices_delta)="{ item }">
            <div v-if="item.invoices_delta">
                <span>{{ item.invoices_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.invoices_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.invoices_delta.is_positive).class,
                        getIntervalStateColor(item.invoices_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faMinus"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    :icon="faEquals"
                    class="text-xxs md:text-sm"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
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
