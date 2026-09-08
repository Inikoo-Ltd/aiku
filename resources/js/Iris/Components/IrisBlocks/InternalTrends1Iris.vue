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
import { ProductHit } from "@/types/Luigi/LuigiTypes"
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

const listProducts = ref<ProductHit[]>([])
const isLoadingFetch = ref(false)
const isFetched = ref(false)

const listLoadingProducts = ref<Record<string, string>>({})
const isProductLoading = (productId: string) => {
    return listLoadingProducts.value?.[`recommender-${productId}`] === 'loading'
}

const fetchProductTrends = async () => {
    try {
        isLoadingFetch.value = true

        const response = await axios.get(
            route('iris.json.product_trends.index'),
            {
                params: props.fieldValue?.recommendation_scope ?? {}
            }
        )

        const currentProductId = props.fieldValue?.product?.id
        listProducts.value = response.data.data.filter(
            (product: ProductHit) => product.id !== currentProductId
        )

        console.log(`LTrends Internal (${response.data.data?.length}): `, response.data.data)
    } catch (error: any) {
        console.error('Error on fetching product trends:', error)
    } finally {
        isFetched.value = true
        isLoadingFetch.value = false
    }
}

onMounted(() => {
    fetchProductTrends()
    window.fetchTrends = fetchProductTrends
})
</script>

<template>
    <div data-block-type="luigi-trends-1-iris" class="w-full pb-6 px-4" :id="fieldValue?.id ? fieldValue?.id  : 'luigi-trends-1-iris'+indexBlock" component="luigi-trends-1-iris"
    :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <template v-if="!isFetched || listProducts?.length">
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
                    <div v-if="isLoadingFetch" class="grid gap-x-3" :style="{ gridTemplateColumns: `repeat(${slidesPerView ? slidesPerView : 4}, minmax(0, 1fr))` }">
                        <div v-for="xx in (slidesPerView ? slidesPerView : 4)" :key="xx" class="flex flex-col rounded bg-white">
                            <div class="mb-3 flex justify-center">
                                <div class="skeleton w-full max-w-[220px] aspect-square rounded"></div>
                            </div>
                            <div class="skeleton mb-1 min-h-[3.15em] w-full rounded"></div>
                            <div class="xflex justify-between">
                                <div class="skeleton h-4 w-1/3 rounded"></div>
                                <div class="skeleton h-4 w-1/4 rounded"></div>
                            </div>
                        </div>
                    </div>

                    <template v-else>
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
                    </template>
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
