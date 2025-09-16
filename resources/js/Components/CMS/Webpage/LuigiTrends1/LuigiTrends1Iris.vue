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
import { trans } from "laravel-vue-i18n"
import { faCircle } from "@fas"
import { Link } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
// import ProductRenderEcom from "../Products1/ProductRenderEcom.vue"
library.add(faChevronLeft, faChevronRight)

interface ProductHits {
    attributes: {
        image_link: string
        price: string
        formatted_price: string
        department: string[]
        category: string[]
        product_code: string[]
        product_id: string[]
        stock_qty: string[]
        title: string
        web_url: string[]
    }
}

const props = defineProps<{
    fieldValue: {}
    webpageData?: any
    blockData?: Object,
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()




const slidesPerView = computed(() => {
    const perRow = props.fieldValue?.settings?.per_row ?? {}
    return {
        desktop: perRow.desktop ?? 4,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }[props.screenType] ?? 1
})

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

const listProducts = ref<ProductHits[] | null>()
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
                    "recommendation_type": "trends",
                    "recommender_client_identifier": "trends",
                    "size": 7,
                    "user_id": layout.iris?.user_auth?.customer_id?.toString(),
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
        console.log('111 rec:', response.data)
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
    <div id="see-also-1-iris" class="w-full pb-6" :style="{
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
                <Swiper :slides-per-view="slidesPerView ? Math.min(listProducts?.length || 0, slidesPerView || 0) : 4"
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
                        <SwiperSlide v-for="(image, index) in listProducts" :key="index" class="w-full cursor-grab relative hover:bg-gray-500/10 px-4 py-3 rounded flex flex-col justify-between h-full min-h-full">
                            <!-- Product Image - Always a link -->
                            <component :is="image.attributes.web_url?.[0] ? Link : 'div'"
                                :href="image.attributes.web_url?.[0]"
                                class="block rounded aspect-[5/4] w-full overflow-hidden">
                                <img :src="image.attributes.image_link" :alt="image.attributes.title"
                                    class="w-full h-full object-contain">
                            </component>

                            <!-- Title - Always a link -->
                            <component :is="image.attributes.web_url?.[0] ? Link : 'div'"
                                :href="image.attributes.web_url?.[0]"
                                class="font-bold text-sm mt-2 mb-1">
                                {{ image.attributes.title }}
                            </component>

                            <!-- SKU and RRP -->
                            <div class="flex justify-between text-xs text-gray-500 mb-1 capitalize">
                                <span>{{ image.attributes.product_code?.[0] }}</span>
                            </div>

                            <!-- Rating and Stock -->
                            <div class="flex justify-between items-center text-xs mb-2">
                                <div v-if="layout?.iris?.is_logged_in" v-tooltip="trans('Stock')"
                                    class="flex items-center gap-1"
                                    :class="Number(image.attributes?.stock_qty?.[0]) > 0 ? 'text-green-600' : 'text-red-600'">
                                    <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
                                    <span>{{ Number(image.attributes?.stock_qty?.[0]) > 0 ?
                                        locale.number(Number(image.attributes?.stock_qty?.[0])) : 0 }} {{ trans('available') }}</span>
                                </div>
                            </div>

                            <!-- Prices -->
                            <div v-if="layout?.iris?.is_logged_in" class="mb-3">
                                <div class="flex justify-between text-sm ">
                                    <span>{{ trans('Price') }}: <span class="font-semibold"> {{ image.attributes.formatted_price }}</span>
                                    </span>
                                    <!-- <span><span v-tooltip="trans('Recommended retail price')" >{{trans('RRP')}}</span>:  <span class="font-semibold">{{ locale.currencyFormat(layout.iris.currency.code,product.rrp) }}</span></span> -->
                                </div>
                            </div>
                            
                            <!-- Add to Basket Button -->
                            <div v-if="image.attributes.product_id?.[0]">
                                <Button @click="() => false"
                                    xdisabled="isProductLoading(image.attributes.product_id[0])"
                                    disabled
                                    :label="isProductLoading(image.attributes.product_id[0]) ? trans('Adding...') :
                                        trans('Add to Basket')"
                                    class="w-full justify-center"
                                    :loading="isProductLoading(image.attributes.product_id[0])"
                                />
                            </div>
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
