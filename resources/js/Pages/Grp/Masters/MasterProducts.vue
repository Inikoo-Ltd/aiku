<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 29 Dec 2024 03:02:54 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head, router, useForm } from "@inertiajs/vue3";
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
import ListSelector from "@/Components/ListSelector.vue";
import { notify } from "@kyvg/vue3-notification";
import PureInput from "@/Components/Pure/PureInput.vue";
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue";

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

const form = useForm({
  name: "",
  code: "",
  units: "",
  items: []
})
const loading = ref(false)
const isLoadingSubmit = ref(false)
const save = (products) => {
  const payload = products.map((item) => ({
    trade_unit_id: item.id,
    units: item.quantity_selected,
  }))

  router.post(
    route('grp.models.master_family.store-assets', { masterFamily: props.familyId }),
    { items: payload },
    {
      onStart: () => loading.value = true,
      onSuccess: () => {
        closeModal()
        console.log("✅ Saved successfully")
        notify({
          title: trans("Success"),
          text: 'Success create product',
          type: "error",
        })
      },
      onError: (error) => {
        console.error("❌ Error while saving:", errors)
        notify({
          title: trans("Something went wrong"),
          text: error?.response?.data?.message || error?.response?.data,
          type: "error",
        })
      },
      onFinish: () => loading.value = false
    }
  )
}

</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <!-- <template #other="{ action }">
      <Button  @click="openModal" type="create" :loading="loading" />
    </template> -->
  </PageHeading>

  <TableMasterProducts :data="data" />

  <!-- PrimeVue Dialog -->
  <Dialog v-model:visible="showModal" modal header="Create Master Family" :dismissableMask="true"
    :style="{ width: '60rem' }" class="rounded-2xl shadow-lg">
    <div class="p-6 space-y-6">
   
      <div>
        <ListSelector :modelValue="form.items" :withQuantity="true"
          :isLoadingSubmit="loading" :route-fetch="{
            name: 'grp.json.master-product-category.recommended-trade-units',
            parameters: { masterProductCategory: route().params['masterFamily'] }
          }" @submit="(products: {}[]) => save(products)" class="mt-4" />
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-3 pt-6 border-t">
        <Button label="Cancel" severity="secondary" @click="showModal = false" />
        <Button label="Save" :loading="loading" @click="save(form)" />
      </div>
    </div>
  </Dialog>

</template>

<style scoped></style>
