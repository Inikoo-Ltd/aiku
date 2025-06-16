<script setup lang="ts">
import { Swiper, SwiperSlide } from 'swiper/vue'
import 'swiper/css'
import 'swiper/css/pagination'
import 'swiper/css/navigation'

import { ref, watch } from 'vue'
import { Pagination } from 'swiper/modules'
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from '@/Composables/styles'
import { faImage } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Image from '@/Components/Image.vue'
import { ulid } from 'ulid'
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from './Blueprint'

const props = defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: Object
    screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const emits = defineEmits<{
    (e: 'autoSave'): void
}>()

const keySwiper = ref(ulid())

const activeEditorIndex = ref<number | null>(null)

// Swiper will be re-rendered if this key changes
watch(
    () => props.modelValue.carousel_data.carousel_setting,
    () => {
        keySwiper.value = ulid()
    },
    { deep: true }
)
</script>

<template>
    <Swiper :key="keySwiper" :slides-per-view="modelValue.carousel_data.carousel_setting.slidesPerView.desktop"
        :loop="modelValue?.carousel_data?.carousel_setting?.loop"
      :autoplay="modelValue?.carousel_data?.carousel_setting?.autoplay"  :simulate-touch="true" :touch-ratio="1" :pagination="{ clickable: true }"
        :modules="[Pagination]" :spaceBetween="modelValue.carousel_data.carousel_setting.spaceBetween"
        class="touch-pan-x" :style="getStyles(modelValue.container?.properties, screenType)" :breakpoints="{
            0: { slidesPerView: modelValue.carousel_data.carousel_setting.slidesPerView.mobile },
            640: { slidesPerView: modelValue.carousel_data.carousel_setting.slidesPerView.mobile },
            768: { slidesPerView: modelValue.carousel_data.carousel_setting.slidesPerView.tablet },
            1024: { slidesPerView: modelValue.carousel_data.carousel_setting.slidesPerView.desktop }
        }">
        <SwiperSlide v-for="(card, index) in modelValue.carousel_data.cards" :key="index" :style="{
            ...getStyles(modelValue.carousel_data.card_container.properties, screenType),
            zIndex: activeEditorIndex === index ? 50 : 10
        }" class="flex flex-col overflow-visible relative overflow-hidden">
            <!-- Image area -->
            <div class="flex justify-center overflow-visible" :style="getStyles(modelValue?.carousel_data?.card_container?.container_image, screenType)">
                <div :style="getStyles(modelValue?.carousel_data?.card_container?.image_properties, screenType)"
                    class="bg-gray-100 w-full flex items-center justify-center overflow-visible">
                    <Image v-if="card.image?.source" :src="card.image.source" :alt="card.image.alt || `image ${index}`"
                        :imageCover="true" :style="getStyles(card.image.properties,screenType)" />
                    <font-awesome-icon v-else :icon="faImage" class="text-gray-400 text-4xl" />
                </div>
            </div>

            <!-- Editor -->
            <div v-if="modelValue?.carousel_data?.carousel_setting?.use_text" class="p-4 flex-1 flex flex-col justify-between pb-12 overflow-visible relative">
                <EditorV2 v-model="card.text" @onEditClick="activeEditorIndex = index" @blur="activeEditorIndex = null"
                    @update:modelValue="() => emits('autoSave')" :uploadImageRoute="{
                        name: webpageData.images_upload_route.name,
                        parameters: { modelHasWebBlocks: blockData.id }
                    }" />
            </div>
        </SwiperSlide>

    </Swiper>
</template>


<style scoped lang="scss">
:deep(.swiper-pagination-bullet) {
    background-color: #d1d5db !important; // Tailwind's gray-300
    opacity: 1;
    transition: background-color 0.3s ease;
}


:deep(.swiper-pagination-bullet-active) {
    background: #4b5563 !important;
}

.swiper {
  touch-action: pan-y;
}

</style>
