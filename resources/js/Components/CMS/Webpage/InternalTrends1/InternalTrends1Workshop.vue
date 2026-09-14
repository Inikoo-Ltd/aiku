<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ctrans } from "@/Composables/useTrans"
import axios from "axios"

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Autoplay } from 'swiper/modules'

import RecommendationSlideInternalWorkshop from "@/Components/Iris/Recommendations/RecommendationSlideInternalWorkshop.vue"
import { InternalRecommendationProduct } from "@/types/Recommendations/RecommendationTypes"

const props = defineProps<{
    modelValue: any
    webpageData?: {
        id: number
        title: string
        sub_type?: string
    }
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
        desktop: perRow.desktop ?? 6,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }[props.screenType] ?? 1
})

const layout = inject('layout', retinaLayoutStructure)

const listProducts = ref<InternalRecommendationProduct[]>([])
const isLoadingFetch = ref(false)
const isFamilyPage = props.webpageData?.sub_type === 'family'

const fetchProductTrends = async () => {
    if (isFamilyPage || !props.webpageData?.id) {
        return
    }

    try {
        isLoadingFetch.value = true

        const response = await axios.get(
            route('grp.json.webpage.product_trends.index', { webpage: props.webpageData.id })
        )

        listProducts.value = response.data.data
    } catch (error: any) {
        console.error('Error on fetching product trends:', error)
    } finally {
        isLoadingFetch.value = false
    }
}

onMounted(() => {
    fetchProductTrends()
})
</script>

<template>
    <div :id="modelValue?.id ? modelValue?.id : 'internal-trends-1-workshop' + indexBlock" class="w-full pb-6" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType),
        width: 'auto'
    }">
        <!-- Title -->
        <div class="px-4 py-6 pb-2 text-3xl font-semibold">
            <p style="text-align: center" :class="isFamilyPage ? 'opacity-40' : ''">{{ ctrans("Trending") }}</p>
        </div>

        <div class="py-4">
            <Swiper
                :slides-per-view="slidesPerView ? Math.min(listProducts?.length || 0, slidesPerView || 0) : 4"
                :loop="false" :autoplay="false" :pagination="{ clickable: true }" :modules="[Autoplay]"
                class="w-full"
                spaceBetween="12"
                autoHeight
            >
                <div v-if="isFamilyPage" class="h-64 flex text-lg font-normal flex-col items-center justify-center w-full bg-red-100 border-y border-red-300">
                    <div class="text-red-500 font-semibold">{{ ctrans("Trends is not available for Family page") }}</div>
                    <div class="text-sm italic opacity-50">{{ ctrans("This will not appear in live website") }}</div>
                </div>
                <div v-else-if="isLoadingFetch" class="grid grid-cols-4 gap-x-4">
                    <div v-for="xx in 4" :key="xx" class="skeleton w-full h-64 rounded">
                    </div>
                </div>
                <template v-else-if="listProducts?.length">
                    <SwiperSlide
                        v-for="(product, index) in listProducts"
                        :key="index"
                        class="w-full cursor-grab relative hover:bg-gray-500/10 px-4 py-3 rounded !grid h-full min-h-full"
                    >
                        <RecommendationSlideInternalWorkshop
                            :product
                        />
                    </SwiperSlide>
                </template>
                <div v-else class="h-64 flex text-lg font-semibold flex-col items-center justify-center w-full bg-gray-200">
                    <div>{{ ctrans("No products to show") }}</div>
                    <div class="text-sm italic text-gray-400">{{ ctrans("This will not appear in live website") }}</div>
                </div>
            </Swiper>
        </div>
    </div>
</template>
