<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Updated & Optimized by ChatGPT
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { ref } from "vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMasterProducts from "@/Components/Tables/Grp/Goods/TableMasterProducts.vue";
import { capitalize } from "@/Composables/capitalize";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus, } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import { routeType } from "@/types/route";
import FormCreateMasterProduct from "@/Components/FormCreateMasterProduct.vue";

library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus);

const props = defineProps<{
  pageHead: PageHeadingTypes;
  title: string;
  data: {};
  familyId: number;
  storeProductRoute: routeType
}>();

// dialog state
const showDialog = ref(false);

</script>

<template>
  <!-- Page Title -->
  <Head :title="capitalize(title)" />

  <!-- Page Heading with slot button -->
  <PageHeading :data="pageHead">
    <template #button-master-product="{ action }">
      <Button :icon="action.icon" :label="action.label" @click="showDialog = true" :style="action.style" />
    </template>
  </PageHeading>

  <!-- Products Table -->
  <TableMasterProducts :data="data" />

  <!-- Dialog Create Product -->
  <FormCreateMasterProduct 
    :showDialog="showDialog" 
    :storeProductRoute="storeProductRoute" 
    @update:show-dialog="(value) => showDialog = value"
  />
</template>

<style>
</style>
