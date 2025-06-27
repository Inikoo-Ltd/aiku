<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight, faImage } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)
defineProps<{
  data: {
    name: string
    description: string
    images: { source: string }[]
  }
}>()
</script>

<template>
  <div>
    <!-- Image Card with fixed 4:3 aspect ratio -->
    <div class="relative w-full bg-white rounded-2xl shadow-md overflow-hidden">
      <div class="relative aspect-[1/1] w-full">
        <template v-if="data?.web_images">
          <Image :src="data?.web_images?.main?.gallery" :alt="data?.name" class="absolute inset-0 w-full h-full object-cover" />
        </template>
        <template v-else>
          <div class="absolute inset-0 w-full h-full flex items-center justify-center bg-gray-100">
            <FontAwesomeIcon :icon="faImage" class="text-4xl text-gray-400" />
          </div>
        </template>

      </div>
    </div>

    <!-- Product Name -->
    <div class="mt-2 px-4 text-base font-medium text-gray-800 leading-tight text-center w-full">
      {{ data?.name }}
    </div>
  </div>
</template>
