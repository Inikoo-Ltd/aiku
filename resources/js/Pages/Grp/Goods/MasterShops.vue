<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMasterShops from "@/Components/Tables/Grp/Goods/TableMasterShops.vue";
import { capitalize } from "@/Composables/capitalize";
import { faFolderOpen, faAtom, faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";

library.add(faFolderOpen, faAtom, faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome);

defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {}
}>();
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <TableMasterShops :data="data" />
</template>
  