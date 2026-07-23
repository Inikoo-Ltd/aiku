<script setup lang="ts">
import { computed, inject } from 'vue'
import Skeleton from 'primevue/skeleton'
import Image from '@common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import { useLocaleStore } from '@/Stores/locale'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Image as ImgTS } from '@/types/Image'
import Button from '@/Components/Elements/Buttons/Button.vue'

library.add(faTimes)

// SearchIrisCatalogue.php

const model = defineModel<boolean>('open')

const props = defineProps<{
    results: {
        products: {
            id: number
            code: string
            name: string
            image: ImgTS
            price?: number | string | null
            stock?: number | null
            units?: number | string | null
            unit?: string | null
            url?: string
        }[]
        product_categories: {
            id: number
            code: string
            name: string
            type?: string
            image: ImgTS
            url?: string
        }[]
        collections: {
            id: number
            code: string
            name: string
            image: ImgTS
            url?: string
        }[]
    } | null
    isLoading: boolean
    query: string
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = useLocaleStore()
const currency = layout?.iris?.currency

const formatPrice = (price?: number | string | null) => {
    if (price === null || price === undefined || price === '') return null
    return locale.currencyFormat(currency?.code, Number(price))
}

// First product is the spotlight, the rest fill the grid (10 items -> 11 total)
const bestProduct = computed(() => props.results?.products?.[0] ?? null)
const gridProducts = computed(() => props.results?.products?.slice(1, 11) ?? [])

// Categories and collections are capped at 10 each
const productCategories = computed(() => props.results?.product_categories?.slice(0, 10) ?? [])
const collections = computed(() => props.results?.collections?.slice(0, 10) ?? [])

// "View all results" jumps to the full search page for the current query
const viewAllUrl = computed(() => `/search?q=${encodeURIComponent(props.query ?? '')}`)

const escapeHtml = (value: string): string =>
    value.replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char] as string))

const escapeRegExp = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

// Wrap the part of the text that matches the current query with a highlight mark
const highlightMatch = (text?: string): string => {
    const safe = escapeHtml(text ?? '')
    const query = props.query?.trim()
    if (!query) {
        return safe
    }
    const pattern = new RegExp(`(${escapeRegExp(query)})`, 'ig')
    return safe.replace(pattern, '<mark class="lb-search-highlight">$1</mark>')
}


// Method: from 'Gemstone Obelisk Points approx 5cm - African Amethyst' to '[5x] Gemstone Obelisk Points approx 5cm - African Amethyst'
const getProductName = (product: { name: string; units?: number | string | null }): string => {
    const units = Number(product.units) || 1
    if (units === 1) {
        return product.name
    } else {
        return `[${units}x] ${product.name}`
    }
}

// Price sent from the backend is for the whole pack of `units`; show the per-unit price.
// e.g. 12 units for 4.8 -> 0.4 per unit
const getProductPrice = (product: { price?: number | string | null; unit?: string | null; units?: number | string | null }): string | null => {
    if (product.price === null || product.price === undefined || product.price === '') {
        return null
    }

    const units = Number(product.units) || 1
    const price = formatPrice(Number(product.price) / units)
    if (!price) return null
    if (product.unit) {
        return `${price}/${product.unit}`
    }
    return `${price}`
}
</script>

<template>
    <div class="relative grid grid-cols-4 h-full min-h-0 w-full col-span-12">
        <!-- Ribbon: accent bar across the top (matches Luigi's Box) -->
        <div class="absolute inset-x-0 top-0 h-[6px] z-20 bg-[var(--theme-color-0)]"></div>

        <!-- Close button -->
        <button
            type="button"
            class="absolute top-4 right-3 z-30 text-gray-400 hover:text-gray-600 transition"
            :aria-label="ctrans('Close')"
            @click="() => model = false"
        >
            <FontAwesomeIcon icon="fal fa-times" fixed-width />
        </button>

        <!-- Column 1: Categories & Collections -->
        <div class="col-span-1 bg-[#F3F7FA] border-r flex flex-col min-h-0 p-5">
            <div class="flex-1 overflow-y-auto space-y-6">
                <template v-if="isLoading">
                    <Skeleton v-for="i in 6" :key="i" width="80%" height="0.875rem" />
                </template>
                <template v-else>
                    <!-- Section: Top categories -->
                    <div>
                        <p class="text-[1.2rem] font-bold text-[var(--theme-color-0)] mb-3">{{ ctrans('Top categories') }}</p>
                        <div v-if="productCategories.length" class="space-y-2">
                            <LinkIris
                                v-for="category in productCategories"
                                :key="category.id"
                                :href="category.url"
                                class="block text-sm text-[#484848] hover:text-[var(--theme-color-0)] hover:underline cursor-pointer truncate transition-colors"
                                @success="() => model = false"
                                v-html="highlightMatch(category.name)"
                            />
                        </div>
                        <p v-else class="text-sm text-gray-400">{{ ctrans('No categories found') }}</p>
                    </div>

                    <!-- Section: Collections -->
                    <div v-if="collections.length">
                        <p class="text-[1.2rem] font-bold text-[var(--theme-color-0)] mb-3">{{ ctrans('Collections') }}</p>
                        <div class="space-y-2">
                            <LinkIris
                                v-for="collection in collections"
                                :key="collection.id"
                                :href="collection.url"
                                class="block text-sm text-[#484848] hover:text-[var(--theme-color-0)] hover:underline cursor-pointer truncate transition-colors"
                                @success="() => model = false"
                                v-html="highlightMatch(collection.name)"
                            />
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Column 2: Best match -->
        <div class="col-span-1 border-r flex flex-col min-h-0 p-5">
            <p class="text-[1.2rem] font-bold text-[var(--theme-color-0)] mb-3">{{ ctrans('Best Match') }}</p>
            <template v-if="isLoading">
                <Skeleton width="100%" height="10rem" class="mb-3" />
                <Skeleton width="70%" height="1rem" class="mx-auto mb-2" />
                <Skeleton width="40%" height="1rem" class="mx-auto" />
            </template>
            
            <LinkIris
                v-else-if="bestProduct"
                :href="bestProduct.url"
                class="p-5 group flex-1 flex flex-col items-center text-center cursor-pointer min-h-0 rounded transition-colors hover:bg-[color-mix(in_srgb,var(--theme-color-0)_8%,var(--theme-color-1))]"
                @success="() => model = false"
            >
                <div class="w-40 h-40 bg-gray-50 overflow-hidden flex items-center justify-center mb-4">
                    <Image v-if="bestProduct.image" :src="bestProduct.image" class="w-full h-full object-contain transition-transform duration-200" :class="{ 'grayscale opacity-60': bestProduct.stock === 0 }" />
                    <span v-else class="text-sm text-gray-300 font-bold uppercase">{{ bestProduct.code?.slice(0, 3) }}</span>
                </div>
                <p class="text-sm font-semibold text-slate-800 leading-snug line-clamp-2" v-html="highlightMatch(getProductName(bestProduct))" />
                <p v-if="getProductPrice(bestProduct)" class="xtext-lg font-bold text-[var(--theme-color-0)] mt-1">
                    <span class="">{{ formatPrice(Number(bestProduct.price)) }}</span> <span v-if="Number(bestProduct.units) !== 1" class="font-normal opacity-80">({{ getProductPrice(bestProduct) }})</span>
                </p>
                <span class="mt-auto inline-block bg-[var(--theme-color-0)] hover:bg-[color-mix(in_srgb,var(--theme-color-0)_75%,var(--theme-color-1))] text-[var(--theme-color-1)] text-xs font-semibold uppercase tracking-wider px-8 py-2.5 rounded-[5px] transition">
                    {{ ctrans('See more') }}
                </span>
            </LinkIris>
            <div v-else class="flex flex-1 items-center justify-center text-gray-400 text-sm">
                {{ ctrans('No products found') }}
            </div>
        </div>

        <!-- Column 3: Products (3x3 grid) -->
        <div class="col-span-2 flex flex-col min-h-0 p-5">
            <p class="text-[1.2rem] font-bold text-[var(--theme-color-0)] mb-3">{{ ctrans('Products') }}</p>
            <div class="flex-1 overflow-y-auto">
                <template v-if="isLoading">
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="i in 10" :key="i" class="flex items-center gap-2">
                            <Skeleton width="3rem" height="3rem" />
                            <div class="flex-1">
                                <Skeleton width="80%" height="0.75rem" class="mb-1" />
                                <Skeleton width="50%" height="0.75rem" />
                            </div>
                        </div>
                    </div>
                </template>
                
                <div v-else-if="gridProducts.length" class="grid grid-cols-2 gap-x-3 gap-y-4">
                    <LinkIris
                        v-for="product in gridProducts"
                        :key="product.id"
                        :href="product.url"
                        class="group flex items-center gap-2.5 cursor-pointer min-w-0 rounded-md p-1.5 transition-colors hover:bg-[color-mix(in_srgb,var(--theme-color-0)_10%,var(--theme-color-1))]"
                        @success="() => model = false"
                    >
                        <div class="w-14 h-14 bg-gray-50 overflow-hidden flex-shrink-0 flex items-center justify-center">
                            <Image v-if="product.image" :src="product.image" class="w-full h-full object-cover transition-transform duration-200" :class="{ 'grayscale opacity-60': product.stock === 0 }" />
                            <span v-else class="text-[10px] text-gray-300 font-bold uppercase">{{ product.code?.slice(0, 3) }}</span>
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 truncate leading-tight group-hover:underline" v-html="highlightMatch(getProductName(product))" />
                            <p v-if="getProductPrice(product)" class="text-sm font-bold mt-0.5 text-[var(--theme-color-0)]">
                                <span class="">{{ formatPrice(Number(product.price)) }}</span> <span v-if="Number(product.units) !== 1" class="font-normal opacity-80">({{ getProductPrice(product) }})</span>
                            </p>
                        </div>
                    </LinkIris>

                    <LinkIris
                        :href="viewAllUrl"
                        class="col-span-2"
                        @success="() => model = false"
                    >
                        <Button
                            type="tertiary"
                            label="View all results"
                            full
                        />
                    </LinkIris>
                </div>
                <div v-else class="flex h-32 items-center justify-center text-gray-400 text-sm">
                    {{ ctrans('No products found') }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
:deep(.lb-search-highlight) {
    background: color-mix(in srgb, var(--theme-color-0) 90%, transparent);
    color: var(--theme-color-1);
    font-weight: normal;
    border-radius: 2px;
    padding: 0 2px;
}
</style>
