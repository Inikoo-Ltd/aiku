<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faSpinnerThird } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight, faImage } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Components/Image.vue"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronCircleLeft,
  faChevronCircleRight,
  faImage,
  faSpinnerThird
)

defineProps<{
  data: {
    name: string
    description?: string
    web_images?: {
      main?: {
        gallery?: string
      }
    }
  }
  isLoading?: boolean
}>()
</script>

<template>
  <!-- CARD -->
  <div class="relative w-full bg-[#E6E6E6] rounded-lg shadow-sm overflow-hidden flex flex-col h-[260px]">

    <!-- IMAGE ALWAYS SQUARE -->
    <div class="w-full relative overflow-hidden bg-gray-100 aspect-square">

      <template v-if="data?.web_images?.main?.gallery">
        <Image
          :src="data.web_images.main.gallery"
          :alt="data?.name"
        />
      </template>

      <template v-else>
        <div class="absolute inset-0 flex items-center justify-center">
          <FontAwesomeIcon :icon="faImage" class="text-3xl text-gray-300" />
        </div>
      </template>

    </div>

    <!-- TITLE -->
    <div class="h-[70px] px-3 flex items-center justify-center">
      <h2 class="text-start !text-sm line-clamp-3 leading-tight">
        {{ data?.name }}
      </h2>
    </div>

    <!-- LOADING OVERLAY -->
    <div
      v-if="isLoading"
      class="absolute inset-0 bg-black/40 flex items-center justify-center z-10 pointer-events-none"
    >
      <FontAwesomeIcon :icon="faSpinnerThird" spin class="text-white text-3xl" fixed-width aria-hidden="true" />
    </div>

  </div>
</template>
