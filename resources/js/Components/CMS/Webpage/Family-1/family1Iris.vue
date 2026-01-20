<script setup lang="ts">
import { ref, inject, computed } from 'vue'
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import StickerLabel from '@/Components/Utils/Iris/StickerLabel.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { Popover } from 'primevue'

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
//   const vol = props.fieldValue?.family?.offers_data?.vol_gr
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
      
      <pre>{{ fieldValue?.family }}</pre>
        <!-- Section: listing Offers -->
        <div v-if="fieldValue?.family?.offers_data && layout.iris.is_logged_in"
            class="flex gap-x-4 mt-4 gap-y-2 mb-3"
        >
            <section
                class="relative flex justify-between items-center w-fit rounded-lg px-5 py-1 shadow-md text-white mb-2"
                :class="layout?.user?.gr_data?.customer_is_gr ? 'bg-[#ff862f]' : 'bg-gray-400/70'"
            >
                
                <!-- Content -->
                <div class="w-full relative flex items-center text-3xl">
                    {{ layout?.user?.gr_data?.customer_is_gr ? trans("Gold Reward Active") : trans("Gold Reward Inactive") }}
                </div>

                <span @click="_popoverInfoGoldReward?.toggle" @mouseenter="_popoverInfoGoldReward?.show" @mouseleave="_popoverInfoGoldReward?.hide" class="align-middle ml-2 opacity-80 hover:opacity-100 cursor-pointer">
                    <FontAwesomeIcon icon="fal fa-info-circle" class="align-middle" fixed-width aria-hidden="true" />
                </span>

                <!-- Popover: Question circle GR member -->
                <Popover ref="_popoverInfoGoldReward" :style="{width: '350px'}" class="py-1 px-2">
                    <div class="text-xs">
                        <p class="font-bold mb-4">{{ trans("Gold Reward Membership") }}</p>
                        <p class="inline-block mb-4 text-justify">
                            {{ trans("Place an order within 30 days of your last invoice and Gold Reward status applies automatically. This unlocks the best pricing across eligible ranges, without needing to bulk up every order.") }}.
                        </p>
                    </div>
                </Popover>
            </section>

            <template v-if="Object.keys(fieldValue?.family?.offers_data).length">
                <section
                    v-for="(offer, idOffer, offIdx) in fieldValue?.family?.offers_data"
                    class="relative flex justify-between w-fit overflow-hidden rounded-lg px-px py-px shadow-md mb-2 bg-[#ff862f]"
                    aria-label="Colorful Volume Promotion"
                >
                
                    <!-- Content -->
                    <div class="w-full relative flex items-center text-white font-bold px-7 text-4xl">
                        {{ offer.allowances[0].label }}
                    </div>
                    <div class="bg-white rounded-md px-2 py-1 flex items-center gap-x-4">
                        <div>
                            <div class="whitespace-nowrap capitalize">{{ offer.allowances[0].class }}</div>
                            <div class="text-xs whitespace-nowrap opacity-70">
                                {{ offer.triggers_labels?.join('/') }}
                            </div>
                        </div>
                        <span @click="_popoverInfoCircle?.[offIdx]?.toggle, console.log('hehehe', _popoverInfoCircle?.[offIdx])" @mouseenter="_popoverInfoCircle?.[offIdx]?.show" @mouseleave="_popoverInfoCircle?.[offIdx]?.hide" class="opacity-60 hover:opacity-100 cursor-pointer">
                            <FontAwesomeIcon icon="fal fa-info-circle" class="" fixed-width aria-hidden="true" />
                        </span>
                    </div>

                    <!-- Popover: Question circle discount -->
                    <Popover ref="_popoverInfoCircle" :style="{width: '300px'}" class="py-1 px-2">
                        <div class="text-xs">
                            <p class="font-bold mb-4">{{ trans("VOLUME DISCOUNT") }}</p>
                            <p class="inline-block mb-4 text-justify">
                                {{ trans("You don't need Gold Reward status to access the lower price") }}.
                            </p>
                            <p class="mb-4 text-justify">
                                {{ trans("Order the listed volume and the member price applies automatically at checkout") }}. {{ trans("The volume can be made up from the whole product family, not just the same item") }}.
                            </p>
                        </div>
                    </Popover>
                </section>
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
