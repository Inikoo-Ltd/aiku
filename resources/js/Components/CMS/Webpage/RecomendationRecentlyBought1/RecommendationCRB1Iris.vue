<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"

import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
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
        family: {   // GetWebBlockRecommendationsCRB
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
        desktop: perRow.desktop ?? 6,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }[props.screenType] ?? 5
})

const layout = inject('layout', retinaLayoutStructure)

const listProducts = ref<LastOrderedProduct[] | null>()
const isLoadingFetch = ref(false)


const isFetched = ref(false)
const fetchRecommenders = async () => {
    if (route().has('iris.json.product_category.last-ordered-products.index')) {
        try {
            isLoadingFetch.value = true
            const response = await axios.get(
                route('iris.json.product_category.last-ordered-products.index', {
                    productCategory: props.fieldValue.family.id,
                    product: props.fieldValue?.product?.id
                })
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
    <div id="recommendation-customer-recently-bought-1-iris" class="w-full pb-6 px-4" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <template v-if="!isFetched || listProducts?.length">
            <!-- Title -->
            <div class="px-3 py-6 pb-2">
                <div class="text-xl md:text-3xl font-semibold">
                    <!-- <div v-html="fieldValue.title"></div> -->
                    <p style="text-align: center">{{ trans("Customers Recently Bought") || "Customers Recently Bought" }}</p>
                </div>
            </div>
            
            <div class="py-4 px-4" id="recommendation-crb-1-iris">
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
                            <RecommendationCRBSlideIris
                                :product
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
