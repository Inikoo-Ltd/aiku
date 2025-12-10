<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Gallery from "@/Components/Fulfilment/Website/Gallery/Gallery.vue";
import Image from "@/Components/Image.vue"
import { ref } from 'vue'
import { cloneDeep } from "lodash-es";
import { getStyles } from "@/Composables/styles";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faImage } from "@fas";

library.add(faCube, faLink)

const props = defineProps<{
  modelValue: any
  webpageData?: any
  web_block: Object
  id: Number,
  type: String
  properties: {}
}>()

const emits = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'autoSave'): void
}>()

const openGallery = ref(false)
const imagePick = ref<number | null>(null);

const setImage = (e) => {
  const data = cloneDeep(props.modelValue)
  data.value[imagePick.value].image = e
  emits('update:modelValue', data)
  onCloseGallery()
  emits('autoSave')
}

const onUpload = (e) => {
  if (e.data && e.data.length <= 1) {
    const data = cloneDeep(props.modelValue)
    data.value[imagePick.value].image = e.data[0]
    emits('update:modelValue', data)
    onCloseGallery();
    emits('autoSave')
  } else {
    console.error('No files or multiple files detected.');
  }
};

const onOpenGallery = (index: number) => {
  openGallery.value = true
  imagePick.value = index
}

const onCloseGallery = () => {
  openGallery.value = false
  imagePick.value = null
}

</script>

<template>
  <div class="bg-white" :style="getStyles(properties)">
    <div class="w-full">


      <div v-if="modelValue.value?.picture" class="flex justify-center">
        <div @click="() => onOpenGallery(index)">
          <div v-if="!modelValue.value?.picture.length"
            class="flex rounded-md border border-black border-dashed w-full p-10 justify-center">
            <FontAwesomeIcon :icon="faImage" class="h-10 w-10 object-cover object-center group-hover:opacity-75" />
          </div>
          <div v-else class="w-full h-full">
            <Image :src="modelValue.value?.picture[0].image.source"
              class="object-cover object-center group-hover:opacity-75"></Image>
          </div>
        </div>
      </div>

      <!--   maintace data galery from aurora -->
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <div v-if="modelValue.value?.gallery" v-for="(product, index) in modelValue.value?.gallery" :key="product.id">
          <div @click="() => onOpenGallery(index)">
            <div v-if="!product.image"
              class="flex rounded-md border border-black border-dashed w-full p-10 justify-center">
              <FontAwesomeIcon :icon="faImage" class="h-10 w-10 object-cover object-center group-hover:opacity-75" />
            </div>
            <div v-else class="w-full h-full">
              <Image :src="product.image.source"
                class="object-cover object-center group-hover:opacity-75 w-full h-full" />
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <Gallery :open="openGallery" @on-close="onCloseGallery"
    :uploadRoutes="route(webpageData?.images_upload_route.name, { modelHasWebBlocks: id })" @onPick="setImage"
    @onUpload="onUpload">
  </Gallery>

</template>
