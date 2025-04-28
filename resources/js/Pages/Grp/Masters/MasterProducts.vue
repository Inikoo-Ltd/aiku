<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 29 Dec 2024 03:02:54 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMasterProducts from "@/Components/Tables/Grp/Goods/TableMasterProducts.vue";
import { capitalize } from "@/Composables/capitalize";
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome);

defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {}
}>();
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <TableMasterProducts :data="data" />
</template>
  