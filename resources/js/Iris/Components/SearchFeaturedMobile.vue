<script setup lang="ts">
import { computed, inject } from 'vue'
import Skeleton from 'primevue/skeleton'
import Image from '@common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import { useLocaleStore } from '@/Stores/locale'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Image as ImgTS } from '@/types/Image'
import DiscountByType from '@/Components/Utils/Label/DiscountByType.vue'

// Shown while the search field is still empty: the items the shop merchandises, framed as a
// centred card so nobody mistakes them for the results of a query they have not typed yet

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
            rrp?: number | string | null
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
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = useLocaleStore()
const currency = layout?.iris?.currency

const products = computed(() => props.results?.products ?? [])

const chips = computed(() => [
    ...(props.results?.product_categories ?? []).map((item) => ({ ...item, key: `category-${item.id}` })),
    ...(props.results?.collections ?? []).map((item) => ({ ...item, key: `collection-${item.id}` })),
])

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
    <div class="h-full overflow-y-auto overscroll-contain px-4 py-6 flex">
        <div class="mx-auto h-fit w-full max-w-sm rounded-2xl border border-dashed border-[color-mix(in_srgb,var(--theme-color-0)_35%,transparent)] bg-[color-mix(in_srgb,var(--theme-color-0)_5%,white)] px-4 py-5">
            <p class="text-center text-lg font-semibold uppercase tracking-widest text-[var(--theme-color-0)]">
                {{ ctrans('Featured') }}
            </p>

            <template v-if="isLoading">
                <div class="mt-4 space-y-3">
                    <div v-for="i in 3" :key="i" class="flex items-center gap-3">
                        <Skeleton width="3rem" height="3rem" border-radius="0.75rem" />
                        <div class="flex-1">
                            <Skeleton width="80%" height="0.75rem" class="mb-2" />
                            <Skeleton width="35%" height="0.75rem" />
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div v-if="products.length" class="mt-4 divide-y divide-[color-mix(in_srgb,var(--theme-color-0)_12%,transparent)]">
                    <LinkIris
                        v-for="product in products"
                        :key="product.id"
                        :href="product.url"
                        class="flex items-center gap-3 min-w-0 py-2.5 first:pt-0 last:pb-0"
                        @success="() => model = false"
                    >
                        <div class="w-12 h-12 shrink-0 rounded-xl bg-white overflow-hidden flex items-center justify-center">
                            <Image v-if="product.image" :src="product.image" class="w-full h-full object-cover" />
                            <span v-else class="text-[10px] text-gray-300 font-bold uppercase">{{ product.code?.slice(0, 3) }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-slate-700 leading-snug line-clamp-2">{{ product.code }}</p>
                            <p class="text-sm text-slate-700 leading-snug line-clamp-2 opacity-70">{{ getProductName(product) }}</p>

                            <p v-if="formatRrp(product.rrp_per_unit, product.unit)" class="text-xxs text-gray-500 mt-0.5">
                                {{ ctrans('RRP') }}: {{ formatRrp(product.rrp_per_unit, product.unit) }}
                            </p>

                            <p v-if="formatPrice(product.price)" class="text-sm font-bold mt-0.5 text-[var(--theme-color-0)]">
                                <template v-if="product.discounted_price">
                                    <span class="mr-1.5 text-xs font-normal text-gray-400 line-through">{{ formatPrice(product.price) }}</span>
                                    <span class="text-[var(--theme-color-0)]">{{ formatPrice(product.discounted_price) }}</span>
                                </template>
                                <template v-else>{{ formatPrice(product.price) }}</template>
                                <span v-if="getPricePerUnit(product)" class="ml-1 text-xs font-normal opacity-70">({{ getPricePerUnit(product) }})</span>
                            </p>

                            <!-- Offers the customer can already claim on this product -->
                            <div v-if="product.product_offers_data?.number_offers" class="mt-1 flex flex-wrap items-center gap-1">
                                <span v-if="product.discounted_percentage" class="rounded-full bg-[var(--theme-color-0)] px-2 py-0.5 text-xxs font-semibold text-white">
                                    {{ product.discounted_percentage }} {{ ctrans('OFF') }}
                                </span>
                                <DiscountByType :offers_data="product.product_offers_data" template="products_triggers_label" />
                            </div>
                        </div>
                    </LinkIris>
                </div>

                <div v-if="chips.length" class="mt-4 flex flex-wrap justify-center gap-2">
                    <LinkIris
                        v-for="chip in chips"
                        :key="chip.key"
                        :href="chip.url"
                        class="max-w-full truncate rounded-full border border-[var(--theme-color-0)] text-[var(--theme-color-0)] text-xs px-3 py-1 active:bg-[color-mix(in_srgb,var(--theme-color-0)_15%,transparent)]"
                        @success="() => model = false"
                    >
                        {{ chip.name }}
                    </LinkIris>
                </div>
            </template>

            <p class="mt-5 text-center text-xs text-gray-400">
                {{ ctrans('Start typing to search the catalogue') }}
            </p>
        </div>
    </div>
</template>
