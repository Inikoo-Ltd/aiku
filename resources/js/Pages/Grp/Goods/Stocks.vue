<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:06:12 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableStocks from "@/Components/Tables/Grp/Goods/TableStocks.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

defineProps<{
  data: object
  title: string
  pageHead: PageHeadingTypes
}>();


</script>


<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <TableStocks :data="data" />
</template>

