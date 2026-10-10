<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'
import { ctrans } from '@/Composables/useTrans'
import { inject, onMounted, ref } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import type { Swiper as SwiperInstance } from 'swiper'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faChevronRight, faSparkles } from '@fas'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Image from '@common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'

interface RecommendedProduct {
    id: number
    code: string
    name: string
    stock: number
    price: number | string
    unit?: string | null
    units?: number | string | null
    url?: string | null
    web_images?: Record<string, any> | null
}

const props = defineProps<{
    listLoadingProducts?: Record<string, string>
}>()

const emit = defineEmits<{
    'add-to-basket': [productId: string, productCode: string, product: RecommendedProduct]
}>()

const screenType: string = inject('screenType', 'desktop')
const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

const handleProductClick = (product: RecommendedProduct) => {
    emit('add-to-basket', String(product.id), product.code, product)
}

const isProductLoading = (productId: number) => {
    return props.listLoadingProducts?.[`recommender-${productId}`] === 'loading'
}

const skeletonSize = 7
const listProducts = ref<RecommendedProduct[]>([])
const isLoadingFetch = ref(false)

const fetchRecommendations = async () => {
    try {
        isLoadingFetch.value = true

        const { data } = await axios.get(route('retina.json.basket_recommendations.index'))

        listProducts.value = data?.data ?? []
    } catch (error: any) {
        console.error('Error on fetching basket recommendations:', error)
    } finally {
        isLoadingFetch.value = false
    }
}

const productImage = (product: RecommendedProduct) => {
    return product.web_images?.main?.gallery ?? product.web_images?.all?.[0]?.gallery ?? null
}

const pricePerUnit = (product: RecommendedProduct) => {
    const units = Number(product.units ?? 1)
    if (!units) return null
    return locale.currencyFormat(layout.iris?.currency?.code, Number(product.price) / units)
}

const swiperInstance = ref<SwiperInstance | null>(null)
const isAtBeginning = ref(true)
const isAtEnd = ref(false)

const onSwiperReady = (swiper: SwiperInstance) => {
    swiperInstance.value = swiper
    syncNavigationState(swiper)
}

const syncNavigationState = (swiper: SwiperInstance) => {
    isAtBeginning.value = swiper.isBeginning
    isAtEnd.value = swiper.isEnd
}

const swiperBreakpoints = {
    0: { slidesPerView: 2.2, spaceBetween: 10 },
    640: { slidesPerView: 3.2, spaceBetween: 12 },
    1024: { slidesPerView: 4.2, spaceBetween: 16 },
}

onMounted(() => {
    fetchRecommendations()
})
</script>

<template>
    <section id="basket-recommendations-internal" class="rounded-2xl border border-gray-200 bg-gradient-to-b from-gray-50 to-white p-4 md:p-6 shadow-sm">
        <div v-if="layout.app.environment === 'local'" class="mb-3 w-full rounded bg-yellow-500 py-1 text-center text-xs">
            Internal recommendations
        </div>

        <div class="mb-4 flex items-end justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white">
                    <FontAwesomeIcon :icon="faSparkles" class="text-base" fixed-width aria-hidden="true" />
                </span>
                <div>
                    <h2 class="text-lg md:text-2xl font-bold leading-tight text-gray-900">{{ ctrans('You might also like') }}</h2>
                    <p class="text-xs md:text-sm text-gray-500">{{ ctrans('Handpicked based on the products in your basket') }}</p>
                </div>
            </div>

            <div v-if="listProducts.length" class="hidden sm:flex gap-2">
                <button
                    type="button"
                    @click="swiperInstance?.slidePrev()"
                    :disabled="isAtBeginning"
                    :aria-label="ctrans('Previous')"
                    class="flex size-9 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-700 shadow-sm transition hover:border-gray-900 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40">
                    <FontAwesomeIcon :icon="faChevronLeft" class="text-sm" fixed-width aria-hidden="true" />
                </button>
                <button
                    type="button"
                    @click="swiperInstance?.slideNext()"
                    :disabled="isAtEnd"
                    :aria-label="ctrans('Next')"
                    class="flex size-9 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-700 shadow-sm transition hover:border-gray-900 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40">
                    <FontAwesomeIcon :icon="faChevronRight" class="text-sm" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>

        <Swiper
            v-if="isLoadingFetch || listProducts.length"
            :breakpoints="swiperBreakpoints"
            :loop="false"
            :autoplay="false"
            class="w-full !pb-1"
            @swiper="onSwiperReady"
            @slide-change="syncNavigationState"
            @reach-beginning="syncNavigationState"
            @reach-end="syncNavigationState"
        >
            <template v-if="!listProducts.length && isLoadingFetch">
                <SwiperSlide v-for="n in skeletonSize" :key="n" class="!h-auto">
                    <div class="flex h-full animate-pulse flex-col overflow-hidden rounded-xl border border-gray-200 bg-white">
                        <div class="aspect-square w-full bg-gray-200"></div>
                        <div class="flex flex-1 flex-col gap-2 p-3">
                            <div class="h-2 w-1/3 rounded bg-gray-200"></div>
                            <div class="h-3 w-full rounded bg-gray-200"></div>
                            <div class="h-3 w-3/4 rounded bg-gray-200"></div>
                            <div class="mt-2 h-4 w-2/5 rounded bg-gray-200"></div>
                            <div class="mt-auto h-8 w-full rounded bg-gray-200"></div>
                        </div>
                    </div>
                </SwiperSlide>
            </template>

            <template v-else-if="listProducts.length">
                <SwiperSlide v-for="product in listProducts" :key="product.id" class="!h-auto">
                    <div
                        class="group flex h-full cursor-grab flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition duration-200"
                        :class="Number(product.stock) > 0 ? 'hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-md' : 'opacity-75'"
                    >
                        <component
                            :is="product.url ? LinkIris : 'div'"
                            :href="product.url"
                            class="relative block aspect-square w-full overflow-hidden bg-gray-50 p-3">
                            <Image
                                v-if="productImage(product)"
                                :src="productImage(product)"
                                :alt="product.name"
                                class="h-full w-full transition-transform duration-300 group-hover:scale-105"
                                :style="{ objectFit: 'contain', objectPosition: 'center' }" />
                            <div v-else class="flex h-full w-full items-center justify-center text-2xl font-bold uppercase text-gray-300">
                                {{ product.code?.slice(0, 3) }}
                            </div>

                            <span
                                v-if="Number(product.stock) <= 0"
                                class="absolute right-2 top-2 rounded-full bg-red-100 px-2 py-0.5 text-xxs font-semibold text-red-600">
                                {{ ctrans('Out of Stock') }}
                            </span>
                        </component>

                        <div class="flex flex-1 flex-col p-3">
                            <div class="truncate text-xxs uppercase tracking-wide text-gray-400">{{ product.code }}</div>

                            <component
                                :is="product.url ? LinkIris : 'div'"
                                :href="product.url"
                                :title="product.name"
                                class="mt-0.5 line-clamp-2 min-h-[2.5rem] text-xs md:text-sm font-medium leading-5 text-gray-800"
                                :class="product.url ? 'hover:underline' : ''"
                            >
                                <span v-if="Number(product.units) > 1">{{ product.units }}x</span> {{ product.name }}
                            </component>

                            <div class="mt-2 min-h-[2.25rem]">
                                <div class="text-sm md:text-base font-bold text-gray-900">
                                    {{ locale.currencyFormat(layout.iris?.currency?.code, Number(product.price)) }}<span v-if="Number(product.units ?? 1) === 1 && product.unit" class="text-xs font-normal text-gray-500">/{{ product.unit }}</span>
                                </div>
                                <div v-if="Number(product.units ?? 1) !== 1 && product.unit" class="text-xxs md:text-xs text-gray-500">
                                    {{ pricePerUnit(product) }}/{{ product.unit }}
                                </div>
                            </div>

                            <div class="mt-auto pt-3">
                                <Button
                                    v-if="Number(product.stock) > 0"
                                    @click="handleProductClick(product)"
                                    :disabled="isProductLoading(product.id)"
                                    :loading="isProductLoading(product.id)"
                                    size="sm"
                                    full
                                    icon="fas fa-cart-plus"
                                >
                                    <template #label>
                                        <span class="text-xxs md:text-sm">{{ isProductLoading(product.id) ? ctrans('Adding...') : ctrans('Add to Basket') }}</span>
                                    </template>
                                </Button>

                                <Button
                                    v-else
                                    disabled
                                    :label="ctrans('Out of Stock')"
                                    type="tertiary"
                                    :size="screenType === 'mobile' ? 'sm' : 'md'"
                                    full
                                />
                            </div>
                        </div>
                    </div>
                </SwiperSlide>
            </template>
        </Swiper>

        <div v-else class="py-8 text-center text-sm text-gray-500">
            {{ ctrans('No recommendations available') }}
        </div>
    </section>
</template>
