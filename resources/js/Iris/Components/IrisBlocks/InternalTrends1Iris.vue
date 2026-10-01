<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { useRevealOutOfView } from "@/Iris/Composables/useRevealOutOfView"
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
import { RecommendationProduct } from "@/types/RecommendationProduct"
import { ctrans } from "@/Composables/useTrans"
import RecommendationSlideLastSeen from "@/Components/Iris/Recommendations/RecommendationSlideLastSeen.vue"

library.add(faChevronLeft, faChevronRight)

const props = defineProps<{
    fieldValue: {
        id?: string
        product?: {
            id?: number
        }
        recommendation_scope?: {
            department_id?: number
            sub_department_id?: number
            family_id?: number
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
    indexBlock: number
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

const listProducts = ref<RecommendationProduct[]>([])
const blockContainer = ref<HTMLElement | null>(null)
const isShown = useRevealOutOfView(blockContainer, () => (listProducts.value?.length ?? 0) > 0)

const listLoadingProducts = ref<Record<string, string>>({})
const isProductLoading = (productId: string) => {
    return listLoadingProducts.value?.[`recommender-${productId}`] === 'loading'
}

const fetchProductTrends = async () => {
    try {
        const response = await axios.get(
            route('iris.json.product_trends.index'),
            {
                params: props.fieldValue?.recommendation_scope ?? {}
            }
        )

        const currentProductId = props.fieldValue?.product?.id
        listProducts.value = response.data.data.filter(
            (product: RecommendationProduct) => product.id !== currentProductId
        )

    } catch (error: any) {
        console.error('Error on fetching product trends:', error)
    }
}

onMounted(() => {
    fetchProductTrends()
    window.fetchTrends = fetchProductTrends
})
</script>

<template>
    <div ref="blockContainer" data-block-type="internal-trends-1-iris" class="w-full pb-6 px-4" :id="fieldValue?.id ? fieldValue?.id  : 'internal-trends-1-iris'+indexBlock" component="internal-trends-1-iris"
    :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <template v-if="isShown">
            <!-- Title -->
            <div class="px-3 pt-6 md:pb-6">
                <div class="text-2xl md:text-3xl font-semibold">
                    <div>
                        <p style="text-align: center">{{ ctrans("Trending") }}<span v-if="layout.app.environment === 'local'" class="ml-2 bg-red-500">(Internal)</span></p>
                    </div>
                </div>
            </div>

            <div class="py-4 px-3 md:px-12" id="InternalTrends1">
                <Swiper :slides-per-view="slidesPerView ? slidesPerView : 4"
                    :pagination="{ clickable: true }"
                    class="w-full"
                    spaceBetween="12"
                    autoHeight
                >
                    <SwiperSlide
                        v-for="(product, index) in listProducts"
                        :key="index"
                        class="w-full cursor-grab relative !grid h-full min-h-full"
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
:deep(#InternalTrends1 .swiper-wrapper) {
  height: 100% !important;
}
</style>
