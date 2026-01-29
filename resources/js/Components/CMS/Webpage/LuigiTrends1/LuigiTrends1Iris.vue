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
import { Autoplay } from 'swiper/modules'
import Cookies from 'js-cookie';

import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import RecommendationSlideLastSeen from "@/Components/Iris/Recommendations/RecommendationSlideLastSeen.vue"
import RecommendationSlideIris from "@/Components/Iris/Recommendations/RecommendationSlideIris.vue"
import { ProductHit } from "@/types/Luigi/LuigiTypes"
import { RecommendationCollector } from "@/Composables/Unique/LuigiDataCollector"
import { trans } from "laravel-vue-i18n"
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
const listProducts= ref<ProductHit[] | null>()

const listLoadingProducts = ref<Record<string, string>>({})
const isProductLoading = (productId: string) => {
    return listLoadingProducts.value?.[`recommender-${productId}`] === 'loading'
}

const isFetched = ref(false)

// const luigiFetchRecommenders = async () => {
//     try {
//         // isLoadingFetch.value = true
//         const response = await axios.post(
//             `https://live.luigisbox.tech/v1/recommend?tracker_id=${layout.iris?.luigisbox_tracker_id}`,
//             [
//                 {
//                     "blacklisted_item_ids": [],
//                     "item_ids": [],
//                     "recommendation_type": "trends",
//                     "recommender_client_identifier": "trends",
//                     "size": 25,
//                     "user_id": layout.iris?.auth?.user?.customer_id?.toString() ?? Cookies.get('_lb') ?? null,
//                     "category": undefined,
//                     "brand": undefined,
//                     "product_id": undefined,
//                     "recommendation_context": {},
//                     // "hit_fields": ["url", "title"]
//                 }
//             ],
//             {
//                 headers: {
//                     'Content-Type': 'application/json;charset=utf-8'
//                 }
//             }
//         )
//         if (response.status !== 200) {
//             console.error('Error fetching recommenders:', response.statusText)
//         }

//         // RecommendationCollector(response.data[0])

//         console.log('LTrends1:', response.data)
//         // listProductsFromLuigi.value = response.data[0].hits
//     } catch (error: any) {
//         console.error('Error on fetching recommendations:', error)
//     } finally {
//         // isFetched.value = true
//     }
//     // isLoadingFetch.value = false
// }

const fetchRecommenders = async () => {
    try {
        // isLoadingFetch.value = true
        const response = await axios.post(
            `https://live.luigisbox.tech/v1/recommend?tracker_id=${layout.iris?.luigisbox_tracker_id}`,
            [
                {
                    "blacklisted_item_ids": [],
                    "item_ids": [],
                    "recommendation_type": "trends",
                    "recommender_client_identifier": "trends",
                    "size": 25,
                    "user_id": layout.iris?.auth?.user?.customer_id?.toString() ?? Cookies.get('_lb') ?? null,
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

        RecommendationCollector(response.data[0])

        console.log('LTrends1:', response.data)
        listProductsFromLuigi.value = response.data[0].hits
        fetchRecommendersToGetProducts()
    } catch (error: any) {
        console.error('Error on fetching recommendations:', error)
    } finally {
        isFetched.value = true
    }
    isLoadingFetch.value = false
}

// const fetchRecommenders = async () => {
//     try {
//         isLoadingFetch.value = true

//         /* const luigiIdentity = props.fieldValue?.product?.luigi_identity

//         if (!luigiIdentity) {
//             listProductsFromLuigi.value = []
//             return
//         } */

//         const response = await axios.post(
//             route('iris.json.luigi.product_recommendation'),
//             {
//                 luigi_identity: '',
//                 recommendation_type : 'trends',
//                 recommender_client_identifier : 'trends',
//                 cookies_lb: Cookies.get('_lb') ?? null,
//             }
//         )
//         listProductsFromLuigi.value = response.data
//     } catch (error: any) {
//         console.error('Error on fetching recommendations:', error)
//     } finally {
//         isFetched.value = true
//         isLoadingFetch.value = false
//     }
// }


const fetchRecommendersToGetProducts = async () => {
    const productListid = listProductsFromLuigi.value?.map((item) => item.attributes.product_id[0])
    if (productListid?.length) {
        try {
            isLoadingFetch.value = true

            const response = await axios.get(
                route('iris.json.luigi.product_details'),
                {
                    params: {
                        product_ids: productListid?.join(',')
                    }
                }
            )
            listProducts.value = response.data.data
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
    window.luigiTrends = fetchRecommenders
})
</script>

<template>
    <div aria-type="luigi-trends-1-iris" class="w-full pb-6 px-4" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <template v-if="!isFetched || listProductsFromLuigi?.length">
            <!-- Title -->
            <div class="px-3 py-6 pb-6">
                <div class="text-3xl font-semibold">
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
                            class="w-full cursor-grab relative !grid h-full min-h-full py-0.5"
                        >
                            <RecommendationSlideLastSeen
                                :product
                                :isProductLoading
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
