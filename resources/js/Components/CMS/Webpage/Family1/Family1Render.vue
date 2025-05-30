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
    <!-- Card Gambar -->
    <div class="relative w-full bg-white rounded-2xl shadow-md overflow-hidden">
      <div class="w-full h-64">
        <Image
          v-if="data.image"
          :src="data.image"
          alt="Product"
          class="w-full h-full object-cover"
        />
        <div
          v-else
          class="w-full h-full flex items-center justify-center bg-gray-100"
        >
          <FontAwesomeIcon :icon="faImage" class="w-6 h-6 text-gray-400" />
        </div>
      </div>
    </div>

    <!-- Nama Produk -->
    <div class="mt-2 px-4 text-base font-medium text-gray-800 leading-tight text-center w-full">
      {{ data.name }}
    </div>
  </div>
</template>
