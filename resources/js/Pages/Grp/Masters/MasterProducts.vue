<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 29 Dec 2024 03:02:54 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMasterProducts from "@/Components/Tables/Grp/Goods/TableMasterProducts.vue";
import { capitalize } from "@/Composables/capitalize";
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { ref } from "vue";
import Dialog from "primevue/dialog";
import { trans } from "laravel-vue-i18n";
import ProductsSelector from "@/Components/Dropshipping/ProductsSelector.vue";
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue";

library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome);

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {}
  familyId: Number
}>();

// state for PrimeVue modal
const showModal = ref(false);

const openModal = () => {
  showModal.value = true;
};
const closeModal = () => {
  showModal.value = false;
};


const isLoadingSubmit = ref(false)
const save = (products) => {
  const payload = products.map((item) => ({
    trade_unit_id: item.id,
    units: item.quantity_selected,
  }))

  router.post(
    route('grp.models.master_family.store-assets', { masterFamily: props.familyId }),
    { items: payload }
  )
}

</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #button-create-product="{ action }">
      <Button :style="action.style" :label="action.label" :icon="action.icon" @click="openModal"
        :key="`ActionButton${action.label}${action.style}`" :tooltip="action.tooltip" />
    </template>
  </PageHeading>

  <TableMasterProducts :data="data" />

  <!-- PrimeVue Dialog -->
  <Dialog v-model:visible="showModal" modal :show-header="false" header="Create" :dismissableMask="true"
    :style="{ width: '70rem', padding: '10px' }" :content-style="{ overflow: 'unset' }">
    <div class="pt-4">
      <ProductsSelector :headLabel="trans('Add Trade Units')" :withQuantity="true" :route-fetch="{
        name: 'grp.json.master-product-category.recommended-trade-units',
        parameters: {
          masterProductCategory: route().params['masterFamily']
        }
      }" :isLoadingSubmit @submit="(products: {}[]) => save(products)" class="px-4" />
    </div>
  </Dialog>
</template>

<style scoped></style>
