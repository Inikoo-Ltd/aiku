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
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
  meta: {
    meta_title: string,
    meta_description: string,
    image: string,
    structured_data: JSON
  },
  data: any,
  header: any,
  blocks: any,
}>()
defineOptions({ layout: LayoutIris })
library.add(faCheck, faPlus, faMinus)

const layout = inject('layout', {})
const isPreviewLoggedIn = ref(layout.iris.user_auth)
const screenType = ref<'mobile' | 'tablet' | 'desktop'>('desktop')

const showWebpage = (activityItem) => {
  if (activityItem?.web_block?.layout && activityItem.show) {
    if (isPreviewLoggedIn.value && activityItem.visibility.in) return true
    else return !isPreviewLoggedIn.value && activityItem.visibility.out;
  } else return false
}


const checkScreenType = () => {
  const width = window.innerWidth
  if (width < 640) screenType.value = 'mobile'
  else if (width >= 640 && width < 1024) screenType.value = 'tablet'
  else screenType.value = 'desktop'
}



onMounted(() => {
  const script = document.createElement('script')
  script.type = 'application/ld+json'
  script.textContent = JSON.stringify(props.meta.structured_data)
  document.head.appendChild(script)
  checkScreenType()
  window.addEventListener('resize', checkScreenType)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', checkScreenType)
})

</script>

<template>
  <Head>
    <title>{{ meta.meta_title }}</title>
    <meta name="description" :content="meta.meta_description">
  </Head>


 <!--  <div class="text-center text-sm text-gray-600 my-4">
  Current screen type: <strong>{{ screenType }}</strong>
</div> -->


  <div class="bg-white">
    <template v-if="props.blocks?.web_blocks?.length">
      <div v-for="(activityItem, activityItemIdx) in props.blocks.web_blocks" :key="'block' + activityItem.id"
        class="w-full">
        <component 
          v-if="showWebpage(activityItem)" 
          :screenType="screenType"
          :is="getIrisComponent(activityItem.type)"
          :theme="data.published_layout.theme" :key="activityItemIdx"
          :fieldValue="activityItem.web_block.layout.data.fieldValue" />
      </div>
    </template>

    <div v-else class="text-center text-2xl sm:text-4xl font-bold text-gray-400 mt-16 pb-20">
      {{ trans("This page have no data") }}
    </div>
  </div>
</template>
