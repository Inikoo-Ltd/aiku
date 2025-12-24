<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { computed, provide, ref } from "vue"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import ProductUnitLabel from "@/Components/Utils/Label/ProductUnitLabel.vue"
import ProductResource from "@/Components/Goods/ProductResource.vue"
import ImagePrime from "primevue/image"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { capitalize } from "@/Composables/capitalize"
import { useLayoutStore } from "@/Stores/layout"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExternalLink } from "@fal"
import { faImage } from "@far"

/* ================= ICONS ================= */
library.add(faExternalLink, faImage)

/* ================= LAYOUT ================= */
const layout = useLayoutStore()
provide("layout", layout)

/* ================= TYPES ================= */
type Variant = {
    label: string
    options: string[]
}

type VariantProductMap = {
    product: {
        id: number
    }
    [key: string]: string | { id: number }
}

type MasterProduct = {
    id: number
    name: string
    slug: string
    unit?: string
    units?: string[]
    main_images?: {
        webp?: string
    }
}

/* ================= PROPS ================= */
const props = defineProps<{
    title: string
    pageHead: any
    data: {
        data: {
            variants: Variant[]
            products: Record<string, VariantProductMap>
        }
    }
    master_products: {
        data: MasterProduct[]
    }
}>()

/* ================= DATA ================= */
const variants = computed(() => props.data?.data?.variants ?? [])
const products = computed(() => props.data?.data?.products ?? {})

/* ================= STATE ================= */
const selectedVariants = ref<Record<string, string>>(
    Object.fromEntries(
        variants.value.map(v => [v.label, v.options[0] ?? ""])
    )
)

/* ================= METHODS ================= */
const toggleVariant = (label: string, option: string) => {
    selectedVariants.value[label] = option
}

/* ================= COMPUTED ================= */
const isSelectionComplete = computed(() =>
    variants.value.every(v => !!selectedVariants.value[v.label])
)

const selectedVariantProduct = computed<VariantProductMap | null>(() => {
    if (!isSelectionComplete.value) return null

    return (
        Object.values(products.value).find(item =>
            variants.value.every(
                variant => item[variant.label] === selectedVariants.value[variant.label]
            )
        ) ?? null
    )
})

const selectedProduct = computed<MasterProduct | null>(() => {
    if (!selectedVariantProduct.value) return null

    return (
        props.master_products.data.find(
            p => p.id === selectedVariantProduct.value?.product?.id
        ) ?? null
    )
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <!-- PRODUCT HEADER -->
    <section class="bg-white shadow-sm px-4 py-3 mb-3">
        <h1 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
            <ProductUnitLabel
                v-if="selectedProduct?.units"
                :units="selectedProduct.units"
                :unit="selectedProduct.unit"
            />
            {{ selectedProduct?.name ?? "Select variant" }}
        </h1>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-4 gap-4">
        <!-- IMAGE -->
        <div class="bg-white p-4 rounded shadow-sm">
            <ImagePrime
                v-if="selectedProduct?.main_images?.webp"
                :src="selectedProduct.main_images.webp"
                :alt="selectedProduct.name"
                preview
            />
            <div
                v-else
                class="flex flex-col items-center justify-center gap-2 py-10 border-2 border-dashed rounded"
            >
                <FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
                <span class="text-sm text-gray-500">No image</span>
            </div>
        </div>

        <!-- VARIANTS -->
        <div class="lg:col-span-2 space-y-6">
            <div
                v-for="variant in variants"
                :key="variant.label"
            >
                <p class="text-sm font-medium text-gray-700 mb-2">
                    {{ variant.label }}
                </p>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="option in variant.options"
                        :key="`${selectedVariants[variant.label]}-${option}`"
                        :type="selectedVariants[variant.label] != option ? 'tertiary' : 'primary'"
                        @click="toggleVariant(variant.label, option)"
                    >
                        {{ option }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- RESOURCE -->
        <div class="bg-white p-4 rounded shadow-sm">
            <ProductResource
                v-if="selectedProduct"
                :data="selectedProduct"
            />
        </div>
    </section>
</template>
