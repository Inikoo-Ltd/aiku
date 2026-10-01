<script setup lang="ts">
import { ref, computed, inject, onMounted, onBeforeUnmount } from "vue"
import { useRevealOutOfView } from "@/Iris/Composables/useRevealOutOfView"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'
import { ctrans } from "@/Composables/useTrans"

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Autoplay } from 'swiper/modules'

// Font Awesome
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import RecommendationCRBSlideIris from "@/Components/Iris/Recommendations/RecommendationCRBSlideIris.vue"
import { LastOrderedProduct } from "@/types/Resource/LastOrderedProductsResource"
library.add(faChevronLeft, faChevronRight)


const props = defineProps<{
    fieldValue: {
        family: {
            id: number
            slug: string
            name: string
        }
        product?: {
            id: number
        }
    }
    webpageData?: any
    blockData?: Object,
    screenType: 'mobile' | 'tablet' | 'desktop'
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

const listProducts = ref<LastOrderedProduct[]>([])
const blockContainer = ref<HTMLElement | null>(null)
const isShown = useRevealOutOfView(blockContainer, () => (listProducts.value?.length ?? 0) > 3)

const routeName = 'iris.json.product_category.last-ordered-products.index'

const isRouteAvailable = () => {
    if (typeof window === 'undefined' || typeof route !== 'function') {
        return false
    }

    try {
        return route().has(routeName)
    } catch {
        return false
    }
}

const fetchRecommenders = async () => {
    if (!props.fieldValue?.family?.id || !isRouteAvailable()) {
        return
    }

    try {
        const response = await axios.get(
            route(routeName, {
                productCategory: props.fieldValue.family.id,
                ignoredProductId: props.fieldValue?.product?.id
            })
        )
        listProducts.value = response.data?.data || []
    } catch (error: any) {
        console.error('Error on fetching recommendations:', error)
    }
}

onMounted(() => {
    fetchRecommenders()
    window.crbFetchRecommenders = fetchRecommenders
})

onBeforeUnmount(() => {
    if (window.crbFetchRecommenders === fetchRecommenders) {
        delete window.crbFetchRecommenders
    }
})
</script>

<template>
    <div ref="blockContainer" :id="fieldValue?.id ? fieldValue?.id  : 'recommendation-customer-recently-bought-1-iris'"  component="recommendation-customer-recently-bought-1-iris"  class="w-full pb-6 px-4" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <!-- Title -->
        <div v-if="isShown" class="px-3 py-6 pb-2">
            <div class="text-2xl md:text-3xl font-semibold">
                <p style="text-align: center">{{ ctrans("Customers Recently Bought") }}</p>
            </div>
        </div>

        <template v-if="isShown">

            <div class="py-4 px-3 md:px-12" id="recommendation-crb-1-iris">
                <Swiper :slides-per-view="slidesPerView ? slidesPerView : 4"
                    :loop="false"
                    :autoplay="false"
                    :pagination="{ clickable: true }"
                    :modules="[Autoplay]"
                    class="w-full"
                    spaceBetween="12"
                    autoHeight
                >
                    <SwiperSlide
                        v-for="(product, index) in listProducts"
                        :key="index"
                        class="p-[1px] w-full cursor-grab relative !grid h-full min-h-full"
                    >
                        <RecommendationCRBSlideIris
                            :product
                        />
                    </SwiperSlide>
                </Swiper>
            </div>
        </template>
    </div>
</template>

<style scoped>
</style>
