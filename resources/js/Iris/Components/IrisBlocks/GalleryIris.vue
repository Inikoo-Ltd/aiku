<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@common/Components/Image.vue"
import { ref } from 'vue'
import { getStyles } from "@/Composables/styles";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage } from "@fas";

library.add(faCube, faLink)

defineProps<{
  modelValue: any
  webpageData?: any
  web_block: Object
  id: Number,
  type: String
  properties: {}
  indexBlock:number
}>()


const openGallery = ref(false)
const imagePick = ref<number | null>(null);

const loadedImages = ref<Record<string | number, boolean>>({})

const onImageLoaded = (key: string | number) => {
  loadedImages.value[key] = true
}

const onOpenGallery = (index: number) => {
  openGallery.value = true
  imagePick.value = index
}



</script>

<template>
  <div class="bg-white" :style="getStyles(properties)" :id="fieldValue?.id ? fieldValue?.id  : 'gallery'+indexBlock"  component="gallery" >
    <div class="w-full">


      <div v-if="modelValue.value?.picture" class="flex justify-center">
        <div class="w-full" @click="() => onOpenGallery(0)">
          <div v-if="!modelValue.value?.picture.length"
            class="flex rounded-md border border-black border-dashed w-full h-[300px] sm:h-[400px] md:h-[500px] p-10 justify-center items-center">
            <FontAwesomeIcon :icon="faImage" class="h-10 w-10 object-cover object-center group-hover:opacity-75" />
          </div>
          <div v-else class="relative w-full h-[300px] sm:h-[400px] md:h-[500px] overflow-hidden">
            <div v-if="!loadedImages['picture']" class="absolute inset-0 animate-pulse bg-gray-200"></div>
            <Image :src="modelValue.value?.picture[0].image.source" imageCover
              class="w-full h-full object-cover object-center group-hover:opacity-75"
              @onLoadImage="onImageLoaded('picture')"></Image>
          </div>
        </div>
      </div>

      <!--   Maintenance data gallery from aurora -->
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <div v-if="modelValue.value?.gallery" v-for="(product, index) in modelValue.value?.gallery" :key="product.id">
          <div @click="() => onOpenGallery(index)">
            <div v-if="!product.image"
              class="flex rounded-md border border-black border-dashed w-full aspect-square p-10 justify-center items-center">
              <FontAwesomeIcon :icon="faImage" class="h-10 w-10 object-cover object-center group-hover:opacity-75" />
            </div>
            <div v-else class="relative w-full aspect-square overflow-hidden">
              <div v-if="!loadedImages[product.id ?? index]" class="absolute inset-0 animate-pulse bg-gray-200"></div>
              <Image :src="product.image.source" imageCover
                class="w-full h-full object-cover object-center group-hover:opacity-75"
                @onLoadImage="onImageLoaded(product.id ?? index)" />
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

</template>
