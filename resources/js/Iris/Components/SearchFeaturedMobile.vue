<script setup lang="ts">
import { computed, inject } from 'vue'
import Skeleton from 'primevue/skeleton'
import Image from '@common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import { useLocaleStore } from '@/Stores/locale'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Image as ImgTS } from '@/types/Image'
import DiscountByType from '@/Components/Utils/Label/DiscountByType.vue'
import MemberPriceLabel from '@/Iris/Components/Offer/MemberPriceLabel.vue'
import { getBestOffer } from '@/Composables/useOffers'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpinnerThird } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import LoadingOverlay2 from '@/Components/Utils/LoadingOverlay2.vue'

library.add(faSpinnerThird)

// Shown while the search field is still empty: the items the shop merchandises, framed as a
// centred card so nobody mistakes them for the results of a query they have not typed yet

const model = defineModel<boolean>('open')

interface FeaturedProduct {
    id: number
    code: string
    name: string
    image: ImgTS
    family_id?: number | null
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
}

const props = defineProps<{
    results: {
        products: FeaturedProduct[]
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

const isGoldRewardMember = computed(() => Boolean(layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr))

const getOffer = (product: FeaturedProduct): any => getBestOffer(product.product_offers_data) ?? undefined

const isIntervalOffer = (product: FeaturedProduct): boolean => getOffer(product)?.type === 'Category Quantity Ordered Order Interval'

// The member price is already earned once the family has been ordered past the offer trigger
const hasMemberPrice = (product: FeaturedProduct): boolean => {
    if (isGoldRewardMember.value) return true

    const trigger = getOffer(product)?.category_qty_trigger
    if (trigger == null || product.family_id == null) return false

    return Number(trigger) <= Number(layout?.family_quantity_ordered?.[product.family_id] ?? 0)
}


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

const getUnitPrice = (price: number | string | null | undefined, unit?: string | null): string | null => {
    const formatted = formatPrice(price)
    if (!formatted) return null
    return unit ? `${formatted}/${unit}` : formatted
}

const isOuter = (product: FeaturedProduct): boolean => (Number(product.units) || 1) !== 1
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
                        class="relative flex items-start gap-3 min-w-0 py-2.5 first:pt-0 last:pb-0"
                        @success="() => model = false"
                    >
                        <template #default="{ isLoading: isVisiting }">
                            <LoadingOverlay2 v-if="isVisiting" class="z-10 rounded"/>

                            <div class="w-12 h-12 shrink-0 rounded bg-white overflow-hidden flex items-center justify-center shadow">
                                <Image v-if="product.image" :src="product.image" class="w-full h-full object-cover" />
                                <span v-else class="text-[10px] text-gray-300 font-bold uppercase">{{ product.code?.slice(0, 3) }}</span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <!-- Section: code and label GR -->
                                <div class="flex justify-between items-center">
                                    <p class="text-sm font-bold text-slate-700 leading-snug line-clamp-2">
                                        {{ product.code }} <span v-if="formatRrp(product.rrp_per_unit, product.unit)" class="text-xxs font-semibold text-[#E87928]">
                                            {{ ctrans('RRP') }}: {{ formatRrp(product.rrp_per_unit, product.unit) }}
                                        </span>
                                    </p>
                                </div>

                                <!-- Section: product name -->
                                <p class="text-sm text-slate-700 leading-snug line-clamp-2 opacity-70 text-justify">{{ getProductName(product) }}</p>

                                <!-- Section: pricing information -->
                                <div v-if="formatPrice(product.price)" class="flex justify-between mt-1 border-t border-[color-mix(in_srgb,var(--theme-color-0)_15%,transparent)] border-dashed pt-1.5 text-xxs tabular-nums leading-none">
                                    <div class="flex flex-col">
                                        <span class="min-w-0 truncate text-right font-semibold text-slate-700">
                                            <template v-if="isOuter(product)">
                                                {{ formatPrice(product.price) }}
                                                <template v-if="getUnitPrice(product.price_per_unit, product.unit)">
                                                    ({{ getUnitPrice(product.price_per_unit, product.unit) }})
                                                </template>
                                            </template>
                                            <template v-else>{{ getUnitPrice(product.price, product.unit) }}</template>
                                        </span>
                                    </div>

                                    <div class="flex items-end gap-1 flex-col">
                                        <!-- Gold Reward: the member price this product already qualifies for -->
                                        <div v-if="product.discounted_price" class="flex items-center justify-between gap-2">
                                            <!-- Price: discount -->
                                            <span class="min-w-0 truncate text-right font-semibold text-[#E87928]">
                                                <template v-if="isOuter(product)">
                                                    {{ formatPrice(product.discounted_price) }}
                                                    <template v-if="getUnitPrice(product.discounted_price_per_unit, product.unit)">
                                                        ({{ getUnitPrice(product.discounted_price_per_unit, product.unit) }})
                                                    </template>
                                                </template>
                                                <template v-else>{{ getUnitPrice(product.discounted_price, product.unit) }}</template>
                                            </span>
                                        </div>
                                
                                        <MemberPriceLabel
                                            v-if="product.discounted_price && isIntervalOffer(product)"
                                            :offer="getOffer(product)"
                                            :active="hasMemberPrice(product)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </LinkIris>
                </div>

                <!-- Section: list of product categories -->
                <div v-if="chips.length" class="mt-4 flex flex-wrap justify-center gap-2">
                    <LinkIris
                        v-for="chip in chips"
                        :key="chip.key"
                        :href="chip.url"
                        class="max-w-full truncate rounded-full border border-[var(--theme-color-0)] text-[var(--theme-color-0)] text-xs px-3 py-1 active:bg-[color-mix(in_srgb,var(--theme-color-0)_15%,transparent)]"
                        @success="() => model = false"
                    >
                        <template #default="{ isLoading: isVisiting }">
                            <FontAwesomeIcon v-if="isVisiting" :icon="faSpinnerThird" spin class="mr-1" fixed-width aria-hidden="true" />
                            {{ chip.name }}
                        </template>
                    </LinkIris>
                </div>
            </template>

            <p class="mt-5 text-center text-xs text-gray-400">
                {{ ctrans('Start typing to search the catalogue') }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.offer :deep(.offer-max-discount) {
    @apply flex min-w-0 max-w-[6rem] items-center rounded-sm border border-red-900 bg-[#A80000] px-1 py-0.5 text-[10px] text-gray-100;
}

.offer :deep(.offer-label) {
    @apply flex min-w-0 max-w-full items-center gap-1;
}

.offer :deep(.label-text) {
    @apply min-w-0 truncate leading-none;
}

.discount :deep(.offer-trigger-label) {
    @apply rounded-md border border-b-4 border-[#E87928] bg-gray-50 px-2 py-1 text-xxs leading-3 text-[#E87928];
}
</style>
