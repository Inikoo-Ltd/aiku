<script setup lang="ts">
import { inject, computed } from 'vue'
import Carousel from 'primevue/carousel'
import Image from '@/Components/Image.vue'
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
    indexBlock: number
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

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []
const baKeys = CardBlueprint?.blueprint?.map((b) => b?.key?.join("-")) || []

</script>

<template>
    <div id="carousel-cta">
        <div :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(modelValue.container?.properties, screenType)
        }">
            <Carousel :value="modelValue.carousel_data.cards" :numVisible="1" :numScroll="1" :circular="isLooping">
                <template #item="{ data, index }">
                    <div :style="{
                        ...getStyles(data.container?.properties, screenType),
                    }">
                        <div class="grid grid-cols-1 md:grid-cols-2 w-full min-h-[250px] md:min-h-[400px]">

                            <div class="relative w-full md:h-full cursor-pointer overflow-hidden" @click.stop="
                                () => {
                                    sendMessageToParent('activeBlock', indexBlock)
                                    sendMessageToParent('activeChildBlock', bKeys[1])
                                    sendMessageToParent('activeChildBlockArray', index)
                                    sendMessageToParent('activeChildBlockArrayBlock', baKeys[0])
                                }
                            " @dblclick.stop="() => sendMessageToParent('uploadImage', { ...imageSettings, key: ['carousel_data', 'cards', index, 'image', 'source'] })"
                                :style="getStyles(modelValue?.image?.container?.properties, screenType)">
                                <Image :src="data.image.source" :imageCover="true"
                                    :alt="data.image.alt || 'Image preview'"
                                    class="absolute inset-0 w-full h-full object-cover"
                                    :imgAttributes="data.image.attributes"
                                    />
                            </div>

                            <div class="flex flex-col justify-center m-auto p-4"
                                :style="getStyles(data?.text_block?.properties, screenType)">
                                <div class="max-w-xl w-full" @click="
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
                                        class="mb-6" 
                                        :uploadImageRoute="{
                                            name: webpageData.images_upload_route.name,
                                            parameters: {
                                                ...webpageData.images_upload_route.parameters,
                                                modelHasWebBlocks: blockData?.id,
                                            },
                                        }" 
                                    />

                                    <div class="flex justify-center">
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
#carousel-cta {
    .p-carousel-item {
        display: flex;
        justify-content: center;
    }
}

:deep(.p-carousel-indicator-list) {
    display: none;
}
</style>
