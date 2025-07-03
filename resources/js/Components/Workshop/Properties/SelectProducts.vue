<script setup lang="ts">
import { computed } from 'vue'
import draggable from 'vuedraggable'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fas'
import { ulid } from 'ulid'

// Props
const props = defineProps<{
  modelValue: {
    type?: string
    products?: any[]
  } | null
  productCategory: number | null
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: { type?: string; products?: any[] }): void
}>()

// Ensure safe fallback structure if modelValue is null
const normalizedModelValue = computed(() => {
  return props.modelValue ?? {
    type: '',
    products: []
  }
})

// Local binding for product type
const localType = computed({
  get: () => normalizedModelValue.value.type ?? '',
  set: (val: string) => {
    emits('update:modelValue', {
      type: val,
      products: normalizedModelValue.value.products ?? []
    })
  }
})

// Local binding for product list
const localProducts = computed({
  get: () => normalizedModelValue.value.products ?? [],
  set: (val: any[]) => {
    emits('update:modelValue', {
      type: normalizedModelValue.value.type ?? 'custom',
      products: val
    })
  }
})

// Update a single product at a given index
function updateProductAt(index: number, newProduct: any) {
  const updated = [...localProducts.value]
  updated[index] = newProduct
  localProducts.value = updated
}

// Add a new placeholder product
function addEmptyProduct() {
  localProducts.value = [...localProducts.value, { id: `new ${localProducts.value.length}` }]
}

// Remove product by index
function removeProduct(index: number) {
  const updated = [...localProducts.value]
  updated.splice(index, 1)
  localProducts.value = updated
}
</script>

<template>
  <div class="space-y-6">
    <!-- Type Selection -->
    <div>
      <label class="block mb-2 font-semibold text-gray-700">Show Type</label>
      <select
        v-model="localType"
        class="border border-gray-300 px-4 py-2 rounded w-full focus:ring-2 focus:ring-primary focus:outline-none"
      >
        <option value="">Select type</option>
        <option value="custom">Custom</option>
        <!-- Future Option: <option value="best-seller">Best Seller</option> -->
      </select>
    </div>

    <!-- Product List (Draggable) -->
    <draggable
      v-model="localProducts"
      item-key="id"
      handle=".drag-handle"
      class="space-y-4"
      :animation="200"
    >
      <template #item="{ element: product, index }">
        <div class="border border-gray-300 rounded p-4 bg-white shadow-sm relative group">
          <!-- Remove Product -->
          <button
            type="button"
            class="absolute top-2 right-2 text-gray-400 hover:text-red-600"
            @click="removeProduct(index)"
            title="Remove product"
          >
            <FontAwesomeIcon :icon="faTimes" />
          </button>

          <!-- Drag Handle -->
          <div class="cursor-move drag-handle text-gray-400 hover:text-gray-600 text-sm mb-2 flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path d="M7 4a1 1 0 112 0 1 1 0 01-2 0zM7 10a1 1 0 112 0 1 1 0 01-2 0zM7 16a1 1 0 112 0 1 1 0 01-2 0zM11 4a1 1 0 112 0 1 1 0 01-2 0zM11 10a1 1 0 112 0 1 1 0 01-2 0zM11 16a1 1 0 112 0 1 1 0 01-2 0z" />
            </svg>
            <span>Drag to reorder</span>
          </div>

          <!-- Product Selector -->
          <PureMultiselectInfiniteScroll
            :modelValue="product"
            :object="true"
            @update:modelValue="(val) => updateProductAt(index, val)"
            :fetchRoute="{
              name: 'grp.json.shop.products',
              parameters: {
                shop: (route().params as any).shop
              }
            }"
            placeholder="Select product"
            valueProp="slug"
            :required="true"
          >
            <template #singlelabel="{ value }">
              <div v-if="value">{{ value.code }} - {{ value.name }}</div>
              <div v-else class="text-gray-400 italic">Select product</div>
            </template>

            <template #option="{ option }">
              <div>{{ option.code }} - {{ option.name }}</div>
            </template>
          </PureMultiselectInfiniteScroll>
        </div>
      </template>
    </draggable>

    <!-- Add Product -->
    <div class="pt-2">
      <Button type="create" label="Add Product" full @click="addEmptyProduct" />
    </div>
  </div>
</template>
