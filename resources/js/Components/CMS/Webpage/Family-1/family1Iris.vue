<script setup lang="ts">
import { ref, inject, computed } from 'vue'
import { faCube, faLink } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

library.add(faCube, faLink, faStar, faCircle, faBadgePercent, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  fieldValue: {
    family: {
      name: string
      description_title?: string
      description?: string
      description_extra?: string
      images: { source: string }[]
      active_offers: {

      }[]
      offers_data?: {
        [key: string]: {
          state: string
          duration: string
          label: string
          allowances: {
            class: string  // 'discount'
            type: string   // 'percentage_off'
            label: string  // '5.0%'
          }[]
          note: string
        }
      }
    }
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

// const promoData = computed(() => {
//   const vol = props.fieldValue?.family?.offers_data?.vol_gr
//   if (!vol?.volume || !vol?.discount) return null

//   return {
//     title: trans("Special Volume Deal"),
//     headline: trans(`Buy ${vol.volume} items, get ${vol.discount}% OFF`),
//     description: trans(`Perfect for bulk buyers. Save more when you purchase ${vol.volume} or more items.`),
//     cta: trans("Start Saving Now")
//   }
// })
</script>

<template>

  <div id="family-1-iris">

  

    <div :style="{...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), ...getStyles(fieldValue?.container?.properties), width : 'auto' }"  class="py-4 px-[10px] sm:px-[50px]"
      aria-label="Family Description Section">
      
        <!-- Section: listing Offers -->
        <div v-if="fieldValue?.family?.offers_data && layout.iris.is_logged_in"
            class="grid gap-x-4 mt-4 gap-y-2"
            :class="fieldValue?.family?.offers_data?.length > 1 ? 'md:grid-cols-2' : ''"
        >
            <section v-for="offer in fieldValue?.family?.offers_data"
                class="relative xmx-[10px] sm:xmx-[50px] overflow-hidden rounded-md px-6 py-3 shadow-md mb-2"
                aria-label="Colorful Volume Promotion">
                <!-- Gradient Background -->
                <div class="absolute inset-0 bg-gradient-to-br from-pink-400 via-purple-400 to-purple-200 opacity-90"></div>
                <!-- Decorative Blurs -->
                <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-yellow-300 opacity-30 blur-3xl"></div>
                <div class="absolute -bottom-10 -left-10 h-40 w-40 rounded-full bg-cyan-300 opacity-30 blur-3xl"></div>
                <!-- Content -->
                <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 text-white">
                    <div>
                        <div class="">
                            <span v-for="(allowance, idx) in offer.allowances" class="text-xs">
                                <span v-if="idx != 0"> | </span>
                                {{ allowance.label }}
                            </span>
                        </div>
                        <p class="text-sm uppercase tracking-wide font-semibold xopacity-90 mb-1">
                            {{ offer.label }}
                        </p>
                        <p v-if="offer.note" class="text-xs mt-1 max-w-[520px] opacity-90">
                            {{ offer.note }}
                        </p>
                    </div>
                    <div>
                        <FontAwesomeIcon v-if="offer.allowances.some((v: { type: string }) => v.type === 'percentage_off')" v-tooltip="trans('Offer with percentage off')" icon="fas fa-badge-percent" class="text-2xl xopacity-60 align-middle" fixed-width aria-hidden="true" />
                        <!-- <FontAwesomeIcon v-if="offer.allowances.some((v: { type: string }) => v.type === 'percentage_off')" icon="fas fa-badge-dollar" class="text-2xl opacity-60" fixed-width aria-hidden="true" /> -->
                    </div>
                </div>
            </section>
        </div>

      <!-- Description Title (SEO: Heading) -->
      <h1 v-if="fieldValue.family.name" class="text-[1.5rem] leading-[2rem] font-semibold text-gray-800">
        {{ fieldValue.family.name }}
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

  

</template>

<style>
  html {
    scroll-behavior: smooth;
  }
</style>
