<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
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
  faImage
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
}>()
</script>

<template>
  <!-- CARD -->
  <div class="w-full bg-[#E6E6E6] rounded-lg shadow-sm overflow-hidden flex flex-col h-[260px]">

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
    <div class="mt-3 h-[70px] px-3 flex items-center justify-center">
      <p class="text-center text-sm line-clamp-3 leading-tight">
        {{ data?.name }}
      </p>
    </div>

  </div>
</template>
