<script setup lang="ts">
import { computed, ref, provide, watch } from "vue"
import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"

import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import SalesAnalyticsCompact from "@/Components/Product/SalesAnalyticsCompact.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

import { useLayoutStore } from "@/Stores/layout"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@far"
import { faBarcode } from "@fas"
import { trans } from "laravel-vue-i18n"

import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@/Components/Image.vue"

library.add(faImage, faBarcode)

const layout = useLayoutStore()
provide("layout", layout)

/* ================= TYPES ================= */

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

/* ================= PROPS ================= */

const props = defineProps<{
    data: {
        master_variant: {
            data: {
                variants: Variant[]
                products: Record<string, VariantProductMap>
            }
        }
        master_products: {
            data: MasterProduct[]
        }
    }
}>()

/* ================= STATE ================= */

const variantProducts = computed(() =>
    Object.values(props.data.master_variant.data.products)
)

const products = computed<MasterProduct[]>(() =>
    variantProducts.value
        .map(v =>
            props.data.master_products.data.find(p => p.id === v.product.id)
        )
        .filter(Boolean) as MasterProduct[]
)

const variants = computed(() => props.data.master_variant.data.variants)

const selectedVariants = ref<Record<string, string>>({})
const activeIndex = ref(0)

const selectedProduct = computed(
    () => products.value[activeIndex.value] ?? null
)

/* ================= LOGIC ================= */

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
</script>

<template>
<section class="grid grid-cols-12 gap-3 p-3">

  <div class="col-span-12 lg:col-span-9 grid grid-cols-1 lg:grid-cols-3 gap-3">

    <div class="space-y-3">
      <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="aspect-square flex items-center justify-center bg-gray-50 relative">
          <Image
            v-if="selectedProduct?.main_images"
            :src="selectedProduct.main_images"
            :alt="selectedProduct.name"
            preview
            image-class="object-contain w-full h-full p-3"
          />

          <div v-else class="flex flex-col items-center text-gray-400">
            <FontAwesomeIcon icon="image" class="text-2xl mb-1" />
            <span class="text-[11px]">{{ trans("No product image") }}</span>
          </div>

          <span
            v-if="selectedProduct?.unit"
            class="absolute top-2 left-2 bg-white/90 text-[10px] px-2 py-0.5 rounded-md shadow"
          >
            {{ selectedProduct.unit }}
          </span>
        </div>

        <div class="border-t px-3 py-2 space-y-2">
          <div
            v-for="variant in variants"
            :key="variant.label"
            class="flex items-center gap-2"
          >
            <span class="text-[11px] font-medium text-gray-500 w-14">
              {{ variant.label }}
            </span>

            <div class="flex flex-wrap gap-1.5">
              <Button
                v-for="option in variant.options"
                :key="`${variant.label}-${option}`"
                size="xs"
                :type="selectedVariants[variant.label] === option ? 'primary' : 'tertiary'"
                class="!px-2 !py-1"
                @click="toggleVariant(variant.label, option)"
              >
                {{ option }}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="lg:col-span-2 space-y-3">

      <div class="bg-white shadow-sm p-0.5 rounded-md">
        <Swiper
          :space-between="4"
          :breakpoints="{
            0: { slidesPerView: 3.5 },
            640: { slidesPerView: 4.5 },
            1024: { slidesPerView: 6 }
          }"
        >
          <SwiperSlide
            v-for="(product, index) in products"
            :key="product.id"
          >
            <button
              class="w-full rounded-md border transition text-center p-0.5"
              :class="index === activeIndex
                ? 'border-primary bg-primary/5 ring-1 ring-primary/20'
                : 'border-gray-200 hover:border-gray-300'"
              @click="selectProduct(index)"
            >
              <div class="aspect-square w-10 mx-auto flex items-center justify-center bg-gray-50 rounded">
                <Image
                  v-if="product.main_images"
                  :src="product.main_images"
                  class="max-h-8 max-w-8 object-contain"
                />
              </div>
              <p class="mt-0.5 text-[8px] truncate text-gray-600">
                {{ product.name }}
              </p>
            </button>
          </SwiperSlide>
        </Swiper>
      </div>


      <div class="bg-white rounded-lg border shadow-sm p-3 space-y-4">
        <div class="text-lg font-semibold text-gray-800">
          <ProductUnitLabel
            v-if="selectedProduct?.units"
            :units="selectedProduct.units"
            :unit="selectedProduct.unit"
            class="mr-2"
          />
          {{ selectedProduct?.name }}
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

        <SalesAnalyticsCompact
          v-if="selectedProduct?.salesData?.yearly_sales?.length"
          :salesData="selectedProduct.salesData"
        />
      </div>
    </div>
  </div>

  <aside class="col-span-12 lg:col-span-3 space-y-3">
  </aside>

</section>
</template>
