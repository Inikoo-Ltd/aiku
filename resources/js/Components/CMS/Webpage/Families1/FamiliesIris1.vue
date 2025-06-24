<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import Family1Render from './Families1Render.vue'
import { getStyles } from "@/Composables/styles"
import { computed } from "vue"

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


// ✅ Komputasi jumlah kolom berdasarkan user input (fallback: desktop=4, tablet=4, mobile=2)
const responsiveGridClass = computed(() => {
  const perRow = props.fieldValue?.settings?.per_row ?? {}

  const columnCount = {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  }

  const count = columnCount[props.screenType] ?? 1
  return `grid-cols-${count}`
})

console.log('families',props)
</script>

<template>
  <div v-if="props.fieldValue?.families && props.fieldValue.families.length" class="px-4 py-10 mx-[30px]"
    :style="getStyles(fieldValue.container?.properties, screenType)">
    <h2 class="text-2xl font-bold mb-6">Browse By Product Lines:</h2>
    <div :class="['grid gap-8', responsiveGridClass]">
      <a v-for="(item, index) in props.fieldValue.families" :key="index" :href="`/${item.url}`">
        <Family1Render :data="item" />
      </a>
    </div>
  </div>

</template>
