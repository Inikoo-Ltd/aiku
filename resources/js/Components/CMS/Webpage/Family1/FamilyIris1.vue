<script setup lang="ts">
import { ref } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from '@/Components/Image.vue'

library.add(faCube, faLink, faStar, faCircle)

const props = defineProps<{
  fieldValue: FieldValue
  webpageData?: any
  blockData?: Object
}>()
console.log(props)
// Mock image array – replace this with actual data from props
const images = ref(props.fieldValue.product.images.map((image: any) => image.source))

const currentImage = ref(0)

function nextImage() {
  currentImage.value = (currentImage.value + 1) % images.value.length
}

function prevImage() {
  currentImage.value = (currentImage.value - 1 + images.value.length) % images.value.length
}
</script>

<template>
  <div class="border-2 p-4 rounded-lg">
    <div class="relative w-full mb-3">
      <Image :src="images[currentImage]" alt="" :imageCover="true" />
      <!-- Arrows -->
      <button
        @click="prevImage"
        class="absolute top-1/2 left-2 transform -translate-y-1/2 bg-white/70 hover:bg-white p-1 rounded-full shadow"
      >
        ‹
      </button>
      <button
        @click="nextImage"
        class="absolute top-1/2 right-2 transform -translate-y-1/2 bg-white/70 hover:bg-white p-1 rounded-full shadow"
      >
        ›
      </button>
    </div>

    <div class="my-0.5 font-bold leading-5">
      {{ fieldValue.product.name }}
    </div>

    <div class="mb-0.5 flex justify-between items-end">
      <div class="leading-none"> {{ fieldValue.product.code }}</div>
      <div class="text-xs">RRP: {{ fieldValue.product.price }}/Piece</div>
    </div>

    <div class="mb-2 space-y-2">
      <div class="flex justify-between items-end">
        <div class="text-sm font-bold leading-none whitespace-nowrap">
          £9.60 (1.20/Piece)
        </div>
      </div>
    </div>

    <div class="mx-auto w-fit flex gap-x-2 mb-4">
      <div class="flex items-start gap-x-1">
        <div class="font-bold text-3xl leading-none cursor-pointer">-</div>
        <div
          class="h-8 aspect-square border border-gray-400 flex items-center justify-center tabular-nums text-xl font-bold"
        >
          1
        </div>
        <div class="font-bold text-3xl leading-none cursor-pointer">+</div>
      </div>
      <div class="bg-gray-600 text-white rounded px-3 py-1 h-fit w-fit">Order Now</div>
    </div>
  </div>
</template>
