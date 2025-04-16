<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 24 Oct 2022 22:46:45 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import TableIngredients from "@/Components/Tables/Grp/Goods/TableIngredients.vue";
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
  <TableIngredients :data="data" />
</template>

