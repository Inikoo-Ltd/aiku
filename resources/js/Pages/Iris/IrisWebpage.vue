<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 16:53:19 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, onMounted, onBeforeUnmount, computed } from 'vue'
import { faCheck, faPlus, faMinus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Head } from '@inertiajs/vue3'
import LayoutIris from '@/Layouts/Iris.vue'
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { usePage } from '@inertiajs/vue3'
import ReviewByStore from "@/Components/CMS/Reviews/ReviewByStore.vue"

const props = defineProps<{
  webpage_data : any
  web_blocks: any,
  webpage_img : any,
  index_page: boolean,
  follow_link: boolean
}>()

defineOptions({ layout: LayoutIris })
library.add(faCheck, faPlus, faMinus)

const layout: any = inject("layout", {})
const review = ref(usePage().props?.iris?.website?.reviews_settings)
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')
const currentUrl = ref('')

const checkScreenType = () => {
  const width = window.innerWidth
  if (width < 640) screenType.value = 'mobile'
  else if (width >= 640 && width < 1024) screenType.value = 'tablet'
  else screenType.value = 'desktop'
}

const robotsContent = computed(() => {
  const index = props.index_page ? 'index' : 'noindex'
  const follow = props.follow_link ? 'follow' : 'nofollow'
  return `${index}, ${follow}`
})


onMounted(() => {
  currentUrl.value = window.location.href

  if(props?.webpage_data?.seo_data?.structured_data){
  const script = document.createElement('script')
  script.type = 'application/ld+json'
  let structuredData = props.webpage_data?.seo_data?.structured_data

  if (typeof structuredData !== 'string') {
    try {
      structuredData = JSON.stringify(structuredData)
    } catch (e) {
      console.error('Invalid structured data:', e)
      structuredData = ''
    }
  }

  
  script.textContent = structuredData
  document.head.appendChild(script)
}

  checkScreenType()
  window.addEventListener('resize', checkScreenType)
  window.listWebBlocks = props.web_blocks


  if(layout.iris.is_logged_in) layout.log_user()
})


onBeforeUnmount(() => {
  window.removeEventListener('resize', checkScreenType)
})


</script>

<template>
    <Head>
        <title>{{ webpage_data.title }}</title>
        <meta
          name="robots"
          :content="robotsContent"
        />
        <meta name="description" :content="webpage_data.description" />
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="webpage_data.title" />
        <meta property="og:description" :content="webpage_data.description" />
        <meta property="og:url" :content="currentUrl" />
        <meta property="og:image" :content="webpage_img.png" />
        <meta property="og:image:alt" :content="webpage_data.title" />
        <meta property="og:locale" content="en_US" />
        <meta property="og:site_name" :content="webpage_data.title" />
    </Head>


      <div class="bg-white">

      <!-- GLOBAL CONTAINER -->
      <div class="mx-auto w-full max-w-screen-3xl">

        <div
          v-for="(web_block_data, index) in props.web_blocks"
          :key="'block-' + web_block_data.id"
          class="w-full 2xl:mb-12"
          :id="`v-${web_block_data.type}-${index}`"
        >
          <component
            :screenType="screenType"
            :code="web_block_data.type"
            :is="getIrisComponent(web_block_data.type, { shop_type: layout.retina.type })"
            :theme="layout?.app?.theme"
            :key="web_block_data.id"
            :fieldValue="web_block_data.web_block.layout.data.fieldValue"
            :indexBlock="index"
          />
        </div>

        <!-- REVIEW -->
        <div
          v-if="(webpage_data.type == 'storefront' || webpage_data.model_type == 'ProductCategory') && (review?.enabled ?? true)"
          class="my-10 2xl:my-16"
        >
          <div class="">
            <ReviewByStore :code="'review-by-store'" />
          </div>
        </div>

      </div>

      </div>
</template>
