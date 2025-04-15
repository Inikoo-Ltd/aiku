<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Jul 2023 22:01:23 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch, computed, onMounted } from 'vue'
import { get } from 'lodash-es'

import SlideCorner from "@/Components/Banners/Slider/SlideCorner.vue"
import Image from "@/Components/Image.vue"
import CentralStage from "@/Components/Banners/Slider/CentralStage.vue"
import SlideControls from '@/Components/Banners/Slider/Corners/SlideControls.vue'

import { breakpointType } from '@/Composables/useWindowSize'
import { useRemoveHttps } from '@/Composables/useRemoveHttps'
import { useWindowSize } from '@vueuse/core'
import { BannerWorkshop, CornersData } from '@/types/BannerWorkshop'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faEyeSlash } from '@fas'
import { faExternalLink, faExclamationTriangle } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faExternalLink, faEyeSlash, faExclamationTriangle)

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay, Pagination, Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

const props = defineProps<{
    production?: boolean
    jumpToIndex?: string  // ulid
    data: BannerWorkshop
    view?: string
}>()

const swiperRef = ref(null)
const intSwiperKey = ref(0)
const { width: screenWidth, height: screenHeight }: any = useWindowSize()  // To detect responsive

const filteredNulls = (corners: CornersData) => {
    if (corners) {
        return Object.fromEntries(Object.entries(corners).filter(([_, v]) => v != null))
    }

    return ''
}

// const componentEdited = toRef(() => props.data.components.filter(component => component.ulid == props.jumpToIndex))  // make jumpToIndex to reactive to watch() it
const compIndexCurrentComponent = computed(() => {
    return props.data.components.findIndex(component => component.ulid == props.jumpToIndex)
})

// Jump view to slide (banner) on click slide (SlidesWorkshop)
watch(() => props.data.components.filter(component => component.ulid == props.jumpToIndex), (newVal) => {
    swiperRef.value?.$el.swiper.slideToLoop(compIndexCurrentComponent.value, 200, false)
})

const screenBreakpoint = computed(() => {
    return breakpointType(screenWidth.value)
})

// SlidesPerView depends on the screen
const compSlidesPerView = computed(() => {
    return !props.view
        ? screenBreakpoint.value == 'sm' || screenBreakpoint.value == 'xs'
            ? 1  // If below md: 1 slide per view
            : screenBreakpoint.value == 'md'
                ? actualSlides.value.length < 3 ? actualSlides.value.length : 3   // If md: 3 slide per view
                : actualSlides.value.length < 4 ? actualSlides.value.length : 4  // If lg and larger: 4 slide per view
        : props.view == 'mobile'
            ? 1  // slidePerview is 1 if responsive button clicked on Mobile
            : props.view == 'tablet'
                ? actualSlides.value.length < 3 ? actualSlides.value.length : 3
                : actualSlides.value.length < 4 ? actualSlides.value.length : 4  // if actualSlides length is less than 4 then slidePerview = actualSlide length
})

// The actual Slides (filter slide that don't have ulid)
const actualSlides = computed(() => {
    return props.data.components.filter((item) => item.ulid)
})

// Square: Double the actualSlides length to avoid Swiper bugs (Slides must 2x length from slidesPerView)
const compHandleBannerLessSlide = computed(() => {
    return actualSlides.value.length <= 4
        ? screenBreakpoint.value == 'sm' || screenBreakpoint.value == 'xs'
            ? actualSlides.value.length == 1 ? actualSlides.value : [...actualSlides.value, ...actualSlides.value]
            : screenBreakpoint.value == 'md'
                ? actualSlides.value.length <= 3 ? actualSlides.value : [...actualSlides.value, ...actualSlides.value]
                : actualSlides.value.length <= 4 ? actualSlides.value : [...actualSlides.value, ...actualSlides.value]
        : screenBreakpoint.value == 'sm' || screenBreakpoint.value == 'xs'
            ? actualSlides.value
            : screenBreakpoint.value == 'md'
                ? actualSlides.value.length >= 6 ? actualSlides.value : [...actualSlides.value, ...actualSlides.value]
                : actualSlides.value.length >= 8 ? actualSlides.value : [...actualSlides.value, ...actualSlides.value]
})

onMounted(() => {
    setTimeout(() => {
        intSwiperKey.value++  // To handle bug on Browser back navigation (Agnest & Cat)
    }, 600)
})

// Handle color of arrow navigation (default is blue)
const compColorNav = computed(() => {
    return get(props.data, ['navigation', 'colorNav'], 'blue')
})


// Handle width of banner on first load
const compWidthBanner = computed(() => {
    return compSlidesPerView.value > compHandleBannerLessSlide.value.length ? compHandleBannerLessSlide.value.length : compSlidesPerView.value
})


const isMounted = ref(false)
onMounted(() => {
    isMounted.value = true
})
</script>

<template>
 <div class="h-full max-h-full max-w-full relative shadow overflow-hidden mx-auto transition-all duration-200 ease-in-out" :style="{
        backgroundColor: props.data.common.spaceColor,
        aspectRatio:
            $props.view == 'mobile' ? '1/1'
            : $props.view == 'tablet' ? compHandleBannerLessSlide.length >= 3 ? '3/1' : `${compHandleBannerLessSlide.length}/1`
            : $props.view == 'desktop' ? compHandleBannerLessSlide.length >= 4 ? '4/1' : `${compHandleBannerLessSlide.length}/1`
            : `${compWidthBanner}/1`
        }">
        <!-- Add v-if to avoid error in SSR -->
        <template v-if="isMounted">
            <Swiper ref="swiperRef"
                :key="'banner' + intSwiperKey"
                :slideToClickedSlide="false"
                :spaceBetween="get(data,['common','spaceBetween']) ? data.common.spaceBetween : 0"
                :slidesPerView="compSlidesPerView"
                :centeredSlides="false"
                :loop="compHandleBannerLessSlide.length > compSlidesPerView"
                :autoplay="{
                    delay: data.delay,
                    disableOnInteraction: false,
                }"
                :pagination="get(data, ['navigation', 'bottomNav', 'value'], false) && get(data, ['navigation', 'bottomNav', 'type', 'value'], false) == 'bullets' ? {  // Render Navigation (bullet)
                    clickable: true,
                    renderBullet: (index, className) => {
                        return `<span class='${className}'></span>`
                    },
                } : false"
                :navigation="!data.navigation || data.navigation?.sideNav?.value"
                :modules="[Autoplay, Pagination, Navigation]"
                class="mySwiper h-full w-full"
            >
                <SwiperSlide v-for="component in compHandleBannerLessSlide" :key="component.id"
                    class="h-full overflow-hidden aspect-square">
                    <!-- Section: image or background -->
                    <div v-if="get(component, ['layout', 'backgroundType', 'desktop'], 'image') === 'image'"
                        class="relative w-full h-full">
                        <Image :src="get(component, ['image', 'desktop', 'source'], null)"
                            alt="Wowsbar" />
                    </div>
                    <div v-else
                        :style="{ background: get(component, ['layout', 'background', 'desktop'], 'gray') }"
                        class="w-full h-full" />
                    <!-- Section: Not Visible (for workshop) -->
                    <div v-if="get(component, ['visibility'], true) === false"
                        class="absolute h-full w-full bg-gray-800/50 z-10 " />
                    <div class="z-[11] absolute left-7 flex flex-col gap-y-2">
                        <FontAwesomeIcon v-if="get(component, ['visibility'], true) === false" icon='fas fa-eye-slash'
                            class=' text-orange-400 text-4xl' aria-hidden='true' />
                        <span v-if="get(component, ['visibility'], true) === false"
                            class="text-orange-400/60 text-sm italic select-none" aria-hidden='true'>
                            <FontAwesomeIcon icon='far fa-exclamation-triangle' class='' aria-hidden='true' />
                            Not visible
                        </span>
                    </div>
                    <!-- <FontAwesomeIcon v-if="!!component?.layout?.link" icon='far fa-external-link' class='text-gray-300/50 text-xl absolute top-2 right-2' aria-hidden='true' /> -->
                    <a target="_top" v-if="!!component?.layout?.link" :href="`https://${useRemoveHttps(component?.layout?.link)}`" class="absolute bg-transparent w-full h-full" />
                    <SlideCorner v-for="(slideCorner, position) in filteredNulls(component?.layout?.corners)"
                        :position="position" :corner="slideCorner" />
                    <!-- CentralStage: slide-centralstage (prioritize) and common-centralStage -->
                    <CentralStage
                        v-if="component?.layout?.centralStage?.title?.length > 0 || component?.layout?.centralStage?.subtitle?.length > 0"
                        :data="component?.layout?.centralStage" />
                    <CentralStage
                        v-else-if="data.common?.centralStage?.title?.length > 0 || data.common?.centralStage?.subtitle?.length > 0"
                        :data="data.common?.centralStage" />
                </SwiperSlide>
                <div v-if="data.navigation?.bottomNav?.value && data.navigation?.bottomNav?.type?.value == 'buttons'" class="absolute bottom-1 left-1/2 -translate-x-1/2 z-10">
                    <SlideControls :dataBanner="data" :swiperRef="swiperRef" />
                </div>
            </Swiper>
            <!-- Reserved Corner: Button Controls -->
            <SlideCorner class="z-10" v-for="(corner, position) in filteredNulls(data.common?.corners)" :position="position"
                :corner="corner" :swiperRef="swiperRef" />
        </template>

        <div v-else class="absolute inset-0 skeleton h-full w-full">

        </div>
    </div>
</template>

<style lang="scss" scoped>
:deep(.swiper) {
    @apply w-full h-full;
}

:deep(.swiper-slide) {
    @apply bg-gray-200;
    text-align: center;
    font-size: 18px;
    display: flex;
    justify-content: center;
    align-items: center;
}

:deep(.swiper-slide img) {
    @apply w-full h-full;
    object-fit: cover;
}

// Pagination: Bullet
:deep(.swiper-pagination-bullet) {
    @apply h-3 w-3 text-slate-700 text-center;
    background-color: v-bind(compColorNav) !important;
    opacity: 0.4 !important;
}

// Pagination: Bullet (active)
:deep(.swiper-pagination-bullet-active) {
    @apply text-white scale-110;
    background-color: v-bind(compColorNav) !important;
    opacity: 1 !important;
}

// Navigation: Arrow
:deep(.swiper-button-prev), :deep(.swiper-button-next) {
    color: v-bind(compColorNav) !important;
}

</style>
