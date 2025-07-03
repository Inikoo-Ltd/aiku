<script setup lang="ts">
import { computed } from 'vue'
import draggable from 'vuedraggable'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faGripVertical } from '@fas'

// Props
const props = defineProps<{
  modelValue: {
    type?: string
    products?: any[]
    top_sellers: any[]
  } | null
  productCategory: number | null
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: { type?: string; products?: any[] }): void
}>()

// Normalized model
const normalizedModelValue = computed(() => {
  return props.modelValue ?? {
    type: '',
    products: [],
    top_sellers: []
  }
})

const localType = computed({
  get: () => normalizedModelValue.value.type ?? '',
  set: (val: string) => {
    emits('update:modelValue', {
      type: val,
      products: normalizedModelValue.value.products ?? [],
      top_sellers: normalizedModelValue.value.top_sellers ?? []
    })
  }
})

const localProducts = computed({
  get: () => normalizedModelValue.value.products ?? [],
  set: (val: any[]) => {
    emits('update:modelValue', {
      type: normalizedModelValue.value.type ?? 'custom',
      products: val,
      top_sellers: normalizedModelValue.value.top_sellers ?? []
    })
  }
})

function updateProductAt(index: number, newProduct: any) {
  const updated = [...localProducts.value]
  updated[index] = newProduct
  localProducts.value = updated
}

function addEmptyProduct() {
  localProducts.value = [...localProducts.value, { id: `new ${localProducts.value.length}` }]
}

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
        <option value="best-seller">Best Seller</option>
      </select>
    </div>

    <!-- Draggable Custom Products -->
    <draggable
      v-if="localType === 'custom'"
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
            <FontAwesomeIcon :icon="faGripVertical" />
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

    <!-- Add Product Button -->
    <div v-if="localType === 'custom'" class="pt-2">
      <Button type="create" label="Add Product" full @click="addEmptyProduct" />
    </div>

    <!-- Best Seller Read-Only List -->
    <div v-else-if="localType === 'best-seller'" class="space-y-4">
      <div
        v-for="(product, index) in normalizedModelValue.top_sellers"
        :key="product.id || index"
        class="border border-gray-300 rounded p-4 bg-gray-50 shadow-sm relative"
      >
        <!-- Static Icon -->
        <div class="text-gray-300 text-sm mb-2 flex items-center gap-1">
          <FontAwesomeIcon :icon="faGripVertical" />
          <span>Best Seller Product {{ index + 1 }}</span>
        </div>

        <!-- Read-only Product Info -->
        <div class="text-gray-700">
          <div class="font-semibold">{{ product.code }} - {{ product.name }}</div>
          <div class="text-sm text-gray-500">Slug: {{ product.slug }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
