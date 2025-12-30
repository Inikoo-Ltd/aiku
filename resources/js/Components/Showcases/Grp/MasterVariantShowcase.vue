<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, provide, ref, watch } from "vue"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import TradeUnitMasterProductSummary from "@/Components/Goods/TradeUnitMasterProductSummary.vue"
import ImagePrime from "primevue/image"
import SalesAnalyticsCompact from "@/Components/Product/SalesAnalyticsCompact.vue"

import { capitalize } from "@/Composables/capitalize"
import { useLayoutStore } from "@/Stores/layout"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@far"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"

library.add(faImage)

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
    slug: string
    unit?: string
    units?: string[]
    main_images?: { webp?: string }
    gpsr?: any
    properties?: any
    attachment_box?: any
    salesData?: any
}

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

const variants = computed(() => props.data?.master_variant?.data?.variants || [])
const products = computed(() => Object.values(props.data?.master_variant?.data?.products || {}))

const selectedVariants = ref<Record<string, string>>(
    Object.fromEntries(variants.value.map(v => [v.label, v.options[0] || ""]))
)

const leaderProduct = Object.values(products.value).find(p => p.is_leader);
if(leaderProduct) selectedVariants.value = Object.fromEntries(variants.value.map(v => [v.label, leaderProduct[v.label] ?? ""]));

const toggleVariant = (label: string, option: string) => {
    selectedVariants.value[label] = option
}

const isSelectionComplete = computed(() =>
    variants.value.every(v => selectedVariants.value[v.label])
)

const selectedVariantProduct = computed(() =>
    isSelectionComplete.value
        ? products.value.find(p =>
            variants.value.every(v => p[v.label] === selectedVariants.value[v.label])
        ) ?? null
        : null
)

const selectedProduct = computed(() =>
    selectedVariantProduct.value
        ? props.data.master_products.data.find(
            p => p.id === selectedVariantProduct.value.product.id
        ) ?? null
        : null
)
console.log(selectedProduct.value,props);
</script>

<template>
    <!-- MAIN GRID -->
    <section class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        
        <!-- LEFT : VARIANT + IMAGE -->
        <div class="bg-white p-4 rounded-xl shadow-sm space-y-5">

            <!-- VARIANT SELECTOR -->
            <div class="border rounded-lg p-4 space-y-4">
                <div v-for="variant in variants" :key="variant.label">
                    <p class="text-sm font-semibold text-gray-800 mb-2">
                        {{ variant.label }}
                    </p>

                    <div class="flex flex-wrap gap-2">
                        <Button v-for="option in variant.options" size="xs"
                            :key="`${selectedVariants[variant.label]}-${option}`"
                            :type="selectedVariants[variant.label] != option ? 'tertiary' : 'primary'"
                            @click="toggleVariant(variant.label, option)">
                            {{ option }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- PRODUCT IMAGE -->
            <div class="relative bg-white border rounded-xl shadow-sm overflow-hidden">
                <div class="aspect-square flex items-center justify-center bg-gray-50">
                    <ImagePrime v-if="selectedProduct?.main_images?.webp" :src="selectedProduct.main_images.webp"
                        :alt="selectedProduct.name" preview image-class="object-contain w-full h-full p-4" />

                    <div v-else class="flex flex-col items-center justify-center text-gray-400">
                        <FontAwesomeIcon icon="image" class="text-4xl mb-2" />
                        <span class="text-xs">{{ trans('No product image') }}</span>
                    </div>
                </div>

                <div v-if="selectedProduct?.unit"
                    class="absolute top-3 left-3 bg-white/90 text-xs px-2 py-1 rounded shadow">
                    {{ selectedProduct.unit }}
                </div>
            </div>
        </div>

        <!-- CENTER : PRODUCT DETAIL -->
        <div class="lg:col-span-2 pt-4">
            <div class="mb-3 grid grid-cols-2">
                <span class="text-xl font-medium pl-5 text-gray-500">
                    {{ trans('Product Name:') }}
                </span>
                <span class="text-xl font-medium pr-5 text-right">
                    {{ selectedProduct?.name ?? '-' }}
                </span>
            </div>

            <TradeUnitMasterProductSummary v-if="selectedProduct" :data="selectedProduct" :gpsr="selectedProduct.gpsr"
                :properties="selectedProduct.properties" :attachments="selectedProduct.attachment_box" />
        </div>

        <!-- RIGHT : SALES -->
        <div class="bg-white p-4 rounded-xl shadow-sm text-sm text-gray-600">
            <SalesAnalyticsCompact v-if="selectedProduct?.salesData" :salesData="selectedProduct.salesData" />
        </div>
    </section>
</template>
