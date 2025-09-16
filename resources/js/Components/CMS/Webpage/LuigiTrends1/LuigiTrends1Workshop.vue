<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"

import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

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
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import axios from "axios"
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import Button from "@/Components/Elements/Buttons/Button.vue"
library.add(faChevronLeft, faChevronRight)

interface ProductHits {
    attributes: {
        image_link: string
        price: string
        formatted_price: string
        department: string[]
        category: string[]
        product_code: string[]
        stock_qty: string[]
        title: string
        web_url: string[]
    }
}
const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: {}
    indexBlock?: number
    screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "autoSave"): void
}>()



const slidesPerView = computed(() => {
    const perRow = props.modelValue?.settings?.per_row ?? {}
    return {
        desktop: perRow.desktop ?? 4,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }[props.screenType] ?? 1
})


const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)
console.log('lala', layout)

const listProducts = ref<ProductHits[] | null>()
const isLoadingFetch = ref(false)
const fetchRecommenders = async () => {
    try {
        isLoadingFetch.value = true
        const response = await axios.post(
            `https://live.luigisbox.com/v1/recommend?tracker_id=${layout.iris?.luigisbox_tracker_id}`,
            [
                {
                    "blacklisted_item_ids":  [],
                    "item_ids": [],
                    "recommendation_type": "trends",
                    "recommender_client_identifier": "trends",
                    "size": 7,
                    // "user_id": "1234",
                    "recommendation_context":  {},
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

onMounted(()=> {
    fetchRecommenders()
})
</script>

<template>
    <div id="see-also-1-workshop" class="w-full pb-6" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType),
        width: 'auto'
    }">
        <!-- Title -->
        <div class="px-4 py-6 pb-2 text-3xl font-semibold">
            <EditorV2
                v-model="modelValue.title"
                @focus="() => {
                    sendMessageToParent('activeBlock', indexBlock)
                }"
                @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
                    name: webpageData.images_upload_route.name,
                    parameters: { modelHasWebBlocks: blockData?.id }
                }"
            />
        </div>

        <Swiper
            :slides-per-view="slidesPerView ? Math.min(listProducts?.length || 0, slidesPerView || 0) : 4"
            :loop="false" :autoplay="false" :pagination="{ clickable: true }" :modules="[Autoplay]"
            class="w-full" xstyle="getStyles(fieldValue?.value?.layout?.properties, screenType)"
            spaceBetween="12"
            autoHeight
        >
            <div v-if="isLoadingFetch" class="grid grid-cols-4 gap-x-4">
                <div v-for="xx in 4" class="skeleton w-full h-64 rounded">

                </div>
            </div>

            <template v-else-if="listProducts?.length">
                <SwiperSlide v-for="(image, index) in listProducts" :key="index" class="w-full h-full min-h-full">
                    <div class="relative border border-gray-300 hover:border-gray-700 px-4 py-3 rounded flex flex-col justify-between h-full">
                        <!-- Product Image -->
                        <component :is="image.attributes.web_url?.[0] ? Link : 'div'" :href="image.attributes.web_url?.[0]" class="block rounded aspect-[5/4] w-full overflow-hidden">
                            <img
                                :src="image.attributes.image_link"
                                :alt="image.attributes.title"
                                class="w-full h-full object-contain"
                            >
                        </component>
                        
                        <!-- Title -->
                        <Link v-if="image.attributes.web_url?.[0]" :href="image.attributes.web_url?.[0]"
                            class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                            {{ image.attributes.title }}
                        </Link>
                        <div v-else class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                            {{ image.attributes.title }}
                        </div>

                        <!-- SKU and RRP -->
                        <div class="flex justify-between text-xs text-gray-500 mb-1 capitalize">
                            <span>{{ image.attributes.product_code?.[0] }}</span>
                        </div>

                        <!-- Rating and Stock -->
                        <div class="flex justify-between items-center text-xs mb-2">
                            <div v-if="layout?.iris?.is_logged_in" v-tooltip="trans('Stock')" class="flex items-center gap-1"
                                :class="Number(image.attributes?.stock_qty?.[0]) > 0 ? 'text-green-600' : 'text-red-600'">
                                <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
                                <span>{{ Number(image.attributes?.stock_qty?.[0]) > 0 ? Number(image.attributes?.stock_qty?.[0]) : 0 }} {{trans('available')}}</span>
                            </div>
                        </div>

                        <!-- Prices -->
                        <div v-if="layout?.iris?.is_logged_in" class="mb-3">
                            <div class="flex justify-between text-sm ">
                                <span>{{ trans('Price') }}: <span class="font-semibold">{{ image.attributes.formatted_price }}</span></span>
                                <!-- <span><span v-tooltip="trans('Recommended retail price')" >{{trans('RRP')}}</span>:  <span class="font-semibold">{{ locale.currencyFormat(layout.iris.currency.code,product.rrp) }}</span></span> -->
                            </div>
                        </div>

                        <!-- Add to Basket Button -->
                        <div v-if="image.attributes.product_id?.[0]">
                            <Button
                                disabled
                                :label="trans('Add to Basket')"
                                class="w-full justify-center"
                            />
                        </div>
                    </div>
                </SwiperSlide>
            </template>

            <div v-else class="h-64 flex text-lg font-semibold flex-col items-center justify-center  w-full bg-gray-200">
                <div>{{ trans("No products to show") }}</div>
                <div class="text-sm italic text-gray-400">{{ trans("This will not appear in live website") }}</div>
            </div>
        </Swiper>
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
