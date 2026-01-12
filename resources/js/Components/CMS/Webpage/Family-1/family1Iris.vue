<script setup lang="ts">
import { ref, inject, computed } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'

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
console.log('ssss',props)

const promoData = computed(() => {
  const vol = props.fieldValue?.family?.offers_data?.vol_gr
  if (!vol?.volume || !vol?.discount) return null

  return {
    title: trans("Special Volume Deal"),
    headline: trans(`Buy ${vol.volume} items, get ${vol.discount}% OFF`),
    description: trans(`Perfect for bulk buyers. Save more when you purchase ${vol.volume} or more items.`),
    cta: trans("Start Saving Now")
  }
})
</script>

<template>

  <div id="family-1">
    <div :style="{...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), ...getStyles(fieldValue?.container?.properties), width : 'auto' }"  class="py-4 px-[10px] sm:px-[50px]"
      aria-label="Family Description Section">

      <!-- Description Title (SEO: Heading) -->
      <h1 v-if="fieldValue.family.description_title" class="text-[1.5rem] leading-[2rem] font-semibold text-gray-800">
        {{ fieldValue.family.description_title }}
      </h1>

      <!-- Main Description -->
      <div v-if="fieldValue.family.description" :style="{ marginTop: 0 }" v-html="fieldValue.family.description"></div>

      <!-- Read More Extra Description -->
      <div v-if="fieldValue.family.description_extra" class="rounded-lg">
        <transition name="fade">
          <div v-if="showExtra" v-html="fieldValue.family.description_extra"></div>
        </transition>
        <button @click="toggleShowExtra"
          class="text-sm text-gray-800 font-semibold hover:underline focus:outline-none transition-colors py-4">
          {{ showExtra ? trans("Show Less") : trans("Read More") }}
        </button>
      </div>
    </div>
  </div>

   <section v-if="promoData && layout.iris.is_logged_in"  class="relative mx-[10px]  sm:mx-[50px] mb-6 overflow-hidden rounded-2xl p-6 shadow-md"
    aria-label="Colorful Volume Promotion">
    <!-- Gradient Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-pink-400 via-purple-400 to-indigo-400 opacity-90"></div>

    <!-- Decorative Blurs -->
    <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-yellow-300 opacity-30 blur-3xl"></div>
    <div class="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-cyan-300 opacity-30 blur-3xl"></div>

    <!-- Content -->
    <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-white">

      <div>
        <p class="text-xs uppercase tracking-wide font-semibold opacity-90 mb-1">
          {{ promoData.title }}
        </p>

        <h2 class="text-xl font-bold leading-tight">
          {{ promoData.headline }}
        </h2>

        <p class="text-sm mt-1 max-w-[520px] opacity-90">
          {{ promoData.description }}
        </p>
      </div>

      <a href="#list-products-ecom-iris" class="flex-shrink-0">
        <Button :label="promoData.cta">
        </Button>
      </a>

    </div>
  </section>

</template>

<style>
  html {
    scroll-behavior: smooth;
  }
</style>
