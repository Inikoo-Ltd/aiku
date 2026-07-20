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

library.add(faTimes)

const model = defineModel<boolean>('open')

const props = defineProps<{
    results: {
        products: {
            id: number
            code: string
            name: string
            image: Record<string, any>
            price?: number | string | null
            url?: string
        }[]
        product_categories: {
            id: number
            code: string
            name: string
            type?: string
            image: Record<string, any>
            url?: string
        }[]
        collections: {
            id: number
            code: string
            name: string
            image: Record<string, any>
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

// First product is the spotlight, the rest fill the grid (9 items)
const bestProduct = computed(() => props.results?.products?.[0] ?? null)
const gridProducts = computed(() => props.results?.products?.slice(1, 10) ?? [])

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
</script>

<template>
    <div class="relative grid grid-cols-4 h-full min-h-0 w-full col-span-12">
        <!-- Close button -->
        <button
            type="button"
            class="absolute top-3 right-3 z-10 text-gray-400 hover:text-gray-600 transition"
            :aria-label="ctrans('Close')"
            @click="() => model = false"
        >
            <FontAwesomeIcon icon="fal fa-times" fixed-width />
        </button>

        <!-- Column 1: Categories & Collections -->
        <div class="col-span-1 bg-slate-50 flex flex-col min-h-0 p-5">
            <div class="flex-1 overflow-y-auto space-y-6">
                <template v-if="isLoading">
                    <Skeleton v-for="i in 6" :key="i" width="80%" height="0.875rem" />
                </template>
                <template v-else>
                    <!-- Section: Top categories -->
                    <div>
                        <p class="text-base font-semibold text-slate-600 mb-3">{{ ctrans('Top categories') }}</p>
                        <div v-if="results?.product_categories?.length" class="space-y-2">
                            <LinkIris
                                v-for="category in results.product_categories"
                                :key="category.id"
                                :href="category.url"
                                class="block text-sm text-slate-700 hover:text-slate-900 hover:underline cursor-pointer truncate"
                                @success="() => model = false"
                                v-html="highlightMatch(category.name)"
                            />
                        </div>
                        <p v-else class="text-sm text-gray-400">{{ ctrans('No categories found') }}</p>
                    </div>

                    <!-- Section: Collections -->
                    <div v-if="results?.collections?.length">
                        <p class="text-base font-semibold text-slate-600 mb-3">{{ ctrans('Collections') }}</p>
                        <div class="space-y-2">
                            <LinkIris
                                v-for="collection in results.collections"
                                :key="collection.id"
                                :href="collection.url"
                                class="block text-sm text-slate-700 hover:text-slate-900 hover:underline cursor-pointer truncate"
                                @success="() => model = false"
                                v-html="highlightMatch(collection.name)"
                            />
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Column 2: Best product -->
        <div class="col-span-1 border-r flex flex-col min-h-0 p-5">
            <p class="text-base font-semibold text-slate-600 mb-3">{{ ctrans('Best product') }}</p>
            <template v-if="isLoading">
                <Skeleton width="100%" height="10rem" class="mb-3" />
                <Skeleton width="70%" height="1rem" class="mx-auto mb-2" />
                <Skeleton width="40%" height="1rem" class="mx-auto" />
            </template>
            <LinkIris
                v-else-if="bestProduct"
                :href="bestProduct.url"
                class="group flex-1 flex flex-col items-center text-center cursor-pointer min-h-0"
                @success="() => model = false"
            >
                <div class="w-40 h-40 bg-gray-50 overflow-hidden flex items-center justify-center mb-4">
                    <Image v-if="bestProduct.image" :src="bestProduct.image" class="w-full h-full object-contain transition-transform duration-200" />
                    <span v-else class="text-sm text-gray-300 font-bold uppercase">{{ bestProduct.code?.slice(0, 3) }}</span>
                </div>
                <p class="text-sm font-semibold text-slate-800 leading-snug line-clamp-2" v-html="highlightMatch(bestProduct.name)" />
                <p v-if="formatPrice(bestProduct.price)" class="text-lg font-bold text-slate-900 mt-1">{{ formatPrice(bestProduct.price) }}</p>
                <span class="mt-auto inline-block bg-[#a58a6f] hover:bg-[#94795e] text-white text-xs font-semibold uppercase tracking-wider px-8 py-2.5 transition">
                    {{ ctrans('See more') }}
                </span>
            </LinkIris>
            <div v-else class="flex flex-1 items-center justify-center text-gray-400 text-sm">
                {{ ctrans('No products found') }}
            </div>
        </div>

        <!-- Column 3: Top products (3x3 grid) -->
        <div class="col-span-2 flex flex-col min-h-0 p-5">
            <p class="text-base font-semibold text-slate-600 mb-3">{{ ctrans('Top products') }}</p>
            <div class="flex-1 overflow-y-auto">
                <template v-if="isLoading">
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="i in 9" :key="i" class="flex items-center gap-2">
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
                        class="group flex items-center gap-2.5 cursor-pointer min-w-0"
                        @success="() => model = false"
                    >
                        <div class="w-14 h-14 bg-gray-50 overflow-hidden flex-shrink-0 flex items-center justify-center">
                            <Image v-if="product.image" :src="product.image" class="w-full h-full object-cover transition-transform duration-200" />
                            <span v-else class="text-[10px] text-gray-300 font-bold uppercase">{{ product.code?.slice(0, 3) }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-800 truncate leading-tight group-hover:underline" v-html="highlightMatch(product.name)" />
                            <p v-if="formatPrice(product.price)" class="text-xs font-bold text-slate-900 mt-0.5">{{ formatPrice(product.price) }}</p>
                        </div>
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
    background-color: #ffe600;
    color: inherit;
    font-weight: 700;
    border-radius: 2px;
    padding: 0 1px;
}
</style>
