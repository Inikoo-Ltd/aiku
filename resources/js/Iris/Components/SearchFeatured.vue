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
import DiscountByType from '@/Components/Utils/Label/DiscountByType.vue'

library.add(faTimes)

// Desktop counterpart of SearchFeaturedMobile: what the shop merchandises, shown while the
// search field is focused but still empty. The panel sizes to its content, since a handful
// of featured items would look abandoned in the full-height results layout.

const model = defineModel<boolean>('open')

const props = defineProps<{
    results: {
        products: {
            id: number
            code: string
            name: string
            image: ImgTS
            price?: number | string | null
            price_per_unit?: number | null
            rrp_per_unit?: number | null
            discounted_price?: number | null
            discounted_price_per_unit?: number | null
            discounted_percentage?: string | null
            product_offers_data?: any
            stock?: number | null
            units?: number | string | null
            unit?: string | null
            url?: string
        }[]
        product_categories: {
            id: number
            code: string
            name: string
            url?: string
        }[]
        collections: {
            id: number
            code: string
            name: string
            url?: string
        }[]
    } | null
    isLoading: boolean
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = useLocaleStore()
const currency = layout?.iris?.currency

const products = computed(() => props.results?.products ?? [])
const productCategories = computed(() => props.results?.product_categories ?? [])
const collections = computed(() => props.results?.collections ?? [])

const getProductName = (product: { name: string; units?: number | string | null }): string => {
    const units = Number(product.units) || 1
    return units === 1 ? product.name : `[${units}x] ${product.name}`
}

const formatPrice = (price?: number | string | null): string | null => {
    if (price === null || price === undefined || price === '') return null
    return locale.currencyFormat(currency?.code, Number(price))
}

const formatRrp = (rrpPerUnit?: number | null, unit?: string | null): string | null => {
    if (!rrpPerUnit) return null
    const rrp = String(locale.currencyFormatRrp(currency?.code, rrpPerUnit))
    return unit ? `${rrp}/${unit}` : rrp
}

// Only worth showing on outers: for a single unit it repeats the price above
const getPricePerUnit = (product: { price_per_unit?: number | null; discounted_price_per_unit?: number | null; unit?: string | null; units?: number | string | null }): string | null => {
    if ((Number(product.units) || 1) === 1) return null
    const price = formatPrice(product.discounted_price_per_unit ?? product.price_per_unit)
    if (!price) return null
    return product.unit ? `${price}/${product.unit}` : price
}
</script>

<template>
    <div class="relative w-full">
        <!-- Ribbon: accent bar across the top, same as the results panel -->
        <div class="absolute inset-x-0 top-0 h-[6px] z-20 bg-[var(--theme-color-0)]"></div>

        <button
            type="button"
            class="absolute top-4 right-3 z-30 text-gray-400 hover:text-gray-600 transition"
            :aria-label="ctrans('Close')"
            @click="() => model = false"
        >
            <FontAwesomeIcon icon="fal fa-times" fixed-width />
        </button>

        <div class="px-6 pt-7 pb-5">
            <p class="text-[1.2rem] font-bold text-[var(--theme-color-0)] mb-4">{{ ctrans('Featured') }}</p>

            <template v-if="isLoading">
                <div class="grid grid-cols-4 gap-x-4 gap-y-4">
                    <div v-for="i in 4" :key="i" class="flex items-center gap-2.5">
                        <Skeleton width="3.5rem" height="3.5rem" />
                        <div class="flex-1">
                            <Skeleton width="80%" height="0.75rem" class="mb-1" />
                            <Skeleton width="50%" height="0.75rem" />
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div v-if="products.length" class="grid grid-cols-4 gap-x-4 gap-y-4">
                    <LinkIris
                        v-for="product in products"
                        :key="product.id"
                        :href="product.url"
                        class="group flex items-start gap-2.5 cursor-pointer min-w-0 rounded-md p-1.5 transition-colors hover:bg-[color-mix(in_srgb,var(--theme-color-0)_10%,var(--theme-color-1))]"
                        @success="() => model = false"
                    >
                        <div class="w-14 h-14 bg-gray-50 overflow-hidden flex-shrink-0 flex items-center justify-center">
                            <Image v-if="product.image" :src="product.image" class="w-full h-full object-cover" :class="{ 'grayscale opacity-60': product.stock === 0 }" />
                            <span v-else class="text-[10px] text-gray-300 font-bold uppercase">{{ product.code?.slice(0, 3) }}</span>
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 leading-tight line-clamp-2 group-hover:underline">{{ getProductName(product) }}</p>

                            <p v-if="formatRrp(product.rrp_per_unit, product.unit)" class="text-xxs text-gray-500 mt-0.5">
                                {{ ctrans('RRP') }}: {{ formatRrp(product.rrp_per_unit, product.unit) }}
                            </p>

                            <p v-if="formatPrice(product.price)" class="text-sm font-bold mt-0.5 text-[var(--theme-color-0)]">
                                <template v-if="product.discounted_price">
                                    <span class="mr-1.5 text-xs font-normal text-gray-400 line-through">{{ formatPrice(product.price) }}</span>
                                    <span>{{ formatPrice(product.discounted_price) }}</span>
                                </template>
                                <template v-else>{{ formatPrice(product.price) }}</template>
                                <span v-if="getPricePerUnit(product)" class="ml-1 text-xs font-normal opacity-80">({{ getPricePerUnit(product) }})</span>
                            </p>

                            <!-- Offers the customer can already claim on this product -->
                            <div v-if="product.product_offers_data?.number_offers" class="mt-1 flex flex-wrap items-center gap-1">
                                <span v-if="product.discounted_percentage" class="rounded-full bg-[var(--theme-color-0)] px-2 py-0.5 text-xxs font-semibold text-[var(--theme-color-1)]">
                                    {{ product.discounted_percentage }} {{ ctrans('OFF') }}
                                </span>
                                <DiscountByType :offers_data="product.product_offers_data" template="products_triggers_label" />
                            </div>
                        </div>
                    </LinkIris>
                </div>

                <div v-if="productCategories.length || collections.length" class="mt-5 pt-4 border-t border-gray-200 flex flex-wrap items-center gap-2">
                    <span class="text-sm font-semibold text-[#484848] mr-1">{{ ctrans('Browse') }}:</span>
                    <LinkIris
                        v-for="category in productCategories"
                        :key="`category-${category.id}`"
                        :href="category.url"
                        class="max-w-full truncate rounded-full border border-[var(--theme-color-0)] text-[var(--theme-color-0)] text-xs px-3 py-1 transition-colors hover:bg-[color-mix(in_srgb,var(--theme-color-0)_12%,transparent)]"
                        @success="() => model = false"
                    >
                        {{ category.name }}
                    </LinkIris>
                    <LinkIris
                        v-for="collection in collections"
                        :key="`collection-${collection.id}`"
                        :href="collection.url"
                        class="max-w-full truncate rounded-full border border-[var(--theme-color-0)] text-[var(--theme-color-0)] text-xs px-3 py-1 transition-colors hover:bg-[color-mix(in_srgb,var(--theme-color-0)_12%,transparent)]"
                        @success="() => model = false"
                    >
                        {{ collection.name }}
                    </LinkIris>
                </div>
            </template>

            <p class="mt-5 text-xs text-gray-400">
                {{ ctrans('Start typing to search the catalogue') }}
            </p>
        </div>
    </div>
</template>
