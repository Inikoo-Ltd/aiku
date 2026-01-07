<script setup lang="ts">
import { computed, ref, provide, watch } from "vue"
import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"

import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import SalesAnalyticsCompact from "@/Components/Product/SalesAnalyticsCompact.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

import { useLayoutStore } from "@/Stores/layout"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@far"
import { faBarcode, faStar } from "@fas"
import { trans } from "laravel-vue-i18n"

import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"

library.add(faImage, faBarcode)

const layout = useLayoutStore()
provide("layout", layout)


type Variant = {
  label: string
  options: string[]
}

type VariantProductMap = {
  product: { id: number }
  [key: string]: any
}

type MasterProduct = {
  id: number
  name: string
  unit?: string
  units?: number | string
  created_at?: string
  marketing_weight?: string
  gross_weight?: string
  marketing_dimensions?: string
  barcode?: string
  main_images?: { webp?: string }
  salesData?: any
}


const props = defineProps<{
  data: {
    variant: {
      data: {
        variants: Variant[]
        products: Record<string, VariantProductMap>
      }
    }
    products: {
      data: MasterProduct[]
    }
  }
}>()


const variantProducts = computed(() =>
  Object.values(props.data.variant.data.products)
)

const products = computed<MasterProduct[]>(() =>
  variantProducts.value
    .map(v => {
      const product = props.data.products.data.find(
        p => p.id === v.product.id
      )

      if (!product) return null

      return {
        ...product,
        is_leader: v.is_leader,
      }
    })
    .filter(Boolean) as MasterProduct[]
)


const variants = computed(() => props.data.variant.data.variants)

const selectedVariants = ref<Record<string, string>>({})
const activeIndex = ref(0)

const selectedProduct = computed(
  () => products.value[activeIndex.value] ?? null
)



const resolveProductByVariants = () => {
  const index = variantProducts.value.findIndex(entry =>
    Object.entries(selectedVariants.value).every(
      ([key, value]) => entry[key] === value
    )
  )

  if (index !== -1) activeIndex.value = index
}

const toggleVariant = (label: string, option: string) => {
  selectedVariants.value = { ...selectedVariants.value, [label]: option }
  resolveProductByVariants()
}

const selectProduct = (index: number) => {
  activeIndex.value = index
  syncVariantsFromProduct(index)
}

const syncVariantsFromProduct = (index: number) => {
  const entry = variantProducts.value[index]
  if (!entry) return

  const next: Record<string, string> = {}
  variants.value.forEach(v => {
    if (entry[v.label]) next[v.label] = entry[v.label]
  })

  selectedVariants.value = next
}
const getVariantLabel = (index: number) => {
  const entry = variantProducts.value[index]
  if (!entry) return null

  return variants.value
    .map(v => entry[v.label])
    .filter(Boolean)
    .join(" – ")
}



watch(
  variants,
  v => {
    v.forEach(variant => {
      if (!selectedVariants.value[variant.label]) {
        selectedVariants.value[variant.label] = variant.options[0]
      }
    })
    resolveProductByVariants()
  },
  { immediate: true }
)

console.log("MasterVariantShowcase props:", props)
</script>

<template>
  <section class="grid grid-cols-12 gap-3 p-3">

    <div class="col-span-12 lg:col-span-9 grid grid-cols-1 lg:grid-cols-3 gap-3">

      <div class="space-y-3">
        <div class="bg-white border rounded-lg shadow-xs overflow-hidden">
          <div class="aspect-square flex items-center justify-center bg-gray-50 relative">
            <Image v-if="selectedProduct?.main_images" :src="selectedProduct.main_images" :alt="selectedProduct.name"
              preview image-class="object-contain w-full h-full p-3" />

            <div v-else class="flex flex-col items-center text-gray-400">
              <FontAwesomeIcon icon="image" class="text-2xl mb-1" />
              <span class="text-[11px]">{{ trans("No product image") }}</span>
            </div>

            <span v-if="selectedProduct?.unit"
              class="absolute top-2 left-2 bg-white/90 text-[10px] px-2 py-0.5 rounded-md shadow-sm">
              {{ selectedProduct.unit }}
            </span>
          </div>

          <div class="border-t px-3 py-2 space-y-2">
            <div v-for="variant in variants" :key="variant.label" class="flex items-center gap-2">
              <span class="text-[11px] font-medium text-gray-500 w-14">
                {{ variant.label }}
              </span>

              <div class="flex flex-wrap gap-1.5">
                <Button v-for="option in variant.options"
                  :key="`${variant.label}-${option}-${selectedVariants[variant.label]}`" size="xs"
                  :type="selectedVariants[variant.label] === option ? 'primary' : 'tertiary'" class="!px-2 !py-1"
                  @click="toggleVariant(variant.label, option)">
                  {{ option }}
                </Button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="lg:col-span-2 space-y-3">

        <div class="bg-white shadow-xs p-0.5 rounded-md">
          <Swiper :space-between="6" :breakpoints="{
            0: { slidesPerView: 3.2 },
            640: { slidesPerView: 4.5 },
            1024: { slidesPerView: 8 }
          }">
            <SwiperSlide v-for="(product, index) in products" :key="product.id">
              <button class="relative w-full rounded-lg border transition p-2 flex flex-col items-center gap-1" :class="index === activeIndex
                ? 'ring-1 primary'
                : 'border-gray-200 hover:border-gray-300'" @click="selectProduct(index)">
                <!-- ⭐ LEADER BADGE -->
                <span v-if="product.is_leader" class="absolute top-1 right-1">
                  <FontAwesomeIcon :icon="faStar" class="text-yellow-400 text-sm drop-shadow" v-tooltip="trans('Leader')"/>
                </span>


                <!-- IMAGE -->
                <div class="aspect-square w-14 flex items-center justify-center bg-gray-50 rounded-md">
                  <Image v-if="product.main_images" :src="product.main_images"
                    class="max-h-12 max-w-12 object-contain" />
                  <FontAwesomeIcon v-else :icon="faImage" class="text-gray-300 text-sm" />
                </div>

                <!-- VARIANT LABEL -->
                <span
                  class="text-[11px] font-medium px-2 py-0.5 rounded bg-gray-100 text-gray-700 leading-tight text-center">
                  {{ getVariantLabel(index) }}
                </span>
              </button>
            </SwiperSlide>
          </Swiper>


        </div>


        <div class="bg-white rounded-lg border shadow-xs p-3 space-y-4">
          <div class="text-lg font-semibold text-gray-800">
            <ProductUnitLabel v-if="selectedProduct?.units" :units="selectedProduct.units" :unit="selectedProduct.unit"class="mr-2" />
              {{ selectedProduct?.name }}
             <FontAwesomeIcon v-if="selectedProduct.is_leader" :icon="faStar" class="text-yellow-400 text-sm drop-shadow" v-tooltip="trans('Leader')" />
          </div>

          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-500">{{ trans("Since") }}</dt>
              <dd class="font-medium">
                {{ useFormatTime(selectedProduct?.created_at) }}
              </dd>
            </div>

            <div class="flex justify-between">
              <dt class="text-gray-500">{{ trans("Weight") }}</dt>
              <dd class="font-medium">{{ selectedProduct?.gross_weight }}</dd>
            </div>

            <div class="flex justify-between">
              <dt class="text-gray-500">{{ trans("Dimensions") }}</dt>
              <dd class="font-medium">{{ selectedProduct?.marketing_dimensions }}</dd>
            </div>

            <div class="flex justify-between">
              <dt class="text-gray-500 flex items-center gap-1">
                {{ trans("Barcode") }}
                <FontAwesomeIcon :icon="faBarcode" />
              </dt>
              <dd class="font-medium">{{ selectedProduct?.barcode }}</dd>
            </div>
          </dl>
        </div>
        
      </div>
      
    </div>

    <aside class="col-span-12 lg:col-span-3 space-y-3">
         <SalesAnalyticsCompact
            :salesData="selectedProduct.salesData" />
    </aside>

  </section>
</template>

<style lang="scss" scoped>
.primary {
  color: var(--theme-color-5) !important;
  border: 1px solid color-mix(in srgb, var(--theme-color-4) 80%, black);
}
</style>