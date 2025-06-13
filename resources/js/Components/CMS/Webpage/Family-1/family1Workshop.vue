<script setup lang="ts">
import { ref } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  modelValue: {
    family: {
      name: string
      description: string
      images: { source: string }[]
    }[]
  }
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

</script>

<template>
  <div class="p-4">
    <div v-if="modelValue.family.description" v-html="modelValue.family.description"></div>
    <div v-else  class="text-gray-500 italic">There is no desciption in this family</div>
  </div>
</template>
