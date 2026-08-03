<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { inject, onBeforeUnmount, onMounted, ref } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/pagination'
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

// The endpoint recommends against the products of the logged in customer's basket
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

// Section: responsive Slides per view
const slidesPerView = ref(3.3)
const updateSlidesPerView = () => {
    slidesPerView.value = window.innerWidth < 640 ? 2.3 : 3.3
}

onMounted(() => {
    fetchRecommendations()
    updateSlidesPerView()
    window.addEventListener('resize', updateSlidesPerView)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateSlidesPerView)
})
</script>

<template>
    <div class="md:py-4" id="basket-recommendations-internal">
        <div v-if="layout.app.environment === 'local'" class="bg-yellow-500 w-full text-center py-1 rounded mb-2">
            Internal recommendations
        </div>

        <Swiper
            :slides-per-view="Math.min((listProducts.length || (isLoadingFetch ? skeletonSize : 0)), slidesPerView)"
            :loop="false"
            :autoplay="false"
            :pagination="{ clickable: true }"
            class="w-full"
            spaceBetween="12"
            autoHeight
        >
            <template v-if="!listProducts.length && isLoadingFetch">
                <SwiperSlide
                    v-for="n in skeletonSize"
                    :key="n"
                    class="w-full cursor-grab relative px-2 md:px-4 py-3 rounded !flex !flex-col !justify-between gap-y-4 min-h-full animate-pulse"
                >
                    <div class="flex flex-col md:flex-row gap-x-2">
                        <div class="h-fit mx-auto md:mx-0 w-full max-w-[50px] md:max-w-[120px] rounded aspect-[4/4] bg-gray-200"></div>
                        <div class="flex-1 space-y-3 mt-2 md:mt-0">
                            <div class="h-3 bg-gray-200 rounded w-full"></div>
                            <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-2 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-4 bg-gray-200 rounded w-2/5"></div>
                            <div class="h-6 bg-gray-200 rounded w-full"></div>
                            <div class="h-9 bg-gray-200 rounded w-2/3"></div>
                        </div>
                    </div>
                </SwiperSlide>
            </template>

            <template v-else-if="listProducts.length">
                <SwiperSlide v-for="product in listProducts"
                    :key="product.id"
                    class="w-full cursor-grab relative px-2 md:px-4 py-3 rounded !flex !flex-col !justify-between gap-y-4 min-h-full"
                    :class="Number(product.stock) > 0 ? 'hover:bg-gray-500/10' : 'opacity-75'"
                >
                    <div class="flex flex-col md:flex-row gap-x-2">
                        <!-- Product Image - Always a link -->
                        <component :is="product.url ? LinkIris : 'div'"
                            :href="product.url"
                            class="mx-auto md:mx-0 w-full max-w-[50px] md:max-w-[120px] block rounded aspect-[4/4] overflow-hidden">
                            <Image v-if="productImage(product)" :src="productImage(product)" :alt="product.name"
                                class="w-full h-full" :style="{ objectFit: 'contain', objectPosition: 'center' }" />
                            <div v-else class="w-full h-full flex items-center justify-center text-gray-400 font-bold uppercase">
                                {{ product.code?.slice(0, 3) }}
                            </div>
                        </component>

                        <div>
                            <!-- Title - Always a link -->
                            <component
                                :is="product.url ? LinkIris : 'div'"
                                :href="product.url"
                                class="xfont-bold text-xs md:text-sm !mt-2 md:mt-2 md:mb-1 text-justify line-clamp-3 overflow-hidden min-h-[3rem] md:min-h-[3.75rem]"
                                :class="product.url ? 'hover:underline' : ''"
                            >
                                {{ product.name }}
                            </component>

                            <!-- SKU -->
                            <div class="flex justify-between text-xxs md:text-xs opacity-70 mb-1">
                                <span>{{ product.code }}</span>
                            </div>

                            <!-- Prices -->
                            <div class="xflex justify-between text-xs md:text-sm">
                                <span class="font-bold">
                                    {{ locale.currencyFormat(layout.iris?.currency?.code, Number(product.price)) }}<span v-if="Number(product.units ?? 1) === 1 && product.unit">/{{ product.unit }}</span>
                                </span>
                                <span v-if="Number(product.units ?? 1) !== 1 && product.unit" class="ml-1">({{ pricePerUnit(product) }}/{{ product.unit }})</span>
                            </div>

                            <!-- Section: Add to Basket Button -->
                            <div class="mt-2 w-full md:w-fit">
                                <Button v-if="Number(product.stock) > 0" @click="handleProductClick(product)"
                                    :disabled="isProductLoading(product.id)"
                                    :loading="isProductLoading(product.id)"
                                    size="sm"
                                    full
                                    icon="fas fa-cart-plus"
                                >
                                    <template #label>
                                        <span class="text-xxs md:text-sm">{{ isProductLoading(product.id) ? trans('Adding...') : trans('Add to Basket') }}</span>
                                    </template>
                                </Button>

                                <Button v-else
                                    disabled
                                    :label="trans('Out of Stock')"
                                    type="tertiary"
                                    :size="screenType === 'mobile' ? 'sm' : 'md'"
                                    class="w-full justify-center"
                                />
                            </div>
                        </div>
                    </div>
                </SwiperSlide>
            </template>
        </Swiper>
    </div>
</template>
