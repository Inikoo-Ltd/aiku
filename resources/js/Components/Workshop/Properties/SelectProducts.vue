<script setup lang="ts">
import { computed } from 'vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { trans } from 'laravel-vue-i18n'
import Icon from '@/Components/Icon.vue'

const props = defineProps<{
  modelValue: {
    type?: string
    products?: any[]
  } | null
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: { type?: string; products?: any[] }): void
}>()

const localType = computed({
  get: () => props.modelValue?.type ?? '',
  set: (val) => {
    emits('update:modelValue', {
      ...(props.modelValue ?? { products: [] }),
      type: val
    })
  }
})

const localProducts = computed({
  get: () => props.modelValue?.products ?? [],
  set: (val) => {
    emits('update:modelValue', {
      ...(props.modelValue ?? { type: 'custom' }),
      products: val
    })
  }
})

function updateProductAt(index: number, newProduct: any) {
  const updated = [...localProducts.value]
  updated[index] = newProduct
  localProducts.value = updated
}

function addEmptyProduct() {
  localProducts.value = [...localProducts.value, null] // bisa juga pakai {} jika kamu ingin default object
}
</script>

<template>
  <!-- Type Selector -->
  <select
    v-model="localType"
    class="border border-gray-300 px-3 py-1 rounded mb-4"
  >
    <option value="">Select type</option>
    <option value="custom">Custom</option>
    <option value="best-seller">Best Seller</option>
  </select>

  <!-- Product Selectors -->
  <div
    v-for="(product, index) in localProducts"
    :key="index"
    class="mb-4"
  >
    <PureMultiselectInfiniteScroll
      :modelValue="product"
      @update:modelValue="(val) => updateProductAt(index, val)"
      :fetchRoute="{
        name: 'grp.json.product_category.products.index',
        parameters: {
          productCategory: '8265'
        }
      }"
      placeholder="Select products"
      valueProp="id"
    >
      <!-- Selected Label -->
      <template #singlelabel="{ value }">
        <div>{{ value.code }} - {{ value.name }} <Icon :data="value.state" /></div>
      </template>

      <!-- Dropdown Options -->
      <template #option="{ option }">
        <div>{{ option.code }} - {{ option.name }} <Icon :data="option.state" /></div>
      </template>
    </PureMultiselectInfiniteScroll>
  </div>

  <!-- Add Product Button -->
  <button
    type="button"
    class="bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 transition"
    @click="addEmptyProduct"
  >
    + Add Product
  </button>
</template>
