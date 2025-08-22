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
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue";

library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome);

const props = defineProps<{
  pageHead: PageHeadingTypes
  title: string
  data: {}
  familyId : Number
}>();

// state for PrimeVue modal
const showModal = ref(false);

const openModal = () => {
  showModal.value = true;
};
const closeModal = () => {
  showModal.value = false;
};
const dummy = ref([])

console.log(props)
const save = () => {
  router.post(
    route('grp.models.master_family.store-assets', { masterFamily: props.familyId }),
    {trade_units : [dummy.value]}
  )
}
</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #button-create-product="{ action }">
      <Button
        :style="action.style"
        :label="action.label"
        :icon="action.icon"
        @click="openModal"
        :key="`ActionButton${action.label}${action.style}`"
        :tooltip="action.tooltip"
       
      />
    </template>
  </PageHeading>

  <TableMasterProducts :data="data" />

  <!-- PrimeVue Dialog -->
  <Dialog v-model:visible="showModal" modal header="Create Product" :style="{ width: '40rem' }">
    <div class="p-4">
      <PureMultiselectInfiniteScroll
						v-model="dummy"
            :labelProp="'name'"
            :value-prop="'slug'"
             :mode="'tags'"
						:fetchRoute="{
              name : 'grp.json.master-product-category.recommended-trade-units',
              parameters : {
                masterProductCategory : route().params['masterFamily']
              }
            }"
				</PureMultiselectInfiniteScroll>
      <div class="flex justify-end gap-2">
        <Button label="Cancel" style="secondary" @click="closeModal" />
        <Button label="Save" style="primary" @click="save" />
      </div>
    </div>
  </Dialog>
</template>
