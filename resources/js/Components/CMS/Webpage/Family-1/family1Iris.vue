<script setup lang="ts">
import { ref, inject } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"

library.add(faCube, faLink, faStar, faCircle, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  fieldValue: {
    family: {
      name: string
      description_title?: string
      description?: string
      description_extra?: string
      images: { source: string }[]
    }[]
  }
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const showExtra = ref(false)
const toggleShowExtra = () => {
  showExtra.value = !showExtra.value
}

const layout: any = inject("layout", {})
</script>

<template>
  <div id="family-1">
    <div :style="{...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), width : 'auto' }" class="py-4 space-y-6"
      aria-label="Family Description Section">

      <!-- Description Title (SEO: Heading) -->
      <h2 v-if="fieldValue.family.description_title" class="text-[1.5rem] leading-[2rem] font-semibold text-gray-800">
        {{ fieldValue.family.description_title }}
      </h2>

      <!-- Main Description -->
      <div v-if="fieldValue.family.description" :style="{ marginTop: 0 }" v-html="fieldValue.family.description"></div>

      <!-- Read More Extra Description -->
      <div v-if="fieldValue.family.description_extra" class="rounded-lg">
        <transition name="fade">
          <div v-if="showExtra" v-html="fieldValue.family.description_extra"></div>
        </transition>
        <button @click="toggleShowExtra"
          class="text-sm text-gray-600 hover:underline focus:outline-none transition-colors">
          {{ showExtra ? 'Show Less' : 'Read More' }}
        </button>
      </div>

    </div>

  </div>

</template>
