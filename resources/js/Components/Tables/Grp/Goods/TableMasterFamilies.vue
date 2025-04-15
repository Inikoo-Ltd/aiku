<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 May 2024 23:30:18 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import { Department } from "@/types/department"
import Icon from "@/Components/Icon.vue"
import { remove as loRemove } from 'lodash-es'
import { routeType } from '@/types/route'
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

defineProps<{
  data: object,
  tab?: string
}>()


function familyRoute(family: Family) {
  return route(
    "grp.goods.catalogue.shops.show.families.show",
    { masterShop: route().params["masterShop"], masterFamily: family.slug })
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
