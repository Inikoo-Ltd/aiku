<script setup lang="ts">
import { inject, computed, ref } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay } from 'swiper/modules'
import 'swiper/css'
import Image from "@common/Components/Image.vue"
import Blueprint from './Blueprint'
import CardBlueprint from './CardBlueprint'
import Button from '@/Components/Elements/Buttons/Button.vue'
import EditorV2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { sendMessageToParent } from "@/Composables/Workshop"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faChevronRight } from '@fas'

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
    indexBlock?: number
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "autoSave"): void
}>()

const imageSettings = {
    key: ["image", "source"],
    stencilProps: {
        aspectRatio: [16 / 9, null],
        movable: true,
        scalable: true,
        resizable: true,
    },
}

const cards = computed(() => props.modelValue?.carousel_data?.cards || [])

const isLooping = computed(() => {
    const settingsLoop = props.modelValue?.carousel_data?.carousel_setting?.loop || false
    return settingsLoop && cards.value.length > 1
})

const swiperInstance = ref<any>(null)
const isBeginning = ref(true)
const reachedEnd = ref(false)
const isEnd = computed(() => cards.value.length <= 1 || reachedEnd.value)

const syncNavigatorState = (swiper: any) => {
    isBeginning.value = swiper.isBeginning
    reachedEnd.value = swiper.isEnd
}

const onSwiper = (swiper: any) => {
    swiperInstance.value = swiper
    syncNavigatorState(swiper)
}

const slidePrev = () => swiperInstance.value?.slidePrev()
const slideNext = () => swiperInstance.value?.slideNext()

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const baKeys = CardBlueprint?.blueprint?.map((b) => b?.key?.join("-")) || []

</script>

<template>
    <div :id="modelValue?.id ? modelValue?.id  : 'carousel-cta' + indexBlock" component="carousel-cta">
        <div :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(modelValue.container?.properties, screenType)
        }">
            <div class="carousel-cta-content">
                <button type="button" class="carousel-cta-nav carousel-cta-nav-prev" aria-label="Previous"
                    :disabled="!isLooping && isBeginning" @click.stop="slidePrev">
                    <FontAwesomeIcon :icon="faChevronLeft" />
                </button>

                <Swiper class="w-full min-w-0" :modules="[Autoplay]" :slides-per-view="1" :space-between="0"
                    :loop="isLooping" :autoplay="false" @swiper="onSwiper" @slide-change="syncNavigatorState">
                    <SwiperSlide v-for="(data, index) in cards" :key="index">
                        <div class="w-full" :style="{
                            ...getStyles(data.container?.properties, screenType),
                        }">
                            <div class="grid grid-cols-1 sm:grid-cols-2 w-full">

                                <div class="relative w-full cursor-pointer overflow-hidden h-[250px] sm:h-[300px] lg:h-[400px]"
                                :style="getStyles(modelValue?.image?.container?.properties, screenType)"
                                 @click.stop="
                                    () => {
                                        sendMessageToParent('activeBlock', indexBlock)
                                        sendMessageToParent('activeChildBlock', bKeys[1])
                                        sendMessageToParent('activeChildBlockArray', index)
                                        sendMessageToParent('activeChildBlockArrayBlock', baKeys[0])
                                    }
                                " 
                                @dblclick.stop="
                                    () => sendMessageToParent('uploadImage', { ...imageSettings, key: ['carousel_data', 'cards', index, 'image', 'source'] })
                                "
                                    >
                                    <Image :src="data.image.source" :imageCover="true"
                                        :alt="data.image.alt || 'Image preview'"
                                        class="absolute inset-0 w-full h-full object-cover"
                                        :imgAttributes="data.image.attributes"
                                        :height="getStyles(modelValue?.image?.container?.properties, screenType, false)?.height"
                                        :width="getStyles(modelValue?.image?.container?.properties, screenType, false)?.width"
                                        />
                                </div>

                                <div class="flex flex-col justify-center m-auto w-full min-w-0 px-4 py-6 sm:p-5 lg:p-4"
                                    :style="getStyles(data?.text_block?.properties, screenType)">
                                    <div class="max-w-xl w-full mx-auto" @click="
                                        () => {
                                            sendMessageToParent('activeBlock', indexBlock)
                                            sendMessageToParent('activeChildBlock', bKeys[1])
                                            sendMessageToParent('activeChildBlockArray', index)
                                        }
                                    ">
                                        <EditorV2
                                            v-if="data?.text"
                                            v-model="data.text"
                                            @focus="() => sendMessageToParent('activeChildBlock', bKeys[1])"
                                            @update:modelValue="(e) => { data.text = e, emits('autoSave')}"
                                            :uploadImageRoute="{
                                                name: webpageData.images_upload_route.name,
                                                parameters: {
                                                    ...webpageData.images_upload_route.parameters,
                                                    modelHasWebBlocks: blockData?.id,
                                                },
                                            }" 
                                        />

                                        <div class="flex justify-center mt-6">
                                            <Button
                                                :injectStyle="getStyles(data?.button?.container?.properties, screenType)"
                                                :label="data?.button?.text" @click.stop="
                                                    () => {
                                                        sendMessageToParent('activeBlock', indexBlock)
                                                        sendMessageToParent('activeChildBlock', bKeys[1])
                                                        sendMessageToParent('activeChildBlockArray', index)
                                                        sendMessageToParent('activeChildBlockArrayBlock', baKeys[1])
                                                    }
                                                " />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </SwiperSlide>
                </Swiper>

                <button type="button" class="carousel-cta-nav carousel-cta-nav-next" aria-label="Next"
                    :disabled="!isLooping && isEnd" @click.stop="slideNext">
                    <FontAwesomeIcon :icon="faChevronRight" />
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.carousel-cta-content {
    display: flex;
    flex-direction: row;
    gap: 0.25rem;
}

.carousel-cta-nav {
    align-self: center;
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    border: 0 none;
    border-radius: 50%;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    transition: background-color 0.2s, color 0.2s;
}

.carousel-cta-nav:hover:not(:disabled) {
    background: rgba(0, 0, 0, 0.05);
    color: #374151;
}

.carousel-cta-nav:disabled {
    opacity: 0.6;
    cursor: default;
}

@media (max-width: 639px) {
    .carousel-cta-content {
        position: relative;
    }

    .carousel-cta-nav {
        position: absolute;
        top: 50%;
        z-index: 2;
        margin: 0;
        transform: translateY(-50%);
    }

    .carousel-cta-nav-prev {
        left: 0.25rem;
    }

    .carousel-cta-nav-next {
        right: 0.25rem;
    }
}
</style>
