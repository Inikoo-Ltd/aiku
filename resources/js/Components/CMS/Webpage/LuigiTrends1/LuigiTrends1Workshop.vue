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
import RecommendationSlideWorkshop from "@/Components/Iris/Recommendations/RecommendationSlideWorkshop.vue"
import { ProductHit } from "@/types/Luigi/LuigiTypes"
library.add(faChevronLeft, faChevronRight)

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

const listProducts = ref<ProductHit[] | null>()
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
                    "category": undefined,
                    "brand": undefined,
                    "product_id": undefined,
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
    <div id="luigi-trends-1-workshop" class="w-full pb-6" :style="{
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

        <div class="py-4">
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
                    <SwiperSlide
                        v-for="(product, index) in listProducts"
                        :key="index"
                        class="w-full cursor-grab relative hover:bg-gray-500/10 px-4 py-3 rounded !grid h-full min-h-full"
                    >
                        <RecommendationSlideWorkshop
                            :product
                        />
                    </SwiperSlide>
                </template>
                <div v-else class="h-64 flex text-lg font-semibold flex-col items-center justify-center  w-full bg-gray-200">
                    <div>{{ trans("No products to show") }}</div>
                    <div class="text-sm italic text-gray-400">{{ trans("This will not appear in live website") }}</div>
                </div>
            </Swiper>
        </div>
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
