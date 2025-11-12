<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"

import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Autoplay } from 'swiper/modules'

// Font Awesome
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import RecommendationCustomerRecentlyBoughtSlideIris from "@/Components/Iris/Recommendations/RecommendationCustomerRecentlyBoughtSlideIris.vue"
import { LastOrderedProduct } from "@/types/Resource/LastOrderedProductsResource"
library.add(faChevronLeft, faChevronRight)


const props = defineProps<{
    fieldValue: {}
    webpageData?: any
    blockData?: Object,
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()




const slidesPerView = computed(() => {
    const perRow = props.fieldValue?.settings?.per_row ?? {}
    return {
        desktop: perRow.desktop ?? 6,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }[props.screenType] ?? 5
})

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

const listProducts = ref<LastOrderedProduct[] | null>()
const isLoadingFetch = ref(false)

const listLoadingProducts = ref<Record<string, string>>({})
const isProductLoading = (productId: string) => {
    return listLoadingProducts.value?.[`recommender-${productId}`] === 'loading'
}

const isFetched = ref(false)
const fetchRecommenders = async () => {
    console.log('qqqqqqq')
    if (route().has('iris.json.product_category.last-ordered-products.index')) {
        // console.log('wwwwwwwwwwww')
        try {
            isLoadingFetch.value = true
            
            const response = await axios.get(
                route('iris.json.product_category.last-ordered-products.index', { productCategory: 31890 })
            )
            
            
            listProducts.value = response.data.data
    
            
            console.log('Final listProducts value:', listProducts.value)
            
        } catch (error: any) {
            console.error('Error on fetching recommendations:', error)
        } finally {
            isFetched.value = true
            isLoadingFetch.value = false
        }
    }
}

onMounted(() => {
    fetchRecommenders()
})
</script>

<template>
    <div aria-type="luigi-trends-1-iris" class="w-full pb-6" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <template v-if="!isFetched || listProducts?.length">
            <!-- Title -->
            <div class="px-3 py-6 pb-2">
                <div class="text-3xl font-semibold">
                    <div v-html="fieldValue.title"></div>
                </div>
            </div>
            
            <div class="py-4" id="LuigiTrends1">
                <Swiper :slides-per-view="slidesPerView ? slidesPerView : 4"
                    :loop="false"
                    :autoplay="false"
                    :pagination="{ clickable: true }"
                    :modules="[Autoplay]"
                    class="w-full"
                    xstyle="getStyles(fieldValue?.value?.layout?.properties, screenType)"
                    spaceBetween="12"
                    autoHeight
                >
                    <div v-if="isLoadingFetch" class="grid grid-cols-4 gap-x-4">
                        <div v-for="xx in 4" class="skeleton w-full h-64 rounded">
                        </div>
                    </div>

                    <template v-else>
                        <SwiperSlide
                            v-for="(product, index) in listProducts"
                            :key="index"
                            class="p-[1px] w-full cursor-grab relative !grid h-full min-h-full"
                        >
                            <RecommendationCustomerRecentlyBoughtSlideIris
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
</style>
