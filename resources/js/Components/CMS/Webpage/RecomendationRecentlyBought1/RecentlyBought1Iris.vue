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
import { Autoplay, Navigation, Pagination } from 'swiper/modules'

// Font Awesome
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import RecommendationSlideIris from "@/Components/Iris/Recommendations/RecommendationSlideIris.vue"
import { ProductHit } from "@/types/Luigi/LuigiTypes"
// import ProductRenderEcom from "../Products1/ProductRenderEcom.vue"
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

const listProducts = ref<ProductHit[] | null>()
const isLoadingFetch = ref(false)

const listLoadingProducts = ref<Record<string, string>>({})
const isProductLoading = (productId: string) => {
    return listLoadingProducts.value?.[`recommender-${productId}`] === 'loading'
}

const isFetched = ref(false)
const fetchRecommenders = async () => {
    try {
        isLoadingFetch.value = true
        
        const response = await axios.get(
            route('iris.json.product_category.last-ordered-products.index', { productCategory: 8279 })
        )
        
        
        if (response.data?.[0]?.hits) {
            console.log('Found hits in data[0]:', response.data[0].hits)
            listProducts.value = response.data[0].hits
        } else if (Array.isArray(response.data)) {
            console.log('Using data directly as array:', response.data)
            listProducts.value = response.data
        } else if (response.data?.data) {
            console.log('Found data property:', response.data.data)
            listProducts.value = response.data.data
        } else {
            console.log('Data structure not recognized, setting to empty array')
            listProducts.value = []
        }
        
        console.log('Final listProducts value:', listProducts.value)
        console.log('=== END FETCH SUCCESS ===')
        
    } catch (error: any) {
        console.log('=== FETCH ERROR ===')
        console.error('Error on fetching recommendations:', error)
        console.log('Error response:', error.response?.data)
        console.log('Error status:', error.response?.status)
        console.log('=== END FETCH ERROR ===')
    } finally {
        isFetched.value = true
        isLoadingFetch.value = false
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
                            class="w-full cursor-grab relative !grid h-full min-h-full"
                        >
                            <RecommendationSlideIris
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
