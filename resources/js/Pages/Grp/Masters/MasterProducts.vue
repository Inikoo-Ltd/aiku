<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Updated & Optimized by ChatGPT
-->

<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMasterProducts from "@/Components/Tables/Grp/Goods/TableMasterProducts.vue";
import { capitalize } from "@/Composables/capitalize";
import Dialog from "primevue/dialog";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus, faEdit, faPencil, faTimes } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import ListSelector from "@/Components/ListSelector.vue";
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { routeType } from "@/types/route";

library.add(faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus);

const props = defineProps<{
  pageHead: PageHeadingTypes;
  title: string;
  data: {};
  familyId: number;
  storeProductRoute : routeType
}>();

// dialog state
const showDialog = ref(false);

// Inertia form
const form = useForm({
  code: "",
  name: "",
  unit: 0,
  trade_units: [],
  price: 0
});

const priceCost = ref([])
const ListSelectorChange = (value) => {
  // replace instead of push
  priceCost.value = value.map((item) => item.value)

  if (value.length === 1) {
    form.name = value[0].name
    form.code = value[0].code
    form.unit = value[0].type
    form.price = value[0].value
  } else if (value.length > 1) {
    form.name = value[0].name
    form.code = value[0].code
    form.unit = value[0].type
  }
}

console.log(props)
// Submit handler
const submitForm = () => {
  form.post(route(props.storeProductRoute.name,props.storeProductRoute.parameters), {
    onSuccess: () => {
      showDialog.value = false;
      form.reset();
    },
    onStart: () => console.log('Starting...'),
    onError: (errors) => console.log('❌ Error', errors),
    onFinish: () => console.log('🏁 Finished'),
  });
};

// ListSelector Tabs
const selectorTab = [
  {
    label: "Recommended",
    routeFetch: {
      name: "grp.json.master-product-category.recommended-trade-units",
      parameters: {
        masterProductCategory: route().params["masterFamily"],
      },
    },
  },
  {
    label: "Taken",
    routeFetch: {
      name: "grp.json.master-product-category.taken-trade-units",
      parameters: {
        masterProductCategory: route().params["masterFamily"],
      },
    },
  },
  {
    label: "All",
    routeFetch: {
      name: "grp.json.master-product-category.all-trade-units",
      parameters: {
        masterProductCategory: route().params["masterFamily"],
      },
    },
  },
];

const profitMargin = computed(() => {
  if (!form.price || priceCost.value.length === 0) return null

  const baseCost = priceCost.value[0]

  // special case: if baseCost is 0, use unit * 10 as % (ex: type 18 -> 180%)
  if (baseCost === 0) {
    return `${form.price * 10}`
  }

  if (baseCost < 0) return "0"

  const margin = ((form.price - baseCost) / baseCost) * 100
  return `${margin.toFixed(2)}`
})
const detailsVisible = ref(false)
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
  <Dialog v-model:visible="showDialog" modal header="Create Product" :style="{ width: '42rem' }"
    :content-style="{ padding: '1rem 1.5rem' }">
    <div class="p-fluid space-y-5">

      <!-- Trade Unit Selector -->
      <div>
        <ListSelector v-model="form.trade_units" :withQuantity="true" :tabs="selectorTab" head_label="Trade Units"
          @update:model-value="ListSelectorChange" key_quantity="quantity" :routeFetch="{
            name: 'grp.json.master-product-category.recommended-trade-units',
            parameters: { masterProductCategory: route().params['masterFamily'] }
          }" />
        <small v-if="form.errors.trade_units" class="text-red-500 text-xs">
          <FontAwesomeIcon :icon="faCircleExclamation" class="mr-1" />
          {{ form.errors.trade_units }}
        </small>
      </div>

      <!-- Product Details Toggle -->
      <div v-if="form.trade_units.length" class="pt-2">
        <button class="text-sm font-semibold flex items-center gap-2 transition-colors duration-200"
          :class="detailsVisible ? 'text-red-500 hover:text-red-600' : 'text-gray-500 hover:text-gray-700'"
          @click="detailsVisible = !detailsVisible">
          <FontAwesomeIcon :icon="detailsVisible ? faTimes : faPencil" />
          <span>
            {{ detailsVisible ? trans('Close Product Details') : trans('Edit Product Details') }}
          </span>
        </button>

        <transition name="fade">
          <div v-if="detailsVisible" class="space-y-4 mt-4 p-4 rounded-xl border border-gray-200 bg-gray-50 shadow-sm">
            <div>
              <label class="font-medium block mb-1 text-sm">Code</label>
              <PureInput type="text" v-model="form.code" />
              <small v-if="form.errors.code" class="text-red-500 text-xs">
                <FontAwesomeIcon :icon="faCircleExclamation" class="mr-1" />
                {{ form.errors.code }}
              </small>
            </div>

            <div>
              <label class="font-medium block mb-1 text-sm">Name</label>
              <PureInput type="text" v-model="form.name" />
              <small v-if="form.errors.name" class="text-red-500 text-xs">
                <FontAwesomeIcon :icon="faCircleExclamation" class="mr-1" />
                {{ form.errors.name }}
              </small>
            </div>

            <div>
              <label class="font-medium block mb-1 text-sm">Unit</label>
              <PureInput v-model="form.unit" />
              <small v-if="form.errors.unit" class="text-red-500 text-xs">
                <FontAwesomeIcon :icon="faCircleExclamation" class="mr-1" />
                {{ form.errors.unit }}
              </small>
            </div>
          </div>
        </transition>
      </div>

      <!-- Price Input -->
      <div v-if="form.trade_units.length">
        <label class="font-medium block mb-1 text-sm">Price</label>
        <PureInputNumber v-model="form.price" suffix="text" :min-value="0">
          <template #suffix>
            <div
              class="flex justify-center items-center px-2 absolute inset-y-0 right-0 gap-x-1 opacity-80 select-none text-xs">
              <span v-if="profitMargin !== null"
                :class="[profitMargin > 0 && 'text-green-600 font-medium', profitMargin < 0 && 'text-red-600 font-medium']">
                <FontAwesomeIcon :icon="profitMargin > 0 ? faArrowTrendUp : faArrowTrendDown" class="mr-1" />
                {{ profitMargin }}%
              </span>
              <span v-else>–</span>
            </div>
          </template>
        </PureInputNumber>
        <small v-if="form.errors.price" class="text-red-500 text-xs">
          <FontAwesomeIcon :icon="faCircleExclamation" class="mr-1" />
          {{ form.errors.price }}
        </small>
      </div>
    </div>

    <!-- Dialog Footer -->
    <template #footer>
      <div class="flex justify-end gap-3">
        <Button label="Cancel" type="secondary" class="!px-5" @click="showDialog = false" />
        <Button type="save" :loading="form.processing" class="!px-6" @click="submitForm" />
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: all 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-5px);
}
</style>
