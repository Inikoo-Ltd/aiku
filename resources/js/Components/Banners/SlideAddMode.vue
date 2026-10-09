<script setup lang='ts'>
import { ref, Ref } from 'vue'
import { ctrans } from '@/Composables/useTrans'
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faImage, faPhotoVideo } from '@fal'
library.add(faImage, faPhotoVideo)

const props = defineProps<{
    bannerType: string
}>()

const emits = defineEmits<{
    (e: 'dragOver'): void
    (e: 'dragLeave'): void
    (e: 'drop'): void
    (e: 'onChangeInput'): void
    (e: 'onClickButtonGallery'): void
    (e: 'addedFiles', files: File[]): void
    (e: 'onClickQuickStart'): void
}>()

const fileInput: Ref<any> = ref(null)

const onChange = () => {
    emits('addedFiles', fileInput.value?.files)
    emits('onChangeInput')
}


</script>

<template>
    <div class="w-full h-full space-y-2" @dragover="emits('dragOver')" @dragleave="emits('dragLeave')" @drop="(value)=>emits('drop',value)">
        <div class="relative mt-2 flex justify-center rounded-lg border border-indigo-400 shadow-lg px-6 py-10 bg-gradient-to-r from-green-300 via-blue-500 to-purple-600 hover:bg-gray-400/20"
            :class="bannerType == 'square' ? 'h-72 aspect-square mx-auto' : ''"
        >
            <label for="fileInput"
                class="absolute cursor-pointer rounded-md inset-0 focus-within:outline-none focus-within:ring-2 focus-within:ring-gray-400 focus-within:ring-offset-0">
                <!-- <span>{{ ctrans("Click") }}</span> -->
                <input type="file" multiple name="file" id="fileInput" class="sr-only" @change="onChange" ref="fileInput" />
            </label>

            <div class="text-center text-white">
                <FontAwesomeIcon :icon="['fal', 'image']" class="mx-auto h-12 w-12 text-gray-300" fixed-width aria-hidden="true" />
                <div class="mt-2 flex  justify-center text-3xl font-semibold leading-6 ">
                    <p class="pl-1">{{ ctrans("Let's get started.") }}</p>
                </div>
                <div class="flex text-sm leading-6 justify-center mt-4">
                    <p class="pl-1">{{ ctrans("Click me or drag some images here.") }}</p>
                </div>
                <p class="text-[0.7rem] mb-2.5">
                    {{ ctrans("PNG, JPG, GIF up to 10MB") }}
                </p>
                <div class="mt-2.5 flex items-center justify-center gap-x-2">
                    <Button id="gallery" :style="`tertiary`" :icon="'fal fa-photo-video'" label="Gallery" size="xs"
                        class="relative text-white hover:text-gray-700" @click="emits('onClickButtonGallery')" />
                    <Button id="quickStart" :style="`secondary`" :icon="''" label="Quick Start" size="xs"
                        class="relative" @click="emits('onClickQuickStart')" />
                </div>
            </div>
        </div>
        <div  class="text-xs text-gray-400 pt-1" :class="bannerType == 'landscape' ? '' : 'mx-auto w-fit'">{{ ctrans("Max file size 10 MB") }}</div>
        <div v-if="bannerType == 'landscape'" class="text-xs text-gray-400 py-1">{{ ctrans("The recommended image size is 1800 x 450") }}</div>
        <div v-else-if="bannerType == 'square'" class="mx-auto w-fit text-xs text-gray-400 py-1">{{ ctrans("The recommended image size is 500 x 500") }}</div>
    </div>
</template>
