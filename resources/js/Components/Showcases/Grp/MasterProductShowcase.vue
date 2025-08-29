<script setup lang="ts">
import ProductCategoryCard from "@/Components/ProductCategoryCard.vue";
import { faImage, faSave } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import Image from "@/Components/Image.vue";
import { trans } from "laravel-vue-i18n";
import { useForm } from "@inertiajs/vue3";
import { InputNumber } from "primevue";
import { ref } from "vue";
import Button from "@/Components/Elements/Buttons/Button.vue";

// Interfaces
interface TradeUnit {
  id: number;
  name: string;
  code?: string;
  image?: {
    thumbnail: string;
  };
}

interface ProductItem {
  product_id: number;
  name: string;
  code?: string;
  shop_id: number;
  shop_name: string;
  shop_currency: string;
  price: number | string;
  update_route: {
    name: string;
    parameters: Record<string, any>;
  };
}

interface ProductData {
  id: number;
  name: string;
  image?: {
    source: string;
  };
  trade_units: TradeUnit[];
  products: ProductItem[];
}

const props = defineProps<{
  data: {
    data: ProductData;
  };
}>();

// Track editing + success highlight
const editingRowId = ref<number | null>(null);
const successRowId = ref<number | null>(null);

const forms = props.data.data.products.reduce((acc: any, product) => {
  acc[product.product_id] = useForm({
    price: product.price,
  });
  return acc;
}, {});

function startEditing(id: number) {
  editingRowId.value = id;
  successRowId.value = null; // reset success highlight if editing again
}

function saveForm(item: ProductItem) {
  const form = forms[item.product_id]

  form.put(route(item.update_route.name, item.update_route.parameters), {
    onSuccess: () => {
      // Reset form state so .isDirty = false
      form.defaults({ price: form.price })
    },
  })
}
</script>


<template>
  <div class="px-4 pb-10 m-5">
    <!-- Grid for left column + trade units -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-8 gap-6 mt-4">
      <!-- Left Column -->
      <div class="col-span-1 md:col-span-1 lg:col-span-2">
        <ProductCategoryCard :data="data.data">
          <template #image>
            <Image v-if="data?.data.image" :src="data?.data.image.source"
              class="w-full h-52 object-cover object-center rounded-t-lg" />
            <div v-else class="flex justify-center items-center bg-gray-100 w-full h-52 rounded-t-lg">
              <FontAwesomeIcon :icon="faImage" class="w-10 h-10 text-gray-400" />
            </div>
          </template>
        </ProductCategoryCard>
      </div>

      <!-- Trade Units -->
      <div class="col-span-1 md:col-span-2 lg:col-span-3 bg-white border rounded-lg shadow-sm p-4 h-fit">
        <h3 class="text-lg font-semibold mb-3 text-gray-800">
          Trade Units ({{ data.data.trade_units.length }})
        </h3>

        <div v-if="data.data.trade_units.length" class="divide-y border rounded-md bg-gray-50">
          <div v-for="item in data.data.trade_units" :key="item.id"
            class="flex items-center justify-between gap-4 p-3 bg-white hover:bg-gray-50 transition-colors">
            <!-- Info -->
            <div class="flex items-center gap-3">
              <Image v-if="item.image" :src="item.image.thumbnail"
                class="w-12 h-12 rounded object-cover shadow-sm border" />
              <div>
                <div class="font-medium text-gray-700">{{ item.name }}</div>
                <div class="flex mt-1 gap-3 text-xs text-gray-500">
                  <span>Code: {{ item.code || "-" }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-sm text-gray-500 italic p-3 bg-gray-50 rounded-md">
          {{ trans('No trade units available') }}
        </div>
      </div>
    </div>
    <!-- Products (full width below) -->
    <div class="mt-6 bg-white border rounded-lg shadow-sm p-4">
      <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-3">
        Products ({{ data.data.products.length }})
      </h3>

      <div v-if="data.data.products.length" class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-gray-100 text-left text-sm font-medium text-gray-600">
              <th class="px-4 py-2 border">Name</th>
              <th class="px-4 py-2 border">Code</th>
              <th class="px-4 py-2 border">Shop</th>
              <th class="px-4 py-2 border">Price</th>
              <th class="px-4 py-2 border text-center">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in data.data.products" :key="item.product_id" :class="[
              'transition-colors',
              forms[item.product_id].isDirty
                ? 'bg-yellow-50'
                : 'bg-white hover:bg-gray-100'
            ]">
              <td class="px-4 py-2 border font-medium text-gray-700">
                {{ item.name }}
              </td>
              <td class="px-4 py-2 border text-sm text-gray-600">
                {{ item.code || '-' }}
              </td>
              <td class="px-4 py-2 border text-sm text-gray-600">
                {{ item.shop_name }}
              </td>
              <td class="px-4 py-2 border w-40">
                <InputNumber v-model="forms[item.product_id].price" mode="currency" :currency="item.shop_currency"
                  :step="0.25" showButtons inputClass="w-full text-sm" />
                <div v-if="forms[item.product_id].errors.price" class="text-xs text-red-500 mt-1">
                  {{ forms[item.product_id].errors.price }}
                </div>
              </td>
              <td class="px-4 py-2 border text-center">
                <Button type="save"  :disabled="forms[item.product_id].processing || !forms[item.product_id].isDirty"  @click="saveForm(item)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="text-sm text-gray-500 italic p-6 text-center bg-gray-50 rounded-md">
        {{ trans('No products available') }}
      </div>
    </div>
  </div>
</template>
