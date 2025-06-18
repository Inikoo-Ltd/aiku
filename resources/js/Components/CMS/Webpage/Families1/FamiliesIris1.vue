<script setup lang="ts">
import { ref } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import Family1Render from './Families1Render.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  fieldValue: {
    families: {
      name: string
      description: string
      images: { source: string }[]
    }[]
  }
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

console.log('family',props)
</script>

<template>
 <div
  v-if="props.fieldValue?.families && props.fieldValue?.families?.length"
  class="px-4 py-10 mx-[30px]"
  :style="getStyles(fieldValue.container?.properties, screenType)"
>
  <h2 class="text-2xl font-bold mb-6">Browse By Product Lines:</h2>
  <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-8">
    <a v-for="(item, index) in props.fieldValue.families" :key="index" :href="`/${item.url}`">
      <Family1Render :data="item" />
    </a>
  </div>
</div>


  <EmptyState v-else :data="{ title: 'Empty Families' }" />
</template>
