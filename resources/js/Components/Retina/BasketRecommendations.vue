<script setup lang="ts">
// import Image from '@/Components/Image.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link } from '@inertiajs/vue3'
// import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { inject, onBeforeUnmount, onMounted, ref, withDefaults } from 'vue'

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay } from 'swiper/modules'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '@/Components/Elements/Buttons/Button.vue'
// import { Carousel } from 'primevue'
library.add(faCircle)

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

const props = withDefaults(defineProps<{
    listLoadingProducts?: Record<string, string>
    blacklistItems?: string[]
}>(), {
    blacklistItems: () => []
})

const emit = defineEmits<{
    'add-to-basket': [productId: string, productCode: string]
}>()

const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

const listProducts = ref<ProductHits[] | null>()
const isLoadingFetch = ref(false)

const handleProductClick = (product: ProductHits) => {
    if (product.attributes.product_id?.[0]) {
        emit('add-to-basket', product.attributes.product_id[0], product.attributes.product_code[0])
    }
}

const isProductLoading = (productId: string) => {
    return props.listLoadingProducts?.[`recommender-${productId}`] === 'loading'
}

const fetchRecommenders = async () => {
    // console.log('11111 recommmmm', layout.user.user.customer_id)
    try {
        isLoadingFetch.value = true
        const response = await axios.post(
            `https://live.luigisbox.com/v1/recommend?tracker_id=${layout.iris.luigisbox_tracker_id}`,
            [
                {
                    "blacklisted_item_ids": props.blacklistItems,
                    "item_ids": [],
                    "recommendation_type": 'basket',
                    "recommender_client_identifier": 'basket',
                    "size": 7,
                    "user_id": layout.user?.customer_id?.toString(),
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
        console.log('Response axios:', response.data)
        listProducts.value = response.data[0].hits
    } catch (error: any) {
        console.error('Error on fetching recommendations:', error)
    }
    isLoadingFetch.value = false
}

onMounted(() => {
    fetchRecommenders()
})


// Section: responsive Slides per view
const slidesPerView = ref(4.5)
const updateSlidesPerView = () => {
    const width = window.innerWidth
    if (width < 640) {
        slidesPerView.value = 2.3 // mobile
    } else if (width < 768) {
        slidesPerView.value = 2.4 // small tablet
    } else if (width < 1024) {
        slidesPerView.value = 3.5 // tablet
    } else if (width < 1280) {
        slidesPerView.value = 4.5 // desktop
    } else {
        slidesPerView.value = 4.5 // large desktop
    }
}
onMounted(() => {
    // Set initial slides per view
    updateSlidesPerView()
    
    // Add resize listener
    window.addEventListener('resize', updateSlidesPerView)
})
// Cleanup resize listener
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateSlidesPerView)
})
</script>

<template>
    <div class="py-4" id="basket-recommendations" >
        <Swiper :slides-per-view="slidesPerView ? Math.min(listProducts?.length || 0, slidesPerView || 0) : 4"
            :loop="false" :autoplay="false" :pagination="{ clickable: true }" :modules="[Autoplay]" class="w-full"
            xstyle="getStyles(fieldValue?.value?.layout?.properties, screenType)" spaceBetween="12">
            <div v-if="isLoadingFetch" class="grid grid-cols-4 gap-x-4">
                <div v-for="xx in 4" class="skeleton w-full h-64 rounded">

                </div>
            </div>

            <template v-else-if="listProducts?.length">
                <SwiperSlide v-for="(image, index) in listProducts" :key="index" class="w-full cursor-grab relative hover:bg-gray-500/10 px-4 py-3 rounded flex flex-col justify-between h-full">
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
                            <span>{{ trans('Price') }}: <span class="font-semibold">{{
                                    image.attributes.formatted_price }}</span></span>
                            <!-- <span><span v-tooltip="trans('Recommended retail price')" >{{trans('RRP')}}</span>:  <span class="font-semibold">{{ locale.currencyFormat(layout.iris.currency.code,product.rrp) }}</span></span> -->
                        </div>
                    </div>

                    <!-- Add to Basket Button -->
                    <div v-if="image.attributes.product_id?.[0]">
                        <Button @click="handleProductClick(image)"
                            :disabled="isProductLoading(image.attributes.product_id[0])" :label="isProductLoading(image.attributes.product_id[0]) ? trans('Adding...') :
                                trans('Add to Basket')" class="w-full justify-center" :loading="isProductLoading(image.attributes.product_id[0])" />
                    </div>
                </SwiperSlide>
            </template>

            <div v-else class="w-full h-full text-center text-gray-400 py-6">
                <span class="italic">{{ trans("No recommendations found. Explore more products to get personalized suggestions") }}</span> 🙂
            </div>
        </Swiper>
    </div>
</template>