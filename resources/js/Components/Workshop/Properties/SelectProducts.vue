<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import draggable from 'vuedraggable'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faGripVertical } from '@fas'
import axios from 'axios'
import { set } from 'lodash-es'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { trans } from 'laravel-vue-i18n'
import Image from '@/Components/Image.vue'

interface Product {
    id: number
    code: string
    name: string
    slug: string
}

// Props
const props = defineProps<{
    modelValue: {
        type?: string
        products?: Product[]
        top_sellers: Product[]
        other_family: {
            id: number
            name: string
            slug: string
            code: string
            option: Product[]
        }
        current_family: {
            id: number
            slug: string
            title: string
            option: Product[]}  // Max 4 products from the current family
    } | null
    productCategory: number | null
    shop: {
        id: number
        slug: string
    }
    family: {
        id: number
        slug: string
        name: string
    }
}>()


const emits = defineEmits<{
    (e: 'update:modelValue', value: keyof typeof props.modelValue): void
}>()

// Normalized model
const normalizedModelValue = computed(() => {
    return props.modelValue ?? {
        type: '',
        products: [],
        top_sellers: [],
        current_family: {
            id: props.family?.id || null,
            slug: props.family?.slug || '',
            name: props.family?.name || '',
            option: []
        },
        other_family: {
            id: null,
            name: '',
            slug: '',
            code: '',
            option: []
        },
    }
})

const isLoadingOtherFamily = ref(false)
const fetchProductFromFamily = async (idFamily: number | null) => {
    console.log('Fetching products for family ID:', idFamily)
    isLoadingOtherFamily.value = true
    if (!idFamily) {
        isLoadingOtherFamily.value = false
        return
    }

    try {
        const response = await axios.get(route('grp.json.product_category.products.index', { productCategory: idFamily }))

        console.log('Fetched Products:', response.data)
        if (response.data.data && response.data.data.length > 5) {
            set(normalizedModelValue.value, ['other_family', 'option'], response.data.data.slice(0, 6))
        } else {
            set(normalizedModelValue.value, ['other_family', 'option'], response.data.data)
        }
    } catch (error) {
        console.error('Error fetching products:', error)
    } finally {
        isLoadingOtherFamily.value = false
    }
}
// const fetchFamiliesList = async () => {
//     isLoadingOtherFamily.value = true
//     try {
//         const response = await axios.get(route('grp.json.shop.families', { shop: props.shop.id }))
//         console.log('Fetched Families qqqq:', response.data)
//         normalizedModelValue.value.other_family = response.data
//     } catch (error) {
//         console.error('Error fetching products:', error)
//     } finally {
//         isLoadingOtherFamily.value = false
//     }
// }
onMounted(() => {
  // fetchProductFromFamily()
//   fetchFamiliesList()
})

const localType = computed({
  get: () => normalizedModelValue.value.type ?? '',
  set: (newType: string) => {
    console.log('Setting localType to:', newType)
    if (newType === 'other-family') {
        fetchProductFromFamily(normalizedModelValue.value.other_family?.id)
    }
    
    emits('update:modelValue', {
        type: newType,
        products: normalizedModelValue.value.products ?? [],
        top_sellers: normalizedModelValue.value.top_sellers ?? [],
        current_family: normalizedModelValue.value.current_family ?? [],
        other_family: normalizedModelValue.value.other_family ?? {},
    })
  }
})

const localProducts = computed({
    get: () => normalizedModelValue.value.products ?? [],
    set: (val: any[]) => {
        emits('update:modelValue', {
            type: normalizedModelValue.value.type ?? 'custom',
            products: val,
            top_sellers: normalizedModelValue.value.top_sellers ?? [],
            current_family: normalizedModelValue.value.current_family ?? [],
            other_family: normalizedModelValue.value.other_family ?? {},
        })
    }
})

const onChangeOtherFamilyId = async (idFamily: number) => {
    set(normalizedModelValue.value, ['other_family', 'id'], idFamily)
    await fetchProductFromFamily(idFamily)
    emits('update:modelValue', {
        ...normalizedModelValue.value,
        other_family: {
            id: idFamily,
            name: normalizedModelValue.value.other_family?.name,
            code: normalizedModelValue.value.other_family?.code,
            slug: normalizedModelValue.value.other_family?.slug,
            option: normalizedModelValue.value.other_family?.option
        }
    })
}

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
        <option value="other-family">Other Family</option>
        <option value="current-family">
          <div>
            Current Family
            <FontAwesomeIcon icon="fas fa-plus" class="text-gray-500" fixed-width aria-hidden="true" />
          </div>
        </option>
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
    <div v-if="localType === 'custom'" class="xpt-2">
        <Button type="dashed" icon="fas fa-plus" label="Add Product" full @click="addEmptyProduct" />
    </div>

    <!-- Section: Best Seller Read-Only List -->
    <div v-else-if="localType === 'best-seller'" class="space-y-4">
        <div
            v-for="(product, index) in normalizedModelValue.top_sellers"
            :key="product.id || index"
            class="border border-gray-300 rounded px-2 py-4 bg-gray-50 shadow-sm relative grid grid-cols-5 gap-x-2"
        >
            <div class=" h-fit shadow">
                <Image
                    :src="product.web_images?.main?.thumbnail"
                    xclass=" object-cover rounded"
                    imageCover
                    :alt="product.name"
                />
            </div>
            <div class="col-span-4">
                <!-- Static Icon -->
                <div class="text-gray-400 text-sm xmb-2 flex items-center gap-1">
                    <!-- <FontAwesomeIcon :icon="faGripVertical" /> -->
                    <span>Best seller product {{ index + 1 }} </span>
                </div>
                <!-- Read-only Product Info -->
                <div class="text-gray-700">
                    <div class="font-semibold text-base">{{ product.name }}</div>
                    <div class="text-xs text-gray-500">Code: {{ product.code }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Other Family -->
    <div v-else-if="localType === 'other-family'" class="space-y-4" >
        <!-- {{ normalizedModelValue.other_family?.id }} -->
        <div>
            <div class="xmb-2 text-gray-700 text-sm">
                <FontAwesomeIcon icon="fas fa-asterisk" class="text-red-500 text-xxs" fixed-width aria-hidden="true" />
                {{ trans("Select family to show products from:") }}
            </div>

            <PureMultiselectInfiniteScroll
                xv-model="normalizedModelValue.other_family.idFamily"
                :modelValue="normalizedModelValue.other_family?.id"
                :initOptions="[{
                    id: normalizedModelValue.other_family?.id,
                    name: normalizedModelValue.other_family?.name,
                    code: normalizedModelValue.other_family?.code,
                    slug: normalizedModelValue.other_family?.slug
                }]"
                @update:modelValue="(val) => {
                    onChangeOtherFamilyId(val)
                }"
                @selectedObject="(selectedObject) => {
                    console.log('selectedObject', e)
                    set(normalizedModelValue, ['other_family'], selectedObject)
                }"
                :fetch-route="{
                    name: 'grp.json.shop.families',
                    parameters: { shop: props.shop.id }
                }"
                xobject
                placeholder="Select family"
            />
        </div>

        <div v-if="normalizedModelValue.other_family?.option || isLoadingOtherFamily" class="relative space-y-2 min-h-12">
            <template v-if="normalizedModelValue.other_family?.option?.length">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300" />
                    </div>
                    <div class="relative flex justify-center">
                        <span class="bg-white px-2 text-sm text-gray-500">{{ trans("Products example") }}</span>
                    </div>
                </div>
                
                <div
                    v-for="(product, index) in normalizedModelValue.other_family?.option"
                    :key="product.id || index"
                    class="border border-gray-300 rounded px-2 py-4 bg-gray-50 shadow-sm relative grid grid-cols-5 gap-x-2"
                >
                    <div class=" h-fit shadow">
                        <Image
                            :src="product.web_images.main.thumbnail"
                            xclass=" object-cover rounded"
                            imageCover
                            :alt="product.name"
                        />
                    </div>
                    <div class="col-span-4">
                        <!-- Static Icon -->
                        <div class="text-gray-400 text-sm xmb-2 flex items-center gap-1">
                            <span>Product {{ index + 1 }} from {{ normalizedModelValue.other_family?.name }} </span>
                        </div>
                        <!-- Read-only Product Info -->
                        <div class="text-gray-700">
                            <div class="font-semibold">{{ product.name }}</div>
                            <div class="text-xs text-gray-500">Code: {{ product.code }}</div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-else-if="!isLoadingOtherFamily" class="text-gray-500 text-sm text-center py-2 bg-gray-200">
                {{ trans("No products found in this family.") }}
            </div>

            <div v-if="isLoadingOtherFamily" class="flex items-center justify-center absolute bg-black/40 text-white text-3xl top-0 inset-0">
                <LoadingIcon />
            </div>
        </div>
    </div>

    <!-- Section: Current Family (read only) -->
    <div v-else-if="localType === 'current-family'" class="space-y-4">
        <template v-if="normalizedModelValue.current_family?.option?.length">
            <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300" />
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-2 text-sm text-gray-500">{{ trans("Products example") }}</span>
                </div>
            </div>

            <div
                v-for="(product, index) in normalizedModelValue.current_family?.option"
                :key="product.id || index"
                class="border border-gray-300 rounded px-2 py-4 bg-gray-50 shadow-sm relative grid grid-cols-5 gap-x-2"
            >
                <div class=" h-fit shadow">
                    <Image
                        :src="product.web_images.main.thumbnail"
                        xclass=" object-cover rounded"
                        imageCover
                        :alt="product.name"
                    />
                </div>
                <div class="col-span-4">
                    <!-- Static Icon -->
                    <div class="text-gray-400 text-xs mb-2 flex items-start gap-1">
                        <!-- <FontAwesomeIcon :icon="faGripVertical" class="mt-1" fixed-width aria-hidden="true" /> -->
                        <span>Product {{ index + 1 }} from <span class="font-semibold">{{normalizedModelValue.current_family.name}}</span> </span>
                    </div>
                    <!-- Read-only Product Info -->
                    <div class="text-gray-700">
                        <div class="font-semibold text-sm">{{ product.name }}</div>
                        <div class="text-xs text-gray-500">Code: {{ product.code }}</div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else-if="!isLoadingOtherFamily" class="text-gray-500 text-sm text-center py-2 bg-gray-200">
            {{ trans("No products found in this family.") }}
        </div>
    </div>
  </div>
</template>
