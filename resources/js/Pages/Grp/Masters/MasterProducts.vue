<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Updated & Optimized by ChatGPT
-->

<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { ref, computed, inject } from "vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableMasterProducts from "@/Components/Tables/Grp/Goods/TableMasterProducts.vue";
import { capitalize } from "@/Composables/capitalize";
import Dialog from "primevue/dialog";
import Button from "@/Components/Elements/Buttons/Button.vue";
import PureInput from "@/Components/Pure/PureInput.vue";
import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown, faHome, faPlus, faMinus, } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import ListSelector from "@/Components/ListSelector.vue";
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue";
import { trans } from "laravel-vue-i18n";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { routeType } from "@/types/route";
import { faMinimize, faExpand } from "@fortawesome/free-solid-svg-icons";
import { InputNumber } from "primevue";

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
const detailsVisible = ref(false)
const layout = inject('layout', {})
const currency = layout.group.currency

// Inertia form
const form = useForm({
  code: "",
  name: "",
  unit: 0,
  trade_units: [],
  price: null
});

const priceCost = ref([])
const ListSelectorChange = (value) => {
  if (value.length === 1) {
    form.name = value[0].name
    form.code = value[0].code
    form.unit = value[0].type
    /* form.price = value[0].value */
  } else if (value.length > 1) {
    form.name = value[0].name
    form.code = value[0].code
    form.unit = value[0].type
  }
}

// Submit handler
const submitForm = () => {
  form.post(route(props.storeProductRoute.name, props.storeProductRoute.parameters), {
    onSuccess: () => {
      showDialog.value = false;
      form.reset();
    },
    onStart: () => console.log('Starting...'),
    onError: (errors) => {
      if (form.errors.code || form.errors.unit || form.errors.name) {
        detailsVisible.value = true
      }
    },
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
  if (!form.price || !form.trade_units.length) return null

  // calculate total cost from trade_units
  const totalCost = form.trade_units.reduce((sum, unit) => {
    const unitPrice = Number(unit.value) || 0
    const unitQty = Number(unit.quantity) || 0
    return sum + (unitPrice * unitQty)
  }, 0)

  // no cost = return 0 margin
  if (totalCost <= 0) return "0"

  // profit margin formula
  const margin = ((form.price - totalCost) / totalCost) * 100
  return `${margin.toFixed(2)}`
})

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
        <ListSelector v-model="form.trade_units" :withQuantity="true" :tabs="selectorTab"
          head_label="Select Trade Units" @update:model-value="ListSelectorChange" key_quantity="quantity" :routeFetch="{
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
          :class="'text-gray-500 hover:text-gray-700'" @click="detailsVisible = !detailsVisible">
          <FontAwesomeIcon :icon="detailsVisible ? faMinimize : faExpand" />
          <span>
            {{ detailsVisible ? trans('Close Product Details') : trans('Edit Product Details') }}
          </span>
        </button>

        <transition name="fade">
          <div v-if="detailsVisible" class="space-y-4 mt-4 p-4 rounded-xl border border-gray-200 bg-gray-50 shadow-sm">
            <div>
              <label class="font-medium block mb-1 text-sm">Code</label>
              <PureInput type="text" v-model="form.code" @update:model-value="form.errors.code = null" />
              <small v-if="form.errors.code" class="text-red-500 text-xs">
                <FontAwesomeIcon :icon="faCircleExclamation" class="mr-1" />
                {{ form.errors.code }}
              </small>
            </div>

            <div>
              <label class="font-medium block mb-1 text-sm">Name</label>
              <PureInput type="text" v-model="form.name" @update:model-value="form.errors.name = null" />
              <small v-if="form.errors.name" class="text-red-500 text-xs">
                <FontAwesomeIcon :icon="faCircleExclamation" class="mr-1" />
                {{ form.errors.name }}
              </small>
            </div>

            <div>
              <label class="font-medium block mb-1 text-sm">Unit</label>
              <PureInput v-model="form.unit" @update:model-value="form.errors.unit = null" />
              <small v-if="form.errors.unit" class="text-red-500 text-xs">
                <FontAwesomeIcon :icon="faCircleExclamation" class="mr-1" />
                {{ form.errors.unit }}
              </small>
            </div>
          </div>
        </transition>
      </div>

      <!-- Price Input -->
      <div v-if="form.trade_units.length" class="space-y-2">
        <!-- Label -->
        <label class="font-semibold text-gray-700 text-sm flex items-center gap-2">
          <FontAwesomeIcon :icon="faTags" class="text-blue-500" />
          Price
        </label>

        <!-- Price Input -->
        <InputNumber v-model="form.price" inputId="horizontal-buttons" showButtons buttonLayout="horizontal"
          :step="0.25" mode="currency" :currency="currency.code" fluid :min="0"  :allowEmpty="true"
          class="w-full rounded-lg border border-gray-300 shadow-sm">
          <template #incrementbuttonicon>
            <FontAwesomeIcon :icon="faPlus" />
          </template>
          <template #decrementbuttonicon>
            <FontAwesomeIcon :icon="faMinus" />
          </template>
        </InputNumber>

        <!-- Errors & Profit Margin -->
        <div class="flex justify-between items-center text-xs mt-1">
          <!-- Error Message -->
          <small v-if="form.errors.price" class="text-red-500 flex items-center gap-1">
            {{ form.errors.price }}
          </small>

          <!-- Profit Margin -->
          <span v-if="profitMargin !== null" :class="[
            profitMargin > 0 ? 'text-green-600' : profitMargin < 0 ? 'text-red-600' : 'text-gray-500',
            'font-medium flex items-center gap-1'
          ]">
            <FontAwesomeIcon :icon="profitMargin > 0 ? faArrowTrendUp : faArrowTrendDown" />
            Profit Margin: {{ profitMargin }}%
          </span>
        </div>
      </div>

    </div>

    <!-- Dialog Footer -->
    <template #footer>
      <div class="flex justify-end gap-3">
        <Button label="Cancel" type="secondary" class="!px-5" @click="showDialog = false" />
        <Button type="save" :loading="form.processing" :disabled="form.trade_units.length < 1" class="!px-6"
          @click="submitForm" />
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
