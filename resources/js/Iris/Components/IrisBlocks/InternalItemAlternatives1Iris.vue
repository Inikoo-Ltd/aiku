<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"

import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from "laravel-vue-i18n"
import { ProductHit } from "@/types/Luigi/LuigiTypes"
import RecommendationSlideLastSeen from "@/Components/Iris/Recommendations/RecommendationSlideLastSeen.vue"

library.add(faChevronLeft, faChevronRight)

const props = defineProps<{
    fieldValue: {
        id?: string
        product?: {
            id?: number
        }
        settings?: {
            per_row?: {
                mobile?: number
                tablet?: number
                desktop?: number
            }
        }
        container?: {
            properties?: any
        }
    }
    webpageData?: any
    blockData?: Object,
    screenType: 'mobile' | 'tablet' | 'desktop'
    indexBlock?: number
}>()


const slidesPerView = computed(() => {
    const perRow = props.fieldValue?.settings?.per_row ?? {}
    return {
        desktop: perRow.desktop ?? 5,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }[props.screenType] ?? 5
})

const layout = inject('layout', retinaLayoutStructure)

const listProducts = ref<ProductHit[]>([])
const isLoadingFetch = ref(false)
const isFetched = ref(false)

const listLoadingProducts = ref<Record<string, string>>({})
const isProductLoading = (productId: string) => {
    return listLoadingProducts.value?.[`recommender-${productId}`] === 'loading'
}

const fetchProductAlternatives = async () => {
    const productId = props.fieldValue?.product?.id

    if (!productId) {
        isFetched.value = true
        return
    }

    try {
        isLoadingFetch.value = true

        const response = await axios.get(
            route('iris.json.product.alternatives', { product: productId })
        )
        
        listProducts.value = response.data.data

        console.log(`LIA Internal (${response.data.data?.length}): `, response.data.data)
        
    } catch (error: any) {
        console.error('Error on fetching product alternatives:', error)
    } finally {
        isFetched.value = true
        isLoadingFetch.value = false
    }
}

onMounted(() => {
    fetchProductAlternatives()
})
</script>

<template>
    <div data-block-type="luigi-item-alternatives-1-iris" class="w-full pb-6 px-4" :id="fieldValue?.id ? fieldValue?.id  : 'luigi-item-alternatives-1-iris'"  component="luigi-item-alternatives-1-iris"
    :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">

        <!-- Title -->
        <div v-if="!isFetched || (isFetched && listProducts?.length)" class="px-3 py-6 pb-2">
            <div class="text-2xl md:text-3xl font-semibold">
                <div>
                    <p style="text-align: center">{{ trans("You may also like") }}<span v-if="layout.app.environment === 'local'" class="ml-2 bg-red-500">(Internal)</span></p>
                </div>
            </div>
        </div>

        <div v-if="isLoadingFetch" class="py-4 px-3 md:px-12 grid gap-x-3" :style="{ gridTemplateColumns: `repeat(${slidesPerView ? slidesPerView : 4}, minmax(0, 1fr))` }">
            <div v-for="xx in (slidesPerView ? slidesPerView : 4)" :key="xx" class="flex flex-col md:p-3 rounded bg-white">
                <div class="mb-3 flex justify-center">
                    <div class="skeleton w-full max-w-[220px] aspect-square rounded"></div>
                </div>
                <div class="skeleton mb-1 min-h-[3.5em] w-full rounded"></div>
                <div class="flex justify-between">
                    <div class="skeleton h-4 w-1/3 rounded"></div>
                    <div class="skeleton h-4 w-1/4 rounded"></div>
                </div>
            </div>
        </div>

        <template v-else-if="isFetched && listProducts?.length">
            <div class="py-4 px-3 md:px-12 swiper-container">
                <Swiper :slides-per-view="slidesPerView ? slidesPerView : 4"
                    :pagination="{ clickable: true }"
                    class="w-full"
                    spaceBetween="12"
                    autoHeight
                >
                    <SwiperSlide
                        v-for="(product, index) in listProducts"
                        :key="index"
                        class="w-full cursor-grab relative !grid  min-h-full"
                    >
                        <RecommendationSlideLastSeen
                            :product
                            :isProductLoading
                        />
                    </SwiperSlide>
                </Swiper>
            </div>
        </template>
    </div>
</template>

<style scoped>
.swiper-nav-button {
    @apply absolute top-1/2 transform -translate-y-1/2 z-10 bg-white border border-gray-300 rounded-full shadow-md p-2 hover:bg-gray-100 transition-all duration-300;
}

.swiper-nav-button svg {
    @apply text-gray-700 w-4 h-4;
}

:deep(.swiper-container .swiper-wrapper) {
  height: 100% !important;
}

</style>
