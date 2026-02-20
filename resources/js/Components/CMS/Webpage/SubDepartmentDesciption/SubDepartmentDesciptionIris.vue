<script setup lang="ts">
import { ref, inject, computed } from 'vue'
import { faCube, faLink, faInfoCircle } from "@fal"
import { faStar, faCircle, faBadgePercent } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { getStyles } from "@/Composables/styles"
import { trans } from 'laravel-vue-i18n'
import { getBestOffer } from '@/Composables/useOffers'

library.add(faCube, faLink, faInfoCircle, faStar, faCircle, faBadgePercent, faChevronCircleLeft, faChevronCircleRight)

const props = defineProps<{
  fieldValue: {
    sub_department: {  // WebBlocksub_departmentResource.
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
  return getBestOffer(props.fieldValue?.sub_department?.offers_data)
})

const _popoverInfoCircle = ref<InstanceType<any> | null>(null)
const _popoverInfoGoldReward = ref<InstanceType<any> | null>(null)

const cleanedDescription = computed(() => {
  const html = props.fieldValue.sub_department.description || ''

  // remove <h1>...</h1>
  return html.replace(/<h1[^>]*>.*?<\/h1>/gis, '')
})


</script>

<template>
  <div  :id="fieldValue?.id ? fieldValue?.id  : 'sub_department-1-iris'"  component="sub_department-1-iris" >
    <div :style="{...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType), ...getStyles(fieldValue?.container?.properties), width : 'auto' }"  class="py-4 px-[10px] sm:px-[50px]"
      aria-label="sub_department Description Section">
       

        <!-- Description Title (SEO: Heading) -->
        <h1 v-if="fieldValue.sub_department.name" class="text-[1.5rem] leading-[2rem] font-semibold">
            {{ fieldValue.sub_department.name }}
        </h1>

        <!-- Main Description -->
        <div
            v-if="cleanedDescription"
            id="description-sub_department-1-iris"
            xstyle="{ marginTop: 0 }"
            v-html="cleanedDescription"
            class="mt-6 text-justify"
        />

      <!-- Read More Extra Description -->
      <div v-if="fieldValue.sub_department.description_extra" class="rounded-lg">
        <transition name="fade">
          <div v-if="showExtra" v-html="fieldValue.sub_department.description_extra"></div>
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

    #description-sub_department-1-iris h1 {
        font-size: 1.5rem;
        line-height: 2rem;
        font-weight: 600;
        margin-top: 1rem;
        margin-bottom: 1rem;
    }

    #description-sub_department-1-iris p {
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
