<script setup lang="ts">
import { ref, inject, computed } from 'vue'
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { trans } from 'laravel-vue-i18n'
import StickerLabel from '@/Components/Utils/Iris/StickerLabel.vue'
import FamilyOfferLabelDiscount from '@/Components/Utils/Iris/Family/FamilyOfferLabelDiscount.vue'
import FamilyOfferLabelGR from '@/Components/Utils/Iris/Family/FamilyOfferLabelGR.vue'

library.add(faCube, faLink, faInfoCircle, faStar, faCircle, faBadgePercent, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  fieldValue: {
    family: {  // WebBlockFamilyResource.
      name: string
      description_title?: string
      description?: string
      description_extra?: string
      images: { 
        png: string
        avif: string
        webp: string
        original: string
       }
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
//   const vol = props.fieldValue?.family?.offers_data.offers?.vol_gr
//   if (!vol?.volume || !vol?.discount) return null

//   return {
//     title: trans("Special Volume Deal"),
//     headline: trans(`Buy ${vol.volume} items, get ${vol.discount}% OFF`),
//     description: trans(`Perfect for bulk buyers. Save more when you purchase ${vol.volume} or more items.`),
//     cta: trans("Start Saving Now")
//   }
// })

const _popoverInfoCircle = ref<InstanceType<any> | null>(null)
const _popoverInfoGoldReward = ref<InstanceType<any> | null>(null)
</script>

<template>

  <div id="family-1-iris">

  

    <div :style="{...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), ...getStyles(fieldValue?.container?.properties), width : 'auto' }"  class="py-4 px-[10px] sm:px-[50px]"
      aria-label="Family Description Section">
      
        <!-- Section: listing Offers -->
        <div class="hidden">
          <pre><span class="bg-yellow-400">layout?.user?.gr_data?.customer_is_gr</span>: {{ layout?.user?.gr_data?.customer_is_gr }}</pre>
          <pre><span class="bg-yellow-400">offers_data</span>: {{ fieldValue?.family?.offers_data }}</pre>
        </div>
         
        <!-- Offer: list offers -->
        <div v-if="fieldValue?.family?.offers_data?.number_offers && layout.iris.is_logged_in"
            class="flex flex-col md:flex-row gap-x-4 mt-4 gap-y-1 md:gap-y-2 mb-3"
        >
            <template v-for="(offer, idOffer, offIdx) in fieldValue?.family?.offers_data.offers">
                <FamilyOfferLabelGR v-if="offer.type == 'Category Quantity Ordered Order Interval'" />

                <!-- <FamilyOfferLabelDiscount
                    v-if="offer.type == 'Category Quantity Ordered Order Interval'"
                    :offer="offer"
                /> -->
            </template>
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
