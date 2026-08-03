<script setup lang="ts">
import { computed, inject } from 'vue'
import Skeleton from 'primevue/skeleton'
import Image from '@common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import { useLocaleStore } from '@/Stores/locale'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Image as ImgTS } from '@/types/Image'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { searchRoute } from '@/Iris/Composables/useSearchRoute'

// Mobile twin of SearchResultCatalogue: one thumb-friendly scrolling list instead of
// desktop's three columns. Chips for categories/collections, large tappable product rows.

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
    searchLogUlid?: string | null
}>()

// keepalive fetch survives the navigation the click triggers
const recordClick = (url?: string | null) => {
    if (!props.searchLogUlid || !url) return
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')
    fetch(route(searchRoute('click')), {
        method: 'POST',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': token },
        body: JSON.stringify({ ulid: props.searchLogUlid, url }),
    }).catch(() => {})
}

const layout = inject('layout', retinaLayoutStructure)
const locale = useLocaleStore()
const currency = layout?.iris?.currency

const formatPrice = (price?: number | string | null) => {
    if (price === null || price === undefined || price === '') return null
    return locale.currencyFormat(currency?.code, Number(price))
}

const products = computed(() => props.results?.products?.slice(0, 11) ?? [])

// Categories and collections share one chip strip, capped to keep it scannable
const chips = computed(() => [
    ...(props.results?.product_categories ?? []).slice(0, 6).map((item) => ({ ...item, key: `category-${item.id}` })),
    ...(props.results?.collections ?? []).slice(0, 4).map((item) => ({ ...item, key: `collection-${item.id}` })),
])

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

const highlightMatch = (text?: string): string => {
    const safe = escapeHtml(text ?? '')
    const query = props.query?.trim()
    if (!query) {
        return safe
    }
    const pattern = new RegExp(`(${escapeRegExp(query)})`, 'ig')
    return safe.replace(pattern, '<mark class="lb-search-highlight">$1</mark>')
}

const getProductName = (product: { name: string; units?: number | string | null }): string => {
    const units = Number(product.units) || 1
    if (units === 1) {
        return product.name
    } else {
        return `[${units}x] ${product.name}`
    }
}

// Price sent from the backend is for the whole pack of `units`; show the per-unit price too
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
    <div class="h-full min-h-0 w-full flex flex-col overflow-y-auto overscroll-contain">
        <template v-if="isLoading">
            <div class="shrink-0 flex gap-2 px-4 pt-4 pb-2 overflow-hidden">
                <Skeleton v-for="i in 4" :key="i" width="6rem" height="2rem" border-radius="9999px" class="shrink-0" />
            </div>
            <div class="px-4 py-2 space-y-4">
                <div v-for="i in 6" :key="i" class="flex items-center gap-3">
                    <Skeleton width="3.5rem" height="3.5rem" />
                    <div class="flex-1">
                        <Skeleton width="85%" height="0.875rem" class="mb-2" />
                        <Skeleton width="40%" height="0.875rem" />
                    </div>
                </div>
            </div>
        </template>

        <template v-else>
            <!-- Categories & collections as a horizontally scrollable chip strip -->
            <div v-if="chips.length" class="shrink-0 flex gap-2 px-4 pt-4 pb-2 overflow-x-auto no-scrollbar">
                <LinkIris
                    v-for="chip in chips"
                    :key="chip.key"
                    :href="chip.url"
                    class="shrink-0 max-w-[60vw] truncate rounded-full border border-[var(--theme-color-0)] text-[var(--theme-color-0)] text-sm px-4 py-1.5 active:bg-[color-mix(in_srgb,var(--theme-color-0)_15%,transparent)]"
                    @click="() => recordClick(chip.url)"
                    @success="() => model = false"
                    v-html="highlightMatch(chip.name)"
                />
            </div>

            <!-- Products: large tappable rows, best match leads -->
            <div v-if="products.length" class="px-2 py-1">
                <LinkIris
                    v-for="(product, index) in products"
                    :key="product.id"
                    :href="product.url"
                    class="group flex items-center gap-3 min-w-0 rounded-md px-2 py-2.5 active:bg-[color-mix(in_srgb,var(--theme-color-0)_10%,var(--theme-color-1))]"
                    @click="() => recordClick(product.url)"
                    @success="() => model = false"
                >
                    <div
                        class="bg-gray-50 overflow-hidden flex-shrink-0 flex items-center justify-center"
                        :class="index === 0 ? 'w-20 h-20' : 'w-14 h-14'"
                    >
                        <Image v-if="product.image" :src="product.image" class="w-full h-full object-cover" :class="{ 'grayscale opacity-60': product.stock === 0 }" />
                        <span v-else class="text-[10px] text-gray-300 font-bold uppercase">{{ product.code?.slice(0, 3) }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p
                            class="text-slate-800 leading-snug line-clamp-2"
                            :class="index === 0 ? 'text-base font-semibold' : 'text-sm font-medium'"
                            v-html="highlightMatch(getProductName(product))"
                        />
                        <p v-if="getProductPrice(product)" class="text-sm font-bold mt-0.5 text-[var(--theme-color-0)]">
                            <span>{{ formatPrice(Number(product.price)) }}</span> <span v-if="Number(product.units) !== 1" class="font-normal opacity-80">({{ getProductPrice(product) }})</span>
                        </p>
                    </div>
                </LinkIris>

                <LinkIris
                    :href="viewAllUrl"
                    class="block px-2 py-3"
                    @success="() => model = false"
                >
                    <Button
                        type="tertiary"
                        label="View all results"
                        full
                    />
                </LinkIris>
            </div>
            <div v-else class="flex-1 flex items-center justify-center text-gray-400 text-sm py-16">
                {{ ctrans('No products found') }}
            </div>
        </template>
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

.no-scrollbar {
    scrollbar-width: none;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
