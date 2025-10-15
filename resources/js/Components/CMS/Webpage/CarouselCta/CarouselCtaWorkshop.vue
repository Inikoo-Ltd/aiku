<script setup lang="ts">
import { inject, ref, watch } from 'vue'
import Carousel from 'primevue/carousel'
import Image from '@/Components/Image.vue'
import Blueprint from './Blueprint'
import Button from '@/Components/Elements/Buttons/Button.vue'
import EditorV2 from '@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue'
import { sendMessageToParent } from "@/Composables/Workshop"
import { getStyles } from "@/Composables/styles"

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
    (e: "update:modelValue", value: string): void
    (e: "autoSave"): void
}>()

const layout: any = inject("layout", {})
const bKeys = Blueprint?.blueprint?.map(b => b?.key?.join("-")) || []

</script>

<template>
    <div id="carousel-cta">
        <div  :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(modelValue.container?.properties, screenType)
        }">
            <Carousel :value="modelValue.carousel_data.cards" :numVisible="1" :numScroll="1"
                :circular="modelValue.carousel_data.carousel_setting.loop">
                <template #item="{ data, index }">
                    <div class="relative bg-white" :style="{
                        ...getStyles(modelValue?.carousel_data?.cards[index]?.container?.properties, screenType)
                    }">
                        <div
                            class="relative h-80 overflow-hidden bg-indigo-600 md:absolute md:left-0 md:h-full md:w-1/3 lg:w-1/2">
                            <Image :src="data.image.source" :alt="data.image.alt" class="size-full object-cover"
                                :imageCover="true" />
                        </div>
                        <div class="relative mx-auto max-w-7xl py-24 sm:py-32 lg:px-8 lg:py-40">
                            <div class="pl-6 pr-6 md:ml-auto md:w-2/3 md:pl-16 lg:w-1/2 lg:pl-24 lg:pr-0 xl:pl-32">
                                <EditorV2 v-model="modelValue.carousel_data.cards[index].text" @focus="() => {
                                    sendMessageToParent('activeChildBlock', bKeys[1])
                                }" @update:modelValue="() => emits('autoSave')" class="mb-6" :uploadImageRoute="{
                                    name: webpageData.images_upload_route.name,
                                    parameters: {
                                        ...webpageData.images_upload_route.parameters,
                                        modelHasWebBlocks: blockData?.id,
                                    }
                                }" />
                                <div class="flex justify-center">
                                    <Button
                                        :injectStyle="getStyles(modelValue.carousel_data.cards[index].button.container?.properties, screenType)"
                                        :label="modelValue.carousel_data.cards[index]?.button?.text" />
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
