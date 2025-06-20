<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import Image from "@/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { computed } from "vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCube, faLink, faImage } from "@fal"

library.add(faCube, faLink, faImage)

const props = defineProps<{
  fieldValue: {
    collections : Array<any>
    container : {
      properties : Object
    }
  }
  webpageData?: any
  blockData?: Object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()


const defaultCols = {
  mobile: 2,
  tablet: 3,
  desktop: 4,
};

const gridColsClass = computed(() => {
  const perRow = props.fieldValue?.settings?.per_row || {};
  const col = perRow[props.screenType] ?? defaultCols[props.screenType];
  return `grid-cols-${col}`;
});

</script>

<template>
  <div v-if="fieldValue?.collections?.length" class="mx-auto px-4 py-12"
    :style="getStyles(fieldValue.container?.properties, screenType)">
    <h2 class="text-2xl font-bold mb-6">Browse By Collections</h2>

    <div>
      <div :class="['grid gap-4', gridColsClass]">
        <a v-for="item in fieldValue.collections" :key="item.code" :href="`/${item.url}`"
          class="flex items-center gap-3 border rounded px-4 py-3 text-sm font-medium text-gray-800 bg-white hover:bg-gray-50 transition-all w-full">
          <div class="flex items-center justify-center w-5 h-5 shrink-0 text-xl ">
            <FontAwesomeIcon v-if="item.icon" :icon="item.icon" class="text-xl w-5 h-5" />
            <Image v-else :src="item.image" class="w-full h-full object-contain" />
          </div>
          <span class="flex-1 text-center">{{ item.name }}</span>
        </a>
      </div>
    </div>
  </div>
</template>