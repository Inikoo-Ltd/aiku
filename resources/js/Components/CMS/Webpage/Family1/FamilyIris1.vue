<script setup lang="ts">
import { ref } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import Family1Render from './Family1Render.vue'
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


const maxDisplay = 10
const visibleFamilies = ref(props.fieldValue.family.slice(0, maxDisplay))


</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 p-3">
    <div v-for="(family, index) in visibleFamilies" :key="index">
      <Family1Render :data="family" />
    </div>
  </div>
</template>
