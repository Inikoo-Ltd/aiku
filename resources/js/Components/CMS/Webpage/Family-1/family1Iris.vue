<script setup lang="ts">
import { ref, inject, computed } from 'vue'
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { trans } from 'laravel-vue-i18n'
/* import FamilyOfferLabelDiscount from '@/Components/Utils/Label/DiscountTemplate/CategoryQuantityOrderedOrderInterval/FamilyOfferLabelDiscount.vue'
import FamilyOfferLabelGR from '@/Components/Utils/Label/DiscountTemplate/CategoryQuantityOrderedOrderInterval/FamilyOfferLabelGR.vue' */
import DiscountByType from '@/Components/Utils/Label/DiscountByType.vue'
import { getBestOffer } from '@/Composables/useOffers'

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

const bestOffer = computed(() => {
  return getBestOffer(props.fieldValue?.family?.offers_data)
})

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
         
        <div class="mt-4">

        </div>
        <!-- Offer: list offers -->
        <div v-if="fieldValue?.family?.offers_data?.number_offers && layout.iris.is_logged_in"
            class="flex flex-col md:flex-row gap-x-4 gap-y-1 md:gap-y-2 mb-3 offers"
        >

            <!-- <template v-for="(offer, idOffer, offIdx) in fieldValue?.family?.offers_data.offers">
                <FamilyOfferLabelGR 
                  v-if="offer.type == 'Category Quantity Ordered Order Interval'" 
                />

                <FamilyOfferLabelDiscount
                    :offer="offer"
                />
            </template> -->

                <DiscountByType 
                   :offers_data="fieldValue?.family?.offers_data"
                   :template="bestOffer.type == 'Category Quantity Ordered Order Interval' ? 'active-inactive-gr'  : 'max_discount'"
                />

                <DiscountByType
                    v-if="!layout?.user?.gr_data?.customer_is_gr && bestOffer.type == 'Category Quantity Ordered Order Interval'"
                   :offers_data="fieldValue?.family?.offers_data"
                   :template="'triggers_labels'"
                />
            
        </div>

        <!-- Description Title (SEO: Heading) -->
        <h1 v-if="fieldValue.family.name" class="text-[1.5rem] leading-[2rem] font-semibold">
            {{ fieldValue.family.name }}
        </h1>

        <!-- Main Description -->
        <div
            v-if="fieldValue.family.description"
            id="description-family-1-iris"
            xstyle="{ marginTop: 0 }"
            v-html="fieldValue.family.description"
            class="mt-4 text-justify"
        />

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

<style scoped>
  html {
    scroll-behavior: smooth;
  }

    #description-family-1-iris h1 {
        font-size: 1.5rem;
        line-height: 2rem;
        font-weight: 600;
        margin-top: 1rem;
        margin-bottom: 1rem;
    }

    #description-family-1-iris p {
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }

.offers :deep(.offer-max-discount) {
  @apply bg-[#A80000] border border-red-900 text-gray-100 w-fit flex items-center
    rounded-sm px-1 py-0.5 text-xl
    sm:px-1.5 sm:py-1 sm:text-xl
    md:px-2 md:py-1;
}

</style>
