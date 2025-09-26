<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"

import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import axios from 'axios'
import Cookies from 'js-cookie';

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Autoplay, Navigation, Pagination } from 'swiper/modules'
import RecommendationSlideIris from "@/Components/Iris/Recommendations/RecommendationSlideIris.vue"

// Font Awesome
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from "laravel-vue-i18n"
import { faCircle } from "@fas"
import { Link } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
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
        desktop: perRow.desktop ?? 5,
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
        const response = await axios.post(
            `https://live.luigisbox.com/v1/recommend?tracker_id=${layout.iris?.luigisbox_tracker_id}`,
            [
                {
                    "blacklisted_item_ids": [],
                    "item_ids": [],
                    "recommendation_type": "last_seen",
                    "recommender_client_identifier": "last_seen",
                    "size": 12,
                    "user_id": layout.iris?.user_auth?.customer_id?.toString() ?? Cookies.get('_lb') ?? null,  // Customer ID or Cookie _lb
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
        console.log('LLS1:', response.data)
        listProducts.value = response.data[0].hits
    } catch (error: any) {
        console.error('Error on fetching recommendations:', error)
    } finally {
        isFetched.value = true
    }
    isLoadingFetch.value = false
}

onMounted(() => {
    fetchRecommenders()
})
</script>

<template>
    <div aria-type="luigi-last-seen-1-iris" class="w-full pb-6" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue.container?.properties, screenType),
        width: 'auto'
    }">
        <template v-if="!isFetched || listProducts?.length > 3">
            <!-- Title -->
            <div class="px-3 py-6 pb-2">
                <div class="text-3xl font-semibold">
                    <div v-html="fieldValue.title"></div>
                </div>
            </div>
            
            <div class="py-4">
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
                            class="w-full cursor-grab relative hover:bg-gray-500/10 px-4 py-3 rounded !grid h-full min-h-full"
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
.swiper-nav-button {
    @apply absolute top-1/2 transform -translate-y-1/2 z-10 bg-white border border-gray-300 rounded-full shadow-md p-2 hover:bg-gray-100 transition-all duration-300;
}

.swiper-nav-button svg {
    @apply text-gray-700 w-4 h-4;
}
</style>
