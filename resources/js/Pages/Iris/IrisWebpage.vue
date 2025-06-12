<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 16:53:19 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref, onMounted, onBeforeUnmount } from 'vue'
import { faCheck, faPlus, faMinus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Head } from '@inertiajs/vue3'
import LayoutIris from '@/Layouts/Iris.vue'
import { getIrisComponent } from '@/Composables/getIrisComponents'


const props = defineProps<{
  meta: {
    meta_title: string,
    meta_description: string,
    image: string,
    structured_data: JSON
  },
  web_blocks: any,
}>()
defineOptions({ layout: LayoutIris })
library.add(faCheck, faPlus, faMinus)

const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')
const currentUrl = ref('')


const checkScreenType = () => {
  const width = window.innerWidth
  if (width < 640) screenType.value = 'mobile'
  else if (width >= 640 && width < 1024) screenType.value = 'tablet'
  else screenType.value = 'desktop'
}



onMounted(() => {
  // Inject structured data script
  currentUrl.value = window.location.href
  const script = document.createElement('script')
  script.type = 'application/ld+json'

  // Fix: stringify only if needed
  let structuredData = props.meta.structured_data
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

  checkScreenType()
  window.addEventListener('resize', checkScreenType)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', checkScreenType)
})

const layout: any = inject("layout", {});

console.log(props)
</script>

<template>
 <Head>
  <title>{{ meta.meta_title }}</title>
    <meta name="description" :content="meta.meta_description" />
    <meta property="og:type" content="website" />
    <meta property="og:title" :content="meta.meta_title" />
    <meta property="og:description" :content="meta.meta_description" />
    <meta property="og:url" :content="currentUrl" />
    <meta property="og:image" :content="meta?.image?.png" />
    <meta property="og:image:alt" :content="meta.meta_title" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:site_name" :content="meta.meta_title" />>
</Head>



  <div class="bg-white">
      <div v-for="(web_block_data, web_block_data_idx) in props.web_blocks" :key="'block' + web_block_data.id" class="w-full">
        <component
          :screenType="screenType"
          :is="getIrisComponent(web_block_data.type)"
          :theme="layout?.app?.theme" :key="web_block_data_idx"
          :fieldValue="web_block_data.web_block.layout.data.fieldValue" />
      </div>
  </div>
</template>
