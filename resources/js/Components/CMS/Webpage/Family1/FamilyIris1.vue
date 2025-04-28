<script setup lang="ts">
import { ref } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight, faImage } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from '@/Components/Image.vue'

// Register all necessary icons
library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  fieldValue: {
    family: {
      name: string
      description: string
      images: { source: string }[] 
    }[] 
  }
  webpageData?: any
  blockData?: Object
}>()



// Limit families to first 10 and create image index tracker
const maxDisplay = 10
const visibleFamilies = ref(props.fieldValue.family.slice(0, maxDisplay))
const imageIndexes = ref(visibleFamilies.value.map(() => 0))

function nextImage(index: number) {
  const productImages = visibleFamilies.value[index].images
  imageIndexes.value[index] = (imageIndexes.value[index] + 1) % productImages.length
}

function prevImage(index: number) {
  const productImages = visibleFamilies.value[index].images
  imageIndexes.value[index] = (imageIndexes.value[index] - 1 + productImages.length) % productImages.length
}
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 p-3">
    <div
      v-for="(product, index) in visibleFamilies"
      :key="index"
      class="bg-white rounded-lg shadow-lg overflow-hidden transition-all duration-300 hover:scale-105 hover:shadow-xl"
    >
      <div class="relative w-full mb-3">
        <!-- Display image or fallback icon -->
        <Image
          v-if="product.images && product.images.length > 0"
          :src="product.images[imageIndexes[index]].source"
          alt="Product image"
          class="w-full h-48 object-cover"
        />
        <div v-else class="flex justify-center items-center bg-gray-100 w-full h-48">
          <FontAwesomeIcon :icon="faImage" class="w-8 h-8 text-gray-400" />
        </div>
        <!-- Navigation buttons -->
        <button
          @click="prevImage(index)"
          class="absolute top-1/2 left-2 transform -translate-y-1/2"
        >
          <FontAwesomeIcon :icon="faChevronCircleLeft" />
        </button>
        <button
          @click="nextImage(index)"
          class="absolute top-1/2 right-2 transform -translate-y-1/2"
        >
          <FontAwesomeIcon :icon="faChevronCircleRight" />
        </button>
      </div>

      <div class="px-4 py-2">
        <!-- Product name -->
        <div class="text-lg font-semibold text-gray-800">{{ product.name }}</div>
        <!-- Product description -->
        <p class="mt-2 text-sm text-gray-600 truncate">{{ product.description || 'No description available' }}</p>
      </div>
    </div>
  </div>
</template>
