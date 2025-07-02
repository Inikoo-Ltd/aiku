<script setup lang="ts">
import { computed } from 'vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import Icon from '@/Components/Icon.vue'
import Button from "@/Components/Elements/Buttons/Button.vue"

const props = defineProps<{
  modelValue: {
    type?: string
    products?: any[]
  } | null
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: { type?: string; products?: any[] }): void
}>()

// Fallback-safe reactive model
const localType = computed({
  get: () => props.modelValue?.type ?? '',
  set: (val: string) => {
    emits('update:modelValue', {
      type: val,
      products: props.modelValue?.products ?? [],
    })
  }
})

const localProducts = computed({
  get: () => props.modelValue?.products ?? [],
  set: (val: any[]) => {
    emits('update:modelValue', {
      type: props.modelValue?.type ?? 'custom',
      products: val,
    })
  }
})

function updateProductAt(index: number, newProduct: any) {
  const updated = [...localProducts.value]
  updated[index] = newProduct
  localProducts.value = updated
}

function addEmptyProduct() {
  localProducts.value = [...localProducts.value, null]
}

function removeProduct(index: number) {
  const updated = [...localProducts.value]
  updated.splice(index, 1)
  localProducts.value = updated
}
</script>

<template>
  <!-- Type Selector -->
  <label class="block mb-2 font-medium text-gray-700">Type</label>
  <select
    v-model="localType"
    class="border border-gray-300 px-3 py-1 rounded mb-6 w-full"
  >
    <option value="">Select type</option>
    <option value="custom">Custom</option>
    <!-- Future: <option value="best-seller">Best Seller</option> -->
  </select>

  <!-- Product Inputs -->
  <div
    v-for="(product, index) in localProducts"
    :key="index"
    class="mb-6 border border-gray-200 p-4 rounded relative bg-white shadow-sm"
  >
    <div class="flex justify-between items-center mb-2">
      <label class="font-semibold text-gray-700">Product {{ index + 1 }}</label>
      <button
        type="button"
        class="text-red-600 hover:text-red-800 text-sm"
        @click="removeProduct(index)"
      >
        Remove
      </button>
    </div>

    <!-- Multiselect Product Field -->
    <PureMultiselectInfiniteScroll
      :modelValue="product"
      :object="true"
      @update:modelValue="(val) => updateProductAt(index, val)"
      :fetchRoute="{
        name: 'grp.json.product_category.products.index',
        parameters: {
          productCategory: '8265'
        }
      }"
      placeholder="Select product"
      valueProp="slug"
    >
      <template #singlelabel="{ value }">
        <div v-if="value">
          {{ value.code }} - {{ value.name }}
        </div>
        <div v-else class="text-gray-400 italic">Select product</div>
      </template>

      <template #option="{ option }">
        <div>
          {{ option.code }} - {{ option.name }}
        </div>
      </template>
    </PureMultiselectInfiniteScroll>
  </div>

  <!-- Add Product Button -->
  <Button type="create" label="Product" full @click="addEmptyProduct" />
</template>
