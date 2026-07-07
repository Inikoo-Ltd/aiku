<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'
import { trans } from "laravel-vue-i18n"

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
const isLoadingFetch = ref(false)
const isFinish = ref(false)

const fetchRecommenders = async () => {
    if (route().has('iris.json.product_category.last-ordered-products.index')) {
        try {
            isLoadingFetch.value = true
            const response = await axios.get(
                route('iris.json.product_category.last-ordered-products.index',
                {  // GetLastOrderedProducts
                    productCategory: props.fieldValue.family.id,
                    ignoredProductId: props.fieldValue?.product?.id
                })
            )
            listProducts.value = response.data.data || []
            // listProducts.value = []
            
            if (!(listProducts.value?.length > 3)) {
                console.warn('Block CRB are less than 3, will not showed.')
            }
        } catch (error: any) {
            console.error('Error on fetching recommendations:', error)
        } finally {
            isLoadingFetch.value = false
            isFinish.value = true
        }
    }
}

onMounted(() => {
    fetchRecommenders()
    window.crbFetchRecommenders = fetchRecommenders
})
</script>

<template>
    <div :id="fieldValue?.id ? fieldValue?.id  : 'recommendation-customer-recently-bought-1-iris'"  component="recommendation-customer-recently-bought-1-iris"  class="w-full pb-6 px-4" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <!-- Title -->
        <div v-if="!isFinish || (isFinish && listProducts.length)" class="px-3 py-6 pb-2">
            <div class="text-2xl md:text-3xl font-semibold">
                <p style="text-align: center">{{ trans("Customers Recently Bought") || "Customers Recently Bought" }}</p>
            </div>
        </div>

        <div v-if="isLoadingFetch" class="py-4 px-3 md:px-12 grid gap-x-3" :style="{ gridTemplateColumns: `repeat(${slidesPerView ? slidesPerView : 4}, minmax(0, 1fr))` }">
            <div v-for="xx in (slidesPerView ? slidesPerView : 4)" :key="xx" class="flex flex-col w-full md:px-4 md:py-3">
                <div class="skeleton w-full max-w-[220px] aspect-square mx-auto rounded"></div>
                <div class="skeleton mt-3 min-h-[2.3em] w-full rounded"></div>
                <!-- <div class="skeleton mt-2 h-4 w-1/2 mx-auto rounded"></div> -->
            </div>
        </div>

        <template v-else-if="listProducts && listProducts.length > 3">

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
