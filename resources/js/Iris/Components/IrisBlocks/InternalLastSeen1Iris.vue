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
import RecommendationSlideLastSeen from "@/Components/Iris/Recommendations/RecommendationSlideLastSeen.vue"

import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from "laravel-vue-i18n"
import { ProductHit } from "@/types/Luigi/LuigiTypes"
library.add(faChevronLeft, faChevronRight)

const props = defineProps<{
    fieldValue: {
        id?: string
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
    indexBlock:number
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

const webpageId = inject<number | null>('webpage_id', null)

const fetchProductsLastSeen = async () => {
    console.log('qqqqqqqqqqqqqq', webpageId)
    if (!webpageId) {
        isFetched.value = true
        return
    }

    try {
        isLoadingFetch.value = true

        const response = await axios.post(
            route('iris.json.product_last_seen.store', { webpage: webpageId })
        )

        listProducts.value = response.data.data
        
        console.log(`LLS (${response.data.data?.length}): `, response.data.data)

    } catch (error: any) {
        console.error('Error on fetching products last seen:', error)
    } finally {
        isFetched.value = true
        isLoadingFetch.value = false
    }
}

onMounted(() => {
    fetchProductsLastSeen()
})
</script>

<template>    
    <div data-block-type="luigi-last-seen-1-iris" class="w-full pb-6 px-4"  :id="fieldValue?.id ? fieldValue?.id  : 'luigi-last-seen-1-iris'+indexBlock"  component="luigi-last-seen-1-iris"
    :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <template v-if="!isFetched || listProducts?.length > 3">
            <!-- Title -->
            <div class="px-3 py-6 pb-2">
                <div class="text-3xl font-semibold">
                    <!-- <div v-html="fieldValue.title"></div> -->
                    <div>
                        <p style="text-align: center">{{ trans("Last seen") }}</p>
                    </div>
                </div>
            </div>
            
            <div class="py-4 px-3 md:px-12 swiper-container">
                <Swiper :slides-per-view="slidesPerView ? slidesPerView : 4"
                    :pagination="{ clickable: true }"
                    class="w-full"
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
