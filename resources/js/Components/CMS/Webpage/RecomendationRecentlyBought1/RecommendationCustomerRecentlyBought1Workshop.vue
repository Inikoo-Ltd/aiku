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
import { trans } from "laravel-vue-i18n"
import { ProductHit } from "@/types/Luigi/LuigiTypes"
import { notify } from "@kyvg/vue3-notification"
import RecommendationCustomerRecentlyBoughtSlideIris from "@/Components/Iris/Recommendations/RecommendationCustomerRecentlyBoughtSlideIris.vue"
library.add(faChevronLeft, faChevronRight)

const props = defineProps<{
    modelValue: {
        family: {   // GetWebBlockRecommendationsCRB
            id: number
            slug: string
            name: string
        }
    }
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
        desktop: perRow.desktop ?? 6,
        tablet: perRow.tablet ?? 4,
        mobile: perRow.mobile ?? 2,
    }[props.screenType] ?? 1
})


const layout = inject('layout', retinaLayoutStructure)

const listProducts = ref<ProductHit[] | null>()
const isLoadingFetch = ref(false)
const fetchRecommenders = async () => {
    if (route().has('grp.json.product_category.last-ordered-products.index') && props.modelValue?.family?.id) {
        try {
            isLoadingFetch.value = true
            const response = await axios.get(
                route('grp.json.product_category.last-ordered-products.index', {
                    productCategory: props.modelValue?.family?.id,
                })
            )
            if (response.status !== 200) {
                console.error('Error fetching recommenders:', response.statusText)
            }
            listProducts.value = response.data.data
        } catch (error: any) {
            console.error('Error on fetching recommendations:', error)
        }
        isLoadingFetch.value = false
    } else {
        setTimeout(() => {
            notify({
                title: trans("Something went wrong"),
                text: "Recommendation CRB is not set properly. Reach developers to fix.",
                type: "error",
            })
        }, 500)
    }
}

onMounted(()=> {
    fetchRecommenders()
})
</script>

<template>
    <div id="recommendation-customer-recently-bought-1-workshop" class="w-full pb-6" :style="{
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
                        class="p-[1px] w-full cursor-grab relative !grid h-full min-h-full"
                    >
                        <RecommendationCustomerRecentlyBoughtSlideIris
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
