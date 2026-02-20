<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 23 Jul 2023 22:01:23 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch, toRef, computed, nextTick, onMounted } from 'vue'
import { get } from 'lodash-es'


import Image from "@/Components/Image.vue"
import { BannerWorkshop, CornersData } from '@/types/BannerWorkshop'
import { useRemoveHttps } from '@/Composables/useRemoveHttps'


import { library } from '@fortawesome/fontawesome-svg-core'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay, Pagination, Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

import SlideControls from '@/Components/Banners/Slider/Corners/SlideControls.vue'
import CentralStage from "@/Components/Banners/Slider/CentralStage.vue"
import SlideCorner from "@/Components/Banners/Slider/SlideCorner.vue"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faEyeSlash } from '@fas'
import { faExternalLink, faExclamationTriangle } from '@far'

library.add(faExternalLink, faEyeSlash, faExclamationTriangle)

const props = defineProps<{
    production?: boolean
    jumpToIndex?: string  // ulid
    data: BannerWorkshop
    view?: string
}>()

const swiperRef = ref(null)
const intSwiperKey = ref(1)

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
    swiperRef.value?.$el.swiper.slideToLoop(compIndexCurrentComponent.value, 0, false)
})

onMounted(() => {
    setTimeout(() => {
        intSwiperKey.value++  // To handle bug on Browser back navigation (Agnest & Cat)
    }, 600)
})

const compColorNav = computed(() => {
    return get(props.data, ['navigation', 'colorNav'], 'blue')
})

const renderImage = (component) => {
    if (props.production) {
        let view = "desktop"
        if (window) {
            if (window?.matchMedia("(max-width: 767px)").matches) {
                view = "mobile";
            } else if (window?.matchMedia("(min-width: 768px) and (max-width: 1023px)").matches) {
                view = "tablet";
            }
        }
        return get(component, ['image', view, 'source'], get(component, ['image', 'desktop', 'source'], null))
    } else return get(component, ['image', props.view, 'source'], get(component, ['image', 'desktop', 'source'], null))
}

const renderBackground = (component) => {
    if (props.production) {
        let view = "desktop"
        if (window) {
            if (window?.matchMedia("(max-width: 767px)").matches) {
                view = "mobile";
            } else if (window?.matchMedia("(min-width: 768px) and (max-width: 1023px)").matches) {
                view = "tablet";
            }
        }
        return get(component, ['layout', 'background', view], get(component, ['layout', 'background', 'desktop'], 'gray'))
    } else return get(component, ['layout', 'background', props.view], get(component, ['layout', 'background', 'desktop'], 'gray'))
}

const renderVideoUrl = (component) => {
    return get(component, ['video', props.view],
        get(component, ['video', 'desktop'], null))
}

const getCard = (component) => {
    return get(component, ['layout', 'card'], {})
}

const isYoutube = (url: string) =>
    url?.includes("youtube.com") || url?.includes("youtu.be")

const isDirectVideo = (url: string) =>
    url?.match(/\.(mp4|webm|ogg)$/i)

const bannerHeight = computed(() => {
    return get(props.data, ['common', 'height', props.view],
        get(props.data, ['common', 'height', 'desktop'], 400))
})

const isMounted = ref(false)
onMounted(() => {
    isMounted.value = true
})
</script>

<template>
    <div class="relative w-full">
        <div class="relative mx-auto transition-all duration-300 ease-in-out" :class="[
            $props.view
                ? {
                    'aspect-[2/1] max-w-[375px] w-full': $props.view == 'mobile',

                    'aspect-[3/1] max-w-[768px] w-full': $props.view == 'tablet',

                    'aspect-[4/1] w-full': $props.view == 'desktop'
                }
                : 'aspect-[2/1] md:aspect-[3/1] lg:aspect-[4/1] w-full'
        ]" :style="{ height: bannerHeight + 'px' }">

            <!-- Add v-if to avoid error in SSR -->
            <template v-if="isMounted">
                <Swiper ref="swiperRef" :key="'banner' + intSwiperKey" :slideToClickedSlide="true"
                    :spaceBetween="get(data, ['common', 'spaceBetween']) ? data.common.spaceBetween : 0"
                    :slidesPerView="1" :centeredSlides="true"
                    :loop="data.components.filter((item) => item.ulid).length > 1" :autoplay="false" :pagination="get(data, ['navigation', 'bottomNav', 'value'], false) && get(data, ['navigation', 'bottomNav', 'type', 'value'], false) == 'bullets' ? {  // Render Navigation (bullet)
                        clickable: true,
                        renderBullet: (index, className) => {
                            return `<span class='${className}'></span>`
                        },
                    } : false" :navigation="!data.navigation || data.navigation?.sideNav?.value"
                    :modules="[Autoplay, Pagination, Navigation]" class="mySwiper">
                    <SwiperSlide v-for="component in data.components.filter((item) => item.ulid)" :key="component.id">
                        <!-- Slide: Image -->
                        <div v-if="get(component, ['layout', 'backgroundType', props.view], get(component, ['layout', 'backgroundType', 'desktop'], 'image')) == 'image'"
                            class="relative w-full h-full">
                            <Image :src="renderImage(component)" alt="Wowsbar" />
                        </div>
                        <div v-else-if="get(component, ['layout', 'backgroundType', props.view], get(component, ['layout', 'backgroundType', 'desktop'], 'image')) == 'video'"
                            class="relative w-full h-full overflow-hidden">

                            <!-- Direct mp4 -->
                            <video v-if="isDirectVideo(renderVideoUrl(component))" :src="renderVideoUrl(component)"
                                autoplay muted loop playsinline class="w-full h-full object-cover" />

                            <!-- YouTube -->
                            <iframe v-else-if="isYoutube(renderVideoUrl(component))"
                                :src="`https://www.youtube.com/embed/${renderVideoUrl(component).split('v=')[1]}?autoplay=1&mute=1&loop=1&playlist=${renderVideoUrl(component).split('v=')[1]}`"
                                class="w-full h-full" allow="autoplay" />

                        </div>
                        <div v-else :style="{ background: renderBackground(component) }" class="w-full h-full" />
                        <!-- Section: Not Visible (for workshop) -->
                        <div v-if="get(component, ['visibility'], true) === false"
                            class="absolute h-full w-full bg-gray-800/50 z-10 " />
                        <div class="z-[11] absolute left-7 flex flex-col gap-y-2">
                            <FontAwesomeIcon v-if="get(component, ['visibility'], true) === false"
                                icon='fas fa-eye-slash' class=' text-orange-400 text-4xl' aria-hidden='true' />
                            <span v-if="get(component, ['visibility'], true) === false"
                                class="text-orange-400/60 text-sm italic select-none" aria-hidden='true'>
                                <FontAwesomeIcon icon='far fa-exclamation-triangle' class='' aria-hidden='true' />
                                Not visible
                            </span>
                        </div>
                        <!-- <FontAwesomeIcon v-if="!!component?.layout?.link" icon='far fa-external-link' class='text-gray-300/50 text-xl absolute top-2 right-2' aria-hidden='true' /> -->
                        <a v-if="!!component?.layout?.link" :href="`https://${useRemoveHttps(component?.layout?.link)}`"
                            target="_top" class="absolute bg-transparent w-full h-full" />
                        <SlideCorner v-for="(slideCorner, position) in filteredNulls(component?.layout?.corners)"
                            :position="position" :corner="slideCorner" :commonCorner="data.common.corners" />
                        <!-- CentralStage: slide-centralstage (prioritize) and common-centralStage -->
                        <template v-if="getCard(component)">
                            <template v-for="(card, key) in getCard(component)" :key="key">
                                <template v-if="card?.enabled">

                                    <div class="absolute inset-0 flex" :class="[
                                        {
                                            'justify-start pl-10': card.horizontal === 'left',
                                            'justify-center': card.horizontal === 'center',
                                            'justify-end pr-10': card.horizontal === 'right'
                                        },
                                        {
                                            'items-start pt-10': card.vertical === 'top',
                                            'items-center': card.vertical === 'middle',
                                            'items-end pb-10': card.vertical === 'bottom'
                                        }
                                    ]">
                                        <div v-if="!card.hideCard" :style="{
                                            width: (card.width || 600) + 'px',
                                            background: card.background || '#ffffff',
                                            padding: (card.padding || 30) + 'px',
                                            borderRadius: (card.radius || 10) + 'px',
                                            opacity: card.opacity ?? 1
                                        }" :class="card.shadow ? 'shadow-2xl' : ''">
                                            <CentralStage :data="{ title: card.title, subtitle: card.subtitle }" />
                                        </div>

                                        <div v-else>
                                            <CentralStage :data="{ title: card.title, subtitle: card.subtitle }" />
                                        </div>
                                    </div>

                                </template>
                            </template>
                        </template>
                        <template v-else>
                            <CentralStage
                                v-if="component?.layout?.centralStage?.title?.length > 0 || component?.layout?.centralStage?.subtitle?.length > 0"
                                :data="component?.layout?.centralStage" />
                            <CentralStage
                                v-else-if="data.common?.centralStage?.title?.length > 0 || data.common?.centralStage?.subtitle?.length > 0"
                                :data="data.common?.centralStage" />
                        </template>

                    </SwiperSlide>
                    <div v-if="data.navigation?.bottomNav?.value && data.navigation?.bottomNav?.type?.value == 'buttons'"
                        class="absolute bottom-1 left-1/2 -translate-x-1/2 z-10">
                        <SlideControls :dataBanner="data" :swiperRef="swiperRef" />
                    </div>
                </Swiper>
                <!-- Reserved Corner: Button Controls -->
                <SlideCorner class="z-10" v-for="(corner, position) in filteredNulls(data.common?.corners)"
                    :position="position" :corner="corner" :swiperRef="swiperRef" />
            </template>

            <div v-else class="absolute inset-0 skeleton h-full w-full">

            </div>
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
:deep(.swiper-button-prev),
:deep(.swiper-button-next) {
    color: v-bind(compColorNav) !important;
}
</style>
