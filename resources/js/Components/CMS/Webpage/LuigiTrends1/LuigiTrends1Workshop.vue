<script setup lang="ts">
import { ref, computed, inject, onMounted } from "vue"
import { getStyles } from "@/Composables/styles"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

// Swiper
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'
import { Autoplay } from 'swiper/modules'

// Font Awesome
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
// import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import axios from "axios"
import RecommendationSlideWorkshop from "@/Components/Iris/Recommendations/RecommendationSlideWorkshop.vue"
import { ProductHit } from "@/types/Luigi/LuigiTypes"
library.add(faChevronLeft, faChevronRight)

const props = defineProps<{
    modelValue: any
    webpageData?: {
        id: number
        title: string
        sub_type?: string
        department?: {
            webpage_title: string
        }
        images_upload_route: {
            name: string
            parameters: any
        }
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

const listProducts = ref<ProductHit[]>([])
const isLoadingFetch = ref(false)
const isFetched = ref(false)
const isFamilyPage = props.webpageData?.sub_type === 'family'

const luigiTrendsDepartment = async (departmentWebpageTitle?: string) => {
    console.log('departmentWebpageTitle', departmentWebpageTitle)
    return axios.post(
        `https://live.luigisbox.tech/v2/recommend?tracker_id=${layout.iris?.luigisbox_tracker_id}`,
        [
            {
                size: 25,
                widget_id: departmentWebpageTitle ? "product_recommendation" : "department_recommendation",
                auth_user_id: null,
                recommendation_context: [{
                    attribute: "department",
                    values: [departmentWebpageTitle ?? props.webpageData?.title],
                    operator: "or"
                }],
                model: "department"
            }
        ],
        { headers: { 'Content-Type': 'application/json;charset=utf-8' } }
    )
}

const luigiTrendsSubDepartment = async () => {
    return axios.post(
        `https://live.luigisbox.tech/v2/recommend?tracker_id=${layout.iris?.luigisbox_tracker_id}`,
        [
            {
                size: 25,
                widget_id: "sub_department_recommendation",
                auth_user_id: null,
                recommendation_context: [{
                    attribute: "sub_department",
                    values: [props.webpageData?.title],
                    operator: "or"
                }],
                model: "department"
            }
        ],
        { headers: { 'Content-Type': 'application/json;charset=utf-8' } }
    )
}

const luigiTrendsGlobal = async () => {
    return axios.post(
        `https://live.luigisbox.tech/v1/recommend?tracker_id=${layout.iris?.luigisbox_tracker_id}`,
        [
            {
                blacklisted_item_ids: [],
                item_ids: [],
                recommendation_type: "trends",
                recommender_client_identifier: "trends",
                size: 25,
                user_id: null,
                recommendation_context: {},
            }
        ],
        { headers: { 'Content-Type': 'application/json;charset=utf-8' } }
    )
}

const fetchRecommenders = async () => {
    try {
        isLoadingFetch.value = true

        const subType = props.webpageData?.sub_type

        if (subType === 'family') {
            return
        }

        const response = await (
            subType === 'department' ? luigiTrendsDepartment() :
            subType === 'product' ? luigiTrendsDepartment(props.webpageData?.department?.webpage_title) :
            subType === 'sub_department' ? luigiTrendsSubDepartment() :
            luigiTrendsGlobal()
        )

        if (response.status !== 200) {
            console.error('Error fetching recommenders:', response.statusText)
        }

        listProducts.value = response.data[0].hits
    } catch (error: any) {
        console.error('Error on fetching recommendations:', error)
    } finally {
        isFetched.value = true
        isLoadingFetch.value = false
    }
}

onMounted(()=> {
    fetchRecommenders()
})
</script>

<template>
    <div :id="modelValue?.id ? modelValue?.id  : 'luigi-trends-1-iris'+indexBlock" class="w-full pb-6" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(modelValue.container?.properties, screenType),
        width: 'auto'
    }">
        <!-- Title -->
        <div class="px-4 py-6 pb-2 text-3xl font-semibold">
            <p style="text-align: center" :class="isFamilyPage ? 'opacity-40' : ''">{{ ctrans("Trending") }}</p>
            <!-- <EditorV2
                v-model="modelValue.title"
                @focus="() => {
                    sendMessageToParent('activeBlock', indexBlock?)
                }"
                @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
                    name: webpageData.images_upload_route.name,
                    parameters: { modelHasWebBlocks: blockData?.id }
                }"
            /> -->
        </div>

        <div class="py-4">
            <Swiper
                :slides-per-view="slidesPerView ? Math.min(listProducts?.length || 0, slidesPerView || 0) : 4"
                :loop="false" :autoplay="false" :pagination="{ clickable: true }" :modules="[Autoplay]"
                class="w-full" xstyle="getStyles(fieldValue?.value?.layout?.properties, screenType)"
                spaceBetween="12"
                autoHeight
            >
                <div v-if="isFamilyPage" class="h-64 flex text-lg font-normal flex-col items-center justify-center w-full bg-red-100 border-y border-red-300">
                    <div class="text-red-500 font-semibold">{{ ctrans("Trends is not available for Family page") }}</div>
                    <div class="text-sm italic opacity-50">{{ ctrans("This will not appear in live website") }}</div>
                </div>
                <div v-else-if="isLoadingFetch" class="grid grid-cols-4 gap-x-4">
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
                    <div>{{ ctrans("No products to show") }}</div>
                    <div class="text-sm italic text-gray-400">{{ ctrans("This will not appear in live website") }}</div>
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
