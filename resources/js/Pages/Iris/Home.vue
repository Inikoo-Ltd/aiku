<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 16:53:19 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { inject, ref } from 'vue'
import { faCheck, faPlus, faMinus } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Head } from '@inertiajs/vue3'
import LayoutIris from '@/Layouts/Iris.vue'
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
  head: {
    title: string,
    description: string,
    keywords: string,
  },
  data: any,
  header: any,
  blocks: any,
}>()
defineOptions({ layout: LayoutIris })
library.add(faCheck, faPlus, faMinus)

const layout = inject('layout', {})
const isPreviewLoggedIn = ref(layout.iris.user_auth)

const showWebpage = (activityItem) => {
    if (activityItem?.web_block?.layout && activityItem.show) {
        if (isPreviewLoggedIn.value && activityItem.visibility.in) return true
        else return !isPreviewLoggedIn.value && activityItem.visibility.out;
    } else return false
}

</script>

<template>
  <Head>
    <title>{{head.title}}</title>
    <meta name="description" content="head.description">
    <meta name="keywords" content="head.keywords">
  </Head>

  <div class="bg-white">
    <template v-if="props.blocks?.web_blocks?.length">
      <div v-for="(activityItem, activityItemIdx) in props.blocks.web_blocks" :key="'block' + activityItem.id"
        class="w-full">
        <component
            v-if="showWebpage(activityItem)"
            :is="getIrisComponent(activityItem.type)"
            :theme="data.published_layout.theme"
            :key="activityItemIdx"
            :fieldValue="activityItem.web_block.layout.data.fieldValue" />
      </div>
    </template>

    <div v-else class="text-center text-2xl sm:text-4xl font-bold text-gray-400 mt-16 pb-20">
      {{ trans("This page have no data") }}
    </div>
  </div>
</template>

