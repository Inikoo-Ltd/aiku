<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { RouteParams } from "@/types/route-params";

interface Family {
  slug: string;
  code: string;
}

defineProps<{
  data: object,
  tab?: string
}>();


function familyRoute(family: Family) {
  if (route().current() == "grp.masters.families.index") {
    return route(
      "grp.masters.families.show",
      { masterFamily: family.slug });
  } else {
    return route(
      "grp.masters.shops.show.families.show",
      { masterShop: (route().params as RouteParams).masterShop, masterFamily: family.slug });
  }


}


</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(code)="{ item: family }">
      <Link :href="familyRoute(family)" class="primaryLink">
        {{ family["code"] }}
      </Link>
    </template>
  </Table>
</template>
