<script setup lang="ts">
import { inject, computed } from 'vue'
import Carousel from 'primevue/carousel'
import Image from "@common/Components/Image.vue"
import Blueprint from './Blueprint'
import CardBlueprint from './CardBlueprint'
import Button from '@/Components/Elements/Buttons/Button.vue'
import EditorV2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { sendMessageToParent } from "@/Composables/Workshop"
import { getStyles } from "@/Composables/styles"

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

const isLooping = computed(() => {
    const settingsLoop = props.modelValue?.carousel_data?.carousel_setting?.loop || false
    return settingsLoop && props.modelValue.carousel_data.cards.length > 1
})

const isStacked = computed(() => props.screenType === 'mobile')

const gridClass = computed(() => isStacked.value ? 'grid-cols-1' : 'grid-cols-2')

const imageHeightClass = computed(() => {
    if (props.screenType === 'mobile') return 'h-[250px]'
    if (props.screenType === 'tablet') return 'h-[300px]'
    return 'h-[400px]'
})

const textPaddingClass = computed(() => {
    if (props.screenType === 'mobile') return 'px-4 py-6'
    if (props.screenType === 'tablet') return 'p-5'
    return 'p-4'
})

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const baKeys = CardBlueprint?.blueprint?.map((b) => b?.key?.join("-")) || []

</script>

<template>
    <div :id="modelValue?.id ? modelValue?.id  : 'carousel-cta' + indexBlock" component="carousel-cta"
        :class="{ 'carousel-cta-overlay-nav': isStacked }">
        <div :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(modelValue.container?.properties, screenType)
        }">
            <Carousel :value="modelValue.carousel_data.cards" :numVisible="1" :numScroll="1" :circular="isLooping">
                <template #item="{ data, index }">
                    <div class="w-full" :style="{
                        ...getStyles(data.container?.properties, screenType),
                    }">
                        <div class="grid w-full" :class="gridClass">

                            <div class="relative w-full cursor-pointer overflow-hidden"
                            :class="imageHeightClass"
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

                            <div class="flex flex-col justify-center m-auto w-full min-w-0"
                                :class="textPaddingClass"
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
                </template>
            </Carousel>
        </div>
    </div>
</template>

<style scoped>
[component="carousel-cta"] :deep(.p-carousel-item) {
    display: flex;
    justify-content: center;
}

[component="carousel-cta"] :deep(.p-carousel-viewport) {
    min-width: 0;
}

:deep(.p-carousel-indicator-list) {
    display: none;
}

.carousel-cta-overlay-nav :deep(.p-carousel-content) {
    position: relative;
}

.carousel-cta-overlay-nav :deep(.p-carousel-prev-button),
.carousel-cta-overlay-nav :deep(.p-carousel-next-button) {
    position: absolute;
    top: 50%;
    z-index: 2;
    margin: 0;
    transform: translateY(-50%);
}

.carousel-cta-overlay-nav :deep(.p-carousel-prev-button) {
    left: 0.25rem;
}

.carousel-cta-overlay-nav :deep(.p-carousel-next-button) {
    right: 0.25rem;
}
</style>
