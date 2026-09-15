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
        desktop: perRow.desktop ?? 5,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }[props.screenType] ?? 1
})

const layout = inject('layout', retinaLayoutStructure)

const listProducts = ref<InternalRecommendationProduct[]>([])
const isLoadingFetch = ref(false)

const fetchProductAlternatives = async () => {
    if (!props.webpageData?.id) {
        return
    }

    try {
        isLoadingFetch.value = true

        const response = await axios.get(
            route('grp.json.webpage.product_alternatives.index', { webpage: props.webpageData.id })
        )

        listProducts.value = response.data.data
    } catch (error: any) {
        console.error('Error on fetching product alternatives:', error)
    } finally {
        isLoadingFetch.value = false
    }
}

onMounted(() => {
    fetchProductAlternatives()
})
</script>

<template>
    <div :id="modelValue?.id ? modelValue?.id : 'internal-item-alternatives-1-workshop' + indexBlock" class="w-full pb-6" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType),
        width: 'auto'
    }">
        <!-- Title -->
        <div class="px-3 py-6 pb-2">
            <div class="text-2xl md:text-3xl font-semibold">
                <div>
                    <p style="text-align: center">{{ ctrans("You may also like") }}<span v-if="layout.app.environment === 'local'" class="ml-2 bg-red-500">(Internal)</span></p>
                </div>
            </div>
        </div>

        <div class="py-4">
            <Swiper
                :slides-per-view="slidesPerView ? Math.min(listProducts?.length || 0, slidesPerView || 0) : 4"
                :loop="false" :autoplay="false" :pagination="{ clickable: true }" :modules="[Autoplay]"
                class="w-full"
                spaceBetween="12"
                autoHeight
            >
                <div v-if="isLoadingFetch" class="grid grid-cols-4 gap-x-4">
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
                    <div>{{ ctrans("No products recommendation to show") }}</div>
                    <div class="text-sm italic text-gray-400">{{ ctrans("If no recommendations found, this web block will not be shown in live website") }}</div>
                </div>
            </Swiper>
        </div>
    </div>
</template>
