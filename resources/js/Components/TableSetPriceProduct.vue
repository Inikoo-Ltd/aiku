<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import { useForm } from "@inertiajs/vue3";
import { InputNumber } from "primevue";
import { inject } from "vue";
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
    data: ProductItem[];
}

const props = defineProps<{
    currency: string
    data: ProductData;
    master_price : number
}>();

const locale = inject('locale', {})

console.log(props)
const forms = props.data.data.reduce((acc: any, product) => {
    acc[product.product_id] = useForm({
        price: product.price,
    });
    return acc;
}, {});


function saveForm(item: ProductItem) {
    const form = forms[item.product_id]

    form.patch(route(item.update_route.name, item.update_route.parameters), {
        onSuccess: () => {
            form.defaults({ price: form.price })
        },
    })
}

console.log(props)
</script>


<template>
  <!-- Products (compact table) -->
  <div class="bg-white border rounded-md shadow-sm p-3">
    <div class="flex justify-between items-center mb-3">
      <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
        Products ({{ data.data.length }})
      </h3>
      <span class="px-2 py-0.5 text-[11px] rounded bg-gray-100 text-gray-600 border border-gray-200">
        Master Price: {{ locale.currencyFormat(data.currency || 'usd', master_price) }}
      </span>
    </div>

    <div v-if="data.data.length" class="overflow-x-auto">
      <table class="w-full border-collapse text-xs">
        <thead>
          <tr class="bg-gray-50 text-left font-medium text-gray-600 border-b border-gray-200">
            <th class="px-2 py-1">Code</th>
            <th class="px-2 py-1">Name</th>
            <th class="px-2 py-1">Price</th>
            <th class="px-2 py-1 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in data.data"
            :key="item.product_id"
            :class="[
              'transition-colors',
              forms[item.product_id].isDirty ? 'bg-yellow-50' : ''
            ]"
          >
            <td class="px-2 py-1 border-b border-gray-100 text-gray-600">
              {{ item.code || '-' }}
            </td>
            <td class="px-2 py-1 border-b border-gray-100 font-medium text-gray-700">
              {{ item.name }}
            </td>
            <td class="px-2 py-1 border-b  w-32">
              <InputNumber
                v-model="forms[item.product_id].price"
                mode="currency"
                :currency="item.currency"
                :step="0.25"
                showButtons
                inputClass="w-full text-xs"
              />
              <div
                v-if="forms[item.product_id].errors.price"
                class="text-[11px] text-red-500 mt-0.5"
              >
                {{ forms[item.product_id].errors.price }}
              </div>
            </td>
            <td class="px-2 py-1 border-b border-gray-100 text-center">
            <!--   <Button
                type="save"
                size="xs"
                label="Save"
                class="!px-2 !py-1 text-xs"
                :loading="forms[item.product_id].processing"
                :disabled="
                  forms[item.product_id].processing ||
                  !forms[item.product_id].isDirty
                "
                @click="saveForm(item)"
              /> -->
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-else
      class="text-xs text-gray-500 italic p-4 text-center bg-gray-50 rounded"
    >
      {{ trans("No data available") }}
    </div>
  </div>
</template>
