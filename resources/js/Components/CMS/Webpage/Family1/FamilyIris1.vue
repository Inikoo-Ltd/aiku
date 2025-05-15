<script setup lang="ts">
import { ref } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import Family1Render from './Family1Render.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
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


/* const maxDisplay = 10
const visibleFamilies = ref(props.fieldValue.family.slice(0, maxDisplay)) */

console.log(props)
</script>

<template>
  <div v-if="props.fieldValue.family && props.fieldValue.family.length" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 p-3">
    <div v-for="(family, index) in props.fieldValue.family" :key="index">
      <Family1Render :data="family" />
    </div>
  </div>
 <EmptyState v-else :data="{title:'Empty Families'}" />
</template>
