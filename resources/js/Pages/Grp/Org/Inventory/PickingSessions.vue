<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:55:18 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head, Link} from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import Table from "@/Components/Table/Table.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import Icon from "@/Components/Icon.vue";


defineProps<{
  data: object
  title: string
  pageHead: PageHeadingTypes
}>();


function referenceRoute(item) {
  /* return route(
    "grp.org.warehouses.show.dispatching.picking_sessions.index",
    [
      route().params["organisation"],
      route().params["warehouse"],
      item.slug
    ]); */
  return 'text'
}

</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <Table :resource="data"  class="mt-5">
    <template #cell(state)="{ item }">
      <Icon :data="item.state" class="px-1" />
    </template>

    <template #cell(reference)="{ item }">
      <Link v-if="item.reference" :href="referenceRoute(item)" class="secondaryLink">
      {{ item["reference"] }}
      </Link>
    </template>
  </Table>
</template>
