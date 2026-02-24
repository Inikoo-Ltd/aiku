<script setup lang="ts">
import { ref, computed, inject, onMounted, nextTick } from "vue"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Autoplay } from 'swiper/modules'
import Cookies from 'js-cookie';

import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import RecommendationSlideLastSeen from "@/Components/Iris/Recommendations/RecommendationSlideLastSeen.vue"
import RecommendationSlideIris from "@/Components/Iris/Recommendations/RecommendationSlideIris.vue"
import { ProductHit } from "@/types/Luigi/LuigiTypes"
import { RecommendationCollector } from "@/Composables/Unique/LuigiDataCollector"
import { trans } from "laravel-vue-i18n"
import RecommendationSlideIrisWithRealData from "@/Components/Iris/Recommendations/RecommendationSlideIrisWithRealData.vue"
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

const layout = inject('layout', retinaLayoutStructure)

const listProductsFromLuigi = ref<ProductHit[] | null>()
const isLoadingFetch = ref(false)

const listLoadingProducts = ref<Record<string, string>>({})
const isProductLoading = (productId: string) => {
    return listLoadingProducts.value?.[`recommender-${productId}`] === 'loading'
}

const isFetched = ref(false)

const fetchRecommenders = async () => {
    const userId = layout.iris.is_logged_in ? layout.iris_variables?.customer_id?.toString() : Cookies.get('_lb')

    try {
        isLoadingFetch.value = true
        const response = await axios.post(
            `https://live.luigisbox.tech/v1/recommend?tracker_id=${layout.iris?.luigisbox_tracker_id}`,
            [
                {
                    "blacklisted_item_ids": [],
                    "item_ids": [],
                    "recommendation_type": "trends",
                    "recommender_client_identifier": "trends",
                    "size": 25,
                    "user_id": userId ?? null,
                    "category": undefined,
                    "brand": undefined,
                    "product_id": undefined,
                    "recommendation_context": {},
                    // "hit_fields": ["url", "title"]
                }
            ],
            {
                headers: {
                    'Content-Type': 'application/json;charset=utf-8'
                }
            }
        )
        if (response.status !== 200) {
            console.error('Error fetching recommenders:', response.statusText)
        }

        // Send Analytics
        if (layout.app.environment === 'production') {
            RecommendationCollector(response.data[0])
        }

        console.log('LTrends1:', response.data)
        listProductsFromLuigi.value = response.data[0].hits
        fetchProductData()  // Fetch real data from DB
    } catch (error: any) {
        console.error('Error on fetching recommendations:', error)
    } finally {
        isFetched.value = true
        isLoadingFetch.value = false
    }
}

const isLoadingProductRealData = ref(false)
const fetchProductData = async () => {
    const productListid = listProductsFromLuigi.value?.map((item) => item.attributes.product_id[0])
    if (productListid?.length) {
        try {
            isLoadingProductRealData.value = true

            const response = await axios.get(
                route('iris.json.luigi.product_details'),
                {
                    params: {
                        product_ids: productListid?.join(',')
                    }
                }
            )

            listProductsFromLuigi.value.forEach((item, index) => {
                // Find the matching product_code[0] in response data
                const relatedProduct = response.data.data.find(product => item.attributes.product_code[0] === product.code);

                // const listKeys = Object.keys(relatedProduct?.web_images || {})
                // const isIncludeMainImage = listKeys.includes('main')

                // console.log('relatedProduct', item.attributes.product_code[0], isIncludeMainImage)

                // If a match is found and the stock is greater than 0, set the iris_attributes
                if (relatedProduct) {
                    item.iris_attributes = relatedProduct;
                }
            })

            // Filter only available stock
            listProductsFromLuigi.value = listProductsFromLuigi.value?.filter(prod => prod.iris_attributes?.stock > 0)
            nextTick()


            // console.log('wwwwwwwww', listProductsFromLuigi.value)
            // listProducts.value = response.data.data
        } catch (error: any) {
            console.error('Error on fetching recommendations:', error)
        } finally {
            isFetched.value = true
            isLoadingProductRealData.value = false
        }
    }
}

onMounted(() => {
    fetchRecommenders()
    window.luigiTrends = fetchRecommenders
})
</script>

<template>
    <div aria-type="luigi-trends-1-iris" class="w-full pb-6 px-4" :id="fieldValue?.id ? fieldValue?.id  : 'luigi-trends-1-iris'"  component="luigi-trends-1-iris"
    :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <template v-if="!isFetched || listProductsFromLuigi?.length">
            <!-- Title -->
            <div class="px-3 pt-6 md:pb-6">
                <div class="text-2xl md:text-3xl font-semibold">
                    <div>
                        <p style="text-align: center">{{ trans("Trending") }}</p>
                    </div>
                </div>
            </div>
            
            <div class="py-4 px-3 md:px-12" id="LuigiTrends1">
                <Swiper :slides-per-view="slidesPerView ? slidesPerView : 4"
                    :loop="false"
                    :autoplay="false"
                    :pagination="{ clickable: true }"
                    :modules="[Autoplay]"
                    class="w-full"
                    xstyle="getStyles(fieldValue?.value?.layout?.properties, screenType)"
                    spaceBetween="20"
                    autoHeight
                >
                    <div v-if="isLoadingFetch" class="grid grid-cols-4 gap-x-4">
                        <div v-for="xx in 4" class="skeleton w-full h-64 rounded">
                        </div>
                    </div>

                    <template v-else>
                        <SwiperSlide
                            v-for="(product, index) in listProductsFromLuigi"
                            :key="product.attributes.product_code[0]"
                            class="w-full cursor-grab relative !grid h-full min-h-full py-0.5"
                        >
                            <!-- <RecommendationSlideLastSeen
                                :product
                                :isProductLoading
                            /> -->

                            <RecommendationSlideIrisWithRealData
                                :product
                                :isProductLoading
                                :isLoadingProductRealData
                            />

                           <!--  <RecommendationSlideIris
                                :product
                                :isProductLoading
                            /> -->

                        </SwiperSlide>
                    </template>
                </Swiper>
            </div>
        </template>
    </div>
</template>

<style scoped>
</style>
